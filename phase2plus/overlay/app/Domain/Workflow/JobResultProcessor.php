<?php

namespace App\Domain\Workflow;

use App\Domain\AI\ModelCatalog;
use App\Models\BrandSource;
use App\Models\ContentItem;
use App\Models\MediaAsset;
use App\Models\ResearchItem;
use App\Models\WorkflowJob;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class JobResultProcessor
{
    public function accept(WorkflowJob $job, array $result): void
    {
        if ($job->status==='succeeded') return;
        match($job->job_type) {
            'research.fetch'=>$this->research($job,$result),
            'content.generate'=>$this->content($job,$result),
            'media.render'=>$this->media($job,$result),
            default=>null,
        };
    }

    private function research(WorkflowJob $job,array $result): void
    {
        $source=BrandSource::query()->where('brand_id',$job->brand_id)->findOrFail((int)($job->payload['source_id']??0));
        if (!$source->allowed) $this->reject('SOURCE_NOT_ALLOWED');
        $title=$result['title']??null; $facts=$result['facts']??null;
        if (!is_string($title)||strlen($title)>300||!is_array($facts)||count($facts)>30) $this->reject('RESEARCH_SCHEMA_INVALID');
        foreach ($facts as $fact) {
            if (!is_array($fact)||!is_string($fact['text']??null)||strlen($fact['text'])>1200) $this->reject('RESEARCH_FACT_INVALID');
        }
        $hash=hash('sha256',json_encode([$source->id,$title,$facts],JSON_THROW_ON_ERROR));
        ResearchItem::query()->firstOrCreate(['brand_id'=>$job->brand_id,'source_id'=>$source->id,'content_hash'=>$hash],[
            'title'=>$title,'summary'=>substr((string)($result['summary']??''),0,3000),
            'extracted_facts'=>$facts,'published_at'=>null,'fetched_at'=>now(),
            'freshness_status'=>'current','verification_status'=>'unverified',
        ]);
        $source->last_fetched_at=now();$source->last_status='fetched';$source->save();
    }

    private function content(WorkflowJob $job,array $result): void
    {
        $raw=$result['content']??null;
        if (!is_array($raw)) $this->reject('CONTENT_SCHEMA_INVALID');
        foreach (['title','topic','body','content_type','language'] as $key) {
            if (!is_string($raw[$key]??null) || trim($raw[$key])==='') $this->reject('CONTENT_REQUIRED_'.$key);
        }
        if (strlen($raw['body'])>12000||strlen($raw['title'])>255||strlen($raw['topic'])>255) $this->reject('CONTENT_TOO_LARGE');
        $hash=hash('sha256',mb_strtolower(preg_replace('/\s+/u',' ',trim($raw['body']))));
        ContentItem::query()->firstOrCreate(['brand_id'=>$job->brand_id,'content_hash'=>$hash],[
            'campaign_id'=>null,'title'=>$raw['title'],'topic'=>$raw['topic'],
            'content_type'=>$raw['content_type'],'language'=>$raw['language'],
            'hook'=>substr((string)($raw['hook']??''),0,2000), 'body'=>$raw['body'],
            'cta'=>substr((string)($raw['cta']??''),0,500),
            'hashtags'=>array_slice(is_array($raw['hashtags']??null)?$raw['hashtags']:[],0,20),
            'source_refs'=>array_slice(is_array($raw['source_refs']??null)?$raw['source_refs']:[],0,30),
            // No generated verification claims are granted 'verified' by ingestion.
            'claim_evidence'=>is_array($raw['claim_evidence']??null)?$raw['claim_evidence']:[],
            'platform_variants'=>is_array($raw['platform_variants']??null)?$raw['platform_variants']:[],
            'status'=>'needs_review','ai_provider'=>$result['provider']??'unknown',
            'ai_model'=>substr((string)($result['model']??''),0,160),
            'prompt_version'=>'v1','risk_level'=>in_array($raw['risk_level']??'low',['low','medium','high'],true)?$raw['risk_level']:'high',
        ]);
    }

    private function media(WorkflowJob $job,array $result): void
    {
        $b64=$result['svg_base64']??null;
        if (!is_string($b64)||strlen($b64)>2_000_000) $this->reject('MEDIA_LIMIT');
        $svg=base64_decode($b64,true);
        if ($svg===false||strlen($svg)>1_000_000||!str_starts_with(ltrim($svg),'<svg')||
            preg_match('/<script|<foreignObject|<image|<use|onload\s*=|href\s*=|url\s*\(/i',$svg)) $this->reject('MEDIA_UNSAFE_SVG');
        $contentId=(int)($job->payload['content_item_id']??0);
        $content=ContentItem::query()->where('brand_id',$job->brand_id)->findOrFail($contentId);
        if (stripos($svg,'<!DOCTYPE')!==false || stripos($svg,'<!ENTITY')!==false || stripos($svg,'<?')!==false) $this->reject('MEDIA_INVALID_XML');
        $old=libxml_use_internal_errors(true);
        try {
            $dom=new \DOMDocument();
            if (!$dom->loadXML($svg, LIBXML_NONET) || !$dom->documentElement || $dom->documentElement->localName!=='svg') $this->reject('MEDIA_INVALID_XML');
            foreach ($dom->getElementsByTagName('*') as $node) {
                if (!in_array($node->localName,['svg','rect','text'],true)) $this->reject('MEDIA_INVALID_ELEMENT');
                foreach ($node->attributes as $attr) {
                    if (!in_array($attr->name,['xmlns','width','height','viewBox','x','y','fill','font-size','font-weight','font-family'],true)) $this->reject('MEDIA_INVALID_ATTRIBUTE');
                }
            }
        } finally { libxml_clear_errors();libxml_use_internal_errors($old); }
        $hash=hash('sha256',$svg); $path='brand-assets/'.$job->brand_id.'/'.$hash.'.svg';
        Storage::disk('local')->put($path,$svg);
        MediaAsset::query()->firstOrCreate(['brand_id'=>$job->brand_id,'checksum'=>$hash,'storage_path'=>$path],[
            'content_item_id'=>$content->id,'asset_type'=>'svg','mime_type'=>'image/svg+xml',
            'file_size'=>strlen($svg),'width'=>1200,'height'=>627,'license_source'=>'owner_generated',
            'metadata'=>['renderer'=>'svg_template_v1'],
        ]);
    }
    private function reject(string $message): never { throw ValidationException::withMessages(['result'=>$message]); }
}
