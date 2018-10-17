<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSubscriptionTables extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('lighthouse_topics', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key')->unique();
        });
        Schema::create('lighthouse_subscriptions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('topic_id');
            $table->string('channel')->unique();
            $table->json('args');
            $table->text('context');
            $table->string('operation_name');
            $table->text('query_string');
            $table->foreign('topic_id')->references('id')->on('lighthouse_topics')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('lighthouse_topics');
        Schema::dropIfExists('lighthouse_subscriptions');
    }
}
