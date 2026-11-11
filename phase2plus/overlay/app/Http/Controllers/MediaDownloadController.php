<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaDownloadController extends Controller
{
    public function __invoke(Request $r, Brand $brand, int $id)
    {
        if (!$r->user()?->hasBrandAccess($brand)) abort(403);
        $asset=MediaAsset::query()->where('brand_id',$brand->id)->findOrFail($id);
        if ($asset->asset_type!=='svg' || $asset->mime_type!=='image/svg+xml' ||
            !str_starts_with($asset->storage_path,'brand-assets/'.$brand->id.'/') ||
            !preg_match('/^[a-f0-9]{64}\.svg$/D',basename($asset->storage_path))) abort(403);
        $disk=Storage::disk('local');
        if (!$disk->exists($asset->storage_path)) abort(404);
        return response()->download($disk->path($asset->storage_path),'aadi-'.$asset->id.'.svg',[
            'Content-Type'=>'application/octet-stream',
            'Content-Security-Policy'=>'sandbox',
            'X-Content-Type-Options'=>'nosniff',
        ]);
    }
}
