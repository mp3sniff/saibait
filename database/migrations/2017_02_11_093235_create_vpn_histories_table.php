<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVpnHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vpn_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id');
            $table->string('user_ip')->default('0.0.0.0');
            $table->string('user_port')->default('0');
            $table->string('server_name');
            $table->string('server_ip');
            $table->string('server_domain');
            $table->double('byte_sent')->default(0)->unsigned();
            $table->double('byte_received')->default(0)->unsigned();
            $table->timestamp('session_start');
            $table->timestamp('session_end')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vpn_histories');
    }
}
