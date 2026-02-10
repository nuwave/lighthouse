<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CreateTestbenchPodcastsTable extends Migration
{
    public function up(): void
    {
        Schema::create('podcasts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamp('schedule_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('podcasts');
    }
}
