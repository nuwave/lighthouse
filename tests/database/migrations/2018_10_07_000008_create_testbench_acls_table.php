<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTestbenchAclsTable extends Migration
{
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

    public function down(): void
    {
        Schema::drop('acls');
    }
}
