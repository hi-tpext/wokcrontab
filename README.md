# workman-crontab

## workman 定时任务

### 请使用 composer 安装**think-worker**后再安装本扩展

#### tp5.1

```bash
composer require topthink/think-worker:^2.0

composer require workerman/crontab:^1.0
```

#### tp6.0

```bash
composer require topthink/think-worker:^3.0

composer require workerman/crontab:^1.0
```

#### webman

```bash
composer require workerman/crontab:^1.0
```

### 安装 GuzzleHttp

#### 可选，没有安装将使用内部实现(https可能有问题，使用http)

```bash 
composer require guzzlehttp/guzzle:^7.8
```

### 使用

#### tp 修改配置

`/config/worker_server.php`

```php
return [
    'worker_class' => ['wokcrontab\\worker\\Index'],//可以多个
];
```

#### webman 修改配置

`/config/process.php`

```php
 return [
    //....其它配置，这里省略....
    'wokcrontab'  => [
        'handler'  => 'wokcrontab\\worker\\Webman'，
        'count' => 4, // 进程数
        'user' => 'www',
        'group' => 'www',
    ],
];
//修改完重启webman
```

#### 环境要求

需要使用以下 php 方法，确保以下方法未被禁用：

```bash
pcntl_wait
pcntl_signal
pcntl_fork
pcntl_signal_dispatch
pcntl_alarm
其他（待补充）
```

#### tp 启动脚本,start.sh

```bash
COUNT1=`ps -ef |grep WorkerMan|grep -v "grep" |wc -l`;

echo $COUNT1

if [ $COUNT1 -eq 0 ];then

    cd /www/wwwroot/www.localhost.com

    php think worker:server start

fi
```

#### tp 重启脚本,restart.sh

```bash
cd /www/wwwroot/www.localhost.com

php think worker:server restart
```

修改`/www/wwwroot/www.localhost.com`为实际网站路径

创建定时任务执行 sh 脚本

#### 启动成功

在 linux 终端执行以下命令，以判断启动成功

`ps aux | grep WorkerMan`

如果输出类似以下，说明启动成功。

```bash
root      122200  0.0  0.1 217728 13776 ?        S    14:43   0:00 WorkerMan: master process  start_file=/www/wwwroot/www.localhost.com/think
www       123287  0.0  0.2 218316 22000 ?        S    14:55   0:00 WorkerMan: worker process  workcrontab websocket://0.0.0.0:22986
```

如果只有第一条[master process]没有[worker process]，则是启动失败，请到网站的`runimeme`目录里面查看`worker22986.stdout.log`日志分析原因。

#### 添加任务

##### 后台添加

可以载后台创建 app，然后添加任务

##### api 添加

使用后台创建 appid/secret_key 来添加任务，便于不同的网站定时任务集中管理。

### 规则

#### 任务类型

只支持访问网址`url`类型的任务。

#### crontab 规则

```bash
*  *  *  *  *  *
-  -  -  -  -  -
|  |  |  |  |  |
|  |  |  |  |  +------- day of week (0 - 6) (Sunday=0)
|  |  |  |  +---------- month (1 - 12)
|  |  |  +------------- day of month (1 - 31)
|  |  +---------------- hour (0 - 23)
|  +------------------- minute (0 - 59)
+---------------------- second (0 - 59)可选

精确到分(5位)：   *  *  *  *  *

精确到秒(6位)：0  *  *  *  *  *
```

一般用 5 位的就行，秒位默认为 0，即每分钟的第 0 秒执行。

#### 一些常用规则

