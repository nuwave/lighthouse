<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CreateTestbenchCategoryPostTable extends Migration
{
    public function up(): void
    {
        Schema::create('category_post', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('post_id');
        });
    }

    public function down(): void
    {
        Schema::drop('category_post');
    }
}
