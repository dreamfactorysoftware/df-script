<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ScriptImplementsAccessList extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('script_config', function (Blueprint $table) {
            $table->boolean('implements_access_list')->default(0)->after('queued');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('script_config', function (Blueprint $table) {
            $table->dropColumn('implements_access_list');
        });
    }
}
