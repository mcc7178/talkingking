<?php

namespace App\Service\Auth;

use App\Constants\StatusCode;
use App\Foundation\Traits\Singleton;
use App\Model\Auth\Permission;
use App\Model\Auth\User;
use App\Model\System\LoginLog;
use App\Model\Wallet;
use App\Service\BaseService;
use App\Service\Common\TronService;
use App\Service\System\LoginLogService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Redis\Redis;
use Hyperf\Utils\ApplicationContext;
use Phper666\JWTAuth\JWT;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * 登陆服务基础类
 * Class LoginService
 * @package App\Service\Auth
 * @Author YiYuan-Lin
 * @Date: 2020/10/29
 */
class LoginService extends BaseService
{
    use Singleton;

    /**
     * @Inject()
     * @var JWT
     */
    private $jwt;

    /**
     * 处理登陆逻辑
     * @param array $params
     * @return array
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function login(array $params): array
    {
        //获取用户信息
        $user = User::getInfoByEmail($params['email']);

        //检查用户以及密码是否正确以及检查账户是否被停用
        if (empty($user)) $this->throwExp(StatusCode::ERR_USER_ABSENT, __('validation.account_not_exist'));
        if (md5($params['password']) != $user->password) $this->throwExp(StatusCode::ERR_USER_PASSWORD, __('validation.password_error'));
        if ($user->status != 1) $this->throwExp(StatusCode::ERR_USER_DISABLE, __('validation.account_null'));

        $userData = [
            'uid' => $user->id, //如果使用单点登录，必须存在配置文件中的sso_key的值，一般设置为用户的id
            'email' => $user->email,
        ];
        $token = $this->jwt->getToken($userData);

        //更新用户信息
        $user->last_login = date('Y-m-d H:i:s');
        $user->last_ip = getClientIp($this->request);
        $user->save();
        $responseData = $this->respondWithToken($token);

        //记录登陆日志
        $loginLogData = LoginLogService::getInstance()->collectLoginLogInfo();
        $loginLogData['response_code'] = 200;
        $loginLogData['response_result'] = '登陆成功';
        LoginLog::add($loginLogData);

        return $responseData;
    }

    /**
     * 处理注册逻辑
     * @param array $params
     * @return bool
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function register(array $params): bool
    {
        $redis = \App\Pool\Redis::getInstance();
        $postData = $this->request->all();

        $exist = User::getInfoByEmail($params['email']);
        if ($exist) {
            $this->throwExp(2006, __('validation.account_exist'));
        }
        $invite_user = User::query()->where('invite_code', $postData['invite_code'])->first();
        if(!$invite_user){
            $this->throwExp(2006, __('validation.invate_code_not_exist'));
        }
        $key = 'SEND_EMAIL:'.$postData['email'];
        if(!$redis->exists($key)){
            $this->throwExp(2006, __('validation.code_key_expired'));
        }
        if($redis->get($key) != $params['code_key']){
            $this->throwExp(2006, __('validation.code_key_error'));
        }

        //生成钱包地址和私钥
        $address = (new TronService())->generateAddress();

        $invite_code = self::generateCode();
        $user = new User();
        $user->email = $postData['email'];
        $user->phone = '';
        $user->invite_code = $invite_code;
        $user->invite_user = $invite_user->id;
        $user->avatar = 'https://shmily-album.oss-cn-shenzhen.aliyuncs.com/admin_face/face' . rand(1, 10) . '.png';
        $user->password = md5($postData['password']);
        $user->status = User::STATUS_ON;
        $user->last_login = date('Y-m-d H:i:s');
        $user->last_ip = getClientIp($this->request);
        $user->privateKey = base64_encode($invite_code.':'.$address->privateKey);
        $user->address = $address->address;

        if (!$user->save()){
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.fail'));
        }

        Wallet::insert(['user_id'=>$user->id]);

        $redis->del($key);
        return true;
    }

    /**
     * 登陆初始化，获取用户信息以及一些权限菜单
     * @return mixed
     */
    public function initialization(): array
    {
        $responseData = [];
        //获取用户信息
        $user = UserService::getInstance()->getUserInfoByToken();
        $userInfo = objToArray($user);
        unset($userInfo['roles']);
        unset($userInfo['permissions']);

        $menu = $this->getMenuList($user);
        $responseData['user_info'] = objToArray($userInfo);
        $responseData['role_info'] = $user->getRoleNames();
        $responseData['menu_header'] = $menu['menuHeader'];
        $responseData['menu_list'] = $menu['menuList'];
        $responseData['permission'] = $menu['permission'];
        $responseData['permission_info'] = $menu['permission_info'];

        return $responseData;
    }

