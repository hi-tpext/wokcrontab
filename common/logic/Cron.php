<?php

namespace wokcrontab\common\logic;

use think\facade\Db;
use think\facade\Cache;
use think\facade\Log;
use GuzzleHttp\Client;
use think\facade\Config;
use tpext\common\ExtLoader;
use wokcrontab\common\model;
use wokcrontab\common\Module;
use Workerman\Crontab\Crontab;
use GuzzleHttp\Exception\RequestException;
use Workerman\Http\Client as HttpClient;

class Cron
{
    protected $appTasks = [];

    protected $appList = null;

    protected $taskList = null;

    protected $allTaskKeys = null;

    protected static $httpClient = null;

    /**
     * this
     *
     * @var Cron 
     */
    protected static $that;

    public function onWorkerStart($worker)
    {
        self::$that = $this;

        Log::info("wokcrontab onWorkerStart");

        $this->initDb();

        $this->runTask($worker);
        $this->heartBeat($worker);
    }

    protected function runTask($worker)
    {
        $this->allTaskKeys = [];

        $this->appList = model\WokCrontabApp::select();

        $tid = '';

        $guzzleHttp = class_exists(Client::class);
        $httpClient = class_exists(HttpClient::class);

        $threadTotal = $worker->count; //总进程数量 n
        $threadId = $worker->id; //当前进程编号 0 ~ (n-1)

        $i = -1;
        foreach ($this->appList as $app) {
            if ($app['enable'] == 0) { //应用被禁用
                continue;
            }
            $this->taskList =  model\WokCrontabTask::where(['app_id' => $app['id']])->select();

            foreach ($this->taskList as $li) {
                $i += 1;
                if ($i % $threadTotal != $threadId) {
                    continue;
                }
                $tid = $li['id'];

                if (isset($this->appTasks[$tid]) && $this->appTasks[$tid]['update_time'] != $li['update_time']) { //修改过任务
                    Crontab::remove($this->appTasks[$tid]['task_id']); //移除
                    unset($this->appTasks[$tid]);
                    Log::info('[thread-' . $threadId . ']task info changed, remove:' . $tid);
                }
                if (!isset($this->appTasks[$tid])) {
                    $task = new Crontab($li['rule'], function () use ($li, $tid, $app, $guzzleHttp, $httpClient, $threadId) {
                        $t1 = microtime(true);
                        $res = null;
                        if ($httpClient) {
                            $res = self::$that->httpClientGet($li, $app, $tid, $threadId);
                        } else if ($guzzleHttp) {
                            $res = self::$that->guzzleHttpGet($li['url'], $app);
                        } else {
                            $res = self::$that->curl($li['url'], $app);
                        }
                        if (!$httpClient) {
                            $t2 = microtime(true);
                            $time1 = round($t2 - $t1, 2);

                            model\WokCrontabTask::where('id', $tid)->update(['last_run_time' => date('Y-m-d H:i:s'), 'last_run_info' => $res[0] . ':' . $res[1]]);

                            Log::info('[thread-' . $threadId . '] ' . $li['rule'] . ' @' . 'request url:' . $li['url']  . ' => ' . $time1 . ' s, ' . '[' . $res[0] . ']' . $res[1]);
                        }
                    });
                    $this->appTasks[$tid] = [
                        'task_id' => $task->getId(),
                        'update_time' => $li['update_time']
                    ];
                    Log::info('[thread-' . $threadId . '] add task:' . $tid);
                }

                $this->allTaskKeys[] = $tid;
            }
        }

        foreach ($this->appTasks as $key => $taskInfo) {
            if (!in_array($key, $this->allTaskKeys)) { //已经从数据库删除
                Crontab::remove($taskInfo['task_id']); //移除
                unset($this->appTasks[$key]);
                Log::info('[thread-' . $threadId . '] remove task:' . $key);
            }
        }
    }

    /**
     * Undocumented function
     *
     * @param string $url
     * @param array|mixed $app
     * @return array
     */
    protected function curl($url, $app)
    {
        try {

            $url = trim($url);
            $time = time();
            $sign = md5($app['secret'] . $time);

            $cafile = Module::getInstance()->getRoot() . 'data' . DIRECTORY_SEPARATOR . 'cacert.pem';

            $headers = [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'User-Agent: Mozilla/5.0 (Linux) Gecko/20100101 Firefox/99.0 Chrome/99.0 Wokcrontab/' . Module::getInstance()->getVersion(),
                'Referer: ' . preg_replace('/^(https?:\/\/[^\/]+).*$/', '$1', $url) . '/',
                'Host: ' . preg_replace('/^https?:\/\/([^\/]+).*$/', '$1', $url),
                'appid: ' . $app['id'],
                'time: ' . $time,
                'sign: ' . $sign,
            ];

            $options = [
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", $headers),
                    'timeout' => Module::getInstance()->config('timeout', 5) // 超时时间（单位:s）
                ],
                'ssl' => [
                    'cafile' => $cafile,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ];
            $context = stream_context_create($options);

            $result = file_get_contents($url, false, $context);

            if (!$result) {
                return [200, '无返回内容'];
            }

            return [200, mb_substr($result, 0, 100)];
        } catch (\Throwable $e) {
            return [500, mb_substr($e->getMessage(), 0, 100)];
        }
    }

