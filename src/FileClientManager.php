<?php

namespace YiluTech\FileCenter;

use YiluTech\MicroApi\MicroApi;
use YiluTech\MicroApi\MicroApiRequestException;

class FileClientManager
{
    protected $buckets = array();

    protected $current;

    public function bucket($bucket = null)
    {
        if ($bucket === null) {
            $bucket = env('FILE_CENTER_BUCKET');
        }

        if (!isset($this->buckets[$bucket])) {
            $this->buckets[$bucket] = new Client($bucket);
        }

        $this->current = $this->buckets[$bucket];

        return $this->current;
    }

    public function commit()
    {
        foreach ($this->buckets as $bucket) {
            if ($bucket->isPrepared())
                $bucket->commit();
        }
    }

    public function rollback()
    {
        foreach ($this->buckets as $bucket) {
            $bucket->rollback();
        }
    }

    public function __call($name, $arguments)
    {
        if (method_exists($this, $name)) {
            return $this->{$name}(...$arguments);
        }

        if (!$this->current) {
            $this->current = $this->bucket();
        }

        return $this->current->{$name}(...$arguments);
    }
}
