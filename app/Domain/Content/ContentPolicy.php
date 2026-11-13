<?php

namespace App\Domain\Content;

use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\ResearchItem;
use Illuminate\Validation\ValidationException;

class ContentPolicy
{
    /** The model never verifies its own evidence. Every supplied claim must refer to already verified research. */
    public function inspect(Brand $brand, array $content): array
    {
        $errors=[];
        $body=trim((string)($content['body']??''));
        $platforms=$content['platform_variants']??[];
        if ($body==='' || mb_strlen($body)>12000) $errors[]='BODY_INVALID';
        if (!is_array($platforms)) $errors[]='VARIANTS_INVALID';
        $errors=array_merge($errors,app(ClaimEvidenceGate::class)->inspect($brand,$content));
        $config=$brand->brand_config??[];
        foreach (($config['prohibited_claims']??[]) as $phrase) {
            if (is_string($phrase)&&strlen($phrase)>2&&mb_stripos($body,$phrase)!==false) $errors[]='BRAND_PROHIBITED_CLAIM';
        }
        $risk=($brand->slug==='overseas-job-placement'||($content['risk_level']??'low')!=='low');
        if ($risk) $errors[]='HUMAN_REVIEW_REQUIRED';
        if ($brand->slug==='aadipro' && empty($config['verified_catalog'])) $errors[]='BRAND_CATALOG_MISSING';
        return array_values(array_unique($errors));
    }

    public function requireApproval(ContentItem $item, int $approverId): void
    {
        $errors=$this->inspect($item->brand, $item->toArray());
        // Human review is intentionally satisfied only by a recorded accountable approval.
        $errors=array_values(array_diff($errors,['HUMAN_REVIEW_REQUIRED']));
        if ($errors!==[]) throw ValidationException::withMessages(['content'=>implode(', ', $errors)]);
        if (!in_array($item->status,['draft','needs_review','approved'],true)) throw ValidationException::withMessages(['status'=>'Cannot approve this content status']);
        $item->status='approved'; $item->approved_by=$approverId; $item->approved_at=now(); $item->save();
    }

    public function requirePublishable(ContentItem $item): void
    {
        if ($item->status!=='approved'||!$item->approved_by||!$item->approved_at) throw ValidationException::withMessages(['content'=>'Approved human review required']);
        $errors=$this->inspect($item->brand,$item->toArray());
        $errors=array_values(array_diff($errors,['HUMAN_REVIEW_REQUIRED']));
        if ($errors!==[]) throw ValidationException::withMessages(['content'=>implode(', ',$errors)]);
    }
}
