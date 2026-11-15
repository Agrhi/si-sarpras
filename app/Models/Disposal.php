<?php

namespace App\Models;

use App\Enums\DisposalMethod;
use App\Enums\DisposalStatus;
use App\Models\Concerns\HasApprovalFlow;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'asset_id', 'reason', 'evidence_path', 'status', 'approval_steps', 'current_step', 'method', 'realized_at', 'document_path'])]
class Disposal extends Model
{
    /** @use HasFactory<DisposalFactory> */
    use HasApprovalFlow, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DisposalStatus::class,
            'method' => DisposalMethod::class,
            'approval_steps' => 'array',
            'current_step' => 'integer',
            'realized_at' => 'date',
        ];
    }

    /**
     * User who proposed the disposal.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Asset to be disposed of.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
