<?php

namespace App\Http\Controllers;

use App\Models\WorkerCredential;
use App\Models\WorkerNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkerController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $input = $request->validate([
            'node_name'=>['required','string','max:120','regex:/^[A-Za-z0-9._-]+$/'],
            'platform'=>['required','string','max:64'],
            'worker_version'=>['required','string','max:48'],
            'capabilities'=>['required','array','max:24'],
            'capabilities.*'=>['string','distinct','max:64'],
        ]);
        /** @var WorkerCredential $credential */
        $credential = $request->attributes->get('worker_credential');
        $approved = $credential->allowed_capabilities ?? [];
        if (array_diff($input['capabilities'], $approved)) {
            throw ValidationException::withMessages(['capabilities'=>'Unapproved worker capability.']);
        }
        $node = DB::transaction(function () use ($credential, $input): WorkerNode {
            $credential = WorkerCredential::query()->lockForUpdate()->findOrFail($credential->getKey());
            $node = $credential->node ?? new WorkerNode();
            $node->fill($input + ['status'=>'online']);
            $node->status='online';
            $node->last_heartbeat_at=now();
            $node->save();
            $credential->worker_node_id=$node->getKey();
            $credential->save();
            return $node;
        });
        return response()->json(['worker_id'=>$node->getKey(),'status'=>'online','server_time'=>now()->toIso8601String()]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $input = $request->validate(['worker_version'=>['nullable','string','max:48']]);
        /** @var WorkerCredential $credential */
        $credential = $request->attributes->get('worker_credential');
        $node = $credential->node;
        if (!$node) return response()->json(['message'=>'Register worker first'], 409);
        $node->status='online';
        $node->last_heartbeat_at=now();
        if (isset($input['worker_version'])) $node->worker_version=$input['worker_version'];
        $node->save();
        return response()->json(['worker_id'=>$node->getKey(),'status'=>'online','server_time'=>now()->toIso8601String()]);
    }
}
