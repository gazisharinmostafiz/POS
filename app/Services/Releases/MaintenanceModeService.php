<?php

namespace App\Services\Releases;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class MaintenanceModeService
{
    private const PATH = 'maintenance/release_maintenance.json';

    public function status(): array
    {
        if (! Storage::disk('local')->exists(self::PATH)) {
            return [
                'enabled' => false,
                'message' => 'Release maintenance is disabled.',
                'updated_at' => null,
                'updated_by' => null,
            ];
        }

        $data = json_decode(Storage::disk('local')->get(self::PATH), true) ?: [];

        return array_merge([
            'enabled' => false,
            'message' => 'Release maintenance is disabled.',
            'updated_at' => null,
            'updated_by' => null,
        ], $data);
    }

    public function enabled(): bool
    {
        return (bool) $this->status()['enabled'];
    }

    public function set(bool $enabled, ?string $message = null, ?User $user = null): array
    {
        $data = [
            'enabled' => $enabled,
            'message' => $message ?: ($enabled ? 'Release maintenance is enabled.' : 'Release maintenance is disabled.'),
            'updated_at' => now()->toIso8601String(),
            'updated_by' => $user?->id,
        ];

        Storage::disk('local')->put(self::PATH, json_encode($data, JSON_PRETTY_PRINT));

        return $data;
    }
}
