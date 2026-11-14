<?php

namespace App\Console\Commands;

use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Notifications\Sarpras\MaintenanceScheduleDue;
use App\Services\MaintenanceService;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SendMaintenanceReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sarpras:send-maintenance-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim pengingat jadwal pemeliharaan sarpras yang mendekati atau melewati jatuh tempo';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notifications, MaintenanceService $maintenance): int
    {
        $today = CarbonImmutable::today();
        $reminderDays = (int) config('sarpras.maintenance_reminder_days', 3);

        $schedules = MaintenanceSchedule::query()
            ->active()
            ->with(['asset', 'assignee'])
            ->whereDate('next_due_at', '<=', $today->addDays($reminderDays))
            ->where(function ($query) use ($today) {
                $query->whereNull('last_reminded_on')
                    ->orWhereDate('last_reminded_on', '<', $today);
            })
            ->get();

        /** @var Collection<int, User>|null $technicians */
        $technicians = null;
        $sent = 0;

        foreach ($schedules as $schedule) {
            $recipients = $schedule->assignee
                ? collect([$schedule->assignee])
                : ($technicians ??= $maintenance->technicians());

            if ($recipients->isEmpty()) {
                // Nobody to remind yet: leave last_reminded_on empty so it retries tomorrow.
                continue;
            }

            $notifications->sendToMultiple($recipients, new MaintenanceScheduleDue($schedule));

            $schedule->update(['last_reminded_on' => $today]);
            $sent++;
        }

        $this->info("Pengingat jadwal pemeliharaan terkirim: {$sent}.");

        return self::SUCCESS;
    }
}
