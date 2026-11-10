<?php

namespace App\Http\Middleware;

use App\Models\WorkerCredential;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWorker
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production') && !$request->isSecure()) {
            return response()->json(['message'=>'HTTPS required'], 403);
        }

        $bearer = $request->bearerToken();
        if (!$bearer || !preg_match('/^amo_([1-9][0-9]*)\.([a-f0-9]{64})$/D', $bearer, $matches)) {
            return response()->json(['message'=>'Unauthorized'], 401);
        }
        $credential = WorkerCredential::find((int) $matches[1]);
        if (!$credential || $credential->revoked_at || !hash_equals($credential->token_hash, hash('sha256', $bearer))) {
            return response()->json(['message'=>'Unauthorized'], 401);
        }

        $timestamp = $request->header('X-Worker-Timestamp');
        $nonce = $request->header('X-Worker-Nonce');
        if (!is_string($timestamp) || !ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300 ||
            !is_string($nonce) || !preg_match('/^[A-Za-z0-9_-]{16,80}$/D', $nonce)) {
            return response()->json(['message'=>'Invalid freshness headers'], 401);
        }

        try {
            DB::table('worker_request_nonces')->insert([
                'worker_credential_id'=>$credential->getKey(),
                'nonce'=>$nonce,
                'created_at'=>now(),
            ]);
        } catch (QueryException $e) {
            if (DB::table('worker_request_nonces')->where('worker_credential_id', $credential->getKey())->where('nonce', $nonce)->exists()) {
                return response()->json(['message'=>'Request replayed'], 409);
            }
            throw $e;
        }

        $credential->forceFill(['last_used_at'=>now()])->save();
        $request->attributes->set('worker_credential', $credential);
        return $next($request);
    }
}
