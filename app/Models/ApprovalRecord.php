<?php

namespace App\Models;

use App\Enums\ApprovalDecision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['step_index', 'user_id', 'decision', 'note'])]
class ApprovalRecord extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step_index' => 'integer',
            'decision' => ApprovalDecision::class,
        ];
    }

    /**
     * The procurement or disposal request this decision belongs to.
     */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Approver who made the decision.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
