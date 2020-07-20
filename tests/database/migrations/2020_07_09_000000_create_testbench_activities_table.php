<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTestbenchActivitiesTable extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table): void {
            $table->increments('id');

            $table->unsignedInteger('user_id');
            $table->morphs('content');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('activities');
    }
}
