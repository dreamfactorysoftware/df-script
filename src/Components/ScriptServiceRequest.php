<?php
namespace DreamFactory\Core\Script\Components;

use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Components\InternalServiceRequest;
use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Enums\ServiceRequestorTypes;

/**
 * Class ScriptServiceRequest
 *
 */
class ScriptServiceRequest extends InternalServiceRequest implements ServiceRequestInterface
{
    protected $requestorType = ServiceRequestorTypes::SCRIPT;

    public function __construct($method = Verbs::GET, $parameters = [], $headers = [])
    {
        $this->setMethod($method);
        $this->setParameters($parameters);
        $this->setHeaders($headers);
    }
}