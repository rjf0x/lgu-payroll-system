<?php

namespace App\Listeners;

use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;

/**
 * Records authentication activity in the audit trail.
 */
class AuthenticationEventSubscriber
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handleLogin(Login $event): void
    {
        $event->user->forceFill(['last_login_at' => now()])->save();

        $this->auditLogger->log(
            'LOGIN',
            'Authentication',
            (int) $event->user->getAuthIdentifier(),
        );
    }

    public function handleFailed(Failed $event): void
    {
        $this->auditLogger->log(
            'LOGIN_FAILED',
            'Authentication',
            $event->user ? (int) $event->user->getAuthIdentifier() : null,
            newValues: ['username' => $event->credentials['username'] ?? null],
        );
    }

    public function handleLogout(Logout $event): void
    {
        $this->auditLogger->log(
            'LOGOUT',
            'Authentication',
            $event->user ? (int) $event->user->getAuthIdentifier() : null,
        );
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Login::class, [self::class, 'handleLogin']);
        $events->listen(Failed::class, [self::class, 'handleFailed']);
        $events->listen(Logout::class, [self::class, 'handleLogout']);
    }
}
