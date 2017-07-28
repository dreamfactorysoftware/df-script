<?php

namespace DreamFactory\Core\Script\Jobs;

use Crypt;
use DreamFactory\Core\Events\ServiceEvent;
use DreamFactory\Core\Script\Models\EventScript;
use Log;
use Session;

class ServiceEventScriptJob extends ScriptJob
{
    public $script_id;

    public $event;

    public $session;

    /**
     * Create a new job instance.
     * @param integer      $id
     * @param ServiceEvent $event
     * @param array        $config
     */
    public function __construct($id, ServiceEvent $event, $config = [])
    {
        $this->script_id = $id;
        $this->event = Crypt::encrypt(json_encode($event->makeData()));
        $this->session = Crypt::encrypt(json_encode(Session::all()));

        parent::__construct($config);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::notice('Queued Script handled for ' . $this->script_id);
        if ($script = $this->getEventScript($this->script_id)) {
            $session = json_decode(Crypt::decrypt($this->session), true);
            Session::replace($session);

            $data = json_decode(Crypt::decrypt($this->event), true);
            if (null !== $this->handleScript($script->name, $script->content, $script->type, $script->config, $data)) {
                Log::notice('Queued Script success for ' . $this->script_id);
            }
        }
    }

    /**
     * @param string $name
     *
     * @return EventScript|null
     */
    public function getEventScript($name)
    {
        if (empty($model = EventScript::whereName($name)->whereIsActive(true)->first())) {
            return null;
        }

        $model->content = \DreamFactory\Core\Utility\Session::translateLookups($model->content, true);
        if (!is_array($model->config)) {
            $model->config = [];
        }

        return $model;
    }
}