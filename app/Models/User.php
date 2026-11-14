<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasTeams;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'current_team_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasTeams, Notifiable, TwoFactorAuthenticatable {
        HasTeams::teams insteadof HasRoles;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Damage reports submitted by this user.
     *
     * @return HasMany<MaintenanceReport, $this>
     */
    public function reportedMaintenanceReports(): HasMany
    {
        return $this->hasMany(MaintenanceReport::class, 'reported_by')->latest('id');
    }

    /**
     * Damage reports assigned to this user.
     *
     * @return HasMany<MaintenanceReport, $this>
     */
    public function assignedMaintenanceReports(): HasMany
    {
        return $this->hasMany(MaintenanceReport::class, 'assigned_to')->latest('id');
    }

    /**
     * Maintenance schedules this user is responsible for.
     *
     * @return HasMany<MaintenanceSchedule, $this>
     */
    public function assignedMaintenanceSchedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class, 'assigned_to')->orderBy('next_due_at');
    }
}
