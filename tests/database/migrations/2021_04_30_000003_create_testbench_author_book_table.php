<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTestbenchAuthorBookTable extends Migration
{
    public function up(): void
    {
        Schema::create('author_book', function (Blueprint $table): void {
            $table->increments('id');

            $table->unsignedInteger('author_id');
            $table->unsignedInteger('book_id');
        });
    }

    public function down(): void
    {
        Schema::drop('author_book');
    }
}
