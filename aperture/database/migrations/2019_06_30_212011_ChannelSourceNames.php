<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChannelSourceNames extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channel_source', function (Blueprint $table) {
            $table->string('name', 255)->nullable();
        });

        DB::table('sources')
            ->where('format', '!=', 'apikey')
            ->update([
                'name' => '',
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('channel_source', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
}
