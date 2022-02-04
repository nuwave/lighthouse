<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTestbenchContractorsTable extends Migration
{
    public function up(): void
    {
        Schema::create('contractors', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('position');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('contractors');
    }
}
