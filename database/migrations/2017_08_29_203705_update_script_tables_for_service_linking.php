<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateScriptTablesForServiceLinking extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(Schema::hasTable('script_config') && !Schema::hasColumn('script_config', 'storage_service_id')){
            Schema::table('script_config', function (Blueprint $t){
                $t->string('scm_reference')->nullable()->after('config');
                $t->string('storage_path')->nullable()->after('config');
                $t->integer('storage_service_id')->unsigned()->nullable()->after('config');
            });
        }

        if(Schema::hasTable('event_script') && !Schema::hasColumn('event_script', 'storage_service_id')){
            Schema::table('event_script', function (Blueprint $t){
                $t->string('scm_reference')->nullable()->after('config');
                $t->string('storage_path')->nullable()->after('config');
                $t->integer('storage_service_id')->unsigned()->nullable()->after('config');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if(Schema::hasTable('script_config') && Schema::hasColumn('script_config', 'storage_service_id')) {
            Schema::table('script_config', function (Blueprint $t){
                $t->dropColumn('storage_service_id');
                $t->dropColumn('storage_path');
                $t->dropColumn('scm_reference');
            });
        }

        if(Schema::hasTable('event_script') && Schema::hasColumn('event_script', 'storage_service_id')) {
            Schema::table('event_script', function (Blueprint $t){
                $t->dropColumn('storage_service_id');
                $t->dropColumn('storage_path');
                $t->dropColumn('scm_reference');
            });
        }
    }
}
