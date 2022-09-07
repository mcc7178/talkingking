<?php

namespace App\Model;

use Hyperf\DbConnection\Db;

class Product extends Model
{
    protected $table = 'product';

    protected $guarded = [];

    /**
     * 定义状态枚举
     */
    public static $statusDesc = [
        0 => '未开放',
        1 => '进行中',
        2 => '已满额',
        3 => '已结束',
    ];

    /**
     * 获取产品列表
     * @param int $uid
     * @param int $status
     * @param int $page
     * @param int $size
     * @return array
     */
    public static function getList(int $uid, int $status, int $page = 1, int $size = 10)
    {
        Db::enableQueryLog();
        $model = self::query()
            ->with([
                'coin' => function ($query) {
                    $query->select(['id', 'name']);
                }
            ])->where('is_show', 1);
        switch ($status) {
            //0-可投资
            case 0:
                $model->where('remaining_amount', '>', 0);
                break;
            //1-已参与-进行中
            case 1:
                $pids = Order::query()->whereIn('status', [1, 2, 3])->where('user_id', $uid)->pluck('product_id')->toArray();
                $model->whereIn('id', $pids);
                break;
            //2-已参与-已赎回
            case 2:
                $pids = Order::query()->where('status', 4)->where('user_id', $uid)->pluck('product_id')->toArray();
                $model->whereIn('id', $pids);
                break;
        }
        $offset = ($page - 1) * $size;
        $count = $model->count();
        $list = $model
            ->orderByDesc('remaining_amount')
            ->offset($offset)
            ->limit($size)
            ->get()
            ->each(function ($item) use ($uid) {
                $order = Order::query()->where('user_id', $uid)->where('product_id', $item->id)->first();
                $item->schedule = sprintf('%.2f', sprintf('%f', $item->participated_amount / $item->total_amount) * 100);
                $item->orderStatus = '';
                $item->statusDesc = self::$statusDesc[$item->status] ?? '';
                $item->profit = $order->profit ?? 0;
                $item->profit_rate = $order->profit_rate ?? 0;
                return $item;
            })
            ->toArray();
        $amount = Order::query()->where('user_id', $uid)->sum('investment_amount') ?? 0;
        return [
            'list' => $list,
            'count' => $count,
            'amount' => $amount,
        ];
    }

    public static function getInfo($id, $uid)
    {
        $info = self::query()->with([
            'coin' => function ($query) {
                $query->select(['id', 'name', 'icon']);
            }
        ])
            ->where('id', $id)
            ->firstOrFail()
            ->toArray();
        $info['num'] = Order::query()->where('product_id', $id)->count();
        $info['schedule'] = sprintf('%.2f', sprintf('%f', $info['participated_amount'] / $info['total_amount']) * 100);
        $info['status0'] = RedeemLog::query()->where('status', 0)->where('product_id', $id)->where('user_id', $uid)->sum('amount') ?? 0;
        $info['status1'] = RedeemLog::query()->where('status', 1)->where('product_id', $id)->where('user_id', $uid)->sum('amount') ?? 0;
        return $info;
    }

    public static function getLogs($id, $uid, $type, $page = 1, $size = 20)
    {
        switch ($type) {
            //收益明细
            case 'profit':
                return ProfitLog::getList(0, $uid, $id, $page, $size);
            //参与记录
            case 'buy':
                return InvestmentLog::getList(0, $uid, $id, $page, $size);
            //赎回记录
            case 'redeem':
                return RedeemLog::getList(0, $uid, $id, $page, $size);
        }
    }

    public function coin()
    {
        return $this->belongsTo(Coin::class, 'coin');
    }
}