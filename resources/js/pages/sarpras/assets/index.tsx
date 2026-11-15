import {
    AppstoreOutlined,
    DeleteOutlined,
    EditOutlined,
    EyeOutlined,
    MoreOutlined,
    ScanOutlined,
} from '@ant-design/icons';
import { Head, Link, router } from '@inertiajs/react';
import { Button, Dropdown, Input, Modal, Select, Tag } from 'antd';
import type { MenuProps } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import jsQR from 'jsqr';
import { useEffect, useRef, useState } from 'react';
import {
    create,
    destroy,
    edit,
    index,
    scan,
    show,
} from '@/actions/App/Http/Controllers/Sarpras/AssetController';
import DataTable from '@/components/data-table';
import PageHeader from '@/components/page-header';
import Panel from '@/components/panel';

interface Asset {
    id: number;
    inventory_code: string;
    name: string;
    category: string | null;
    location: string | null;
    brand: string | null;
    condition: string;
    status: string;
    acquisition_year: number | null;
}

interface Option {
    value: number | string;
    label: string;
}

interface Props {
    assets: Asset[];
    total: number;
    page: number;
    perPage: number;
    search: string;
    filters: {
        category: string | null;
        location: string | null;
        condition: string | null;
        status: string | null;
    };
    categories: { id: number; name: string }[];
    locations: { id: number; name: string }[];
    conditions: Option[];
    statuses: Option[];
}

const CONDITION_COLORS: Record<string, string> = {
    Baik: 'green',
    'Kurang Baik': 'orange',
    Rusak: 'red',
};

const STATUS_COLORS: Record<string, string> = {
    Aktif: 'green',
    Dipinjam: 'blue',
    'Dalam Perbaikan': 'orange',
    Dihapus: 'default',
};

