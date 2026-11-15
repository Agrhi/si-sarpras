import { Head, router } from '@inertiajs/react';
import { Button, Input, Select, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useState } from 'react';
import { index } from '@/actions/App/Http/Controllers/Sarpras/AuditLogController';
import DataTable from '@/components/data-table';
import PageHeader from '@/components/page-header';
import Panel from '@/components/panel';

interface Log {
    id: number;
    user: string | null;
    action: string;
    summary: string;
    created_at: string;
}

interface Props {
    logs: Log[];
    total: number;
    page: number;
    perPage: number;
    search: string;
    users: { id: number; name: string }[];
    userId: number | null;
    from: string | null;
    to: string | null;
}

export default function AuditLogIndex({
    logs,
    total,
    page,
    perPage,
    search,
    users,
    userId,
    from,
    to,
}: Props) {
    const [filters, setFilters] = useState({
        userId: userId ? String(userId) : '',
        from: from ?? '',
        to: to ?? '',
    });
    const columns: ColumnsType<Log> = [
        {
            title: 'Waktu',
            dataIndex: 'created_at',
            key: 'created_at',
            width: 180,
        },
        {
            title: 'Aktor',
            dataIndex: 'user',
            key: 'user',
            width: 180,
            render: (text) => text ?? 'Sistem',
        },
        {
            title: 'Ringkasan',
            dataIndex: 'summary',
            key: 'summary',
        },
        {
            title: 'Aksi',
            dataIndex: 'action',
            key: 'action',
            width: 220,
            render: (text) => <Tag>{text}</Tag>,
        },
    ];

    const navigate = (params: Record<string, any>) => {
        router.get(
            index().url,
            {
                user_id: filters.userId || undefined,
                from: filters.from || undefined,
                to: filters.to || undefined,
                ...params,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <div className="mx-auto w-full max-w-7xl space-y-6">
            <Head title="Audit Log" />
            <PageHeader
                eyebrow="Sarana Prasarana"
                title="Audit Log"
                description="Jejak aktivitas pengguna, terbaru di atas. Hanya bisa dibaca."
            />
            <Panel bodyClassName="p-4 sm:p-6">
                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <label className="text-sm">
                        <span className="mb-1 block text-muted-foreground">
                            Pengguna
                        </span>
                        <Select
                            placeholder="Semua pengguna"
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            className="w-full sm:w-56"
                            value={filters.userId || undefined}
                            onChange={(value) =>
                                setFilters((f) => ({
                                    ...f,
                                    userId: value ?? '',
                                }))
                            }
                            options={users.map((user) => ({
                                value: String(user.id),
                                label: user.name,
                            }))}
                        />
                    </label>
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
                    <Button
                        type="primary"
                        onClick={() => navigate({ page: 1, perPage, search })}
                    >
                        Terapkan
                    </Button>
                </div>
                <DataTable
                    columns={columns}
                    data={logs}
                    rowKey="id"
                    total={total}
                    page={page}
                    perPage={perPage}
                    search={search}
                    onPageChange={(p) => navigate({ page: p, perPage, search })}
                    onPerPageChange={(pp) =>
                        navigate({ page: 1, perPage: pp, search })
                    }
                    onSearchChange={(s) =>
                        navigate({ page: 1, perPage, search: s })
                    }
                    searchPlaceholder="Cari ringkasan atau nama pengguna..."
                />
            </Panel>
        </div>
    );
}

AuditLogIndex.layout = () => ({
    breadcrumbs: [
        { title: 'Sarana Prasarana', href: '#' },
        { title: 'Audit Log', href: index().url },
    ],
});
