<?php

namespace App\Controller;

use App\Constants\StatusCode;
use App\Model\Coin;
use App\Model\InvestmentLog;
use App\Model\Order;
use App\Model\Product;
use App\Model\RedeemLog;
use App\Model\TeamProfitSetting;
use App\Model\UserLevelLog;
use App\Model\Wallet;
use App\Model\WalletLog;
use App\Service\Auth\UserService;
use Hyperf\DbConnection\Db;
use Psr\Http\Message\ResponseInterface;

class ProductController extends AbstractController
{
    public function list()
    {
        $params = $this->request->all();
        $join_type = $params['join_type'] ?? 0;
        $size = $params['size'] ?? 10;
        $page = $params['page'] ?? 1;

        $userInfo = UserService::getInstance()->getUserInfoByToken();
        $uid = $userInfo->id;
        $list = Product::getList($uid, $join_type, (int)$page, (int)$size);
        return $this->success($list);
    }

    public function detail()
    {
        $pid = $this->request->input('product_id');
        $type = $this->request->input('type');
        $page = $this->request->input('page', 1);
        $size = $this->request->input('size', 20);

        if (!in_array($type, ['profit', 'buy', 'redeem'])) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.type_error'));
        }
        $userInfo = UserService::getInstance()->getUserInfoByToken();
        $uid = $userInfo->id;
        $list = Product::getLogs($pid, $uid, $type, $page, $size);
        $data = [
            'info' => Product::getInfo($pid, $uid),
            'list' => $list['list'],
            'count' => $list['count']
        ];
        return $this->success($data);
    }


    /**
     * 投资-基础信息
     * @param int $id
     * @return ResponseInterface
     */
    public function buyBalance(int $id)
    {
        $userInfo = UserService::getInstance()->getUserInfoByToken();
        $uid = $userInfo->id;
        $product = Product::query()->findOrFail($id);
        $info = [
            'profit_days' => $product->profit_days ?? 0,
            'coin' => $product->coin,
            'icon' => Coin::query()->where('id', $product->coin)->value('icon') ?? "",
            'remaining_amount' => $product->remaining_amount,
            'balance' => Wallet::query()->where('user_id', $uid)->where('coin', $product->coin)->value('available') ?? 0,
        ];
        return $this->success($info);
    }

    /**
     * 购买
     * @return ResponseInterface
     */
    public function buy()
    {
        bcscale(8);
        $params = [
            'id' => $this->request->input('id'),
            'amount' => $this->request->input('amount'),
            'security_code' => $this->request->input('security_code'),
            'is_accepted' => $this->request->input('is_accepted'),
        ];
        $rules = [
            'id' => 'required',
//            'amount' => 'required|min:10000',
            'security_code' => 'required',
            'is_accepted' => 'accepted',
        ];
        $message = [
            'id.required' => __('validation.required'),
//            'amount.required' => 'amount is required',
            'amount.min' => __('validation.amount_gt'),
            'security_code.required' => __('validation.required'),
            'is_accepted.required' => __('validation.accepted'),
        ];
        $this->verifyParams($params, $rules, $message);
        $userInfo = UserService::getInstance()->getUserInfoByToken();
        $uid = $userInfo->id;
        if ($params['amount'] % 10000 != 0) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('valiadtion.amount_multi'));
        }
        if ($userInfo->status == 0) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.account_null'));
        }
        if (!$userInfo->security_code) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.security_code_empty'));
        }
        if ($userInfo->security_code != md5($params['security_code'])) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.security_code_error'));
        }

        $amount = $params['amount'];
        $product = Product::query()->where('id', $params['id'])->firstOrFail();
        $balance = Wallet::query()->where('user_id', $uid)->where('coin', $product->coin)->value('available') ?? 0;
        $coins = Coin::query()->get()->keyBy('id')->toArray();
        $coin = $coins[$product->coin] ?? '';
        //产品状态判断 todo
        if ($product->status == 0) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.product_disable'));
        }
        if ($product->status == 2) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.product_full'));
        }
        if ($amount > $balance) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.balance_amount', ['amount' => $balance]));
        }
        if ($amount > $product->remaining_amount) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.product_balance', ['amount' => $product->remaining_amount]));
        }

        Db::beginTransaction();
        try {
            //新增投资订单信息
            $pid = $product->id;
            $time = date('Y-m-d H:i:s');
            $order = Order::query()->where('user_id', $uid)->where('product_id', $pid)->first();
            $status = 1;
            if ($order) {
                if (in_array($order->status, [2, 3])) {
                    $status = 2;
                }
            }

            //新增/更新订单数据
            $data = [
                'status' => $status,
                'user_account' => $userInfo->email,
                'investment_amount' => $order ? bcadd($amount, $order->investment_amount) : $amount,
                'available_amount' => $order ? bcadd($amount, $order->available_amount) : $amount,
                'product_name' => $product->product_name,
                'created_at' => $time,
                'updated_at' => $time,
            ];
            $orderModel = Order::updateOrCreate([
                'user_id' => $uid,
                'product_id' => $pid
            ], $data);
            $oid = $orderModel->id;

            //新增投资记录表
            InvestmentLog::addLog($oid, $pid, $uid, $amount);

            //扣减用户资产
            $wallet = Wallet::query()->where('user_id', $uid)->where('coin', $product->coin)->first();
            $wallet->available = bcsub($wallet->available, $amount);
            $wallet->save();

            //新增用户资产日志记录数据
            WalletLog::addLog($oid, $uid, 3, $amount, 1, $product->coin);

            //更新产品数据
            $product->participated_amount = bcadd($product->participated_amount, $amount, 8);
            $product->remaining_amount = bcsub($product->remaining_amount, $amount, 8);
            $product->save();

            //用户级别变更
            $total_amount = Order::query()->where('user_id', $uid)->sum('available_amount') ?? 0;
            $setting = TeamProfitSetting::query()
                ->where('max_amount', '<=', $total_amount)
                ->orderByDesc('max_amount')
                ->first();
            if ($setting && $setting->level != $userInfo->vip_level) {
                UserLevelLog::insert([
                    'user_id' => $uid,
                    'order_id' => $oid,
                    'product_id' => $pid,
                    'before_level' => $userInfo->vip_level,
                    'current_level' => $setting->level,
                    'created_at' => $time
                ]);
                $userInfo->vip_level = $setting->level;
                $userInfo->save();
            }

            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollBack();
            $this->throwExp(StatusCode::ERR_EXCEPTION, $exception->getMessage() . ',line:' . $exception->getLine());
        }

        return $this->successByMessage(__('validation.success'));
    }

    /**
     * 赎回
     * @return ResponseInterface|void
     */
    public function redeem()
    {
        bcscale(8);
        $params = [
            'id' => $this->request->input('id'),
            'amount' => $this->request->input('amount'),
            'security_code' => $this->request->input('security_code'),
            'is_accepted' => $this->request->input('is_accepted'),
        ];
        $rules = [
            'id' => 'required',
            'amount' => 'required',
            'security_code' => 'required',
            'is_accepted' => 'accepted',
        ];
        $message = [
            'id.required' => __('validation.required'),
            'amount.required' => __('validation.required'),
            //'amount.min' => 'amount must great than 10000',
            'security_code.required' => __('validation.required'),
            'is_accepted.required' => __('validation.accepted'),
        ];
        $this->verifyParams($params, $rules, $message);
        $id = $params['id'];
        $amount = $params['amount'];
        if ($amount % 10000 != 0) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.amount_multi'));
        }
        $userInfo = UserService::getInstance()->getUserInfoByToken();
        $uid = $userInfo->id;
        $order = Order::query()->where('product_id', $id)->where('user_id', $uid)->first();
        if ($amount > $order->available_amount) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.amount_multi'));
        }
        if ($userInfo->status == 0) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.account_null'));
        }
        if (!$userInfo->security_code) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.security_code_empty'));
        }
        if ($userInfo->security_code != md5($params['security_code'])) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, __('validation.security_code_error'));
        }

        //更新订单数据
        //1进行中,2部分赎回,3全部赎回,4已赎回
        Db::beginTransaction();
        try {
            $status = 1;
            if (($order->available_amount - $amount) > 0) {
                $status = 2;
            }
            if (($order->available_amount - $amount) == 0) {
                $status = 3;
            }
            $order->freeze_amount = bcadd($order->freeze_amount, $amount);
            $order->available_amount = bcsub($order->available_amount, $amount);
            $order->status = $status;
            $order->save();

            //新增赎回记录
            $rid = RedeemLog::addLog($order->id, $id, $uid, $amount);

            //新增钱包日志记录
            $coin = Product::query()->where('id', $order->product_id)->value('coin') ?? 0;
            WalletLog::addLog($rid, $uid, 4, $amount, 0, $coin);
            Db::commit();
            return $this->successByMessage(__('validation.success'));
        } catch (\Throwable $exception) {
            Db::rollBack();
            $this->throwExp(StatusCode::ERR_EXCEPTION, $exception->getMessage() . ',line:' . $exception->getLine());
        }
    }

    /**
     * 赎回-基础信息
     * @param $id
     * @return ResponseInterface
     */
    public function redeemBalance($id)
    {
        $userInfo = UserService::getInstance()->getUserInfoByToken();
        $uid = $userInfo->id;
        $product = Product::query()->findOrFail($id);
        $order = Order::query()->where('product_id', $id)->where('user_id', $uid)->first();
        $info = [
            'amount' => $order->available_amount,
            'profit_days' => $product->profit_days ?? 0,
        ];

        return $this->success($info);
    }
}