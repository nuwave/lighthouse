<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Constants;

class CreateTestbenchTagsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('default_string')->default(Constants::TAGS_DEFAULT_STRING);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('tags');
    }
}
