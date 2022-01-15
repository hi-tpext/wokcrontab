<?php

namespace wokcrontab\worker;

use think\Db;
use think\facade\Log;
use Workerman\Worker;
use think\facade\Config;
use think\worker\Server;
use Workerman\Lib\Timer;
use tpext\common\ExtLoader;
use wokcrontab\common\model;
use wokcrontab\common\Module;
use Workerman\Crontab\Crontab;

class Index extends Server
{
    protected $socket = 'websocket://0.0.0.0:22986';

    protected $option   = [
        'name' => 'workcrontab',
        'count' => 1,
        'user' => 'www',
        'group' => 'www',
        'reloadable' => true,
        'reusePort' => true,
    ];

    protected $appTasks = [];

    protected $appList = null;

    protected $taskList = null;

    protected $allTaskKeys = null;

    protected static $that;

    public function __construct()
    {
        self::$that = $this;

        $config = Module::getInstance()->config();

        $this->socket = 'websocket://0.0.0.0:' . ($config['port'] ?: 22986);

        $this->option['user'] = $config['user'] ?: 'www';
        $this->option['group'] = $config['group'] ?: 'www';

        Worker::$daemonize = $config['daemonize'] == 1;

        Worker::$pidFile = app()->getRuntimePath() . 'worker' . $config['port'] . '.pid';
        Worker::$logFile = app()->getRuntimePath() . 'worker' . $config['port'] . '.log';
        Worker::$stdoutFile = app()->getRuntimePath() . 'worker' . $config['port'] . '.stdout.log';

        Log::init(['type' => 'File', 'path' => app()->getRuntimePath() . 'log' . DIRECTORY_SEPARATOR . 'worker']);

        parent::__construct();
    }

    public function onWorkerStart($worker)
    {
        Log::info("wokcrontab onWorkerStart");

        $this->initDb();

        $this->runTask();
        $this->heartBeat($worker);
    }

    protected function runTask()
    {
        $this->allTaskKeys = [];

        $this->appList = model\WokCrontabApp::select();

        $tid = '';

        foreach ($this->appList as $app) {

            $this->taskList =  model\WokCrontabTask::where(['app_id' => $app['id']])->select();

            foreach ($this->taskList as $li) {
                $tid = $li['id'];
                if (isset($this->appTasks[$tid]) && $this->appTasks[$tid]['update_time'] != $li['update_time']) { //修改过任务
                    Crontab::remove($this->appTasks[$tid]['task_id']); //移除
                    unset($this->appTasks[$tid]);
                    Log::info("task info changed, remove:" . $tid);
                }
                if (!isset($this->appTasks[$tid])) {
                    $task = new Crontab($li['rule'], function () use ($li, $tid) {
                        $t1 = microtime(true);
                        $res = Index::$that->curl($li['url'], '');
                        $t2 = microtime(true);
                        $time1 = round($t2 - $t1, 2);

                        model\WokCrontabTask::where('id', $tid)->update(['last_run_time' => date('Y-m-d H:i:s'), 'last_run_info' => $res[0] . ':' . $res[1]]);

                        Log::info($li['rule'] . ' @ ' . 'request url :' . $li['url']  . ' => ' . $time1 . ' s, ' . ' [' . $res[0] . ']' . $res[1]);
                    });
                    $this->appTasks[$tid] = [
                        'task_id' => $task->getId(),
                        'update_time' => $li['update_time']
                    ];
                    Log::info("add task:" . $tid);
                }

                $this->allTaskKeys[] = $tid;
            }
        }

        foreach ($this->appTasks as $key => $taskInfo) {
            if (!in_array($key, $this->allTaskKeys)) { //已经从数据库删除
                Crontab::remove($taskInfo['task_id']); //移除
                unset($this->appTasks[$key]);
                Log::info("remove task:" . $key);
            }
        }
    }

    /**
     * Undocumented function
     *
     * @param string $url
     * @return array
     */
    protected function curl($url)
    {
        try {

            $cafile = Module::getInstance()->getRoot() . 'data' . DIRECTORY_SEPARATOR . 'cacert.pem';
            echo is_file($cafile)?'yes':'no';

            $options = array(
                'http' => array(
                    'method' => 'GET',
                    'ssl' => [
                        'cafile' => $cafile,
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                    'timeout' => 300 // 超时时间（单位:s）
                )
            );
            $context = stream_context_create($options);

            $result = file_get_contents(trim($url), false, $context, 0, 30);

            if (!$result) {
                return [200, '无返回内容'];
            }

            return [200, $result];
        } catch (\Exception $e) {
            return [500, $e->getMessage()];
        }
    }

    /**
     * 心跳
     */
    protected function heartBeat()
    {
        Timer::add(5, function () {
            model\WokCrontabTask::where('id', 1)->find(); //保存数据库连接
        });

        Timer::add(60, function () {
            Index::$that->runTask();
        });
    }

    public function onWorkerReload($worker)
    {
        Log::info("wokcrontab onWorkerReload");
        $this->initDb();
    }

    public function onClose($connection)
    {
        Log::info("wokcrontab onClose");
    }

    public function onConnect($connection)
    {
        Log::info("wokcrontab onConnect not allowed");

        $connection->close();
    }

    /**
     * 当客户端的连接上发生错误时触发
     * @param mixed $connection
     * @param string $code
     * @param string $msg
     */
    public function onError($connection, $code, $msg)
    {
        Log::error("wokcrontab error $code $msg");
    }

    protected function initDb()
    {
        if (ExtLoader::isTP51()) {
            $breakMatchStr = [
                'server has gone away',
                'no connection to the server',
                'Lost connection',
                'is dead or not enabled',
                'Error while sending',
                'decryption failed or bad record mac',
                'server closed the connection unexpectedly',
                'SSL connection has been closed unexpectedly',
                'Error writing data to the connection',
                'Resource deadlock avoided',
                'failed with errno',
                'child connection forced to terminate due to client_idle_limit',
                'query_wait_timeout',
                'reset by peer',
                'Physical connection is not usable',
                'TCP Provider: Error code 0x68',
                'ORA-03114',
                'Packets out of order. Expected',
                'Adaptive Server connection failed',
                'Communication link failure',
                'connection is no longer usable',
                'Login timeout expired',
                'SQLSTATE[HY000] [2002] Connection refused',
                'running with the --read-only option so it cannot execute this statement',
                'The connection is broken and recovery is not possible. The connection is marked by the client driver as unrecoverable. No attempt was made to restore the connection.',
                'SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo failed: Try again',
                'SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo failed: Name or service not known',
                'SQLSTATE[HY000]: General error: 7 SSL SYSCALL error: EOF detected',
                'SQLSTATE[HY000] [2002] Connection timed out',
                'SSL: Connection timed out',
                'SQLSTATE[HY000]: General error: 1105 The last transaction was aborted due to Seamless Scaling. Please retry.',
                'bytes failed with errno=32 Broken pipe'
            ];

            $config = array_merge(Config::pull('database'), ['break_reconnect' => true, 'break_match_str' => $breakMatchStr]);

            Db::init($config);
            Db::connect($config);
        } else if (ExtLoader::isTP60()) {
            $config = array_merge(Config::get('database.connections.mysql'), ['break_reconnect' => true]);

            Db::setConfig($config);
            Db::connect('mysql')->connect($config);
        }
    }
}
