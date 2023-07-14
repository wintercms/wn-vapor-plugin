<?php

namespace Winter\Vapor;

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Laravel\Vapor\ConfiguresQueue;
use Laravel\Vapor\Console\Commands\VaporHealthCheckCommand;
use Laravel\Vapor\Console\Commands\VaporQueueListFailedCommand;
use Laravel\Vapor\Console\Commands\VaporWorkCommand;
use System\Classes\PluginBase;
use Winter\Vapor\Console\VaporMirror;

/**
 * Vapor Plugin Information File
 * @TODO:
 *  - Include httpHandler stub logic
 *  - Replace usage of vapor:mirror with winter:mirror when
 *    https://github.com/wintercms/winter/pull/559 is merged
 */
class Plugin extends PluginBase
{
    use ConfiguresQueue;

    public $elevated = true;

    /**
     * Returns information about this plugin.
     */
    public function pluginDetails(): array
    {
        return [
            'name'        => 'winter.vapor::lang.plugin.name',
            'description' => 'winter.vapor::lang.plugin.description',
            'author'      => 'Winter CMS',
            'icon'        => 'icon-leaf'
        ];
    }

    /**
     * Registers the plugin
     */
    public function register(): void
    {
        // Register the queue worker
        $this->ensureQueueIsConfigured();

        // Register the vapor console
        $this->registerConsole();

        // Register our custom mirror
        $this->registerConsoleCommand('vapor.mirror', VaporMirror::class);
    }

    /**
     * Registers CLI resources provided by this plugin
     */
    public function registerConsole()
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->app[ConsoleKernel::class]->command('vapor:handle {payload}', function () {
            throw new \InvalidArgumentException(
                'Unknown event type. Please create a vapor:handle command to handle custom events.'
            );
        });

        $this->registerConsoleCommand('vapor.work', function ($app) {
            return new VaporWorkCommand($app['queue.vaporWorker']);
        });
        $this->registerConsoleCommand('vapor.queue-failed', VaporQueueListFailedCommand::class);
        $this->registerConsoleCommand('vapor.health-check', VaporHealthCheckCommand::class);
    }
}
