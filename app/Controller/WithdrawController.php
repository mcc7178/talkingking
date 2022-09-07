<?php

namespace App\Controller;

use App\Constants\StatusCode;
use App\Model\Coin;
use App\Model\Wallet;
use App\Model\WalletLog;
use App\Model\Withdraw;
use App\Service\Auth\UserService;
use Hyperf\DbConnection\Db;
use Psr\Http\Message\ResponseInterface;

class WithdrawController extends AbstractController
{
    /**
     * 提现列表
     * @return ResponseInterface
     */
    public function list()
    {
        $page = $this->request->input('page', 1);
        $size = $this->request->input('size', 20);

        $offset = ($page - 1) * $size;
        $userInfo = UserService::getInstance()->getUserInfoByToken();
        $model = Withdraw::query()->where('user_id', $userInfo->id);
        $count = $model->count();
        $list = $model
            ->with([
                'coin' => function ($query) {
                    $query->select(['id', 'name', 'icon']);
                }
            ])
            ->offset($offset)->limit($size)
            ->get()
            ->each(function ($item) {
                $item->statusDesc = Withdraw::$statusDesc[$item->status] ?? '';
            })->toArray();
        return $this->success([
            'list' => $list,
            'count' => $count
        ]);
    }

    /**
     * 提现提交
     * @return ResponseInterface
     */
    public function commit()
    {
        $userInfo = UserService::getInstance()->getUserInfoByToken();
        $params = [
            'coin' => $this->request->input('coin'),
            'chain' => $this->request->input('chain'),
            'address' => $this->request->input('address'),
            'amount' => $this->request->input('amount'),
            'security_code' => $this->request->input('security_code'),
            'key_code' => $this->request->input('key_code'),
        ];
        $rules = [
            'coin' => 'required',
            'chain' => 'required',
            'address' => 'required',
            'amount' => 'required|numeric',
            'security_code' => 'required|numeric',
            'key_code' => 'required|numeric',
        ];
        $message = [
            'coin.required' => __('validation.required'),
            'chain.required' => __('validation.required'),
            'address.required' => __('validation.required'),
            'amount.required' => __('validation.required'),
            'amount.numeric' => __('validation.numeric'),
            'security_code.required' => __('validation.required'),
            'security_code.numeric' => __('validation.numeric'),
            'key_code.required' => __('validation.required'),
            'key_code.numeric' => __('validation.numeric'),
        ];

        $uid = $userInfo->id;
        $amount = $params['amount'];
        $this->verifyParams($params, $rules, $message);
        if ($userInfo->withdraw_auth == 0) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.withdraw_forbidden'));
        }
        if ($userInfo->security_code != md5($params['security_code'])) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.security_code_error'));
        }
        $coin = Coin::query()->findOrFail($params['coin']);
        if ($coin->allow_withdraw == 0) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.coin_withdraw_forbidden'));
        }
        if ($amount < $coin->min_withdraw) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.withdraw_min', ['amount' => $coin->min_withdraw]));
        }
        if ($amount > $coin->max_withdraw) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.withdraw_max', ['amount' => $coin->max_withdraw]));
        }

        //24小时提现额度 days_withdraw
        $walletLog = WalletLog::query()->where('user_id', $uid)->where('coin', $params['coin'])->orderByDesc('created_at')->first();
        if ($walletLog) {
            $allow_amount = bcsub($coin->days_withdraw, $walletLog->amount);
        } else {
            $allow_amount = $coin->days_withdraw;
        }
        if ($amount > $allow_amount) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.withdraw_24_hours', ['amount' => $allow_amount]));
        }
        //todo 邮箱验证

        Db::beginTransaction();
        try {
            //用户资产变更
            bcscale(8);
            $wallet = Wallet::query()->where('user_id', $uid)->where('coin', $params['coin'])->firstOrFail();
            $wallet->available = bcsub($wallet->available, $amount);
            $wallet->freeze = bcadd($wallet->freeze, $amount);
            $wallet->save();

            //增加提现日志
            $commission = bcadd(bcmul($amount, bcdiv($coin->commission_rate, 100)), $coin->commission);
            $income = bccomp(bcsub($amount, $commission), 0) == 1 ? bcsub($amount, $commission) : 0;
            $id = Withdraw::insertGetId([
                'user_id' => $uid,
                'email' => $userInfo->email,
                'coin' => $params['coin'],
                'chain' => $params['chain'],
                'quantity' => $amount,
                'commission' => $commission,
                'income' => $income,
                'address' => $params['address'],
                'hash' => '',
                'status' => 0,//待审核
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            //增加钱包流水
            WalletLog::addLog($id, $uid, 2, $amount, 0, $params['coin']);
            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollBack();
            $this->throwExp(StatusCode::ERR_EXCEPTION, $exception->getMessage() . ',line:' . $exception->getLine());
        }

        return $this->successByMessage(__('validation.success'));
    }

    /**
     * 提现详情
     * @param int $id
     * @return ResponseInterface
     */
    public function detail(int $id)
    {
        $userInfo = UserService::getInstance()->getUserInfoByToken();
        $info = Withdraw::query()->with([
            'coin' => function ($query) {
                $query->select(['id', 'name', 'icon']);
            },
            'chain'
        ])->where('user_id', $userInfo->id)->findOrFail($id);
        $info['statusDesc'] = Withdraw::$statusDesc[$info['status']] ?? '';
        return $this->success([
            'list' => $info
        ]);
    }

    /**
     * 提现余额
     * @return ResponseInterface
     */
    public function balance()
    {
        $coin = $this->request->input('coin', 0);
        if (!$coin) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, 'coin is required');
        }
        $userInfo = UserService::getInstance()->getUserInfoByToken();
        $balance = Wallet::query()->where('user_id', $userInfo->id)->where('coin', $coin)->first()->toArray();
        return $this->success([
            'balance' => $balance
        ]);
    }

    /**
     * 手续费计算
     * @return ResponseInterface
     */
    public function commissionCalc()
    {
        bcscale(8);
        $amount = $this->request->input('amount', 0);
        $coin = $this->request->input('coin', 0);
        if (!$amount) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, 'amount is required');
        }
        if (!$coin) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, 'coin is required');
        }
        $coinModel = Coin::query()->findOrFail($coin);
        $commission = bcadd(bcmul($amount, bcdiv($coinModel->commission_rate, 100)), $coinModel->commission);
        $income = bccomp(bcsub($amount, $commission), 0) == 1 ? bcsub($amount, $commission) : 0;
        return $this->success([
            'commission' => $commission,
            'income' => $income
        ]);
    }
}