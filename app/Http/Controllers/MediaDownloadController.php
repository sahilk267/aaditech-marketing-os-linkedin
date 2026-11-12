<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaDownloadController extends Controller
{
    public function __invoke(Request $request, Brand $brand, int $id)
    {
        if (!$request->user()?->hasBrandAccess($brand)) abort(403);

        $asset = MediaAsset::query()->where('brand_id', $brand->id)->findOrFail($id);
        $formats = [
            'svg' => ['mime' => 'image/svg+xml', 'extension' => 'svg'],
            'png' => ['mime' => 'image/png', 'extension' => 'png'],
            'carousel' => ['mime' => 'image/png', 'extension' => 'png'],
        ];
        $format = $formats[$asset->asset_type] ?? null;
        if (!$format || $asset->mime_type !== $format['mime']) abort(403);

        $prefix = 'brand-assets/' . $brand->id . '/';
        if (!str_starts_with($asset->storage_path, $prefix) ||
            !preg_match('/^[a-f0-9]{64}\.' . $format['extension'] . '$/D', basename($asset->storage_path)) ||
            !preg_match('/^[a-f0-9]{64}$/D', $asset->checksum)) abort(403);

        $disk = Storage::disk('local');
        if (!$disk->exists($asset->storage_path)) abort(404);
        $file = $disk->path($asset->storage_path);
        if (!is_file($file) || !is_readable($file) ||
            filesize($file) !== (int) $asset->file_size ||
            !hash_equals($asset->checksum, hash_file('sha256', $file))) abort(409, 'Asset integrity check failed');

        if ($format['extension'] === 'png') {
            $dimensions = @getimagesize($file);
            if (!$dimensions || ($dimensions[2] ?? null) !== IMAGETYPE_PNG ||
                $dimensions[0] !== (int) $asset->width ||
                $dimensions[1] !== (int) $asset->height) abort(409, 'Image dimensions do not match asset record');
        }

        $filename = 'aadi-' . $brand->id . '-' . $asset->id . '.' . $format['extension'];
        return response()->download($file, $filename, [
            'Content-Type' => $format['extension'] === 'svg' ? 'application/octet-stream' : 'image/png',
            'Content-Security-Policy' => 'sandbox',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
