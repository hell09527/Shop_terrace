<?php

require __DIR__ . '/../../vendor/autoload.php';

use OMS\Sdk\Oms;

$oms = new Oms('https://chen.b2c.omnixdb.com', 'cb');


# 订单推送
$client = new \OMS\Sdk\Pospal\OrderCreate();
$client->setStoreCode('VS0001')
    ->setSn('201907081129007120001');

$res = $oms->getRawResponse($client);


var_dump($res);


