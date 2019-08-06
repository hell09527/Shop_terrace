<?php
/**
 * Events.php
 *
 * Niushop商城系统 - 团队十年电商经验汇集巨献!
 * =========================================================
 * Copy right 2015-2025 山西牛酷信息科技有限公司, 保留所有权利。
 * ----------------------------------------------
 * 官方网址: http://www.niushop.com.cn
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用。
 * 任何企业和个人不允许对程序代码以任何形式任何目的再发布。
 * =========================================================
 * @author : niuteam
 * @date : 2015.1.17
 * @version : v1.0.0.0
 */
namespace data\service;
use data\api\IEvents;
use data\model\NsPromotionMansongModel;
use data\model\NsPromotionNeigouGoodsModel;
use data\service\Order;
use data\model\NsOrderModel;
use data\model\NsPromotionMansongGoodsModel;
use data\model\NsPromotionDiscountModel;
use data\model\NsPromotionDiscountGoodsModel;
use data\model\NsGoodsSkuModel;
use data\model\NsGoodsModel;
use data\model\NsCouponModel;
use data\model\NsPromotionGamesModel;
use data\model\NsOrderGoodsModel;
use data\model\NsPromotionMansongRuleModel;
use data\model\NsPromotionGiftModel;
use think\Log;
use data\service\Order as OrderService;
use think\Db;
use data\model\BcDistributorAccountRecordsModel;
use data\model\BcEmployeeModel;
use data\model\BcDistributorModel;
/**
 * 计划任务
 */
class Events implements IEvents{
    /**
     * (non-PHPdoc)
     * @see \data\api\IEvents::giftClose()
     */
    public function giftClose(){

    }
    /**
     * (non-PHPdoc)
     * @see \data\api\IEvents::mansongClose()
     */
    public function mansongOperation(){
        $mansong = new NsPromotionMansongModel();
        $mansong->startTrans();
        try{
            $time = time();
            $condition_close = array(
                'end_time' => array('LT', $time),
                'status'   => array('NEQ', 3)
            );
            $condition_start = array(
                'start_time' => array('ELT', $time),
                'status'   => 0
            );
            $mansong->save(['status' => 4], $condition_close);
            $mansong->save(['status' => 1], $condition_start);
            $mansong_goods = new NsPromotionMansongGoodsModel();
            $mansong_goods->save(['status' => 4], $condition_close);
            $mansong_goods->save(['status' => 1], $condition_start);
            $mansong->commit();
            return 1;
        }catch (\Exception $e)
        {
            $mansong->rollback();
            return $e->getMessage();
        }

    }

