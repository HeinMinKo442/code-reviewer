<?php

namespace App\Queue;

use App\Services\CloudTasks\CloudTasksDispatcher;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;

class CloudTasksQueue extends Queue implements QueueContract
{
    public function __construct(
        protected CloudTasksDispatcher $dispatcher,
        protected string $defaultQueue = 'default',
    ) {}

    public function size($queue = null): int
    {
        return 0;
    }

    public function push($job, $data = '', $queue = null)
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
            null,
            function (string $payload, ?string $queue): void {
                $this->dispatcher->dispatch($payload, $this->getQueue($queue));
            }
        );
    }

    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $this->dispatcher->dispatch(
            $payload,
            $this->getQueue($queue),
            isset($options['delay']) ? (int) $options['delay'] : null,
        );

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? ($decoded['uuid'] ?? null) : null;
    }

    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
            $delay,
            function (string $payload, ?string $queue) use ($delay): void {
                $this->dispatcher->dispatch($payload, $this->getQueue($queue), $this->secondsUntil($delay));
            }
        );
    }

    public function pop($queue = null)
    {
        return null;
    }

    public function getQueue($queue = null): string
    {
        return $queue ?: $this->defaultQueue;
    }
}
