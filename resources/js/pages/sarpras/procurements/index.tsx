import { EyeOutlined, PlusOutlined } from '@ant-design/icons';
import { Head, Link, router } from '@inertiajs/react';
import { Button, Select, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import {
    create,
    index,
    show,
} from '@/actions/App/Http/Controllers/Sarpras/ProcurementController';
import DataTable from '@/components/data-table';
import PageHeader from '@/components/page-header';
import Panel from '@/components/panel';

interface Option {
    value: string;
    label: string;
}

interface Procurement {
    id: number;
    title: string;
    proposer: string;
    item_count: number;
    estimated_total: number;
    created_at: string;
    status: Option;
}

interface Props {
    procurements: Procurement[];
    total: number;
    page: number;
    perPage: number;
    search: string;
    status: string;
    statuses: Option[];
    can_create: boolean;
}

const STATUS_COLORS: Record<string, string> = {
    pending: 'gold',
    approved: 'cyan',
    rejected: 'red',
    received: 'green',
};

export default function ProcurementIndex({
    procurements,
    total,
    page,
    perPage,
    search,
    status,
    statuses,
    can_create,
}: Props) {
    const columns: ColumnsType<Procurement> = [
        {
            title: 'Usulan',
            dataIndex: 'title',
            key: 'title',
            ellipsis: true,
            render: (text, record) => (
                <Link href={show(record.id).url} className="font-bold text-primary">
                    {text}
                </Link>
            ),
        },
        {
            title: 'Pengusul',
            dataIndex: 'proposer',
            key: 'proposer',
            width: 160,
        },
        {
            title: 'Item',
            dataIndex: 'item_count',
            key: 'item_count',
            width: 80,
        },
        {
            title: 'Estimasi',
            dataIndex: 'estimated_total',
            key: 'estimated_total',
            width: 150,
            render: (value: number) =>
                `Rp ${Number(value).toLocaleString('id-ID')}`,
        },
        {
            title: 'Tanggal',
            dataIndex: 'created_at',
            key: 'created_at',
            width: 110,
        },
        {
            title: 'Status',
            dataIndex: 'status',
            key: 'status',
            width: 170,
            render: (value: Option) => (
                <Tag color={STATUS_COLORS[value.value] ?? 'default'}>
                    {value.label}
                </Tag>
            ),
        },
        {
            title: 'Aksi',
            key: 'actions',
            width: 60,
            render: (_, record) => (
                <Button
                    type="text"
                    icon={<EyeOutlined />}
                    size="small"
                    onClick={() => router.get(show(record.id).url)}
                />
            ),
        },
    ];

    const navigate = (params: Record<string, any>) => {
        router.get(
            index().url,
            { ...params },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <div className="mx-auto w-full max-w-7xl space-y-6">
            <Head title="Pengadaan" />
            <PageHeader
                eyebrow="Sarana Prasarana"
                title="Pengadaan"
                description="Usulkan pengadaan barang, pantau persetujuan, lalu terima barang menjadi aset inventaris."
                actions={
                    can_create ? (
                        <Link href={create().url}>
                            <Button
                                type="primary"
                                icon={<PlusOutlined />}
                                size="large"
                            >
                                Ajukan Pengadaan
                            </Button>
                        </Link>
                    ) : undefined
                }
            />
            <Panel bodyClassName="p-4 sm:p-6">
                <div className="mb-4 max-w-xs">
                    <Select
                        placeholder="Status"
                        allowClear
                        className="w-full"
                        value={status || undefined}
                        onChange={(value) =>
                            navigate({
                                page: 1,
                                perPage,
                                search,
                                status: value,
                            })
                        }
                        options={statuses}
                    />
                </div>
                <DataTable
                    columns={columns}
                    data={procurements}
                    rowKey="id"
                    total={total}
                    page={page}
                    perPage={perPage}
                    search={search}
                    onPageChange={(p) =>
                        navigate({ page: p, perPage, search, status })
                    }
                    onPerPageChange={(pp) =>
                        navigate({ page: 1, perPage: pp, search, status })
                    }
                    onSearchChange={(s) =>
                        navigate({ page: 1, perPage, search: s, status })
                    }
                    searchPlaceholder="Cari usulan..."
                />
            </Panel>
        </div>
    );
}

ProcurementIndex.layout = () => ({
    breadcrumbs: [
        { title: 'Sarana Prasarana', href: '#' },
        { title: 'Pengadaan', href: index().url },
    ],
});
