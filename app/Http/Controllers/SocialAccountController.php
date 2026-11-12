<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\SocialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialAccountController extends Controller
{
    public function index(Request $r,Brand $brand): JsonResponse
    {
        if (!$r->user()?->hasBrandAccess($brand)) abort(403);
        return response()->json(SocialAccount::query()->where('brand_id',$brand->id)->get([
            'id','platform','account_name','external_account_id','scopes','token_expires_at','connection_status','api_access_approved'
        ]));
    }
    public function approval(Request $r,Brand $brand,int $id): JsonResponse
    {
        if (!$r->user()?->isAdmin()||!$r->user()->hasBrandAccess($brand)) abort(403);
        $in=$r->validate([
            'approved'=>['required','boolean'],
            'verification_note'=>['required_if:approved,1','nullable','string','min:30','max:1500'],
        ]);
        $account=SocialAccount::query()->where('brand_id',$brand->id)->findOrFail($id);
        $allowedScopes = ($account->platform==='linkedin' &&
            in_array('w_organization_social',$account->scopes??[],true)) ||
            ($account->platform==='facebook' &&
             in_array('pages_manage_posts',$account->scopes??[],true) &&
             in_array('pages_show_list',$account->scopes??[],true) &&
             in_array('pages_read_engagement',$account->scopes??[],true));
        if ($in['approved'] && ($account->connection_status!=='connected'||!$allowedScopes)) {
            abort(422,'Official write approval and scope required');
        }
        // Admin can assert approval only after documented app verification/test. Audit the decision.
        $account->api_access_approved=$in['approved'];$account->save();
        \App\Models\AuditLog::query()->forceCreate([
            'user_id'=>$r->user()->id,'actor_type'=>'user','action'=>'social_account.approval_changed',
            'resource_type'=>'social_account','resource_id'=>$account->id,
            'metadata'=>['approved'=>(bool)$in['approved'],'platform'=>$account->platform,
                'verification_note'=>$in['verification_note']??null],
        ]);
        return response()->json(['id'=>$account->id,'api_access_approved'=>$account->api_access_approved]);
    }
}
