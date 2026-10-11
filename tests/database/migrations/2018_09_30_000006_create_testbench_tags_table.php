<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Constants;

final class CreateTestbenchTagsTable extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('default_string')->default(Constants::TAGS_DEFAULT_STRING);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('tags');
    }
}