    public function giftMansongClose(){
        $mansong = new NsPromotionMansongModel();
        $mansong->startTrans();
        try{
            $condition =array(
                'pmr.gift_id' => array('GT', 0),
                'pm.status' => 1

            );
            // 查询活动状态正常的增品gift_id
            $mansongGift = $mansong->getMansongGift($condition);
            if(!empty($mansongGift)){
                $orderGoods = new NsOrderGoodsModel();
                $promotionGift = new NsPromotionGiftModel();
                $mansong_goods = new NsPromotionMansongGoodsModel();
                foreach ($mansongGift as $k => $v)
                {
                    if(!empty($v['gift_id'])) {
                        if (strpos($v['gift_id'], ',') !== false) {
                            #多个赠品
                            $ids = explode(',', $v['gift_id']);
                            for ($i = 0; $i < count($ids); $i++) {
                                $_ids          = explode(':', $ids[$i]);
                                $where         = ['nog.gift_flag' => $_ids[0], "no.order_status" => array("neq", 5)];
                                $gift_song_num = $orderGoods->getorderGoodsNum($where) ? $orderGoods->getorderGoodsNum($where) : 0;
                                \think\Db::name('ns_promotion_gift')->where(['gift_id' => $_ids[0]])->update(['gift_song_num' => $gift_song_num]);
                                $gift_info = $promotionGift->getInfo(['gift_id' => $_ids[0]], 'gift_num, gift_song_num');
//                                if ($gift_info['gift_song_num'] >= $gift_info['gift_num']) {
                                    $replace_info = \think\Db::name('ns_promotion_gift_replace')->where(['gift_id' => $_ids[0]])->find();
                                    if ($replace_info['n_gift_id'] > 0) {
                                        $where         = ['nog.gift_flag' => $replace_info['n_gift_id'], "no.order_status" => array("neq", 5)];
                                        $new_gift_song_num = $orderGoods->getorderGoodsNum($where) ? $orderGoods->getorderGoodsNum($where) : 0;
                                        \think\Db::name('ns_promotion_gift')->where(['gift_id' => $replace_info['n_gift_id']])->update(['gift_song_num' => $new_gift_song_num]);
//                                        # 有补充赠品的情况。。。
//                                        $arr          = [];
//                                        for($k = 0; $k < count($ids); $k++){
//                                            $_ids_         = explode(':', $ids[$k]);
//                                            if ($_ids_[0] == $replace_info['gift_id']) {
//                                                $str = $replace_info['n_gift_id'].':'. $replace_info['n_num'];
//                                            } else {
//                                                $str = $_ids_[0].':'. $_ids_[1];
//                                            }
//                                            array_push($arr, $str);
//                                        }
//                                        $n_ids            = implode(',', $arr);
//                                        $_rule['gift_id'] = $n_ids;
//                                        \think\Db::name('ns_promotion_mansong_rule')->where(['gift_id' => $v['gift_id'], 'mansong_id' => $v['mansong_id']])->update($_rule);
//                                    } else {
//                                        # 无补充赠品
////                                        $mansong->save(['status' => 4], ['mansong_id' => $v['mansong_id']]);
////                                        $mansong_goods->save(['status' => 4], ['mansong_id' => $v['mansong_id']]);
                                    }
//                                }
                            }
                        } else {
                            #单个赠品
                            $_ids          = explode(':', $v['gift_id']);
                            $where         = ['nog.gift_flag' => $_ids[0], "no.order_status" => array("neq", 5)];
                            $gift_song_num = $orderGoods->getorderGoodsNum($where) ? $orderGoods->getorderGoodsNum($where) : 0;
                            $promotionGift->save(['gift_song_num' => $gift_song_num], ['gift_id' => $_ids[0]]);
                            $gift_info    = $promotionGift->getInfo(['gift_id' => $_ids[0]], 'gift_num, gift_song_num');
//                            if ($gift_info['gift_song_num'] >= $gift_info['gift_num']) {
                                $replace_info = \think\Db::name('ns_promotion_gift_replace')->where(['gift_id' => $_ids[0]])->find();
                                if ($replace_info['n_gift_id'] > 0) {
                                    $where         = ['nog.gift_flag' => $replace_info['n_gift_id'], "no.order_status" => array("neq", 5)];
                                    $new_gift_song_num = $orderGoods->getorderGoodsNum($where) ? $orderGoods->getorderGoodsNum($where) : 0;
                                    $promotionGift->save(['gift_song_num' => $new_gift_song_num], ['gift_id' => $replace_info['n_gift_id']]);
//                                    # 有补充赠品的情况。。。
//                                    $_res['gift_id'] = $replace_info['n_gift_id'].':'.$replace_info['n_num'];
//                                    \think\Db::name('ns_promotion_mansong_rule')->where(['gift_id' => $v['gift_id'], 'mansong_id' => $v['mansong_id']])->update($_res);
//                                } else {
//                                    # 无补充赠品
////                                    $mansong->save(['status' => 4], ['mansong_id' => $v['mansong_id']]);
////                                    $mansong_goods->save(['status' => 4], ['mansong_id' => $v['mansong_id']]);
                                }
//                            }

                        }
                    }
                    }
            }
            $mansong->commit();
            return 1;
        }catch (\Exception $e)
        {
            $mansong->rollback();
            return $e->getMessage();
        }
    }

