<?php

namespace wokcrontab\common;

use tpext\common\Module as baseModule;
use tpext\common\ExtLoader;

/**
 * Undocumented class
 */
class Module  extends baseModule
{
    protected $version = '1.0.6';

    protected $name = 'wokman.crontab';

    protected $title = 'workerman定时任务';

    protected $description = '基于workerman实现的定时任务系统';

    protected $root = __DIR__ . '/../';

    protected $modules = [
        'admin' => ['wokcrontabapp', 'wokcrontabtask'],
        'api' => ['wokcrontabdmin']
    ];

    /**
     * 后台菜单
     *
     * @var array
     */
    protected $menus = [
        [
            'title' => '定时任务',
            'sort' => 1,
            'url' => '#',
            'icon' => 'mdi mdi-av-timer',
            'children' => [
                [
                    'title' => '应用管理',
                    'sort' => 1,
                    'url' => '/admin/wokcrontabapp/index',
                    'icon' => 'mdi mdi-apple-keyboard-command',
                ],
                [
                    'title' => '任务管理',
                    'sort' => 2,
                    'url' => '/admin/wokcrontabtask/index',
                    'icon' => 'mdi mdi-subdirectory-arrow-right',
                ]
            ],
        ]
    ];

    /**
     * 默认的configPath()是composer模式带`src`的，extend模式没有src所以重写一下。
     * 不重写此方法也可以，创建一个`src`目录把config.php放里面
     *
     * @return string
     */
    public function configPath()
    {
        return realpath($this->getRoot() . 'config.php');
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function install()
    {
        if (!class_exists('\\think\\worker\\Server')) { //根据think-worker中某一个类是否存在来判断sdk是否已经安装

            if (ExtLoader::isTP51()) {

                $this->errors[] = new \Exception('<p>请使用composer安装think-worker后再安装本扩展！</p><pre>composer require topthink/think-worker:^2.0</pre>');
            } else if (ExtLoader::isTP60()) {

                $this->errors[] = new \Exception('<p>请使用composer安装think-worker后再安装本扩展！</p><pre>composer require topthink/think-worker:^3.0</pre>');
            }

            return false;
        }

        if (!class_exists('\\Workerman\\Crontab\\Crontab')) { //根据Workerman-Crontab中某一个类是否存在来判断sdk是否已经安装

            $this->errors[] = new \Exception('<p>请使用composer安装think-worker后再安装本扩展！</p><pre>composer require workerman/crontab:^1.0</pre>');

            return false;
        }

        return parent::install();
    }
}
