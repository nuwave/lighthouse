<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CreateTestbenchRoleUserTable extends Migration
{
    public function up(): void
    {
        Schema::create('role_user', function (Blueprint $table): void {
            $table->id();
            $table->string('meta')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
        });
    }

    public function down(): void
    {
        Schema::drop('role_user');
    }
}
