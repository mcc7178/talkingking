<?php

namespace App\Model;

class UserCredentials extends Model
{
    protected $table = 'user_credentials';
    protected $guarded = [];

    const UPDATED_AT = NULL;
    protected $dates = [
        'created_at',
        'audit_at'
    ];

    public static $typeDesc = [
        1 => '身份证',
        2 => '护照',
        3 => '驾照'
    ];

    public static $statusDesc = [
        0 => '未审核',
        1 => '已审核'
    ];
}