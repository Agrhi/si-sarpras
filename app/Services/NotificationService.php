<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class NotificationService
{
    /**
     * Send a notification to a specific notifiable entity (e.g. User).
     */
    public function send(mixed $notifiable, Notification $notification): void
    {
        $notifiable->notify($notification);
    }

    /**
     * Send a notification to multiple notifiable entities.
     */
    public function sendToMultiple(Collection|array $notifiables, Notification $notification): void
    {
        NotificationFacade::send($notifiables, $notification);
    }

    /**
     * Send a notification to every user holding the role and permission.
     */
    public function sendToRole(string $role, string $permission, Notification $notification, ?User $actor = null): void
    {
        $users = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', $role))
            ->get()
            ->filter(fn (User $user) => $user->can($permission))
            ->reject(fn (User $user) => $actor?->is($user));

        if ($users->isNotEmpty()) {
            $this->sendToMultiple($users, $notification);
        }
    }

    /**
     * Get unread database notifications for an entity.
     */
    public function getUnread(mixed $notifiable, int $perPage = 10)
    {
        return $notifiable->unreadNotifications()->paginate($perPage);
    }

    /**
     * Get all database notifications for an entity.
     */
    public function getAll(mixed $notifiable, int $perPage = 10)
    {
        return $notifiable->notifications()->paginate($perPage);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(mixed $notifiable, string $notificationId): bool
    {
        $notification = $notifiable->unreadNotifications()->where('id', $notificationId)->first();

        if ($notification) {
            $notification->markAsRead();

            return true;
        }

        return false;
    }

    /**
     * Mark all unread notifications as read.
     */
    public function markAllAsRead(mixed $notifiable): void
    {
        $notifiable->unreadNotifications->markAsRead();
    }

    /**
     * Delete a specific notification.
     */
    public function delete(mixed $notifiable, string $notificationId): bool
    {
        $notification = $notifiable->notifications()->where('id', $notificationId)->first();

        if ($notification) {
            $notification->delete();

            return true;
        }

        return false;
    }

    /**
     * Delete all notifications for an entity.
     */
    public function deleteAll(mixed $notifiable): void
    {
        $notifiable->notifications()->delete();
    }
}
