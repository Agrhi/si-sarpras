import { UploadOutlined } from '@ant-design/icons';
import { Head, useForm } from '@inertiajs/react';
import { App, Button, Form, Input, Select } from 'antd';
import { useRef, useState } from 'react';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/Sarpras/DisposalController';
import PageHeader from '@/components/page-header';
import Panel from '@/components/panel';

interface Option {
    value: number;
    label: string;
}

interface Props {
    assets: Option[];
}

export default function DisposalCreate({ assets }: Props) {
    const { message } = App.useApp();
    const fileInput = useRef<HTMLInputElement>(null);
    const [fileName, setFileName] = useState<string | null>(null);

    const { data, setData, post, processing, errors } = useForm<{
        asset_id: number | null;
        reason: string;
        evidence: File | null;
    }>({
        asset_id: null,
        reason: '',
        evidence: null,
    });

    const onFinish = () => {
        post(store().url, {
            forceFormData: true,
            onSuccess: () =>
                message.success('Usulan penghapusan berhasil dikirim'),
        });
    };

    return (
        <div className="mx-auto w-full max-w-3xl space-y-6">
            <Head title="Ajukan Penghapusan Aset" />
            <PageHeader
                eyebrow="Sarana Prasarana / Penghapusan Aset"
                title="Ajukan Penghapusan Aset"
                description="Sertakan alasan dan bukti pendukung agar usulan dapat dievaluasi."
            />

            <Form layout="vertical" onFinish={onFinish}>
                <Panel title="Detail usulan">
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
                                onChange={(value) =>
                                    setData('asset_id', value)
                                }
                                options={assets}
                            />
                        </Form.Item>

                        <Form.Item
                            label="Alasan Penghapusan"
                            required
                            validateStatus={errors.reason ? 'error' : ''}
                            help={errors.reason}
                            className="!mb-0"
                        >
                            <Input.TextArea
                                rows={5}
                                placeholder="Jelaskan kondisi aset dan alasan penghapusan."
                                value={data.reason}
                                onChange={(e) =>
                                    setData('reason', e.target.value)
                                }
                            />
                        </Form.Item>

                        <Form.Item
                            label="Bukti Pendukung"
                            validateStatus={errors.evidence ? 'error' : ''}
                            help={errors.evidence}
                            className="!mb-0"
                        >
                            <input
                                ref={fileInput}
                                type="file"
                                accept="image/jpeg,image/png,image/webp,application/pdf"
                                className="hidden"
                                onChange={(e) => {
                                    const file = e.target.files?.[0] ?? null;

                                    setData('evidence', file);
                                    setFileName(file?.name ?? null);
                                }}
                            />
                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    icon={<UploadOutlined />}
                                    onClick={() => fileInput.current?.click()}
                                >
                                    Pilih Berkas
                                </Button>
                                <span className="text-sm text-muted-foreground">
                                    {fileName ??
                                        'Foto atau PDF, maks. 4 MB (opsional).'}
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
                        Kirim Usulan
                    </Button>
                </div>
            </Form>
        </div>
    );
}

DisposalCreate.layout = () => ({
    breadcrumbs: [
        { title: 'Sarana Prasarana', href: '#' },
        { title: 'Penghapusan Aset', href: index().url },
        { title: 'Baru', href: '#' },
    ],
});
