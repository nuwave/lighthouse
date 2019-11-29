<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTestbenchBrandSupplierTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('brand_supplier', function (Blueprint $table): void {
            $table->unsignedInteger('brand_id');
            $table->unsignedInteger('supplier_id');
            $table->boolean('is_preferred_supplier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('brand_supplier');
    }
}
