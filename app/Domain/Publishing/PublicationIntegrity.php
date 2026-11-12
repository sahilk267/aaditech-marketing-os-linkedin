<?php

namespace App\Domain\Publishing;

use App\Models\ContentItem;
use App\Models\MediaAsset;

/** Seal the exact approved content and linked media used to schedule a post. */
class PublicationIntegrity
{
    public function fingerprint(ContentItem $item, string $platform): string
    {
        $assetChecksums = MediaAsset::query()
            ->where('brand_id', $item->brand_id)
            ->where('content_item_id', $item->id)
            ->whereIn('asset_type', ['png', 'carousel', 'svg'])
            ->orderBy('checksum')
            ->pluck('checksum')->all();

        $snapshot = [
            'schema' => 'publication-v1',
            'brand_id' => (int) $item->brand_id,
            'content_id' => (int) $item->id,
            'platform' => $platform,
            'title' => $item->title,
            'body' => $item->body,
            'hook' => $item->hook,
            'cta' => $item->cta,
            'content_type' => $item->content_type,
            'language' => $item->language,
            'hashtags' => $item->hashtags ?? [],
            'source_refs' => $item->source_refs ?? [],
            'claim_evidence' => $item->claim_evidence ?? [],
            'platform_variants' => $item->platform_variants ?? [],
            'risk_level' => $item->risk_level,
            'status' => $item->status,
            'approved_by' => $item->approved_by === null ? null : (int) $item->approved_by,
            'approved_at' => $item->approved_at?->toISOString(),
            'media_sha256' => $assetChecksums,
        ];

        return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
