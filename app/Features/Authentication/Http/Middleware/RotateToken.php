<?php

namespace App\Features\Authentication\Http\Middleware;

use App\Features\Authentication\Domain\Contracts\AuthenticationRepositoryInterface;
use App\Features\Users\Domain\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class RotateToken
{
    public function __construct(
        private readonly AuthenticationRepositoryInterface $repository,
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

        $sevenDaysAgo = now()->subDays(7);

        if ($token->created_at->isAfter($sevenDaysAgo)) {
            return $response;
        }

        /** @var User $user */
        $user = $request->user();

        $newToken = $this->repository->rotateToken($token, $user);

        $response->headers->set('X-New-Token', $newToken->plainTextToken);

        return $response;
    }
}
