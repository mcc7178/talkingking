<?php

namespace App\Service\Auth;

use App\Constants\StatusCode;
use App\Foundation\Traits\Singleton;
use App\Model\Auth\User;
use App\Service\BaseService;
use Phper666\JWTAuth\JWT;
use Hyperf\Di\Annotation\Inject;

/**
 * 用户服务基础类
 * Class UserService
 * @package App\Service\Auth
 * @Author YiYuan-Lin
 * @Date: 2020/10/29
 */
class UserService extends BaseService
{
    use Singleton;

    /**
     * @Inject()
     * @var JWT
     */
    private $jwt;

    /**
     * 根据Token获取用户的信息
     * @return object
     */
    public function getUserInfoByToken(): object
    {
        //获取Token解析的数据
        $parserData = $this->jwt->getParserData();
        $userId = $parserData['uid'];

        $userInfo = User::getOneByUid($userId);
        if (!$userInfo) {
            $this->throwExp(StatusCode::ERR_USER_ABSENT, __('validation.account_not_exist'));
        }
        return $userInfo;
    }
}
