<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceUrgency;
use Database\Factories\MaintenanceReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asset_id',
    'reported_by',
    'assigned_to',
    'urgency',
    'description',
    'photo_path',
    'status',
    'action_taken',
    'cost',
    'spare_parts',
    'started_at',
    'completed_at',
])]
class MaintenanceReport extends Model
{
    /** @use HasFactory<MaintenanceReportFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'urgency' => MaintenanceUrgency::class,
            'status' => MaintenanceStatus::class,
            'cost' => 'decimal:2',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Asset that needs maintenance.
     *
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * User who submitted the report.
     *
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * Technician assigned to handle the report.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Limit the query to reports that are still open.
     *
     * @param  Builder<MaintenanceReport>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [
            MaintenanceStatus::Pending->value,
            MaintenanceStatus::InProgress->value,
        ]);
    }
}
