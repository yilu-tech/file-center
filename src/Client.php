<?php

namespace YiluTech\FileCenter;

use YiluTech\MicroApi\Exceptions\MicroApiRequestException;

class Client
{
    protected $history = array();

    protected $prepared = false;

    protected $queue = array();

    protected $bucket;

    protected $prefix;

    protected $uriPrefix;

    protected $server_info;

    public function __construct($bucket = null)
    {
        $this->bucket = $bucket ?? env('FILE_CENTER_BUCKET');

        if (!$bucket) {
            throw new FileCenterException('bucket not dedined.');
        }

        $uri_prefix = env('FILE_CENTER_URI_PREFIX');

        $this->uriPrefix = $uri_prefix ? rtrim($uri_prefix, '\\/') . '/' : '';

        $this->server_info = $this->exec('info', null, 'get');
    }

    public static function make($bucket = null)
    {
        return new self($bucket);
    }

    public function bucket($bucket)
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

    public function getBucket()
    {
        return $this->bucket;
    }

    public function getHost()
    {
        return $this->server_info['host'];
    }

    public function getRoot()
    {
        return $this->server_info['root'];
    }

    public function getUrl($path)
    {
        return "{$this->getHost()}/{$this->getRoot()}{$path}";
    }

    public function applyPrefix($path)
    {
        if ($path{0} !== '.' && $path{0} !== '/') {
            $path = $this->prefix . ltrim($path, '\\/');
        }

        return $path;
    }

    /**
     * 移动文件
     * 如果 $to = null, 则将文件移动到默认目录下
     *
     * @param $from string|array
     * @param null $to
     * @return mixed
     */
    public function move($from, $to = null)
    {
        $items = $this->getMoveToFilePaths(is_array($from) ? $from : [$from], $to);

        if(!count($items)) return $items;

        $tos = array_map(function ($item) {
            return $item['to'];
        }, $items);

        if ($this->prepared) {
            $this->queue['move'][] = [compact('from', 'to')];
            return is_array($from) ? $tos : $tos[0];
        }

        $exec_items = array_filter($items, function ($item) {
            return $item['from'] !== $item['to'];
        });

        if (count($exec_items) && !$this->exec('move', $exec_items)) {
            return false;
        }

        $this->record('move', array_map(function ($item) {
            return ['from' => $item['to'], 'to' => $item['from']];
        }, $exec_items));

        return is_array($from) ? $tos : $tos[0];
    }

    protected function getMoveToFilePaths(array $froms, $to)
    {
        return array_map(function ($from) use ($to) {
            if (is_array($from)) {
                $to = $from['to'] ?? $to;
                $from = $from['from'];
            }
            if (!$to) {
                $to = basename($from);
            } elseif (substr($to, -1) === '/') {
                $to .= basename($from);
            }

            $from = $this->applyPrefix($from);
            $to = $this->applyPrefix($to);
            return compact('from', 'to');
        }, $froms);
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

    public function isPrepared()
    {
        return $this->prepared;
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

    protected function exec($action, $paths = null, $method = 'post')
    {
        $result = \MicroApi::{$method}($this->uriPrefix . $action)->json([
            'paths' => $paths,
            'bucket' => $this->bucket
        ])->run()->getJson();

        if ($result['status'] === -1) {
            throw new FileCenterException($result['message']);
        }
        return $result['message'];
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