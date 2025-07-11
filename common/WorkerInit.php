<?php

namespace wokcrontab\common;

use think\worker\Manager;
use think\worker\Worker;
use wokcrontab\worker\Webman;

## tp8 自定义worker
## 监听`worker.init`事件 注入`Manager`对象，调用addWorker方法添加

/**
 * Undocumented class
 */
class WorkerInit extends Webman
{
    public function handle(Manager $manager)
    {
        $manager->addWorker([$this, 'createHttpServer'], 'wokcrontab', 1);
    }

    public function createHttpServer($server)
    {
        $config = Module::getInstance()->getConfig();
        $count = $config['count'] ?: 4;

        for ($i = 0; $i < $count; $i += 1) {
            $server = new Worker();
            $server->id = $i;
            $server->count = $count;

            if ($config['user']) {
                $server->user = $config['user'];
            }
            if ($config['group']) {
                $server->group = $config['group'];
            }

            $callbackMap = [
                'onConnect',
                'onMessage',
                'onClose',
                'onError',
                'onBufferFull',
                'onBufferDrain',
                'onWorkerStop',
                'onWebSocketConnect',
                'onWorkerReload'
            ];

            foreach ($callbackMap as $name) {
                if (method_exists($this, $name)) {
                    $server->$name = [$this, $name];
                }
            }

            if (method_exists($this, 'onWorkerStart')) {
                call_user_func([$this, 'onWorkerStart'], $server);
            }

            $server->listen();
        }
    }
}
