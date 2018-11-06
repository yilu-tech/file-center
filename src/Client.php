<?php

namespace YiluTech\FileUpload;

use YiluTech\MicroApi\MicroApi;

class Client
{
    use PathPrefixSupportTrait;

    protected $history = array();

    protected $prepared = false;

    protected $queue = array();

    protected $bucket;

    /**
     * Client constructor.
     *
     * @param $instance
     * @param null $bucket
     */

    public function __construct($instance, $bucket = null)
    {
        $this->setPathPrefix($instance);

        $this->bucket = $bucket ?? env('FILE_BUCKET');
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

        $result = array_map(function ($item) {
            return $this->applyPathPrefix(is_array($item) ? $item['to'] : basename($item));
        }, $items);

        if ($this->prepared) {

            $this->queue['move'][] = $items;

        } else {

            if ($this->exec('move', $items)) {

                $this->record('move', array_map(function ($item) {
                    return is_array($item) ? ['from' => $item['to'], 'to' => $item['from']] :
                        ['from' => $this->applyPathPrefix(basename($item)), 'to' => $item];
                }, $items));

            } else {
                $result = false;
            }
        }

        return $result;
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

        $result = true;
        if ($this->prepared) {

            $this->queue['delete'][] = $paths;

        } else {

            if ($this->exec('delete', $paths)) {
                $this->record('recover', array_map(function ($path) {
                    return $this->encodeRecyclePath($path);
                }, $paths));
            } else {
                $result = false;
            }
        }

        return $result;
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

        $result = true;
        if ($this->prepared) {

            $this->queue['recover'][] = $paths;

        } else {

            if ($this->exec('recover', $paths)) {
                $this->record('delete', array_map(function ($path) {
                    return $this->decodeRecyclePath($path);
                }, $paths));
            } else {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * 开启合并提交操作, 接下来的操作不会立即提交
     *
     */
    public function prepare()
    {
        $this->history = [];

        $this->prepared = true;
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
            if (!$this->{$fun}($paths)) {
                break;
            }
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

        $result = $api->post($action)->json([
            'paths' => $paths,
            'bucket' => $this->bucket,
            'instance' => rtrim($this->pathPrefix, '/')
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