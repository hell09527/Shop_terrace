<?php

require __DIR__ . '/../../vendor/autoload.php';

use OMS\Sdk\Oms;

$oms = new Oms('https://chen.b2c.omnixdb.com', '爱库存');

$client = new \OMS\Sdk\Joard\OrderCreate();

$tid = '2019070700000'.random_int(999, 9999);

$client->setSellerNick('爱库存自营店')
    ->setTid($tid)
    ->setIsDelivery(0)
    ->setTxType(1)
    ->setCreated(date('Y-m-d H:i:s',time()))
    ->setPayTime(date('Y-m-d H:i:s',time()))
    ->setEstConTime('')
    ->setConsignTime('')
    ->setPayment(120.50)
    ->setPostFee(20)
    ->setDiscountFee(0)
    ->setOrderTaxFee(0)
    ->setPromotionDetails([
        [
            'discount_fee'   => 1.00,
            'promotion_desc' => '优惠详情',
            'promotion_name' => '优惠名称',
        ],
    ])
    ->setBuyerNick('买家昵称')
    ->setBuyerEmail('four.li@yaya.com')
    ->setBuyerMessage('发顺丰啊')
    ->setSellerMemo('我就不发顺丰')
    ->setReceiverName('脚德先森')
    ->setReceiverState('上海')
    ->setReceiverCity('上海市')
    ->setReceiverDistrict('长宁区')
    ->setReceiverAddress('T135楼设计部Yaya')
    ->setReceiverMobile(13900012201)
    ->setReceiverPhone('')
    ->setCardNo('42012313131313123131')
    ->setCardName('李四')
    ->setPayType(2)
    ->setPayFlowNo('20192381321300000123131')
    ->setOrderItems([
        [
            'product_code'      => 'FBAV092',
            'title'             => 'Aveeno 婴儿每日倍护洗发沐浴露354ml+成人舒缓柔嫩沐浴露 532ml- 大贸',
            'is_gift'           => 0,
            'discount_fee'      => 0,
            'adjust_fee'        => 0,
            'estimate_con_time' => '',
            'logistics_company' => '',
            'invoice_no'        => '',
            'sku_prop'          => '一大瓶 30毫升',
            'consign_time'      => '',
            'num'               => 1,
            'price'             => 50,
        ],
    ]);

$res = $oms->getRawResponse($client);

var_dump($res);

echo '推送订单:'. $tid;
