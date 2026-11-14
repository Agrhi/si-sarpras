<?php

namespace App\Http\Controllers\Sarpras;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
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

        $logs = AuditLog::with('user')
            ->when($search, fn ($query) => $query->where(
                fn ($query) => $query->where('summary', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%")),
            ))
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
        ]);
    }
}
