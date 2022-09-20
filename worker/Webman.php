<?php

namespace wokcrontab\worker;

use think\facade\Db;
use think\facade\Log;
use Webman\Config;
use Workerman\Lib\Timer;
use wokcrontab\common\model;
use wokcrontab\common\Module;
use Workerman\Crontab\Crontab;

class Webman
{
    protected $appTasks = [];

    protected $appList = null;

    protected $taskList = null;

    protected $allTaskKeys = null;

    protected static $that;

    public function __construct()
    {
        self::$that = $this;
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
                    $task = new Crontab($li['rule'], function () use ($li, $tid, $app) {
                        $t1 = microtime(true);
                        $res = self::$that->curl($li['url'], $app);
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
    protected function curl($url, $app)
    {
        try {

            $url = trim($url);
            $time = time();
            $sign = md5($app['secret'] . $time);

            $cafile = Module::getInstance()->getRoot() . 'data' . DIRECTORY_SEPARATOR . 'cacert.pem';

            $header = [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Encoding: gzip, deflate, br',
                'Accept-Language: zh-CN,en-US;q=0.7,en;q=0.3',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'Connection: close',
                'User-Agent: Mozilla/5.0 (Linux) Gecko/20100101 Firefox/99.0 Chrome/99.0 Wokcrontab/1.0.8',
                'Referer: ' . preg_replace('/^(https?:\/\/[^\/]+).*$/', '$1', $url) . '/',
                'Host: ' . preg_replace('/^https?:\/\/([^\/]+).*$/', '$1', $url),
                'appid: ' . $app['id'],
                'time: ' . $time,
                'sign: ' . $sign,
            ];

            $options = array(
                'http' => array(
                    'method' => 'GET',
                    'ssl' => [
                        'cafile' => $cafile,
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                    'header' => implode("\r\n", $header),
                    'timeout' => 300 // 超时时间（单位:s）
                )
            );
            $context = stream_context_create($options);

            $result = file_get_contents($url, false, $context);

            if (!$result) {
                return [200, '无返回内容'];
            }

            return [200, mb_substr($result, 0, 100)];
        } catch (\Exception $e) {
            return [500, mb_substr($e->getMessage(), 0, 100)];
        }
    }

    /**
     * 心跳
     */
    protected function heartBeat()
    {
        Timer::add(60, function () {
            self::$that->runTask();
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
        //无需处理数据库
    }
}
