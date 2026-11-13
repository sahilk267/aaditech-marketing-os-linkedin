<?php

namespace App\Domain\Content;

use App\Models\Brand;
use App\Models\BrandSource;
use App\Models\ContentReview;
use App\Models\ResearchItem;

/**
 * Claim-to-research reviewer attestation. Matching text is evidence of a quoted
 * excerpt, NOT proof that a paraphrase is semantically true. A human admin
 * must separately attest its meaning. No AI-created "verified" flag is trusted.
 */
class ClaimEvidenceGate
{
    public function inspect(Brand $brand, array $content): array
    {
        $claims = $content['claim_evidence'] ?? [];
        if (!is_array($claims) || count($claims) > 40) return ['EVIDENCE_INVALID'];

        $errors = [];
        $body = (string) ($content['body'] ?? '');
        $contentId = $content['id'] ?? null;
        if ($brand->slug === 'overseas-job-placement' && $claims === [] &&
            preg_match('/\b(?:salary|visa|work permits?|vacanc(?:y|ies)|employer|job openings?|placement guarantees?|sponsorship)\b/iu', $body)) {
            $errors[] = 'HIGH_RISK_CLAIM_EVIDENCE_REQUIRED';
        }

        if ($claims === []) return $errors;
        $reviews = is_int($contentId) && $contentId > 0
            ? ContentReview::query()->where('content_item_id', $contentId)
                ->where('review_type', 'claim_attestation')->orderByDesc('id')->get()
            : collect();
        $seen = [];

        foreach ($claims as $claim) {
            if (!is_array($claim) || !is_string($claim['claim'] ?? null) ||
                mb_strlen(trim($claim['claim'])) < 10 ||
                mb_strlen($claim['claim']) > 1000 ||
                !is_array($claim['research_item_ids'] ?? null) ||
                count($claim['research_item_ids']) === 0 ||
                count($claim['research_item_ids']) > 20) {
                $errors[] = 'CLAIM_UNSUPPORTED';
                continue;
            }
            $claimText = trim($claim['claim']);
            if (mb_stripos($body, $claimText) === false) {
                $errors[] = 'CLAIM_NOT_IN_CONTENT';
                continue;
            }
            $ids = $claim['research_item_ids'];
            if (count(array_filter($ids, fn ($v) => is_int($v) && $v > 0)) !== count($ids) ||
                count(array_unique($ids)) !== count($ids)) {
                $errors[] = 'CLAIM_EVIDENCE_INVALID';
                continue;
            }
            $claimHash = self::claimHash($claimText);
            $bodyHash = hash('sha256', $body);
            foreach ($ids as $id) {
                $key = $claimHash . ':' . $id;
                if (isset($seen[$key])) {
                    $errors[] = 'CLAIM_EVIDENCE_INVALID';
                    continue;
                }
                $seen[$key] = true;
                $research = ResearchItem::query()->where('brand_id', $brand->id)->find($id);
                $source = $research ? BrandSource::query()->where('brand_id', $brand->id)
                    ->find($research->source_id) : null;
                if (!$research || !$source || !$source->allowed ||
                    $research->verification_status !== 'verified' ||
                    !app(\App\Domain\Research\ResearchFreshnessGuard::class)->isCurrent($brand, $research) ||
                    ($brand->slug === 'overseas-job-placement' && $source->trust_tier !== 'A')) {
                    $errors[] = 'CLAIM_UNVERIFIED';
                    continue;
                }

                // The latest review for this exact claim/source controls.
                // A rejection or an outdated source/body cannot be bypassed
                // by an older approval.
                $review = $reviews->first(fn (ContentReview $candidate) =>
                    ($candidate->findings['claim_hash'] ?? null) === $claimHash &&
                    ($candidate->findings['research_item_id'] ?? null) === $id);
                if (!$review || !$review->reviewer_id) {
                    $errors[] = 'CLAIM_ATTESTATION_REQUIRED';
                    continue;
                }
                if ($review->result !== 'approved') {
                    $errors[] = 'CLAIM_REJECTED';
                    continue;
                }
                $finding = $review->findings ?? [];
                if (($finding['body_hash'] ?? null) !== $bodyHash ||
                    ($finding['source_hash'] ?? null) !== $research->content_hash ||
                    !is_string($finding['source_excerpt'] ?? null) ||
                    !self::excerptPresent($research, $finding['source_excerpt'])) {
                    $errors[] = 'CLAIM_ATTESTATION_STALE';
                }
            }
        }
        return array_values(array_unique($errors));
    }

    public static function claimHash(string $claim): string
    {
        return hash('sha256', trim($claim));
    }

    public static function excerptPresent(ResearchItem $research, string $excerpt): bool
    {
        $text = (string) ($research->summary ?? '');
        $facts = $research->extracted_facts ?? [];
        if (is_array($facts)) {
            $stack = [$facts];
            while ($stack !== []) {
                $part = array_pop($stack);
                foreach ($part as $value) {
                    if (is_string($value)) $text .= "\n" . $value;
                    elseif (is_array($value)) $stack[] = $value;
                }
                if (strlen($text) > 300000) break;
            }
        }
        $normalize = static fn (string $s): string =>
            trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
        $needle = $normalize($excerpt);
        return mb_strlen($needle) >= 15 && mb_strlen($needle) <= 300 &&
            mb_stripos($normalize($text), $needle) !== false;
    }
}
