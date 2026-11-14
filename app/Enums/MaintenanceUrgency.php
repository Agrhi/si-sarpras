<?php

namespace App\Enums;

enum MaintenanceUrgency: string
{
    case Low = 'rendah';
    case Medium = 'sedang';
    case High = 'tinggi';

    /**
     * Get the display label for the urgency.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Rendah',
            self::Medium => 'Sedang',
            self::High => 'Tinggi',
        };
    }

    /**
     * Get options for select inputs.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $urgency) => ['value' => $urgency->value, 'label' => $urgency->label()])
            ->values()
            ->toArray();
    }
}
