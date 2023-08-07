# Laravel Vapor for Winter CMS

*Simplifies running Winter CMS projects on [Laravel Vapor](https://vapor.laravel.com)*

This plugin is available for installation via [Composer](http://getcomposer.org/).

```bash
composer require winter/wn-vapor-plugin
```

## Installation

To get started running Winter CMS on Vapor you will need to follow these steps:

- Sign up for an [AWS account](https://docs.aws.amazon.com/accounts/latest/reference/welcome-first-time-user.html)
- Sign up for a [Laravel Vapor account](https://vapor.laravel.com/register)
- Install the [Vapor CLI](https://github.com/laravel/vapor-cli): `composer global require laravel/vapor-cli`
- Authenticate the Vapor CLI: `vapor login`
- Install this plugin in your Winter CMS project: `composer require winter/wn-vapor-plugin`
- Create the `vapor.yml` file: `vapor init` (say NO when asked if you would like to install the laravel/vapor-core package)
- Modify the `vapor.yml`:
    - Replace `npm run build` with `php artisan mix:compile --production --stop-on-error`
    - Add `- 'php artisan vapor:mirror "public" --copy --verbose --ignore "/modules\/.*\/.*\.less$/" --ignore "/\/src\//" --ignore "/(.*).php$/" --ignore "/(.*).md$/" --ignore "/.htaccess/" --ignore "/.DS_Store/" --ignore "/\/storage\//"'` to `build` after asset compilation.
    - Add a bucket name to `storage: my-bucket-name` for Vapor to setup the bucket for you (it is recommended that you configure the bucket to be private setup and use a CloudFront distribution to serve the public assets from the bucket. See [Configure S3 & CloudFront](#configure-s3-cloudfront) for more information.)
- Copy the `plugins/winter/vapor/stubs/httpHandler.php` and the `plugins/winter/vapor/stubs/runtime.php` file to your project's root directory.


### Configure S3 & CloudFront

Vapor will automatically create an S3 bucket for you, but it will be publicly accessible. It is recommended that you configure the bucket to be private and setup a CloudFront distribution to serve the public assets from the bucket.

To do so, follow these steps after Vapor creates the bucket for you:

#### Configure CloudFront

In order to configure CloudFront you will need to create an Origin Access Identity (OAI) and a CloudFront distribution.

- Go to the CloudFront console and create a new Distribution
- Select the S3 bucket as the origin domain
- Leave the origin path blank
- Leave the name as the default
- Set origin access to Legacy Access Identities
- Create a new OAI
    - Leave the name as the default or change it
- Leave "Enable Origin Shield" set to to No (https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/origin-shield.html?icmpid=docs_cf_help_panel)
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
- Alternate domain name (CNAME) - Leave blank for now or set to your desired domain (i.e. cdn.example.com), but you will need to make sure that you [add the domain](https://docs.vapor.build/1.0/projects/domains.html#adding-domains) and [request a cert](https://docs.vapor.build/1.0/projects/domains.html#requesting-ssl-certificates) for it in Vapor.
- Custom SSL certificate - Leave blank
- Supported HTTP versions - Enable HTTP/2 and HTTP/3
- Default Root Object - Leave blank
- Standard logging - Off
- IPv6 - On
- Description - Set to whatever you want (I recommend the alternate domain name, i.e. cdn.example.com)

#### Configure S3 Bucket

For maximum security and reliability for using S3 with Winter CMS, buckets should be configured as follows:

##### Lock down the bucket

- Skip enabling versioning for now, resized images could be annoying to deal with
- Go to the bucket's Permissions tab
    - Set "Block all public access" to "On"
    - Set the policy to the following (use your own bucketName and the oai from the CloudFront OAI you created earlier):
```json
{
    "Version": "2008-10-17",
    "Id": "PolicyForCloudFrontPrivateContent",
    "Statement": [
        {
            "Sid": "1",
            "Effect": "Allow",
            "Principal": {
                "AWS": "arn:aws:iam::cloudfront:user/CloudFront Origin Access Identity {oai}"
            },
            "Action": "s3:GetObject",
            "Resource": [
                "arn:aws:s3:::{bucketName}/media/*",
                "arn:aws:s3:::{bucketName}/uploads/public/*",
                "arn:aws:s3:::{bucketName}/public/*",
                "arn:aws:s3:::{bucketName}/resized/*"
            ]
        }
    ]
}
```
    - Set the CORS configuration to the following:
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

##### Create an IAM user for the application to access that specific bucket:
- Create a new IAM policy named s3-$bucketName-iam-policy with the following (replacing your own values for `bucketName`)
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:ListBucket",
                "s3:GetBucketLocation"
            ],
            "Resource": [
                "arn:aws:s3:::{bucketName}"
            ]
        },
        {
            "Effect": "Allow",
            "Action": [
                "s3:PutObject",
                "s3:PutObjectAcl",
                "s3:GetObject",
                "s3:GetObjectAcl",
                "s3:DeleteObject"
            ],
            "Resource": [
                "arn:aws:s3:::{bucketName}/*"
            ]
        }
    ]
}
```
- Create a new IAM user named s3-$bucketName-iam-user
- Attach the s3-$bucketName-iam-policy to the user
- Create a new Access Key for the user and make note of it (User details page -> Security credentials tab -> Access keys section)
    - Application running on an AWS compute service
    - Give it description (s3-$bucket-iam-access-key)
    - Copy the Access Key ID to .env as AWS_S3_ACCESS_KEY_ID
    - Copy the Secret Access Key to .env as AWS_S3_SECRET_ACESS_KEY
    - Copy the region, bucket name, and CF URL to .env as AWS_S3_REGION, AWS_S3_BUCKET, and AWS_S3_URL respectively
    - Ensure filesystems.php -> s3 is setup to use the new environment variables
    - Ensure cms.php is setup `'path' => env('FILESYSTEM_DISK', 'local') === 'local' ? '/storage/app/uploads' : env('AWS_S3_URL') . '/uploads',`
    - Copy the env variables to the Vapor dashboard


## Common Errors

> Winter.Redirect rules don't work consistently

Enable the "Caching of redirects (advanced)" setting to store redirects in Redis

> ERROR: "In PhpRedisConnector.php line 161: Connection refused"

You are currently trying to use Redis (for a database, cache, session, etc) but you don't have it running on the machine you're running the build from

> ERROR: "In PhpRedisConnector.php line 81: Class "Redis" not found" - your configuration references phpredis as the driver for redis but your build machine doesn't have the phpredis extension installed

Your build machine will need either the PHPRedis extension installed or predis installed via composer on the project
- `pecl install redis`
or
- `composer require predis/predis`

> ERROR: "The command `npm ci && php artisan mix:compile --production --stop-on-error && rm -rf node_modules` failed. Exit Code: 127(Command not found)" - your build machine doesn't have node installed

Your build machine will need node / npm installed
- `brew install node` (on MacOS)

> ERROR: "In DynamoDbStore.php line 451: DynamoDb does not support flushing an entire table. Please create a new table."

DynamoDB is poorly suited to act as a cache store for Winter CMS; use Redis instead. Create a cache in https://vapor.laravel.com/app/caches or by running `vapor cache nameofcache`

> ERROR: "Your application exceeds the maximum size allowed by AWS Lambda."

AWS Lamda [limits the size](https://docs.vapor.build/1.0/projects/deployments.html#initiating-deployments) of the upload to [50MB (zipped) and 250MB (unzipped)](https://docs.aws.amazon.com/lambda/latest/dg/gettingstarted-limits.html).

Several dev-dependencies in the `composer.json` file can cause the upload to exceed the limit (i.e. faker, phpunit, phpstan, etc.). Set the `--no-dev` flag on the `composer install` step in your `vapor.yml` file (or include them in the ignore patterns sent to `artisan vapor:mirror public` to prevent these from being included in the upload.

If that still isn't enough, try switching to using a [Docker runtime](https://docs.vapor.build/1.0/projects/environments.html#runtime) for your deployments (increases the limit to 10GB and allows full control over the available PHP extensions).


## Usage Notes:

### Configuration

Winter CMS includes most required environment variables in the configuration files by default, but if you have customized them or are adding Vapor support to an existing project you will need to ensure the following configuration items are set to use environment variables:

#### Automatically added by Winter.Vapor Plugin.php
- `app.tempPath`: Should be set to `env('APP_TEMP_PATH', null)`, required for anything using temporary storage on the server (i.e. file uploads).
- `app.trustedProxies`: Recommended to set to `**` so that your application can correctly identify the real IP address of the client making the request despite the fact that it is behind multiple layers of proxies.
- `cms.databaseTemplates`: Should be set to `true` to ensure that the database is used for storing CMS templates since the filesystem is read-only on Vapor.
- `filesystems.disks.s3.visibility`: Should be set to `private` if using CloudFront with locked down S3 buckets to ensure that the correct ACL
- `filesystems.disks.s3.stream_uploads`: Should be set to `true` or at least `env('AWS_S3_STREAM_UPLOADS', false)` to ensure that file uploads are streamed to S3 instead of attempting to upload them to the Vapor PHP server first, which has a low hard limit on request size which will perform worse and cause larger uploads to fail.

#### Manual verification required:
- `app.asset_url`: Should be set to `env('ASSET_URL', null)`, required for loading assets from the CDN that Vapor configures for you as asset files are not made available on the Vapor servers directly.
- `app.trustedHosts`: Recommended to add entries for every valid host to your application here.
- `filesystems.disks.s3.url`: Should be set to `env('AWS_URL')` to ensure that the correct URL is used for accessing files on S3, especially if you are using a CloudFront CDN in front of S3.

### Required Vapor Overrides

Laravel Vapor by default loads its own bootstrapping files which then load Laravel's bootstrapper directly. This prevents Winter's bootstrapper from running first and thus causes issues with overrides provided by Winter no longer taking effect. To fix this, copy the `httpHandler.php` and `runtime.php` file from the `/stubs` folder into your project root.

### Caching

Use Redis, DynamoDB doesn't currently support being flushed in Laravel which is required for cache clearing through the Winter backend.

### webmanifest & .json files:

You will need to create manual routes for `.webmanifest` & `.json` files if they are not being served from your CDN. See https://github.com/laravel/vapor-cli/commit/30f55eff6ebc22e30796e9e033536894a28b0824#commitcomment-46973546 for more details. Potentially a future version of Winter.Favicon

## License

This package is licensed under the [MIT license](https://github.com/wintercms/wn-vapor-plugin/blob/master/LICENSE.txt).

## Credits
This plugin was based on development done by Jack Wilkinson to get [SpatialMedia.io](https://spatialmedia.io) running on Laravel Vapor. It has been polished and released under the Winter namespace as a first party plugin for Winter CMS maintained by the Winter CMS team and has since been improved further and used to run [WinterCMS.com](https://wintercms.com) on Laravel Vapor.

If you would like to contribute to this plugin's development, please feel free to submit issues or pull requests to the plugin's repository here: https://github.com/wintercms/wn-vapor-plugin

If you would like to support Winter CMS, please visit [WinterCMS.com](https://wintercms.com/support)
