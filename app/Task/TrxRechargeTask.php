<?php

namespace App\Task;

use App\Foundation\Facades\Log;
use App\Model\Auth\User;
use App\Model\Recharge;
use App\Model\Wallet;
use App\Model\WalletLog;
use App\Pool\Redis;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Hyperf\DbConnection\Db;
use IEXBase\TronAPI\TronAwareTrait;

class TrxRechargeTask
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
        $this->rechargeHandle();
    }

    /**{
     * "data": [{
     * "block_number": 26147348,
     * "block_timestamp": 1658399601000,
     * "caller_contract_address": "TA3ghA6ZYWdwxkAQqyCEF2cjabqaR8yom9",
     * "contract_address": "TA3ghA6ZYWdwxkAQqyCEF2cjabqaR8yom9",
     * "event_index": 0,
     * "event_name": "Transfer",
     * "result": {
     * "0": "0x71753d1fc89f92cac79b05fc8c83a2e8c364bb6c",
     * "1": "0x4983bf9cdc57e6c752d2bc92823108087a22c70c",
     * "2": "1000000",
     * "from": "0x71753d1fc89f92cac79b05fc8c83a2e8c364bb6c",
     * "to": "0x4983bf9cdc57e6c752d2bc92823108087a22c70c",
     * "value": "1000000"
     * },
     * "transaction_id": "af79abb180c73e018d4e138f79a1c2daaa9e2b96d1682699237a2b83af6a21ec"
     * }],
     * "success": true,
     * "meta": {
     * "fingerprint": "T6atjwJCtWof3oL58g5WtMAZ1RBLKdGsS797m49aoVCFQYUxQqYQnkTw5W88KBjvSkxSD3unR1PJJnhXDTjTV6J556SRua",
     * "at": 1658402405220,
     * "links": {
     *   "next": "https://api.shasta.trongrid.io/v1/contracts/TA3ghA6ZYWdwxkAQqyCEF2cjabqaR8yom9/events?event_name=Transfer&limit=1&only_confirmed=true&fingerprint=T6atjwJCtWof3oL58g5WtMAZ1RBLKdGsS797m49aoVCFQYUxQqYQnkTw5W88KBjvSkxSD3unR1PJJnhXDTjTV6J556SRua"
     *},
     * "page_size": 2
     * }}*/
    /**
     * 获取事件数据
     * @param string $fingerprint
     * @return bool
     * @throws GuzzleException
     */
    public function rechargeHandle(string $fingerprint = '', $timestamp = 0, $url = '', &$num = 0)
    {
        set_time_limit(0);
        if (!$url) {
            if (!$timestamp) {
                $timestamp = strtotime('2022-07-21') . '000';
            }
            $address = self::CONTRACT['contract_address'];
            $url = $this->uri . "/v1/contracts/$address/events?event_name=Transfer&only_confirmed=true&min_block_timestamp=$timestamp&limit=200";
            if ($fingerprint) {
                $url .= '&fingerprint=' . $fingerprint;
            }
            $url .= '&order_by=block_timestamp,desc';
        }

        $redis = Redis::getInstance();
        if ($redis->sIsMember('rechargeUrls', $url)) {
            return true;
        }
        $redis->sAdd('rechargeUrls', $url);

        Log::codeDebug()->info("url:$url");
        try {
            $client = new  Client();
            $response = $client->request('GET', $url, [
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (\Throwable $e) {
            $msg = "error:" . $e->getMessage() . ',file:' . $e->getFile() . ',line:' . $e->getLine();
            Log::codeDebug()->info($msg);
            return true;
        }
        $res = $response->getBody();
        $res = json_decode($res, true);
        //var_dump($res['success']);
        bcscale(8);
        if ($res && $res['success'] && !empty($res['data'])) {
            foreach ($res['data'] as $item) {
                Db::beginTransaction();
                try {
                    if ($item['result']['value'] == 0) {
                        continue;
                    }
                    $base58 = $this->hexString2Address(str_replace('0x', '41', $item['result']['to']));
                    $user = User::query()->where('address', $base58)->first();
                    if ($user) {
                        $hash = $item['transaction_id'];
                        $model = Recharge::query()->where('hash', $hash)->first();
                        if (!$model) {
                            Log::codeDebug()->info("base58:$base58");
                            $amount = bcdiv($item['result']['value'], 1000000, 8);
                            Log::codeDebug()->info("用户:" . $user->email . "，充值金额:" . $amount . "，hash:$hash");
                            $rechargeData = [
                                'user_id' => $user->id,
                                'email' => $user->email,
                                'coin' => 1,
                                'quantity' => $amount,
                                'chain' => 1,
                                'address' => $user->address,
                                'hash' => $hash,
                                'source' => json_encode($item),
                                'status' => 1,
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s'),
                            ];
                            $rechargeId = Recharge::insertGetId($rechargeData);
                            Log::codeDebug()->info(__METHOD__ . ',recharge:' . $rechargeId);

                            //新增钱包流水
                            WalletLog::addLog($rechargeId, $user->id, 1, $amount, 0, 1);
                            Log::codeDebug()->info(__METHOD__ . '新增钱包流水');

                            //变更钱包余额
                            $wallet = Wallet::query()->where('user_id', $user->id)->where('coin', 1)->first();
                            if (!$wallet) {
                                $wallet = new Wallet();
                            }
                            $wallet->user_id = $user->id;
                            $wallet->coin = 1;
                            $wallet->available = bcadd($wallet->available, $amount);
                            $wallet->freeze = bcadd($wallet->freeze, 0);
                            $wallet->total = bcadd($wallet->total, $amount);
                            $wallet->save();
                            Log::codeDebug()->info(__METHOD__ . '变更钱包余额,id:' . $wallet->id);

                            $private = (explode(':', base64_decode($user->privateKey)))[1];
                            $redis->lPush('RechargeCollection', json_encode([
                                'privateKey' => $private,
                                'amount' => $amount,
                                'rechargeId' => $rechargeId,
                                'uid' => $user->id
                            ]));
                        }
                    }
                    Db::commit();
                } catch (\Throwable $e) {
                    Db::rollBack();
                    $msg = "执行错误，回退数据1：" . $e->getMessage() . ',file:' . $e->getFile() . ',line:' . $e->getLine();
                    Log::codeDebug()->info($msg);
                    //$this->throwExp(StatusCode::ERR_EXCEPTION, $msg);
                }
            }
            sleep(20);
            $next = '';
            if (!empty($res['meta']['links']) && $num <= 4) {
                $next = $res['meta']['links']['next'] ?? '';
            }
            $this->rechargeHandle($res['meta']['fingerprint'], $res['data'][count($res['data']) - 1]['block_timestamp'], $next, ++$num);
        }
        return true;
    }
}