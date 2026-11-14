<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RecordAuditLog
{
    /**
     * Routes that are too chatty or outside asset governance.
     *
     * @var list<string>
     */
    private const IGNORED_PREFIXES = [
        'notifications.',
        'teams.',
        'profile.',
        'user-password.',
        'security.',
        'invitations.',
    ];

    /**
     * Human labels for the trailing route action.
     *
     * @var array<string, string>
     */
    private const VERBS = [
        'store' => 'Menambah',
        'update' => 'Mengubah',
        'destroy' => 'Menghapus',
        'approve' => 'Menyetujui',
        'reject' => 'Menolak',
        'handover' => 'Menyerahkan',
        'return' => 'Mengembalikan',
        'cancel' => 'Membatalkan',
        'assign' => 'Menugaskan',
        'start' => 'Memproses',
        'complete' => 'Menyelesaikan',
    ];

    /**
     * Human labels for audited subjects, keyed by model or route module.
     *
     * @var array<string, string>
     */
    private const SUBJECTS = [
        'Asset' => 'aset',
        'assets' => 'aset',
        'Category' => 'kategori',
        'categories' => 'kategori',
        'Location' => 'lokasi',
        'locations' => 'lokasi',
        'Room' => 'ruangan',
        'rooms' => 'ruangan',
        'LoanRequest' => 'peminjaman',
        'loans' => 'peminjaman',
        'MaintenanceReport' => 'laporan kerusakan',
        'maintenance-reports' => 'laporan kerusakan',
        'MaintenanceSchedule' => 'jadwal pemeliharaan',
        'maintenance-schedules' => 'jadwal pemeliharaan',
        'ApprovalFlow' => 'alur persetujuan',
        'User' => 'pengguna',
        'users' => 'pengguna',
        'Role' => 'role',
        'roles' => 'role',
        'Permission' => 'permission',
        'permissions' => 'permission',
        'admin' => 'data',
    ];

    /**
     * Record every successful state-changing request in the audit log.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $action = $request->route()?->getName();

        if (! $action
            || ! $request->user()
            || $request->isMethodSafe()
            || $response->getStatusCode() >= 400
            || ($request->hasSession() && $request->session()->has('errors'))
            || Str::startsWith($action, self::IGNORED_PREFIXES)) {
            return $response;
        }

        $auditable = collect($request->route()->parameters())
            ->first(fn ($parameter) => $parameter instanceof Model);

        AuditLog::create([
            'user_id' => $request->user()->getKey(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'summary' => $this->summarize($action, $auditable),
        ]);

        return $response;
    }

    /**
     * Build a short Indonesian description such as "Mengubah aset Kursi Lipat".
     */
    private function summarize(string $action, ?Model $auditable): string
    {
        $verb = self::VERBS[Str::afterLast($action, '.')] ?? Str::headline(Str::afterLast($action, '.'));
        $subject = self::SUBJECTS[$auditable ? class_basename($auditable) : Str::before($action, '.')]
            ?? Str::headline($auditable ? class_basename($auditable) : Str::before($action, '.'));

        return trim("{$verb} {$subject} {$auditable?->getAttribute('name')}");
    }
}
