import { DownloadOutlined } from '@ant-design/icons';
import { Head, router } from '@inertiajs/react';
import { Button, Select, Table, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useState } from 'react';
import { assets as assetsReport } from '@/actions/App/Http/Controllers/Sarpras/ReportController';
import PageHeader from '@/components/page-header';
import Panel from '@/components/panel';

interface Option {
    value: string;
    label: string;
}

interface Row {
    id: number;
    inventory_code: string | null;
    name: string;
    category: string;
    location: string;
    condition: Option;
    status: Option;
    image_count: number;
}

interface Filters {
    category_id: number | '';
    location_id: number | '';
    status: string;
    condition: string;
}

interface Summary {
    total: number;
    by_condition: { value: string; label: string; count: number }[];
    without_code: number;
    without_photo: number;
}

interface Props {
    rows: Row[];
    categories: { id: number; name: string }[];
    locations: { id: number; name: string }[];
    statuses: Option[];
    conditions: Option[];
    filters: Filters;
    summary: Summary;
    missing_code: Row[];
    missing_photo: Row[];
}

const STATUS_COLORS: Record<string, string> = {
    aktif: 'green',
    dipinjam: 'blue',
    'dalam-perbaikan': 'orange',
    dihapus: 'red',
};

const CONDITION_COLORS: Record<string, string> = {
    baik: 'green',
    'kurang-baik': 'gold',
    rusak: 'red',
};

