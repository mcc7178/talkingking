<?php

declare(strict_types=1);

namespace App\Controller\System;

use App\Constants\StatusCode;
use App\Controller\AbstractController;
use App\Foundation\Annotation\Explanation;
use App\Model\System\GlobalConfig;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use Hyperf\HttpServer\Annotation\RequestMapping;
use App\Middleware\RequestMiddleware;
use App\Middleware\PermissionMiddleware;

/**
 * 全局参数配置
 * Class GlobalConfigController
 * @package App\Controller\System
 * @Controller(prefix="setting/system_module/global_config")
 * @Author YiYuan-Lin
 * @Date: 2021/6/10
 */
class GlobalConfigController extends AbstractController
{
    /**
     * @Inject()
     * @var GlobalConfig
     */
    private $globalConfig;

    /**
     * 获取参数列表
     * @RequestMapping(path="list", methods="get")
     * @Middlewares({
     *     @Middleware(RequestMiddleware::class),
     *     @Middleware(PermissionMiddleware::class)
     * })
     */
    public function index()
    {
        $globalConfigQuery = $this->globalConfig->newQuery();
        $name = $this->request->input('name') ?? '';
        $keyName = $this->request->input('key_name') ?? '';
        $type = $this->request->input('type') ?? '';

        if (!empty($name)) $globalConfigQuery->where('name', 'like', '%'. $name . '%');
        if (!empty($keyName)) $globalConfigQuery->where('key_name', 'like', '%'. $keyName . '%');
        if (!empty($type)) $globalConfigQuery->where('type', $type);

        $total = $globalConfigQuery->count();
        $globalConfigQuery->orderBy('created_at', 'desc');
        $globalConfigQuery = $this->pagingCondition($globalConfigQuery, $this->request->all());
        $data = $globalConfigQuery->get();

        return $this->success([
            'list' => $data,
            'total' => $total,
        ]);
    }

    /**
     * @Explanation(content="添加全局参数")
     * @RequestMapping(path="store", methods="post")
     * @Middlewares({
     *     @Middleware(RequestMiddleware::class),
     *     @Middleware(PermissionMiddleware::class)
     * })
     */
    public function store()
    {
        $postData = $this->request->all();
        $params = [
            'name' => $postData['name'] ?? '',
            'key_name' => $postData['key_name'] ?? '',
            'data' => $postData['data'] ?? '',
            'remark' => $postData['remark'] ?? '',
            'type' => $postData['type'] ?? '',
        ];
        //配置验证
        $rules = [
            'name' => 'required',
            'key_name' => 'required',
            'type' => 'required',
        ];
        //错误信息
        $message = [
            'name.required' => '[name]缺失',
            'key_name.required' => '[key_name]缺失',
            'type.required' => '[type]缺失',
        ];
        $this->verifyParams($params, $rules, $message);

        $globalConfigQuery = new GlobalConfig();
        $globalConfigQuery->name = $params['name'];
        $globalConfigQuery->key_name = $params['key_name'];
        $globalConfigQuery->data = $params['data'];
        $globalConfigQuery->remark = $params['remark'];
        $globalConfigQuery->type = $params['type'];

        if (!$globalConfigQuery->save()) $this->throwExp(StatusCode::ERR_EXCEPTION, '添加全局参数错误');

        return $this->successByMessage('添加全局参数成功');
    }

    /**
     * 获取单个全局参数信息
     * @param int $id
     * @RequestMapping(path="edit/{id}", methods="get")
     * @Middlewares({
     *     @Middleware(RequestMiddleware::class),
     * })
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function edit(int $id)
    {
        $globalConfigInfo = GlobalConfig::findById($id);
        if (empty($globalConfigInfo)) $this->throwExp(StatusCode::ERR_USER_ABSENT, '获取全局参数失败');

        if ($globalConfigInfo['type'] == GlobalConfig::TYPE_BY_BOOLEAN) {
            $globalConfigInfo['data'] = boolval($globalConfigInfo['data']);
        }

        return $this->success([
            'list' => $globalConfigInfo
        ]);
    }

    /**
     * @Explanation(content="修改全局参数信息")
     * @param int $id
     * @RequestMapping(path="update/{id}", methods="put")
     * @Middlewares({
     *     @Middleware(RequestMiddleware::class),
     *     @Middleware(PermissionMiddleware::class)
     * })
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function update(int $id)
    {
        if (empty($id)) $this->throwExp(StatusCode::ERR_VALIDATION, 'ID 不能为空');
        $postData = $this->request->all();
        $params = [
            'name' => $postData['name'] ?? '',
            'key_name' => $postData['key_name'] ?? '',
            'data' => $postData['data'] ?? '',
            'remark' => $postData['remark'] ?? '',
            'type' => $postData['type'] ?? '',
        ];
        //配置验证
        $rules = [
            'name' => 'required',
            'key_name' => 'required',
            'type' => 'required',
        ];
        //错误信息
        $message = [
            'name.required' => '[name]缺失',
            'key_name.required' => '[key_name]缺失',
            'type.required' => '[type]缺失',
        ];
        $this->verifyParams($params, $rules, $message);

        $globalConfigQuery = GlobalConfig::findById($id);
        $globalConfigQuery->name = $params['name'];
        $globalConfigQuery->key_name = $params['key_name'];
        $globalConfigQuery->data = $params['data'];
        $globalConfigQuery->remark = $params['remark'];
        $globalConfigQuery->type = $params['type'];

        if (!$globalConfigQuery->save()) $this->throwExp(StatusCode::ERR_EXCEPTION, '修改全局参数错误');

        return $this->successByMessage('修改全局参数成功');
    }

    /**
     * @Explanation(content="删除全局参数")
     * @param int $id
     * @RequestMapping(path="destroy/{id}", methods="delete")
     * @Middlewares({
     *     @Middleware(RequestMiddleware::class),
     *     @Middleware(PermissionMiddleware::class)
     * })
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function destroy(int $id)
    {
        if ($id == 0) {
            $idArr = $this->request->input('id') ?? [];
            if (empty($idArr) || !is_array($idArr)) $this->throwExp(StatusCode::ERR_VALIDATION, '参数类型不正确');
            if (!GlobalConfig::whereIn('id', $idArr)->delete()) $this->throwExp(StatusCode::ERR_EXCEPTION, '删除失败');
        }else {
            if (!intval($id)) $this->throwExp(StatusCode::ERR_VALIDATION, '参数错误');
            if (!GlobalConfig::destroy($id)) $this->throwExp(StatusCode::ERR_EXCEPTION, '删除失败');
        }

        return $this->successByMessage('删除全局参数成功');
    }

}