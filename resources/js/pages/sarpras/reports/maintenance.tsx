import { DownloadOutlined, PrinterOutlined } from '@ant-design/icons';
import { Head, router } from '@inertiajs/react';
import { Button, Input, Select, Table, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useState } from 'react';
import { maintenance as maintenanceReport } from '@/actions/App/Http/Controllers/Sarpras/ReportController';
import PageHeader from '@/components/page-header';
import Panel from '@/components/panel';

interface Option {
    value: string;
    label: string;
}

interface Row {
    id: number;
    asset: string;
    category: string;
    reporter: string | null;
    assignee: string | null;
    urgency: Option;
    status: Option;
    created_at: string;
    completed_at: string | null;
    cost: number;
    spare_parts: string | null;
}

interface Summary {
    total: number;
    completed: number;
    open: number;
    total_cost: number;
}

interface CategoryRecap {
    category: string;
    total: number;
    cost: number;
}

interface Props {
    rows: Row[];
    from: string;
    to: string;
    status: string;
    statuses: Option[];
    summary: Summary;
    category_recap: CategoryRecap[];
}

const STATUS_COLORS: Record<string, string> = {
    menunggu: 'gold',
    diproses: 'blue',
    selesai: 'green',
    dibatalkan: 'default',
};

const URGENCY_COLORS: Record<string, string> = {
    rendah: 'green',
    sedang: 'gold',
    tinggi: 'red',
};

const rupiah = (value: number) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);

