<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CreateTestbenchRolesTable extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');

            $table->unsignedInteger('acl_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::drop('roles');
    }
}
