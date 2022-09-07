<?php

namespace App\Model;

class ProfitLog extends Model
{
    protected $table = 'profit_log';
    const UPDATED_AT = NULL;

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
                unset($item['id'], $item['order_id'], $item['product_id'], $item['user_id'], $item['status']);
            })
            ->toArray();
        return [
            'count' => $count,
            'list' => $list
        ];

    }
}