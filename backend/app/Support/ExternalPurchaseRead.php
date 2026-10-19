<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ExternalPurchaseRead
{
    public static function received(string $org): Builder
    {
        return DB::table('goods_receipt_items as gri')
            ->join('goods_receipts as gr', 'gr.id', '=', 'gri.goods_receipt_id')
            ->join('purchase_order_items as poi', 'poi.id', '=', 'gri.purchase_order_item_id')
            ->where('gri.org_id', $org)->where('gr.org_id', $org)->where('poi.org_id', $org)
            ->where('gr.status', 'posted')->whereColumn('gr.purchase_order_id', 'poi.purchase_order_id')
            ->where(fn ($q) => $q->whereColumn('gri.product_id', 'poi.product_id')->orWhere(fn ($both) => $both->whereNull('gri.product_id')->whereNull('poi.product_id')))
            ->where(fn ($q) => $q->whereRaw(ExternalUnits::expression('gri.unit').' = '.ExternalUnits::expression('poi.unit'))->orWhere(fn ($both) => $both->whereNull('gri.unit')->whereNull('poi.unit')))
            ->select('gri.purchase_order_item_id')->selectRaw('SUM(gri.quantity) as received_quantity')->groupBy('gri.purchase_order_item_id');
    }
}
