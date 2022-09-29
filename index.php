<?php

require __DIR__ . '/vendor/autoload.php';

use Web3\Web3;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;
use Web3\Contract;
use Web3p\EthereumTx\Transaction;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

function nft_transfer($contractAddressReceiver, $tokenId, $callback) {
        $web3 = new Web3(new HttpProvider(new HttpRequestManager($_ENV["INFURA_URL"])));
        $eth = $web3->eth;
        $abi = file_get_contents("abi.json");
        $contract = new Contract($web3->provider, $abi);
        $data_hash = $contract->at($_ENV["CONTRACT_ADDRESS"])->getData("transferFrom", $_ENV["ACCOUNT_ADDRESS"], $contractAddressReceiver, $tokenId);
        $eth->getTransactionCount($_ENV["ACCOUNT_ADDRESS"], function ($err, $nonce) use($eth, $contractAddressReceiver, $callback, $data_hash) {
                $nonce = intval($nonce->toString());
                $txParams = [
                        'from' => $_ENV["ACCOUNT_ADDRESS"],
                        'to' => $_ENV["CONTRACT_ADDRESS"],
                        'value' => '0x0',
                        'nonce' => $nonce,
                        'gas' => 600000,
                        'gasPrice' => dechex(60000),
                        'chainId' => dechex(3),
                        'data' => '0x'.$data_hash,
                ];
                $transaction = new Transaction($txParams);
                $signedTransaction = $transaction->sign($_ENV["PRIVATE_KEY"]);
                $trx_hash = '';
                $eth->sendRawTransaction('0x'. $signedTransaction, function ($err, $tx) use(&$trx_hash) {
                        $trx_hash = $tx;
                });
                $stacked = true;
                while($stacked) {
                        $eth->getTransactionCount($_ENV["ACCOUNT_ADDRESS"], function ($err, $trx_num) use($nonce, &$stacked) {
                                $trx_num = intval($trx_num->toString());
                                $stacked = $trx_num == $nonce;
                        });
                }
                $trx_info = $eth->getTransactionByHash($trx_hash, function ($err, $trx_info) use($callback) {
                        $callback($trx_info);
                });
        });

}



$contractAddressReceiver = "0xDc0004A53859b2b77bd3bDa161Ce5B4D234b59c6";
$tokenId = 4;
$callback = function ($trx_info) {
        echo(json_encode($trx_info, JSON_PRETTY_PRINT));
};
nft_transfer($contractAddressReceiver, $tokenId, $callback)

?>