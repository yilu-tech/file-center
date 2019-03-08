<?php

namespace YiluTech\FileCenter;

use Carbon\Carbon;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
 * root bucket + ?prefix
 *
 *      .temp/{Y-m-d}/  暂存文件目录
 *      .recycled/      回收站
 *      {system-dir}/   系统目录
 *
 *      {user}/     用户目录
 */

class Server
{
    protected $root;

    protected $prefix;

    protected $disk;

    protected $paths = array();

    /**
     * @var FilesystemAdapter
     */
    protected $driver;

    public function __construct($bucket, $prefix = null)
    {
        $config = config('filesystems.buckets.' . $bucket);

        if (!$config) throw new \Exception("bucket \"$bucket\" not exists");

        if (empty($config['disk']) || empty($disk = config('filesystems.disks.' . $config['disk']))) {
            throw new \Exception("bucket \"$bucket\" disk not defined");
        }

        $this->disk = $disk;

        if (isset($config['root'])) {
            $this->root = rtrim($config['root'], '\\/') . '/';
        }

        $this->prefix($prefix);

        $this->driver = Storage::disk($config['disk']);
    }

    public function prefix($prefix)
    {
        if (!$prefix) {
            $this->prefix = '';
            return;
        }

        $this->prefix = rtrim($prefix, '\\/') . '/';
    }

    public function getRoot()
    {
        return $this->root;
    }

    public function getHost()
    {
        if (empty($this->disk['driver'])) {
            throw new FileCenterException('disk url not defined .');
        }
        return $this->disk['url'];
    }

    public function getUrl($path)
    {
        return "{$this->getHost()}/{$this->getRoot()}{$path}";
    }

    /**
     * 存储文件
     *
     * @param $file
     * @param null $dir
     * @return mixed
     */
    public function store($file, $dir = null)
    {
        return $this->storeAs($file, $this->makeFileName($file), $dir);
    }

    /**
     * 存储文件另存为
     *
     * @param $file
     * @param $name
     * @param null $dir
     * @return mixed
     */
    public function storeAs($file, $name, $dir = null)
    {
        $path = $this->driver->putFileAs($this->applyPrefix($dir), $file, $name);

        if ($path) {
            $this->paths[] = $path;
            $path = $this->removeRoot($path);
        }

        return $path;
    }

    /**
     * 存储文件到暂存目录
     *
     * @param $file
     * @return mixed
     */
    public function storeTemp($file)
    {
        return $this->storeAs($file, $this->makeFileName($file), $this->getTempDir());
    }

    /**
     * 存储文件到暂存目录另存为
     *
     * @param $file
     * @param $name
     * @return mixed
     */
    public function storeTempAs($file, $name)
    {
        return $this->storeAs($file, $name, $this->getTempDir());
    }

    /**
     * 裁剪图片并存储
     * $data array  [image] 图片文件
     *              [src_w] 原始图片宽
     *              [src_h] 原始图片高
     *              [src_x] 原始图片裁剪横向起点
     *              [src_h] 原始图片裁剪纵向起点
     *              [dst_w] 裁剪后图片宽
     *              [dst_h] 裁剪后图片高
     *
     * @param $data
     * @param null $name
     * @param null $dir
     * @return bool|string
     */
    public function storeWithCut($data, $name = null, $dir = null)
    {
        $imgSteam = file_get_contents($data['image']);
        $img = imagecreatefromstring($imgSteam);
        if (function_exists("imagecreatetruecolor")) {
            $dim = imagecreatetruecolor($data['dst_w'], $data['dst_h']);
        } else {
            $dim = imagecreate($data['dst_w'], $data['dst_h']);
        }
        $white = imagecolorallocate($dim, 255, 255, 255);
        imagefill($dim, 0, 0, $white);

        imageCopyreSampled($dim, $img, 0, 0,
            $data['src_x'], $data['src_y'],
            $data['dst_w'], $data['dst_h'],
            $data['src_w'], $data['src_h']);

        $filename = $name ?? $this->makeFileName(null, 'png');

        $path = $this->applyPrefix($dir ? $dir . '/' . $filename : $filename);

        $fp = fopen("php://memory", 'r+');

        imagepng($dim, $fp);

        imagedestroy($dim);

        rewind($fp);

        $bool = $this->driver->put($path, stream_get_contents($fp));

        fclose($fp);

        if ($bool) {
            $this->paths[] = $path;
        }
        return $bool ? $this->removeRoot($path) : false;
    }

    /**
     * 裁剪图片并存储到暂存目录
     *
     * @param $data
     * @param null $name
     * @return bool|string
     */
    public function storeTempWithCut($data, $name = null)
    {
        return $this->storeWithCut($data, $name, $this->getTempDir());
    }

