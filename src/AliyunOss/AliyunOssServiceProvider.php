<?php

namespace YiluTech\FileCenter\AliyunOss;

use OSS\OssClient;
use League\Flysystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use YiluTech\FileUpload\AliyunOss\Plugins\PutFile;
use YiluTech\FileUpload\AliyunOss\Plugins\SignedDownloadUrl;
use YiluTech\FileUpload\AliyunOss\Plugins\PutContent;

/**
 * Aliyun Oss ServiceProvider class.
 */
class AliyunOssServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        Storage::extend('oss', function ($app, $config) {
            $accessId = $config['access_id'];
            $accessKey = $config['access_key'];
            $endPoint = $config['endpoint'];
            $bucket = $config['bucket'];

            $prefix = null;
            if (isset($config['prefix'])) {
                $prefix = $config['prefix'];
            }

            $client = new OssClient($accessId, $accessKey, $endPoint);
            $adapter = new AliyunOssAdapter($client, $bucket, $prefix);

            $filesystem = new Filesystem($adapter);
            $filesystem->addPlugin(new PutFile());
            $filesystem->addPlugin(new PutContent());
            $filesystem->addPlugin(new SignedDownloadUrl());

            return $filesystem;
        });
    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
