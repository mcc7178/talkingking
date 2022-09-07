<?php

namespace App\Controller;

use App\Service\Common\TronService;
use App\Task\WithdrawHandleTask;
use GuzzleHttp\Client;
use Hyperf\HttpServer\Contract\RequestInterface;
use IEXBase\TronAPI\TronAwareTrait;
use Tron\Address;
use Tron\Api;
use Tron\TRC20;
use Tron\TRX;

class TestController extends AbstractController
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

    private function getTRX()
    {
        $api = new Api(new Client(['base_uri' => $this->uri]));
        return new TRX($api);
    }

    public function index(RequestInterface $request)
    {
//        $uri = 'https://api.trongrid.io';// mainnet
        $uri = 'https://api.shasta.trongrid.io';// shasta testnet

        $api = new Api(new Client(['base_uri' => $uri]));

        $trxWallet = new TRX($api);
        $addressData = $trxWallet->generateAddress();
        $service = new TronService();
        $k = base64_encode('chaiqiqi:' . $addressData->privateKey);
        return $this->success([
//            'address' => $addressData,
//            'key' => $k,
//            'getResult' => $service->rechargeHandle(),
            //'recharge' => (new TronService())->rechargeHandle(),
//            'withdrawTask' => (new TrxWithdrawTask())->exec(),
            //'getEvent' => $service->getEvent(),
//            'decode' => base64_decode('eVhCZVpDOjB4ZjJlMmU4ZmQ0YmRmZTFiNzg5ZWExZjViMWY2NTI4MzNmNDU2MmMxMTVmMTA0YTE2NjM1Y2NjZGUxM2UxMzJhYQ=='),
//            'dec' => base64_decode('Q2ZmRGR1OjB4ZmQ5ODZlY2YxNzEzODlkZDE1ZGM5Nzk2OGUwODY2MjhlNDg4YjQ5NDJmMWIyNGQ4MTU2ZjljYjMyMGUyNzRlOQ=='),
//            'address2' => $service->generateAddress(),
            'userbalance' => $this->getUserBalance(),
            'platformbalance' => $this->getPlatformBalance(),
//            'withdrawHandleTask' => (new WithdrawHandleTask())->exec()
        ]);
    }

    private function getUserBalance()
    {
        $privateKey = '0xf2e2e8fd4bdfe1b789ea1f5b1f652833f4562c115f104a16635cccde13e132aa';
        $from = $this->getTRC20()->privateKeyToAddress($privateKey);
        $balance = $this->getTRC20()->balance(new Address(
            'TGfvA7DYuuM8gJ8E5Mpjn7Du1DQK3Lz3VZ',
            '',
            $this->getTRC20()->tron->address2HexString('TGfvA7DYuuM8gJ8E5Mpjn7Du1DQK3Lz3VZ')
        ));
        $balance2 = $this->getTRX()->balance($from);
        return [
            'usdt' => $balance,//83994.0799
            'trx' => $balance2,//0.000112--144.447712--
        ];
    }

    private function getPlatformBalance()
    {
        $balance = $this->getTRC20()->balance(new Address(
            $this->address,
            '',
            $this->getTRC20()->tron->address2HexString($this->address)
        ));
        $balance2 = $this->getTRX()->balance(new Address(
            $this->address,
            '',
            $this->getTRC20()->tron->address2HexString($this->address)
        ));
        return [
            'usdt' => $balance,//"13987.9201"
            'trx' => $balance2,//4968.68024--4703.05224
        ];
    }
}