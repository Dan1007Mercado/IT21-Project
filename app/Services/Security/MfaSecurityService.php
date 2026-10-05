<?php

namespace App\Services\Security;

use App\Mail\SecurityNotificationMail;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class MfaSecurityService
{
    public function __construct(private ClientIpResolver $clientIpResolver) {}

    /** @param array<string, scalar|null> $metadata */
    public function record(Request $request, User $user, string $eventType, string $title, array $metadata = []): void
    {
        try {
            SecurityEvent::record(
                title: $title,
                eventType: $eventType,
                severity: str_contains($eventType, 'FAILED') ? 'Low' : 'Info',
                user: $user,
                sourceIp: $this->clientIpResolver->resolve($request)['ip'],
                metadata: $metadata,
            );
        } catch (Throwable $exception) {
            Log::warning('An MFA security event could not be recorded.', [
                'user_id' => $user->getKey(),
                'event_type' => $eventType,
                'exception' => $exception::class,
            ]);
        }
    }

    public function notify(User $user, string $message): void
    {
        if ($user->email_verified_at === null) {
            return;
        }

        try {
            Mail::to($user->email)->send(new SecurityNotificationMail($message));
        } catch (Throwable $exception) {
            Log::warning('An MFA security notification could not be delivered.', [
                'user_id' => $user->getKey(),
                'exception' => $exception::class,
            ]);
        }
    }

    public function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return substr($local, 0, 1).str_repeat('*', max(3, strlen($local) - 1)).'@'.$domain;
    }
}
