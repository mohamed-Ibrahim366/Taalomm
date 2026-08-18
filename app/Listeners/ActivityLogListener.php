<?php

namespace App\Listeners;

use App\Events\PasswordChanged;
use App\Events\ProfileUpdated;
use App\Events\UserCreated;
use App\Events\UserDeleted;
use App\Events\UserRestored;
use App\Events\UserStatusChanged;
use App\Events\UserUpdated;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;

class ActivityLogListener
{
    public function __construct(private readonly Request $request) {}

    public function handleUserCreated(UserCreated $event): void
    {
        $this->log(
            causer: $event->createdBy,
            event: 'user_created',
            subject: $event->user,
            description: "User '{$event->user->name}' ({$event->user->role->value}) was created.",
            properties: [
                'role' => $event->user->role->value,
                'email' => $event->user->email,
            ],
        );
    }

    public function handleUserUpdated(UserUpdated $event): void
    {
        $this->log(
            causer: $event->updatedBy,
            event: 'user_updated',
            subject: $event->user,
            description: "User '{$event->user->name}' account was updated.",
            properties: $event->changes,
        );
    }

    public function handleUserStatusChanged(UserStatusChanged $event): void
    {
        $this->log(
            causer: $event->changedBy,
            event: 'user_status_changed',
            subject: $event->user,
            description: "User '{$event->user->name}' status changed from '{$event->oldStatus->value}' to '{$event->newStatus->value}'.",
            properties: [
                'old_status' => $event->oldStatus->value,
                'new_status' => $event->newStatus->value,
            ],
        );
    }

    public function handleUserDeleted(UserDeleted $event): void
    {
        $this->log(
            causer: $event->deletedBy,
            event: 'user_deleted',
            subject: $event->user,
            description: "User '{$event->user->name}' was soft-deleted.",
        );
    }

    public function handleUserRestored(UserRestored $event): void
    {
        $this->log(
            causer: $event->restoredBy,
            event: 'user_restored',
            subject: $event->user,
            description: "User '{$event->user->name}' was restored.",
        );
    }

    public function handleProfileUpdated(ProfileUpdated $event): void
    {
        $this->log(
            causer: $event->user,
            event: 'profile_updated',
            subject: $event->user,
            description: "User '{$event->user->name}' updated their profile.",
            properties: $event->changes,
        );
    }

    public function handlePasswordChanged(PasswordChanged $event): void
    {
        $this->log(
            causer: $event->user,
            event: 'password_changed',
            subject: $event->user,
            description: "User '{$event->user->name}' changed their password.",
        );
    }

    /**
     * Register all event → handler mappings for this subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            UserCreated::class      => 'handleUserCreated',
            UserUpdated::class      => 'handleUserUpdated',
            UserStatusChanged::class => 'handleUserStatusChanged',
            UserDeleted::class      => 'handleUserDeleted',
            UserRestored::class     => 'handleUserRestored',
            ProfileUpdated::class   => 'handleProfileUpdated',
            PasswordChanged::class  => 'handlePasswordChanged',
        ];
    }

    // ---- Private helpers ----

    private function log(
        ?User $causer,
        string $event,
        Model $subject,
        string $description,
        array $properties = [],
    ): void {
        ActivityLog::create([
            'causer_id'    => $causer?->id,
            'event'        => $event,
            'subject_type' => $subject::class,
            'subject_id'   => $subject->getKey(),
            'description'  => $description,
            'properties'   => empty($properties) ? null : $properties,
            'ip_address'   => $this->request->ip(),
            'user_agent'   => $this->request->userAgent(),
        ]);
    }
}
