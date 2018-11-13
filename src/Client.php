<?php

namespace YiluTech\FileCenter;

use YiluTech\MicroApi\MicroApi;

class Client
{
    protected $history = array();

    protected $prepared = false;

    protected $queue = array();

    protected $bucket;

    protected $prefix;

    protected $uriPrefix;

    public function __construct($bucket = null)
    {
        $this->bucket = $bucket ?? env('FILE_BUCKET');

        $uri_prefix = env('FILE_URI_FREFIX');

        $this->uriPrefix = $uri_prefix ? rtrim($uri_prefix, '\\/') . '/' : '';
    }

    public function bucket($bucket = null)
    {
        return new self($bucket);
    }

    public function setBucket($bucket)
    {
        $this->bucket = $bucket;

        return $this;
    }

    public function prefix($prefix)
    {
        $this->prefix = (string)$prefix ? rtrim($prefix, '\\/') . '/' : '';

        return $this;
    }

    public function getPrefix()
    {
        return $this->prefix;
    }

    public function applyPrefix($path)
    {
        if ($path{0} !== '.') {
            $path = $this->prefix . ltrim($path, '\\/');
        }

        return $path;
    }

    /**
     * 移动文件
     * 如果 $to = null, 则将文件移动到默认目录下
     * 批量移动
     *      $from array < (string)path | array([ (string)from , (string)to ]) >
     *
     * @param $from string|array
     * @param null $to
     * @return array|bool
     */
    public function move($from, $to = null)
    {
        $items = is_array($from) ? $from :
            [$to === null ? $from : ['from' => $from, 'to' => $to]];

        if ($this->prepared) {
            $this->queue['move'][] = $items;
            return true;
        }

        $items = array_map(function ($item) {
            return [
                'from' => $this->applyPrefix(is_array($item) ? $item['from'] : $item),
                'to' => $this->applyPrefix(is_array($item) ? $item['to'] : basename($item))
            ];
        }, $items);

        if (!$this->exec('move', $items)) {
            return false;
        }

        $this->record('move', array_map(function ($item) {
            return ['from' => $item['to'], 'to' => $item['from']];
        }, $items));

        return true;
    }

    /**
     * 删除文件
     *
     * @param $path string|array
     * @return bool
     */
    public function delete($path)
    {
        $paths = is_array($path) ? $path : func_get_args();

        if ($this->prepared) {
            $this->queue['delete'][] = $paths;
            return true;
        }

        $paths = array_map(function ($path) {
            return $this->applyPrefix($path);
        }, $paths);

        if (!$this->exec('delete', $paths)) {
            return false;
        }

        $this->record('recover', array_map(function ($path) {
            return $this->encodeRecyclePath($path);
        }, $paths));

        return true;
    }

    /**
     * 恢复删除文件
     *
     * @param $path string|array
     * @return bool
     */
    public function recover($path)
    {
        $paths = is_array($path) ? $path : func_get_args();

        if ($this->prepared) {
            $this->queue['recover'][] = $paths;
            return true;
        }

        if (!$this->exec('recover', $paths)) {
            return false;
        }

        $this->record('delete', array_map(function ($path) {
            return $this->decodeRecyclePath($path);
        }, $paths));

        return true;
    }

    /**
     * 开启合并提交操作, 接下来的操作不会立即提交
     *
     */
    public function prepare()
    {
        $this->history = [];

        $this->prepared = true;

        return $this;
    }

    /**
     * 提交操作
     *
     * @return bool
     */
    public function commit()
    {
        $this->prepared = false;

        $bool = true;

        foreach ($this->queue as $fun => $paths) {
            if (!$this->{$fun}(array_collapse($paths))) {
                $bool = false;
                $this->rollback();
                break;
            }
        }

        if ($bool) {
            $this->queue = [];
        } else {
            $this->prepared = true;
        }

        return $bool;
    }

    /**
     * 回滚操作记录
     *
     */
    public function rollback()
    {
        if ($this->prepared) {
            $this->prepared = false;
        }

        foreach ($this->history as $fun => $paths) {
            $this->exec($fun, $paths);
        }

        $this->history = [];
    }

    protected function record($action, $paths)
    {
        $this->history[$action] = isset($this->history[$action]) ? array_merge($this->history[$action], $paths) : $paths;
    }

    protected function exec($action, $paths)
    {
        $api = new MicroApi();

        $result = $api->post($this->uriPrefix . $action)->json([
            'paths' => $paths,
            'bucket' => $this->bucket
        ])->run()->getContents();

        return $result === 'success';
    }

    protected function encodeRecyclePath($path)
    {
        $path = str_replace('/', '#', $path);

        if ($path{0} !== '#') {
            $path = '#' . $path;
        }

        return $path;
    }

    protected function decodeRecyclePath($name)
    {
        return substr(str_replace('#', '/', $name), 1);
    }
}