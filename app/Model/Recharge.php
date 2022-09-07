<?php

namespace App\Model;

class Recharge extends Model
{
    protected $table = 'recharge';
    protected $guarded = [];

    public function coin()
    {
        return $this->belongsTo(Coin::class, 'coin');
    }

    public function chain()
    {
        return $this->belongsTo(Chain::class, 'chain');
    }
}