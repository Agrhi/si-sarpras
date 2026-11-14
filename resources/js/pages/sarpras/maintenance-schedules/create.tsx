import { Head, useForm } from '@inertiajs/react';
import { App, Button, DatePicker, Form, Input, Select, Switch } from 'antd';
import dayjs from 'dayjs';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/Sarpras/MaintenanceScheduleController';
import PageHeader from '@/components/page-header';
import Panel from '@/components/panel';

interface Option {
    value: number | string;
    label: string;
}

interface Props {
    assets: Option[];
    technicians: Option[];
    frequencies: Option[];
}

export default function MaintenanceScheduleCreate({
    assets,
    technicians,
    frequencies,
}: Props) {
    const { message } = App.useApp();
    const { data, setData, post, processing, errors } = useForm<{
        asset_id: number | null;
        assigned_to: number | null;
        title: string;
        description: string;
        frequency: string;
        next_due_at: string;
        is_active: boolean;
    }>({
        asset_id: null,
        assigned_to: null,
        title: '',
        description: '',
        frequency: 'bulanan',
        next_due_at: '',
        is_active: true,
    });

    const onFinish = () => {
        post(store().url, {
            onSuccess: () => message.success('Jadwal berhasil dibuat'),
        });
    };

    return (
        <div className="mx-auto w-full max-w-3xl space-y-6">
            <Head title="Buat Jadwal Pemeliharaan" />
            <PageHeader
                eyebrow="Sarana Prasarana / Jadwal Pemeliharaan"
                title="Buat Jadwal Pemeliharaan"
                description="Tentukan aset, frekuensi, dan petugas penanggung jawab pemeliharaan rutin."
            />

            <Form layout="vertical" onFinish={onFinish}>
                <Panel title="Detail jadwal">
                    <div className="space-y-6">
                        <Form.Item
                            label="Aset"
                            required
                            validateStatus={errors.asset_id ? 'error' : ''}
                            help={errors.asset_id}
                            className="!mb-0"
                        >
                            <Select
                                showSearch
                                optionFilterProp="label"
                                placeholder="Cari dan pilih aset"
                                value={data.asset_id ?? undefined}
                                onChange={(value) => setData('asset_id', value)}
                                options={assets}
                            />
                        </Form.Item>

                        <Form.Item
                            label="Nama Kegiatan"
                            required
                            validateStatus={errors.title ? 'error' : ''}
                            help={errors.title}
                            className="!mb-0"
                        >
                            <Input
                                placeholder="mis. Pembersihan filter proyektor"
                                value={data.title}
                                onChange={(e) =>
                                    setData('title', e.target.value)
                                }
                            />
                        </Form.Item>

                        <Form.Item
                            label="Deskripsi"
                            validateStatus={errors.description ? 'error' : ''}
                            help={errors.description}
                            className="!mb-0"
                        >
                            <Input.TextArea
                                rows={3}
                                placeholder="Rincian pekerjaan pemeliharaan (opsional)."
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                            />
                        </Form.Item>

                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <Form.Item
                                label="Frekuensi"
                                required
                                validateStatus={
                                    errors.frequency ? 'error' : ''
                                }
                                help={errors.frequency}
                                className="!mb-0"
                            >
                                <Select
                                    value={data.frequency}
                                    onChange={(value) =>
                                        setData('frequency', value)
                                    }
                                    options={frequencies}
                                />
                            </Form.Item>
                            <Form.Item
                                label="Jatuh Tempo Berikutnya"
                                required
                                validateStatus={
                                    errors.next_due_at ? 'error' : ''
                                }
                                help={errors.next_due_at}
                                className="!mb-0"
                            >
                                <DatePicker
                                    className="w-full"
                                    format="YYYY-MM-DD"
                                    value={
                                        data.next_due_at
                                            ? dayjs(data.next_due_at)
                                            : undefined
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'next_due_at',
                                            value
                                                ? value.format('YYYY-MM-DD')
                                                : '',
                                        )
                                    }
                                />
                            </Form.Item>
                        </div>

                        <Form.Item
                            label="Petugas Penanggung Jawab"
                            validateStatus={
                                errors.assigned_to ? 'error' : ''
                            }
                            help={errors.assigned_to}
                            className="!mb-0"
                        >
                            <Select
                                allowClear
                                placeholder="Pilih petugas teknis (opsional)"
                                value={data.assigned_to ?? undefined}
                                onChange={(value) =>
                                    setData('assigned_to', value ?? null)
                                }
                                options={technicians}
                            />
                        </Form.Item>

                        <Form.Item label="Aktif" className="!mb-0">
                            <Switch
                                checked={data.is_active}
                                onChange={(checked) =>
                                    setData('is_active', checked)
                                }
                            />
                        </Form.Item>
                    </div>
                </Panel>

                <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button size="large" href={index().url}>
                        Batal
                    </Button>
                    <Button
                        type="primary"
                        htmlType="submit"
                        loading={processing}
                        size="large"
                    >
                        Simpan Jadwal
                    </Button>
                </div>
            </Form>
        </div>
    );
}

MaintenanceScheduleCreate.layout = () => ({
    breadcrumbs: [
        { title: 'Sarana Prasarana', href: '#' },
        { title: 'Jadwal Pemeliharaan', href: index().url },
        { title: 'Baru', href: '#' },
    ],
});
