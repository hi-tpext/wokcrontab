<?php

namespace wokcrontab\worker;

use think\facade\Log;
use Workerman\Worker;
use think\worker\Server;
use wokcrontab\common\Module;
use wokcrontab\common\logic;

class Index extends Server
{
    protected $socket = 'websocket://0.0.0.0:22986';

    protected $option   = [
        'name' => 'workcrontab',
        'count' => 4,
        'user' => 'www',
        'group' => 'www',
        'reloadable' => true,
        'reusePort' => true,
    ];

    /**
     * Undocumented variable
     *
     * @var logic\Cron
     */
    protected $cron;

    public function __construct()
    {
        $config = Module::getInstance()->config();

        $this->socket = 'websocket://0.0.0.0:' . ($config['port'] ?: 22986);

        $this->option['user'] = $config['user'] ?: 'www';
        $this->option['group'] = $config['group'] ?: 'www';
        $this->option['count'] = $config['count'] ?: 4;

        Worker::$daemonize = $config['daemonize'] == 1;

        Worker::$pidFile = app()->getRuntimePath() . 'worker' . $config['port'] . '.pid';
        Worker::$logFile = app()->getRuntimePath() . 'worker' . $config['port'] . '.log';
        Worker::$stdoutFile = app()->getRuntimePath() . 'worker' . $config['port'] . '.stdout.log';

        Log::init(['type' => 'File', 'path' => app()->getRuntimePath() . 'log' . DIRECTORY_SEPARATOR . 'worker']);

        parent::__construct();
    }

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
