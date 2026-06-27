<?php

namespace Illuminate\Support;

if (! function_exists('Illuminate\Support\defer')) {
    function defer(?callable $callback = null, ?string $name = null, bool $always = false)
    {
        if ($callback === null) {
            return [];
        }

        return $callback();
    }
}

if (! function_exists('Illuminate\Support\php_binary')) {
    function php_binary()
    {
        return PHP_BINARY;
    }
}

if (! function_exists('Illuminate\Support\artisan_binary')) {
    function artisan_binary()
    {
        return defined('ARTISAN_BINARY') ? ARTISAN_BINARY : 'artisan';
    }
}
