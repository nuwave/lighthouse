<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTestbenchAclsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('acls', function (Blueprint $table): void {
            $table->increments('id');
            $table->boolean('create_post');
            $table->boolean('read_post');
            $table->boolean('update_post');
            $table->boolean('delete_post');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('acls');
    }
}
