import {
    CheckOutlined,
    CloseOutlined,
    DeleteOutlined,
    UploadOutlined,
} from '@ant-design/icons';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    Button,
    Descriptions,
    Input,
    Modal,
    Select,
    Tag,
    Timeline,
} from 'antd';
import { useRef, useState } from 'react';
import { show as assetShow } from '@/actions/App/Http/Controllers/Sarpras/AssetController';
import {
    approve,
    complete,
    index,
    reject,
    show,
} from '@/actions/App/Http/Controllers/Sarpras/DisposalController';
import PageHeader from '@/components/page-header';
import Panel from '@/components/panel';

interface Option {
    value: string;
    label: string;
}

interface Step {
    title: string;
    role: string;
    state: string;
    approval: {
        user: string;
        decision: string;
        note: string | null;
        created_at: string;
    } | null;
}

interface Props {
    disposal: {
        id: number;
        proposer: string;
        asset: {
            id: number;
            inventory_code: string;
            name: string;
            status: string;
        } | null;
        reason: string;
        created_at: string;
        status: Option;
        evidence_url: string | null;
        method: string | null;
        realized_at: string | null;
        document_url: string | null;
        steps: Step[];
    };
    methods: Option[];
    can_approve: boolean;
    can_complete: boolean;
}

const STATUS_COLORS: Record<string, string> = {
    pending: 'gold',
    approved: 'cyan',
    rejected: 'red',
    completed: 'green',
};

const STEP_COLORS: Record<string, string> = {
    approved: 'green',
    rejected: 'red',
    current: 'blue',
    waiting: 'gray',
};

