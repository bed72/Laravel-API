<?php

use App\Core\Domain\Enums\HttpStatusCode;
use App\Core\Domain\Exceptions\DomainException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Privacy: prevent credential fields from being flashed to session or error reports
        $exceptions->dontFlash([
            'password',
            'password_confirmation',
            'new_password',
            'current_password',
            'token',
        ]);

        // Privacy: strip sensitive headers and server vars from error reporting context
        $exceptions->context(function (Throwable $e) {
            $request = request();

            $redactedHeaders = ['authorization', 'cookie', 'x-forwarded-for', 'x-real-ip', 'forwarded'];
            $redactedServerVars = ['REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR'];

            $headers = $request->headers->all();
            foreach ($redactedHeaders as $header) {
                unset($headers[$header]);
            }

            $serverVars = $request->server->all();
            foreach ($redactedServerVars as $var) {
                unset($serverVars[$var]);
            }

            return [
                'request' => [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'headers' => $headers,
                    'server' => $serverVars,
                ],
            ];
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            // ValidationException → 422
            if ($e instanceof ValidationException) {
                $errors = [];
                $failedRules = $e->validator->failed();

                foreach ($e->errors() as $field => $messages) {
                    $code = 'invalid';

                    $fieldRules = $failedRules[$field] ?? [];
                    if (isset($fieldRules['Required'])) {
                        $code = 'field_required';
                    } elseif (in_array($field, ['password', 'new_password'], true) && isset($fieldRules['Min'])) {
                        $code = 'password_weak';
                    }

                    $errors[] = [
                        'field' => $field,
                        'message' => implode('; ', $messages),
                        'code' => $code,
                    ];
                }

                return new JsonResponse(
                    ['errors' => $errors],
                    $e->status,
                );
            }

            // AuthenticationException → 401 (token ausente/expirado — não é "credencial errada")
            if ($e instanceof AuthenticationException) {
                return new JsonResponse(
                    ['errors' => [
                        [
                            'field' => null,
                            'message' => 'Não autenticado.',
                            'code' => 'not_authenticated',
                        ],
                    ]],
                    HttpStatusCode::Unauthorized->value,
                );
            }

            // ThrottleRequestsException → 429
            if ($e instanceof ThrottleRequestsException) {
                $headers = $e->getHeaders();

                $response = new JsonResponse(
                    ['errors' => [
                        [
                            'field' => null,
                            'message' => 'Muitas tentativas. Tente novamente mais tarde.',
                            'code' => 'throttled',
                        ],
                    ]],
                    HttpStatusCode::TooManyRequests->value,
                );

                foreach ($headers as $key => $value) {
                    $response->headers->set($key, (string) $value);
                }

                return $response;
            }

            // DomainException → custom error code + field
            if ($e instanceof DomainException) {
                return new JsonResponse(
                    ['errors' => [
                        [
                            'field' => $e->error->field(),
                            'message' => $e->error->message(),
                            'code' => $e->error->value,
                        ],
                    ]],
                    $e->error->status()->value,
                );
            }

            // HttpException (generic) → appropriate status with mapped code
            if ($e instanceof HttpException) {
                $status = $e->getStatusCode();
                $code = match ($status) {
                    HttpStatusCode::Unauthorized->value => 'not_authenticated',
                    HttpStatusCode::Forbidden->value => 'permission_denied',
                    HttpStatusCode::NotFound->value => 'not_found',
                    HttpStatusCode::TooManyRequests->value => 'throttled',
                    default => 'server_error',
                };

                $message = match ($status) {
                    HttpStatusCode::Unauthorized->value => 'Não autenticado.',
                    HttpStatusCode::Forbidden->value => 'Você não tem permissão para realizar esta ação.',
                    HttpStatusCode::NotFound->value => 'Recurso não encontrado.',
                    HttpStatusCode::TooManyRequests->value => 'Muitas tentativas. Tente novamente mais tarde.',
                    default => $e->getMessage() ?: 'Ocorreu um erro interno.',
                };

                $response = new JsonResponse(
                    ['errors' => [
                        [
                            'field' => null,
                            'message' => $message,
                            'code' => $code,
                        ],
                    ]],
                    $status,
                );

                foreach ($e->getHeaders() as $key => $value) {
                    $response->headers->set($key, (string) $value);
                }

                return $response;
            }

            // Unhandled exceptions → 500
            Log::error($e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse(
                ['errors' => [
                    [
                        'field' => null,
                        'message' => 'Ocorreu um erro interno.',
                        'code' => 'server_error',
                    ],
                ]],
                HttpStatusCode::InternalServerError->value,
            );
        });
    })->create();
