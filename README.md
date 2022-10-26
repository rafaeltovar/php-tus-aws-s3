# PHP TUS protocol server for Amazon Web Service S3

Simple, light, minimum TUS server connected with AWS S3. Based on [ankitpokhrel/tus-php](https://github.com/ankitpokhrel/tus-php).


## Versions
If you are using Symfony, check the table below.

| Symfony Version | php-tus-aws-s3 version |
| --------------- | ---------------------- |
| ^4.3            | ~1.0                   |
| ^5.0  or ^6.0   | ~1.1                   |

## Installation

### Composer

```
composer require rafaeltovar/php-tus-aws-s3:~1.x predis/predis
```

## Features

- [x] Implements TUS protocol server for upload files
- [x] AWS S3 multiparts uploads
- [x] Uploads directly to AWS S3
- [x] Use Redis like data cache with Predis
- [x] Flysystem compatible

## Documentation

### Understanding TusPhpS3\Server class constructor

```php
use TusPhp\Tus\Server as TusServer;

class Server
extends TusServer
{
    //...
    public function __construct(
        TusPhp\Cache\AbstractCache $cache,
        League\Flysystem\AwsS3v3\AwsS3Adapter $storage,
        TusPhpS3\Http\Request $request,
        $excludeAttrApiPath = [],
        $forceLocationSSL = true)
        {
            //...
        }
}
```

| Property   | Type    | Details    |
|------------|---------|------------|
| `$cache`   | `TusPhp\Cache\AbstractCache`  | We are using `TusPhpS3\Cache\PredisCache` for `Predis` client.    |
| `$storage` | `League\Flysystem\AwsS3v3\AwsS3Adapter` | This adapter contains the AWS S3 Client.                |
| `$request` | `TusPhps3\Http\Request`       | This object contain a `Symfony\Component\HttpFoundation\Request`. |
| `$excludeAttrApiPath` | `array`  | Exclude some parts from Api path for create a real Api Base Path for TUS Server. For example, if my Api base path is `https://example.com/uploads` but my upload PATCH is `http://example.com/uploads/{id}` We need exclude `['id']`. |
| `$forceLocationSSL`   | `boolean` | Force `location` header property to `https`. |


### TUS Routes

```php
/**
 * Create new upload
 * or get server configuration
 **/
$routes->add('uploads', '/api/uploads')
        ->controller([UploadController::class, 'upload'])
        ->methods([POST, OPTIONS])

/**
 * Upload files
 * or delete uploads
 **/
$routes->add('uploads', '/api/uploads/{id}')
        ->controller([UploadController::class, 'upload'])
        ->methods([PATCH, DELETE])
```

### Running TUS Server

```php

use TusPhpS3;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3v3\AwsS3Adapter;

use Symfony\Component\HttpFoundation\Request as HttpRequest;

class UploadController
{
    public function upload()
    {

        // redis connection
        $predis = new Predis\Client('tcp://10.0.0.1:6379');


        // AWS S3 Client
        $S3client = new S3Client([
            'credentials' => [
                'key'    => 'your-key',
                'secret' => 'your-secret',
            ],
            'region' => 'your-region',
            'version' => 'latest|version',
        ]);

        $server = new TusPhpS3\Server(
            new TusPhpS3\Cache\PredisCache($predis),
            new AwsS3Adapter($S3client, 'your-bucket-name', 'optional/path/prefix'),
            new TusPhpS3\Http\Request(HttpRequest::createFromGlobals()),
            ['id'],
            true
        );

        return $server->serve(); // return an TusPhpS3\Http\Response
    }
}
