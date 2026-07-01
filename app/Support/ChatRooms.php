<?php

namespace App\Support;

use App\Models\User;

class ChatRooms
{
    public const ALL_STAFF = 'all_staff';
    public const WAITER_KITCHEN = 'waiter_kitchen';
    public const WAITER_COUNTER = 'waiter_counter';
    public const COUNTER_KITCHEN = 'counter_kitchen';
    public const ADMIN_ANNOUNCEMENTS = 'admin_announcements';

    public const ALL = [
        self::ALL_STAFF,
        self::WAITER_KITCHEN,
        self::WAITER_COUNTER,
        self::COUNTER_KITCHEN,
        self::ADMIN_ANNOUNCEMENTS,
    ];

    public const LABELS = [
        self::ALL_STAFF => 'All staff',
        self::WAITER_KITCHEN => 'Waiter + Kitchen',
        self::WAITER_COUNTER => 'Waiter + Counter',
        self::COUNTER_KITCHEN => 'Counter + Kitchen',
        self::ADMIN_ANNOUNCEMENTS => 'Admin announcements',
    ];

    public static function canAccess(User $user, string $room): bool
    {
        return in_array($room, self::roomsFor($user), true);
    }

    public static function canSend(User $user, string $room): bool
    {
        if ($room === self::ADMIN_ANNOUNCEMENTS) {
            return $user->hasRole([Roles::ADMIN, Roles::SUPER_ADMIN]);
        }

        return self::canAccess($user, $room);
    }

    public static function roomsFor(User $user): array
    {
        if ($user->hasRole([Roles::ADMIN, Roles::SUPER_ADMIN])) {
            return self::ALL;
        }

        return match ($user->role) {
            Roles::WAITER => [self::ALL_STAFF, self::WAITER_KITCHEN, self::WAITER_COUNTER, self::ADMIN_ANNOUNCEMENTS],
            Roles::COUNTER => [self::ALL_STAFF, self::WAITER_COUNTER, self::COUNTER_KITCHEN, self::ADMIN_ANNOUNCEMENTS],
            Roles::KITCHEN => [self::ALL_STAFF, self::WAITER_KITCHEN, self::COUNTER_KITCHEN, self::ADMIN_ANNOUNCEMENTS],
            default => [],
        };
    }

    public static function label(string $room): string
    {
        return self::LABELS[$room] ?? $room;
    }
}
