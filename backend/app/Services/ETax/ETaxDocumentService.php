<?php

namespace App\Services\ETax;

use App\Models\AuditLog;
use App\Models\CreditDebitNote;
use App\Models\ETaxDocument;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ETaxDocumentService
{
    public function generate(Model $source, string $documentType, string $actorId): ETaxDocument
    {
        $this->assertSource($source, $documentType);

        return DB::transaction(function () use ($source, $documentType, $actorId): ETaxDocument {
            $document = ETaxDocument::query()->lockForUpdate()->firstOrNew([
                'org_id' => $source->org_id,
                'source_type' => $source::class,
                'source_id' => $source->getKey(),
                'document_type' => $documentType,
            ]);

            if ($document->exists && in_array($document->status, ['submitted', 'accepted'], true)) {
                throw new ImmutableETaxDocumentException('A submitted or accepted e-Tax document cannot be regenerated. Issue a credit or debit note instead.');
            }

            $payload = $this->payload($source, $documentType);
            $xml = $this->buildXml($payload);
            $path = 'e-tax/'.$source->org_id.'/'.$documentType.'/'.$payload['document_no'].'.xml';

            Storage::disk('local')->put($path, $xml);
            $document->fill([
                'document_no' => $payload['document_no'],
                'status' => 'generated',
                'xml_storage_path' => $path,
                'xml_sha256' => hash('sha256', $xml),
                'payload_json' => $payload,
                'signed_at' => null,
                'submitted_at' => null,
                'accepted_at' => null,
                'rejected_at' => null,
                'last_error' => null,
                'created_by' => $document->exists ? $document->created_by : $actorId,
            ])->save();

            AuditLog::create([
                'org_id' => $source->org_id,
                'actor_user_id' => $actorId,
                'action' => 'e_tax.generate',
                'entity_type' => 'e_tax_document',
                'entity_id' => $document->id,
                'after_json' => ['document_type' => $documentType, 'source_type' => class_basename($source), 'source_id' => $source->id, 'sha256' => $document->xml_sha256],
            ]);

            return $document;
        });
    }

    public function xml(ETaxDocument $document): string
    {
        abort_unless(Storage::disk('local')->exists($document->xml_storage_path), 404, 'e-Tax XML file not found.');

        return Storage::disk('local')->get($document->xml_storage_path);
    }

    private function assertSource(Model $source, string $documentType): void
    {
        if (! in_array($documentType, ETaxDocument::TYPES, true)) {
            throw ValidationException::withMessages(['document_type' => 'Unsupported e-Tax document type.']);
        }

        $valid = match ($documentType) {
            'tax_invoice' => $source instanceof Invoice && in_array($source->status, ['sent', 'partially_paid', 'paid', 'overdue'], true),
            'receipt' => $source instanceof Payment && $source->entry_type === 'receipt' && (float) $source->amount > 0,
            'credit_note' => $source instanceof CreditDebitNote && $source->type === 'credit',
            'debit_note' => $source instanceof CreditDebitNote && $source->type === 'debit',
            default => false,
        };

        if (! $valid) {
            throw ValidationException::withMessages(['source' => 'The selected source is not eligible for this e-Tax document type.']);
        }
    }

    /** @return array<string, mixed> */
    private function payload(Model $source, string $documentType): array
    {
        if ($source instanceof Invoice) {
            $source->loadMissing(['customer', 'items']);

            return $this->basePayload($source, $documentType, $source->invoice_no, $source->issue_date?->toDateString(), $source->customer?->company_name, $source->customer?->tax_id, $source->subtotal, $source->tax_amount, $source->total, collect($source->items)->map(fn ($item) => ['description' => $item->description, 'quantity' => (string) $item->quantity, 'unit_price' => (string) $item->unit_price, 'line_total' => (string) $item->line_total])->all());
        }

        if ($source instanceof Payment) {
            $source->loadMissing('invoice.customer');

            return $this->basePayload($source, $documentType, 'RC-'.$source->id, $source->payment_date?->toDateString(), $source->invoice?->customer?->company_name, $source->invoice?->customer?->tax_id, $source->amount, '0.00', $source->amount, [['description' => 'Receipt for '.$source->invoice?->invoice_no, 'quantity' => '1', 'unit_price' => (string) $source->amount, 'line_total' => (string) $source->amount]]);
        }

        /** @var CreditDebitNote $source */
        $source->loadMissing('invoice.customer');

        return $this->basePayload($source, $documentType, $source->note_no, $source->issue_date?->toDateString(), $source->invoice?->customer?->company_name, $source->invoice?->customer?->tax_id, $source->subtotal, $source->tax_amount, $source->total, collect($source->items)->map(fn ($item) => ['description' => $item->description, 'quantity' => (string) $item->quantity, 'unit_price' => (string) $item->unit_price, 'line_total' => (string) $item->line_total])->all());
    }

    /** @param list<array<string, string>> $lines */
    private function basePayload(Model $source, string $documentType, string $documentNo, ?string $issueDate, ?string $buyerName, ?string $buyerTaxId, mixed $subtotal, mixed $tax, mixed $total, array $lines): array
    {
        $organization = $source->organization ?? null;
        if (! $organization) {
            $organization = Organization::findOrFail($source->org_id);
        }

        return [
            'profile' => 'provider-mapping-v1',
            'document_type' => $documentType,
            'document_no' => $documentNo,
            'issue_date' => $issueDate,
            'seller' => ['name' => $organization->legal_name ?: $organization->name, 'tax_id' => $organization->tax_id, 'address' => $organization->address],
            'buyer' => ['name' => $buyerName, 'tax_id' => $buyerTaxId],
            'currency' => $source->currency ?? 'THB',
            'subtotal' => number_format((float) $subtotal, 2, '.', ''),
            'tax_amount' => number_format((float) $tax, 2, '.', ''),
            'total' => number_format((float) $total, 2, '.', ''),
            'lines' => $lines,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function buildXml(array $payload): string
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $root = $xml->createElementNS('urn:erp:etax:provider-mapping:v1', 'etax:ElectronicTaxDocument');
        $root->setAttribute('profile', (string) $payload['profile']);
        $xml->appendChild($root);
        $this->append($xml, $root, 'DocumentType', $payload['document_type']);
        $this->append($xml, $root, 'DocumentNumber', $payload['document_no']);
        $this->append($xml, $root, 'IssueDate', $payload['issue_date']);
        foreach (['seller' => 'Seller', 'buyer' => 'Buyer'] as $key => $label) {
            $party = $xml->createElement($label);
            foreach ($payload[$key] as $field => $value) {
                $this->append($xml, $party, ucfirst($field), $value);
            }
            $root->appendChild($party);
        }
        $this->append($xml, $root, 'Currency', $payload['currency']);
        $this->append($xml, $root, 'Subtotal', $payload['subtotal']);
        $this->append($xml, $root, 'TaxAmount', $payload['tax_amount']);
        $this->append($xml, $root, 'Total', $payload['total']);
        $lines = $xml->createElement('Lines');
        foreach ($payload['lines'] as $row) {
            $line = $xml->createElement('Line');
            foreach ($row as $field => $value) {
                $this->append($xml, $line, ucfirst(str_replace('_', '', $field)), $value);
            }
            $lines->appendChild($line);
        }
        $root->appendChild($lines);

        return $xml->saveXML();
    }

    private function append(\DOMDocument $xml, \DOMElement $parent, string $name, mixed $value): void
    {
        $parent->appendChild($xml->createElement($name, (string) ($value ?? '')));
    }
}
