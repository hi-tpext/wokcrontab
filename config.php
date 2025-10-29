<?php

use tpext\builder\common\Form;
use tpext\common\ExtLoader;

return [
    'port' => 22986,
    'daemonize' => 1,
    'user' => 'www',
    'group' => 'www',
    'count' => 4,
    'timeout' => 5,
    //
    //配置描述
    '__config__' => function (Form $form) {
        if (!ExtLoader::isWebman()) {
            if (!ExtLoader::isTP80()) {
                $form->text('port', '端口号')->help('1000~65535');
            }
            $form->text('user', '运行用户')->help('(linux系统有效)一般为www或www-data，不确定则留空');
            $form->text('group', '运行用户组')->help('(linux系统有效)一般为www或www-data，不确定则留空');
            $form->number('count', '进程数量')->help('可把任务按序号分配到多个进程上，任务过多时可适当提高进程数量');
            if (!ExtLoader::isTP80()) {
                $form->radio('daemonize', '守护模式')->options([1 => '是', 0 => '否'])->help('运行模式，daemonize');
            }
        } else {
            $form->raw('tips', '提示')->value('<p>进程配置信息在`/config/process.php`中设置</p>');
        }
        $form->number('timeout', '超时时间')->help('http请求超时时间，单位秒');
        $form->text('sign_timeout', '设备时间误差')->help('允许的时间误差，当客户端与服务器时间不同步超过值时sign会验证失败');
    },
];
