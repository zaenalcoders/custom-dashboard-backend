<?php

namespace App\Exceptions;

use Dotenv\Exception\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Validation\UnauthorizedException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        if ($request->wantsJson()) {
            return $this->custom_render($request, $exception);
        }
        return parent::render($request, $exception);
    }

    /**
     * custom_render
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable $e
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    private function custom_render($request, $e)
    {
        $status = Response::HTTP_INTERNAL_SERVER_ERROR;
        if ($e instanceof HttpResponseException) {
            $status = Response::HTTP_INTERNAL_SERVER_ERROR;
            $message = 'HTTP_INTERNAL_SERVER_ERROR';
        } elseif ($e instanceof MethodNotAllowedHttpException) {
            $status = Response::HTTP_METHOD_NOT_ALLOWED;
            $message = 'HTTP_METHOD_NOT_ALLOWED';
        } elseif ($e instanceof NotFoundHttpException) {
            $status = Response::HTTP_NOT_FOUND;
            $message = 'HTTP_NOT_FOUND';
        } elseif ($e instanceof \Dotenv\Exception\ValidationException) {
            $status = Response::HTTP_BAD_REQUEST;
            $message = 'HTTP_BAD_REQUEST';
        } elseif ($e instanceof AuthorizationException || $e instanceof UnauthorizedHttpException || ($e instanceof JWTException && !$e instanceof TokenExpiredException) || $e instanceof UnauthorizedException) {
            $status = Response::HTTP_UNAUTHORIZED;
            $message = 'HTTP_UNAUTHORIZED';
        } elseif ($e instanceof AccessDeniedHttpException) {
            $status = Response::HTTP_FORBIDDEN;
            $message = 'HTTP_FORBIDDEN';
        } elseif ($e instanceof TokenExpiredException) {
            $status = Response::HTTP_LOCKED;
            $message = 'HTTP_AUTHLOCKED';
        } elseif ($e instanceof ValidationException || $e->getCode() == 422) {
            $status = Response::HTTP_UNPROCESSABLE_ENTITY;
            $message = 'HTTP_UNPROCESSABLE_ENTITY';
        } elseif ($e instanceof ModelNotFoundException) {
            $status = Response::HTTP_INTERNAL_SERVER_ERROR;
            $message = 'MODEL_NOT_FOUND';
        } elseif ($e) {
            $message = 'HTTP_INTERNAL_SERVER_ERROR';
        }
        $data = array();
        $data['status'] = $status;
        $data['message'] = $message;
        if (config('app.debug')) {
            $data['description'] = $e->getMessage();
            $data['file'] = $e->getFile();
            $data['line'] = $e->getLine();
            // $data['trace'] = $e->getTrace();
            $data['request'] = '[' . $request->method() . '] ' . $request->fullUrl();
            $data['date_time'] = date('Y-m-d H:i:s');
        }
        $headers = [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*'
        ];

        return response()->json($data, $status, $headers);
    }
}
