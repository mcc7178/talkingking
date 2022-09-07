<?php

namespace App\Model;

class WalletLog extends Model
{
    protected $table = 'wallet_log';
    const UPDATED_AT = NULL;
    protected $guarded = [];
    protected $dates = [
        'created_at',
        'updated_at',
        'audit_at'
    ];

    public static $typeDesc = [
        1 => '充值',
        2 => '提现',
        3 => '参与',
        4 => '赎回',
        5 => '分润',
        6 => '直推分润',
        7 => '管理分润',
        8 => '平级分润',
    ];

    public static function addLog($source_id, $user_id, $type, $amount, $status = 1,$coin = 1): bool
    {
        $model = new self();
        $model->source_id = $source_id;
        $model->user_id = $user_id;
        $model->type = $type;
        $model->amount = $amount;
        $model->status = $status;
        $model->coin = $coin;
        return $model->save();
    }
}