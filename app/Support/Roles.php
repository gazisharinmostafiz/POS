<?php

namespace App\Support;

class Roles
{
    public const PLATFORM_OWNER = 'platform_owner';
    public const SUPER_ADMIN = 'super_admin';
    public const ADMIN = 'admin';
    public const WAITER = 'waiter';
    public const COUNTER = 'counter';
    public const KITCHEN = 'kitchen';

    public const ALL = [
        self::PLATFORM_OWNER,
        self::SUPER_ADMIN,
        self::ADMIN,
        self::WAITER,
        self::COUNTER,
        self::KITCHEN,
    ];

    public const REDIRECTS = [
        self::PLATFORM_OWNER => '/platform',
        self::SUPER_ADMIN => '/tenant/admin',
        self::ADMIN => '/tenant/admin',
        self::WAITER => '/waiter/pos',
        self::COUNTER => '/counter',
        self::KITCHEN => '/kitchen',
    ];

    public static function redirectFor(string $role): string
    {
        return self::REDIRECTS[$role] ?? '/';
    }
}
