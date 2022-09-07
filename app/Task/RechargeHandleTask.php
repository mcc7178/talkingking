<?php

namespace App\Task;

use App\Foundation\Facades\Log;
use App\Model\TrxTransferLog;
use App\Pool\Redis;
use GuzzleHttp\Client;
use IEXBase\TronAPI\TronAwareTrait;
use Tron\Address;
use Tron\Api;
use Tron\Exceptions\TransactionException;
use Tron\TRC20;
use Tron\TRX;

class RechargeHandleTask
{
    use TronAwareTrait;

    private $uri;
    private $trxWallet;
    private $toAddress = 'TXrg8xtYXo1EkztbjQm3rjfwtqtirqFT8f';
    private $privateKey = 'b5f7a7b7b5698bc876de8de3c3b13fc313861b73bcc12556c6a89d39f3bde8c0';
    const CONTRACT = [
        'contract_address' => 'TA3ghA6ZYWdwxkAQqyCEF2cjabqaR8yom9', // USDT TRC20
        'decimals' => 6,
    ];

    public function __construct()
    {
        /*if (env('APP_ENV' == 'product')) {
            $this->uri = 'https://api.trongrid.io';// mainnet
        } else {
            $this->uri = 'https://api.shasta.trongrid.io';// shasta testnet
        }*/

        //todo 上线删除
        $this->uri = 'https://api.shasta.trongrid.io';// shasta testnet
    }

    public function exec()
    {
        $redis = Redis::getInstance();
        $data = $redis->rPop('RechargeCollection');
        if ($data) {
            $data = json_decode($data, true);
            $privateKey = $data['privateKey'];
            $amount = $data['amount'];
            $rechargeId = $data['rechargeId'];
            $uid = $data['uid'];
            try {
                //转入归集账户
                $from = $this->getTRC20()->privateKeyToAddress($privateKey);
                $to = new Address(
                    $this->toAddress,
                    '',
                    $this->getTRC20()->tron->address2HexString($this->toAddress)
                );

                //检测用户TRX账户余额
                $this->getTrxBalance($from, $to);
                sleep(30);

                $transferData = $this->getTRC20()->transfer($from, $to, $amount);
                Log::codeDebug()->info(__METHOD__ . '充值金额转入归集账户');

                sleep(5);
                if (!empty($transferData->txID)) {
                    $info = $this->getTRC20()->transactionReceipt($transferData->txID);
                    if (!empty($info->contractRet) && $info->contractRet == 'SUCCESS') {
                        $id = TrxTransferLog::insertGetId([
                            'user_id' => $uid,
                            'recharge_id' => $rechargeId,
                            'amount' => $amount,
                            'source' => json_encode($info),
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        Log::codeDebug()->info(__METHOD__ . '记录TRX转账日志,id:' . $id);
                    }
                }
            } catch (\Throwable $e) {
                $msg = "error:" . $e->getMessage() . ',file:' . $e->getFile() . ',line:' . $e->getLine();
                Log::codeDebug()->info($msg);
                $redis->lPush('RechargeCollection', json_encode($data));
            }
        }
    }

    /**
     * @param Address $from 用户地址
     * @param Address $to 归集地址
     * @return bool
     * @throws TransactionException
     */
    private function getTrxBalance(Address $address, Address $to)
    {
        //bcscale(8);
        $balance = $this->getTRX()->balance($address);
        $balance = number_format($balance, 8, '.', '');
        Log::codeDebug()->info("用户余额:$balance,用户：" . json_encode($address));
        if (bccomp($balance, 10,8) == -1) {
            $from = $this->getTRX()->privateKeyToAddress($this->privateKey);

            $balance = $this->getTRX()->balance($from);
            $balance = number_format($balance, 8, '.', '');
            Log::codeDebug()->info("平台余额:$balance," . json_encode($from));

            $this->getTRX()->transfer($from, $address, 10);
            Log::codeDebug()->info("用户余额不足,转trx10");
        }
        return true;
    }

    private function getTRC20(): TRC20
    {
        $api = new Api(new Client(['base_uri' => $this->uri]));
        $config = self::CONTRACT;
        return new TRC20($api, $config);
    }

    private function getTRX()
    {
        $api = new Api(new Client(['base_uri' => $this->uri]));
        return new TRX($api);
    }
}