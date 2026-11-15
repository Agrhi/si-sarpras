import { DownloadOutlined } from '@ant-design/icons';
import { Head, router } from '@inertiajs/react';
import { Button, Input, Table, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useState } from 'react';
import { governance } from '@/actions/App/Http/Controllers/Sarpras/ReportController';
import PageHeader from '@/components/page-header';
import Panel from '@/components/panel';

interface Option {
    value: string;
    label: string;
}

interface ProcurementRow {
    id: number;
    title: string;
    proposer: string | null;
    item_count: number;
    estimated_total: number;
    status: Option;
    created_at: string;
    received_at: string | null;
}

interface DisposalRow {
    id: number;
    asset: string;
    proposer: string | null;
    reason: string;
    status: Option;
    created_at: string;
    method: string | null;
    realized_at: string | null;
}

interface Summary {
    procurement_total: number;
    procurement_received: number;
    procurement_cost: number;
    disposal_total: number;
    disposal_completed: number;
}

interface Props {
    procurements: ProcurementRow[];
    disposals: DisposalRow[];
    from: string;
    to: string;
    summary: Summary;
}

const STATUS_COLORS: Record<string, string> = {
    pending: 'gold',
    approved: 'cyan',
    rejected: 'red',
    received: 'green',
    completed: 'green',
};

const rupiah = (value: number) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);

export default function GovernanceReport({
    procurements,
    disposals,
    from,
    to,
    summary,
}: Props) {
    const [filters, setFilters] = useState({ from, to });

    const navigate = () => {
        router.get(
            governance().url,
            { from: filters.from, to: filters.to },
            { preserveState: true, preserveScroll: true },
        );
    };

    const exportUrl = (type: 'procurement' | 'disposal') =>
        governance({ query: { from, to, export: type } }).url;

    const procurementColumns: ColumnsType<ProcurementRow> = [
        {
            title: 'Usulan',
            dataIndex: 'title',
            key: 'title',
            ellipsis: true,
        },
        {
            title: 'Pengusul',
            dataIndex: 'proposer',
            key: 'proposer',
            width: 160,
            render: (text) => text ?? '-',
        },
        {
            title: 'Item',
            dataIndex: 'item_count',
            key: 'item_count',
            width: 70,
        },
        {
            title: 'Estimasi Biaya',
            dataIndex: 'estimated_total',
            key: 'estimated_total',
            width: 160,
            render: (value: number) => rupiah(value),
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
            title: 'Tanggal Usul',
            dataIndex: 'created_at',
            key: 'created_at',
            width: 120,
        },
        {
            title: 'Tanggal Terima',
            dataIndex: 'received_at',
            key: 'received_at',
            width: 130,
            render: (text) => text ?? '-',
        },
    ];

    const disposalColumns: ColumnsType<DisposalRow> = [
        {
            title: 'Aset',
            dataIndex: 'asset',
            key: 'asset',
            ellipsis: true,
        },
        {
            title: 'Pengusul',
            dataIndex: 'proposer',
            key: 'proposer',
            width: 160,
            render: (text) => text ?? '-',
        },
        {
            title: 'Alasan',
            dataIndex: 'reason',
            key: 'reason',
            ellipsis: true,
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
            title: 'Tanggal Usul',
            dataIndex: 'created_at',
            key: 'created_at',
            width: 120,
        },
        {
            title: 'Metode',
            dataIndex: 'method',
            key: 'method',
            width: 130,
            render: (text) => text ?? '-',
        },
        {
            title: 'Tanggal Realisasi',
            dataIndex: 'realized_at',
            key: 'realized_at',
            width: 140,
            render: (text) => text ?? '-',
        },
    ];

    const stats = [
        { label: 'Total Pengadaan', value: summary.procurement_total },
        { label: 'Barang Diterima', value: summary.procurement_received },
        { label: 'Estimasi Biaya', value: rupiah(summary.procurement_cost) },
        { label: 'Total Penghapusan', value: summary.disposal_total },
        { label: 'Penghapusan Selesai', value: summary.disposal_completed },
    ];

    return (
        <div className="mx-auto w-full max-w-7xl space-y-6">
            <Head title="Laporan Pengadaan & Penghapusan" />
            <PageHeader
                eyebrow="Sarana Prasarana"
                title="Laporan Pengadaan & Penghapusan"
                description="Rekap usulan pengadaan dan penghapusan aset berdasarkan tanggal usul."
                actions={
                    <>
                        <Button
                            type="primary"
                            icon={<DownloadOutlined />}
                            size="large"
                            onClick={() => {
                                window.location.href = exportUrl('procurement');
                            }}
                        >
                            Ekspor Pengadaan
                        </Button>
                        <Button
                            type="primary"
                            icon={<DownloadOutlined />}
                            size="large"
                            onClick={() => {
                                window.location.href = exportUrl('disposal');
                            }}
                        >
                            Ekspor Penghapusan
                        </Button>
                    </>
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
                    <Button type="primary" onClick={navigate}>
                        Terapkan
                    </Button>
                </div>

                <div className="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-5">
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

                <h2 className="mb-2 mt-6 text-base font-semibold">Pengadaan</h2>
                <Table
                    columns={procurementColumns}
                    dataSource={procurements}
                    rowKey="id"
                    size="middle"
                    pagination={false}
                    scroll={{ x: 1000 }}
                />

                <h2 className="mb-2 mt-6 text-base font-semibold">
                    Penghapusan Aset
                </h2>
                <Table
                    columns={disposalColumns}
                    dataSource={disposals}
                    rowKey="id"
                    size="middle"
                    pagination={false}
                    scroll={{ x: 1000 }}
                />
            </Panel>
        </div>
    );
}

GovernanceReport.layout = () => ({
    breadcrumbs: [
        { title: 'Sarana Prasarana', href: '#' },
        { title: 'Laporan Pengadaan & Penghapusan', href: governance().url },
    ],
});
