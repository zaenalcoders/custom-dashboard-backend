<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (in_array((explode('/', $request->path())[1] ?? null), ['import-example', 'export', 'download', 'download-attachment'])) {
            return $next($request);
        }
        if (in_array((explode('/', $request->path())[2] ?? null), ['import-example', 'export', 'download', 'download-attachment'])) {
            return $next($request);
        }
        $headers = [
            'Access-Control-Allow-Origin'      => '*',
            // 'Vary' => 'Origin',
            'Access-Control-Allow-Methods'     => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            // 'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Headers'     => 'Accept, Content-Type, Authorization',
            'Access-Control-Expose-Headers' => 'X-Access'
        ];
        // if (config('app.env') == 'production') {
        //     $allowOrigins = DB::table('client_origins')->where(['url' => $request->header('origin'), 'status' => 1]);
        //     if ($allowOrigins->count()) {
        //         $headers['Access-Control-Allow-Origin'] = $allowOrigins->pluck('url')->first();
        //     }
        // }
        if ($request->isMethod('OPTIONS')) {
            return response()->json([], 200, $headers);
        }
        $response = $next($request);
        if (!$request->wantsJson()) {
            return $response;
        }
        foreach ($headers as $key => $value) {
            $response->header($key, $value);
        }
        return $response;
    }
}
