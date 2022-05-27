# workman-crontab

## workman定时任务

### 请使用composer安装**think-worker**后再安装本扩展

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

### 使用

#### 修改配置

`/config/worker_server.php`

```php
return [
    'worker_class' => ['wokcrontab\\worker\\Index'],//可以多个
];
```

#### 环境要求

需要使用以下php方法，确保以下方法未被禁用：

```bash
pcntl_wait
pcntl_signal
pcntl_fork
pcntl_signal_dispatch
pcntl_alarm
其他（待补充）
```

#### 启动脚本,start.sh

```bash
COUNT1=`ps -ef |grep WorkerMan|grep -v "grep" |wc -l`;

echo $COUNT1

if [ $COUNT1 -eq 0 ];then

    cd /www/wwwroot/www.localhost.com

    php think worker:server start

fi
```

#### 重启脚本,restart.sh

```bash
cd /www/wwwroot/www.localhost.com

php think worker:server restart
```

修改`/www/wwwroot/www.localhost.com`为实际网站路径

创建定时任务执行sh脚本

#### 启动成功

在linux终端执行以下命令，以判断启动成功

`ps aux | grep WorkerMan`

如果输出类似以下，说明启动成功。

```bash
root      122200  0.0  0.1 217728 13776 ?        S    14:43   0:00 WorkerMan: master process  start_file=/www/wwwroot/www.localhost.com/think
www       123287  0.0  0.2 218316 22000 ?        S    14:55   0:00 WorkerMan: worker process  workcrontab websocket://0.0.0.0:22986
```

如果只有第一条[master process]没有[worker process]，则是启动失败，请到网站的`runimeme`目录里面查看`worker22986.stdout.log`日志分析原因。

#### 添加任务

##### 后台添加

可以载后台创建app，然后添加任务

##### api添加

使用后台创建appid/secret_key来添加任务，便于不同的网站定时任务集中管理。

### 规则

#### 任务类型

只支持访问网址`url`类型的任务。

#### crontab规则

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

一般用5位的就行，秒位默认为0，即每分钟的第0秒执行。

#### 一些常用规则

|  说明   | 规则  |
|  ----  | ----  |
| 每分钟第30秒行                       |     `30  *  *  *  *  *`      |
| 每分钟执行                           |     `*  *  *  *  *`          |
| 每2分钟执行                          |     `*/2  *  *  *  *`        |
| 每隔30分钟执行一次(整点、半点)：        |     `*/30  *  *  *  *`       |
| 每小时第30分钟执行：                   |     `30  *  *  *  *`        |
| 每2小时执行(整点)：                    |    `0  */2  *  *  *`        |
| 每2小时第30分执行：                   |     `30  */2  *  *  *`       |
| 每天03点30分执行：                    |     `30  3  *  *  *`         |
| 每天03~08点,每隔30分钟执行(整点、半点)： |     `*/30  3-8  *  *  *`     |
| 每天03~08点,每小时第30执行：           |     `30  3-8  *  *  *`       |
| 每月1号的3点30执行：                   |     `30  3  1  *  *`         |
| 每月2号的03、06、09点,15分、20分执行：   |     `15,20  3,6,9  2  *  *`  |
| 每周星期二的05点40分执行：              |     `40  5  *  *  2`         |
| 每周(星期日、星期六)的05点40分执行：      |    `40  5  *  *  0,6`        |
| 每年6月的每个星期一05点0分执行：         |     `0  5  *  6 1`           |
| 每年3、6、9月的15号05点0分执行：        |     `0  5  15  3,6,9 *`       |

##### 注意

- 每隔几分钟，每隔几小时，每隔几天，都是代表数字能被整除，不一定是严格间隔。比如，每小时有60分钟，当60除以间隔数字不能除尽时就不是严格间隔。比如`*/7  *  *  *  *`每隔7分钟，每小时56分后要到下一个小时的07分，间隔11分钟。
- day of week 和 day of month是有冲突的，严格来讲不能同时设置。比如`30  3  2  *  2`，每月2号的星期二，能同时满足是2号并且是星期二的日期很少。
- second、minute、hour，一般设置了huor，那么second、minute也必须设置。设置了huor，那么minute也必须设置。比如`*  3  *  *  *`(5位规则精确到分设置了huor，没设置minute，秒位默认0),每天03点00分开始每分钟执行一次，一直到3点59分，执行了60次。再比如`*  *  3  *  *  *`(6位规则精确到秒，设置了huor，没设置minute和second),每天03点00分00秒开始每秒钟执行一次，一直到3点59分59秒，执行了3600次。如果只想在03点的时候执行一次，一般建议写成`0  3  *  *  *`，minute设置为0~59，执行一次。

#### 在线验证工具

<https://tool.lu/crontab/>

#### 请求验证(自版本1.0.8)

请求header中三个参数：`appid`,`time`,`sign`

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
