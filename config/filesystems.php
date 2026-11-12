<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        // Firmas del consentimiento informado: bucket privado de un almacenamiento compatible con S3.
        // Nunca se sirven por URL pública; las entrega una ruta autenticada del sistema.
        // SIGABU_FIRMAS_DRIVER solo se cambia en el entorno E2E; en el servidor siempre es s3.
        'firmas' => [
            'driver' => env('SIGABU_FIRMAS_DRIVER', 's3'),
            'root' => env('SIGABU_FIRMAS_DRIVER', 's3') === 'local' ? storage_path('app/private/firmas-e2e') : '',
            'key' => env('SIGABU_S3_KEY'),
            'secret' => env('SIGABU_S3_SECRET'),
            'region' => env('SIGABU_S3_REGION', 'us-east-1'),
            'bucket' => env('SIGABU_S3_BUCKET', 'consentimientos-firmas'),
            'endpoint' => env('SIGABU_S3_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
            'throw' => true,
            'report' => true,
        ],

        // Archivos del repositorio de Bienestar. Con las claves S3 van al bucket
        // privado; sin ellas, a una carpeta privada del servidor (no accesible desde la web).
        'repositorio' => env('SIGABU_S3_KEY') && env('SIGABU_S3_SECRET') && env('SIGABU_S3_ENDPOINT')
            ? [
                'driver' => 's3',
                'key' => env('SIGABU_S3_KEY'),
                'secret' => env('SIGABU_S3_SECRET'),
                'region' => env('SIGABU_S3_REGION', 'us-east-1'),
                'bucket' => env('SIGABU_S3_BUCKET_REPOSITORIO', 'repositorio-documentos'),
                'endpoint' => env('SIGABU_S3_ENDPOINT'),
                'use_path_style_endpoint' => true,
                'visibility' => 'private',
                'throw' => true,
                'report' => true,
            ]
            : [
                'driver' => 'local',
                'root' => storage_path('app/private/repositorio'),
                'visibility' => 'private',
                'throw' => true,
                'report' => true,
            ],

        // Evidencias del módulo VBG (firmas de consentimiento por cita y audios de las sesiones):
        // datos sensibles, en su propio bucket privado. Sin claves S3, o en el entorno E2E, van a
        // una carpeta privada del servidor.
        'vbg' => env('SIGABU_S3_KEY') && env('SIGABU_S3_SECRET') && env('SIGABU_S3_ENDPOINT') && env('SIGABU_FIRMAS_DRIVER', 's3') === 's3'
            ? [
                'driver' => 's3',
                'key' => env('SIGABU_S3_KEY'),
                'secret' => env('SIGABU_S3_SECRET'),
                'region' => env('SIGABU_S3_REGION', 'us-east-1'),
                'bucket' => env('SIGABU_S3_BUCKET_VBG', 'vbg-evidencias'),
                'endpoint' => env('SIGABU_S3_ENDPOINT'),
                'use_path_style_endpoint' => true,
                'visibility' => 'private',
                'throw' => true,
                'report' => true,
            ]
            : [
                'driver' => 'local',
                'root' => storage_path('app/private/vbg'),
                'visibility' => 'private',
                'throw' => true,
                'report' => true,
            ],

        // Soportes y evidencias de los formatos de Inclusión: datos sensibles, en su propio bucket
        // privado. Sin claves S3, o en el entorno E2E, van a una carpeta privada del servidor.
        'inclusion' => env('SIGABU_S3_KEY') && env('SIGABU_S3_SECRET') && env('SIGABU_S3_ENDPOINT') && env('SIGABU_FIRMAS_DRIVER', 's3') === 's3'
            ? [
                'driver' => 's3',
                'key' => env('SIGABU_S3_KEY'),
                'secret' => env('SIGABU_S3_SECRET'),
                'region' => env('SIGABU_S3_REGION', 'us-east-1'),
                'bucket' => env('SIGABU_S3_BUCKET_INCLUSION', 'inclusion-evidencias'),
                'endpoint' => env('SIGABU_S3_ENDPOINT'),
                'use_path_style_endpoint' => true,
                'visibility' => 'private',
                'throw' => true,
                'report' => true,
            ]
            : [
                'driver' => 'local',
                'root' => storage_path('app/private/inclusion'),
                'visibility' => 'private',
                'throw' => true,
                'report' => true,
            ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