export default function DisposalShow({
    disposal,
    methods,
    can_approve,
    can_complete,
}: Props) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [decisionOpen, setDecisionOpen] = useState<'approve' | 'reject' | null>(null);
    const [completeOpen, setCompleteOpen] = useState(false);
    const [fileName, setFileName] = useState<string | null>(null);

    const decisionForm = useForm({ note: '' });
    const completeForm = useForm<{
        method: string | null;
        realized_at: string;
        document: File | null;
    }>({
        method: null,
        realized_at: '',
        document: null,
    });

    const submitDecision = () => {
        const url =
            decisionOpen === 'approve'
                ? approve(disposal.id).url
                : reject(disposal.id).url;

        decisionForm.post(url, {
            onSuccess: () => {
                setDecisionOpen(null);
                decisionForm.reset('note');
            },
        });
    };

    const submitComplete = () => {
        completeForm.post(complete(disposal.id).url, {
            forceFormData: true,
            onSuccess: () => {
                setCompleteOpen(false);
                setFileName(null);
                completeForm.reset();
            },
        });
    };

    const timelineItems = disposal.steps.map((step, index) => ({
        key: index,
        color: STEP_COLORS[step.state] ?? 'gray',
        children: (
            <div>
                <div className="font-semibold">{step.title}</div>
                <div className="text-xs opacity-60">Peran: {step.role}</div>
                {step.approval && (
                    <div className="mt-1 text-sm">
                        {step.approval.user} · {step.approval.decision} ·{' '}
                        {step.approval.created_at}
                        {step.approval.note && (
                            <div className="opacity-70">
                                Catatan: {step.approval.note}
                            </div>
                        )}
                    </div>
                )}
                {step.state === 'current' && (
                    <Tag color="blue" className="mt-1">
                        Menunggu keputusan
                    </Tag>
                )}
                {step.state === 'waiting' && (
                    <Tag className="mt-1">Menunggu giliran</Tag>
                )}
            </div>
        ),
    }));

    return (
        <div className="mx-auto w-full max-w-7xl space-y-6">
            <Head title={`Penghapusan #${disposal.id}`} />
            <PageHeader
                eyebrow="Sarana Prasarana / Penghapusan Aset"
                title={`Penghapusan #${disposal.id}`}
                description={`Diajukan oleh ${disposal.proposer}.`}
                actions={
                    <div className="flex flex-wrap gap-2">
                        {can_approve && (
                            <>
                                <Button
                                    type="primary"
                                    icon={<CheckOutlined />}
                                    size="large"
                                    loading={decisionForm.processing}
                                    onClick={() => setDecisionOpen('approve')}
                                >
                                    Setujui
                                </Button>
                                <Button
                                    danger
                                    icon={<CloseOutlined />}
                                    size="large"
                                    onClick={() => setDecisionOpen('reject')}
                                >
                                    Tolak
                                </Button>
                            </>
                        )}
                        {can_complete && (
                            <Button
                                type="primary"
                                icon={<DeleteOutlined />}
                                size="large"
                                onClick={() => setCompleteOpen(true)}
                            >
                                Realisasikan
                            </Button>
                        )}
                    </div>
                }
            />

            <Panel title="Detail Penghapusan">
                <Descriptions column={{ xs: 1, sm: 2, lg: 3 }}>
                    <Descriptions.Item label="Pengusul">
                        {disposal.proposer}
                    </Descriptions.Item>
                    <Descriptions.Item label="Status">
                        <Tag
                            color={
                                STATUS_COLORS[disposal.status.value] ?? 'default'
                            }
                        >
                            {disposal.status.label}
                        </Tag>
                    </Descriptions.Item>
                    <Descriptions.Item label="Tanggal Usul">
                        {disposal.created_at}
                    </Descriptions.Item>
                    <Descriptions.Item label="Aset" span={3}>
                        {disposal.asset ? (
                            <span>
                                <Link
                                    href={assetShow(disposal.asset.id).url}
                                    className="font-semibold text-primary"
                                >
                                    {disposal.asset.inventory_code} ·{' '}
                                    {disposal.asset.name}
                                </Link>{' '}
                                <Tag className="ml-2">
                                    {disposal.asset.status}
                                </Tag>
                            </span>
                        ) : (
                            '-'
                        )}
                    </Descriptions.Item>
                    <Descriptions.Item label="Alasan" span={3}>
                        {disposal.reason}
                    </Descriptions.Item>
                    <Descriptions.Item label="Bukti Pendukung" span={3}>
                        {disposal.evidence_url ? (
                            <a
                                href={disposal.evidence_url}
                                target="_blank"
                                rel="noreferrer"
                                className="text-primary"
                            >
                                Lihat berkas
                            </a>
                        ) : (
                            '-'
                        )}
                    </Descriptions.Item>
                    <Descriptions.Item label="Metode">
                        {disposal.method ?? '-'}
                    </Descriptions.Item>
                    <Descriptions.Item label="Tanggal Realisasi">
                        {disposal.realized_at ?? '-'}
                    </Descriptions.Item>
                    <Descriptions.Item label="Dokumen">
                        {disposal.document_url ? (
                            <a
                                href={disposal.document_url}
                                target="_blank"
                                rel="noreferrer"
                                className="text-primary"
                            >
                                Lihat dokumen
                            </a>
                        ) : (
                            '-'
                        )}
                    </Descriptions.Item>
                </Descriptions>
            </Panel>

            <Panel
                title="Alur Persetujuan"
                description="Langkah dievaluasi berurutan sesuai konfigurasi alur."
            >
                {timelineItems.length > 0 ? (
                    <Timeline items={timelineItems} />
                ) : (
                    <p className="text-sm opacity-60">
                        Tidak ada langkah persetujuan.
                    </p>
                )}
            </Panel>

            <Modal
                title={
                    decisionOpen === 'approve'
                        ? 'Setujui penghapusan'
                        : 'Tolak penghapusan'
                }
                open={decisionOpen !== null}
                okText={decisionOpen === 'approve' ? 'Setujui' : 'Tolak'}
                okType={decisionOpen === 'reject' ? 'danger' : 'primary'}
                cancelText="Batal"
                confirmLoading={decisionForm.processing}
                onOk={submitDecision}
                onCancel={() => setDecisionOpen(null)}
            >
                <p className="mb-3 text-sm opacity-70">
                    {decisionOpen === 'approve'
                        ? 'Usulan diteruskan ke langkah berikutnya (atau disetujui penuh bila ini langkah terakhir).'
                        : 'Usulan akan ditolak dan tidak dapat dilanjutkan.'}
                </p>
                <Input.TextArea
                    placeholder="Catatan keputusan (opsional)"
                    value={decisionForm.data.note}
                    onChange={(e) =>
                        decisionForm.setData('note', e.target.value)
                    }
                    rows={3}
                />
            </Modal>

            <Modal
                title="Realisasikan penghapusan"
                open={completeOpen}
                okText="Simpan"
                cancelText="Batal"
                confirmLoading={completeForm.processing}
                onOk={submitComplete}
                onCancel={() => setCompleteOpen(false)}
            >
                <p className="mb-4 text-sm opacity-70">
                    Aset akan berstatus dihapus setelah realisasi dicatat.
                </p>
                <div className="space-y-4">
                    <label className="block text-sm">
                        <span className="mb-1 block text-muted-foreground">
                            Metode
                        </span>
                        <Select
                            className="w-full"
                            placeholder="Pilih metode"
                            value={completeForm.data.method ?? undefined}
                            onChange={(value) =>
                                completeForm.setData('method', value)
                            }
                            options={methods}
                        />
                        {completeForm.errors.method && (
                            <span className="text-xs text-red-500">
                                {completeForm.errors.method}
                            </span>
                        )}
                    </label>
                    <label className="block text-sm">
                        <span className="mb-1 block text-muted-foreground">
                            Tanggal Realisasi
                        </span>
                        <Input
                            type="date"
                            value={completeForm.data.realized_at}
                            onChange={(e) =>
                                completeForm.setData(
                                    'realized_at',
                                    e.target.value,
                                )
                            }
                        />
                        {completeForm.errors.realized_at && (
                            <span className="text-xs text-red-500">
                                {completeForm.errors.realized_at}
                            </span>
                        )}
                    </label>
                    <div className="text-sm">
                        <span className="mb-1 block text-muted-foreground">
                            Dokumen (opsional)
                        </span>
                        <input
                            ref={fileInput}
                            type="file"
                            accept="image/jpeg,image/png,image/webp,application/pdf"
                            className="hidden"
                            onChange={(e) => {
                                const file = e.target.files?.[0] ?? null;

                                completeForm.setData('document', file);
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
                            <span className="text-muted-foreground">
                                {fileName ?? 'Foto atau PDF, maks. 4 MB.'}
                            </span>
                        </div>
                        {completeForm.errors.document && (
                            <span className="text-xs text-red-500">
                                {completeForm.errors.document}
                            </span>
                        )}
                    </div>
                </div>
            </Modal>
        </div>
    );
}

DisposalShow.layout = (page: Props) => ({
    breadcrumbs: [
        { title: 'Sarana Prasarana', href: '#' },
        { title: 'Penghapusan Aset', href: index().url },
        {
            title: `#${page.disposal.id}`,
            href: show(page.disposal.id).url,
        },
    ],
});
