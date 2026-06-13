<?php

namespace App\Features\Authentication\Presentation\Controllers;

use App\Core\Domain\Enums\HttpStatusCode;
use App\Features\Authentication\Application\Services\AuthenticationService;
use App\Features\Authentication\Application\Data\IssuedTokenData;
use App\Features\Authentication\Presentation\Requests\PasswordResetConfirmRequest;
use App\Features\Authentication\Presentation\Requests\PasswordResetRequest;
use App\Features\Authentication\Presentation\Requests\SignInRequest;
use App\Features\Authentication\Presentation\Requests\SignUpRequest;
use App\Features\Authentication\Presentation\Responses\SignInResponse;
use App\Features\Authentication\Presentation\Responses\SignUpResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthenticationController
{
    public function __construct(
        private readonly AuthenticationService $service,
    ) {}

    public function signUp(SignUpRequest $request): SignUpResponse
    {
        $session = $this->service->signUp(
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
        );

        return SignUpResponse::make($session->user, (string) $session->token->plainTextToken);
    }

    public function signIn(SignInRequest $request): SignInResponse
    {
        $session = $this->service->signIn(
            email: $request->validated('email'),
            password: $request->validated('password'),
        );

        return SignInResponse::make($session->user, (string) $session->token->plainTextToken);
    }

    public function signOut(Request $request): JsonResponse
    {
        /** @var PersonalAccessToken $token */
        $token = $request->user()->currentAccessToken();

        $this->service->signOut(new IssuedTokenData($token->getKey(), $token->created_at));

        return response()->json(null, HttpStatusCode::NoContent->value);
    }

    public function logOut(Request $request): JsonResponse
    {
        $this->service->logOut($request->user());

        return response()->json(null, HttpStatusCode::NoContent->value);
    }

    public function requestPasswordReset(PasswordResetRequest $request): JsonResponse
    {
        $this->service->requestPasswordReset($request->validated('email'));

        return response()->json(null, HttpStatusCode::NoContent->value);
    }

    public function resetPassword(PasswordResetConfirmRequest $request): JsonResponse
    {
        $this->service->resetPassword(
            uid: $request->validated('uid'),
            token: $request->validated('token'),
            newPassword: $request->validated('new_password'),
        );

        return response()->json(null, HttpStatusCode::NoContent->value);
    }
}
