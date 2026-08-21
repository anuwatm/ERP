<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE invoices MODIFY invoice_no VARCHAR(30) NOT NULL');
            DB::statement('ALTER TABLE expenses MODIFY expense_no VARCHAR(30) NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE invoices MODIFY invoice_no CHAR(6) NOT NULL');
            DB::statement('ALTER TABLE expenses MODIFY expense_no CHAR(6) NOT NULL');
        }
    }
};
