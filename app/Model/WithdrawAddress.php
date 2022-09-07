<?php

namespace App\Model;

use Hyperf\Database\Model\SoftDeletes;

class WithdrawAddress extends Model
{
    use SoftDeletes;

    protected $table = 'withdraw_address';
}