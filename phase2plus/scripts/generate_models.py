from pathlib import Path
root=Path(__file__).resolve().parents[1]/'overlay/app/Models'
models={
 'WorkflowJob':('workflow_jobs', {'payload':'array','result':'array','scheduled_at':'datetime','started_at':'datetime','completed_at':'datetime','lease_expires_at':'datetime'}, ['brand_id','job_type','payload','status','priority','scheduled_at','started_at','completed_at','lease_owner','lease_expires_at','attempt_count','max_attempts','idempotency_key','last_error','result']),
 'WorkflowEvent':('workflow_events', {'payload':'array','occurred_at':'datetime','processed_at':'datetime'}, ['event_type','aggregate_type','aggregate_id','payload','occurred_at','processed_at','status']),
 'BrandSource':('brand_sources',{'allowed':'boolean','robots_checked_at':'datetime','last_fetched_at':'datetime'}, ['brand_id','source_url','canonical_hash','source_type','trust_tier','allowed','robots_checked_at','last_fetched_at','last_status']),
 'ResearchItem':('research_items',{'extracted_facts':'array','published_at':'datetime','fetched_at':'datetime'},['brand_id','source_id','title','summary','extracted_facts','published_at','fetched_at','content_hash','freshness_status','verification_status']),
 'Campaign':('campaigns',{'content_pillars':'array','start_date':'date','end_date':'date'},['brand_id','name','objective','audience','content_pillars','start_date','end_date','status']),
 'ContentItem':('content_items',{'hashtags':'array','source_refs':'array','claim_evidence':'array','platform_variants':'array','approved_at':'datetime'},['brand_id','campaign_id','title','topic','content_type','language','hook','body','cta','hashtags','source_refs','claim_evidence','platform_variants','status','quality_score','content_hash','ai_provider','ai_model','prompt_version','risk_level','approved_by','approved_at']),
 'ContentReview':('content_reviews',{'findings':'array','reviewed_at':'datetime'},['content_item_id','review_type','result','findings','reviewer_id','reviewed_at']),
 'MediaAsset':('media_assets',{'metadata':'array'},['brand_id','content_item_id','asset_type','storage_path','mime_type','file_size','width','height','duration_seconds','license_source','checksum','metadata']),
 'SocialAccount':('social_accounts',{'scopes':'array','token_expires_at':'datetime','last_health_check_at':'datetime'},['brand_id','platform','account_name','external_account_id','access_token_encrypted','refresh_token_encrypted','scopes','token_expires_at','connection_status','last_health_check_at']),
 'PublishingJob':('publishing_jobs',{'scheduled_at':'datetime','published_at':'datetime','request_metadata':'array','response_metadata':'array'},['brand_id','content_item_id','social_account_id','platform','scheduled_at','status','idempotency_key','attempt_count','platform_post_id','permalink','published_at','last_error','request_metadata','response_metadata']),
 'OauthState':('oauth_states',{'expires_at':'datetime','consumed_at':'datetime'},['user_id','brand_id','platform','state_hash','expires_at','consumed_at']),
 'AiModel':('ai_models',{'capabilities':'array','last_verified_at':'datetime','is_free':'boolean','is_enabled':'boolean','input_price':'decimal:10','output_price':'decimal:10'},['provider','model_id','is_free','input_price','output_price','capabilities','context_limit','health_status','is_enabled','priority','last_verified_at']),
 'AiUsage':('ai_usage',{'estimated_cost':'decimal:10'},['provider','model_id','task_type','request_count','input_tokens','output_tokens','estimated_cost','status']),
 'AnalyticsSnapshot':('analytics_snapshots',{'captured_at':'datetime','metric_value':'decimal:6'},['publishing_job_id','platform','metric_name','metric_value','metric_definition','captured_at','source']),
 'NotificationLog':('notification_logs',{'sent_at':'datetime'},['channel','event_type','destination_masked','status','sent_at','error'])
}
for name,(table,casts,fields) in models.items():
   fields_s=', '.join(repr(f) for f in fields).replace("'", "'")
   casts_s=', '.join(f"'{k}' => '{v}'" for k,v in casts.items())
   extra=""
   if name=='SocialAccount':
      extra="\n    protected $hidden = ['access_token_encrypted','refresh_token_encrypted'];\n    public function accessToken(): ?string { return $this->access_token_encrypted ? decrypt($this->access_token_encrypted) : null; }\n"
   if name=='ContentItem':
      extra="\n    public function brand(): \\Illuminate\\Database\\Eloquent\\Relations\\BelongsTo { return $this->belongsTo(Brand::class); }\n"
   text=f'''<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;

class {name} extends Model
{{
    protected $table = '{table}';
    protected $fillable = [{fields_s}];
    protected function casts(): array {{ return [{casts_s}]; }}
    {extra}
}}
'''
   (root/(name+'.php')).write_text(text)
print('Generated',len(models),'Eloquent model files')
