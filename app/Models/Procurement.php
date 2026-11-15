<?php

namespace App\Models;

use App\Enums\ProcurementStatus;
use App\Models\Concerns\HasApprovalFlow;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'title', 'reason', 'status', 'approval_steps', 'current_step', 'received_at'])]
class Procurement extends Model
{
    /** @use HasFactory<ProcurementFactory> */
    use HasApprovalFlow, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProcurementStatus::class,
            'approval_steps' => 'array',
            'current_step' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    /**
     * User who proposed the procurement.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Items requested in this procurement.
     *
     * @return HasMany<ProcurementItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProcurementItem::class);
    }
}
