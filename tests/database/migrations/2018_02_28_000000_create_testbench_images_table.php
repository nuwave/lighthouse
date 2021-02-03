<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTestbenchImagesTable extends Migration
{
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('url')->nullable();

            $table->unsignedInteger('imageable_id')->nullable();
            $table->string('imageable_type')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('images');
    }
}
