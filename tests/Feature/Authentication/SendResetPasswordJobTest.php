<?php

use App\Features\Authentication\Infrastructure\Jobs\SendResetPasswordJob;
use App\Features\Authentication\Infrastructure\Notifications\ResetPasswordNotification;
use App\Features\Users\Domain\Models\User;
use Illuminate\Support\Facades\Notification;

/*
 * Regression guard for the broker DI bug: the job used to type-hint the concrete
 * Illuminate\Auth\Passwords\PasswordBroker, which the container cannot build
 * (its constructor needs the unbound TokenRepositoryInterface), so handle()
 * threw BindingResolutionException at runtime. Every other reset test uses
 * Queue::fake(), so handle() was never exercised and the bug stayed hidden.
 *
 * This test runs the job synchronously through the container — exactly how the
 * queue invokes it — so the dependency actually resolves.
 */

it('resolves its broker dependency and sends exactly one reset notification', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'user@example.com']);

    // dispatchSync resolves handle()'s dependencies via the container,
    // mirroring real queue execution (no Queue::fake masking it).
    SendResetPasswordJob::dispatchSync($user);

    Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);
});
