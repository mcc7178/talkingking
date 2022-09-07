<?php

declare(strict_types=1);

namespace App\Model\Auth;

use App\Model\Model;
use Hyperf\Database\Model\Builder;

class User extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user';

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'default';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];

    protected $guarded = [];

    /**
     * 定义状态枚举
     */
    const STATUS_ON = 1;
    const STATUS_OFF = 0;

    public static function getInfoByEmail(string $email)
    {
        return self::query()->where('email', $email)->first();
    }

    /**
     * 根据用户ID获取用户信息
     * @param $id
     * @return array|Builder|\Hyperf\Database\Model\Model|object|null
     */
    static function getOneByUid($id)
    {
        if (empty($id)) return [];

        $query = static::query();
        $query = $query->where('id', $id);

        return $query->first();
    }
}