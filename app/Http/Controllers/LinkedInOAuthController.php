<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\OauthState;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LinkedInOAuthController extends Controller
{
    public function connect(Request $r, Brand $brand)
    {
        if (!$r->user()?->isAdmin()||!$r->user()->hasBrandAccess($brand)) abort(403);
        $in=$r->validate(['organization_id'=>['required','regex:/^[1-9][0-9]{0,18}$/D']]);
        $client=(string)config('aadi.linkedin.client_id','');
        $redirect=(string)config('aadi.linkedin.redirect_uri','');
        if (!$client||!filter_var($redirect,FILTER_VALIDATE_URL)||parse_url($redirect,PHP_URL_SCHEME)!=='https') abort(503,'LinkedIn configuration missing');
        $state=bin2hex(random_bytes(32));
        OauthState::query()->create([
            'user_id'=>$r->user()->id,'brand_id'=>$brand->id,'platform'=>'linkedin',
            'external_account_id'=>'urn:li:organization:'.$in['organization_id'],
            'state_hash'=>hash('sha256',$state),'expires_at'=>now()->addMinutes(10),
        ]);
        return redirect()->away('https://www.linkedin.com/oauth/v2/authorization?'.http_build_query([
            'response_type'=>'code','client_id'=>$client,'redirect_uri'=>$redirect,
            'state'=>$state,'scope'=>'w_organization_social r_organization_social',
        ],'','&',PHP_QUERY_RFC3986));
    }

    public function callback(Request $r)
    {
        if (!$r->user()?->isAdmin()) abort(403);
        $data=$r->validate(['state'=>['required','string','size:64'], 'code'=>['required','string','max:4096']]);
        $flow=DB::transaction(function() use ($data,$r): OauthState {
            $flow=OauthState::query()->where('state_hash',hash('sha256',$data['state']))->lockForUpdate()->firstOrFail();
            if ($flow->consumed_at || $flow->expires_at->isPast() || (int)$flow->user_id!==$r->user()->id) abort(403,'OAuth flow expired/invalid');
            $flow->consumed_at=now();$flow->save();return $flow;
        });
        $secret=(string)config('aadi.linkedin.client_secret','');
        $client=(string)config('aadi.linkedin.client_id','');
        $redirect=(string)config('aadi.linkedin.redirect_uri','');
        if (!$secret||!$client||!$redirect) abort(503,'LinkedIn credentials not configured');
        $response=Http::asForm()->timeout(20)->post('https://www.linkedin.com/oauth/v2/accessToken',[
            'grant_type'=>'authorization_code','code'=>$data['code'],
            'redirect_uri'=>$redirect,'client_id'=>$client,'client_secret'=>$secret,
        ]);
        if (!$response->successful()) throw ValidationException::withMessages(['oauth'=>'LinkedIn token exchange failed']);
        $token=$response->json('access_token');
        if (!is_string($token)||strlen($token)<10) throw ValidationException::withMessages(['oauth'=>'Missing access token']);
        $scope=$response->json('scope','');
        // Permissions are not inferred from a successful authorization redirect.
        // The OAuth code exchange succeeded for requested scopes; page role is still unverified.
        $scopes=is_string($scope) && trim($scope)!=='' ? array_values(array_unique(preg_split('/[ ,]+/',trim($scope)))) : [];
        // An omitted provider scope response does not prove a grant. Fail closed.
        $scopeConfirmed=in_array('w_organization_social',$scopes,true);
        $account=SocialAccount::query()->updateOrCreate([
            'platform'=>'linkedin','external_account_id'=>$flow->external_account_id,
        ],[
            'brand_id'=>$flow->brand_id,'account_name'=>$flow->external_account_id,
            'access_token_encrypted'=>encrypt($token),
            'refresh_token_encrypted'=>null,'scopes'=>$scopes,
            'token_expires_at'=>now()->addSeconds(max(0,(int)$response->json('expires_in',0))),
            'connection_status'=>$scopeConfirmed ? 'connected' : 'reauthorization_required', 'api_access_approved'=>false,
        ]);
        return redirect('/brands/'.$flow->brand_id)->with('status', $scopeConfirmed
            ? 'LinkedIn token received; organization permissions still require verification. Account '.$account->id
            : 'LinkedIn scopes were not confirmed. Reauthorize with approved organization scopes before publishing.');
    }
}
