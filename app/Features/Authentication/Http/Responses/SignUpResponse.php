<?php

namespace App\Features\Authentication\Http\Responses;

use App\Features\Users\Domain\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\NewAccessToken;

/** @mixin User */
class SignUpResponse extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    public function __construct(
        User $resource,
        private readonly NewAccessToken $accessToken,
    ) {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->accessToken->plainTextToken,
            'user' => [
                'id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
            ],
        ];
    }
}
