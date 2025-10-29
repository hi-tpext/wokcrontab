<?php

namespace wokcrontab\common\logic;

class Timer
{
    protected static $libVer = 0;

    protected static function getLibVer()
    {
        if (static::$libVer == 0) {
            if (class_exists(\Workerman\Lib\Timer::class)) {
                static::$libVer = 1;
            } else if (class_exists(\Workerman\Timer::class)) {
                static::$libVer = 2;
            }
        }

        return static::$libVer;
    }

    /**
     * Add a timer.
     *
     * @param float    $time_interval
     * @param callable $func
     * @param mixed    $args
     * @param bool     $persistent
     * @return int|false
     */
    public static function add($time_interval, $func, $args = [], $persistent = true)
    {
        $ver = static::getLibVer();

        if ($ver == 1) {
            return \Workerman\Lib\Timer::add($time_interval, $func, $args, $persistent);
        } else if ($ver == 2) {
            return \Workerman\Timer::add($time_interval, $func, $args, $persistent);
        }

        return false;
    }

    /**
     * 删除一个定时器
     * @param mixed $timerId
     * @return bool
     */
    public static function del($timerId)
    {
        $ver = static::getLibVer();

        if ($ver == 1) {
            return \Workerman\Lib\Timer::del($timerId);
        } else if ($ver == 2) {
            return \Workerman\Timer::del($timerId);
        }

        return false;
    }
}
