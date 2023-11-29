<?php

return [
    'port' => 22986,
    'daemonize' => 1,
    'user' => 'www',
    'group' => 'www',
    'count' => 4,
    'tips' => '<p>以上配置在thinkphp中有效。webman请在/config/process.php自定义进程中设置</p>',
    //
    //配置描述
    '__config__' => [
        'port' => ['type' => 'text', 'label' => '端口号', 'size' => [2, 8], 'help' => '1000~65535'],
        'daemonize' => ['type' => 'radio', 'label' => '守护模式', 'options' => [1 => '是', 0 => '否'], 'size' => [2, 8], 'help' => '运行模式，daemonize'],
        'user' => ['type' => 'text', 'label' => '运行用户', 'size' => [2, 8], 'help' => '(linux系统有效)一般为www或www-data，确保系统中用户存在，不行的话填root'],
        'group' => ['type' => 'text', 'label' => '运行用户组', 'size' => [2, 8], 'help' => '(linux系统有效)一般为www或www-data，确保系统中分组存在，不行的话填root'],
        'count' => ['type' => 'number', 'label' => '进程数量', 'size' => [2, 8], 'help' => '可把任务按序号分配到多个进程上，任务过多时可适当提高进程数量'],
        'tips' => ['type' => 'raw', 'label' => '提示', 'size' => [2, 8]],
    ],
];
