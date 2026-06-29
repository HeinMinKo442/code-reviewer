<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Throwable;

class CloudTaskHandlerController extends Controller
{
    /**
     * Process a Laravel queue payload delivered by Google Cloud Tasks.
     */
    public function handle(Request $request, Worker $worker): Response
    {
        $payload = $request->getContent();

        if ($payload === '') {
            return response('Empty queue payload.', Response::HTTP_BAD_REQUEST);
        }

        $queueName = $request->header('X-CloudTasks-QueueName')
            ?? $request->header('X-Cloud-Tasks-QueueName')
            ?? config('cloudtasks.queue', 'default');

        try {
            $worker->process(
                'cloudtasks',
                new SyncJob(app(), $payload, 'cloudtasks', (string) $queueName),
                new WorkerOptions(
                    'cloudtasks',
                    0,
                    256,
                    (int) config('queue.connections.cloudtasks.timeout', 900),
                    3,
                    3,
                ),
            );
        } catch (Throwable $exception) {
            report($exception);

            return response('Queue job processing failed.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response('OK', Response::HTTP_OK);
    }
}
