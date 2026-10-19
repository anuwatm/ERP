<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\StockMovement;
use App\Models\Task;
use App\Models\WorkflowInstance;
use App\Services\ExternalWebhookService;
use Illuminate\Database\Eloquent\Model;

class ExternalWebhookObserver
{
    public function created(Model $model): void
    {
        $this->record($model);
    }

    public function updated(Model $model): void
    {
        $fields = match (true) {
            $model instanceof Invoice => ['status', 'paid_amount', 'balance_due'],
            $model instanceof Task => ['status'],
            $model instanceof WorkflowInstance => ['status', 'current_step_no'],
            $model instanceof StockMovement => ['quantity', 'product_id', 'warehouse_id', 'warehouse_bin_id', 'inventory_lot_id'],
            default => [],
        };
        if ($model->wasChanged($fields)) {
            $this->record($model);
        }
        if (config('external_api.webhooks_enabled') && $model instanceof StockMovement && $model->wasChanged('product_id')
            && $model->getRawOriginal('product_id') && $model->getRawOriginal('product_id') !== $model->product_id) {
            app(ExternalWebhookService::class)->record($model->org_id, 'stock.balance.changed', 'product', $model->getRawOriginal('product_id'));
        }
    }

    private function record(Model $model): void
    {
        if (! config('external_api.webhooks_enabled')) {
            return;
        }
        [$event, $resource, $id] = match (true) {
            $model instanceof Invoice => ['invoice.payment.status.changed', 'invoice', $model->id],
            $model instanceof Task => ['task.status.changed', 'task', $model->id],
            $model instanceof WorkflowInstance => ['approval.status.changed', 'workflow_instance', $model->id],
            $model instanceof StockMovement => ['stock.balance.changed', 'product', $model->product_id],
            default => [null, null, null],
        };
        if ($event && $id) {
            app(ExternalWebhookService::class)->record($model->org_id, $event, $resource, $id);
        }
    }
}
