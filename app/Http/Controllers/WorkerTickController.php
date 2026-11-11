<?php
namespace App\Http\Controllers;
use App\Models\WorkerCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class WorkerTickController extends Controller
{
    public function tick(Request $request):JsonResponse
    {
        /** @var WorkerCredential|null $credential */
        $credential=$request->attributes->get('worker_credential');
        if (!$credential || !in_array('heartbeat',$credential->allowed_capabilities??[],true)) abort(403);
        // Local worker can trigger deterministic tasks even if shared-hosting cron is disabled.
        // 15-minute server-side guard prevents multi-worker tick storms.
        if (!Cache::add('aadi:scheduler:tick',now()->toIso8601String(),now()->addMinutes(15))) {
            return response()->json(['status'=>'already_scheduled']);
        }
        Artisan::call('aadi:tick');
        Artisan::call('aadi:maintenance');
        return response()->json(['status'=>'ok','scheduler'=>'executed']);
    }
}
