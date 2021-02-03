<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTestbenchProductsTable extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            // Composite primary key
            $table->string('barcode');
            $table->string('uuid');

            $table->string('name');

            $table->unsignedInteger('color_id');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('products');
    }
}
