<?php
namespace DreamFactory\Core\Script;

use DreamFactory\Core\Components\ServiceDocBuilder;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Models\SystemTableModelMapper;
use DreamFactory\Core\Resources\System\SystemResourceManager;
use DreamFactory\Core\Script\Components\ScriptEngineManager;
use DreamFactory\Core\Script\Facades\ScriptEngineManager as ScriptEngineManagerFacade;
use DreamFactory\Core\Script\Handlers\Events\ScriptableEventHandler;
use DreamFactory\Core\Script\Resources\System\EventScript;
use DreamFactory\Core\Script\Resources\System\ScriptType;
use DreamFactory\Core\Resources\System\SystemResourceType;
use DreamFactory\Core\Script\Models\NodejsConfig;
use DreamFactory\Core\Script\Models\PhpConfig;
use DreamFactory\Core\Script\Models\PythonConfig;
use DreamFactory\Core\Script\Models\V8jsConfig;
use DreamFactory\Core\Script\Services\Nodejs;
use DreamFactory\Core\Script\Services\Php;
use DreamFactory\Core\Script\Services\Python;
use DreamFactory\Core\Script\Services\V8js;
use DreamFactory\Core\Services\ServiceManager;
use DreamFactory\Core\Services\ServiceType;
use Event;
use Illuminate\Foundation\AliasLoader;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    use ServiceDocBuilder;

    public function boot()
    {
        $this->app->alias('df.script', ScriptEngineManager::class);
        $loader = AliasLoader::getInstance();
        $loader->alias('ScriptEngineManager', ScriptEngineManagerFacade::class);

        // Add our scripting service types.
        $this->app->resolving('df.service', function (ServiceManager $df) {
            $df->addType(
                new ServiceType(
                    [
                        'name'            => 'nodejs',
                        'label'           => 'Node.js',
                        'description'     => 'Service that allows client-callable scripts utilizing the system scripting.',
                        'group'           => ServiceTypeGroups::SCRIPT,
                        'config_handler'  => NodejsConfig::class,
                        'default_api_doc' => function ($service) {
                            return $this->buildServiceDoc($service->id, Nodejs::getApiDocInfo($service));
                        },
                        'factory'         => function ($config) {
                            return new Nodejs($config);
                        },
                    ]));
            $df->addType(
                new ServiceType(
                    [
                        'name'            => 'php',
                        'label'           => 'PHP',
                        'description'     => 'Service that allows client-callable scripts utilizing the system scripting.',
                        'group'           => ServiceTypeGroups::SCRIPT,
                        'config_handler'  => PhpConfig::class,
                        'default_api_doc' => function ($service) {
                            return $this->buildServiceDoc($service->id, Php::getApiDocInfo($service));
                        },
                        'factory'         => function ($config) {
                            return new Php($config);
                        },
                    ]));
            $df->addType(
                new ServiceType(
                    [
                        'name'            => 'python',
                        'label'           => 'Python',
                        'description'     => 'Service that allows client-callable scripts utilizing the system scripting.',
                        'group'           => ServiceTypeGroups::SCRIPT,
                        'config_handler'  => PythonConfig::class,
                        'default_api_doc' => function ($service) {
                            return $this->buildServiceDoc($service->id, Python::getApiDocInfo($service));
                        },
                        'factory'         => function ($config) {
                            return new Python($config);
                        },
                    ]));
            $df->addType(
                new ServiceType(
                    [
                        'name'            => 'v8js',
                        'label'           => 'V8js',
                        'description'     => 'Service that allows client-callable scripts utilizing the system scripting.',
                        'group'           => ServiceTypeGroups::SCRIPT,
                        'config_handler'  => V8jsConfig::class,
                        'default_api_doc' => function ($service) {
                            return $this->buildServiceDoc($service->id, V8js::getApiDocInfo($service));
                        },
                        'factory'         => function ($config) {
                            return new V8js($config);
                        },
                    ]));
        });

        // Add our service types.
        $this->app->resolving('df.system.resource', function (SystemResourceManager $df) {
            $df->addType(
                new SystemResourceType([
                    'name'        => 'script_type',
                    'label'       => 'Script Types',
                    'description' => 'Read-only system scripting types.',
                    'class_name'  => ScriptType::class,
                    'read_only'   => true,
                ])
            );
            $df->addType(
                new SystemResourceType([
                    'name'        => 'event_script',
                    'label'       => 'Event Scripts',
                    'description' => 'Allows registering server-side scripts to system generated events.',
                    'class_name'  => EventScript::class,
                ])
            );
        });

        // Add our table model mapping
        $this->app->resolving('df.system.table_model_map', function (SystemTableModelMapper $df) {
            $df->addMapping('event_script', EventScript::class);
        });

        // add migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        Event::subscribe(new ScriptableEventHandler());
    }

    public function register()
    {
        // The script engine manager is used to resolve various script engines.
        // It also implements the resolver interface which may be used by other components adding script engines.
        $this->app->singleton('df.script', function ($app) {
            return new ScriptEngineManager($app);
        });
    }
}
