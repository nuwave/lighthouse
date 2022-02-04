<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTestbenchPostsTable extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->string('body')->nullable();

            $table->unsignedInteger('task_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('parent_id')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('posts', function (Blueprint $table): void {
            $table->foreign('parent_id')
                ->references('id')
                ->on('posts');
        });
    }

    public function down(): void
    {
        Schema::drop('posts');
    }
}
