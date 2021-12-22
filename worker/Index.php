<?php

namespace wokcrontab\worker;

use think\facade\Log;
use Workerman\Worker;
use think\worker\Server;
use wokcrontab\common\model;
use wokcrontab\common\Module;
use Workerman\Crontab\Crontab;
use Workerman\Lib\Timer;

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

        Log::init(['type' => 'File', 'path' => app()->getRuntimePath() . 'log' . DIRECTORY_SEPARATOR . 'task']);

        parent::__construct();
    }

    public function onWorkerStart($worker)
    {
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
        try
        {
            $options = array(
                'http' => array(
                    'method' => 'GET',
                    'timeout' => 300 // 超时时间（单位:s）
                )
            );
            $context = stream_context_create($options);

            $result = file_get_contents(trim($url), false, $context, 0, 30);
    
            if (!$result) {
                return [200, '无返回内容'];
            }
    
            return [200, $result];
        }
        catch(\Exception $e)
        {
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

    public function onConnect($connection)
    {
        Log::info("not allowed");

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
        Log::error("error $code $msg");
    }
}
