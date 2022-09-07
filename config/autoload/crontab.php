<?php

use App\Task\RechargeHandleTask;
use App\Task\TrxRechargeTask;
use App\Task\TrxWithdrawTask;
use App\Task\WithdrawHandleTask;
use Hyperf\Crontab\Crontab;

return [
    //是否开启定时任务
    'enable' => true,
    'crontab' => [
        // Callback类型定时任务（默认）
        (new Crontab())->setName('TrxRechargeTask')
            ->setRule('* */5 * * * *')
            ->setCallback([TrxRechargeTask::class, 'exec'])
            ->setMemo('充值数据处理'),
        (new Crontab())->setName('RechargeHandleTask')
            ->setRule('* */5 * * * *')
            ->setCallback([RechargeHandleTask::class, 'exec'])
            ->setMemo('充值数据处理'),

        (new Crontab())->setName('TrxWithdrawTask')
            ->setRule('* */5 * * * *')
            ->setCallback([TrxWithdrawTask::class, 'exec'])
            ->setMemo('提现处理'),
        (new Crontab())->setName('WithdrawHandleTask')
            ->setRule('* */5 * * * *')
            ->setCallback([WithdrawHandleTask::class, 'exec'])
            ->setMemo('提现处理'),
    ],
];