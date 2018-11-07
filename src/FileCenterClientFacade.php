<?php

namespace YiluTech\FileCenter;

use Illuminate\Support\Facades\Facade;

class FileCenterClientFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'FileCenterClient';
    }
}