    /**
     * (non-PHPdoc)
     * @see \data\api\IEvents::ordersClose()
     */
    public function ordersClose(){
        $order_model = new NsOrderModel();

        try{
            $config = new Config();
            $config_info = $config->getConfig(0, 'ORDER_BUY_CLOSE_TIME');
            if(!empty($config_info['value']))
            {
                $close_time = $config_info['value'];
            }else{
                $close_time = 60;//默认1小时
            }
            $time = time()-$close_time*60;//订单自动关闭
            $condition = array(
                'order_status' => 0,
                'create_time'  => array('LT', $time),
                'payment_type' => array('neq', 6)
            );
            $order_list = $order_model->getQuery($condition, 'order_id', '');
            if(!empty($order_list))
            {
                $order = new Order();
                foreach ($order_list as $k => $v)
                {
                    if(!empty($v['order_id']))
                    {
                        $order->orderClose($v['order_id']);
                    }

                }

            }

            //含有限时折扣商品的订单30分钟自动关闭
            $close_time = 30;
            $time = time()-$close_time*60;
            $condition = array(
                'no.order_status' => 0,
                'no.create_time'  => array('LT', $time),
                'no.payment_type' => array('neq', 6)
            );
            $order_list = $order_model->getDiscountOrder($condition);
            if(!empty($order_list))
            {
                $order = new Order();
                foreach ($order_list as $k => $v)
                {
                    if(!empty($v['order_id']))
                    {
                        $order->orderClose($v['order_id']);
                    }

                }

            }

            return 1;
        }catch (\Exception $e)
        {
            return $e->getMessage();
        }

    }
    /**
     * (non-PHPdoc)
     * @see \data\api\IEvents::ordersComplete()
     */
    public function ordersComplete(){
        $order_model = new NsOrderModel();
        try{

            $config = new Config();
            $config_info = $config->getConfig(0, 'ORDER_DELIVERY_COMPLETE_TIME');
            if($config_info['value'] != '')
            {
                $complete_time = $config_info['value'];
            }else{
                $complete_time = 7;//7天
            }
            $time = time()-3600*24*$complete_time;//订单自动完成

            $condition = array(
                'order_status' => 3,
                'pay_status' => 2,
                'sign_time'  => array('LT', $time)
            );
            $order_list = $order_model->getQuery($condition, 'order_id', '');
            if(!empty($order_list))
            {
                $order = new Order();
                foreach ($order_list as $k => $v)
                {
                    if(!empty($v['order_id']))
                    {
                        $order->orderComplete($v['order_id']);
                    }

                }

            }

            return 1;
        }catch (\Exception $e)
        {
            return $e->getMessage();
        }
    }

