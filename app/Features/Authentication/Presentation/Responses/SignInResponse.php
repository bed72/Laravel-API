<?php

namespace App\Features\Authentication\Presentation\Responses;

use App\Features\Users\Domain\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class SignInResponse extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    public function __construct(
        User $resource,
        private readonly string $token,
    ) {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->token,
            'user' => [
                'id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
            ],
        ];
    }
}
