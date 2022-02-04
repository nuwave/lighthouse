<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTestbenchCategoriesTable extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->increments('category_id');
            $table->string('name');

            $table->unsignedInteger('parent_id')->nullable();

            $table->timestamps();

            $table->foreign('parent_id')->references('category_id')->on('categories');
        });
    }

    public function down(): void
    {
        Schema::drop('categories');
    }
}
