<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterBillHistoryMaxMin extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bill_history', function (Blueprint $table) {
            $table->bigInteger('bill_max_out')->nullable()->after('traf_total');
            $table->bigInteger('bill_max_in')->nullable()->after('bill_max_out');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('slas', function (Blueprint $table) {
            $table->dropColumn(['bill_max_in', 'bill_max_out']);
        });
    }
}
