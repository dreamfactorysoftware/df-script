<?php
namespace DreamFactory\Core\Script;

use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Models\SystemTableModelMapper;
use DreamFactory\Core\Script\Models\Python3Config;
use DreamFactory\Core\Script\Services\Python3;
use DreamFactory\Core\System\Components\SystemResourceManager;
use DreamFactory\Core\System\Components\SystemResourceType;
use DreamFactory\Core\Script\Components\ScriptEngineManager;
use DreamFactory\Core\Script\Facades\ScriptEngineManager as ScriptEngineManagerFacade;
use DreamFactory\Core\Script\Handlers\Events\ScriptableEventHandler;
use DreamFactory\Core\Script\Resources\System\EventScript;
use DreamFactory\Core\Script\Resources\System\ScriptType;
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
    public function register()
    {
        // Add our scripting service types.
        $this->app->resolving('df.service', function (ServiceManager $df) {
            $df->addType(
                new ServiceType(
                    [
                        'name'                  => 'nodejs',
                        'label'                 => 'Node.js',
                        'description'           => 'Service that allows client-callable scripts utilizing the system scripting.',
                        'group'                 => ServiceTypeGroups::SCRIPT,
                        'subscription_required' => LicenseLevel::SILVER,
                        'config_handler'        => NodejsConfig::class,
                        'factory'               => function ($config) {
                            return new Nodejs($config);
                        },
                    ]));
            $df->addType(
                new ServiceType(
                    [
                        'name'                  => 'php',
                        'label'                 => 'PHP',
                        'description'           => 'Service that allows client-callable scripts utilizing the system scripting.',
                        'group'                 => ServiceTypeGroups::SCRIPT,
                        'subscription_required' => LicenseLevel::SILVER,
                        'config_handler'        => PhpConfig::class,
                        'factory'               => function ($config) {
                            return new Php($config);
                        },
                    ]));
            $df->addType(
                new ServiceType(
                    [
                        'name'                  => 'python',
                        'label'                 => 'Python',
                        'description'           => 'Service that allows client-callable scripts utilizing the system scripting.',
                        'group'                 => ServiceTypeGroups::SCRIPT,
                        'subscription_required' => LicenseLevel::SILVER,
                        'config_handler'        => PythonConfig::class,
                        'factory'               => function ($config) {
                            return new Python($config);
                        },
                    ]));
            $df->addType(
                new ServiceType(
                    [
                        'name'            => 'python3',
                        'label'           => 'Python3',
                        'description'     => 'Service that allows client-callable scripts utilizing the system scripting.',
                        'group'           => ServiceTypeGroups::SCRIPT,
                        'subscription_required' => LicenseLevel::SILVER,
                        'config_handler'  => Python3Config::class,
                        'factory'         => function ($config) {
                            return new Python3($config);
                        },
                    ]));
            $df->addType(
                new ServiceType(
                    [
                        'name'                  => 'v8js',
                        'label'                 => 'V8js',
                        'description'           => 'Service that allows client-callable scripts utilizing the system scripting.',
                        'group'                 => ServiceTypeGroups::SCRIPT,
                        'subscription_required' => LicenseLevel::SILVER,
                        'config_handler'        => V8jsConfig::class,
                        'factory'               => function ($config) {
                            return new V8js($config);
                        },
                    ]));
        });

        // Add our service types.
        $this->app->resolving('df.system.resource', function (SystemResourceManager $df) {
            $df->addType(
                new SystemResourceType([
                    'name'                  => 'script_type',
                    'label'                 => 'Script Types',
                    'description'           => 'Read-only system scripting types.',
                    'class_name'            => ScriptType::class,
                    'subscription_required' => LicenseLevel::SILVER,
                    'read_only'             => true,
                ])
            );
            $df->addType(
                new SystemResourceType([
                    'name'                  => 'event_script',
                    'label'                 => 'Event Scripts',
                    'description'           => 'Allows registering server-side scripts to system generated events.',
                    'class_name'            => EventScript::class,
                    'subscription_required' => LicenseLevel::SILVER,
                ])
            );
        });

        // Add our table model mapping
        $this->app->resolving('df.system.table_model_map', function (SystemTableModelMapper $df) {
            $df->addMapping('event_script', EventScript::class);
        });

        // The script engine manager is used to resolve various script engines.
        // It also implements the resolver interface which may be used by other components adding script engines.
        $this->app->singleton('df.script', function ($app) {
            return new ScriptEngineManager($app);
        });

        // merge in df config, https://laravel.com/docs/5.4/packages#resources
        $this->mergeConfigFrom(__DIR__ . '/../config/df.php', 'df');
    }

    public function boot()
    {
        $this->app->alias('df.script', ScriptEngineManager::class);
        $loader = AliasLoader::getInstance();
        $loader->alias('ScriptEngineManager', ScriptEngineManagerFacade::class);

        // add migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        Event::subscribe(new ScriptableEventHandler());
    }
}
