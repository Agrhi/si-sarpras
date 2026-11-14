import {
    CheckCircleOutlined,
    DeleteOutlined,
    EditOutlined,
    PlusOutlined,
} from '@ant-design/icons';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { App, Button, DatePicker, Form, Modal, Select, Space, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import dayjs from 'dayjs';
import { useState } from 'react';
import {
    complete,
    create,
    destroy,
    edit,
    index,
} from '@/actions/App/Http/Controllers/Sarpras/MaintenanceScheduleController';
import DataTable from '@/components/data-table';
import PageHeader from '@/components/page-header';
import Panel from '@/components/panel';

interface Option {
    value: string;
    label: string;
}

interface Schedule {
    id: number;
    asset: string;
    title: string;
    frequency: Option;
    assignee: string | null;
    next_due_at: string;
    last_performed_at: string | null;
    due_state: string;
    is_active: boolean;
}

interface Props {
    schedules: Schedule[];
    total: number;
    page: number;
    perPage: number;
    search: string;
    state: string;
    states: Option[];
    can_create: boolean;
    can_edit: boolean;
    can_delete: boolean;
    can_execute: boolean;
}

const STATE_LABELS: Record<string, string> = {
    terjadwal: 'Terjadwal',
    'jatuh-tempo': 'Jatuh Tempo',
    terlambat: 'Terlambat',
    nonaktif: 'Nonaktif',
};

const STATE_COLORS: Record<string, string> = {
    terjadwal: 'blue',
    'jatuh-tempo': 'gold',
    terlambat: 'red',
    nonaktif: 'default',
};

export default function MaintenanceScheduleIndex({
    schedules,
    total,
    page,
    perPage,
    search,
    state,
    states,
    can_create,
    can_edit,
    can_delete,
    can_execute,
}: Props) {
    const { message } = App.useApp();
    const [selected, setSelected] = useState<Schedule | null>(null);
    const { data, setData, patch, processing, reset } = useForm({
        performed_at: '',
    });

    const openRealization = (schedule: Schedule) => {
        reset();
        setSelected(schedule);
    };

    const submitRealization = () => {
        if (!selected) {
            return;
        }

        patch(complete(selected.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                setSelected(null);
                reset();
                message.success('Realisasi pemeliharaan tercatat.');
            },
        });
    };

    const columns: ColumnsType<Schedule> = [
        {
            title: 'Aset',
            dataIndex: 'asset',
            key: 'asset',
            render: (text) => <span className="font-bold">{text}</span>,
        },
        {
            title: 'Kegiatan',
            dataIndex: 'title',
            key: 'title',
            ellipsis: true,
        },
        {
            title: 'Frekuensi',
            dataIndex: 'frequency',
            key: 'frequency',
            width: 130,
            render: (value: Option) => value.label,
        },
        {
            title: 'Petugas',
            dataIndex: 'assignee',
            key: 'assignee',
            width: 160,
            render: (text) => text ?? '-',
        },
        {
            title: 'Jatuh Tempo',
            dataIndex: 'next_due_at',
            key: 'next_due_at',
            width: 130,
        },
        {
            title: 'Realisasi Terakhir',
            dataIndex: 'last_performed_at',
            key: 'last_performed_at',
            width: 150,
            render: (text) => text ?? '-',
        },
        {
            title: 'Status',
            dataIndex: 'due_state',
            key: 'due_state',
            width: 130,
            render: (value: string) => (
                <Tag color={STATE_COLORS[value] ?? 'default'}>
                    {STATE_LABELS[value] ?? value}
                </Tag>
            ),
        },
        {
            title: 'Aksi',
            key: 'actions',
            width: 130,
            render: (_, record) => (
                <Space size="small">
                    {can_execute && record.is_active && (
                        <Button
                            type="text"
                            size="small"
                            icon={<CheckCircleOutlined />}
                            title="Catat realisasi"
                            onClick={() => openRealization(record)}
                        />
                    )}
                    {can_edit && (
                        <Button
                            type="text"
                            size="small"
                            icon={<EditOutlined />}
                            title="Edit"
                            onClick={() => router.get(edit(record.id).url)}
                        />
                    )}
                    {can_delete && (
                        <Button
                            type="text"
                            danger
                            size="small"
                            icon={<DeleteOutlined />}
                            title="Hapus"
                            onClick={() =>
                                Modal.confirm({
                                    title: 'Hapus jadwal',
                                    content:
                                        'Hapus jadwal pemeliharaan ini? Jadwal yang sudah memiliki realisasi akan dinonaktifkan.',
                                    okText: 'Ya',
                                    cancelText: 'Tidak',
                                    okType: 'danger',
                                    onOk: () =>
                                        router.delete(
                                            destroy(record.id).url,
                                            { preserveScroll: true },
                                        ),
                                })
                            }
                        />
                    )}
                </Space>
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
            <Head title="Jadwal Pemeliharaan" />
            <PageHeader
                eyebrow="Sarana Prasarana"
                title="Jadwal Pemeliharaan"
                description="Atur pemeliharaan rutin aset dan pantau jadwal yang mendekati jatuh tempo."
                actions={
                    can_create ? (
                        <Link href={create().url}>
                            <Button
                                type="primary"
                                icon={<PlusOutlined />}
                                size="large"
                            >
                                Buat Jadwal
                            </Button>
                        </Link>
                    ) : undefined
                }
            />
            <Panel bodyClassName="p-4 sm:p-6">
                <div className="mb-4 max-w-xs">
                    <Select
                        placeholder="Status jadwal"
                        allowClear
                        className="w-full"
                        value={state || undefined}
                        onChange={(value) =>
                            navigate({
                                page: 1,
                                perPage,
                                search,
                                state: value,
                            })
                        }
                        options={states}
                    />
                </div>
                <DataTable
                    columns={columns}
                    data={schedules}
                    rowKey="id"
                    total={total}
                    page={page}
                    perPage={perPage}
                    search={search}
                    onPageChange={(p) =>
                        navigate({ page: p, perPage, search, state })
                    }
                    onPerPageChange={(pp) =>
                        navigate({ page: 1, perPage: pp, search, state })
                    }
                    onSearchChange={(s) =>
                        navigate({ page: 1, perPage, search: s, state })
                    }
                    searchPlaceholder="Cari kegiatan atau aset..."
                />
            </Panel>

            <Modal
                title={
                    selected
                        ? `Catat realisasi: ${selected.title}`
                        : 'Catat realisasi'
                }
                open={selected !== null}
                okText="Simpan"
                cancelText="Batal"
                confirmLoading={processing}
                onOk={submitRealization}
                onCancel={() => setSelected(null)}
            >
                <p className="mb-3 text-sm opacity-70">
                    Tanggal realisasi menentukan jadwal berikutnya. Kosongkan
                    untuk memakai tanggal hari ini.
                </p>
                <Form layout="vertical">
                    <Form.Item label="Tanggal Realisasi" className="!mb-0">
                        <DatePicker
                            className="w-full"
                            format="YYYY-MM-DD"
                            value={
                                data.performed_at
                                    ? dayjs(data.performed_at)
                                    : undefined
                            }
                            onChange={(value) =>
                                setData(
                                    'performed_at',
                                    value ? value.format('YYYY-MM-DD') : '',
                                )
                            }
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </div>
    );
}

MaintenanceScheduleIndex.layout = () => ({
    breadcrumbs: [
        { title: 'Sarana Prasarana', href: '#' },
        { title: 'Jadwal Pemeliharaan', href: index().url },
    ],
});
