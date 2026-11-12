<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\OauthState;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Owner-controlled Facebook Page OAuth. A successful connection is NOT
 * publication approval: an administrator must separately verify the app.
 */
class FacebookPageOAuthController extends Controller
{
    private function configuration(): array
    {
        $id = (string) config('aadi.meta.app_id', '');
        $secret = (string) config('aadi.meta.app_secret', '');
        $redirect = (string) config('aadi.meta.redirect_uri', '');
        $version = (string) config('aadi.meta.graph_version', '');
        if (!$id || !$secret || !preg_match('/^v[0-9]{1,2}\.[0-9]{1,2}$/D', $version) ||
            !filter_var($redirect, FILTER_VALIDATE_URL) ||
            parse_url($redirect, PHP_URL_SCHEME) !== 'https') {
            abort(503, 'Meta app configuration is not ready');
        }
        return [$id, $secret, $redirect, $version];
    }

    public function connect(Request $request, Brand $brand)
    {
        if (!$request->user()?->isAdmin() || !$request->user()->hasBrandAccess($brand)) abort(403);
        $data = $request->validate(['page_id' => ['required', 'regex:/^[1-9][0-9]{0,20}$/D']]);
        [$appId, , $redirect, $version] = $this->configuration();
        $state = bin2hex(random_bytes(32));
        OauthState::query()->create([
            'user_id' => $request->user()->id, 'brand_id' => $brand->id,
            'platform' => 'facebook', 'external_account_id' => $data['page_id'],
            'state_hash' => hash('sha256', $state),
            'expires_at' => now()->addMinutes(10),
        ]);
        return redirect()->away('https://www.facebook.com/' . $version . '/dialog/oauth?' . http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => 'pages_show_list,pages_read_engagement,pages_manage_posts',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986));
    }

    public function callback(Request $request)
    {
        if (!$request->user()?->isAdmin()) abort(403);
        $in = $request->validate([
            'state' => ['required', 'string', 'size:64'],
            'code' => ['required', 'string', 'max:4096'],
        ]);
        [$appId, $secret, $redirect, $version] = $this->configuration();

        // Consume before provider calls; an expired/replayed code cannot create
        // or reassign a Page account.
        $flow = DB::transaction(function () use ($in, $request): OauthState {
            $flow = OauthState::query()
                ->where('state_hash', hash('sha256', $in['state']))->lockForUpdate()->firstOrFail();
            if ($flow->platform !== 'facebook' || $flow->consumed_at ||
                $flow->expires_at->isPast() || (int) $flow->user_id !== (int) $request->user()->id) {
                abort(403, 'Facebook OAuth state expired or already used');
            }
            $flow->consumed_at = now();
            $flow->save();
            return $flow;
        });
        $response = Http::timeout(20)->get('https://graph.facebook.com/' . $version . '/oauth/access_token', [
            'client_id' => $appId,
            'client_secret' => $secret,
            'redirect_uri' => $redirect,
            'code' => $in['code'],
        ]);
        if (!$response->successful() || !is_string($response->json('access_token')) ||
            strlen($response->json('access_token')) < 10) {
            throw ValidationException::withMessages(['oauth' => 'Facebook code exchange failed']);
        }
        $userToken = $response->json('access_token');
        $granted = Http::timeout(20)->withToken($userToken)
            ->get('https://graph.facebook.com/' . $version . '/me/permissions');
        if (!$granted->successful() || !is_array($granted->json('data'))) {
            throw ValidationException::withMessages(['oauth' => 'Facebook permission status unavailable']);
        }
        $permissions = [];
        foreach ($granted->json('data') as $row) {
            if (is_array($row) && ($row['status'] ?? null) === 'granted' && is_string($row['permission'] ?? null)) {
                $permissions[] = $row['permission'];
            }
        }
        $required = ['pages_show_list', 'pages_read_engagement', 'pages_manage_posts'];
        if (array_diff($required, $permissions)) {
            throw ValidationException::withMessages(['oauth' => 'Required Facebook Page scopes were not granted']);
        }
        $pages = Http::timeout(20)->withToken($userToken)->get(
            'https://graph.facebook.com/' . $version . '/me/accounts',
            ['fields' => 'id,name,access_token,tasks', 'limit' => 100]
        );
        if (!$pages->successful() || !is_array($pages->json('data'))) {
            throw ValidationException::withMessages(['oauth' => 'Facebook Page access list unavailable']);
        }
        $page = null;
        foreach ($pages->json('data') as $row) {
            if (is_array($row) && (string) ($row['id'] ?? '') === $flow->external_account_id) {
                $page = $row;
                break;
            }
        }
        if (!$page || !is_string($page['access_token'] ?? null) ||
            strlen($page['access_token']) < 10) {
            throw ValidationException::withMessages(['oauth' => 'Requested Facebook Page not authorized; reconnect its Page administrator']);
        }
        DB::transaction(function () use ($flow, $page, $permissions): void {
            $existing = SocialAccount::query()->where('platform', 'facebook')
                ->where('external_account_id', $flow->external_account_id)->lockForUpdate()->first();
            if ($existing && (int) $existing->brand_id !== (int) $flow->brand_id) {
                abort(409, 'Facebook Page already belongs to another brand');
            }
            SocialAccount::query()->updateOrCreate([
                'platform' => 'facebook', 'external_account_id' => $flow->external_account_id,
            ], [
                'brand_id' => $flow->brand_id,
                'account_name' => mb_substr((string) ($page['name'] ?? 'Facebook Page'), 0, 200),
                'access_token_encrypted' => encrypt($page['access_token']),
                'refresh_token_encrypted' => null,
                'scopes' => array_values(array_unique($permissions)),
                'token_expires_at' => null, // Unknown; never invent a token lifetime.
                'connection_status' => 'connected',
                'api_access_approved' => false,
            ]);
        });
        return redirect('/brands/' . $flow->brand_id)->with('status',
            'Facebook Page connected. Verify Page permissions and approve the official app before publishing.');
    }
}
