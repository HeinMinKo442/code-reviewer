<?php

namespace App\Providers;

use App\Queue\Connectors\CloudTasksConnector;
use App\Services\CloudTasks\CloudTasksDispatcher;
use App\Services\Github\GitHubService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GitHubService::class, function (): GitHubService {
            return new GitHubService((string) config('services.github.token'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Queue::extend('cloudtasks', function () {
            return (new CloudTasksConnector(app(CloudTasksDispatcher::class)))
                ->connect(config('queue.connections.cloudtasks'));
        });
    }
}
