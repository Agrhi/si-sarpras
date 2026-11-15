<?php

namespace App\Http\Controllers\Sarpras;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Display the audit trail, newest first.
     */
    public function index(Request $request): Response
    {
        $search = $request->get('search', '');
        $perPage = (int) $request->get('perPage', 10);

        $filters = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $userId = isset($filters['user_id']) ? (int) $filters['user_id'] : null;
        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : null;
        $to = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : null;

        $logs = AuditLog::with('user')
            ->when($search, fn ($query) => $query->where(
                fn ($query) => $query->where('summary', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%")),
            ))
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
            ->latest('id')
            ->paginate($perPage)
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'user' => $log->user?->name,
                'action' => $log->action,
                'summary' => $log->summary,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
            ]);

        return Inertia::render('audit-logs/index', [
            'logs' => $logs->items(),
            'total' => $logs->total(),
            'page' => $logs->currentPage(),
            'perPage' => $logs->perPage(),
            'search' => $search,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'userId' => $userId,
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
        ]);
    }
}
