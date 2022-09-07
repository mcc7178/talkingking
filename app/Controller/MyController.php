<?php

namespace App\Controller;

use App\Constants\StatusCode;
use App\Model\Auth\User;
use App\Model\Chain;
use App\Model\Coin;
use App\Model\Wallet;
use App\Model\WalletLog;
use App\Service\Auth\UserService;
use Psr\Http\Message\ResponseInterface;

class MyController extends AbstractController
{
    /**
     * 我的-基础信息
     * @return ResponseInterface
     */
    public function info()
    {
        $userInfo = UserService::getInstance()->getUserInfoByToken();
        $wallet = Wallet::query()->where('user_id', $userInfo->id)->get()->toArray();

        return $this->success([
            'list' => [
                'id' => $userInfo->id,
                'avatar' => $userInfo->avatar,
                'email' => $userInfo->email,
                'vip_level' => $userInfo->vip_level,
                'invite_code' => $userInfo->invite_code,
            ],
            'wallet' => $wallet
        ]);
    }

    /**
     * 资产管理-资产信息
     * @return ResponseInterface
     */
    public function wallet()
    {
        $type = $this->request->input('type', 0);
        $page = $this->request->input('page', 1);
        $size = $this->request->input('size', 20);
        $userInfo = UserService::getInstance()->getUserInfoByToken();

        $uid = $userInfo->id;
        $offset = ($page - 1) * $size;
        $info = Wallet::query()->where('user_id', $uid)->first();
        $list = WalletLog::query()->where('user_id', $uid)
            ->when($type, function ($query) use ($type) {
                $query->where('type', $type);
            });
        $count = $list->count();
        $list = $list->offset($offset)->limit($size)
            ->orderByDesc('created_at')
            ->get()
            ->each(function ($item) {
                $item->typeDesc = WalletLog::$typeDesc[$item->type] ?? '';
            })->toArray();
        return $this->success([
            'list' => $list,
            'count' => $count,
            'info' => $info ? $info->toArray() : []
        ]);
    }

    /**
     * 资产管理-充值页面
     * @return ResponseInterface
     */
    public function recharge()
    {
        $userInfo = UserService::getInstance()->getUserInfoByToken();
        $coins = Coin::query()->select(['id', 'name', 'icon', 'min_recharge'])->get()->toArray();
        $chains = Chain::query()->get()->toArray();

        return $this->success([
            'coin' => $coins,
            'chain' => $chains,
            'address' => $userInfo->address
        ]);
    }

    /**
     * 经纪人收益
     * @return ResponseInterface
     */
    public function teamProfit()
    {
        $user = UserService::getInstance()->getUserInfoByToken();
        $uid = $user->id;
        $start = date('Y-m-d 00:00:00');
        $end = date('Y-m-d 23:59:59');
        $total = WalletLog::query()->where('user_id', $uid)->where('type', 5)->sum('amount') ?? 0;//经纪人总分润(USDT)
        $today = WalletLog::query()->where('user_id', $uid)->where('type', 5)
                ->whereBetween('created_at', [$start, $end])
                ->sum('amount') ?? 0;//今日总分润
        $profit6 = WalletLog::query()->where('user_id', $uid)->where('type', 6)->sum('amount') ?? 0;//累计直推分润
        $profit7 = WalletLog::query()->where('user_id', $uid)->where('type', 7)->sum('amount') ?? 0;//累计直推分润

        $page = $this->request->input('page', 1);
        $size = $this->request->input('size', 20);
        $date = $this->request->input('date');
        $type = $this->request->input('type', 0);
        if ($type && !in_array($type, [6, 7, 8])) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, 'type error');
        }
        $offset = ($page - 1) * $size;
        $model = WalletLog::query()->where('user_id', $uid)->whereIn('type', [6, 7, 8])
            ->when($type, function ($query) use ($type) {
                $query->where('type', $type);
            })->when($date, function ($query) use ($date) {
                $start = $date . ' 00:00:00';
                $end = $date . ' 23:59:59';
                $query->whereBetween('created_at', [$start, $end]);
            });
        $count = $model->count();
        $list = $model->orderByDesc('created_at')
            ->offset($offset)->limit($size)->get()
            ->each(function ($item) {
                $item->typeDesc = WalletLog::$typeDesc[$item->type] ?? '';
            })
            ->toArray();

        return $this->success([
            'info' => [
                'total' => $total,
                'today' => $today,
                'profit6' => $profit6,
                'profit7' => $profit7
            ],
            'list' => $list,
            'count' => $count
        ]);
    }

    /**
     * 邀请好友
     * @return ResponseInterface
     */
    public function invite()
    {
        $user = UserService::getInstance()->getUserInfoByToken();
        $data = [
            'invite' => $user->invite_code,
            'invite_url' => env('APP_URL') . 'register?invite_code=' . $user->invite_code
        ];
        return $this->success(['list' => $data]);
    }

    /**
     * 我的团队
     * @return ResponseInterface
     */
    public function myTeam()
    {
        $user = UserService::getInstance()->getUserInfoByToken();
        $uid = $user->id;
        $info = [
            'profit' => WalletLog::query()->whereIn('type', [5, 6, 7, 8])->where('user_id', $uid)->sum('amount') ?? 0,
            'vip_level' => $user->vip_level,
            'team_num' => 1,
            'invite_num' => User::query()->where('invite_user', $uid)->count(),
            'team_amount' => 1
        ];
        $page = $this->request->input('page', 1);
        $size = $this->request->input('size', 20);
        $offset = ($page - 1) * $size;
        $model = User::query()->where('invite_user', $uid);
        $count = $model->count();
        $list = $model->select(['id', 'email', 'vip_level'])
            ->offset($offset)->limit($size)
            ->orderByDesc('created_at')
            ->get()
            ->each(function ($item) {
                $item->amount = WalletLog::query()->where('user_id', $item->id)->where('type', 3)->sum('amount') ?? 0;
            })
            ->toArray();
        return $this->success([
            'info' => $info,
            'list' => $list,
            'count' => $count
        ]);
    }
}