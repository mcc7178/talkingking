<?php

namespace App\Service\Common;

use GuzzleHttp\Client;
use IEXBase\TronAPI\TronAwareTrait;
use Tron\Api;
use Tron\TRC20;

class WithdrawService extends \App\Service\BaseService
{
    use TronAwareTrait;

    private $uri;
    private $trxWallet;
    private $address = 'TXrg8xtYXo1EkztbjQm3rjfwtqtirqFT8f';
    private $toAddress = 'TNQKRfNNA9vwDVp3EzdhFA9Mwgmy1fn9Y1';
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

    private function getTRC20(): TRC20
    {
        $api = new Api(new Client(['base_uri' => $this->uri]));
        $config = self::CONTRACT;
        return new TRC20($api, $config);
    }

    public function withdrawHandle()
    {

    }
}