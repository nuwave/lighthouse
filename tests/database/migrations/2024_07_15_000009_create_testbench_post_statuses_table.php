<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CreateTestbenchPostStatusesTable extends Migration
{
    public function up(): void
    {
        Schema::create('post_statuses', function (Blueprint $table): void {
            $table->increments('id');
            $table->enum('status' , ["DONE" , "PENDING"]);
            $table->unsignedInteger("post_id")
                ->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('post_statuses');
    }
}
