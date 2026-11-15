<?php

namespace App\Models\Concerns;

use App\Models\ApprovalRecord;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasApprovalFlow
{
    /**
     * Decisions recorded per approval step.
     *
     * @return MorphMany<ApprovalRecord, $this>
     */
    public function approvals(): MorphMany
    {
        return $this->morphMany(ApprovalRecord::class, 'approvable')->orderBy('step_index');
    }

    /**
     * Role that must act on the current approval step.
     */
    public function currentStepRole(): ?string
    {
        return $this->approval_steps[$this->current_step]['role'] ?? null;
    }

    /**
     * Whether more approval steps remain.
     */
    public function hasMoreSteps(): bool
    {
        return $this->current_step < count($this->approval_steps);
    }

    /**
     * Derive the display state of an approval step.
     */
    public function stepState(int $index, bool $pending): string
    {
        $approval = $this->approvals->firstWhere('step_index', $index);

        if ($approval) {
            return $approval->decision->value;
        }

        if ($index < $this->current_step) {
            return 'approved';
        }

        if ($index === $this->current_step && $pending) {
            return 'current';
        }

        return 'waiting';
    }
}
