<?php

namespace Winter\Vapor;

use Config;
use Event;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Laravel\Vapor\ConfiguresQueue;
use Laravel\Vapor\Console\Commands\VaporHealthCheckCommand;
use Laravel\Vapor\Console\Commands\VaporQueueListFailedCommand;
use Laravel\Vapor\Console\Commands\VaporWorkCommand;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use System\Classes\PluginBase;
use Winter\Vapor\Console\VaporMirror;

/**
 * Vapor Plugin Information File
 * @TODO:
 *  - Add support to winter/wn-cms-module for creating database-only themes when databaseTemplates is enabled
 *  - Include httpHandler stub logic
 *  - Replace usage of vapor:mirror with winter:mirror when
 *    https://github.com/wintercms/winter/pull/559 is merged
 */
class Plugin extends PluginBase
{
    use ConfiguresQueue;

    public $elevated = true;

    public $require = [
        'Winter.DriverAWS',
    ];

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
     * Boot the plugin
     */
    public function boot()
    {
        // Add App::isRunningOnVapor() macro
        $this->app->macro('isRunningOnVapor', function() {
            return getenv('VAPOR_ARTIFACT_NAME') !== false;
        });

        if ($this->app->isRunningOnVapor()) {
            // Add required header to binary file responses
            Event::listen(RequestHandled::class, function (RequestHandled $event) {
                // @TODO: Consider adding to Laravel\Vapor\Runtime\Http\Middleware\EnsureBinaryEncoding
                if ($event->response instanceof BinaryFileResponse) {
                    $event->response->headers->set('X-Vapor-Base64-Encode', 'True');
                }
            });

            // The only writable path on Vapor is /tmp, so we need to set the temp path to /tmp
            // @see https://docs.vapor.build/1.0/resources/storage.html#temporary-storage
            Config::set('app.tempPath', '/tmp');

            // Set the trusted proxies to ** to ensure that the correct IP address is used when
            // using the Request::ip() method. This is required because Vapor uses a load balancer
            // to route requests to the application.
            Config::set('app.trustedProxies', '**');

            // Enable S3 streamed uploads, Lamda has a ~4.5MB limit for uploading files directly through the
            // application, streaming uploads allows us to upload files of any size and with less overhead.
            // @see https://docs.vapor.build/1.0/resources/storage.html#file-uploads
            Config::set('filesystems.s3.stream_uploads', true);

            // If the bucket is configured to be private ("Bucket and objects not public", the recommended setting,
            // usually in concert with a CloudFront distribution), then we need to set the visibility to private
            // in order to ensure that the S3 client doesn't attempt to set the ACL to public-read when uploading
            // which will result in a 403 Forbidden error.
            Config::set('filesystems.s3.visibility', 'private');

            // Enable database templates for the CMS as the default filesystem is read-only on Vapor
            Config::set('cms.databaseTemplates', true);

            // Disable capturing AJAX requests via Winter.Debugbar when running on Laravel Vapor
            // @see https://github.com/barryvdh/laravel-debugbar/issues/251
            Config::set('debugbar.capture_ajax', false);
        }
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
