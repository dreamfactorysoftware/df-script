<?php

namespace DreamFactory\Core\Script\Jobs;

use Crypt;
use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Script\Services\Script;
use Log;
use ServiceManager;

class ScriptServiceJob extends ScriptJob
{
    public $service_id;

    public $resource;

    public $request;

    public $session;

    /**
     * Create a new job instance.
     * @param integer                 $id
     * @param ServiceRequestInterface $request
     * @param ServiceRequestInterface $resource
     * @param array                   $config
     */
    public function __construct($id, ServiceRequestInterface $request, $resource = null, $config = [])
    {
        $this->service_id = $id;
        $this->resource = $resource;
        $this->request = Crypt::encrypt(json_encode($request->toArray()));
        $this->session = Crypt::encrypt(json_encode(\Session::all()));

        parent::__construct($config);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::notice('Queued Script handled for ' . $this->service_id);
        /** @var Script $service */
        if (!empty($service = ServiceManager::getServiceById($this->service_id))) {

            $session = json_decode(Crypt::decrypt($this->session), true);
            \Session::replace($session);

            $data = [
                'resource' => $this->resource,
                'request'  => json_decode(Crypt::decrypt($this->request), true),
            ];

            $logOutput = ($data['request']['parameters']['log_output'] ?? true);
            if (null !== $this->handleScript('service.' . $service->getName(), $service->getScriptContent(),
                    $service->getType(), $service->getScriptConfig(), $data, $logOutput)
            ) {
                Log::notice('Queued Script success for ' . $service->getName());
            }
        }
    }
}