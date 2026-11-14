<?php

namespace App\Enums;

enum MaintenanceStatus: string
{
    case Pending = 'menunggu';
    case InProgress = 'diproses';
    case Completed = 'selesai';
    case Cancelled = 'dibatalkan';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::InProgress => 'Dalam Proses',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
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
            ->map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()])
            ->values()
            ->toArray();
    }

    /**
     * Determine whether the report is still open.
     */
    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::InProgress;
    }
}
