import { UploadOutlined } from '@ant-design/icons';
import { Head, useForm } from '@inertiajs/react';
import { App, Button, Form, Input, Select } from 'antd';
import { useRef, useState } from 'react';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/Sarpras/MaintenanceReportController';
import PageHeader from '@/components/page-header';
import Panel from '@/components/panel';

interface Option {
    value: number | string;
    label: string;
}

interface Props {
    assets: Option[];
    urgencies: Option[];
}

export default function MaintenanceReportCreate({ assets, urgencies }: Props) {
    const { message } = App.useApp();
    const fileInput = useRef<HTMLInputElement>(null);
    const [fileName, setFileName] = useState<string | null>(null);

    const { data, setData, post, processing, errors } = useForm<{
        asset_id: number | null;
        urgency: string;
        description: string;
        photo: File | null;
    }>({
        asset_id: null,
        urgency: 'sedang',
        description: '',
        photo: null,
    });

    const onFinish = () => {
        post(store().url, {
            forceFormData: true,
            onSuccess: () => message.success('Laporan berhasil dikirim'),
        });
    };

    return (
        <div className="mx-auto w-full max-w-3xl space-y-6">
            <Head title="Buat Laporan Kerusakan" />
            <PageHeader
                eyebrow="Sarana Prasarana / Laporan Kerusakan"
                title="Buat Laporan Kerusakan"
                description="Sertakan deskripsi kerusakan dan foto bila tersedia agar petugas lebih cepat menindaklanjuti."
            />

            <Form layout="vertical" onFinish={onFinish}>
                <Panel title="Detail kerusakan">
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
                            label="Tingkat Urgensi"
                            required
                            validateStatus={errors.urgency ? 'error' : ''}
                            help={errors.urgency}
                            className="!mb-0"
                        >
                            <Select
                                value={data.urgency}
                                onChange={(value) => setData('urgency', value)}
                                options={urgencies}
                            />
                        </Form.Item>

                        <Form.Item
                            label="Deskripsi Kerusakan"
                            required
                            validateStatus={errors.description ? 'error' : ''}
                            help={errors.description}
                            className="!mb-0"
                        >
                            <Input.TextArea
                                rows={5}
                                placeholder="Jelaskan gejala kerusakan, kapan terjadi, dan dampaknya."
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                            />
                        </Form.Item>

                        <Form.Item
                            label="Foto Lampiran"
                            validateStatus={errors.photo ? 'error' : ''}
                            help={errors.photo}
                            className="!mb-0"
                        >
                            <input
                                ref={fileInput}
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                className="hidden"
                                onChange={(e) => {
                                    const file = e.target.files?.[0] ?? null;

                                    setData('photo', file);
                                    setFileName(file?.name ?? null);
                                }}
                            />
                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    icon={<UploadOutlined />}
                                    onClick={() => fileInput.current?.click()}
                                >
                                    Pilih Foto
                                </Button>
                                <span className="text-sm text-muted-foreground">
                                    {fileName ?? 'Belum ada foto dipilih (maks. 2 MB).'}
                                </span>
                            </div>
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
                        Kirim Laporan
                    </Button>
                </div>
            </Form>
        </div>
    );
}

MaintenanceReportCreate.layout = () => ({
    breadcrumbs: [
        { title: 'Sarana Prasarana', href: '#' },
        { title: 'Laporan Kerusakan', href: index().url },
        { title: 'Baru', href: '#' },
    ],
});
