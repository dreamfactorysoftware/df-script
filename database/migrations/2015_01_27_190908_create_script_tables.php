<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Symfony\Component\Console\Output\ConsoleOutput;

class CreateScriptTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $driver = Schema::getConnection()->getDriverName();
        $sqlsrv = (('sqlsrv' === $driver) || ('dblib' === $driver));
        // Even though we take care of this scenario in the code,
        // SQL Server does not allow potential cascading loops,
        // so set the default no action and clear out created/modified by another user when deleting a user.
        $userOnDelete = ($sqlsrv ? 'no action' : 'set null');

        $output = new ConsoleOutput();
        $output->writeln("Migration driver used: $driver");

        // Script Service Config
        Schema::create(
            'script_config',
            function (Blueprint $t){
                $t->integer('service_id')->unsigned()->primary();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->mediumText('content')->nullable();
                $t->text('config')->nullable();
            }
        );

        // Event Scripts
        Schema::create(
            'event_script',
            function (Blueprint $t) use ($userOnDelete){
                $t->string('name', 80)->primary();
                $t->string('type', 40);
                $t->boolean('is_active')->default(0);
                $t->mediumText('content')->nullable();
                $t->text('config')->nullable();
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($userOnDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($userOnDelete);
            }
        );

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop created tables in reverse order

        // Script Service Configs
        Schema::dropIfExists('script_config');
        // Event Scripts
        Schema::dropIfExists('event_script');
    }
}
