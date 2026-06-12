<?php

namespace App\Features\Authentication\Http\Controllers;

use App\Core\Domain\Enums\HttpStatusCode;
use App\Features\Authentication\Domain\Services\AuthenticationService;
use App\Features\Authentication\Http\Requests\PasswordResetConfirmRequest;
use App\Features\Authentication\Http\Requests\PasswordResetRequest;
use App\Features\Authentication\Http\Requests\SignInRequest;
use App\Features\Authentication\Http\Requests\SignUpRequest;
use App\Features\Authentication\Http\Responses\SignInResponse;
use App\Features\Authentication\Http\Responses\SignUpResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthenticationController
{
    public function __construct(
        private readonly AuthenticationService $service,
    ) {}

    public function signUp(SignUpRequest $request): JsonResponse
    {
        $result = $this->service->signUp(
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
        );

        return SignUpResponse::make($result['user'], $result['token'])
            ->response()
            ->setStatusCode(HttpStatusCode::Created->value);
    }

    public function signIn(SignInRequest $request): SignInResponse
    {
        $accessToken = $this->service->signIn(
            email: $request->validated('email'),
            password: $request->validated('password'),
        );

        return SignInResponse::make($accessToken);
    }

    public function signOut(Request $request): JsonResponse
    {
        /** @var PersonalAccessToken $token */
        $token = $request->user()->currentAccessToken();
        $this->service->signOut($token);

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

        return response()->json((object) []);
    }

    public function resetPassword(PasswordResetConfirmRequest $request): JsonResponse
    {
        $this->service->resetPassword(
            uid: $request->validated('uid'),
            token: $request->validated('token'),
            newPassword: $request->validated('new_password'),
        );

        return response()->json((object) []);
    }
}
