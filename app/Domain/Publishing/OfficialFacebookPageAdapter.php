<?php

namespace App\Domain\Publishing;

use App\Models\ContentItem;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/** Official Meta Graph API — authorized Facebook Page TEXT posts only. */
class OfficialFacebookPageAdapter implements PlatformAdapter
{
    public function publish(ContentItem $content, SocialAccount $account): array
    {
        if ($account->platform !== 'facebook' || $account->connection_status !== 'connected' ||
            !$account->api_access_approved) $this->deny('Facebook Page authorization/approval missing');
        $scopes = $account->scopes ?? [];
        if (!in_array('pages_manage_posts', $scopes, true) ||
            !in_array('pages_show_list', $scopes, true) ||
            !in_array('pages_read_engagement', $scopes, true)) {
            $this->deny('Facebook Page publishing scopes not verified');
        }
        if ($account->token_expires_at && $account->token_expires_at->lte(now()->addMinutes(5))) {
            $this->deny('Facebook Page access token expired');
        }
        if (!preg_match('/^[1-9][0-9]{0,20}$/D', $account->external_account_id)) {
            $this->deny('Invalid Facebook Page identifier');
        }
        $version = (string) config('aadi.meta.graph_version', '');
        if (!preg_match('/^v[0-9]{1,2}\.[0-9]{1,2}$/D', $version)) {
            $this->deny('Configure supported Meta Graph API version');
        }
        if (!in_array($content->content_type, ['text', 'educational_post'], true)) {
            $this->deny('Only text Facebook Page posts enabled; use manual_ready for media');
        }
        $variant = ($content->platform_variants ?? [])['facebook'] ?? [];
        $body = is_array($variant) ? ($variant['body'] ?? $content->body) : $content->body;
        if (!is_string($body) || trim($body) === '' || mb_strlen($body) > 60000) {
            $this->deny('Facebook Page message invalid');
        }
        $token = $account->accessToken();
        if (!$token) $this->deny('Facebook Page token missing');

        // Provider may publish before an HTTP timeout; calling service handles
        // uncertain outcomes by reconciling instead of blindly retrying.
        $response = Http::timeout(25)->withToken($token)->asForm()
            ->post('https://graph.facebook.com/' . $version . '/' . $account->external_account_id . '/feed', [
                'message' => $body,
                'published' => 'true',
            ]);
        if (!$response->successful()) {
            throw new \RuntimeException('FACEBOOK_HTTP_' . $response->status());
        }
        $id = $response->json('id');
        if (!is_string($id) || !preg_match('/^[0-9]+(?:_[0-9]+)?$/D', $id)) {
            throw new \RuntimeException('FACEBOOK_ID_UNCONFIRMED');
        }
        return ['platform_post_id' => $id, 'permalink' => null, 'http_status' => $response->status()];
    }

    private function deny(string $error): never
    {
        throw ValidationException::withMessages(['facebook' => $error]);
    }
}
