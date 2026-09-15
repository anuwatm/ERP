import { useCallback, useState } from 'react';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import EditorLineItems from '@/Components/UI/EditorLineItems';
import EditorAttachments from '@/Components/UI/EditorAttachments';
import EditorFinancialSummary from '@/Components/UI/EditorFinancialSummary';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useEditorPreview from '@/Hooks/useEditorPreview';

type Item = {
    key: string;
    description: string;
    quantity: string;
    unit_price: string;
    discount_amount: string;
    tax_rate: string;
};
const item = (): Item => ({
    key: crypto.randomUUID(),
    description: '',
    quantity: '1',
    unit_price: '100',
    discount_amount: '0',
    tax_rate: '7',
});

// Accessible only through tests/Browser/phase231-router.php, never registered in application routes.
export default function DialogStandard() {
    const [open, setOpen] = useState(false);
    const [minimized, setMinimized] = useState(false);
    const form = useForm<{
        title: string;
        tax_mode: string;
        discount_amount: string;
        items: Item[];
        attachment: File | null;
    }>({
        title: '',
        tax_mode: 'exclusive',
        discount_amount: '0',
        items: [item()],
        attachment: null,
    });
    const guard = useDiscardGuard(open && form.isDirty, form.processing);
    const resolve = useCallback(
        async (signal: AbortSignal) => {
            const response = await axios.post<{
                tax_amount: string;
                total: string;
            }>(
                '/phase231/standard/preview',
                {
                    tax_mode: form.data.tax_mode,
                    discount_amount: form.data.discount_amount,
                    items: form.data.items,
                },
                { signal },
            );
            return response.data;
        },
        [form.data.tax_mode, form.data.discount_amount, form.data.items],
    );
    const preview = useEditorPreview(resolve);
    const close = () => {
        setOpen(false);
        setMinimized(false);
        form.reset();
        form.clearErrors();
    };
    return (
        <main className="p-6">
            <h1 className="text-xl font-semibold">
                Shared dialog · isolated test fixture
            </h1>
            <p>
                No business document is created here. Totals use
                InvoiceController calculation.
            </p>
            <button
                type="button"
                className="mt-4 rounded border p-3"
                onClick={() => setOpen(true)}
            >
                Open standard dialog
            </button>
            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />
            <CreateEditDialog
                open={open}
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(close)}
                title="Create test document"
                breadcrumb="Fixture / Document"
                entityLabel="Document"
                draftLabel={form.data.title || 'Test document'}
                storageKey="erp.standard-fixture.size"
                formId="standard-fixture"
                processing={form.processing}
                saveLabel="Validate document"
                errors={form.errors}
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/phase231/standard/validate', {
                        preserveState: true,
                        preserveScroll: true,
                    });
                }}
                status={
                    <EditorFinancialSummary
                        pending={preview.pending}
                        error={preview.error}
                        amounts={
                            preview.value
                                ? [
                                      {
                                          label: 'VAT',
                                          value: preview.value.tax_amount,
                                          currency: 'THB',
                                      },
                                      {
                                          label: 'Total',
                                          value: preview.value.total,
                                          currency: 'THB',
                                      },
                                  ]
                                : []
                        }
                    />
                }
            >
                {(expanded) => (
                    <>
                        <section
                            className={`grid gap-4 ${expanded ? 'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4' : 'sm:grid-cols-2'}`}
                            aria-label="Document details"
                        >
                            <label>
                                Title
                                <input
                                    name="title"
                                    value={form.data.title}
                                    aria-invalid={!!form.errors.title}
                                    onChange={(event) =>
                                        form.setData(
                                            'title',
                                            event.target.value,
                                        )
                                    }
                                    className="block w-full min-w-0"
                                />
                            </label>
                            <label>
                                Tax mode
                                <select
                                    value={form.data.tax_mode}
                                    onChange={(event) =>
                                        form.setData(
                                            'tax_mode',
                                            event.target.value,
                                        )
                                    }
                                    className="block w-full"
                                >
                                    <option value="exclusive">Exclusive</option>
                                    <option value="inclusive">Inclusive</option>
                                    <option value="no_tax">No tax</option>
                                </select>
                            </label>
                            <label>
                                Discount
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={form.data.discount_amount}
                                    onChange={(event) =>
                                        form.setData(
                                            'discount_amount',
                                            event.target.value,
                                        )
                                    }
                                    className="block w-full min-w-0"
                                />
                            </label>
                        </section>
                        <EditorLineItems
                            rows={form.data.items}
                            rowKey={(row) => row.key}
                            columns={[
                                { key: 'description', label: 'Description' },
                                { key: 'quantity', label: 'Quantity' },
                                { key: 'unit_price', label: 'Price' },
                                { key: 'tax_rate', label: 'VAT %' },
                            ]}
                            errors={form.errors}
                            disabled={form.processing}
                            onAdd={() => {
                                form.clearErrors();
                                form.setData('items', [
                                    ...form.data.items,
                                    item(),
                                ]);
                            }}
                            onRemove={(index) => {
                                form.clearErrors();
                                form.setData(
                                    'items',
                                    form.data.items.filter(
                                        (_, i) => i !== index,
                                    ),
                                );
                            }}
                            renderCell={(row, index, field, errorId) => (
                                <input
                                    aria-label={`Item ${index + 1} ${field}`}
                                    aria-invalid={!!errorId}
                                    aria-describedby={errorId}
                                    name={`items.${index}.${field}`}
                                    type={
                                        field === 'description'
                                            ? 'text'
                                            : 'number'
                                    }
                                    step="any"
                                    value={row[field as keyof Item]}
                                    onChange={(event) =>
                                        form.setData(
                                            'items',
                                            form.data.items.map(
                                                (existing, i) =>
                                                    i === index
                                                        ? {
                                                              ...existing,
                                                              [field]:
                                                                  event.target
                                                                      .value,
                                                          }
                                                        : existing,
                                            ),
                                        )
                                    }
                                    className="w-full min-w-0"
                                />
                            )}
                        />
                        <EditorAttachments
                            selected={form.data.attachment}
                            onChange={(file) =>
                                form.setData('attachment', file)
                            }
                            existing={[
                                {
                                    id: 'retained',
                                    name: 'Existing restricted document',
                                    status: 'Access restricted',
                                },
                            ]}
                            error={form.errors.attachment}
                            disabled={form.processing}
                        />
                    </>
                )}
            </CreateEditDialog>
        </main>
    );
}