    /**
     * 礼物订单超过七天未领取订单项自动变为买家申请退款
     *
     * author:Fu
     */
    public function giftOrderGoodsRefundAskfor()
    {
        try{
            $complete_time = 7;//7天
            $time = time()-3600*24*$complete_time;
            $condition = array(
                'no.order_type' => 4,
                'no.order_status' => 11,
                'pay_time'  => array('LT', $time)
            );
            $list = Db::table('ns_order')
                ->alias('no')
                ->join('ns_order_goods nog','no.order_id = nog.order_id','right')
                ->field('nog.order_id, nog.order_goods_id, nog.goods_money')
                ->where($condition)
                ->select();
            $order_service = new OrderService();
            foreach ($list as $k => $v) {
                $retval = $order_service->giftOrderGoodsRefundAskfor($v['order_id'], $v['order_goods_id'], 1, $v['goods_money'], '礼物订单超过七天未领取订单项自动变为买家申请退款');
            }
            return 1;
        }catch(\Exception $e)
        {
            return $e->getMessage();
        }

    }
    /**
     * (non-PHPdoc)
     * @see \data\api\IEvents::discountOperation()
     */
//    public function discountOperation(){
//        $discount = new NsPromotionDiscountModel();
//        $discount->startTrans();
//        try{
//            $time = time();
//            $discount_goods = new NsPromotionDiscountGoodsModel();
//            /************************************************************结束活动**************************************************************/
//            $condition_close = array(
//                'end_time' => array('LT', $time),
//                'status'   => array('NEQ', 3)
//            );
//             $discount->save(['status' => 4], $condition_close);
//             $discount_close_goods_list = $discount_goods->getQuery($condition_close, '*', '');
//             if(!empty($discount_close_goods_list))
//             {
//                 foreach ( $discount_close_goods_list as $k => $discount_goods_item)
//                 {
//                     $goods = new NsGoodsModel();
//
//                     $data_goods = array(
//                         'promotion_type' => 2,
//                         'promote_id'     => $discount_goods_item['discount_id']
//                     );
//                     $goods_id_list = $goods->getQuery($data_goods, 'goods_id', '');
//                     if(!empty($goods_id_list))
//                     {
//                         foreach($goods_id_list as $k => $goods_id)
//                         {
//                             $goods_info = $goods->getInfo(['goods_id' => $goods_id['goods_id']], 'promotion_type,price');
//                             $goods->save(['promotion_price' => $goods_info['price']], ['goods_id'=> $goods_id['goods_id'] ]);
//                             $goods_sku = new NsGoodsSkuModel();
//                             $goods_sku_list = $goods_sku->getQuery(['sku_id'=> $goods_id['sku_id'] ], 'price,sku_id', '');
//                             foreach ($goods_sku_list as $k_sku => $sku)
//                             {
//                                 $goods_sku = new NsGoodsSkuModel();
//                                 $data_goods_sku = array(
//                                     'promote_price' => $sku['price']
//                                 );
//                                 $goods_sku->save($data_goods_sku, ['sku_id' => $sku['sku_id']]);
//                             }
//
//                         }
//
//                     }
//                     $goods->save(['promotion_type' => 0, 'promote_id' => 0], $data_goods);
//
//                 }
//             }
//             $discount_goods->save(['status' => 4], $condition_close);
//             /************************************************************结束活动**************************************************************/
//             /************************************************************开始活动**************************************************************/
//            $condition_start = array(
//                'start_time' => array('ELT', $time),
//                'status'   => 0
//            );
//            //查询待开始活动列表
//            $discount_goods_list = $discount_goods->getQuery($condition_start, '*', '');
//            if(!empty($discount_goods_list))
//            {
//                foreach ( $discount_goods_list as $k => $discount_goods_item)
//                {
//                    $goods = new NsGoodsModel();
//                    $goods_info = $goods->getInfo(['goods_id' => $discount_goods_item['goods_id']],'promotion_type,price');
//                    $data_goods = array(
//                        'promotion_type' => 2,
//                        'promote_id'     => $discount_goods_item['discount_id'],
//                        'promotion_price'  => $goods_info['price'] *$discount_goods_item['discount']/10
//                    );
//                    $goods->save($data_goods,['goods_id' => $discount_goods_item['goods_id']]);
//                    $goods_sku = new NsGoodsSkuModel();
//                    $goods_sku_list = $goods_sku->getQuery(['sku_id'=> $discount_goods_item['sku_id'] ], 'price,sku_id', '');
//                    foreach ($goods_sku_list as $k_sku => $sku)
//                    {
//                        $goods_sku = new NsGoodsSkuModel();
//                        $data_goods_sku = array(
//                            'promote_price' => $sku['price']*$discount_goods_item['discount']/10
//                        );
//                        $goods_sku->save($data_goods_sku, ['sku_id' => $sku['sku_id']]);
//                    }
//                }
//            }
//            $discount_goods->save(['status' => 1], $condition_start);
//            $discount->save(['status' => 1], $condition_start);
//            /************************************************************开始活动**************************************************************/
//            $discount->commit();
//            return 1;
//        }catch (\Exception $e)
//        {
//            $discount->rollback();
//            return $e;
//        }
//    }

