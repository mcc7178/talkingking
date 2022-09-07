<?php

namespace App\Model;

class Withdraw extends Model
{
    protected $table = 'withdraw';

    public static $statusDesc = [
        0 => '待审核',
        1 => '已完成',
        2 => '已撤销',
        -1 => '放行失败'
    ];

    public function coin()
    {
        return $this->belongsTo(Coin::class, 'coin');
    }

    public function chain()
    {
        return $this->belongsTo(Chain::class, 'chain');
    }
}