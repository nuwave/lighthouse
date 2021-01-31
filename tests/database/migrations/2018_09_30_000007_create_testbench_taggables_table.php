<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTestbenchTaggablesTable extends Migration
{
    public function up(): void
    {
        Schema::create('taggables', function (Blueprint $table): void {
            $table->unsignedInteger('tag_id');

            $table->unsignedInteger('taggable_id');
            $table->string('taggable_type');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('taggables');
    }
}
