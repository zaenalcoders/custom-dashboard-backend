<?php

namespace App\Http\Middleware;

use Closure;

class ClearEmptyStringMiddleware
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
        if (strtolower($request->method()) == 'get') {
            return $next($request);
        }
        if ($request->has('data')) {
            $data = [];
            $pData = gettype($request->data) == 'array' ? $request->data : json_decode($request->data);
            foreach ($pData as $key => $val) {
                $data[$key] = (gettype($val) == 'string' && !strlen(trim($val))) || $val === null || $val == 'null' || $val === 'undefined' ? null : $val;
            }
            $request->merge(['data' => json_encode($data)]);
        }
        return $next($request);
    }
}
