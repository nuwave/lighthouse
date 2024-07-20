<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CreateTestbenchInvoicesTable extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create('payable_line_items', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();

            $table->morphs('object');
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();

            $table->nullableMorphs('fulfilled_by');
        });
    }

    public function down(): void
    {
        Schema::drop('invoices');
        Schema::drop('line_items');
        Schema::drop('payments');
    }
}
