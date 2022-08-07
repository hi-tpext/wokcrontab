<?php

namespace wokcrontab\api\controller;

use think\Controller;
use wokcrontab\common\logic\CrontabApp;

class Wokcrontabadmin extends Controller
{
    /**
     * Undocumented variable
     *
     * @var CrontabApp
     */
    protected $appLogic;

    protected function initialize()
    {
        $this->appLogic = new CrontabApp;
    }

    /**
     * Undocumented function
     *
     * @param array|null $data
     * @return void
     */
    private function validateApp($data = null)
    {
        if ($data == null) {
            $data = request()->post();
        }

        if (isset($data['secret'])) {
            return ['code' => 0, 'msg' => '不要传secret参数'];
        }

        $result = $this->validate($data, [
            'app_id|应用app_id' => 'require|number',
            'sign|sign签名' => 'require',
            'time|时间戳' => 'require|number',
        ]);

        if ($result !== true) {
            return ['code' => 0, 'msg' => $result];
        }

        $res = $this->appLogic->validateApp($data['app_id'], $data['sign'], $data['time']);

        return $res;
    }

    public function pushTask()
    {
        $data = request()->post();

        $valdate = $this->validateApp($data);

        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'name|任务名称' => 'require',
            'rule|任务规则' => 'require',
            'url|url' => 'require',
            //'remark|用户备注' => 'require',
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        if (!isset($data['remark'])) {
            $data['remark'] = $data['name'];
        }

        if (!isset($data['avatar'])) {
            $data['avatar'] = '';
        }

        $res = $this->appLogic->pushTask($data['name'], $data['rule'], $data['url'], $data['remark']);

        return json($res);
    }

    public function taskList()
    {
        $data = request()->post();

        $valdate = $this->validateApp($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $res = $this->appLogic->taskList($data['tag'] ?? '');

        return json($res);
    }

    public function editTask()
    {
        $data = request()->post();

        $valdate = $this->validateApp($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'id|任务id' => 'require',
            'name|任务名称' => 'require',
            'rule|任务规则' => 'require',
            'url|url' => 'require',
            //'remark|用户备注' => 'require',
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        $res = $this->appLogic->editTask($data['id'], $data);

        return json($res);
    }

    public function deleteTask()
    {
        $data = request()->post();

        $valdate = $this->validateApp($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'id|任务id' => 'require',
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        $res = $this->appLogic->deleteTask($data['id']);

        return json($res);
    }

    public function editTaskName()
    {
        $data = request()->post();

        $valdate = $this->validateApp($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'id|任务id' => 'require',
            'name|任务名称' => 'require',
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        $res = $this->appLogic->editTaskName($data['id'], $data['name']);

        return json($res);
    }

    public function editTaskRule()
    {
        $data = request()->post();

        $valdate = $this->validateApp($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'id|任务id' => 'require',
            'rule|规则' => 'require',
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        $res = $this->appLogic->editTaskRule($data['id'], $data['rule']);

        return json($res);
    }

    public function editTaskUrl()
    {
        $data = request()->post();

        $valdate = $this->validateApp($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'id|任务id' => 'require',
            'url|url' => 'require',
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        $res = $this->appLogic->editTaskUrl($data['id'], $data['url']);

        return json($res);
    }

    public function editTaskRemark()
    {
        $data = request()->post();

        $valdate = $this->validateApp($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'id|任务id' => 'require',
            'remark|备注信息' => 'require',
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        $res = $this->appLogic->editTaskRemark($data['id'], $data['remark']);

        return json($res);
    }

    public function editTaskTag()
    {
        $data = request()->post();

        $valdate = $this->validateApp($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'id|任务id' => 'require',
            'tag|标签' => 'require',
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        $res = $this->appLogic->editTaskTag($data['id'], $data['tag']);

        return json($res);
    }
}
