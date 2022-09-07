<?php

namespace App\Controller;

use App\Constants\StatusCode;
use App\Model\UserCredentials;
use App\Pool\Redis;
use App\Service\Auth\UserService;
use Psr\Http\Message\ResponseInterface;

class SecurityCenter extends AbstractController
{
    public function baseInfo()
    {
        $user = UserService::getInstance()->getUserInfoByToken();
        $info = [
            'verified' => $user->verified ? 1 : 0,
            'security_code' => $user->security_code ? 1 : 0
        ];
        return $this->success([
            'list' => $info
        ]);
    }

    public function info()
    {
        $user = UserService::getInstance()->getUserInfoByToken();
        $info = UserCredentials::query()->where('user_id', $user->id)->first();
        $info['typeDesc'] = UserCredentials::$typeDesc[$info['type']] ?? '';
        $info['statusDesc'] = UserCredentials::$statusDesc[$info['status']] ?? '';
        return $this->success([
            'list' => $info
        ]);
    }

    public function update()
    {
        $user = UserService::getInstance()->getUserInfoByToken();
        $id = $this->request->input('id', 0);
        $model = new UserCredentials();
        if ($id) {
            $model = UserCredentials::query()->where('user_id', $user->id)->findOrFail($id);
        }

        $params = [
            'name' => $this->request->input('name'),
            'type' => $this->request->input('type'),
            'number' => $this->request->input('number'),
        ];
        $model->user_id = $user->id;
        $model->name = $params['name'];
        $model->type = $params['type'];
        $model->number = $params['number'];
        $res = $model->save();
        $user->verified = 1;
        $user->save();
        if (!$res) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.fail'));
        }
        return $this->successByMessage(__('validation.success'));
    }

    /**
     * 参数验证
     * @param $params
     * @return void
     */
    private function validParams($params)
    {
        $rules = [
            'name' => 'required',
            'type' => 'required|in:1,2,3',
            'number' => 'required',
        ];
        $message = [
            'name.required' => __('validation.required'),
            'type.required' => __('validation.required'),
            'type.in' => __('validation.type_error'),
            'number.required' => __('validation.required'),
        ];
        $this->verifyParams($params, $rules, $message);
    }

    /**
     * 修改登录密码
     * @return ResponseInterface
     */
    public function resetLoginPassword()
    {
        $user = UserService::getInstance()->getUserInfoByToken();
        $params = [
            'old_password' => $this->request->input('old_password', ''),
            'password' => $this->request->input('password', ''),
            'repassword' => $this->request->input('repassword', ''),
            'code_key' => $this->request->input('code_key', ''),
        ];
        $rules = [
            'old_password' => 'required',
            'password' => 'required|min:8',
            'repassword' => 'required',
            'code_key' => 'required',
        ];
        $message = [
            'old_password.required' => __('validation.required'),
            'password.required' => __('validation.required'),
            'password.min' => __('validation.min.string', ['min' => 8]),
            'repassword.required' => __('validation.required'),
            'code_key.required' => __('validation.required'),
        ];
        if ($params['password'] != $params['repassword']) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.password_same'));
        }
        $this->verifyParams($params, $rules, $message);
        if (!ctype_alnum($this->request->input('password'))) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.password_num_letter'));
        }
        $old = md5($params['old_password']);
        if ($old != $user->password) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.password_error'));
        }

        $redis = Redis::getInstance();
        $email = $user->email;
        $key = 'SEND_EMAIL:' . $email;
        if (!$redis->exists($key)) {
            $this->throwExp(2006, __('validation.code_key_expired'));
        }
        if ($redis->get($key) != $params['code_key']) {
            $this->throwExp(2006, __('validation.code_key_error'));
        }
        $user->password = md5($params['password']);
        $res = $user->save();
        if (!$res) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.fail'));
        }
        $redis->del($key);
        return $this->successByMessage(__('validation.success'));
    }

    public function setSecurityCode()
    {
        $user = UserService::getInstance()->getUserInfoByToken();
        $params = [
            'password' => $this->request->input('password', ''),
            'repassword' => $this->request->input('repassword', ''),
            'code_key' => $this->request->input('code_key', ''),
        ];
        $rules = [
            'password' => 'required|numeric',
            'repassword' => 'required',
            'code_key' => 'required',
        ];
        $message = [
            'password.numeric' => __('validation.numeric'),
            'password.required' => __('validation.required'),
            'repassword.required' => __('validation.required'),
            'code_key.required' => __('validation.required'),
        ];
        if ($params['password'] != $params['repassword']) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.password_same'));
        }
        if (strlen($params['password']) != 6) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.security_code_len'));
        }
        $this->verifyParams($params, $rules, $message);
        $pwdArr = str_split($params['password']);
        if (count(array_unique($pwdArr)) == 1) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.security_code_repeat'));
        }

        $redis = Redis::getInstance();
        $email = $user->email;
        $key = 'SEND_EMAIL:' . $email;
        if (!$redis->exists($key)) {
            $this->throwExp(2006, __('validation.code_key_expired'));
        }
        if ($redis->get($key) != $params['code_key']) {
            $this->throwExp(2006, __('validation.code_key_error'));
        }

        $step = [];
        for ($i = 0, $j = count($pwdArr); $i < $j; $i++) {
            if ($i < $j - 1) {
                $step[] = $pwdArr[$i + 1] - $pwdArr[$i];
            }
        }
        if (array_unique($step) == [1]) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.security_code_continuous'));
        }
        $user->security_code = md5($params['password']);
        $res = $user->save();
        if (!$res) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.fail'));
        }
        $redis->del($key);
        return $this->successByMessage(__('validation.success'));
    }
}