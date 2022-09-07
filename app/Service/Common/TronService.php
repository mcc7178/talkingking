<?php

namespace App\Service\Common;

use App\Constants\StatusCode;
use App\Service\BaseService;
use GuzzleHttp\Client;
use IEXBase\TronAPI\TronAwareTrait;
use Tron\Api;
use Tron\Exceptions\TronErrorException;
use Tron\TRX;

class TronService extends BaseService
{
    use TronAwareTrait;

    private $uri;
    private $trxWallet;

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

    public function generateAddress()
    {
        try {
            $api = new Api(new Client(['base_uri' => $this->uri]));
            $this->trxWallet = new TRX($api);
            return $this->trxWallet->generateAddress();
        } catch (TronErrorException $e) {
            $this->throwExp(StatusCode::ERR_EXCEPTION, $e->getMessage() . ',line:' . $e->getLine());
        }
    }
}