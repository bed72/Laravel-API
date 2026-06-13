<?php

namespace App\Features\Authentication\Presentation\Middleware;

use App\Features\Authentication\Application\Services\AuthenticationService;
use App\Features\Authentication\Application\Data\IssuedTokenData;
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

        $issued = new IssuedTokenData($token->getKey(), $token->created_at);

        $newToken = $this->service->rotateTokenIfStale($issued, $user);

        if ($newToken !== null) {
            $response->headers->set('X-New-Token', (string) $newToken->plainTextToken);
        }

        return $response;
    }
}
