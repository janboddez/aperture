<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChannelSourceFetchOriginal extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channel_source', function (Blueprint $table) {
            $table->boolean('fetch_original')->default(0);
            $table->string('site_url', 255)->nullable();
            $table->string('xpath_selector', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('channel_source', function (Blueprint $table) {
            $table->dropColumn('fetch_original');
            $table->dropColumn('site_url');
            $table->dropColumn('xpath_selector');
        });
    }
}
