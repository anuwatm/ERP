<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillOfMaterials;
use App\Models\BomItem;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Support\ExternalApiResponse;
use App\Support\ExternalBusinessAccess;
use App\Support\ExternalPeriod;
use App\Support\ExternalReadPagination;
use App\Support\ExternalUnits;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExternalManufacturingController extends Controller
{
    private const FIELDS = [
        'boms' => ['id', 'bom_no', 'name', 'product_id', 'version', 'quantity', 'unit', 'is_active', 'updated_at'],
        'work-orders' => ['id', 'wo_no', 'bill_of_materials_id', 'product_id', 'status', 'planned_quantity', 'actual_quantity', 'scrap_quantity', 'start_date', 'due_date', 'completed_date', 'qc_passed', 'updated_at'],
    ];

    private const COSTS = ['boms' => ['labor_cost', 'overhead_cost', 'total_cost'],
        'work-orders' => ['material_cost', 'labor_cost', 'overhead_cost', 'total_cost', 'unit_cost']];

    public function index(Request $request, string $resource)
    {
        $filters = ExternalReadPagination::filters($request, $resource === 'boms' ? ['is_active' => ['sometimes', 'boolean']] : ['status' => ['sometimes', Rule::in(['draft', 'planned', 'in_progress', 'quality_check', 'completed', 'cancelled'])]]);
        $query = $this->query($request, $resource);
        if (isset($filters['updated_since'])) {
            $query->withTrashed();
        }
        foreach (['is_active', 'status'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        $fields = $this->fields($request, $resource);
        if (isset($filters['updated_since'])) {
            $fields[] = 'deleted_at';
        }

        return ExternalReadPagination::page($request, $query, $fields, $filters, fn ($row) => $this->serialize($request, $row, $resource));
    }

    public function show(Request $request, string $id, string $resource)
    {
        ExternalReadPagination::filters($request, ['limit' => ['prohibited'], 'cursor' => ['prohibited'], 'updated_since' => ['prohibited']]);
        $row = $this->query($request, $resource)->whereKey($id)->firstOrFail($this->fields($request, $resource));

        return response()->json(['data' => $this->serialize($request, $row, $resource), 'meta' => $this->meta($request)]);
    }

    public function items(Request $request, string $id, string $resource)
    {
        $this->query($request, $resource)->whereKey($id)->firstOrFail(['id']);
        $key = $request->attributes->get('integration_key');
        $org = $key->org_id;
        if ($resource === 'boms') {
            abort_unless(in_array('manufacturing:formulas:read', $key->scopes, true), 403);
            ExternalBusinessAccess::issuer($request, 'manufacturing:formulas:read');
            $query = BomItem::where('org_id', $org)->where('bill_of_materials_id', $id)
                ->where(fn ($q) => $q->whereNull('sub_bom_id')->orWhereIn('sub_bom_id', BillOfMaterials::where('org_id', $org)->select('id')));
            $fields = ['id', 'product_id', 'sub_bom_id', 'quantity', 'unit', 'waste_percent', 'sequence', 'updated_at'];
        } else {
            $query = WorkOrderItem::where('org_id', $org)->where('work_order_id', $id);
            $fields = ['id', 'product_id', 'planned_quantity', 'issued_quantity', 'unit', 'updated_at'];
        }
        $query->where(fn ($q) => $q->whereNull('product_id')->orWhereIn('product_id', Product::withTrashed()->where('org_id', $org)->select('id')));
        $costs = in_array('manufacturing:costs:read', $key->scopes, true);
        if ($costs) {
            ExternalBusinessAccess::issuer($request, 'manufacturing:costs:read');
            $fields = [...$fields, 'unit_cost', 'total_cost'];
        }
        $filters = ExternalReadPagination::filters($request);

        return ExternalReadPagination::page($request, $query, $fields, $filters, function ($row) use ($fields, $costs) {
            $data = ExternalReadPagination::fields($row, $fields);
            if ($costs) {
                $data['cost_currency_status'] = 'not_recorded_on_source';
            }

            return $data;
        });
    }

    public function summary(Request $request)
    {
        $filters = ExternalReadPagination::filters($request, array_merge(ExternalPeriod::rules(), ['limit' => ['prohibited'], 'cursor' => ['prohibited'], 'updated_since' => ['prohibited']]));
        [$start, $end, $zone, $now] = ExternalPeriod::resolve($request, $filters);
        ExternalBusinessAccess::issuer($request, 'summary:manufacturing:read');
        $org = $request->attributes->get('integration_key')->org_id;
        $orders = $this->base($org, 'work-orders');
        $from = CarbonImmutable::parse($start, $zone)->startOfDay()->setTimezone(config('app.timezone'));
        $until = CarbonImmutable::parse($end, $zone)->addDay()->startOfDay()->setTimezone(config('app.timezone'));
        $statuses = (clone $orders)->select('status')->selectRaw('COUNT(*) as records_count, SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as period_count', [$from, $until])
            ->groupBy('status')->orderBy('status')->get();
        $quantities = (clone $orders)->join('products as p', 'p.id', '=', 'work_orders.product_id')->where('p.org_id', $org)
            ->selectRaw(ExternalUnits::expression('p.unit').' as unit')->selectRaw('SUM(planned_quantity) as planned, SUM(actual_quantity) as actual, SUM(scrap_quantity) as scrap')
            ->groupByRaw(ExternalUnits::expression('p.unit'))->orderByRaw(ExternalUnits::expression('p.unit'))->limit(101)->get();
        $snapshotQuantities = $quantities->take(100)->map(function ($row) {
            $result = ['unit' => $row->unit];
            foreach (['planned', 'actual', 'scrap'] as $field) {
                $result[$field.'_quantity'] = (string) BigDecimal::of((string) $row->$field)->toScale(4, RoundingMode::HalfUp);
            }

            return $result;
        });

        return response()->json(['data' => ['period' => ['work_orders_created_by_current_status' => $statuses->map(fn ($row) => ['status' => $row->status, 'count' => (int) $row->period_count])],
            'snapshot' => ['as_of' => $now->toIso8601String(), 'active_boms' => $this->base($org, 'boms')->where('is_active', true)->count(),
                'work_orders_by_status' => $statuses->map(fn ($row) => ['status' => $row->status, 'count' => (int) $row->records_count]),
                'production_quantities_by_unit' => $snapshotQuantities, 'units_truncated' => $quantities->count() > 100]],
            'meta' => $this->meta($request) + ['period' => ['start_date' => $start, 'end_date' => $end], 'timezone' => $zone,
                'snapshot_as_of' => $now->toIso8601String(), 'quantity_unit_basis' => 'current_product_master', 'currency_mode' => 'not_applicable', 'visibility' => 'organization', 'freshness' => 'live']]);
    }

    private function query(Request $request, string $resource)
    {
        ExternalBusinessAccess::issuer($request, 'manufacturing:'.$resource.':read');

        return $this->base($request->attributes->get('integration_key')->org_id, $resource);
    }

    private function base(string $org, string $resource)
    {
        $model = $resource === 'boms' ? BillOfMaterials::class : WorkOrder::class;
        $table = $resource === 'boms' ? 'bill_of_materials' : 'work_orders';
        $query = $model::where($table.'.org_id', $org)->whereIn('product_id', Product::withTrashed()->where('org_id', $org)->select('id'));
        if ($resource === 'work-orders') {
            $query->whereIn('bill_of_materials_id', BillOfMaterials::withTrashed()->where('org_id', $org)->select('id'));
            foreach (['warehouse_id', 'source_warehouse_id'] as $field) {
                $query->where(fn ($q) => $q->whereNull($field)->orWhereIn($field, Warehouse::where('org_id', $org)->select('id')));
            }
        }

        return $query;
    }

    private function fields(Request $request, string $resource): array
    {
        if (in_array('manufacturing:costs:read', $request->attributes->get('integration_key')->scopes, true)) {
            ExternalBusinessAccess::issuer($request, 'manufacturing:costs:read');
        }

        return in_array('manufacturing:costs:read', $request->attributes->get('integration_key')->scopes, true)
            ? [...self::FIELDS[$resource], ...self::COSTS[$resource]] : self::FIELDS[$resource];
    }

    private function serialize(Request $request, $row, string $resource): array
    {
        $data = ExternalReadPagination::fields($row, self::FIELDS[$resource]);
        if (in_array('manufacturing:costs:read', $request->attributes->get('integration_key')->scopes, true)) {
            $data['costs'] = $row->only(self::COSTS[$resource]) + ['currency' => null, 'currency_status' => 'not_recorded_on_source'];
        }

        return $data;
    }

    private function meta(Request $request): array
    {
        return ['api_version' => 'v1', 'request_id' => ExternalApiResponse::requestId($request), 'generated_at' => now()->toIso8601String()];
    }
}
