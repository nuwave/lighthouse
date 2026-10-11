<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CreateTestbenchAlternateConnectionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('alternate_connections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::drop('alternate_connections');
    }
}
