<?php
namespace DreamFactory\Core\Script\Events;

use DreamFactory\Core\Script\Models\EventScript;
use Illuminate\Queue\SerializesModels;

abstract class BaseEventScriptEvent
{
    use SerializesModels;

    public $script;

    /**
     * Create a new event instance.
     *
     * @param EventScript $script
     */
    public function __construct(EventScript $script)
    {
        $this->script = $script;
    }
}
