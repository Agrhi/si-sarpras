import {
    CheckOutlined,
    CloseOutlined,
    DownloadOutlined,
} from '@ant-design/icons';
import { Head, useForm } from '@inertiajs/react';
import {
    Button,
    Descriptions,
    Input,
    Modal,
    Table,
    Tag,
    Timeline,
} from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useState } from 'react';
import {
    approve,
    index,
    receive,
    reject,
    show,
} from '@/actions/App/Http/Controllers/Sarpras/ProcurementController';
import PageHeader from '@/components/page-header';
import Panel from '@/components/panel';

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

interface Item {
    id: number;
    name: string;
    category: string | null;
    location: string | null;
    quantity: number;
    unit_price: number;
    estimated_total: number;
    notes: string | null;
}

interface Option {
    value: string;
    label: string;
}

interface Props {
    procurement: {
        id: number;
        title: string;
        proposer: string;
        reason: string;
        created_at: string;
        received_at: string | null;
        status: Option;
        estimated_total: number;
        steps: Step[];
    };
    items: Item[];
    can_approve: boolean;
    can_receive: boolean;
}

const STATUS_COLORS: Record<string, string> = {
    pending: 'gold',
    approved: 'cyan',
    rejected: 'red',
    received: 'green',
};

const STEP_COLORS: Record<string, string> = {
    approved: 'green',
    rejected: 'red',
    current: 'blue',
    waiting: 'gray',
};

const rupiah = (value: number) =>
    `Rp ${Number(value).toLocaleString('id-ID')}`;

export default function ProcurementShow({
    procurement,
    items,
    can_approve,
    can_receive,
}: Props) {
    const { data, setData, post, processing, reset } = useForm({
        note: '',
    });

    const [decisionOpen, setDecisionOpen] = useState<'approve' | 'reject' | null>(null);

    const submitDecision = () => {
        const url =
            decisionOpen === 'approve'
                ? approve(procurement.id).url
                : reject(procurement.id).url;

        post(url, {
            onSuccess: () => {
                setDecisionOpen(null);
                reset('note');
            },
        });
    };

    const itemColumns: ColumnsType<Item> = [
        {
            title: 'Barang',
            dataIndex: 'name',
            key: 'name',
            render: (text) => <span className="font-medium">{text}</span>,
        },
        {
            title: 'Kategori',
            dataIndex: 'category',
            key: 'category',
            width: 140,
            render: (text) => text ?? '-',
        },
        {
            title: 'Lokasi',
            dataIndex: 'location',
            key: 'location',
            width: 140,
            render: (text) => text ?? '-',
        },
        {
            title: 'Jumlah',
            dataIndex: 'quantity',
            key: 'quantity',
            width: 90,
        },
        {
            title: 'Harga Satuan',
            dataIndex: 'unit_price',
            key: 'unit_price',
            width: 150,
            render: (value: number) => rupiah(value),
        },
        {
            title: 'Estimasi Total',
            dataIndex: 'estimated_total',
            key: 'estimated_total',
            width: 160,
            render: (value: number) => rupiah(value),
        },
        {
            title: 'Catatan',
            dataIndex: 'notes',
            key: 'notes',
            ellipsis: true,
            render: (text) => text ?? '-',
        },
    ];

    const timelineItems = procurement.steps.map((step, index) => ({
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
            <Head title={`Pengadaan #${procurement.id}`} />
            <PageHeader
                eyebrow="Sarana Prasarana / Pengadaan"
                title={procurement.title}
                description={`Diajukan oleh ${procurement.proposer}.`}
                actions={
                    <div className="flex flex-wrap gap-2">
                        {can_approve && (
                            <>
                                <Button
                                    type="primary"
                                    icon={<CheckOutlined />}
                                    size="large"
                                    loading={processing}
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
                        {can_receive && (
                            <Button
                                type="primary"
                                icon={<DownloadOutlined />}
                                size="large"
                                onClick={() =>
                                    Modal.confirm({
                                        title: 'Terima barang',
                                        content:
                                            'Barang akan dicatat sebagai aset inventaris sesuai jumlah pada rincian. Lanjutkan?',
                                        okText: 'Ya',
                                        cancelText: 'Batal',
                                        onOk: () =>
                                            post(receive(procurement.id).url),
                                    })
                                }
                            >
                                Terima Barang
                            </Button>
                        )}
                    </div>
                }
            />

            <Panel title="Detail Pengadaan">
                <Descriptions column={{ xs: 1, sm: 2, lg: 3 }}>
                    <Descriptions.Item label="Pengusul">
                        {procurement.proposer}
                    </Descriptions.Item>
                    <Descriptions.Item label="Status">
                        <Tag
                            color={
                                STATUS_COLORS[procurement.status.value] ??
                                'default'
                            }
                        >
                            {procurement.status.label}
                        </Tag>
                    </Descriptions.Item>
                    <Descriptions.Item label="Estimasi Total">
                        {rupiah(procurement.estimated_total)}
                    </Descriptions.Item>
                    <Descriptions.Item label="Tanggal Usul">
                        {procurement.created_at}
                    </Descriptions.Item>
                    <Descriptions.Item label="Tanggal Terima">
                        {procurement.received_at ?? '-'}
                    </Descriptions.Item>
                    <Descriptions.Item label="Alasan" span={3}>
                        {procurement.reason}
                    </Descriptions.Item>
                </Descriptions>
            </Panel>

            <Panel title="Rincian Barang">
                <Table
                    columns={itemColumns}
                    dataSource={items}
                    rowKey="id"
                    size="small"
                    pagination={false}
                    scroll={{ x: 900 }}
                />
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
                        ? 'Setujui pengadaan'
                        : 'Tolak pengadaan'
                }
                open={decisionOpen !== null}
                okText={decisionOpen === 'approve' ? 'Setujui' : 'Tolak'}
                okType={decisionOpen === 'reject' ? 'danger' : 'primary'}
                cancelText="Batal"
                confirmLoading={processing}
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
                    value={data.note}
                    onChange={(e) => setData('note', e.target.value)}
                    rows={3}
                />
            </Modal>
        </div>
    );
}

ProcurementShow.layout = (page: Props) => ({
    breadcrumbs: [
        { title: 'Sarana Prasarana', href: '#' },
        { title: 'Pengadaan', href: index().url },
        { title: `#${page.procurement.id}`, href: show(page.procurement.id).url },
    ],
});