    public function discountOperation(){
        $discount = new NsPromotionDiscountModel();
        $discount->startTrans();
        try{
            $time = time();
            $discount_goods = new NsPromotionDiscountGoodsModel();
            /************************************************************结束活动**************************************************************/
            $condition_close = array(
                'end_time' => array('LT', $time),
                'status'   => array('NEQ', 3)
            );
            $discount->save(['status' => 4], $condition_close);
            $discount_close_goods_list = $discount_goods->getQuery($condition_close, '*', '');
            if(!empty($discount_close_goods_list))
            {
                foreach ( $discount_close_goods_list as $k => $discount_goods_item)
                {
                    $goods = new NsGoodsModel();

                    $data_goods = array(
                        'promotion_type' => 2,
                        'promote_id'     => $discount_goods_item['discount_id']
                    );
                    $goods_id_list = $goods->getQuery($data_goods, 'goods_id', '');
                    if(!empty($goods_id_list))
                    {
                        foreach($goods_id_list as $k => $goods_id)
                        {
                            $goods_info = $goods->getInfo(['goods_id' => $goods_id['goods_id']], 'promotion_type,price');
                            $goods->save(['promotion_price' => $goods_info['price']], ['goods_id'=> $goods_id['goods_id'] ]);
                            $goods_sku = new NsGoodsSkuModel();
                            $goods_sku_list = $goods_sku->getQuery(['goods_id'=> $goods_id['goods_id'] ], 'price,sku_id', '');
                            foreach ($goods_sku_list as $k_sku => $sku)
                            {
                                $goods_sku = new NsGoodsSkuModel();
                                $data_goods_sku = array(
                                    'promote_price' => $sku['price']
                                );
                                $goods_sku->save($data_goods_sku, ['sku_id' => $sku['sku_id']]);
                            }

                        }

                    }
                    $goods->save(['promotion_type' => 0, 'promote_id' => 0], $data_goods);

                }
            }
            $discount_goods->save(['status' => 4], $condition_close);
            /************************************************************结束活动**************************************************************/
            /************************************************************开始活动**************************************************************/
            $condition_start = array(
                'start_time' => array('ELT', $time),
                'status'   => 0
            );
            //查询待开始活动列表
            $discount_goods_list = $discount_goods->getQuery($condition_start, '*', '');
            if(!empty($discount_goods_list))
            {
                foreach ( $discount_goods_list as $k => $discount_goods_item)
                {
                    $goods = new NsGoodsModel();
                    $goods_info = $goods->getInfo(['goods_id' => $discount_goods_item['goods_id']],'promotion_type,price');
                    $data_goods = array(
                        'promotion_type' => 2,
                        'promote_id'     => $discount_goods_item['discount_id'],
                        'promotion_price'  => $goods_info['price'] *$discount_goods_item['discount']/10
                    );
                    $goods->save($data_goods,['goods_id' => $discount_goods_item['goods_id']]);
                    $goods_sku = new NsGoodsSkuModel();
                    $goods_sku_list = $goods_sku->getQuery(['sku_id'=> $discount_goods_item['sku_id'] ], 'price,sku_id', '');
                    foreach ($goods_sku_list as $k_sku => $sku)
                    {
                        $goods_sku = new NsGoodsSkuModel();
                        $data_goods_sku = array(
                            'promote_price' => $sku['price']*$discount_goods_item['discount']/10
                        );
                        $goods_sku->save($data_goods_sku, ['sku_id' => $sku['sku_id']]);
                    }
                }
            }
            $discount_goods->save(['status' => 1], $condition_start);
            $discount->save(['status' => 1], $condition_start);
            /************************************************************开始活动**************************************************************/
            $discount->commit();
            return 1;
        }catch (\Exception $e)
        {
            $discount->rollback();
            return $e;
        }
    }

    /**
     * (non-PHPdoc)
     * @see \data\api\IEvents::autoDeilvery()
     */
    public function autoDeilvery(){
        $order_model = new NsOrderModel();

        try{

            $config = new Config();
            $config_info = $config->getConfig(0, 'ORDER_AUTO_DELIVERY');
            if(!empty($config_info['value']))
            {
                $delivery_time = $config_info['value'];
            }else{
                $delivery_time = 7;//默认7天自动收货
            }
            $time = time()-3600*24*$delivery_time;//订单自动完成

            $condition = array(
                'order_status' => 2,
                'consign_time'  => array('LT', $time)
            );
            $order_list = $order_model->getQuery($condition, 'order_id', '');
             if(!empty($order_list))
            {
                $order = new \data\service\Order\Order();
                foreach ($order_list as $k => $v)
                {
                    if(!empty($v['order_id']))
                    {
                        $order->orderAutoDelivery($v['order_id']);
                    }

                }

            }

            return 1;
        }catch (\Exception $e)
        {
            return $e->getMessage();
        }
    }

