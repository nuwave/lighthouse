<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTestbenchCommentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('comment');

            $table->unsignedInteger('user_id');
            $table->unsignedInteger('post_id');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('comments');
    }
}