export default function MaintenanceReport({
    rows,
    from,
    to,
    status,
    statuses,
    summary,
    category_recap,
}: Props) {
    const [filters, setFilters] = useState({ from, to, status });

    const navigate = (overrides: Partial<typeof filters> = {}) => {
        const next = { ...filters, ...overrides };

        router.get(
            maintenanceReport().url,
            {
                from: next.from,
                to: next.to,
                status: next.status,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const exportUrl = maintenanceReport({
        query: { from, to, status, export: 'csv' },
    }).url;

    const columns: ColumnsType<Row> = [
        {
            title: 'Aset',
            dataIndex: 'asset',
            key: 'asset',
            ellipsis: true,
        },
        {
            title: 'Kategori',
            dataIndex: 'category',
            key: 'category',
            width: 140,
        },
        {
            title: 'Pelapor',
            dataIndex: 'reporter',
            key: 'reporter',
            width: 140,
            render: (text) => text ?? '-',
        },
        {
            title: 'Petugas',
            dataIndex: 'assignee',
            key: 'assignee',
            width: 140,
            render: (text) => text ?? '-',
        },
        {
            title: 'Urgensi',
            dataIndex: 'urgency',
            key: 'urgency',
            width: 110,
            render: (value: Option) => (
                <Tag color={URGENCY_COLORS[value.value] ?? 'default'}>
                    {value.label}
                </Tag>
            ),
        },
        {
            title: 'Status',
            dataIndex: 'status',
            key: 'status',
            width: 140,
            render: (value: Option) => (
                <Tag color={STATUS_COLORS[value.value] ?? 'default'}>
                    {value.label}
                </Tag>
            ),
        },
        {
            title: 'Lapor',
            dataIndex: 'created_at',
            key: 'created_at',
            width: 110,
        },
        {
            title: 'Selesai',
            dataIndex: 'completed_at',
            key: 'completed_at',
            width: 110,
            render: (text) => text ?? '-',
        },
        {
            title: 'Biaya',
            dataIndex: 'cost',
            key: 'cost',
            width: 140,
            align: 'right',
            render: (value: number) => (value > 0 ? rupiah(value) : '-'),
        },
        {
            title: 'Suku Cadang',
            dataIndex: 'spare_parts',
            key: 'spare_parts',
            width: 160,
            ellipsis: true,
            render: (text) => text ?? '-',
        },
    ];

    const recapColumns: ColumnsType<CategoryRecap> = [
        {
            title: 'Kategori',
            dataIndex: 'category',
            key: 'category',
        },
        {
            title: 'Jumlah Laporan',
            dataIndex: 'total',
            key: 'total',
            width: 160,
            align: 'right',
        },
        {
            title: 'Total Biaya',
            dataIndex: 'cost',
            key: 'cost',
            width: 180,
            align: 'right',
            render: (value: number) => rupiah(value),
        },
    ];

    const stats = [
        { label: 'Total Laporan', value: summary.total },
        { label: 'Selesai', value: summary.completed },
        { label: 'Belum Selesai', value: summary.open },
        { label: 'Total Biaya', value: rupiah(summary.total_cost) },
    ];

    return (
        <div className="mx-auto w-full max-w-7xl space-y-6 print:max-w-none print:space-y-3">
            <Head title="Rekap Kerusakan" />
            <div className="print:hidden">
                <PageHeader
                    eyebrow="Sarana Prasarana"
                    title="Rekap Kerusakan & Biaya Perbaikan"
                    description="Rekap laporan kerusakan berdasarkan tanggal lapor beserta biaya perbaikannya."
                    actions={
                        <>
                            <Button
                                icon={<PrinterOutlined />}
                                size="large"
                                onClick={() => window.print()}
                            >
                                Cetak
                            </Button>
                            <Button
                                type="primary"
                                icon={<DownloadOutlined />}
                                size="large"
                                onClick={() => {
                                    window.location.href = exportUrl;
                                }}
                            >
                                Ekspor CSV
                            </Button>
                        </>
                    }
                />
            </div>

            <div className="hidden print:block">
                <h1 className="text-xl font-bold">
                    Rekap Kerusakan & Biaya Perbaikan
                </h1>
                <p className="text-sm">
                    Periode {from} s/d {to}
                </p>
            </div>

            <Panel bodyClassName="p-4 sm:p-6 print:border-0 print:p-0 print:shadow-none">
                <div className="mb-4 flex flex-wrap items-end gap-3 print:hidden">
                    <label className="text-sm">
                        <span className="mb-1 block text-muted-foreground">
                            Dari
                        </span>
                        <Input
                            type="date"
                            className="w-full sm:w-40"
                            value={filters.from}
                            onChange={(e) =>
                                setFilters((f) => ({
                                    ...f,
                                    from: e.target.value,
                                }))
                            }
                        />
                    </label>
                    <label className="text-sm">
                        <span className="mb-1 block text-muted-foreground">
                            Sampai
                        </span>
                        <Input
                            type="date"
                            className="w-full sm:w-40"
                            value={filters.to}
                            onChange={(e) =>
                                setFilters((f) => ({
                                    ...f,
                                    to: e.target.value,
                                }))
                            }
                        />
                    </label>
                    <Select
                        placeholder="Status"
                        allowClear
                        className="w-full sm:w-48"
                        value={filters.status || undefined}
                        onChange={(value) =>
                            setFilters((f) => ({ ...f, status: value ?? '' }))
                        }
                        options={statuses}
                    />
                    <Button type="primary" onClick={() => navigate()}>
                        Terapkan
                    </Button>
                </div>

                <div className="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {stats.map((stat) => (
                        <div
                            key={stat.label}
                            className="rounded-xl border border-border p-3"
                        >
                            <div className="text-xs text-muted-foreground">
                                {stat.label}
                            </div>
                            <div className="text-xl font-semibold">
                                {stat.value}
                            </div>
                        </div>
                    ))}
                </div>

                <Table
                    columns={columns}
                    dataSource={rows}
                    rowKey="id"
                    size="middle"
                    pagination={false}
                    scroll={{ x: 1200 }}
                />
            </Panel>

            <Panel
                title="Rekap per Kategori"
                description="Jumlah laporan dan total biaya perbaikan per kategori aset."
                bodyClassName="p-4 sm:p-6 print:border-0 print:p-0 print:shadow-none"
            >
                <Table
                    columns={recapColumns}
                    dataSource={category_recap}
                    rowKey="category"
                    size="middle"
                    pagination={false}
                />
            </Panel>
        </div>
    );
}

MaintenanceReport.layout = () => ({
    breadcrumbs: [
        { title: 'Sarana Prasarana', href: '#' },
        { title: 'Rekap Kerusakan', href: maintenanceReport().url },
    ],
});