    /**
     * Undocumented function
     *
     * @param string $url
     * @param array|mixed $app
     * @return array
     */
    protected function guzzleHttpGet($url, $app)
    {
        try {
            $url = trim($url);
            $time = time();
            $sign = md5($app['secret'] . $time);

            $headers = [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'User-Agent' => 'Mozilla/5.0 (Linux) Gecko/20100101 Firefox/99.0 Chrome/99.0 Wokcrontab/' . Module::getInstance()->getVersion(),
                'Referer' =>  preg_replace('/^(https?:\/\/[^\/]+).*$/', '$1', $url) . '/',
                'Host' => preg_replace('/^https?:\/\/([^\/]+).*$/', '$1', $url),
                'appid' => $app['id'],
                'time' => $time,
                'sign' => $sign,
            ];

            $client = new Client([
                'verify' => false, //不验证https
                'timeout' => Module::getInstance()->config('timeout', 5), // 超时时间（单位:s）
                'headers' => $headers,
                'http_errors' => false,
            ]);

            $response = $client->request('GET', $url);
            if ($response->getStatusCode() == '200') {
                $content = (string)$response->getBody();
                if (!$content) {
                    return [200, '无返回内容'];
                }
                return [200, mb_substr($content, 0, 100)];
            } else {
                return [$response->getStatusCode(), ''];
            }
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $content = (string)$response->getBody();
                return [500, mb_substr($content, 0, 100)];
            }
            return [500, mb_substr($e->getMessage(), 0, 100)];
        } catch (\Throwable $e) {
            return [500, mb_substr($e->getMessage(), 0, 100)];
        }
    }

    /**
     * Undocumented function
     *
     * @param array|mixed $li
     * @param array|mixed $app
     * @param int $tid
     * @param int $threadId
     * 
     * @return void
     */
    protected function httpClientGet($li, $app, $tid, $threadId)
    {
        try {
            $url = trim($li['url']);
            $time = time();
            $sign = md5($app['secret'] . $time);

            if (!self::$httpClient) {
                $options = [
                    'max_conn_per_addr' => 128, // 每个域名最多维持多少并发连接
                    'keepalive_timeout' => 15,  // 连接多长时间不通讯就关闭
                    'connect_timeout'   => 30,  // 连接超时时间
                    'timeout'           => Module::getInstance()->config('timeout', 5),  // 请求发出后等待响应的超时时间
                ];
                self::$httpClient = new HttpClient($options);
            }

            $headers = [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'User-Agent' => 'Mozilla/5.0 (Linux) Gecko/20100101 Firefox/99.0 Chrome/99.0 Wokcrontab/' . Module::getInstance()->getVersion(),
                'Referer' =>  preg_replace('/^(https?:\/\/[^\/]+).*$/', '$1', $url) . '/',
                'Host' => preg_replace('/^https?:\/\/([^\/]+).*$/', '$1', $url),
                'appid' => $app['id'],
                'time' => $time,
                'sign' => $sign,
            ];

            $t1 = microtime(true);
            self::$httpClient->request($url, [
                'method' => 'GET',
                'headers' => $headers,
                'success' => function ($response) use ($li, $t1, $threadId, $tid) {
                    $content = (string)$response->getBody();
                    if (!$content) {
                        $content = '无返回内容';
                    }
                    $content = mb_substr($content, 0, 100);
                    $t2 = microtime(true);
                    $time1 = round($t2 - $t1, 2);
                    model\WokCrontabTask::where('id', $tid)->update(['last_run_time' => date('Y-m-d H:i:s'), 'last_run_info' => 200 . ':' . $content]);

                    Log::info('[thread-' . $threadId . '] ' . $li['rule'] . ' @' . 'request url:' . $li['url']  . ' => ' . $time1 . ' s, ' . '[' . 200 . ']' . $content);
                },
                'error' => function ($e) {
                    throw $e;
                }
            ]);
        } catch (\Throwable $e) {
            $t2 = microtime(true);
            $time1 = round($t2 - $t1, 2);

            $content = mb_substr($e->getMessage(), 0, 100);
            model\WokCrontabTask::where('id', $tid)->update(['last_run_time' => date('Y-m-d H:i:s'), 'last_run_info' => 500 . ':' . $content]);
            Log::info('[thread-' . $threadId . '] ' . $li['rule'] . ' @' . 'request url:' . $li['url']  . ' => ' . $time1 . ' s, ' . '[' . 500 . ']' . $content);
        }
    }

    /**
     * 心跳
     */
    protected function heartBeat($worker)
    {
        if (!ExtLoader::isWebman()) {
            Timer::add(50, function () {
                Cache::get('ping');
                if (ExtLoader::isTP51()) {
                    \think\Db::query('SELECT 1'); //保存数据库连接
                } else {
                    Db::query('SELECT 1'); //保存数据库连接
                }
            });
        }

        Timer::add(60, function () use ($worker) {
            self::$that->runTask($worker);
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
        if (ExtLoader::isWebman()) {
            //无需处理数据库
        } else if (ExtLoader::isTP51()) {
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

            \think\Db::init($config);
            \think\Db::connect($config);
        } else if (ExtLoader::isTP60()) {
            $config = array_merge(Config::get('database.connections.mysql'), ['break_reconnect' => true]);

            Db::connect('mysql')->connect($config);
        }
    }
}
