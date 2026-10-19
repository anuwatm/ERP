<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Division;
use App\Models\OrganizationCurrency;
use App\Models\Position;
use App\Models\Product;
use App\Models\Warehouse;
use App\Support\ExternalApiResponse;
use App\Support\ExternalReadPagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExternalCatalogController extends Controller
{
    public const REFERENCES = [
        'branches' => [Branch::class, ['id', 'code', 'name', 'status', 'updated_at']],
        'divisions' => [Division::class, ['id', 'code', 'name', 'status', 'branch_id', 'updated_at']],
        'departments' => [Department::class, ['id', 'code', 'name', 'status', 'branch_id', 'division_id', 'updated_at']],
        'positions' => [Position::class, ['id', 'code', 'name', 'status', 'department_id', 'updated_at']],
        'warehouses' => [Warehouse::class, ['id', 'code', 'name', 'status', 'branch_id', 'updated_at']],
        'currencies' => [OrganizationCurrency::class, ['id', 'code', 'name', 'decimal_places', 'status', 'updated_at']],
    ];

    public function reference(Request $request, string $resource): JsonResponse
    {
        abort_unless(isset(self::REFERENCES[$resource]), 404);
        [$model, $fields] = self::REFERENCES[$resource];
        $filters = ExternalReadPagination::filters($request);
        $org = $request->attributes->get('integration_key')->org_id;
        $query = $model::where('org_id', $org);
        // Hide corrupt parent links rather than disclosing foreign organization IDs.
        foreach (['branch_id' => Branch::class, 'division_id' => Division::class, 'department_id' => Department::class] as $column => $parent) {
            if (in_array($column, $fields, true)) {
                $query->where(fn ($q) => $q->whereNull($column)->orWhereIn($column, $parent::where('org_id', $org)->select('id')));
            }
        }
        if (isset($filters['updated_since']) && method_exists($model, 'bootSoftDeletes')) {
            $query->withTrashed();
            $fields[] = 'deleted_at';
        }

        return ExternalReadPagination::page($request, $query, $fields, $filters);
    }

    public function products(Request $request): JsonResponse
    {
        $filters = ExternalReadPagination::filters($request, [
            'search' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', Rule::in(['product', 'service', 'package'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $query = $this->productQuery($request);
        if (isset($filters['updated_since'])) {
            $query->withTrashed();
        }
        if (isset($filters['search'])) {
            $query->where(fn ($q) => $q->where('sku', 'like', '%'.$filters['search'].'%')->orWhere('name', 'like', '%'.$filters['search'].'%'));
        }
        foreach (['type', 'is_active'] as $filter) {
            if (isset($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }
        $fields = $this->productFields($request);
        if (isset($filters['updated_since'])) {
            $fields[] = 'deleted_at';
        }

        return ExternalReadPagination::page($request, $query, $fields, $filters);
    }

    public function product(Request $request, string $id): JsonResponse
    {
        ExternalReadPagination::filters($request, ['limit' => ['prohibited'], 'cursor' => ['prohibited'], 'updated_since' => ['prohibited']]);
        $product = $this->productQuery($request)->whereKey($id)->firstOrFail($this->productFields($request));

        return response()->json(['data' => $product->only($this->productFields($request)), 'meta' => [
            'api_version' => 'v1', 'request_id' => ExternalApiResponse::requestId($request), 'generated_at' => now()->toIso8601String(),
        ]]);
    }

    private function productQuery(Request $request): Builder
    {
        return Product::where('org_id', $request->attributes->get('integration_key')->org_id);
    }

    private function productFields(Request $request): array
    {
        $fields = ['id', 'sku', 'name', 'type', 'category', 'unit', 'is_active', 'track_inventory', 'updated_at'];
        $scopes = $request->attributes->get('integration_key')->scopes;
        if (in_array('products:prices:read', $scopes, true)) {
            $fields[] = 'price';
        }
        if (in_array('products:costs:read', $scopes, true)) {
            $fields[] = 'cost';
        }

        return $fields;
    }
}