    /**
     * 处理权限得到路由（提供给前端注册路由）
     * @return array
     */
    public function getRouters(): array
    {
        $userInfo = conGet('user_info');
        $permissionList = Permission::getUserPermissions($userInfo);
        $permissionList = objToArray($permissionList);
        $permissionList = array_column($permissionList, null, 'id');

        foreach ($permissionList as $key => $val) {
            if ($val['status'] == Permission::OFF_STATUS) unset($permissionList[$key]);
            if ($val['type'] == Permission::BUTTON_OR_API_TYPE) unset($permissionList[$key]);
        }

        //使用引用传递递归数组
        $routers = [
            'default' => [
                'path' => '',
                'component' => 'Layout',
                'redirect' => '/home',
                'children' => [],
            ]
        ];
        $module_children = [];
        foreach ($permissionList as $key => $value) {
            if (isset($permissionList[$value['parent_id']])) {
                $permissionList[$value['parent_id']]['children'][] = &$permissionList[$key];
            } else {
                $module_children[] = &$permissionList[$key];
            }
        }
        foreach ($module_children as $key => $value) {
            if (!empty($value['children'])) {
                $routers[$value['id']] = [
                    'name' => $value['name'],
                    'path' => $value['url'],
                    'redirect' => 'noRedirect',
                    'hidden' => $value['hidden'],
                    'alwaysShow' => true,
                    'component' => $value['component'],
                    'meta' => [
                        'icon' => $value['icon'],
                        'title' => $value['display_name'],
                    ],
                    'children' => []
                ];
                $routers[$value['id']]['children'] = $this->dealRouteChildren($value['children']);
            } else {
                array_push($routers['default']['children'], [
                    'name' => $value['name'],
                    'path' => $value['url'],
                    'hidden' => $value['hidden'],
                    'alwaysShow' => true,
                    'component' => $value['component'],
                    'meta' => [
                        'icon' => $value['icon'],
                        'title' => $value['display_name'],
                    ],
                ]);
            }
        }
        return array_values($routers);
    }

    /**
     * 处理路由下顶级路由下子路由
     * @param array $children
     * @return array
     */
    private function dealRouteChildren(array $children): array
    {
        $temp = [];
        if (!empty($children)) {
            foreach ($children as $k => $v) {
                if ($v['type'] == Permission::MENU_TYPE) {
                    $temp[] = [
                        'name' => $v['name'],
                        'path' => $v['url'],
                        'hidden' => $v['hidden'],
                        'alwaysShow' => true,
                        'component' => $v['component'],
                        'meta' => [
                            'icon' => $v['icon'],
                            'title' => $v['display_name'],
                        ],
                    ];
                }
                if (!empty($v['children'])) {
                    $temp = array_merge($temp, $this->dealRouteChildren($v['children']));
                }
            }
        }
        return $temp;
    }

    /**
     * 处理TOKEN数据
     * @param $token
     * @return array
     */
    protected function respondWithToken(string $token): array
    {
        $data = [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->jwt->getTTL(),
        ];
        return $data;
    }

    /**
     * 获取头部菜单数据以及菜单列表
     * @param object $user
     * @return array
     */
    protected function getMenuList(object $user): array
    {
        //获取菜单树形
        $menuList = Permission::getUserMenuList($user);
        $permission = Permission::getUserPermissions($user);
        $menuHeader = [];
        foreach ($menuList as $key => $val) {
            if ($val['status'] != 0) {
                $menuHeader[] = [
                    'title' => $val['display_name'],
                    'icon' => $val['icon'],
                    'path' => $val['url'],
                    'name' => $val['name'],
                    'id' => $val['id'],
                    'type' => $val['type'],
                    'sort' => $val['sort'],
                ];
            }
        }
        //排序
        array_multisort(array_column($menuHeader, 'sort'), SORT_ASC, $menuHeader);

        return [
            'menuList' => $menuList,
            'menuHeader' => $menuHeader,
            'permission' => array_column($permission, 'name'),
            'permission_info' => $permission,
        ];
    }

    private static function generateCode()
    {
        $code = getRandStr(6);
        $existCode = User::query()->where('invite_code', $code)->first();
        if ($existCode) {
            self::generateCode();
        }
        return $code;
    }

}
