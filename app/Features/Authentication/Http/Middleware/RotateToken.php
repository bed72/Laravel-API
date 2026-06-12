<?php

namespace App\Features\Authentication\Http\Middleware;

use App\Features\Authentication\Domain\Services\AuthenticationService;
use App\Features\Users\Domain\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class RotateToken
{
    public function __construct(
        private readonly AuthenticationService $service,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $token = $request->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return $response;
        }

        // Token may have been deleted by sign-out/log-out during request processing
        if ($token->exists === false) {
            return $response;
        }

        /** @var User $user */
        $user = $request->user();

        $newToken = $this->service->rotateTokenIfStale($token, $user);

        if ($newToken !== null) {
            $response->headers->set('X-New-Token', $newToken->plainTextToken);
        }

        return $response;
    }
}
