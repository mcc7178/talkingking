<?php

namespace App\Task;

use App\Foundation\Facades\Log;
use App\Foundation\Utils\Mail;
use App\Model\Withdraw;
use App\Pool\Redis;
use GuzzleHttp\Client;
use Hyperf\DbConnection\Db;
use IEXBase\TronAPI\TronAwareTrait;
use Tron\Address;
use Tron\Api;
use Tron\TRC20;

class TrxWithdrawTask
{
    use TronAwareTrait;

    private $uri;
    private $address = 'TXrg8xtYXo1EkztbjQm3rjfwtqtirqFT8f';
    private $privateKey = 'b5f7a7b7b5698bc876de8de3c3b13fc313861b73bcc12556c6a89d39f3bde8c0';
    const CONTRACT = [
        'contract_address' => 'TA3ghA6ZYWdwxkAQqyCEF2cjabqaR8yom9', // USDT TRC20
        'decimals' => 6,
    ];
    private $balance = 1;
    private $email = '836733916@qq.com';

    private function getTRC20(): TRC20
    {
        /*if (env('APP_ENV' == 'product')) {
            $this->uri = 'https://api.trongrid.io';// mainnet
        } else {
            $this->uri = 'https://api.shasta.trongrid.io';// shasta testnet
        }*/

        //todo 上线删除
        $this->uri = 'https://api.shasta.trongrid.io';// shasta testnet

        $api = new Api(new Client(['base_uri' => $this->uri]));
        $config = self::CONTRACT;
        return new TRC20($api, $config);
    }

    public function exec()
    {
        bcscale(8);
        $balance = $this->getTRC20()->balance(new Address(
            $this->address,
            '',
            $this->getTRC20()->tron->address2HexString($this->address)
        ));
        Log::codeDebug()->info("余额：$balance");
        if (bccomp($balance, $this->balance, 8) == -1) {
            $redis = Redis::getInstance();
            if ($redis->setnx('balance_email', $balance)) {
                Log::codeDebug()->info("发送充值邮件");
                $redis->expire('balance_email', 3600);
                Mail::init()->setFromAddress('notifynotify@tradingking.vip', 'Tradingking')
                    ->setAddress('836733916@qq.com', '')
                    ->setSubject('重要操作验证码')
                    ->setBody("平台账户余额不足,请及时充值")
                    ->send();
            }
            return true;
        }
        $list = Withdraw::query()->where('status', 3)->get()->toArray();
        if ($list) {
            Log::codeDebug()->info("处理提现数据，总条数：" . count($list));
            foreach ($list as $item) {
                Db::beginTransaction();
                try {
                    $from = $this->getTRC20()->privateKeyToAddress($this->privateKey);
                    $to = new Address(
                        $item['address'],
                        '',
                        $this->getTRC20()->tron->address2HexString($item['address'])
                    );
                    $transferData = $this->getTRC20()->transfer($from, $to, $item['income']);
                    Log::codeDebug()->info("转账...:{$item['income']}");
                    if (!empty($transferData->txID)) {
                        $baseModel = Withdraw::query()->find($item['id']);
                        $baseModel->hash = $transferData->txID;
                        $baseModel->status = 4;
                        $baseModel->save();
                        echo "更新提现数据,id:" . $baseModel->id . PHP_EOL;
                        Log::codeDebug()->info("更新提现数据..." . $baseModel->id);
                    }
                    Db::commit();
                } catch (\Throwable $e) {
                    Db::rollBack();
                    $msg = "error:" . $e->getMessage() . ',file:' . $e->getFile() . ',line:' . $e->getLine();
                    echo $msg . PHP_EOL;
                    Log::codeDebug()->info($msg);
                }
            }
        }
        return true;
    }
}