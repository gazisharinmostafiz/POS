<?php

namespace App\Support;

class OrderSourceTypes
{
    public const TABLE = 'table';
    public const TAKEAWAY = 'takeaway';
    public const WALK_IN = 'walk_in';

    public const ALL = [
        self::TABLE,
        self::TAKEAWAY,
        self::WALK_IN,
    ];

    public static function isValid(string $source): bool
    {
        return in_array($source, self::ALL, true);
    }

    public static function requiresTable(string $source): bool
    {
        return $source === self::TABLE;
    }
}
