# Laravel Vapor for Winter CMS

*Simplifies running Winter CMS projects on [Laravel Vapor](https://vapor.laravel.com)*

## Installation

This plugin is available for installation via [Composer](http://getcomposer.org/).

```bash
composer require winter/wn-vapor-plugin
```

## Installation Notes (WIP)

Next, you will need to install [Vapor CLI](https://github.com/laravel/vapor-cli) — this tool brings Vapor to your terminal and allows you to interact with your Vapor project to create databases, caches, or trigger new deployments:

```bash
composer global require laravel/vapor-cli
```

After you have installed the Vapor CLI, you should authenticate your Vapor account using the login command:

```bash
vapor login
```

- Create vapor.yml
    - Either manually or through `vapor init`
    - vapor init
        - What is the name of this project?
        - What region do you want it in?
        - Would you like Vapor to assign vanity domains to each of your environments? (yes/no)
        - Would you like to install the laravel/vapor-core package (yes/no) (SAY NO)
- Edit vapor.yml
    - TODO
    - Replace `npm run build` with `php artisan mix:compile --production --stop-on-error`
    - Add `- 'php artisan vapor:mirror "public" --copy --ignore "//src//" --ignore "/(.*).php/" --ignore "/.htaccess/" --ignore "//storage//"'` to `build` after asset compilation.
    - Add a bucket name to `storage: my-bucket-name` (https://docs.vapor.build/1.0/resources/storage.html#attaching-storage)
        - Vapor creates the bucket
        - Block all public access
        - Create a CloudFront distribution
            - Select the S3 bucket as the origin domain
            - Leave the origin path blank
            - Leave the name as the default or change it if you want
            - Set origin access to Legacy Access Identities
            - Create a new OAI
                - Leave the name as the default or change it
            - Leave "Enable Origin Shield" set to to No (ask Jack about https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/origin-shield.html?icmpid=docs_cf_help_panel)
            - Verify with Jack?
                - Path pattern: default
                - Compress objects automatically - yes
                - Viewer protocol policy - Redirect HTTP to HTTPS
                - Allowed HTTP methods - GET, HEAD
                - Restrict viewer access - No
                - Cache policy and origin request policy
                    - Cache Policy - CachingOptimized
                    - Origin Request Policy - Leave blank
                    - Response headers policy - SimpleCORS
                - Response headers policy - Leave blank
                - Smooth streaming - No
                - Field Level Encryption - Leave blank, uploads are handled through the streamed uploads feature and signed URLs with the bucket specific IAM user and policy that will be created later
                - Enable real time logs - no
                - Web Application Firewall - no, additional cost and we're only allowing read operations
                - Price class - Whatever is relevant, but for global audience leave on Use All Edge Locations
                - Alternate domain name (CNAME) - Leave blank probably
                - Custom SSL certificate - Leave blank
                - Supported HTTP versions - Enable HTTP/2 and HTTP/3
                - Default Root Object - Leave blank
                - Standard logging - Off
                - IPv6 - On
                - Description - Set if you want (Distribution for S3 buckets used by Winter)
            - Copy the Distribution ID to .env as AWS_CF_DISTRIBUTION
            - Go to https://us-east-1.console.aws.amazon.com/cloudfront/v3/home?region=ca-central-1#/originAccess, Identities tab and copy the ID to .env as AWS_CF_OAI
            - Copy the Distribution Domain Name to .env as AWS_CF_DOMAIN
        - Go to the bucket's Permissions tab and set the policy to the rendered version of stubs/bucket-policy.json
        - Scroll down to the CORS configuration section and set the policy to the following:
```json
[
    {
        "AllowedHeaders": [
            "*"
        ],
        "AllowedMethods": [
            "HEAD",
            "GET",
            "PUT",
            "POST"
        ],
        "AllowedOrigins": [
            "*"
        ],
        "ExposeHeaders": [
            "ETag"
        ]
    }
]
```
        - Skip enabling versioning for now, resized images could be annoying to deal with
        - Create a new policy named s3-$bucket-iam-policy with the rendered version of stubs/iam-role.json
        - Create a new IAM user named s3-$bucket-iam and attach the previously created policy to it
        - Create a new Access Key for the user and make note of it (User details page -> Security credentials tab -> Access keys section)
            - Application running on an AWS compute service
            - Give it description (s3-$bucket-iam-access-key)
            - Copy the Access Key ID to .env as AWS_S3_ACCESS_KEY_ID
            - Copy the Secret Access Key to .env as AWS_S3_SECRET_ACESS_KEY
            - Copy the region, bucket name, and CF URL to .env as AWS_S3_REGION, AWS_S3_BUCKET, and AWS_S3_URL respectively
            - Ensure filesystems.php -> s3 is setup to use the new environment variables
            - Ensure cms.php is setup `'path' => env('FILESYSTEM_DISK', 'local') === 'local' ? '/storage/app/uploads' : env('AWS_S3_URL') . '/uploads',`
            - Copy the env variables to the Vapor dashboard
        - `APP_TEMP_PATH="/tmp"` to .env





- Edit environment variables for environments through the Vapor dashboard.
- run `vapor deploy $env`
    "In PhpRedisConnector.php line 161: Connection refused" - your .env file uses redis but you don't have it running on the machine you're running the build from
    "In PhpRedisConnector.php line 81: Class "Redis" not found" - your configuration references phpredis as the driver for redis but your build machine doesn't have the phpredis extension installed
    - Your build machine will need either the PHPRedis extension installed or predis installed via composer on the project
       - `pecl install redis`
       or
       - `composer require predis/predis`
    "The command "npm ci && php artisan mix:compile --production --stop-on-error && rm -rf node_modules" failed. Exit Code: 127(Command not found)" - your build machine doesn't have node installed
    - Your build machine will need node / npm installed
        - `brew install node`
    "In DynamoDbStore.php line 451: DynamoDb does not support flushing an entire table. Please create a new table."
    - DynamoDB is poorly suited to act as a cache store for Winter CMS, use Redis instead.
    - Create a cache in https://vapor.laravel.com/app/caches or by running `vapor cache nameofcache`
    "Your application exceeds the maximum size allowed by AWS Lambda."
    - AWS Lamda's [limit the size](https://docs.vapor.build/1.0/projects/deployments.html#initiating-deployments) of the upload to [50MB (zipped) and 250MB (unzipped)](https://docs.aws.amazon.com/lambda/latest/dg/gettingstarted-limits.html).
    - Switch to using a [Docker runtime](https://docs.vapor.build/1.0/projects/environments.html#runtime) for your deployments (ups the limit to 10GB and allows full control over the available PHP extensions).
    - Several dev-dependencies in the `composer.json` file can cause the upload to exceed the limit (i.e. faker, phpunit, phpstan, etc.). Set the `--no-dev` flag on the `composer install` step in your `vapor.yml` file to prevent these from being included in the upload (alternatively maybe use the ignore option to exclude them?)


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

Laravel Vapor by default loads its own bootstrapping files which then load Laravel's bootstrapper directly. This prevents Winter's bootstrapper from running first and thus causes issues with overrides provided by Winter no longer taking effect. To fix this, copy the `httpHandler.php` and `runtime.php` file from the `/stubs` folder into your project root.



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
