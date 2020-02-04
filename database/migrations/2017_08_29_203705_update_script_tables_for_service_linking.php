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
        $driver = Schema::getConnection()->getDriverName();
        // Even though we take care of this scenario in the code,
        // SQL Server does not allow potential cascading loops,
        // so set the default no action and clear out created/modified by another user when deleting a user.
        $onDelete = (('sqlsrv' === $driver) ? 'no action' : 'set null');

        if (Schema::hasTable('script_config') && !Schema::hasColumn('script_config', 'storage_service_id')) {
            Schema::table('script_config', function (Blueprint $t) use ($onDelete) {
                $t->string('scm_reference')->nullable()->after('config');
                $t->string('scm_repository')->nullable()->after('config');
                $t->string('storage_path')->nullable()->after('config');
                $t->integer('storage_service_id')->unsigned()->nullable()->after('config');
                $t->foreign('storage_service_id')->references('id')->on('service')->onDelete($onDelete);
            });
        }

        if (Schema::hasTable('event_script') && !Schema::hasColumn('event_script', 'storage_service_id')) {
            Schema::table('event_script', function (Blueprint $t) use ($onDelete) {
                $t->string('scm_reference')->nullable()->after('config');
                $t->string('scm_repository')->nullable()->after('config');
                $t->string('storage_path')->nullable()->after('config');
                $t->integer('storage_service_id')->unsigned()->nullable()->after('config');
                $t->foreign('storage_service_id')->references('id')->on('service')->onDelete($onDelete);
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
        if (Schema::hasTable('script_config') && Schema::hasColumn('script_config', 'storage_service_id')) {
            Schema::table('script_config', function (Blueprint $t) {
                if (DB::getDriverName() !== 'sqlite') {
                    $t->dropForeign('script_config_storage_service_id_foreign');
                }
                $t->dropColumn([
                    'storage_service_id',
                    'storage_path',
                    'scm_reference',
                    'scm_repository'
                ]);
            });
        }

        if (Schema::hasTable('event_script') && Schema::hasColumn('event_script', 'storage_service_id')) {
            Schema::table('event_script', function (Blueprint $t) {
                if (DB::getDriverName() !== 'sqlite') {
                    $t->dropForeign('event_script_storage_service_id_foreign');
                }
                $t->dropColumn([
                    'storage_service_id',
                    'storage_path',
                    'scm_reference',
                    'scm_repository'
                ]);
            });
        }
    }
}
