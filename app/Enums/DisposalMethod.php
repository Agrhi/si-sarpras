<?php

namespace App\Enums;

enum DisposalMethod: string
{
    case Sold = 'dijual';
    case Donated = 'dihibahkan';
    case Destroyed = 'dimusnahkan';

    /**
     * Human-readable label in Indonesian.
     */
    public function label(): string
    {
        return match ($this) {
            self::Sold => 'Dijual',
            self::Donated => 'Dihibahkan',
            self::Destroyed => 'Dimusnahkan',
        };
    }

    /**
     * Options array for select inputs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
