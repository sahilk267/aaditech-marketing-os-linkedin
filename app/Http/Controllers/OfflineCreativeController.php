<?php

namespace App\Http\Controllers;

use App\Domain\Content\ContentPolicy;
use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\MediaAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Safe PHP-only SVG renderer: works without AI APIs, GD, FFmpeg or worker.
 * Rendering never publishes; approved content remains owner-controlled.
 */
class OfflineCreativeController extends Controller
{
    public function store(Request $request, Brand $brand, int $contentId, ContentPolicy $policy): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasBrandAccess($brand) ||
            !in_array($user->role, ['admin', 'editor'], true)) abort(403);
        $content = ContentItem::query()->where('brand_id', $brand->id)->findOrFail($contentId);
        $policy->requirePublishable($content);

        $colors = ($brand->brand_config ?? [])['colors'] ?? [];
        $primary = $this->color($colors['primary'] ?? null, '#17324D');
        $accent = $this->color($colors['accent'] ?? null, '#20C997');
        $title = $this->escape(mb_substr($content->title, 0, 72));
        $brandName = $this->escape(mb_substr($brand->name, 0, 60));
        $lines = $this->lines($content->body);
        $body = '';
        foreach ($lines as $i => $line) {
            $y = 320 + 52 * $i;
            $body .= '<text x="68" y="' . $y . '" font-size="31" fill="#FFFFFF"'
                . ' font-family="Arial, sans-serif">' . $this->escape($line) . '</text>';
        }
        $contentIdentifier = $content->id;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="627" viewBox="0 0 1200 627">'
            . '<rect width="1200" height="627" fill="' . $primary . '"/>'
            . '<rect x="65" y="55" width="11" height="70" fill="' . $accent . '"/>'
            . '<text x="100" y="100" font-size="34" font-weight="bold" fill="#FFFFFF" font-family="Arial, sans-serif">'
            . $brandName . '</text>'
            . '<text x="65" y="240" font-size="46" font-weight="bold" fill="#FFFFFF" font-family="Arial, sans-serif">'
            . $title . '</text>' . $body
            . '<text x="65" y="580" font-size="20" fill="#FFFFFF" font-family="Arial, sans-serif">'
            . 'Aadi Marketing OS | Content ' . $contentIdentifier . '</text></svg>';
        $checksum = hash('sha256', $svg);
        $path = 'brand-assets/' . $brand->id . '/' . $checksum . '.svg';

        $asset = DB::transaction(function () use ($brand, $content, $svg, $checksum, $path): MediaAsset {
            // One content row is a deterministic lock for repeat render requests.
            $current=ContentItem::query()->where('brand_id',$brand->id)
                ->whereKey($content->id)->lockForUpdate()->firstOrFail();
            $policy=app(ContentPolicy::class);
            $policy->requirePublishable($current);
            if ($current->body!==$content->body || $current->title!==$content->title ||
                $current->approved_at?->format('Y-m-d H:i:s')!==$content->approved_at?->format('Y-m-d H:i:s')) {
                abort(409,'Content changed while rendering; regenerate after approval');
            }
            if (!Storage::disk('local')->put($path, $svg)) abort(500, 'Unable to store offline SVG');
            $asset = MediaAsset::query()->firstOrCreate([
                'brand_id' => $brand->id, 'checksum' => $checksum, 'storage_path' => $path,
            ], [
                'content_item_id' => $content->id, 'asset_type' => 'svg',
                'mime_type' => 'image/svg+xml', 'file_size' => strlen($svg),
                'width' => 1200, 'height' => 627,
                'license_source' => 'owner_generated',
                'metadata' => ['renderer' => 'php_offline_svg_v1'],
            ]);
            // Reusing the exact same creative after a new human approval must
            // relink an old, detached asset rather than silently losing it.
            if (!$asset->wasRecentlyCreated) {
                if ($asset->content_item_id !== null &&
                    (int)$asset->content_item_id !== (int)$content->id) {
                    abort(409, 'Asset belongs to another content item');
                }
                if ($asset->content_item_id === null) {
                    $asset->content_item_id = $content->id;
                    $asset->save();
                }
            }
            return $asset;
        });
        return response()->json([
            'media_asset_id' => $asset->id,
            'type' => 'svg',
            'download_url' => '/brands/' . $brand->id . '/assets/' . $asset->id . '/download',
            'worker_required' => false, 'status' => 'ready',
        ], $asset->wasRecentlyCreated ? 201 : 200);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function color(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/D', $value) ? $value : $fallback;
    }

    private function lines(string $value): array
    {
        $words = preg_split('/\s+/u', trim($value)) ?: [];
        $lines = []; $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if (mb_strlen($candidate) > 52 && $line !== '') {
                $lines[] = mb_substr($line, 0, 52);
                $line = $word;
            } else $line = $candidate;
            if (count($lines) >= 3) break;
        }
        if ($line !== '' && count($lines) < 3) $lines[] = mb_substr($line, 0, 52);
        return array_slice($lines, 0, 3);
    }
}
