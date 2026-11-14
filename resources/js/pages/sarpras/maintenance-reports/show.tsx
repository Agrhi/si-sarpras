import {
    CloseOutlined,
    PlayCircleOutlined,
    ToolOutlined,
    UserAddOutlined,
} from '@ant-design/icons';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    App,
    Button,
    Descriptions,
    Form,
    Image,
    Input,
    InputNumber,
    Modal,
    Select,
    Tag,
} from 'antd';
import { useState } from 'react';
import {
    assign,
    cancel,
    complete,
    index,
    start,
} from '@/actions/App/Http/Controllers/Sarpras/MaintenanceReportController';
import { show as assetShow } from '@/actions/App/Http/Controllers/Sarpras/AssetController';
import PageHeader from '@/components/page-header';
import Panel from '@/components/panel';

interface Option {
    value: string;
    label: string;
}

interface TechnicianOption {
    value: number;
    label: string;
}

interface Props {
    report: {
        id: number;
        asset: {
            id: number;
            inventory_code: string;
            name: string;
            status: string;
        } | null;
        reporter: string | null;
        assignee: string | null;
        urgency: Option;
        status: Option;
        description: string;
        photo_url: string | null;
        action_taken: string | null;
        cost: string | null;
        spare_parts: string | null;
        started_at: string | null;
        completed_at: string | null;
        created_at: string;
    };
    technicians: TechnicianOption[];
    can: {
        assign: boolean;
        process: boolean;
        cancel: boolean;
    };
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

export default function MaintenanceReportShow({
    report,
    technicians,
    can,
}: Props) {
    const { message } = App.useApp();
    const [assignOpen, setAssignOpen] = useState(false);
    const [completeOpen, setCompleteOpen] = useState(false);
    const [technicianId, setTechnicianId] = useState<number | null>(
        technicians[0]?.value ?? null,
    );

    const { data, setData, patch, processing, errors, reset } = useForm({
        action_taken: '',
        cost: '' as string | number,
        spare_parts: '',
    });

    const submitAssign = () => {
        if (!technicianId) {
            message.error('Pilih petugas teknis terlebih dahulu.');

            return;
        }

        router.patch(
            assign(report.id).url,
            { assigned_to: technicianId },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAssignOpen(false);
                    message.success('Petugas teknis ditugaskan.');
                },
            },
        );
    };

    const submitComplete = () => {
        patch(complete(report.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                setCompleteOpen(false);
                reset();
                message.success('Laporan diselesaikan.');
            },
        });
    };

    return (
        <div className="mx-auto w-full max-w-5xl space-y-6">
            <Head title={`Laporan Kerusakan #${report.id}`} />
            <PageHeader
                eyebrow="Sarana Prasarana / Laporan Kerusakan"
                title={`Laporan #${report.id}`}
                description={report.asset?.name}
                actions={
                    <div className="flex flex-wrap gap-2">
                        {can.assign && (
                            <Button
                                icon={<UserAddOutlined />}
                                size="large"
                                onClick={() => setAssignOpen(true)}
                            >
                                Tugaskan Petugas
                            </Button>
                        )}
                        {can.process &&
                            report.status.value === 'menunggu' && (
                                <Button
                                    type="primary"
                                    icon={<PlayCircleOutlined />}
                                    size="large"
                                    onClick={() =>
                                        Modal.confirm({
                                            title: 'Mulai proses perbaikan',
                                            content:
                                                'Tandai laporan ini sedang dikerjakan?',
                                            okText: 'Ya',
                                            cancelText: 'Batal',
                                            onOk: () =>
                                                router.patch(
                                                    start(report.id).url,
                                                    {},
                                                    { preserveScroll: true },
                                                ),
                                        })
                                    }
                                >
                                    Mulai Proses
                                </Button>
                            )}
                        {(can.process || can.assign) &&
                            report.status.value !== 'selesai' &&
                            report.status.value !== 'dibatalkan' && (
                                <Button
                                    type="primary"
                                    icon={<ToolOutlined />}
                                    size="large"
                                    onClick={() => setCompleteOpen(true)}
                                >
                                    Selesaikan
                                </Button>
                            )}
                        {can.cancel && (
                            <Button
                                danger
                                icon={<CloseOutlined />}
                                size="large"
                                onClick={() =>
                                    Modal.confirm({
                                        title: 'Batalkan laporan',
                                        content:
                                            'Batalkan laporan kerusakan ini?',
                                        okText: 'Ya',
                                        cancelText: 'Tidak',
                                        okType: 'danger',
                                        onOk: () =>
                                            router.patch(
                                                cancel(report.id).url,
                                                {},
                                                { preserveScroll: true },
                                            ),
                                    })
                                }
                            >
                                Batalkan
                            </Button>
                        )}
                    </div>
                }
            />

            <Panel title="Detail laporan">
                <Descriptions column={{ xs: 1, sm: 2, lg: 3 }}>
                    <Descriptions.Item label="Aset">
                        {report.asset ? (
                            <Link
                                href={assetShow(report.asset.id).url}
                                className="text-primary"
                            >
                                {report.asset.inventory_code} ·{' '}
                                {report.asset.name}
                            </Link>
                        ) : (
                            '-'
                        )}
                    </Descriptions.Item>
                    <Descriptions.Item label="Status Aset">
                        {report.asset?.status ?? '-'}
                    </Descriptions.Item>
                    <Descriptions.Item label="Pelapor">
                        {report.reporter ?? '-'}
                    </Descriptions.Item>
                    <Descriptions.Item label="Petugas">
                        {report.assignee ?? 'Belum ditugaskan'}
                    </Descriptions.Item>
                    <Descriptions.Item label="Urgensi">
                        <Tag
                            color={
                                URGENCY_COLORS[report.urgency.value] ?? 'default'
                            }
                        >
                            {report.urgency.label}
                        </Tag>
                    </Descriptions.Item>
                    <Descriptions.Item label="Status">
                        <Tag
                            color={
                                STATUS_COLORS[report.status.value] ?? 'default'
                            }
                        >
                            {report.status.label}
                        </Tag>
                    </Descriptions.Item>
                    <Descriptions.Item label="Dilaporkan">
                        {report.created_at}
                    </Descriptions.Item>
                    <Descriptions.Item label="Mulai Diproses">
                        {report.started_at ?? '-'}
                    </Descriptions.Item>
                    <Descriptions.Item label="Selesai">
                        {report.completed_at ?? '-'}
                    </Descriptions.Item>
                </Descriptions>
            </Panel>

            <Panel title="Deskripsi kerusakan" bodyClassName="space-y-4">
                <p className="text-sm whitespace-pre-line">
                    {report.description}
                </p>
                {report.photo_url && (
                    <Image
                        src={report.photo_url}
                        alt={`Foto laporan ${report.id}`}
                        width={280}
                        className="rounded-lg"
                    />
                )}
            </Panel>

            {(report.action_taken ||
                report.cost ||
                report.spare_parts) && (
                <Panel title="Hasil perbaikan">
                    <Descriptions column={1}>
                        <Descriptions.Item label="Tindakan">
                            {report.action_taken ?? '-'}
                        </Descriptions.Item>
                        <Descriptions.Item label="Biaya">
                            {report.cost
                                ? `Rp ${Number(report.cost).toLocaleString('id-ID')}`
                                : '-'}
                        </Descriptions.Item>
                        <Descriptions.Item label="Suku Cadang">
                            {report.spare_parts ?? '-'}
                        </Descriptions.Item>
                    </Descriptions>
                </Panel>
            )}

            <div className="flex justify-start">
                <Button size="large" href={index().url}>
                    Kembali ke Daftar Laporan
                </Button>
            </div>

            <Modal
                title="Tugaskan petugas teknis"
                open={assignOpen}
                okText="Tugaskan"
                cancelText="Batal"
                confirmLoading={processing}
                onOk={submitAssign}
                onCancel={() => setAssignOpen(false)}
            >
                <p className="mb-3 text-sm opacity-70">
                    Pilih petugas yang akan menangani laporan ini.
                </p>
                {technicians.length > 0 ? (
                    <Select
                        className="w-full"
                        placeholder="Pilih petugas"
                        value={technicianId ?? undefined}
                        onChange={(value) => setTechnicianId(value)}
                        options={technicians}
                    />
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Belum ada pengguna dengan peran petugas teknis.
                    </p>
                )}
            </Modal>

            <Modal
                title="Selesaikan laporan"
                open={completeOpen}
                okText="Simpan Penyelesaian"
                cancelText="Batal"
                confirmLoading={processing}
                onOk={submitComplete}
                onCancel={() => setCompleteOpen(false)}
            >
                <Form layout="vertical">
                    <Form.Item
                        label="Tindakan Perbaikan"
                        required
                        validateStatus={errors.action_taken ? 'error' : ''}
                        help={errors.action_taken}
                    >
                        <Input.TextArea
                            rows={3}
                            value={data.action_taken}
                            onChange={(e) =>
                                setData('action_taken', e.target.value)
                            }
                            placeholder="Jelaskan tindakan yang dilakukan."
                        />
                    </Form.Item>
                    <Form.Item
                        label="Biaya (Rp)"
                        validateStatus={errors.cost ? 'error' : ''}
                        help={errors.cost}
                    >
                        <InputNumber
                            className="w-full"
                            min={0}
                            value={
                                data.cost === '' ? null : Number(data.cost)
                            }
                            onChange={(value) =>
                                setData('cost', value ?? '')
                            }
                            placeholder="0"
                        />
                    </Form.Item>
                    <Form.Item
                        label="Suku Cadang"
                        validateStatus={errors.spare_parts ? 'error' : ''}
                        help={errors.spare_parts}
                    >
                        <Input.TextArea
                            rows={2}
                            value={data.spare_parts}
                            onChange={(e) =>
                                setData('spare_parts', e.target.value)
                            }
                            placeholder="mis. Lampu LED 12V, kabel HDMI"
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </div>
    );
}

MaintenanceReportShow.layout = (page: Props) => ({
    breadcrumbs: [
        { title: 'Sarana Prasarana', href: '#' },
        { title: 'Laporan Kerusakan', href: index().url },
        { title: `#${page.report.id}`, href: '#' },
    ],
});
