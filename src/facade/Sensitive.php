<?php
declare (strict_types = 1);

namespace isszz\sensitive\facade;

class Sensitive
{
    protected static ?\isszz\sensitive\Sensitive $_instance = null;

    public static function instance()
    {
        if (!static::$_instance) {
            static::$_instance = new \isszz\sensitive\Sensitive;
        }

        return static::$_instance;
    }

    public static function __callStatic($name, $arguments)
    {
        return static::instance()->{$name}(... $arguments);
    }
}
