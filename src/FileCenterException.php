<?php

namespace YiluTech\FileCenter;

use Illuminate\Http\Request;
use Throwable;

class FileCenterException extends \Exception
{
   public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
   {
       parent::__construct($message, $code, $previous);
   }
}
