<?php

namespace wokcrontab\worker;

use wokcrontab\common\logic;

class Webman
{
    /**
     * Undocumented variable
     *
     * @var logic\Cron
     */
    protected $cron;

    public function onWorkerStart($worker)
    {
        $this->cron = new logic\Cron;
        $this->cron->onWorkerStart($worker);
    }

    public function onWorkerReload($worker)
    {
        $this->cron->onWorkerReload($worker);
    }

    public function onClose($connection)
    {
        $this->cron->onClose($connection);
    }

    public function onConnect($connection)
    {
        $this->cron->onConnect($connection);
    }

    /**
     * 当客户端的连接上发生错误时触发
     * @param mixed $connection
     * @param string $code
     * @param string $msg
     */
    public function onError($connection, $code, $msg)
    {
        $this->cron->onError($connection, $code, $msg);
    }
}
