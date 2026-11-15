import { DeleteOutlined, PlusOutlined } from '@ant-design/icons';
import { Head, useForm } from '@inertiajs/react';
import { App, Button, Form, Input, InputNumber, Select } from 'antd';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/Sarpras/ProcurementController';
import PageHeader from '@/components/page-header';
import Panel from '@/components/panel';

interface Option {
    value: number;
    label: string;
}

interface Item {
    name: string;
    category_id: number | null;
    location_id: number | null;
    quantity: number;
    unit_price: number | null;
    notes: string;
}

interface Props {
    categories: Option[];
    locations: Option[];
}

const emptyItem = (): Item => ({
    name: '',
    category_id: null,
    location_id: null,
    quantity: 1,
    unit_price: null,
    notes: '',
});

export default function ProcurementCreate({ categories, locations }: Props) {
    const { message } = App.useApp();
    const { data, setData, post, processing, errors } = useForm<{
        title: string;
        reason: string;
        items: Item[];
    }>({
        title: '',
        reason: '',
        items: [emptyItem()],
    });

    const updateItem = (index: number, key: keyof Item, value: unknown) => {
        setData(
            'items',
            data.items.map((item, i) =>
                i === index ? { ...item, [key]: value } : item,
            ),
        );
    };

    const removeItem = (index: number) => {
        setData(
            'items',
            data.items.filter((_, i) => i !== index),
        );
    };

    const itemError = (index: number, key: string) =>
        (errors as Record<string, string>)[`items.${index}.${key}`];

    const onFinish = () => {
        post(store().url, {
            onSuccess: () => message.success('Usulan pengadaan berhasil dikirim'),
        });
    };

    return (
        <div className="mx-auto w-full max-w-5xl space-y-6">
            <Head title="Ajukan Pengadaan" />
            <PageHeader
                eyebrow="Sarana Prasarana / Pengadaan"
                title="Ajukan Pengadaan"
                description="Usulkan satu atau beberapa barang beserta jumlah dan estimasi harganya."
            />

            <Form layout="vertical" onFinish={onFinish}>
                <Panel title="Detail usulan">
                    <div className="space-y-6">
                        <Form.Item
                            label="Nama Usulan"
                            required
                            validateStatus={errors.title ? 'error' : ''}
                            help={errors.title}
                            className="!mb-0"
                        >
                            <Input
                                placeholder="mis. Pengadaan komputer laboratorium"
                                value={data.title}
                                onChange={(e) =>
                                    setData('title', e.target.value)
                                }
                            />
                        </Form.Item>
                        <Form.Item
                            label="Alasan / Dasar Usulan"
                            required
                            validateStatus={errors.reason ? 'error' : ''}
                            help={errors.reason}
                            className="!mb-0"
                        >
                            <Input.TextArea
                                rows={4}
                                placeholder="Jelaskan kebutuhan dan dasar pengadaan."
                                value={data.reason}
                                onChange={(e) =>
                                    setData('reason', e.target.value)
                                }
                            />
                        </Form.Item>
                    </div>
                </Panel>

                <Panel
                    title="Rincian Barang"
                    description="Jumlah barang menentukan banyaknya aset yang dibuat saat penerimaan."
                    className="mt-6"
                >
                    <div className="space-y-6">
                        {data.items.map((item, index) => (
                            <div
                                key={index}
                                className="space-y-4 rounded-xl border border-border p-4"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <span className="text-sm font-semibold">
                                        Barang {index + 1}
                                    </span>
                                    <Button
                                        danger
                                        type="text"
                                        icon={<DeleteOutlined />}
                                        disabled={data.items.length === 1}
                                        onClick={() => removeItem(index)}
                                    />
                                </div>

                                <Form.Item
                                    label="Nama Barang"
                                    required
                                    validateStatus={
                                        itemError(index, 'name') ? 'error' : ''
                                    }
                                    help={itemError(index, 'name')}
                                    className="!mb-0"
                                >
                                    <Input
                                        placeholder="mis. Laptop"
                                        value={item.name}
                                        onChange={(e) =>
                                            updateItem(
                                                index,
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Form.Item>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <Form.Item
                                        label="Jumlah"
                                        required
                                        validateStatus={
                                            itemError(index, 'quantity')
                                                ? 'error'
                                                : ''
                                        }
                                        help={itemError(index, 'quantity')}
                                        className="!mb-0"
                                    >
                                        <InputNumber
                                            min={1}
                                            max={1000}
                                            className="!w-full"
                                            value={item.quantity}
                                            onChange={(value) =>
                                                updateItem(
                                                    index,
                                                    'quantity',
                                                    value ?? 1,
                                                )
                                            }
                                        />
                                    </Form.Item>
                                    <Form.Item
                                        label="Kategori Aset"
                                        validateStatus={
                                            itemError(index, 'category_id')
                                                ? 'error'
                                                : ''
                                        }
                                        help={itemError(index, 'category_id')}
                                        className="!mb-0"
                                    >
                                        <Select
                                            allowClear
                                            placeholder="Pilih kategori"
                                            value={item.category_id ?? undefined}
                                            onChange={(value) =>
                                                updateItem(
                                                    index,
                                                    'category_id',
                                                    value ?? null,
                                                )
                                            }
                                            options={categories}
                                        />
                                    </Form.Item>
                                    <Form.Item
                                        label="Lokasi Aset"
                                        validateStatus={
                                            itemError(index, 'location_id')
                                                ? 'error'
                                                : ''
                                        }
                                        help={itemError(index, 'location_id')}
                                        className="!mb-0"
                                    >
                                        <Select
                                            allowClear
                                            placeholder="Pilih lokasi"
                                            value={item.location_id ?? undefined}
                                            onChange={(value) =>
                                                updateItem(
                                                    index,
                                                    'location_id',
                                                    value ?? null,
                                                )
                                            }
                                            options={locations}
                                        />
                                    </Form.Item>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <Form.Item
                                        label="Estimasi Harga Satuan"
                                        validateStatus={
                                            itemError(index, 'unit_price')
                                                ? 'error'
                                                : ''
                                        }
                                        help={itemError(index, 'unit_price')}
                                        className="!mb-0"
                                    >
                                        <InputNumber
                                            min={0}
                                            className="!w-full"
                                            prefix="Rp"
                                            value={item.unit_price ?? undefined}
                                            onChange={(value) =>
                                                updateItem(
                                                    index,
                                                    'unit_price',
                                                    value ?? null,
                                                )
                                            }
                                        />
                                    </Form.Item>
                                    <Form.Item
                                        label="Catatan"
                                        validateStatus={
                                            itemError(index, 'notes')
                                                ? 'error'
                                                : ''
                                        }
                                        help={itemError(index, 'notes')}
                                        className="!mb-0"
                                    >
                                        <Input
                                            placeholder="Spesifikasi atau catatan (opsional)"
                                            value={item.notes}
                                            onChange={(e) =>
                                                updateItem(
                                                    index,
                                                    'notes',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Form.Item>
                                </div>
                            </div>
                        ))}

                        {errors.items && (
                            <div className="text-sm text-red-500">
                                {errors.items}
                            </div>
                        )}

                        <Button
                            icon={<PlusOutlined />}
                            onClick={() =>
                                setData('items', [...data.items, emptyItem()])
                            }
                        >
                            Tambah Barang
                        </Button>
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

ProcurementCreate.layout = () => ({
    breadcrumbs: [
        { title: 'Sarana Prasarana', href: '#' },
        { title: 'Pengadaan', href: index().url },
        { title: 'Baru', href: '#' },
    ],
});
