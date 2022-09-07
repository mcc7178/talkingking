<?php

namespace App\Task;

use App\Foundation\Facades\Log;
use App\Model\TrxWithdrawLog;
use App\Model\Wallet;
use App\Model\WalletLog;
use App\Model\Withdraw;
use GuzzleHttp\Client;
use Hyperf\DbConnection\Db;
use IEXBase\TronAPI\TronAwareTrait;
use Tron\Api;
use Tron\TRC20;

class WithdrawHandleTask
{
    use TronAwareTrait;

    private $uri;
    private $address = 'TXrg8xtYXo1EkztbjQm3rjfwtqtirqFT8f';
    private $privateKey = 'b5f7a7b7b5698bc876de8de3c3b13fc313861b73bcc12556c6a89d39f3bde8c0';
    const CONTRACT = [
        'contract_address' => 'TA3ghA6ZYWdwxkAQqyCEF2cjabqaR8yom9', // USDT TRC20
        'decimals' => 6,
    ];
    private $balance = 100000;
    private $email = '836733916@qq.com';

    public function exec()
    {
        $list = Withdraw::query()->whereIn('status', [-1, 4])
            ->where('hash', '<>', '')
            ->get()->toArray();
        if ($list) {
            foreach ($list as $item) {
                try {
                    $info = $this->getTRC20()->transactionReceipt($item['hash']);
                    Log::codeDebug()->info("获取详情...");
                    if (!empty($info->contractRet) && $info->contractRet == 'SUCCESS') {
                        Log::codeDebug()->info("获取详情成功...{$item['hash']}");
                        $id = TrxWithdrawLog::insertGetId([
                            'user_id' => $item['user_id'],
                            'withdraw_id' => $item['id'],
                            'amount' => $item['income'],
                            'source' => json_encode($info),
                            'status' => 'SUCCESS',
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        echo "记录TRX提现日志,id:$id" . PHP_EOL;
                        Log::codeDebug()->info(__METHOD__ . '记录TRX提现日志,id:' . $id);

                        //更新钱包数据
                        $model = Wallet::query()->where('user_id', $item['user_id'])->where('coin', $item['coin'])->first();
                        $model->freeze = bcsub($model->freeze, $item['income'],8);
                        $model->save();
                        echo "更新钱包数据,id:" . $model->id . PHP_EOL;
                        Log::codeDebug()->info("更新钱包数据..." . $model->id);

                        //更新提现数据d
                        $baseModel = Withdraw::query()->find($item['id']);
                        $baseModel->hash = $item['hash'];
                        $baseModel->status = 1;
                        $baseModel->save();
                        echo "更新提现数据,id:" . $baseModel->id . PHP_EOL;
                        Log::codeDebug()->info("更新提现数据..." . $baseModel->id);

                        //更新钱包流水
                        $walletLogModel = WalletLog::query()->where('source_id', $item['id'])->first();
                        $walletLogModel->status = 1;
                        $walletLogModel->save();
                        echo "更新钱包流水,id:" . $walletLogModel->id . PHP_EOL;
                        Log::codeDebug()->info("更新钱包流水..." . $walletLogModel->id);
                    } else {
                        Log::codeDebug()->info("获取详情失败...");
                        $id = TrxWithdrawLog::insertGetId([
                            'user_id' => $item['user_id'],
                            'withdraw_id' => $item['id'],
                            'amount' => $item['income'],
                            'source' => json_encode($info),
                            'status' => 'FAILED',
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        echo "失败,记录TRX提现日志,id:$id" . PHP_EOL;
                        Log::codeDebug()->info(__METHOD__ . '提现失败,记录TRX提现日志,id:' . $id);

                        //更新提现数据
                        $baseModel = Withdraw::query()->find($item['id']);
                        $baseModel->hash = $item['hash'];
                        $baseModel->status = -1;
                        $baseModel->save();
                        echo "失败,更新提现数据,id:" . $baseModel->id . PHP_EOL;
                        Log::codeDebug()->info("失败,更新提现数据..." . $baseModel->id);
                    }
                    Db::commit();
                } catch (\Throwable $e) {
                    Db::rollBack();
                    $msg = 'error:' . $e->getMessage() . ',file:' . $e->getFile() . ',line:' . $e->getLine();
                    echo $msg . PHP_EOL;
                    Log::codeDebug()->info($msg);
                }
            }
        }
    }

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
}