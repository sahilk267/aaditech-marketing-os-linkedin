<?php

namespace App\Domain\Publishing;

use App\Models\ContentItem;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/** LinkedIn Marketing Posts API: authorized organization text and single-image posts. */
class OfficialLinkedInAdapter implements PlatformAdapter
{
    public function __construct(private LinkedInImageUploader $imageUploader) { }

    public function publish(ContentItem $item, SocialAccount $account): array
    {
        if ($account->platform!=='linkedin'||$account->connection_status!=='connected'||!$account->api_access_approved) $this->deny('LinkedIn approval/connection required');
        if (!in_array('w_organization_social',$account->scopes??[],true)) $this->deny('LinkedIn organization write scope missing');
        if ($account->token_expires_at && $account->token_expires_at->lte(now()->addMinutes(5))) $this->deny('LinkedIn OAuth token expired');
        if (!preg_match('/^urn:li:organization:[1-9][0-9]{0,18}$/D',$account->external_account_id)) $this->deny('Invalid organization URN');
        $version=(string)config('aadi.linkedin.version','');
        if (!preg_match('/^20[0-9]{4}$/D',$version)) $this->deny('Configure supported Linkedin-Version YYYYMM');
        $variant=($item->platform_variants??[])['linkedin']??[];
        $commentary=is_array($variant)?($variant['body']??$item->body):$item->body;
        if (!is_string($commentary)||trim($commentary)===''||mb_strlen($commentary)>3000) $this->deny('LinkedIn text body invalid');
        $token=$account->accessToken();
        if (!$token) $this->deny('LinkedIn token unavailable');
        $payload=[
            'author'=>$account->external_account_id,'commentary'=>$commentary,
            'visibility'=>'PUBLIC', 'distribution'=>[
                'feedDistribution'=>'MAIN_FEED','targetEntities'=>[],
                'thirdPartyDistributionChannels'=>[],
            ],
            'lifecycleState'=>'PUBLISHED','isReshareDisabledByAuthor'=>false,
        ];
        if (in_array($item->content_type,['image_post','visual_post'],true)) {
            $imageUrn=$this->imageUploader->upload($item,$account,$token,$version);
            $payload['content']=['media'=>[
                'id'=>$imageUrn,'altText'=>mb_substr(strip_tags($item->title),0,120),
            ]];
        }
        // A timeout after submission is never considered safe to retry blindly.
        $response=Http::timeout(25)->withToken($token)->withHeaders([
            'Linkedin-Version'=>$version,'X-Restli-Protocol-Version'=>'2.0.0',
            'Content-Type'=>'application/json',
        ])->post('https://api.linkedin.com/rest/posts',$payload);
        if (!$response->successful()) {
            // Do not log full provider body; it may contain PII or opaque tokens.
            throw new \RuntimeException('LINKEDIN_HTTP_'.$response->status());
        }
        $postId=$response->header('x-restli-id');
        if (!is_string($postId)||trim($postId)==='') throw new \RuntimeException('LINKEDIN_ID_UNCONFIRMED');
        return ['platform_post_id'=>$postId,'permalink'=>null,'http_status'=>$response->status()];
    }
    private function deny(string $error): never { throw ValidationException::withMessages(['linkedin'=>$error]); }
}
