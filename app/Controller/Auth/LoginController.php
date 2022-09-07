<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Constants\StatusCode;
use App\Controller\AbstractController;
use App\Model\Auth\User;
use App\Pool\Redis;
use App\Service\Auth\LoginService;
use App\Service\Common\EmailService;
use Psr\Http\Message\ResponseInterface;

/**
 * 登陆控制器
 */
class LoginController extends AbstractController
{
    /**
     * 登陆操作
     * @return ResponseInterface
     */
    public function login()
    {
        $params = [
            'email' => $this->request->input('email') ?? '',
            'password' => $this->request->input('password') ?? '',
        ];
        $rules = [
            'email' => 'required',
            'password' => 'required',
        ];
        $message = [
            'email.required' => __('validation.required'),
            'password.required' => __('validation.required'),
        ];
        $this->verifyParams($params, $rules, $message);

        $responseData = LoginService::getInstance()->login($params);
        return $this->success($responseData);
    }

    /**
     * 注册操作
     * @return ResponseInterface
     */
    public function register()
    {
        $params = [
            'email' => $this->request->input('email', ''),
            'password' => $this->request->input('password', ''),
            'code_key' => $this->request->input('code_key', ''),
            'is_accepted' => $this->request->input('is_accepted', ''),
            'invite_code' => $this->request->input('invite_code', ''),
        ];
        $rules = [
            'email' => 'required|email',
            'password' => 'required|min:8',
            'code_key' => 'required',
            'is_accepted' => 'accepted',
            'invite_code' => 'required',
        ];
        $message = [
            'email.required' => __('validation.required'),
            'email.email' => __('validation.email'),
            'password.required' => __('validation.required'),
            'password.min' => __('validation.min.string', ['min' => 8]),
            'code_key.required' => __('validation.required'),
            'invite_code.required' => __('validation.required'),
            'is_accepted.accepted' => __('validation.accepted'),
        ];
        $this->verifyParams($params, $rules, $message);
        if (!ctype_alnum($this->request->input('password'))) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.password_num_letter'));
        }

        $result = LoginService::getInstance()->register($params);
        if (!$result) $this->throwExp(StatusCode::ERR_REGISTER_ERROR, __('validation.fail'));

        return $this->successByMessage(__('validation.success'));
    }

    /**
     * 重置登录密码
     * @return ResponseInterface
     */
    public function reset_login_password()
    {
        $email = $this->request->input('email', '');
        $password = $this->request->input('password', '');
        $code_key = $this->request->input('code_key', '');
        $params = [
            'email' => $email,
            'password' => $password,
            'code_key' => $code_key,
        ];
        $rules = [
            'email' => 'required|email',
            'password' => 'required|min:8',
            'code_key' => 'required',
        ];
        $message = [
            'email.required' => __('validation.required'),
            'email.email' => __('validation.email'),
            'password.required' => __('validation.required'),
            'password.min' => __('validation.min.string', ['min' => 8]),
            'code_key.required' => __('validation.required'),
        ];
        $this->verifyParams($params, $rules, $message);
        if (!ctype_alnum($password)) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.password_num_letter'));
        }
        $redis = Redis::getInstance();
        $key = 'SEND_EMAIL:' . $email;
        if (!$redis->exists($key)) {
            $this->throwExp(2006, __('validation.code_key_expired'));
        }
        if ($redis->get($key) != $code_key) {
            $this->throwExp(2006, __('validation.code_key_expired'));
        }

        $user = User::getInfoByEmail($email);
        if (!$user) {
            $this->throwExp(StatusCode::ERR_USER_ABSENT, __('validation.account_not_exist'));
        }
        $tmp_password = md5($password);
        $user->password = $tmp_password;
        if (!$user->save()) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.fail'));
        }
        $redis->del($key);
        return $this->success([
            'msg' => __('validation.success'),
        ]);
    }

    /**
     * 发送验证码
     * @return ResponseInterface
     */
    public function send_email()
    {
        $params = [
            'email' => $this->request->input('email', ''),
        ];
        $rules = [
            'email' => 'required|email'
        ];
        $message = [
            'email.required' => __('validation.required'),
            'email.email' => __('validation.email')
        ];
        $this->verifyParams($params, $rules, $message);
        $code = str_pad(mt_rand(0, 1000000) . '', 6, '0');
        $redis = Redis::getInstance();
        if ($redis->setex('SEND_EMAIL:' . $params['email'], 300, $code)) {
            $res = (new EmailService())->send($params['email'], '', $code);
            if (!$res) {
                $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.fail'));
            }
        } else {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.fail'));
        }
        return $this->successByMessage(__('validation.success'));
    }
}
