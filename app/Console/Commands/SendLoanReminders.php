<?php

namespace App\Console\Commands;

use App\Enums\LoanStatus;
use App\Models\LoanRequest;
use App\Notifications\Sarpras\LoanDueSoon;
use App\Notifications\Sarpras\LoanOverdue;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendLoanReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sarpras:send-loan-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim pengingat jatuh tempo dan keterlambatan peminjaman sarpras';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notifications): int
    {
        $now = CarbonImmutable::now();
        $reminderDays = (int) config('sarpras.loan_reminder_days', 1);

        $dueSoon = LoanRequest::query()
            ->with('user')
            ->where('status', LoanStatus::Active)
            ->whereNull('due_reminder_sent_at')
            ->where('due_at', '>=', $now)
            ->where('due_at', '<=', $now->addDays($reminderDays))
            ->get();

        foreach ($dueSoon as $loan) {
            $notifications->send($loan->user, new LoanDueSoon($loan));
            $loan->update(['due_reminder_sent_at' => $now]);
        }

        $overdueQuery = LoanRequest::query()
            ->with('user')
            ->where('status', LoanStatus::Active)
            ->where('due_at', '<', $now);

        if (! config('sarpras.notify_overdue_daily', false)) {
            $overdueQuery->whereNull('overdue_reminder_sent_at');
        }

        $overdue = $overdueQuery->get();

        foreach ($overdue as $loan) {
            $notifications->send($loan->user, new LoanOverdue($loan));
            $loan->update(['overdue_reminder_sent_at' => $now]);
        }

        $this->info("Pengingat jatuh tempo terkirim: {$dueSoon->count()}. Keterlambatan: {$overdue->count()}.");

        return self::SUCCESS;
    }
}
