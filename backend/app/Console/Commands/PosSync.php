<?php

namespace App\Console\Commands;

use App\Services\Pos\PosCheckpointService;
use App\Services\Pos\PosImportService;
use App\Services\Pos\PosRecallService;
use App\Services\Pos\PosTransport;
use App\Support\PosCanonicalJson;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PosSync extends Command
{
    protected $signature = 'pos:sync {instance} {--force : Retry transport before the current backoff expires}';

    protected $description = 'Run the POS inbox pipeline with a provisioned transport (network disabled by default).';

    public function handle(PosImportService $import, PosTransport $transport): int
    {
        $id = $this->argument('instance');
        $lease = Cache::lock('pos-sync:'.$id, 300);
        if (! $lease->get()) {
            $this->warn('POS worker already running.');

            return self::FAILURE;
        }
        try {
            $instance = DB::table('pos_instances')->where('id', $id)->where('active', true)->firstOrFail();
            if (! $this->option('force') && $instance->next_sync_at && Carbon::parse($instance->next_sync_at)->isFuture()) {
                $this->warn('POS sync is in bounded transport backoff.');

                return self::FAILURE;
            }
            if (config('pos.local_admin_sync_enabled') === true) {
                abort_unless(app()->environment(['local', 'testing']), 403);
                $catalog = DB::table('pos_catalog_publications')->where('pos_instance_id', $id)->where('version', $instance->current_catalog_version)->firstOrFail();
                $accepted = $transport->request($id, 'catalog', json_decode($catalog->payload, true), $catalog->version);
                abort_unless(($accepted['status'] ?? null) === 'accepted', 502, 'Catalog was not acknowledged.');
                foreach (DB::table('pos_stock_entitlements')->where('pos_instance_id', $id)->where('status', 'pending')->whereNotNull('publication_snapshot')->orderBy('id')->limit(100)->get() as $grant) {
                    $ack = $transport->request($id, 'grant', json_decode($grant->publication_snapshot, true), $grant->id);
                    abort_unless(($ack['status'] ?? null) === 'accepted', 502, 'Stock grant was not acknowledged.');
                    DB::transaction(function () use ($id, $grant) {
                        DB::table('pos_instances')->where('id', $id)->where('active', true)->lockForUpdate()->firstOrFail();
                        DB::table('pos_stock_entitlements')->where('id', $grant->id)->where('status', 'pending')->update(['status' => 'active', 'updated_at' => now()]);
                    });
                }
            }
            if (config('pos.local_recalls_enabled') === true) {
                app(PosRecallService::class)->publish($id, $transport);
            }
            if (config('pos.local_tax_documents_enabled') === true) {
                $profile = DB::table('pos_tax_profile_publications')->where('pos_instance_id', $id)->where('version', $instance->current_tax_profile_version)->firstOrFail();
                $published = $transport->request($id, 'tax-profile', json_decode($profile->snapshot, true));
                abort_unless(($published['status'] ?? null) === 'accepted', 502, 'Issuer profile was not acknowledged.');
            }
            $batch = $transport->request($id, 'events', ['cursor' => $instance->durable_cursor, 'limit' => 100]);
            validator($batch, ['events' => 'present|array|max:100', 'next_cursor' => 'required|integer|min:0'])->validate();
            $import->receive($id, $batch['events'], $batch['next_cursor']);
            $ready = DB::table('pos_sync_inbox')->where('pos_instance_id', $id)->where(function ($query) {
                $query->where('status', 'pending')->orWhere(function ($waiting) {
                    $waiting->where('status', 'dependency_pending')->where(function ($ready) {
                        $ready->whereExists(function ($sale) {
                            $sale->selectRaw('1')->from('pos_import_sales')->whereColumn('pos_import_sales.pos_instance_id', 'pos_sync_inbox.pos_instance_id')->whereColumn('pos_import_sales.source_sale_id', 'pos_sync_inbox.dependency_source_sale_id');
                        })->orWhere(function ($kitchen) {
                            $kitchen->whereExists(function ($deps) {
                                $deps->selectRaw('1')->from('pos_sync_dependencies')->whereColumn('pos_sync_dependencies.inbox_id', 'pos_sync_inbox.id');
                            });
                            $kitchen->whereNotExists(function ($deps) {
                                $deps->selectRaw('1')->from('pos_sync_dependencies')->whereColumn('pos_sync_dependencies.inbox_id', 'pos_sync_inbox.id')->whereNotExists(function ($parent) {
                                    $parent->selectRaw('1')->from('pos_import_kitchen_consumptions')->whereColumn('pos_import_kitchen_consumptions.pos_instance_id', 'pos_sync_dependencies.pos_instance_id')->whereColumn('pos_import_kitchen_consumptions.source_line_id', 'pos_sync_dependencies.source_id')->where(function ($state) {
                                        $state->where('pos_sync_dependencies.source_kind', 'kitchen_consumption')->orWhere(function ($posted) {
                                            $posted->where('pos_sync_dependencies.source_kind', 'kitchen_posted')->where('pos_import_kitchen_consumptions.inventory_status', 'posted');
                                        });
                                    });
                                })->whereNotExists(function ($refund) {
                                    $refund->selectRaw('1')->from('pos_import_refunds')->where('pos_sync_dependencies.source_kind', 'refund')->whereColumn('pos_import_refunds.pos_instance_id', 'pos_sync_dependencies.pos_instance_id')->whereColumn('pos_import_refunds.source_refund_id', 'pos_sync_dependencies.source_id');
                                })->whereNotExists(function ($sale) {
                                    $sale->selectRaw('1')->from('pos_import_sales')->where('pos_sync_dependencies.source_kind', 'sale')->whereColumn('pos_import_sales.pos_instance_id', 'pos_sync_dependencies.pos_instance_id')->whereColumn('pos_import_sales.source_sale_id', 'pos_sync_dependencies.source_id');
                                })->whereNotExists(function ($tax) {
                                    $tax->selectRaw('1')->from('pos_import_tax_documents')->where('pos_sync_dependencies.source_kind', 'tax_document')->whereColumn('pos_import_tax_documents.pos_instance_id', 'pos_sync_dependencies.pos_instance_id')->whereColumn('pos_import_tax_documents.source_document_id', 'pos_sync_dependencies.source_id');
                                });
                            });
                        });
                    });
                });
            });
            foreach ($ready->orderBy('source_sequence')->limit(100)->get() as $row) {
                $import->process($id, $row->event_id);
            }
            foreach (DB::table('pos_sync_inbox')->where('pos_instance_id', $id)->whereNull('acknowledged_at')->whereNotNull('result')->orderBy('source_sequence')->limit(100)->get() as $row) {
                $transport->request($id, 'result', json_decode($row->result, true, flags: JSON_THROW_ON_ERROR), $row->event_id);
                DB::transaction(function () use ($row) {
                    $latest = DB::table('pos_sync_inbox')->where('id', $row->id)->lockForUpdate()->firstOrFail();
                    if ($latest->status === $row->status && $latest->result !== null
                        && PosCanonicalJson::encode(json_decode($latest->result, true, flags: JSON_THROW_ON_ERROR)) === PosCanonicalJson::encode(json_decode($row->result, true, flags: JSON_THROW_ON_ERROR))) {
                        DB::table('pos_sync_inbox')->where('id', $row->id)->update(['acknowledged_at' => now()]);
                    }
                });
            }
            if (config('pos.local_checkpoints_enabled') === true) {
                $current = DB::table('pos_instances')->where('id', $id)->firstOrFail();
                $snapshot = $transport->request($id, 'checkpoint', ['branch_id' => $current->branch_id, 'after_sequence' => (int) $current->checkpoint_cursor]);
                $verified = app(PosCheckpointService::class)->verify($id, $snapshot);
                if ($verified['status'] === 'verified') {
                    $transport->request($id, 'checkpoint-ack', ['digest' => $verified['digest']], $verified['checkpoint_id']);
                } else {
                    $this->warn('Checkpoint requires review; archive acknowledgement withheld.');

                    return self::FAILURE;
                }
            }
            DB::table('pos_instances')->where('id', $id)->update(['last_sync_at' => now(), 'last_sync_error' => null, 'sync_failures' => 0, 'next_sync_at' => null]);
            $this->info('POS inbox and results synchronized.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::transaction(function () use ($id, $e) {
                $row = DB::table('pos_instances')->where('id', $id)->lockForUpdate()->first();
                if ($row) {
                    $count = min(31, (int) $row->sync_failures + 1);
                    $delay = min(300, 5 * (2 ** min(6, $count - 1)));
                    DB::table('pos_instances')->where('id', $id)->update(['sync_failures' => $count, 'next_sync_at' => now()->addSeconds($delay), 'last_sync_error' => substr($e::class, 0, 200)]);
                }
            });
            $this->error('POS sync failed ('.$e::class.'); durable records retained.');

            return self::FAILURE;
        } finally {
            $lease->release();
        }
    }
}
