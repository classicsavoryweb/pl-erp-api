<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropManagementMaxFromPhilhealthPercentageBaseTable extends Migration
{
    public function up()
    {
        Schema::table('philhealth_percentage_base', function (Blueprint $table) {
            $table->dropColumn('management_max');
        });
    }

    public function down()
    {
        Schema::table('philhealth_percentage_base', function (Blueprint $table) {
            //
        });
    }
}

