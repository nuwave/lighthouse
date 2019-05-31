<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTestbenchPostsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->string('body')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('task_id');
            $table->unsignedInteger('parent_id')->nullable();
            $table->timestamps();
        });

        Schema::table('posts', function (Blueprint $table): void {
            $table->foreign('parent_id')
                ->references('id')
                ->on('posts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('posts');
    }
}
