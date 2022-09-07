<?php

namespace App\Controller;

use App\Constants\StatusCode;
use App\Model\WithdrawAddress;
use App\Service\Auth\UserService;
use Exception;
use Psr\Http\Message\ResponseInterface;

class WithdrawAddressController extends AbstractController
{
    /**
     * 列表
     * @return ResponseInterface
     */
    public function list()
    {
        $user = UserService::getInstance()->getUserInfoByToken();
        $uid = $user->id;
        $page = $this->request->input('page', 1);
        $size = $this->request->input('size', 20);
        $offset = ($page - 1) * $size;

        $model = WithdrawAddress::query()->where('user_id', $uid);
        $count = $model->count();
        $list = $model
            ->offset($offset)
            ->limit($size)
            ->orderByDesc('updated_at')
            ->get()
            ->toArray();

        return $this->success([
            'list' => $list,
            'count' => $count
        ]);
    }

    /**
     * 新增
     * @return ResponseInterface
     */
    public function add()
    {
        $user = UserService::getInstance()->getUserInfoByToken();
        $uid = $user->id;
        $params = [
            'address' => $this->request->input('address'),
            'tag' => $this->request->input('tag', ''),
            'security_code' => $this->request->input('security_code'),
        ];
        $this->validParams($params);
        $code = md5($params['security_code']);
        if ($user->security_code != $code) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.security_code_error'));
        }
        $model = new WithdrawAddress();
        $model->user_id = $uid;
        $model->tag = $params['tag'];
        $model->address = $params['address'];
        $res = $model->save();
        if (!$res) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.fail'));
        }
        return $this->successByMessage(__('validation.success'));
    }

    /**
     * 详情
     * @param int $id
     * @return ResponseInterface
     */
    public function info(int $id)
    {
        $user = UserService::getInstance()->getUserInfoByToken();
        $uid = $user->id;
        $info = WithdrawAddress::query()->where('user_id', $uid)->findOrFail($id)->toArray();
        return $this->success([
            'list' => $info
        ]);
    }

    /**
     * 更新数据
     * @param int $id
     * @return ResponseInterface
     */
    public function update(int $id)
    {
        $user = UserService::getInstance()->getUserInfoByToken();
        $uid = $user->id;
        $model = WithdrawAddress::query()->where('user_id', $uid)->findOrFail($id);

        $params = [
            'address' => $this->request->input('address'),
            'tag' => $this->request->input('tag', ''),
            'security_code' => $this->request->input('security_code'),
        ];
        $exist = $this->isExist($params['address'], $model->id);
        if ($exist) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.fail'));
        }
        $this->validParams($params);
        $model->user_id = $uid;
        $model->tag = $params['tag'];
        $model->address = $params['address'];
        $res = $model->save();
        if (!$res) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.fail'));
        }
        return $this->successByMessage(__('validation.success'));
    }

    /**
     * 删除数据
     * @param int $id
     * @return ResponseInterface
     * @throws Exception
     */
    public function delete(int $id)
    {
        $user = UserService::getInstance()->getUserInfoByToken();
        $uid = $user->id;
        $model = WithdrawAddress::query()->where('user_id', $uid)->findOrFail($id);
        $model->delete();
        return $this->successByMessage(__('validation.success'));
    }

    /**
     * 数据是否存在
     * @param $address
     * @param $id
     * @return bool
     */
    private function isExist($address, $id)
    {
        $model = WithdrawAddress::query()->where('address', $address);
        if ($id) {
            $model->where('id', '<>', $id);
        }
        return $model->exists();
    }

    /**
     * 参数验证
     * @param $params
     * @return void
     */
    private function validParams($params)
    {
        $rules = [
            'address' => 'required',
            'security_code' => 'required',
        ];
        $message = [
            'address.required' => __('validation.required'),
            'security_code.required' => __('validation.required'),
        ];
        $this->verifyParams($params, $rules, $message);
    }
}