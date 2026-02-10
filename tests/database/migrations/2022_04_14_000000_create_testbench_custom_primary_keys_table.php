<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CreateTestbenchCustomPrimaryKeysTable extends Migration
{
    public function up(): void
    {
        Schema::create('custom_primary_keys', function (Blueprint $table): void {
            $table->id('custom_primary_key_id');
            $table->unsignedBigInteger('user_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::drop('custom_primary_keys');
    }
}