export default function AssetReport({
    rows,
    categories,
    locations,
    statuses,
    conditions,
    filters: initialFilters,
    summary,
    missing_code,
    missing_photo,
}: Props) {
    const [filters, setFilters] = useState<Filters>(initialFilters);

    const query = Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== ''),
    );

    const navigate = () => {
        router.get(assetsReport().url, query, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const columns: ColumnsType<Row> = [
        {
            title: 'Kode Inventaris',
            dataIndex: 'inventory_code',
            key: 'inventory_code',
            width: 160,
            render: (text: string | null) => text ?? '-',
        },
        {
            title: 'Nama Aset',
            dataIndex: 'name',
            key: 'name',
            ellipsis: true,
        },
        {
            title: 'Kategori',
            dataIndex: 'category',
            key: 'category',
            width: 150,
            ellipsis: true,
        },
        {
            title: 'Lokasi',
            dataIndex: 'location',
            key: 'location',
            width: 170,
            ellipsis: true,
        },
        {
            title: 'Kondisi',
            dataIndex: 'condition',
            key: 'condition',
            width: 130,
            render: (value: Option) => (
                <Tag color={CONDITION_COLORS[value.value] ?? 'default'}>
                    {value.label}
                </Tag>
            ),
        },
        {
            title: 'Status',
            dataIndex: 'status',
            key: 'status',
            width: 150,
            render: (value: Option) => (
                <Tag color={STATUS_COLORS[value.value] ?? 'default'}>
                    {value.label}
                </Tag>
            ),
        },
        {
            title: 'Foto',
            dataIndex: 'image_count',
            key: 'image_count',
            width: 100,
            render: (count: number) =>
                count > 0 ? `${count} foto` : <Tag color="red">Belum ada</Tag>,
        },
    ];

    const stats = [
        { label: 'Total Aset', value: summary.total },
        ...summary.by_condition.map((item) => ({
            label: item.label,
            value: item.count,
        })),
        { label: 'Tanpa Kode', value: summary.without_code },
        { label: 'Tanpa Foto', value: summary.without_photo },
    ];

    const missingList = (items: Row[], empty: string) =>
        items.length === 0 ? (
            <p className="p-5 text-sm text-muted-foreground">{empty}</p>
        ) : (
            <ul className="m-0 max-h-64 list-none divide-y divide-border overflow-y-auto p-0">
                {items.map((item) => (
                    <li key={item.id} className="px-5 py-3 text-sm">
                        <p className="truncate font-medium text-foreground">
                            {item.name}
                        </p>
                        <p className="truncate text-xs text-muted-foreground">
                            {item.inventory_code ?? 'Tanpa kode'} ·{' '}
                            {item.category} · {item.location}
                        </p>
                    </li>
                ))}
            </ul>
        );

    return (
        <div className="mx-auto w-full max-w-7xl space-y-6">
            <Head title="Laporan Aset" />
            <PageHeader
                eyebrow="Sarana Prasarana"
                title="Laporan Aset"
                description="Daftar aset per kategori/lokasi, rekap kondisi, dan aset dengan data belum lengkap."
                actions={
                    <Button
                        type="primary"
                        icon={<DownloadOutlined />}
                        size="large"
                        onClick={() => {
                            window.location.href = assetsReport({
                                query: { ...query, export: 'csv' },
                            }).url;
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
                            Kategori
                        </span>
                        <Select
                            placeholder="Semua kategori"
                            allowClear
                            className="w-full sm:w-44"
                            value={filters.category_id || undefined}
                            onChange={(value) =>
                                setFilters((f) => ({
                                    ...f,
                                    category_id: value ?? '',
                                }))
                            }
                            options={categories.map((category) => ({
                                value: category.id,
                                label: category.name,
                            }))}
                        />
                    </label>
                    <label className="text-sm">
                        <span className="mb-1 block text-muted-foreground">
                            Lokasi
                        </span>
                        <Select
                            placeholder="Semua lokasi"
                            allowClear
                            className="w-full sm:w-44"
                            value={filters.location_id || undefined}
                            onChange={(value) =>
                                setFilters((f) => ({
                                    ...f,
                                    location_id: value ?? '',
                                }))
                            }
                            options={locations.map((location) => ({
                                value: location.id,
                                label: location.name,
                            }))}
                        />
                    </label>
                    <label className="text-sm">
                        <span className="mb-1 block text-muted-foreground">
                            Kondisi
                        </span>
                        <Select
                            placeholder="Semua kondisi"
                            allowClear
                            className="w-full sm:w-40"
                            value={filters.condition || undefined}
                            onChange={(value) =>
                                setFilters((f) => ({
                                    ...f,
                                    condition: value ?? '',
                                }))
                            }
                            options={conditions}
                        />
                    </label>
                    <label className="text-sm">
                        <span className="mb-1 block text-muted-foreground">
                            Status
                        </span>
                        <Select
                            placeholder="Semua status"
                            allowClear
                            className="w-full sm:w-40"
                            value={filters.status || undefined}
                            onChange={(value) =>
                                setFilters((f) => ({
                                    ...f,
                                    status: value ?? '',
                                }))
                            }
                            options={statuses}
                        />
                    </label>
                    <Button type="primary" onClick={navigate}>
                        Terapkan
                    </Button>
                </div>

                <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    {stats.map((stat) => (
                        <div
                            key={stat.label}
                            className="rounded-xl border border-border p-3"
                        >
                            <div className="text-xs text-muted-foreground">
                                {stat.label}
                            </div>
                            <div className="text-xl font-semibold tabular-nums">
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

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Panel
                    title="Aset Tanpa Kode"
                    description={`${summary.without_code} aset belum memiliki kode inventaris`}
                    bodyClassName="p-0 sm:p-0"
                >
                    {missingList(missing_code, 'Semua aset sudah memiliki kode.')}
                </Panel>
                <Panel
                    title="Aset Tanpa Foto"
                    description={`${summary.without_photo} aset belum memiliki foto`}
                    bodyClassName="p-0 sm:p-0"
                >
                    {missingList(missing_photo, 'Semua aset sudah memiliki foto.')}
                </Panel>
            </div>
        </div>
    );
}

AssetReport.layout = () => ({
    breadcrumbs: [
        { title: 'Sarana Prasarana', href: '#' },
        { title: 'Laporan Aset', href: assetsReport().url },
    ],
});
