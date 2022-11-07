# Laravel Vapor for Winter CMS

*Simplifies running Winter CMS projects on [Laravel Vapor](https://vapor.laravel.com)*

## Installation

This plugin is available for installation via [Composer](http://getcomposer.org/).

```bash
composer require winter/wn-vapor-plugin
```

## Usage Notes:

### Configuration

Winter CMS includes most required environment variables in the configuration files by default, but if you have customized them or are adding Vapor support to an existing project you will need to ensure the following configuration items are set to use environment variables:

- `app.tempPath`: Should be set to `env('APP_TEMP_PATH', null)`, required for anything using temporary storage on the server (i.e. file uploads).
- `app.asset_url`: Should be set to `env('ASSET_URL', null)`, required for loading assets from the CDN that Vapor configures for you as asset files are not made available on the Vapor servers directly.
- `app.trustedHosts`: Recommended to add entries for every valid host to your application here.
- `app.trustedProxies`: Recommended to set to `**` so that your application can correctly identify the real IP address of the client making the request despite the fact that it is behind multiple layers of proxies.
- `cms.linkPolicy`: Should be set to `force` to ensure that the URL helper handles generating URLs.
- `cms.databaseTemplates`: Should be set to `true` to ensure that the database is used for storing CMS templates since the filesystem is read-only on Vapor.
- `filesystems.s3.stream_uploads`: Should be set to `true` or at least `env('AWS_S3_STREAM_UPLOADS', false)` to ensure that file uploads are streamed to S3 instead of attempting to upload them to the Vapor PHP server first, which has a low hard limit on request size which will perform worse and cause larger uploads to fail.
- `filesystems.s3.url`: Should be set to `env('AWS_URL')` to ensure that the correct URL is used for accessing files on S3, especially if you are using a CloudFront CDN in front of S3.



### Required Vapor Overrides

Laravel Vapor by default loads its own bootstrapping files which then load Laravel's bootstrapper directly. This prevents Winter's bootstrapper from running first and thus causes issues with overrides provided by Winter no longer taking effect. To fix this, copy the `httpHandler.php` and `runtimes.php` file from the `/stubs` folder into your project root.



### Returning Binary Responses (File Downloads)

See https://docs.vapor.build/1.0/projects/development.html#binary-responses. Basically `$response->headers->set('X-Vapor-Base64-Encode', 'True');` needs to be set on the response object for any binary responses (i.e. file downloads).



### Caching

Use Redis, DynamoDB doesn't currently support being flushed in Laravel which is required for cache clearing through the Winter backend.



### webmanifest & .json files:

You will need to create manual routes for `.webmanifest` & `.json` files if they are not being served from your CDN. See https://github.com/laravel/vapor-cli/commit/30f55eff6ebc22e30796e9e033536894a28b0824#commitcomment-46973546 for more details.



## License

This package is licensed under the [MIT license](https://github.com/wintercms/wn-vapor-plugin/blob/master/LICENSE.txt).

## Credits
This plugin is based on development done by Jack Wilkinson for Spatial Media in order to get Winter CMS running on Laravel Vapor. It has been polished and released under the Winter namespace as a first party plugin for Winter CMS maintained by the Winter CMS team.

If you would like to contribute to this plugin's development, please feel free to submit issues or pull requests to the plugin's repository here: https://github.com/wintercms/wn-vapor-plugin

If you would like to support Winter CMS, please visit [WinterCMS.com](https://wintercms.com/support)
