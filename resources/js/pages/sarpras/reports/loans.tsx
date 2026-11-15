import { DownloadOutlined } from '@ant-design/icons';
import { Head, router } from '@inertiajs/react';
import { Button, Checkbox, Input, Select, Table, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useState } from 'react';
import { loans as loansReport } from '@/actions/App/Http/Controllers/Sarpras/ReportController';
import PageHeader from '@/components/page-header';
import Panel from '@/components/panel';

interface Option {
    value: string;
    label: string;
}

interface Row {
    id: number;
    borrower: string | null;
    purpose: string;
    item_count: number;
    borrowed_at: string;
    due_at: string;
    returned_at: string | null;
    status: Option;
    overdue_days: number;
}

interface Summary {
    total: number;
    late: number;
    active: number;
    items: number;
}

interface Props {
    rows: Row[];
    from: string;
    to: string;
    status: string;
    late: boolean;
    statuses: Option[];
    summary: Summary;
}

const STATUS_COLORS: Record<string, string> = {
    pending: 'gold',
    approved: 'cyan',
    rejected: 'red',
    active: 'blue',
    returned: 'green',
    cancelled: 'default',
};

export default function LoanReport({
    rows,
    from,
    to,
    status,
    late,
    statuses,
    summary,
}: Props) {
    const [filters, setFilters] = useState({ from, to, status, late });

    const navigate = (overrides: Partial<typeof filters> = {}) => {
        const next = { ...filters, ...overrides };

        router.get(
            loansReport().url,
            {
                from: next.from,
                to: next.to,
                status: next.status,
                late: next.late ? 1 : 0,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const exportUrl = loansReport({
        query: {
            from,
            to,
            status,
            late: late ? 1 : 0,
            export: 'csv',
        },
    }).url;

    const columns: ColumnsType<Row> = [
        {
            title: 'Peminjam',
            dataIndex: 'borrower',
            key: 'borrower',
            width: 160,
            render: (text) => text ?? '-',
        },
        {
            title: 'Keperluan',
            dataIndex: 'purpose',
            key: 'purpose',
            ellipsis: true,
        },
        {
            title: 'Item',
            dataIndex: 'item_count',
            key: 'item_count',
            width: 70,
        },
        {
            title: 'Pinjam',
            dataIndex: 'borrowed_at',
            key: 'borrowed_at',
            width: 140,
        },
        {
            title: 'Batas Kembali',
            dataIndex: 'due_at',
            key: 'due_at',
            width: 140,
        },
        {
            title: 'Kembali',
            dataIndex: 'returned_at',
            key: 'returned_at',
            width: 140,
            render: (text) => text ?? '-',
        },
        {
            title: 'Status',
            dataIndex: 'status',
            key: 'status',
            width: 160,
            render: (value: Option) => (
                <Tag color={STATUS_COLORS[value.value] ?? 'default'}>
                    {value.label}
                </Tag>
            ),
        },
        {
            title: 'Terlambat',
            dataIndex: 'overdue_days',
            key: 'overdue_days',
            width: 110,
            render: (days: number) =>
                days > 0 ? <Tag color="red">{days} hari</Tag> : '-',
        },
    ];

    const stats = [
        { label: 'Total Peminjaman', value: summary.total },
        { label: 'Terlambat', value: summary.late },
        { label: 'Sedang Dipinjam', value: summary.active },
        { label: 'Total Item', value: summary.items },
    ];

    return (
        <div className="mx-auto w-full max-w-7xl space-y-6">
            <Head title="Riwayat Peminjaman" />
            <PageHeader
                eyebrow="Sarana Prasarana"
                title="Riwayat Peminjaman & Keterlambatan"
                description="Rekap peminjaman berdasarkan tanggal pinjam, lengkap dengan hari keterlambatan."
                actions={
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
                }
            />

            <Panel bodyClassName="p-4 sm:p-6">
                <div className="mb-4 flex flex-wrap items-end gap-3">
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
                    <Checkbox
                        checked={filters.late}
                        onChange={(e) =>
                            setFilters((f) => ({
                                ...f,
                                late: e.target.checked,
                            }))
                        }
                    >
                        Hanya terlambat
                    </Checkbox>
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
                    scroll={{ x: 1000 }}
                />
            </Panel>
        </div>
    );
}

LoanReport.layout = () => ({
    breadcrumbs: [
        { title: 'Sarana Prasarana', href: '#' },
        { title: 'Riwayat Peminjaman', href: loansReport().url },
    ],
});