| 说明                                         | 规则                    |
| -------------------------------------------- | ----------------------- |
| 每分钟第 30 秒行                             | `30  *  *  *  *  *`     |
| 每分钟执行                                   | `*  *  *  *  *`         |
| 每 2 分钟执行                                | `*/2  *  *  *  *`       |
| 每隔 30 分钟执行一次(整点、半点)：           | `*/30  *  *  *  *`      |
| 每小时第 30 分钟执行：                       | `30  *  *  *  *`        |
| 每 2 小时执行(整点)：                        | `0  */2  *  *  *`       |
| 每 2 小时第 30 分执行：                      | `30  */2  *  *  *`      |
| 每天 03 点 30 分执行：                       | `30  3  *  *  *`        |
| 每天 03~08 点,每隔 30 分钟执行(整点、半点)： | `*/30  3-8  *  *  *`    |
| 每天 03~08 点,每小时第 30 执行：             | `30  3-8  *  *  *`      |
| 每月 1 号的 3 点 30 执行：                   | `30  3  1  *  *`        |
| 每月 2 号的 03、06、09 点,15 分、20 分执行： | `15,20  3,6,9  2  *  *` |
| 每周星期二的 05 点 40 分执行：               | `40  5  *  *  2`        |
| 每周(星期日、星期六)的 05 点 40 分执行：     | `40  5  *  *  0,6`      |
| 每年 6 月的每个星期一 05 点 0 分执行：       | `0  5  *  6 1`          |
| 每年 3、6、9 月的 15 号 05 点 0 分执行：     | `0  5  15  3,6,9 *`     |

##### 注意

- 每隔几分钟，每隔几小时，每隔几天，都是代表数字能被整除，不一定是严格间隔。比如，每小时有 60 分钟，当 60 除以间隔数字不能除尽时就不是严格间隔。比如`*/7  *  *  *  *`每隔 7 分钟，每小时 56 分后要到下一个小时的 07 分，间隔 11 分钟。
- day of week 和 day of month 是有冲突的，严格来讲不能同时设置。比如`30  3  2  *  2`，每月 2 号的星期二，能同时满足是 2 号并且是星期二的日期很少。
- second、minute、hour，一般设置了 huor，那么 second、minute 也必须设置。设置了 huor，那么 minute 也必须设置。比如`*  3  *  *  *`(5 位规则精确到分设置了 huor，没设置 minute，秒位默认 0),每天 03 点 00 分开始每分钟执行一次，一直到 3 点 59 分，执行了 60 次。再比如`*  *  3  *  *  *`(6 位规则精确到秒，设置了 huor，没设置 minute 和 second),每天 03 点 00 分 00 秒开始每秒钟执行一次，一直到 3 点 59 分 59 秒，执行了 3600 次。如果只想在 03 点的时候执行一次，一般建议写成`0  3  *  *  *`，minute 设置为 0~59，执行一次。

#### 在线验证工具

<https://tool.lu/crontab/>

#### 请求验证(自版本 1.0.8)

请求 header 中三个参数：`appid`,`time`,`sign`

```php

<?php

namespace app\crontab\controller;

use think\Controller;

class Order extends Controller
{
    protected $app_id = 10001; //后台查看。本控制器所有任务都应在同样appid下面
    protected $secret = 'xxxxxxxxxxxxxxxxxx';//后台查看

    public function __construct()
    {

        $appid = request()->header('appid', '');
        $time = request()->header('time', '');
        $sign = request()->header('sign', '');

        $res = $this->validateApp($appid, $sign, $time);

        if ($res['code'] != 1) {//验证失败
            exit $res['msg'];
        }
    }

    private function validateApp($appid, $sign, $time)
    {
        if (empty($appid) || empty($sign) || empty($time)) {
            return ['code' => 0, 'msg' => '参数错误'];
        }

        if ($appid != $this->app_id) {
            return ['code' => 0, 'msg' => '任务不在本appid下-' . $appid];
        }

        if ((abs(time() - $time)) > 10) {
            return ['code' => 0, 'msg' => 'sign超时请检查设备时间'];
        }

        if ($sign != md5($this->secret . $time)) {
            return ['code' => 0, 'msg' => 'sign验证失败'];
        }

        return ['code' => 1, 'msg' => '成功'];
    }

    public function taskA(){
        //
    }
}

```
