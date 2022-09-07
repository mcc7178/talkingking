<?php
declare(strict_types=1);

/**
 * 路由控制中心
 */

use App\Controller\Auth\LoginController;
use App\Controller\CircularController;
use App\Controller\CommonController;
use App\Controller\HomeController;
use App\Controller\MyController;
use App\Controller\ProductController;
use App\Controller\SecurityCenter;
use App\Controller\TestController;
use App\Controller\WithdrawAddressController;
use App\Controller\WithdrawController;
use App\Middleware\RequestMiddleware;
use Hyperf\HttpServer\Router\Router;

Router::addGroup('/api/', function () {
    Router::addGroup('auth/', function () {
        Router::post('register', [LoginController::class, 'register']);
        Router::post('login', [LoginController::class, 'login']);
        Router::post('reset_login_password', [LoginController::class, 'reset_login_password']);
        Router::post('send_email', [LoginController::class, 'send_email']);
    });
});
Router::addGroup('/api/', function () {
    //产品
    Router::addGroup('product/', function () {
        Router::get('list', [ProductController::class, 'list']);
        Router::get('detail', [ProductController::class, 'detail']);
        Router::post('buy', [ProductController::class, 'buy']);
        Router::get('buy/balance/{id}', [ProductController::class, 'buyBalance']);
        Router::post('redeem', [ProductController::class, 'redeem']);
        Router::get('redeem/balance/{id}', [ProductController::class, 'redeemBalance']);
    });

    //公告
    Router::get('circular', [CircularController::class, 'list']);
    Router::get('circular/info/{id}', [CircularController::class, 'info']);

    //首页
    Router::get('home', [HomeController::class, 'list']);

    //我的
    Router::get('user/info', [MyController::class, 'info']);
    Router::get('user/wallet', [MyController::class, 'wallet']);
    Router::get('user/recharge', [MyController::class, 'recharge']);
    Router::get('user/teamprofit', [MyController::class, 'teamProfit']);
    Router::get('user/invite', [MyController::class, 'invite']);
    Router::get('user/team', [MyController::class, 'myTeam']);

    //提现
    Router::get('withdraw', [WithdrawController::class, 'list']);
    Router::get('withdraw/detail/{id}', [WithdrawController::class, 'detail']);
    Router::post('withdraw', [WithdrawController::class, 'commit']);
    Router::get('withdraw/balance', [WithdrawController::class, 'balance']);
    Router::get('withdraw/commission', [WithdrawController::class, 'commissionCalc']);

    //安全中心
    Router::get('security/base', [SecurityCenter::class, 'baseInfo']);
    Router::get('security/info', [SecurityCenter::class, 'info']);
    Router::post('security/update', [SecurityCenter::class, 'update']);
    Router::post('security/reset_login_password', [SecurityCenter::class, 'resetLoginPassword']);
    Router::post('security/set_security_code', [SecurityCenter::class, 'setSecurityCode']);

    //提现地址管理
    Router::get('address', [WithdrawAddressController::class, 'list']);
    Router::get('address/{id}', [WithdrawAddressController::class, 'info']);
    Router::post('address', [WithdrawAddressController::class, 'add']);
    Router::put('address/{id}', [WithdrawAddressController::class, 'update']);
    Router::post('address/delete/{id}', [WithdrawAddressController::class, 'delete']);

    //公共接口
    Router::get('common/chain', [CommonController::class, 'chains']);
    Router::get('common/coin', [CommonController::class, 'coins']);
    Router::get('common/type', [CommonController::class, 'types']);
    Router::get('common/wallettype', [CommonController::class, 'walletType']);
}, ['middleware' => [RequestMiddleware::class]]);

Router::get('/api/test1', [TestController::class, 'index']);
Router::get('/api/mail', [CommonController::class, 'mail']);

//邀请注册
Router::get('register', [LoginController::class, 'register']);
