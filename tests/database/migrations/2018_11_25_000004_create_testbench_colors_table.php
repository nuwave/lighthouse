<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTestbenchColorsTable extends Migration
{
    public function up(): void
    {
        Schema::create('colors', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');

            $table->unsignedInteger('creator_id')->nullable();
            $table->string('creator_type')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('colors');
    }
}
