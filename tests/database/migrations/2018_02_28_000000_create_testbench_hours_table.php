<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTestbenchHoursTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hours', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('hourable_type', 15);
            $table->unsignedInteger('hourable_id');
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
