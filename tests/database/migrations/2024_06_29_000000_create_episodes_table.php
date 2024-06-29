<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CreateTestbenchNullConnectionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('episodes', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->timestamp('schedule_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::drop('episodes');
    }
}
