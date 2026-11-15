<?php

namespace App\Http\Controllers\Sarpras;

use App\Enums\DisposalStatus;
use App\Enums\LoanStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\ProcurementStatus;
use App\Http\Controllers\Controller;
use App\Models\Disposal;
use App\Models\LoanRequest;
use App\Models\MaintenanceReport;
use App\Models\Procurement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Loan history and lateness report.
     */
    public function loans(Request $request): Response|StreamedResponse
    {
        [$from, $to] = $this->period($request);
        $status = $request->string('status')->toString();
        $late = $request->boolean('late');

        $rows = LoanRequest::query()
            ->with('user:id,name')
            ->withCount('items')
            ->whereBetween('borrowed_at', [$from, $to->copy()->endOfDay()])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderBy('borrowed_at')
            ->get()
            ->map(fn (LoanRequest $loan) => [
                'id' => $loan->id,
                'borrower' => $loan->user?->name,
                'purpose' => $loan->purpose,
                'item_count' => $loan->items_count,
                'borrowed_at' => $loan->borrowed_at->format('d/m/Y H:i'),
                'due_at' => $loan->due_at->format('d/m/Y H:i'),
                'returned_at' => $loan->returned_at?->format('d/m/Y H:i'),
                'status' => ['value' => $loan->status->value, 'label' => $loan->status->label()],
                'overdue_days' => $this->overdueDays($loan),
            ])
            ->filter(fn (array $row) => ! $late || $row['overdue_days'] > 0)
            ->values();

        if ($request->string('export')->toString() === 'csv') {
            return $this->csv('riwayat-peminjaman.csv', [
                'Peminjam', 'Keperluan', 'Jumlah Item', 'Tanggal Pinjam', 'Batas Kembali',
                'Tanggal Kembali', 'Status', 'Hari Terlambat',
            ], $rows->map(fn (array $row) => [
                $row['borrower'], $row['purpose'], $row['item_count'], $row['borrowed_at'],
                $row['due_at'], $row['returned_at'] ?? '-', $row['status']['label'], $row['overdue_days'],
            ]));
        }

        return Inertia::render('sarpras/reports/loans', [
            'rows' => $rows,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'status' => $status,
            'late' => $late,
            'statuses' => LoanStatus::options(),
            'summary' => [
                'total' => $rows->count(),
                'late' => $rows->where('overdue_days', '>', 0)->count(),
                'active' => $rows->where('status.value', LoanStatus::Active->value)->count(),
                'items' => $rows->sum('item_count'),
            ],
        ]);
    }

    /**
     * Damage report and repair cost recap.
     */
    public function maintenance(Request $request): Response|StreamedResponse
    {
        [$from, $to] = $this->period($request);
        $status = $request->string('status')->toString();

        $rows = MaintenanceReport::query()
            ->with([
                'asset:id,inventory_code,name,category_id',
                'asset.category:id,name',
                'reporter:id,name',
                'assignee:id,name',
            ])
            ->whereBetween('created_at', [$from, $to->copy()->endOfDay()])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderBy('created_at')
            ->get()
            ->map(fn (MaintenanceReport $report) => [
                'id' => $report->id,
                'asset' => $report->asset
                    ? "{$report->asset->inventory_code} · {$report->asset->name}"
                    : '—',
                'category' => $report->asset?->category?->name ?? 'Tanpa kategori',
                'reporter' => $report->reporter?->name,
                'assignee' => $report->assignee?->name,
                'urgency' => ['value' => $report->urgency->value, 'label' => $report->urgency->label()],
                'status' => ['value' => $report->status->value, 'label' => $report->status->label()],
                'created_at' => $report->created_at->format('d/m/Y'),
                'completed_at' => $report->completed_at?->format('d/m/Y'),
                'cost' => (float) $report->cost,
                'spare_parts' => $report->spare_parts,
            ]);

        if ($request->string('export')->toString() === 'csv') {
            return $this->csv('rekap-kerusakan.csv', [
                'Aset', 'Kategori', 'Pelapor', 'Petugas', 'Urgensi', 'Status',
                'Tanggal Lapor', 'Tanggal Selesai', 'Biaya', 'Suku Cadang',
            ], $rows->map(fn (array $row) => [
                $row['asset'], $row['category'], $row['reporter'] ?? '-', $row['assignee'] ?? '-',
                $row['urgency']['label'], $row['status']['label'], $row['created_at'],
                $row['completed_at'] ?? '-', $row['cost'], $row['spare_parts'] ?? '-',
            ]));
        }

        return Inertia::render('sarpras/reports/maintenance', [
            'rows' => $rows,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'status' => $status,
            'statuses' => MaintenanceStatus::options(),
            'summary' => [
                'total' => $rows->count(),
                'completed' => $rows->where('status.value', MaintenanceStatus::Completed->value)->count(),
                'open' => $rows->whereIn('status.value', [
                    MaintenanceStatus::Pending->value,
                    MaintenanceStatus::InProgress->value,
                ])->count(),
                'total_cost' => $rows->sum('cost'),
            ],
            'category_recap' => $rows->groupBy('category')->map(fn (Collection $group) => [
                'category' => $group->first()['category'],
                'total' => $group->count(),
                'cost' => $group->sum('cost'),
            ])->values(),
        ]);
    }

    /**
     * Procurement and disposal recap.
     */
    public function governance(Request $request): Response|StreamedResponse
    {
        [$from, $to] = $this->period($request);

        $procurements = Procurement::query()
            ->with(['user:id,name', 'items:id,procurement_id,quantity,unit_price'])
            ->whereBetween('created_at', [$from, $to->copy()->endOfDay()])
            ->orderBy('created_at')
            ->get()
            ->map(fn (Procurement $procurement) => [
                'id' => $procurement->id,
                'title' => $procurement->title,
                'proposer' => $procurement->user?->name,
                'item_count' => $procurement->items->count(),
                'estimated_total' => $procurement->items->sum(fn ($item) => $item->estimatedTotal()),
                'status' => ['value' => $procurement->status->value, 'label' => $procurement->status->label()],
                'created_at' => $procurement->created_at->format('d/m/Y'),
                'received_at' => $procurement->received_at?->format('d/m/Y'),
            ]);

        $disposals = Disposal::query()
            ->with(['user:id,name', 'asset:id,inventory_code,name'])
            ->whereBetween('created_at', [$from, $to->copy()->endOfDay()])
            ->orderBy('created_at')
            ->get()
            ->map(fn (Disposal $disposal) => [
                'id' => $disposal->id,
                'asset' => $disposal->asset
                    ? "{$disposal->asset->inventory_code} · {$disposal->asset->name}"
                    : '—',
                'proposer' => $disposal->user?->name,
                'reason' => $disposal->reason,
                'status' => ['value' => $disposal->status->value, 'label' => $disposal->status->label()],
                'created_at' => $disposal->created_at->format('d/m/Y'),
                'method' => $disposal->method?->label(),
                'realized_at' => $disposal->realized_at?->format('d/m/Y'),
            ]);

        $export = $request->string('export')->toString();

        if ($export === 'procurement') {
            return $this->csv('laporan-pengadaan.csv', [
                'Usulan', 'Pengusul', 'Jumlah Item', 'Estimasi Biaya', 'Status',
                'Tanggal Usul', 'Tanggal Terima',
            ], $procurements->map(fn (array $row) => [
                $row['title'], $row['proposer'] ?? '-', $row['item_count'], $row['estimated_total'],
                $row['status']['label'], $row['created_at'], $row['received_at'] ?? '-',
            ]));
        }

        if ($export === 'disposal') {
            return $this->csv('laporan-penghapusan.csv', [
                'Aset', 'Pengusul', 'Alasan', 'Status', 'Tanggal Usul', 'Metode', 'Tanggal Realisasi',
            ], $disposals->map(fn (array $row) => [
                $row['asset'], $row['proposer'] ?? '-', $row['reason'], $row['status']['label'],
                $row['created_at'], $row['method'] ?? '-', $row['realized_at'] ?? '-',
            ]));
        }

        return Inertia::render('sarpras/reports/governance', [
            'procurements' => $procurements,
            'disposals' => $disposals,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'summary' => [
                'procurement_total' => $procurements->count(),
                'procurement_received' => $procurements->where('status.value', ProcurementStatus::Received->value)->count(),
                'procurement_cost' => $procurements->sum('estimated_total'),
                'disposal_total' => $disposals->count(),
                'disposal_completed' => $disposals->where('status.value', DisposalStatus::Completed->value)->count(),
            ],
        ]);
    }

    /**
     * Resolve the requested period, defaulting to the current month.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(Request $request): array
    {
        $rules = [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];

        if ($request->filled('from')) {
            $rules['to'][] = 'after_or_equal:from';
        }

        $validated = $request->validate($rules);

        return [
            Carbon::parse($validated['from'] ?? now()->startOfMonth())->startOfDay(),
            Carbon::parse($validated['to'] ?? now())->startOfDay(),
        ];
    }

    /**
     * Days past the due date, for active or returned loans.
     */
    private function overdueDays(LoanRequest $loan): int
    {
        if (! in_array($loan->status, [LoanStatus::Active, LoanStatus::Returned], true)) {
            return 0;
        }

        $end = $loan->returned_at ?? now();

        if ($end->lte($loan->due_at)) {
            return 0;
        }

        return (int) floor(($end->getTimestamp() - $loan->due_at->getTimestamp()) / 86400);
    }

    /**
     * Stream the given rows as a CSV download.
     *
     * @param  array<int, string>  $headings
     * @param  Collection<int, array<int, mixed>>  $rows
     */
    private function csv(string $filename, array $headings, Collection $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headings);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
