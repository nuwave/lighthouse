<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedSmallInteger('interest_id')->nullable();
            $table->string('uid')->unique()->collation('utf8mb4_bin');
            $table->string('abid')->nullable();
            $table->enum('relation', ['FAMILY_FRIEND', 'RECENTLY_MET', 'REFERRAL']);
            $table->string('referral_name')->nullable();
            $table->enum('first_touch_type', ['ARTICLE', 'BOOKLET', 'EDUCATIONAL_VIDEO', 'PURCHASE'])->nullable();

            // one-to-one relationship with 'users'
            $table->primary('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('interest_id')->references('id')->on('interests');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('leads');
    }
}
