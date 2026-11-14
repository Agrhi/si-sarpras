import { EyeOutlined, PlusOutlined } from '@ant-design/icons';
import { Head, Link, router } from '@inertiajs/react';
import { Button, Checkbox, Select, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import {
    create,
    index,
    show,
} from '@/actions/App/Http/Controllers/Sarpras/MaintenanceReportController';
import DataTable from '@/components/data-table';
import PageHeader from '@/components/page-header';
import Panel from '@/components/panel';

interface Option {
    value: string;
    label: string;
}

interface Report {
    id: number;
    asset: string;
    reporter: string | null;
    assignee: string | null;
    urgency: Option;
    status: Option;
    created_at: string;
}

interface Props {
    reports: Report[];
    total: number;
    page: number;
    perPage: number;
    search: string;
    status: string;
    urgency: string;
    mine: boolean;
    statuses: Option[];
    urgencies: Option[];
    can_create: boolean;
    can_process: boolean;
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

export default function MaintenanceReportIndex({
    reports,
    total,
    page,
    perPage,
    search,
    status,
    urgency,
    mine,
    statuses,
    urgencies,
    can_create,
    can_process,
}: Props) {
    const columns: ColumnsType<Report> = [
        {
            title: 'Aset',
            dataIndex: 'asset',
            key: 'asset',
            render: (text, record) => (
                <Link href={show(record.id).url} className="font-bold text-primary">
                    {text}
                </Link>
            ),
        },
        {
            title: 'Pelapor',
            dataIndex: 'reporter',
            key: 'reporter',
            width: 160,
        },
        {
            title: 'Petugas',
            dataIndex: 'assignee',
            key: 'assignee',
            width: 160,
            render: (text) => text ?? '-',
        },
        {
            title: 'Urgensi',
            dataIndex: 'urgency',
            key: 'urgency',
            width: 120,
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
            title: 'Dilaporkan',
            dataIndex: 'created_at',
            key: 'created_at',
            width: 150,
        },
        {
            title: 'Aksi',
            key: 'actions',
            width: 60,
            render: (_, record) => (
                <Button
                    type="text"
                    size="small"
                    icon={<EyeOutlined />}
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
            <Head title="Laporan Kerusakan" />
            <PageHeader
                eyebrow="Sarana Prasarana"
                title="Laporan Kerusakan"
                description="Laporkan kerusakan aset dan pantau proses perbaikannya."
                actions={
                    can_create ? (
                        <Link href={create().url}>
                            <Button
                                type="primary"
                                icon={<PlusOutlined />}
                                size="large"
                            >
                                Buat Laporan
                            </Button>
                        </Link>
                    ) : undefined
                }
            />
            <Panel bodyClassName="p-4 sm:p-6">
                <div className="mb-4 flex flex-wrap gap-3">
                    <Select
                        placeholder="Status"
                        allowClear
                        className="w-full sm:w-48"
                        value={status || undefined}
                        onChange={(value) =>
                            navigate({
                                page: 1,
                                perPage,
                                search,
                                status: value,
                                urgency,
                                mine: mine ? 1 : 0,
                            })
                        }
                        options={statuses}
                    />
                    <Select
                        placeholder="Urgensi"
                        allowClear
                        className="w-full sm:w-48"
                        value={urgency || undefined}
                        onChange={(value) =>
                            navigate({
                                page: 1,
                                perPage,
                                search,
                                status,
                                urgency: value,
                                mine: mine ? 1 : 0,
                            })
                        }
                        options={urgencies}
                    />
                    {can_process && (
                        <Checkbox
                            checked={mine}
                            onChange={(e) =>
                                navigate({
                                    page: 1,
                                    perPage,
                                    search,
                                    status,
                                    urgency,
                                    mine: e.target.checked ? 1 : 0,
                                })
                            }
                        >
                            Hanya tugas saya
                        </Checkbox>
                    )}
                </div>
                <DataTable
                    columns={columns}
                    data={reports}
                    rowKey="id"
                    total={total}
                    page={page}
                    perPage={perPage}
                    search={search}
                    onPageChange={(p) =>
                        navigate({
                            page: p,
                            perPage,
                            search,
                            status,
                            urgency,
                            mine: mine ? 1 : 0,
                        })
                    }
                    onPerPageChange={(pp) =>
                        navigate({
                            page: 1,
                            perPage: pp,
                            search,
                            status,
                            urgency,
                            mine: mine ? 1 : 0,
                        })
                    }
                    onSearchChange={(s) =>
                        navigate({
                            page: 1,
                            perPage,
                            search: s,
                            status,
                            urgency,
                            mine: mine ? 1 : 0,
                        })
                    }
                    searchPlaceholder="Cari aset atau deskripsi..."
                />
            </Panel>
        </div>
    );
}

MaintenanceReportIndex.layout = () => ({
    breadcrumbs: [
        { title: 'Sarana Prasarana', href: '#' },
        { title: 'Laporan Kerusakan', href: index().url },
    ],
});
