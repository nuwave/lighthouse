<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CreateTestbenchAclsTable extends Migration
{
    public function up(): void
    {
        Schema::create('acls', function (Blueprint $table): void {
            $table->id();
            $table->boolean('create_post');
            $table->boolean('read_post');
            $table->boolean('update_post');
            $table->boolean('delete_post');
        });
    }

    public function down(): void
    {
        Schema::drop('acls');
    }
}
