<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTestbenchHoursTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hours', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('hourable_type')->nullable();
            $table->unsignedInteger('hourable_id')->nullable();
            $table->string('from', 5)->nullable();
            $table->string('to', 5)->nullable();
            $table->unsignedTinyInteger('weekday')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('hours');
    }
}