    public function sendMail(){
        $order_model = new NsOrderModel();

        try{
            $send_time = 20;//默认20天发送短信
            $time = time()-3600*24*$send_time;

            $condition = array(
                'payment_type' => 11,
                'pay_status' => 3,
                'pay_time'  => array('LT', $time)
            );
            $order_list = $order_model->getQuery($condition, 'order_id,order_no', '');
            if(!empty($order_list))
            {
                $order_no_array = [];
                foreach ($order_list as $k => $v)
                {
                    $order_no_array[$k] = $v['order_no'];
                }
                $order_no_array = implode(',',$order_no_array);
                $toEmail0 = 'xiaoshi.he@ushopal.com';
                $name0    = '何晓诗';

                $toEmail1 = 'xiantao.fu@ushopal.com';
                $name1    = '付贤涛';

                $subject = 'BC赊销订单尾款待结清';
                $content = '你好，订单号 [ '.$order_no_array.' ] 的赊销订单尚未结清款项，即将超期，请点击
                    <br><br><a href="https://www.bonnieclyde.cn/dira">https://www.bonnieclyde.cn/dira</a>查看!谢谢';

                sendMail($toEmail0, $name0, $subject, $content);
                sendMail($toEmail1, $name1, $subject, $content);

            }
            return 1;
        }catch (\Exception $e)
        {
            return $e->getMessage();
        }
    }

