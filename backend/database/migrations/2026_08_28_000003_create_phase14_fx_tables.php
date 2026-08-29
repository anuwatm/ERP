<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_currencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 3);
            $table->string('name', 100);
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['org_id', 'code']);
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('base_currency', 3);
            $table->string('quote_currency', 3);
            $table->date('rate_date');
            $table->decimal('rate', 18, 6);
            $table->string('source', 50)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['org_id', 'base_currency', 'quote_currency', 'rate_date'], 'fx_rate_org_pair_date_uq');
            $table->index(['org_id', 'quote_currency', 'rate_date'], 'fx_rate_org_quote_date_idx');
        });

        Schema::create('fx_revaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('source_type', 100);
            $table->uuid('source_id');
            $table->string('currency', 3);
            $table->date('revaluation_month');
            $table->decimal('foreign_amount', 18, 2);
            $table->decimal('closing_rate', 18, 6);
            $table->decimal('base_before', 18, 2);
            $table->decimal('base_after', 18, 2);
            $table->decimal('difference', 18, 2);
            $table->foreignUuid('journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $table->foreignUuid('reversal_journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['org_id', 'source_type', 'source_id', 'revaluation_month'], 'fx_reval_org_source_month_uq');
        });

        foreach (['invoices', 'quotations', 'purchase_orders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('base_currency', 3)->default('THB')->after('currency');
                $table->decimal('exchange_rate', 18, 6)->default(1)->after('base_currency');
                $table->decimal('base_subtotal', 18, 2)->default(0)->after('exchange_rate');
                $table->decimal('base_tax_amount', 18, 2)->default(0)->after('base_subtotal');
                $table->decimal('base_total', 18, 2)->default(0)->after('base_tax_amount');
            });
        }
        Schema::table('credit_debit_notes', function (Blueprint $table) {
            $table->string('currency', 3)->default('THB')->after('total');
            $table->string('base_currency', 3)->default('THB')->after('currency');
            $table->decimal('exchange_rate', 18, 6)->default(1)->after('base_currency');
            $table->decimal('base_subtotal', 18, 2)->default(0)->after('exchange_rate');
            $table->decimal('base_tax_amount', 18, 2)->default(0)->after('base_subtotal');
            $table->decimal('base_total', 18, 2)->default(0)->after('base_tax_amount');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('base_paid_amount', 18, 2)->default(0)->after('base_total');
            $table->decimal('base_balance_due', 18, 2)->default(0)->after('base_paid_amount');
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('currency', 3)->default('THB')->after('amount');
            $table->string('base_currency', 3)->default('THB')->after('currency');
            $table->decimal('exchange_rate', 18, 6)->default(1)->after('base_currency');
            $table->decimal('base_amount', 18, 2)->default(0)->after('exchange_rate');
            $table->decimal('base_tax_amount', 18, 2)->default(0)->after('base_amount');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->string('currency', 3)->default('THB')->after('amount');
            $table->string('base_currency', 3)->default('THB')->after('currency');
            $table->decimal('exchange_rate', 18, 6)->default(1)->after('base_currency');
            $table->decimal('base_amount', 18, 2)->default(0)->after('exchange_rate');
            $table->decimal('invoice_base_amount', 18, 2)->default(0)->after('base_amount');
        });
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->foreignUuid('chart_of_account_id')->nullable()->after('branch_id')->constrained('chart_of_accounts')->nullOnDelete();
        });

        $permissions = [
            ['code' => 'fx.view', 'module' => 'fx', 'action' => 'view', 'description' => 'View currencies and exchange rates'],
            ['code' => 'fx.manage', 'module' => 'fx', 'action' => 'manage', 'description' => 'Manage currencies and exchange rates'],
            ['code' => 'fx.revalue', 'module' => 'fx', 'action' => 'revalue', 'description' => 'Run FX revaluation and reversal'],
        ];
        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(['code' => $permission['code']], array_merge($permission, ['id' => DB::table('permissions')->where('code', $permission['code'])->value('id') ?: (string) Str::orderedUuid(), 'created_at' => now(), 'updated_at' => now()]));
        }
        $ids = DB::table('permissions')->whereIn('code', array_column($permissions, 'code'))->pluck('id');
        foreach (['owner', 'admin', 'finance'] as $roleCode) {
            DB::table('roles')->where('code', $roleCode)->get(['id'])->each(function ($role) use ($ids): void {
                foreach ($ids as $id) {
                    DB::table('role_permissions')->updateOrInsert(['role_id' => $role->id, 'permission_id' => $id]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('bank_accounts', fn (Blueprint $table) => $table->dropConstrainedForeignId('chart_of_account_id'));
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn(['currency', 'base_currency', 'exchange_rate', 'base_amount', 'invoice_base_amount']));
        Schema::table('expenses', fn (Blueprint $table) => $table->dropColumn(['currency', 'base_currency', 'exchange_rate', 'base_amount', 'base_tax_amount']));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn(['base_paid_amount', 'base_balance_due']));
        foreach (['invoices', 'quotations', 'purchase_orders'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn(['base_currency', 'exchange_rate', 'base_subtotal', 'base_tax_amount', 'base_total']));
        }
        Schema::table('credit_debit_notes', fn (Blueprint $table) => $table->dropColumn(['currency', 'base_currency', 'exchange_rate', 'base_subtotal', 'base_tax_amount', 'base_total']));
        Schema::dropIfExists('fx_revaluations');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('organization_currencies');
    }
};
