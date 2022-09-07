<?php

namespace App\Controller;

use App\Foundation\Utils\Mail;
use App\Model\Chain;
use App\Model\Coin;

class CommonController extends AbstractController
{
    public static $types = [
        ['type' => 6, 'name' => '直推分润',],
        ['type' => 7, 'name' => '管理分润',],
        ['type' => 8, 'name' => '平级分润',],
    ];

    public static $walletTypes = [
        ['type' => 1, 'name' => '充值',],
        ['type' => 2, 'name' => '提现',],
        ['type' => 3, 'name' => '参与',],
        ['type' => 4, 'name' => '赎回',],
        ['type' => 5, 'name' => '分润',],
        ['type' => 6, 'name' => '直推分润',],
        ['type' => 7, 'name' => '管理分润',],
        ['type' => 8, 'name' => '平级分润',],
    ];

    public function coins()
    {
        $list = Coin::query()->get()->toArray();
        return $this->success([
            'list' => $list
        ]);
    }

    public function chains()
    {
        $list = Chain::query()->get()->toArray();
        return $this->success([
            'list' => $list
        ]);
    }

    public function types()
    {
        return $this->success([
            'list' => self::$types
        ]);
    }

    public function walletType()
    {
        return $this->success([
            'list' => self::$walletTypes
        ]);
    }
}