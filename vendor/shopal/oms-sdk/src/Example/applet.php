<?php

require __DIR__ . '/../../vendor/autoload.php';

use OMS\Sdk\Oms;

$oms = new Oms('https://chen.b2c.omnixdb.com', 'cb');

////# 订单推送
//$client = new \OMS\Sdk\Applet\OrderCreate();
//$client->setId([
//    433, 434
//]);

# 订单修改地址

$client = new \OMS\Sdk\Applet\OrderModify();

$client->setTid('20190712081900001')
    ->setReceiverMobile('13988880001')
    ->setReceiverAddress('新地址')
    ->setReceiverName('姓名')
//    ->setCardName('cardname')
//    ->setCardNo('cardno')
    ->setReceiverZip('zip')
//    ->setProvinceName('s')
    ->setCityName('c')->setDistrictName('d');

////# 售后单推送
//$client = new \OMS\Sdk\Applet\RefundSocket();
//$client->setId(99);
//
//$res = $oms->getRawResponse($client);
//
//var_dump($res);


# 员工数据

//$client = new \OMS\Sdk\Applet\Employee();


$res = $oms->getRawResponse($client);
print_r($res);
