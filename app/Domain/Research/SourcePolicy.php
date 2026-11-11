<?php

namespace App\Domain\Research;

use Illuminate\Validation\ValidationException;

class SourcePolicy
{
    /** Registration validation; network worker must independently validate resolved DNS and all redirects. */
    public static function assertPublicHttps(string $url): void
    {
        if (strlen($url)>2048 || preg_match('/[\x00-\x20]/',$url)) self::reject();
        $parts=parse_url($url);
        if ($parts===false || strtolower($parts['scheme']??'')!=='https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) self::reject();
        $host=strtolower(rtrim($parts['host'],'.'));
        if (!preg_match('/^[a-z0-9.-]+$/', $host) || !str_contains($host,'.') || preg_match('/(^|\.)(localhost|local|internal|test|invalid)$/',$host)) self::reject();
        if (filter_var($host,FILTER_VALIDATE_IP)) self::reject(); // worker also blocks DNS-rebinding on every connection
        if (isset($parts['port']) && $parts['port'] !== 443) self::reject();
    }
    private static function reject(): never { throw ValidationException::withMessages(['url'=>'Only approved public HTTPS DNS host URLs are accepted']); }
}
