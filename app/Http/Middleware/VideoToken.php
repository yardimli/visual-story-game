<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VideoToken
{
    public function handle(Request $request, Closure $next, string $role)
    {
        abort_unless(config('video.mode') === 'hub', 409, 'Only a video hub accepts these requests.');
        $token = $request->bearerToken();
        abort_unless($token, 401);
        $record = DB::table('video_access_tokens')->where('token_hash', hash('sha256', $token))->where('role', $role)->first();
        abort_unless($record, 401);
        $request->attributes->set('video_token', $record);

        return $next($request);
    }
}
