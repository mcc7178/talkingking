<?php

namespace App\Model;

class Order extends Model
{
    protected $table = 'orders';
    protected $guarded = [];

    public static $typeDesc = [
        1 => '进行中',
        2 => '部分赎回中',
        3 => '全部赎回中',
        4 => '已赎回',
    ];
}