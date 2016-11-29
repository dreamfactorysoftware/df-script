<?php

namespace DreamFactory\Core\Script\Jobs;

use DreamFactory\Core\Jobs\Job;
use DreamFactory\Core\Script\Components\ScriptHandler;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class ScriptJob extends Job  implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels, ScriptHandler;

    /**
     * Create a new job instance.
     * @param array $config
     */
    public function __construct($config = [])
    {
        parent::__construct($config);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
    }
}
