<?php

namespace App\Models;

use App\Enums\MaintenanceFrequency;
use Database\Factories\MaintenanceScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asset_id',
    'assigned_to',
    'title',
    'description',
    'frequency',
    'next_due_at',
    'last_performed_at',
    'last_reminded_on',
    'is_active',
])]
class MaintenanceSchedule extends Model
{
    /** @use HasFactory<MaintenanceScheduleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'frequency' => MaintenanceFrequency::class,
            'next_due_at' => 'date',
            'last_performed_at' => 'date',
            'last_reminded_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Asset this schedule applies to.
     *
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Technician responsible for the schedule.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Limit the query to active schedules.
     *
     * @param  Builder<MaintenanceSchedule>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Derive the display state of the schedule.
     *
     * @return Attribute<string, never>
     */
    protected function dueState(): Attribute
    {
        return Attribute::get(function (): string {
            if (! $this->is_active) {
                return 'nonaktif';
            }

            if ($this->next_due_at->isBefore(today())) {
                return 'terlambat';
            }

            $window = today()->addDays((int) config('sarpras.maintenance_reminder_days', 3));

            if ($this->next_due_at->lessThanOrEqualTo($window)) {
                return 'jatuh-tempo';
            }

            return 'terjadwal';
        });
    }
}
