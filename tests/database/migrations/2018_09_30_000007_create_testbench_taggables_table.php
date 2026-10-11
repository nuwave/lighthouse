<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CreateTestbenchTaggablesTable extends Migration
{
    public function up(): void
    {
        Schema::create('taggables', function (Blueprint $table): void {
            $table->unsignedBigInteger('tag_id');
            $table->unsignedBigInteger('taggable_id');
            $table->string('taggable_type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('taggables');
    }
}
