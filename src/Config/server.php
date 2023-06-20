<?php

return [
    's3' => [
        'AWS_S3_BUCKET' => 'default',
        'AWS_S3_PREFIX' => 'prefix/',
        'AWS_S3_PUT_REQUEST_OPTIONS' => [],

        'AWS_S3_CACHE_BUCKET' => 'default',
        'AWS_S3_CACHE_PREFIX' => 'cache/',
        'AWS_S3_CACHE_PUT_REQUEST_OPTIONS' => [],

        'SERVER_EXCLUDE_API_PATH' =>[],
        'SERVER_FORCE_LOCATION_SSL' => true
    ]
];