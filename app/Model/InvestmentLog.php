<?php

namespace App\Model;

class InvestmentLog extends Model
{
    const UPDATED_AT = NULL;
    protected $table = 'investment_log';
    protected $guarded = [];

    public static function addLog($order_id, $product_id, $user_id, $amount): bool
    {
        $model = new self();
        $model->order_id = $order_id;
        $model->product_id = $product_id;
        $model->user_id = $user_id;
        $model->amount = $amount;
        return $model->save();
    }

    public static function getList($oid, $uid, $pid, $page, $size)
    {
        $offset = ($page - 1) * $size;
        $model = self::query()
            ->when($oid, function ($query) use ($oid) {
                $query->where('order_id', $oid);
            })->when($uid, function ($query) use ($uid) {
                $query->where('user_id', $uid);
            })->when($pid, function ($query) use ($pid) {
                $query->where('product_id', $pid);
            })->orderByDesc('created_at');
        $count = $model->count();
        $list = $model->offset($offset)
            ->limit($size)
            ->get()
            ->each(function ($item) {
                $item->statusDesc = $item->status == 0 ? '确认中' : '已确认';
                unset($item['id'], $item['order_id'], $item['product_id'], $item['user_id'], $item['status']);
            })
            ->toArray();
        return [
            'count' => $count,
            'list' => $list
        ];

    }
}