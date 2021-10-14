<?php

namespace wokcrontab\common\model;

use think\Model;

class WokCrontabTask extends Model
{
    protected $name = 'wok_crontab_task';

    protected $autoWriteTimestamp = 'datetime';

    public function app()
    {
        return $this->belongsTo(WokCrontabApp::class, 'app_id', 'id');
    }
}