export default function AssetIndex({
    assets,
    total,
    page,
    perPage,
    search,
    filters,
    categories,
    locations,
    conditions,
    statuses,
}: Props) {
    const [scanOpen, setScanOpen] = useState(false);
    const [scanCode, setScanCode] = useState('');
    const [cameraActive, setCameraActive] = useState(false);
    const [cameraError, setCameraError] = useState<string | null>(null);
    const videoRef = useRef<HTMLVideoElement>(null);
    const streamRef = useRef<MediaStream | null>(null);

    const supportsCamera =
        typeof window !== 'undefined' && !!navigator.mediaDevices?.getUserMedia;

    useEffect(() => {
        if (!scanOpen || !supportsCamera) {
            return;
        }

        let cancelled = false;
        let timer: number | undefined;
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d', { willReadFrequently: true });

        const start = async () => {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment' },
                });

                if (cancelled) {
                    stream.getTracks().forEach((track) => track.stop());

                    return;
                }

                streamRef.current = stream;

                if (videoRef.current) {
                    videoRef.current.srcObject = stream;
                    await videoRef.current.play();
                }

                setCameraActive(true);

                const tick = () => {
                    const video = videoRef.current;

                    if (cancelled || !video) {
                        return;
                    }

                    if (context && video.videoWidth > 0) {
                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;
                        context.drawImage(video, 0, 0, canvas.width, canvas.height);

                        const frame = context.getImageData(
                            0,
                            0,
                            canvas.width,
                            canvas.height,
                        );
                        const result = jsQR(frame.data, frame.width, frame.height);
                        const value = result?.data.trim();

                        if (value) {
                            setScanOpen(false);
                            router.get(scan(value).url);

                            return;
                        }
                    }

                    timer = window.setTimeout(tick, 250);
                };

                tick();
            } catch {
                setCameraError(
                    'Kamera tidak dapat diakses. Gunakan input kode manual.',
                );
            }
        };

        void start();

        return () => {
            cancelled = true;

            if (timer) {
                window.clearTimeout(timer);
            }

            streamRef.current?.getTracks().forEach((track) => track.stop());
            streamRef.current = null;
            setCameraActive(false);
        };
    }, [scanOpen, supportsCamera]);

    const columns: ColumnsType<Asset> = [
        {
            title: 'Kode',
            dataIndex: 'inventory_code',
            key: 'inventory_code',
            width: 130,
            render: (text) => <span className="font-bold">{text}</span>,
        },
        {
            title: 'Nama',
            dataIndex: 'name',
            key: 'name',
            render: (text, record) => (
                <Link href={show(record.id).url} className="text-primary">
                    {text}
                </Link>
            ),
        },
        {
            title: 'Kategori',
            dataIndex: 'category',
            key: 'category',
            render: (text) => text ?? <Tag>Tanpa kategori</Tag>,
        },
        {
            title: 'Lokasi',
            dataIndex: 'location',
            key: 'location',
            render: (text) => text ?? <Tag>Tanpa lokasi</Tag>,
        },
        {
            title: 'Merek',
            dataIndex: 'brand',
            key: 'brand',
            ellipsis: true,
            render: (text) => text ?? '-',
        },
        {
            title: 'Kondisi',
            dataIndex: 'condition',
            key: 'condition',
            width: 120,
            render: (text) => (
                <Tag color={CONDITION_COLORS[text] ?? 'default'}>{text}</Tag>
            ),
        },
        {
            title: 'Status',
            dataIndex: 'status',
            key: 'status',
            width: 140,
            render: (text) => (
                <Tag color={STATUS_COLORS[text] ?? 'default'}>{text}</Tag>
            ),
        },
        {
            title: 'Aksi',
            key: 'actions',
            width: 60,
            render: (_, record) => {
                const items: MenuProps['items'] = [
                    {
                        key: 'view',
                        icon: <EyeOutlined />,
                        label: 'Detail',
                        onClick: () => router.get(show(record.id).url),
                    },
                    {
                        key: 'edit',
                        icon: <EditOutlined />,
                        label: 'Edit',
                        onClick: () => router.get(edit(record.id).url),
                    },
                    { type: 'divider' },
                    {
                        key: 'delete',
                        icon: <DeleteOutlined />,
                        label: 'Arsipkan',
                        danger: true,
                        onClick: () =>
                            Modal.confirm({
                                title: 'Arsipkan aset',
                                content: `Yakin ingin mengarsipkan "${record.name}"? Aset tetap tersimpan dalam histori dan dapat dipulihkan.`,
                                okText: 'Ya',
                                cancelText: 'Batal',
                                okType: 'danger',
                                onOk: () =>
                                    router.delete(destroy(record.id).url),
                            }),
                    },
                ];

                return (
                    <Dropdown
                        menu={{ items }}
                        trigger={['click']}
                        placement="bottomRight"
                    >
                        <Button
                            type="text"
                            icon={<MoreOutlined />}
                            size="small"
                        />
                    </Dropdown>
                );
            },
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
            <Head title="Data Aset" />
            <PageHeader
                eyebrow="Sarana Prasarana"
                title="Data Aset"
                description="Inventaris aset dengan kode unik, kondisi fisik, dan status operasional."
                actions={
                    <div className="flex gap-2">
                        <Button
                            icon={<ScanOutlined />}
                            size="large"
                            onClick={() => {
                                setScanCode('');
                                setCameraError(null);
                                setScanOpen(true);
                            }}
                        >
                            Scan Kode
                        </Button>
                        <Link href={create().url}>
                            <Button
                                type="primary"
                                icon={<AppstoreOutlined />}
                                size="large"
                            >
                                Aset Baru
                            </Button>
                        </Link>
                    </div>
                }
            />
            <Modal
                title="Cari aset berdasarkan kode"
                open={scanOpen}
                okText="Buka Aset"
                cancelText="Batal"
                okButtonProps={{ disabled: !scanCode.trim() }}
                onOk={() => {
                    setScanOpen(false);
                    router.get(scan(scanCode.trim()).url);
                }}
                onCancel={() => setScanOpen(false)}
            >
                {supportsCamera ? (
                    <>
                        <div className="relative mb-2 overflow-hidden rounded-xl border border-border bg-black">
                            <video
                                ref={videoRef}
                                className="h-56 w-full object-cover"
                                muted
                                playsInline
                            />
                            {!cameraActive && !cameraError && (
                                <p className="absolute inset-0 flex items-center justify-center text-sm text-white">
                                    Menyiapkan kamera…
                                </p>
                            )}
                        </div>
                        <p className="mb-3 text-sm text-muted-foreground">
                            {cameraError ??
                                'Arahkan kamera ke QR pada label aset.'}
                        </p>
                    </>
                ) : (
                    <p className="mb-3 text-sm text-muted-foreground">
                        Kamera tidak tersedia (butuh HTTPS atau localhost).
                        Gunakan input kode manual.
                    </p>
                )}
                <p className="mb-2 text-sm text-muted-foreground">
                    Atau masukkan kode manual:
                </p>
                <Input
                    placeholder="Contoh: INV-00042"
                    value={scanCode}
                    onChange={(e) => setScanCode(e.target.value)}
                    onPressEnter={() => {
                        if (scanCode.trim()) {
                            setScanOpen(false);
                            router.get(scan(scanCode.trim()).url);
                        }
                    }}
                />
            </Modal>
            <Panel bodyClassName="p-4 sm:p-6">
                <div className="mb-4 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <Select
                        placeholder="Kategori"
                        allowClear
                        className="w-full"
                        value={filters.category ?? undefined}
                        onChange={(value) =>
                            navigate({
                                page: 1,
                                perPage,
                                search,
                                category: value,
                                location: filters.location,
                                condition: filters.condition,
                                status: filters.status,
                            })
                        }
                        options={categories.map((c) => ({
                            value: c.id,
                            label: c.name,
                        }))}
                    />
                    <Select
                        placeholder="Lokasi"
                        allowClear
                        className="w-full"
                        value={filters.location ?? undefined}
                        onChange={(value) =>
                            navigate({
                                page: 1,
                                perPage,
                                search,
                                category: filters.category,
                                location: value,
                                condition: filters.condition,
                                status: filters.status,
                            })
                        }
                        options={locations.map((l) => ({
                            value: l.id,
                            label: l.name,
                        }))}
                    />
                    <Select
                        placeholder="Kondisi"
                        allowClear
                        className="w-full"
                        value={filters.condition ?? undefined}
                        onChange={(value) =>
                            navigate({
                                page: 1,
                                perPage,
                                search,
                                category: filters.category,
                                location: filters.location,
                                condition: value,
                                status: filters.status,
                            })
                        }
                        options={conditions}
                    />
                    <Select
                        placeholder="Status"
                        allowClear
                        className="w-full"
                        value={filters.status ?? undefined}
                        onChange={(value) =>
                            navigate({
                                page: 1,
                                perPage,
                                search,
                                category: filters.category,
                                location: filters.location,
                                condition: filters.condition,
                                status: value,
                            })
                        }
                        options={statuses}
                    />
                </div>
                <DataTable
                    columns={columns}
                    data={assets}
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
                            ...filters,
                        })
                    }
                    onPerPageChange={(pp) =>
                        navigate({
                            page: 1,
                            perPage: pp,
                            search,
                            ...filters,
                        })
                    }
                    onSearchChange={(s) =>
                        navigate({
                            page: 1,
                            perPage,
                            search: s,
                            ...filters,
                        })
                    }
                    searchPlaceholder="Cari kode, nama, merek, atau nomor seri..."
                />
            </Panel>
        </div>
    );
}

AssetIndex.layout = () => ({
    breadcrumbs: [
        { title: 'Sarana Prasarana', href: '#' },
        { title: 'Aset', href: index().url },
    ],
});
