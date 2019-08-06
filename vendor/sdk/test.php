<?php

require ('Client.php');
define('OMS_SERVICE_HOST', 'https://omstest.b2c.omnixdb.com'); # dev
//define('OMS_SERVICE_HOST', 'https://oms.b2c.omnixdb.com');  # prod

$order_id = $argv[1]?:1;    # 订单内部id ..
$option = ['token'=>'BC'];  # token 必传固定参数'BC'
$order_client = \refund\Push::_instance($option);
$ret = $order_client->push($order_id);

if( json_decode($ret,true)['code'] != 0 ){
    sleep( 20 );
    $order_client->push($order_id);
}

/**
 * 返回值 code == 0  成功 ..
 *
 * 其它失败  错误信息 msg
 *
 * code == 301 需要重推.
 */
print_r($ret);