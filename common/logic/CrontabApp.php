<?php

namespace wokcrontab\common\logic;

use wokcrontab\common\model;
use Workerman\Crontab\Parser;

/**
 * 封装后台操作，添加用户、修改用户
 */

class CrontabApp
{
    protected $app_id = 0;

    /**
     * Undocumented variable
     *
     * @var model\WokChatApp
     */
    protected $app = null;

    /**
     * Undocumented function
     *
     * @param int $app_id
     * @param string $sign
     * @param int $time
     * @return array
     */
    public function validateApp($app_id, $sign, $time)
    {
        if (empty($app_id) || empty($sign) || empty($time)) {
            return ['code' => 0, 'msg' => '参数错误'];
        }

        if (abs(time() - $time) > 10) {
            return ['code' => 0, 'msg' => 'sign超时请检查设备时间'];
        }

        $app = model\WokCrontabApp::where('id', $app_id)->find();

        if (!$app) {
            return ['code' => 0, 'msg' => 'app_id:应用未找到'];
        }

        if ($app['enable'] == 0) {
            return ['code' => 0, 'msg' => '应用未开启'];
        }

        if (empty($app['secret'])) {
            return ['code' => 0, 'msg' => '系统错误，secret配置有误'];
        }

        if ($sign != md5($app['secret'] . $time)) {
            return ['code' => 0, 'msg' => 'sign验证失败'];
        }

        unset($app['secret']);

        $this->app = $app;
        $this->app_id = $app_id;

        return ['code' => 1, 'msg' => '成功'];
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function isValidateApp()
    {
        if (empty($this->app) || empty($this->app_id)) {
            return ['code' => 0, 'msg' => 'app验证未通过'];
        }

        return ['code' => 1, 'msg' => '成功'];
    }

    /**
     * 免验证，切换到用户
     *
     * @param array $app
     * @return void
     */
    public function switchApp($app)
    {
        unset($user['secret']);
        $this->app = $app;
        $this->app_id = $app['id'];
    }

    /**
     * Undocumented function
     *
     * @param string $name
     * @param string $rule
     * @param string $url
     * @param string $remark
     * @param string $tag
     * @return array
     */
    public function pushTask($name, $rule, $url, $remark = '', $tag = '')
    {
        $valdate = $this->isValidateApp();

        if ($valdate['code'] != 1) {
            return $valdate;
        }

        if (empty($remark)) {
            $remark = $name;
        }

        $parser = new Parser();

        if (!$parser->isValid($rule)) {
            return ['code' => 0, 'msg' => '规则输入有误'];
        }

        if ($exist = model\WokCrontabTask::where(['app_id' => $this->app_id, 'name' => $name])->find()) {
            $res = $exist->save([
                'name' => $name,
                'rule' => $rule,
                'url' => $url,
                'remark' => $remark,
                'tag' => $tag,
            ]);

            if ($res) {
                return ['code' => 1, 'msg' => '成功'];
            }

            return ['code' => 0, 'msg' => '保存失败'];
        }

        $user = new model\WokCrontabTask;

        $data = [
            'app_id' => $this->app_id,
            'name' => $name,
            'rule' => $rule,
            'url' => $url,
            'remark' => $remark,
        ];

        $res = $user->save($data);

        if ($res) {
            return ['code' => 1, 'msg' => '成功'];
        }

        return ['code' => 0, 'msg' => '添加失败'];
    }

    /**
     * Undocumented function
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function editTask($id, $data)
    {
        $valdate = $this->isValidateApp();

        if ($valdate['code'] != 1) {
            return $valdate;
        }

        if (isset($data['rule'])) {
            $parser = new Parser();
            if (!$parser->isValid($data['rule'])) {
                return ['code' => 0, 'msg' => '规则输入有误'];
            }
        }

        if ($exist = model\WokCrontabTask::where(['id' => $id, 'app_id' => $this->app_id])->find()) {
            $res = $exist->allowField(['name', 'rule', 'url', 'remark', 'tag'])->save($data);

            if ($res) {
                return ['code' => 1, 'msg' => '成功'];
            }

            return ['code' => 0, 'msg' => '修改失败'];
        }

        return ['code' => 0, 'msg' => '任务不存在'];
    }

    /**
     * Undocumented function
     *
     * @param int $id
     * @param string $name
     * @return array
     */
    public function editTaskName($id, $name)
    {
        return $this->editTask($id, ['name' => $name]);
    }

    /**
     * Undocumented function
     *
     * @param int $id
     * @param string $rule
     * @return array
     */
    public function editTaskRule($id, $rule)
    {
        return $this->editTask($id, ['rule' => $rule]);
    }

    /**
     * Undocumented function
     *
     * @param int $id
     * @param string $url
     * @return array
     */
    public function editTaskUrl($id, $url)
    {
        return $this->editTask($id, ['url' => $url]);
    }

    /**
     * Undocumented function
     *
     * @param int $id
     * @param string $remark
     * @return array
     */
    public function editTaskRemark($id, $remark)
    {
        return $this->editTask($id, ['remark' => $remark]);
    }

    /**
     * Undocumented function
     *
     * @param int $id
     * @param string $tag
     * @return array
     */
    public function editTaskTag($id, $tag)
    {
        return $this->editTask($id, ['tag' => $tag]);
    }

    /**
     * Undocumented function
     *
     * @param int $id
     * @return array
     */
    public function deleteTask($id)
    {
        $valdate = $this->isValidateApp();

        if ($valdate['code'] != 1) {
            return $valdate;
        }

        if ($exist = model\WokCrontabTask::where(['id' => $id, 'app_id' => $this->app_id])->find()) {
            $res = $exist->delete();

            if ($res) {
                return ['code' => 1, 'msg' => '删除成功'];
            }

            return ['code' => 0, 'msg' => '删除失败'];
        }

        return ['code' => 0, 'msg' => '任务不存在'];
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function taskList($tag = '')
    {
        $where = [['app_id', '=', $this->app_id]];
        if ($tag) {
            $where[] = ['tag', 'like', "%{$tag}%"];
        }

        $list =  model\WokCrontabTask::where($where)->select();

        return ['code' => 1, 'data' => $list];
    }
}
