<?php

namespace App\Queue\Connectors;

use App\Queue\CloudTasksQueue;
use App\Services\CloudTasks\CloudTasksDispatcher;
use Illuminate\Queue\Connectors\ConnectorInterface;

class CloudTasksConnector implements ConnectorInterface
{
    public function __construct(
        private readonly CloudTasksDispatcher $dispatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function connect(array $config): CloudTasksQueue
    {
        return new CloudTasksQueue(
            $this->dispatcher,
            $config['queue'] ?? 'default',
        );
    }
}
