<?php

namespace App\Model;

use App\Model\Auth\User;

class RedeemLog extends Model
{
    const UPDATED_AT = NULL;
    protected $table = 'redeem_log';
    protected $guarded = [];
    protected $dates = [
        'created_at',
        'updated_at',
        'audit_at'
    ];

    public static function addLog($order_id, $product_id, $user_id, $amount): int
    {
        $product = Product::query()->findOrFail($product_id);
        return self::insertGetId([
            'order_id' => $order_id,
            'product_id' => $product_id,
            'product_name' => $product->product_name,
            'user_id' => $user_id,
            'email' => User::query()->where('id', $user_id)->value('email') ?? '',
            'amount' => $amount,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
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
                $item->statusDesc = $item->status == 0 ? '赎回中' : '已赎回';
                unset($item['id'], $item['order_id'], $item['product_id'], $item['user_id'], $item['status']);
            })
            ->toArray();
        return [
            'count' => $count,
            'list' => $list
        ];

    }
}