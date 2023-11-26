<?php

namespace Shawnveltman\LaravelOpenai\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Shawnveltman\LaravelOpenai\LaravelOpenai
 */
class LaravelOpenai extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Shawnveltman\LaravelOpenai\LaravelOpenai::class;
    }
}
