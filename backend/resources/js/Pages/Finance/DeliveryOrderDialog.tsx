import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import {
    EditorChoice,
    EditorField,
    EditorReference,
} from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Invoice = {
    id: string;
    invoice_no: string;
    items: Array<{
        product_id: string | null;
        product?: {
            barcode?: string | null;
            sku?: string | null;
            name: string;
        } | null;
    }>;
};

type Warehouse = { id: string; code: string; name: string };

type DeliveryOrderForm = {
    invoice_id: string;
    warehouse_id: string;
    delivery_date: string;
    status: 'draft' | 'delivered';
    receiver_name: string;
    note: string;
};

export default function DeliveryOrderDialog({
    open,
    invoices,
    warehouses,
    onClose,
}: {
    open: boolean;
    invoices: Invoice[];
    warehouses: Warehouse[];
    onClose: () => void;
}) {
    const today = new Date().toISOString().slice(0, 10);
    const form = useForm<DeliveryOrderForm>({
        invoice_id: '',
        warehouse_id: '',
        delivery_date: today,
        status: 'draft',
        receiver_name: '',
        note: '',
    });

    const [minimized, setMinimized] = useState(false);
    const [barcode, setBarcode] = useState('');
    const [scanResult, setScanResult] = useState('');
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    const isDirty =
        form.data.invoice_id !== '' ||
        form.data.warehouse_id !== '' ||
        form.data.delivery_date !== today ||
        form.data.receiver_name !== '' ||
        form.data.note !== '';

    const guard = useDiscardGuard(isDirty, form.processing);

    const selectedInvoice = invoices.find(
        (invoice) => invoice.id === form.data.invoice_id,
    );

    const scan = () => {
        if (!selectedInvoice) {
            setScanResult('Please select an invoice first.');
            return;
        }
        const product = selectedInvoice.items.find(
            (item) =>
                item.product?.barcode === barcode ||
                item.product?.sku === barcode,
        )?.product;
        setScanResult(
            product
                ? `✓ ${product.name} matches the selected invoice.`
                : '✕ Barcode / SKU does not match items in the selected invoice.',
        );
    };

    function submit(event: FormEvent) {
        event.preventDefault();
        if (form.processing || !form.data.invoice_id) return;

        form.post(route('delivery-orders.store'), {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                resetSubmissionKey();
                onClose();
            },
        });
    }

    if (!open) return null;

    return (
        <>
            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />
            <CreateEditDialog
                open={open}
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(onClose)}
                title="Create Delivery Order"
                breadcrumb="Finance / Commercial Documents"
                entityLabel="Delivery Order"
                draftLabel={
                    selectedInvoice
                        ? `Invoice ${selectedInvoice.invoice_no}`
                        : 'New DO'
                }
                storageKey="delivery-order-editor-size"
                formId="delivery-order-editor"
                processing={form.processing}
                saveDisabled={!form.data.invoice_id}
                saveLabel="Create DO"
                status="Draft / Outbound Fulfillment"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorReference
                                id="do-invoice-id"
                                label="Invoice"
                                value={form.data.invoice_id}
                                options={invoices.map((inv) => ({
                                    id: inv.id,
                                    label: inv.invoice_no,
                                }))}
                                onChange={(val: string) => {
                                    form.setData('invoice_id', val);
                                    setScanResult('');
                                }}
                                error={form.errors.invoice_id}
                                required
                            />

                            <EditorReference
                                id="do-warehouse-id"
                                label="Warehouse"
                                emptyLabel="All warehouse stock"
                                value={form.data.warehouse_id}
                                options={warehouses.map((wh) => ({
                                    id: wh.id,
                                    label: `${wh.code} - ${wh.name}`,
                                }))}
                                onChange={(val: string) =>
                                    form.setData('warehouse_id', val)
                                }
                                error={form.errors.warehouse_id}
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="do-delivery-date"
                                label="Delivery Date"
                                type="date"
                                value={form.data.delivery_date}
                                onChange={(val: string) =>
                                    form.setData('delivery_date', val)
                                }
                                error={form.errors.delivery_date}
                                required
                            />

                            <EditorChoice
                                id="do-status"
                                label="Status"
                                value={form.data.status}
                                options={['draft', 'delivered']}
                                onChange={(val: string) =>
                                    form.setData(
                                        'status',
                                        val as 'draft' | 'delivered',
                                    )
                                }
                                error={form.errors.status}
                            />
                        </div>

                        <EditorField
                            id="do-receiver-name"
                            label="Receiver Name"
                            value={form.data.receiver_name}
                            onChange={(val: string) =>
                                form.setData('receiver_name', val)
                            }
                            error={form.errors.receiver_name}
                        />

                        {/* Barcode scanner panel */}
                        <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/50">
                            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Barcode / SKU Verification
                            </label>
                            <div className="mt-2 flex gap-2">
                                <TextInput
                                    value={barcode}
                                    onChange={(event) =>
                                        setBarcode(event.target.value)
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            event.preventDefault();
                                            scan();
                                        }
                                    }}
                                    placeholder="Scan barcode or SKU..."
                                    className="w-full"
                                />
                                <SecondaryButton type="button" onClick={scan}>
                                    Verify
                                </SecondaryButton>
                            </div>
                            {scanResult && (
                                <p
                                    className={`mt-2 text-xs font-medium ${
                                        scanResult.startsWith('✓')
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-rose-600 dark:text-rose-400'
                                    }`}
                                >
                                    {scanResult}
                                </p>
                            )}
                        </div>

                        <EditorField
                            id="do-note"
                            label="Note"
                            multiline
                            value={form.data.note}
                            onChange={(val: string) =>
                                form.setData('note', val)
                            }
                            error={form.errors.note}
                        />
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}
