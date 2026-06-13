<?php

/*
 * Architectural guards for the Authentication feature.
 *
 * The whole point of the TokenIssuer abstraction is that the inner layers
 * (Domain + Application) never reference the token infrastructure (Sanctum).
 * AuthenticationService lives in Application/ and orchestrates the use case
 * through Domain contracts only — if a future change imports Laravel\Sanctum\*
 * or any Infrastructure concretion into Domain/ or Application/, this fails.
 */

arch('domain layer does not depend on Sanctum')
    ->expect('App\Features\Authentication\Domain')
    ->not->toUse('Laravel\Sanctum');

arch('domain layer does not depend on Infrastructure')
    ->expect('App\Features\Authentication\Domain')
    ->not->toUse('App\Features\Authentication\Infrastructure');

arch('application layer does not depend on Sanctum')
    ->expect('App\Features\Authentication\Application')
    ->not->toUse('Laravel\Sanctum');

arch('application layer does not depend on Infrastructure')
    ->expect('App\Features\Authentication\Application')
    ->not->toUse('App\Features\Authentication\Infrastructure');
