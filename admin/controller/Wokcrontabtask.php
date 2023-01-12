<?php

namespace wokcrontab\admin\controller;

use wokcrontab\common\model\WokCrontabTask as WokCrontabTaskModel;
use think\Controller;
use tpext\builder\traits\actions;
use think\facade\Lang;
use wokcrontab\common\Module;
use Workerman\Crontab\Parser;
use tpext\think\App;

/**
 * @time tpextmanager 生成于2021-08-06 17:23:30
 * @title 定时任务
 */
class Wokcrontabtask extends Controller
{
    use actions\HasBase;
    use actions\HasIndex;
    use actions\HasAutopost;
    use actions\HasAdd;
    use actions\HasEdit;
    use actions\HasDelete;
    use actions\HasView;

    /**
     * Undocumented variable
     * @var WokCrontabTaskModel
     */
    protected $dataModel;

    protected function initialize()
    {
        $this->dataModel = new WokCrontabTaskModel;
        $this->pageTitle = '定时任务';
        $this->selectTextField = '{app_id}#{uid}:{name}';
        $this->selectSearch = 'id|name';
        $this->pk = 'id';
        $this->pagesize = 14;
        $this->sortOrder = 'id desc';

        $this->indexWith = ['app'];

        Lang::load(Module::getInstance()->getRoot() . implode(DIRECTORY_SEPARATOR, ['admin', 'lang', App::getDefaultLang(), 'wokcrontabtask' . '.php']));
    }

    /**
     * 构建搜索
     * @return mixed
     */
    protected function buildSearch()
    {
        $search = $this->search;

        $search->defaultDisplayerColSize(3);

        $search->select('app_id')->dataUrl(url('/admin/wokcrontabapp/selectpage'));
        $search->text('name');
        $search->text('url');
        $search->text('remark');
        $search->text('tag');
    }

    /**
     * 构建搜索条件
     * @param array $data
     * @return mixed
     */
    protected function filterWhere()
    {
        $searchData = request()->get();
        $where = [];
        if (isset($searchData['app_id']) && $searchData['app_id'] != '') {
            $where[] = ['app_id', '=', $searchData['app_id']];
        }
        if (isset($searchData['name']) && $searchData['name'] != '') {
            $where[] = ['name', 'like', '%' . trim($searchData['name']) . '%'];
        }
        if (isset($searchData['url']) && $searchData['url'] != '') {
            $where[] = ['url', 'like', '%' . trim($searchData['url']) . '%'];
        }
        if (isset($searchData['remark']) && $searchData['remark'] != '') {
            $where[] = ['remark', 'like', '%' . trim($searchData['remark']) . '%'];
        }
        if (isset($searchData['tag']) && $searchData['tag'] != '') {
            $where[] = ['tag', 'like', '%' . trim($searchData['tag']) . '%'];
        }

        return $where;
    }

    /**
     * 构建表格
     * @param array $data
     * @return mixed
     */
    protected function buildTable(&$data = [], $isExporting = false)
    {
        $table = $this->table;

        $table->show('id');
        $table->show('app_id')->to('{app_id}#{app.name}');
        $table->text('name')->autoPost()->getWrapper()->addStyle('max-width:150px');
        $table->fields('rule', '规则')->with(
            $table->show('rule'),
            $table->show('url')->cut(130)
        )->getWrapper()->addStyle('max-width:250px');
        $table->show('remark')->autoPost();
        $table->show('tag')->autoPost();
        $table->fields('last_run_info', '最后运行信息')->with(
            $table->show('last_run_info')->cut(30),
            $table->show('last_run_time')
        )->getWrapper()->addStyle('max-width:250px');
        $table->fields('create_time', '添加/修改时间')->with(
            $table->show('create_time'),
            $table->show('update_time')
        );
        $table->getToolbar()
            ->btnAdd()
            ->btnRefresh();

        $table->getActionbar()
            ->btnEdit()
            ->btnView()
            ->btnDelete();

        $table->sortable('id,app_id,uid');

        $this->builder()->addStyleSheet('
        .table > tbody > tr > td .row-last_run_info,.table > tbody > tr > td .row-rule
        {
            white-space:normal;
        }
        ');
    }

    /**
     * 构建搜索条件
     * @param boolean $isEdit
     * @param array $data
     * @return mixed
     */
    protected function buildForm($isEdit, &$data = [])
    {
        $form = $this->form;

        $form->hidden('id');
        $form->select('app_id')->dataUrl(url('/admin/wokcrontabapp/selectpage'))->required();
        $form->text('name')->maxlength(55)->required();
        $form->text('rule')->maxlength(55)->default('*  *  *  *  *')->required()->help('crontab格式的规则，一定要仔细了解规则，避免频繁请求对服务器造成压力。<pre>' . '
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
精确到秒(6位)：0  *  *  *  *  *'
            . '</pre>');

        $form->text('url')->maxlength(255)->required()->help('要请求的网址');
        $form->textarea('remark')->maxlength(255);
        $form->text('tag')->maxlength(55);

        if ($isEdit) {
            $form->show('last_run_info');
            $form->show('last_run_time');
            $form->show('create_time');
            $form->show('update_time');
        }
    }

    /**
     * 保存数据
     * @param integer $id
     * @return mixed
     */
    private function save($id = 0)
    {
        $data = request()->post();

        $result = $this->validate($data, [
            'name|名称' => 'require',
            'rule|规则' => 'require',
            'url|规则' => 'require',
        ]);

        if (true !== $result) {
            $this->error($result);
        }

        $parser = new Parser();

        if (!$parser->isValid($data['rule'])) {
            $this->error('规则输入有误');
        }

        return $this->doSave($data, $id);
    }
}