    /**
     * 优惠券自动过期
     * {@inheritDoc}
     * @see \data\api\IEvents::autoCoupon()
     */
    public function autoCouponClose(){
        $ns_coupon_model = new NsCouponModel();
        $ns_coupon_model->startTrans();
        try{
            $condition['end_time']  = array('LT',time());
            $condition['state']     = array('NEQ',2);//排除已使用的优惠券
            $condition['use_type']  = array('NEQ',2); # todo @dai  新增条件 （使用期限  1:仅限活动期间使用 2:领取后多少天内使用）
            $count = $ns_coupon_model->getCount($condition);
            $res = -1;
            if($count){
                $res = $ns_coupon_model->save(['state'=>3],$condition);
            }
            $ns_coupon_model->commit();
            return $res;
        }catch (\Exception $e)
        {
            $ns_coupon_model->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 优惠券自动过期2
     * {@inheritDoc}
     * @see \data\api\IEvents::autoCouponDay()
     */
    public function autoCouponDayClose(){
        $condition['state']    = array('NOT IN', [0,2]);//排除未领取  已使用 的优惠券
        $condition['use_type'] = array('EQ', 2); # todo @dai  新增条件 （使用期限  1:仅限活动期间使用 2:领取后多少天内使用）
        $time                  = time();
        $coupon_list  = \think\Db::name('ns_coupon')->where($condition)->select();
        if(count($coupon_list) > 0) {
            foreach ($coupon_list as $v) {
                $times = $v['get_after_days'] * 86400 + $v['fetch_time'];
                if ($times < $time) {
                    \think\Db::name('ns_coupon')->where(['coupon_id' => $v['coupon_id']])->update(['state' => 3]);
                }
            }
        }
    }

    /**
     * 营销游戏自动执行操作，改变活动状态
     * 创建时间：2018年1月30日11:45:48 王永杰
     */
    public function autoPromotionGamesOperation(){
        $model = new NsPromotionGamesModel();
        $model->startTrans();
        try{
            $time = time();

            //活动开始条件：当前时间大于开始时间，并且活动状态等于0（未开始）
            $condition_start = array(
                'start_time' => array('ELT', $time),
                'status'   => 0
            );

            //活动结束条件：当前时间大于结束时间，并且活动状态不等于-1（已结束）
            $condition_close = array(
                'end_time' => array('LT', $time),
                'status'   => array('NEQ', -1)
            );

            $start_count = $model->getCount($condition_start);
            $close_count = $model->getCount($condition_close);

            if($start_count){
                $model->save(['status'=>1],$condition_start);
            }

            if($close_count){
                $model->save(['status'=>-1],$condition_close);
            }

            $model->commit();
        }catch(\Exception $e){
            $model->rollback();
            return $e->getMessage();
        }
    }

    //结算订单分润
    public function settlementOrderSeparation()
    {
        $orderModel = new NsOrderModel();
        $orderGoodsModel = new NsOrderGoodsModel();
        $distributor = new BcDistributorModel();
        $order_list = $orderModel->where(['distributor_type' => ['in', '2,4'], 'order_status' => 4, 'is_settlement' => 0])->order('finish_time desc')->select();
        foreach ($order_list as $k => $v) {
            $where = ['order_id' => $v['order_id'], 'refund_status' => ['neq', 5]];
            $direct_separation = $orderGoodsModel->directSeparation($where);
            if($v['source_distribution'] > 0){
                // 获取账户信息
                $infoOne = $distributor->getInfo(['uid' => $v['source_distribution']], '*');
                $dataOne = [
                    'uid' => $v['source_distribution'],
                    'data_id' => $v['order_id'],
                    'order_no' =>$v['order_no'],
                    'account_type' => 1,
                    'from_type' => 1,
                    'money' => $direct_separation,
                    'text' => '直接分润',
                    'settlement_time' => $v['finish_time'],
                    'balance_record' => $infoOne['balance']+$direct_separation,
                    'bonus_record' => $infoOne['bonus']
                ];
                $distributorSeparationRecordsModel = new BcDistributorAccountRecordsModel();
                $distributorSeparationRecordsModel->save($dataOne);

                $distributor_model = new BcDistributorModel();
                $distributor_model->where(['uid'=>$v['source_distribution']])->setInc('balance',$direct_separation); //增加账户分润余额

            }

            $indirect_separation = $orderGoodsModel->indirectSeparation($where);
            if($v['parent_source_distribution'] > 0){
                // 获取账户信息
                $infoTwo = $distributor->getInfo(['uid' => $v['parent_source_distribution']], '*');
                $dataTwo = [
                    'uid' => $v['parent_source_distribution'],
                    'data_id' => $v['order_id'],
                    'order_no' =>$v['order_no'],
                    'account_type' => 1,
                    'from_type' => 2,
                    'money' => $indirect_separation,
                    'text' => '间接分润',
                    'settlement_time' => $v['finish_time'],
                    'balance_record' => $infoTwo['balance']+$indirect_separation,
                    'bonus_record' => $infoTwo['bonus']
                ];
                $distributorSeparationRecordsModel = new BcDistributorAccountRecordsModel();
                $distributorSeparationRecordsModel->save($dataTwo);
                $distributor_model = new BcDistributorModel();
                $distributor_model->where(['uid'=>$v['parent_source_distribution']])->setInc('balance',$indirect_separation); //增加账户分润余额

            }

            $orderModel = new NsOrderModel();
            $orderModel->save(['is_settlement' => 1,'settlement_time'=>$v['finish_time']],['order_id'=>$v['order_id']]);
        }
        return 1;
    }

    //更新员工信息
    public function updateEmployeeData()
    {
        $ret = getEmployeeList();
        if($ret){
//            \think\Db::name('bc_employee')->where('1=1')->delete();
            \think\Db::execute('truncate table `bc_employee`');
//            $ret = json_decode($ret,true);
            foreach($ret as $v){
                $employ = new BcEmployeeModel();
                $retval = $employ->allowField(['user_name', 'mobile', 'hired_date', 'avatar', 'org_email', 'group_name'])->save($v);
            }
        }
        return $retval;
    }

    #参与内购活动商品数量限制  @戴
    public function promotionGoodsLimit(){
        $mansong  = new NsPromotionMansongModel();
        $NG_MODEL = new NsPromotionNeigouGoodsModel();

        $mansong->startTrans();
        try{
            $condition = array(
                'is_neigou' => 1,   #内购
                'status'    => 1
            );
            // 查询内购活动状态正常的活动id
            $mansongInfo = $mansong->getQuery($condition,'mansong_id,start_time','');
            if(!empty($mansongInfo)){
                $discount_id = $mansongInfo[0]['mansong_id'];
                $start_time  = $mansongInfo[0]['start_time'];
                $condition1  = array(
                    'discount_id' => $discount_id,
                );
                $neigou_lists = $NG_MODEL->getQuery($condition1,'sku_id','');
                if($neigou_lists){
                    foreach($neigou_lists as $v){
                        $orderGoods      = new NsOrderGoodsModel();
                        $where           = ['no.is_inside' => 1, "no.order_status" => array("neq", 5), 'nog.sku_id' => $v['sku_id'],'no.create_time' => array("gt", $start_time)];
                        $use_num         = $orderGoods->getorderGoodsNum($where) ? $orderGoods->getorderGoodsNum($where) : 0;
                        $data['use_num'] = $use_num;
                        $where1          = ['discount_id' => $discount_id, "sku_id" => $v['sku_id']];

                        $NG_MODEL->save($data,$where1);
                    }
                }
            }
            $mansong->commit();
            return 1;
        }catch (\Exception $e){
            $mansong->rollback();
            return $e->getMessage();
        }

    }
}
