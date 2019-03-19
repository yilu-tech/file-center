<?php

namespace YiluTech\FileCenter;

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

    public function getInfo()
    {
        if (!$this->server_info) {
            $this->server_info = $this->exec('info', null, 'get');
        }
        return $this->server_info;
    }

    public function getHost()
    {
        return $this->getInfo()['host'];
    }

    public function getRoot()
    {
        return $this->getInfo()['root'];
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
        $items = $this->getMoveItems($from, $to);

        if (!count($items)) return $items;

        if ($this->prepared) {
            $this->push('move', $items);
        }

        foreach ($items as &$item) {
            if (!$item['to']) {
                $item['to'] = basename($item['from']);
            } elseif (substr($item['to'], -1) === '/') {
                $item['to'] .= basename($item['from']);
            }
            $item['from'] = $this->applyPrefix($item['from']);
            $tos[] = $item['to'] = $this->applyPrefix($item['to']);
        }

        if (!$this->prepared) {
            $exec_items = array_filter($items, function ($item) {
                return $item['from'] !== $item['to'];
            });

            if (count($exec_items) && !$this->exec('move', $exec_items)) {
                return false;
            }

            $this->record('move', array_map(function ($item) {
                return ['from' => $item['to'], 'to' => $item['from']];
            }, $exec_items));
        }

        return is_array($from) ? $tos : $tos[0];
    }

    protected function getMoveItems($from, $to)
    {
        return array_map(function ($from) use ($to) {
            if (is_array($from)) {
                $from['to'] = $from['to'] ?? $to;
                return $from;
            }
            return compact('from', 'to');
        }, is_array($from) ? $from : [$from]);
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

        if (!count($paths)) return true;

        if ($this->prepared) {
            $this->push('delete', $paths);
            return true;
        }

        $paths = array_map(function ($path) {
            return $this->applyPrefix($path);
        }, $paths);

        if (!$this->exec('delete', $paths)) {
            return false;
        }

        $this->record('recover', array_map(function ($path) {
            return $this->makeRecycledFilename($path);
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

        if (!count($paths)) return true;

        if ($this->prepared) {
            $this->push('recover', $paths);
            return true;
        }

        if (!$this->exec('recover', $paths)) {
            return false;
        }

        $this->record('delete', array_map(function ($path) {
            return $this->getRecoverPath($path);
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

        foreach ($this->queue as $action => $items) {
            if (!$this->{$action}(array_collapse($items))) {
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

        foreach ($this->history as $action => $items) {
            $this->exec($action, array_collapse($items));
        }

        $this->history = [];
    }

    protected function push($action, $paths)
    {
        $this->queue[$action][] = $paths;
    }

    protected function record($action, $paths)
    {
        $this->history[$action][] = $paths;
    }

    protected function exec($action, $paths = null, $method = 'post')
    {
        $uri = $this->uriPrefix . $action . '?bucket=' . $this->bucket;
        $result = \MicroApi::{$method}($this->uriPrefix . $action)->json(compact('paths'))->run()->getJson();

        if ($result['errcode']) throw new FileCenterException($result['errmsg']);
        return $result['data'];
    }

    protected function makeRecycledFilename($path)
    {
        return $this->timeToStr() . '#' . str_replace('/', '#', $path);
    }

    protected function getRecoverPath($filename)
    {
        $parts = explode('#', $filename);
        array_shift($parts);
        return implode('/', $parts);
    }

    protected function timeToStr($timestamp = null)
    {
        return base_convert(floor($timestamp ?? time() / 86400), 10, 36);
    }

    protected function strToTime($str)
    {
        return base_convert($str, 36, 10) * 86400;
    }
}