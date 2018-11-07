<?php

namespace YiluTech\FileCenter\AliyunOss\Plugins;

use League\Flysystem\Config;
use League\Flysystem\Plugin\AbstractPlugin;

/**
 * PutFile class
 * 上传本地文件.
 *
 */
class PutContent extends AbstractPlugin
{
    /**
     * Get the method name.
     *
     * @return string
     */
    public function getMethod()
    {
        return 'putContent';
    }

    /**
     * Handle.
     *
     * @param string $path
     * @param string $content
     * @param array  $config
     * @return bool
     */
    public function handle($path, $content, array $config = [])
    {
        if (! method_exists($this->filesystem, 'getAdapter')) {
            return false;
        }

        if (! method_exists($this->filesystem->getAdapter(), 'putContent')) {
            return false;
        }

        $config = new Config($config);
        if (method_exists($this->filesystem, 'getConfig')) {
            $config->setFallback($this->filesystem->getConfig());
        }

        return (bool) $this->filesystem->getAdapter()->putContent($path, $content, $config);
    }
}
