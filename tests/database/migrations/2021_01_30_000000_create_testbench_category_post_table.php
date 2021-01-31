<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTestbenchCategoryPostTable extends Migration
{
    public function up(): void
    {
        Schema::create('category_post', function (Blueprint $table): void {
            $table->increments('id');

            $table->unsignedInteger('category_id');
            $table->unsignedInteger('post_id');
        });
    }

    public function down(): void
    {
        Schema::drop('category_post');
    }
}
