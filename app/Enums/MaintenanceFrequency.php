<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum MaintenanceFrequency: string
{
    case Daily = 'harian';
    case Weekly = 'mingguan';
    case Monthly = 'bulanan';
    case Semesterly = 'semesteran';
    case Yearly = 'tahunan';

    /**
     * Get the display label for the frequency.
     */
    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Harian',
            self::Weekly => 'Mingguan',
            self::Monthly => 'Bulanan',
            self::Semesterly => 'Semesteran',
            self::Yearly => 'Tahunan',
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
            ->map(fn (self $frequency) => ['value' => $frequency->value, 'label' => $frequency->label()])
            ->values()
            ->toArray();
    }

    /**
     * Calculate the next due date from the given date.
     */
    public function nextDueFrom(CarbonInterface $date): CarbonInterface
    {
        return match ($this) {
            self::Daily => $date->copy()->addDay(),
            self::Weekly => $date->copy()->addWeek(),
            self::Monthly => $date->copy()->addMonth(),
            self::Semesterly => $date->copy()->addMonths(6),
            self::Yearly => $date->copy()->addYear(),
        };
    }
}