    /**
     * 移动文件
     * 如果 $to = null, 则将文件移动到默认目录下
     *
     * @param $from
     * @param null $to
     * @return bool
     */
    public function move($from, $to = null)
    {
        if ($to && substr($to, -1) === '/') {
            $to .= basename($from);
        }
        $to = $this->applyPrefix($to ?? basename($from));

        $from = $this->applyPrefix($from);

        if ($from === $to) return true;

        try {
            $result = $this->driver->move($from, $to);

            if ($result) {
                $this->paths[] = ['from' => $from, 'to' => $to];
            }

            return $result;
        } catch (\Exception $exception) {
            throw new FileCenterException($exception->getMessage());
        }
    }

    /**
     * 将文件移入回收站；如果是回收站文件，则删除文件
     *
     * @param $paths
     * @return bool
     */
    public function delete($paths)
    {
        $paths = is_array($paths) ? $paths : func_get_args();

        $success = true;

        foreach ($paths as $path) {

            if (!$this->exists($path)) continue;

            try {
                if ($this->isTempFile($path)) {
                    if (!$this->destroy($path)) {
                        $success = false;
                    }
                } elseif (!$this->move($path, $this->makeRecyclePath($path))) {
                    $success = false;
                }
            } catch (\Exception $e) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * 恢复回收站文件
     *
     * @param $filename
     * @return bool
     */
    public function recovery($filename)
    {
        $form = $this->applyRoot($this->getRecyclePath($filename));

        $to = $this->applyRoot($this->getRecoverPath($filename));

        $bool = $this->driver->move($form, $to);

        if ($bool) {
            $this->paths[] = ['from' => $form, 'to' => $to];
        }

        return $bool;
    }

    /**
     * 销毁文件
     *
     * @param $path
     * @return bool
     */
    public function destroy($path)
    {
        $path = is_array($path) ? $path : func_get_args();

        try {
            return $this->driver->delete(array_map(function ($path) {
                return $this->applyPrefix($path);
            }, $path));
        } catch (\Exception $exception) {
            throw new FileCenterException($exception->getMessage());
        }
    }

    /**
     * 清除操作记录
     *
     */
    public function clearRecord()
    {
        $this->paths = array();
    }

    /**
     * 回滚操作
     *
     */
    public function rollBack()
    {
        foreach ($this->paths as $path) {
            if (is_array($path)) {
                $this->driver->move($path['to'], $path['from']);
            } else {
                $this->driver->delete($path);
            }
        }
        $this->clearRecord();
    }

    /**
     * 判断是否为暂存目录下的文件
     *
     * @param $path
     * @return false|int
     */
    public function isTempFile($path)
    {
        return preg_match('/\\.temp\\/\\d{8}\\//', $path);
    }

    /**
     * 判断文件是否存在
     *
     * @param $path
     * @return bool
     */
    public function exists($path)
    {
        return $this->driver->exists($path);
    }

    /**
     * 获取文件驱动
     *
     * @return Filesystem
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * 清空7天前暂存文件
     *
     * @return bool
     */
    public function clearTemp()
    {
        $date = Carbon::now()->subDays(7);
        $result = false;
        while ($this->driver->deleteDir('.temp/' . $date->format('Ymd'))) {
            $date = $date->subDay();
            $result = true;
        }
        return $result;
    }

    protected function makeFileName($file, $suffix = null)
    {
        return $this->timeToStr() . Str::random(16) . '.' . ($suffix ?? $file->guessExtension());
    }

    protected function makeRecyclePath($path)
    {
        return $this->getTempDir() . '/' . $this->encodePath($path);
    }

    protected function getRecyclePath($filename)
    {
        $parts = explode('#', $filename);
        $timestamp = $this->strToTime(current($parts));
        return '.temp/' . date('Ymd', $timestamp) . '/' . $filename;
    }

    protected function getRecoverPath($filename)
    {
        $parts = explode('#', $filename);
        array_shift($parts);
        return implode('/', $parts);
    }

    protected function getTempDir($timestamp = null)
    {
        return '.temp/' . date('Ymd', $timestamp ?? time());
    }

    protected function encodePath($path)
    {
        return $this->timeToStr() . '#' . str_replace('/', '#', $this->applyPrefix($path, false));
    }

    protected function timeToStr($timestamp = null)
    {
        return base_convert(floor($timestamp ?? time() / 86400), 10, 36);
    }

    protected function strToTime($str)
    {
        return base_convert($str, 36, 10) * 86400;
    }

    protected function applyPrefix($path, $with_root = true)
    {
        if ($path{0} !== '.') {
            $path = $this->prefix . ltrim($path, '\\/');
        }
        return $with_root ? $this->applyRoot($path) : $path;
    }

    protected function applyRoot($path)
    {
        return $this->root . ltrim($path, '\\/');
    }

    protected function removeRoot($path)
    {
        return substr($path, strlen($this->root));
    }
}