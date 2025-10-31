<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, ...$request_access)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (Exception $ex) {
            throw $ex;
        }
        if ($user === false) {
            throw new AuthorizationException('Token is invalid');
        }
        if ($request->path() == 'auth/logout') {
            goto response;
        }
        if ($user->status == -1) {
            throw new AccessDeniedHttpException('Your account was blocked');
        }
        if ($user->status == 0) {
            throw new AccessDeniedHttpException('Your account is inactive');
        }

        if ($request->method() == 'GET' && !$request->wantsJson()) {
            return $next($request);
        }

        if (in_array((explode('/', $request->path())[1] ?? null), ['import-example', 'export', 'download', 'download-template', 'download-attachment', 'file-uploader', 'pdf'])) {
            return $next($request);
        }
        if (in_array((explode('/', $request->path())[0] ?? null), ['download', 'download-attachment'])) {
            return $next($request);
        }

        if (in_array(explode('/', $request->path())[0], ['auth', 'dashboard', 'img-uploads', 'navigation', 'import-example', 'export', 'download', 'notifications', 'file-uploader', 'pdf'])) {
            goto response;
        }

        if (!count($request_access) && isset($request->forceView)) {
            goto response;
        }

        // $current_url = explode('/', $request->path());
        // $user_menu = $user->role->access;
        // $menu = DB::table('navigations')->where(['link' => $current_url[0]])
        //     ->first(['action']);
        // if (!$menu) {
        //     if ($user_menu[0] != '*') {
        //         throw new AccessDeniedHttpException('Access forbidden');
        //     } else {
        //         goto response;
        //     }
        // }
        // $menu_action = json_decode($menu->action ?? '[]') ?? [];
        // $action = array();
        // if ($user_menu[0] == '*') {
        //     foreach ($menu_action as $a) {
        //         $action[$a] = true;
        //     }
        //     goto response;
        // }
        // $user_menu = collect($user_menu);
        // $access_link = $user_menu->where('link', $current_url[0])->first();
        // if (!$access_link) {
        //     throw new AccessDeniedHttpException('Access forbidden');
        // }

        // $action = (array)$access_link;
        // if (in_array($request->method(), ['POST', 'PATCH', 'PUT', 'DELETE'])) {
        //     $_access = explode('/', $request->path());
        //     $request_access = $_access[count($_access) - 1];
        //     $request_access = $request_access == 'set-status' || $request_access == 'set-code' || $request_access == 'set-cancel' || $request_access == 'set-active' || $request_access == 'set-location' || $request_access == 'approve' || $request_access == 'reject' || $request_access == 'confirm' || $request_access == 'accountability' || $request_access == 'upload-receipt' ? 'update' : $request_access;
        //     if (!isset($action[$request_access])) {
        //         throw new AccessDeniedHttpException('Access forbidden');
        //     }
        //     if (!$action[$request_access]) {
        //         throw new AccessDeniedHttpException('Access forbidden');
        //     }
        // }

        response:
        $response = $next($request);
        // $response->header('X-Access', base64_encode(json_encode($action ?? null)));
        return $response;
    }
}
