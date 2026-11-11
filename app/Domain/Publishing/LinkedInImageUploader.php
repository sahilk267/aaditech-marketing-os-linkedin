<?php

namespace App\Domain\Publishing;

use App\Models\ContentItem;
use App\Models\MediaAsset;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Official LinkedIn Images API. Never fetch or redirect to provider-selected arbitrary URLs. */
class LinkedInImageUploader
{
    public function upload(ContentItem $item,SocialAccount $account,string $token,string $version):string
    {
        $asset=MediaAsset::query()->where('brand_id',$item->brand_id)
            ->where('content_item_id',$item->id)->where('asset_type','png')
            ->where('mime_type','image/png')->latest('id')->first();
        if (!$asset) $this->deny('LINKEDIN_IMAGE_ASSET_REQUIRED');
        $prefix='brand-assets/'.$item->brand_id.'/';
        if (!str_starts_with((string)$asset->storage_path,$prefix) ||
            str_contains((string)$asset->storage_path,'..') ||
            !Storage::disk('local')->exists($asset->storage_path)) $this->deny('LINKEDIN_IMAGE_MISSING');
        $bytes=Storage::disk('local')->get($asset->storage_path);
        $details=@getimagesizefromstring($bytes);
        if (strlen($bytes)<32 || strlen($bytes)>10*1024*1024 ||
            !str_starts_with($bytes,"\x89PNG\r\n\x1a\n") || $details===false ||
            $details[2]!==IMAGETYPE_PNG ||
            $asset->checksum!==hash('sha256',$bytes)) $this->deny('LINKEDIN_IMAGE_INTEGRITY_FAILED');
        $headers=['Linkedin-Version'=>$version,'X-Restli-Protocol-Version'=>'2.0.0'];
        $init=Http::timeout(25)->withToken($token)->withHeaders($headers)
            ->post('https://api.linkedin.com/rest/images?action=initializeUpload',[
                'initializeUploadRequest'=>['owner'=>$account->external_account_id],
            ]);
        if (!$init->successful()) throw new \RuntimeException('LINKEDIN_IMAGE_INIT_HTTP_'.$init->status());
        $uploadUrl=$init->json('value.uploadUrl');
        $urn=$init->json('value.image');
        if (!is_string($urn)||!preg_match('/^urn:li:image:[A-Za-z0-9_-]{1,128}$/D',$urn)) $this->deny('LINKEDIN_IMAGE_URN_INVALID');
        if (!is_string($uploadUrl)||!self::isApprovedUploadUrl($uploadUrl)) $this->deny('LINKEDIN_UPLOAD_URL_REJECTED');
        $response=Http::timeout(60)->withOptions(['allow_redirects'=>false])
            ->withToken($token)->withBody($bytes,'image/png')->put($uploadUrl);
        if (!$response->successful()) throw new \RuntimeException('LINKEDIN_IMAGE_UPLOAD_HTTP_'.$response->status());
        // The modern Images API is asynchronous; do not post an unusable asset.
        $status=Http::timeout(25)->withToken($token)->withHeaders($headers)
            ->get('https://api.linkedin.com/rest/images/'.rawurlencode($urn));
        if (!$status->successful() || $status->json('status')!=='AVAILABLE') {
            throw new \RuntimeException('LINKEDIN_IMAGE_NOT_AVAILABLE');
        }
        return $urn;
    }

    public static function isApprovedUploadUrl(string $url):bool
    {
        $parts=parse_url($url);
        if (!is_array($parts)) return false;
        return ($parts['scheme']??'')==='https' && ($parts['host']??'')==='www.linkedin.com'
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['fragment'])
            && (!isset($parts['port']) || $parts['port']===443)
            && str_starts_with((string)($parts['path']??''),'/dms-uploads/');
    }

    private function deny(string $code):never
    {
        throw ValidationException::withMessages(['linkedin'=>$code]);
    }
}
