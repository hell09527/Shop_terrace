<?php
/**
 * OrderAccount.php
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
namespace data\service\Order;

use data\model\AlbumPictureModel;
use data\model\BcOrderOfflinePayModel;
use data\model\ConfigModel;
use data\model\NsGoodsModel;
use data\model\NsGoodsSkuModel;
use data\model\NsOrderActionModel as NsOrderActionModel;
use data\model\NsGiftGiveGetModel as NsGiftGiveGetModel;
use data\model\NsOrderExpressCompanyModel;
use data\model\NsOrderGoodsExpressModel;
use data\model\NsOrderGoodsModel;
use data\model\NsOrderGoodsPromotionDetailsModel;
use data\model\NsOrderModel;
use data\model\NsOrderPickupModel;
use data\model\NsOrderPromotionDetailsModel;
use data\model\NsOrderRefundAccountRecordsModel;
use data\model\NsPickupPointModel;
use data\model\NsPromotionFullMailModel;
use data\model\NsPromotionMansongRuleModel;
use data\model\UserModel as UserModel;
use data\model\NsMemberModel as NsMemberModel;
use data\service\Address;
use data\service\BaseService;
use data\service\Config;
use data\service\Member\MemberAccount;
use data\service\Member\MemberCoupon;
use data\service\Order\OrderStatus;
use data\service\promotion\GoodsExpress;
use data\service\promotion\GoodsMansong;
use data\service\promotion\GoodsPreference;
use data\service\UnifyPay;
use data\service\WebSite;
use think\Log;
use data\service\VirtualGoods;
use data\service\Promotion;
use data\model\NsPromotionGiftModel;
use data\model\NsPromotionGiftGoodsModel;
use data\service\Order\OrderCard;
use think\migration\command\migrate\Run;

include('RunSa.php');

/**
 * 订单操作类
 */
class Order extends BaseService
{

    public $order;

    // 订单主表
    function __construct()
    {
        parent::__construct();
        $this->order = new NsOrderModel();
    }

    /**
     * 订单创建
     * （订单传入积分系统默认为使用积分兑换商品）
     *
     * @param unknown $order_type
     * @param unknown $out_trade_no
     * @param unknown $pay_type
     * @param unknown $shipping_type
     * @param unknown $order_from
     * @param unknown $buyer_ip
     * @param unknown $buyer_message
     * @param unknown $buyer_invoice
     * @param unknown $shipping_time
     * @param unknown $receiver_mobile
     * @param unknown $receiver_province
     * @param unknown $receiver_city
     * @param unknown $receiver_district
     * @param unknown $receiver_address
     * @param unknown $receiver_zip
     * @param unknown $receiver_name
     * @param unknown $point
     * @param unknown $point_money
     * @param unknown $coupon_money
     * @param unknown $coupon_id
     * @param unknown $user_money
     * @param unknown $promotion_money
     * @param unknown $shipping_money
     * @param unknown $pay_money
     * @param unknown $give_point
     * @param unknown $goods_sku_list
     * @return number|Exception
     */
    public function orderCreate($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $point, $coupon_id, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $tx_type, $is_inside, $card_name, $card_no, $source_branch, $source_distribution, $parent_source_distribution, $inviter, $distributor_type, $traffic_acquisition_source, $coin, $fixed_telephone = "")
    {
        $this->order->startTrans();

        try {
            // 设定不使用会员余额支付
            $user_money = 0;
            // 查询商品对应的店铺ID
            $order_goods_preference = new GoodsPreference();
            $shop_id                = $order_goods_preference->getGoodsSkuListShop($goods_sku_list);
            // 单店版查询网站内容
            $web_site  = new WebSite();
            $web_info  = $web_site->getWebSiteInfo();
            $shop_name = $web_info['title'];
            // 获取优惠券金额
            $coupon       = new MemberCoupon();
            $coupon_money = $coupon->getCouponMoney($coupon_id);

            // 获取购买人信息
            $buyer      = new UserModel();
            $buyer_info = $buyer->getInfo([
                'uid' => $this->uid
            ], 'nick_name');
            // 订单商品费用

            $goods_money = $order_goods_preference->getGoodsSkuListPrice($goods_sku_list);
            $point       = $order_goods_preference->getGoodsListExchangePoint($goods_sku_list);
            // 获取订单邮费,订单自提免除运费
            if ($shipping_type == 1) {
                $order_goods_express = new GoodsExpress();
                $deliver_price       = $order_goods_express->getSkuListExpressFee($goods_sku_list, $shipping_company_id, $receiver_province, $receiver_city, $receiver_district);
                if ($deliver_price < 0) {
                    $this->order->rollback();
                    return $deliver_price;
                }
            } else {
                // 根据自提点服务费用计算
                $deliver_price = $order_goods_preference->getPickupMoney($goods_money);
            }

            // 积分兑换抵用金额
            $account_flow = new MemberAccount();
            /*
             * $point_money = $order_goods_preference->getPointMoney($point, $shop_id);
             */
            $point_money = 0;
            /*
             * if($point > 0)
             * {
             * //积分兑换抵用商品金额+邮费
             * $point_money = $goods_money;
             * //订单为已支付
             * if($deliver_price == 0)
             * {
             * $order_status = 1;
             * }else
             * {
             * $order_status = 0;
             * }
             *
             * //赠送积分为0
             * $give_point = 0;
             * //不享受满减送优惠
             * $promotion_money = 0;
             *
             * }else{
             */
            // 订单来源
            if (isWeixin()) {
                $order_from = 1; // 微信
            } elseif (request()->isMobile()) {
                $order_from = 2; // 手机
            } else {
                $order_from = 3; // 电脑
            }
            // 订单支付方式

            // 订单待支付
            $order_status = 0;
            // 购买商品获取积分数
            $give_point = $order_goods_preference->getGoodsSkuListGivePoint($goods_sku_list);
            // 订单满减送活动优惠
            $goods_mansong          = new GoodsMansong();
            $mansong_array          = $goods_mansong->getGoodsSkuListMansong($goods_sku_list);
            $promotion_money        = 0;
            $mansong_rule_array     = array();
            $mansong_discount_array = array();
            $manson_gift_array      = array(); // 赠品[id]=>数量
            $promotion              = new Promotion();

            if (!empty($mansong_array)) {
                $manson_gift_temp_array = array();
                $gift_num_arr           = array();
                foreach ($mansong_array as $k_mansong => $v_mansong) {
                    foreach ($v_mansong['discount_detail'] as $k_rule => $v_rule) {
                        $rule                     = $v_rule[1];
                        $discount_money_detail    = explode(':', $rule);
                        $mansong_discount_array[] = array(
                            $discount_money_detail[0],
                            $discount_money_detail[1],
                            $v_rule[0]['rule_id']
                        );
                        $promotion_money += $discount_money_detail[1]; // round($discount_money_detail[1],2);
                        // 添加优惠活动信息
                        $mansong_rule_array[] = $v_rule[0];

                        $gift_id = $v_rule[0]['gift_id'];

                        #todo  @dai  设置多个赠品  ......
                        if ($gift_id > 0) {
                            if (strpos($gift_id, ',') !== false) {
                                #多个赠品
                                $gift_id = explode(',', $gift_id);
                                $num     = count($gift_id);
                                for ($i = 0; $i < $num; $i++) {
                                    $_v          = explode(':', $gift_id[$i]);
                                    $gift_info   = \think\Db::name('ns_promotion_gift')->where(['gift_id' => $_v[0]])->find();
                                    $can_use_num = $gift_info['gift_num'] - $gift_info['gift_song_num'];
                                    if ($can_use_num < $_v[1]) {
                                        #原赠品不足
                                        $replace_info = \think\Db::name('ns_promotion_gift_replace')->where(['gift_id' => $_v[0]])->find();
                                        if (!empty($replace_info)) {
                                            # 有补充赠品
                                            $new_gift_info   = \think\Db::name('ns_promotion_gift')->where(['gift_id' => $replace_info['n_gift_id']])->find();
                                            $new_can_use_num = $new_gift_info['gift_num'] - $new_gift_info['gift_song_num'];
                                            if ($can_use_num > 0) {
                                                # 原赠品 + 补充赠品
                                                array_push($manson_gift_temp_array, $_v[0]);
                                                array_push($gift_num_arr, $can_use_num);

                                                $_can_use_num = (($_v[1] - $can_use_num) * $replace_info['n_num'] >= $new_can_use_num) ? $new_can_use_num : ($_v[1] - $can_use_num) * $replace_info['n_num'];
                                                if ($_can_use_num > 0) {
                                                    array_push($manson_gift_temp_array, $new_gift_info['gift_id']);
                                                    array_push($gift_num_arr, $_can_use_num);
                                                }
                                            } else {
                                                # 全补充赠品
                                                if ($new_can_use_num >= $_v[1] * $replace_info['n_num']) {
                                                    #补充赠品足够
                                                    array_push($manson_gift_temp_array, $new_gift_info['gift_id']);
                                                    array_push($gift_num_arr, $_v[1] * $replace_info['n_num']);
                                                } else {
                                                    # 补充赠品不足
                                                    if ($new_can_use_num != 0) {
                                                        array_push($manson_gift_temp_array, $new_gift_info['gift_id']);
                                                        array_push($gift_num_arr, $new_can_use_num);
                                                    }
                                                }
                                            }
                                        } else {
                                            # 无补充
                                            if ($can_use_num > 0) {
                                                array_push($manson_gift_temp_array, $_v[0]);
                                                array_push($gift_num_arr, $can_use_num);
                                            }
                                        }

                                    } else {
                                        # 原赠品充足
                                        array_push($manson_gift_temp_array, $_v[0]);
                                        array_push($gift_num_arr, $_v[1]);
                                    }
                                }
                            } else {
                                $_v          = explode(':', $gift_id);
                                $gift_info   = \think\Db::name('ns_promotion_gift')->where(['gift_id' => $_v[0]])->find();
                                $can_use_num = $gift_info['gift_num'] - $gift_info['gift_song_num'];
                                if ($can_use_num < $_v[1]) {
                                    #原赠品不足
                                    $replace_info = \think\Db::name('ns_promotion_gift_replace')->where(['gift_id' => $_v[0]])->find();
                                    if (!empty($replace_info)) {
                                        # 有补充赠品
                                        $new_gift_info   = \think\Db::name('ns_promotion_gift')->where(['gift_id' => $replace_info['n_gift_id']])->find();
                                        $new_can_use_num = $new_gift_info['gift_num'] - $new_gift_info['gift_song_num'];
                                        if ($can_use_num > 0) {
                                            # 原赠品 + 补充赠品
                                            array_push($manson_gift_temp_array, $_v[0]);
                                            array_push($gift_num_arr, $can_use_num);

                                            $_can_use_num = (($_v[1] - $can_use_num) * $replace_info['n_num'] >= $new_can_use_num) ? $new_can_use_num : ($_v[1] - $can_use_num) * $replace_info['n_num'];
                                            if ($_can_use_num > 0) {
                                                array_push($manson_gift_temp_array, $new_gift_info['gift_id']);
                                                array_push($gift_num_arr, $_can_use_num);
                                            }
                                        } else {
                                            # 全补充赠品
                                            if ($new_can_use_num >= $_v[1] * $replace_info['n_num']) {
                                                #补充赠品足够
                                                array_push($manson_gift_temp_array, $new_gift_info['gift_id']);
                                                array_push($gift_num_arr, $_v[1] * $replace_info['n_num']);
                                            } else {
                                                # 补充赠品不足
                                                if ($new_can_use_num != 0) {
                                                    array_push($manson_gift_temp_array, $new_gift_info['gift_id']);
                                                    array_push($gift_num_arr, $new_can_use_num);
                                                }
                                            }
                                        }
                                    } else {
                                        # 无补充
                                        if ($can_use_num > 0) {
                                            array_push($manson_gift_temp_array, $_v[0]);
                                            array_push($gift_num_arr, $can_use_num);
                                        }
                                    }
                                } else {
                                    # 原赠品充足
                                    array_push($manson_gift_temp_array, $_v[0]);
                                    array_push($gift_num_arr, $_v[1]);
                                }
                            }
                        }
                    }
                }

                $promotion_money = round($promotion_money, 2);
//                $manson_gift_array = array_count_values($manson_gift_temp_array);
                // $manson_gift_array = array('3'=>1); // 赠品[id]=>数量,暂时写死
            }
            $full_mail_array = array();
            // 计算订单的满额包邮
            $full_mail_model = new NsPromotionFullMailModel();
            // 店铺的满额包邮
            $full_mail_obj = $full_mail_model->getInfo([
                "shop_id" => $shop_id
            ], "*");
            $no_mail       = checkIdIsinIdArr($receiver_city, $full_mail_obj['no_mail_city_id_array']);
            if ($no_mail) {
                $full_mail_obj['is_open'] = 0;
            }
            if (!empty($full_mail_obj)) {
                $is_open          = $full_mail_obj["is_open"];
                $full_mail_money  = $full_mail_obj["full_mail_money"];
                $order_real_money = $goods_money - $promotion_money - $coupon_money - $point_money;
                if ($is_open == 1 && $order_real_money >= $full_mail_money && $deliver_price > 0) {
                    // 符合满额包邮 邮费设置为0
                    $full_mail_array["promotion_id"]        = $full_mail_obj["mail_id"];
                    $full_mail_array["promotion_type"]      = 'MANEBAOYOU';
                    $full_mail_array["promotion_name"]      = '满额包邮';
                    $full_mail_array["promotion_condition"] = '满' . $full_mail_money . '元,包邮!';
                    $full_mail_array["discount_money"]      = $deliver_price;
                    $deliver_price                          = 0;
                }
            }

            // 订单费用(具体计算)
            $order_money = $goods_money + $deliver_price - $promotion_money - $coupon_money - $point_money;

            if ($order_money < 0) {
                $order_money    = 0;
                $user_money     = 0;
                $platform_money = 0;
            }

            if (!empty($buyer_invoice)) {
                // 添加税费
                $config    = new Config();
                $tax_value = $config->getConfig(0, 'ORDER_INVOICE_TAX');
                if (empty($tax_value['value'])) {
                    $tax = 0;
                } else {
                    $tax = $tax_value['value'];
                }
                $tax_money = $order_money * $tax / 100;
            } else {
                $tax_money = 0;
            }
            $order_money = $order_money + $tax_money;

            if ($order_money < $platform_money) {
                $platform_money = $order_money;
            }
            // $card_money = round($card_money, 2);
            $pay_money = $order_money - $user_money - $platform_money;
            if ($pay_money <= 0) {
                $pay_money    = 0;
                $order_status = 0;
                $pay_status   = 0;
            } else {
                $order_status = 0;
                $pay_status   = 0;
            }

            // 积分返还类型
            $config          = new ConfigModel();
            $config_info     = $config->getInfo([
                "instance_id" => $shop_id,
                "key" => "SHOPPING_BACK_POINTS"
            ], "value");
            $give_point_type = $config_info["value"];

            //内购订单不绑定任何来源网点和分销来源
            if ($is_inside == 1) {
                $source_branch              = 0;
                $distributor_type           = 0;
                $source_distribution        = 0;
                $parent_source_distribution = 0;
            }

            //特殊分销来源及买家时user_name取值为receiver_name
            //uid           手机号           用户昵称       事业部
            //181,4030(原)
            //5546          13162697120      暖言         新零售事业部
            //5553          13162695763      樊心         Ponroy事业部
            //5554          13162695613      七夏         新兴品牌事业部
            //5555          13162691325      不忘初心      总代事业部
            $uidArray = [5546,5553,5554,5555];
            if (in_array($this->uid,$uidArray) && $source_distribution == 4560) {
                $user_name = $receiver_name;
            } else {
                $user_name = $buyer_info['nick_name'];
            }

            $source_distribution_name = '';
            if($this->uid == 5546  && $source_distribution == 4560) {
                $source_distribution_name = 'SHOPAL分销新零售事业部';
            }

            if($this->uid == 5553  && $source_distribution == 4560){
                $source_distribution_name = 'SHOPAL分销Ponroy事业部';
            }

            if($this->uid == 5554  && $source_distribution == 4560){
                $source_distribution_name = 'SHOPAL分销新兴品牌事业部';
            }

            if($this->uid == 5555  && $source_distribution == 4560){
                $source_distribution_name = 'SHOPAL分销总代事业部';
            }

            // 店铺名称
            $data_order = array(
                'order_type' => $order_type,
                'order_no' => $this->createOrderNo($shop_id),
                'out_trade_no' => $out_trade_no,
                'payment_type' => $pay_type,
                'shipping_type' => $shipping_type,
                'order_from' => $order_from,
                'buyer_id' => $this->uid,
                'user_name' => $user_name,
                'buyer_ip' => $buyer_ip,
                'buyer_message' => $buyer_message,
                'buyer_invoice' => $buyer_invoice,
                'shipping_time' => $shipping_time, // datetime NOT NULL COMMENT '买家要求配送时间',
                'receiver_mobile' => $receiver_mobile, // varchar(11) NOT NULL DEFAULT '' COMMENT '收货人的手机号码',
                'receiver_province' => $receiver_province, // int(11) NOT NULL COMMENT '收货人所在省',
                'receiver_city' => $receiver_city, // int(11) NOT NULL COMMENT '收货人所在城市',
                'receiver_district' => $receiver_district, // int(11) NOT NULL COMMENT '收货人所在街道',
                'receiver_address' => $receiver_address, // varchar(255) NOT NULL DEFAULT '' COMMENT '收货人详细地址',
                'receiver_zip' => $receiver_zip, // varchar(6) NOT NULL DEFAULT '' COMMENT '收货人邮编',
                'receiver_name' => $receiver_name, // varchar(50) NOT NULL DEFAULT '' COMMENT '收货人姓名',
                'shop_id' => $shop_id, // int(11) NOT NULL COMMENT '卖家店铺id',
                'shop_name' => $shop_name, // varchar(100) NOT NULL DEFAULT '' COMMENT '卖家店铺名称',
                'goods_money' => $goods_money, // decimal(19, 2) NOT NULL COMMENT '商品总价',
                'tax_money' => $tax_money, // 税费
                'order_money' => $order_money, // decimal(10, 2) NOT NULL COMMENT '订单总价',
                'point' => $point, // int(11) NOT NULL COMMENT '订单消耗积分',
                'point_money' => $point_money, // decimal(10, 2) NOT NULL COMMENT '订单消耗积分抵多少钱',
                'coupon_money' => $coupon_money, // _money decimal(10, 2) NOT NULL COMMENT '订单代金券支付金额',
                'coupon_id' => $coupon_id, // int(11) NOT NULL COMMENT '订单代金券id',
                'user_money' => $user_money, // decimal(10, 2) NOT NULL COMMENT '订单预存款支付金额',
                'promotion_money' => $promotion_money, // decimal(10, 2) NOT NULL COMMENT '订单优惠活动金额',
                'shipping_money' => $deliver_price, // decimal(10, 2) NOT NULL COMMENT '订单运费',
                'pay_money' => $pay_money, // decimal(10, 2) NOT NULL COMMENT '订单实付金额',
                'refund_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单退款金额',
                'give_point' => $give_point, // int(11) NOT NULL COMMENT '订单赠送积分',
                'order_status' => $order_status, // tinyint(4) NOT NULL COMMENT '订单状态',
                'pay_status' => $pay_status, // tinyint(4) NOT NULL COMMENT '订单付款状态',
                'shipping_status' => 0, // tinyint(4) NOT NULL COMMENT '订单配送状态',
                'review_status' => 0, // tinyint(4) NOT NULL COMMENT '订单评价状态',
                'feedback_status' => 0, // tinyint(4) NOT NULL COMMENT '订单维权状态',
                'user_platform_money' => $platform_money, // 平台余额支付
                'coin_money' => $coin,
                'tx_type' => $tx_type,  // 交易类型（1：大贸，2：跨境）
                'is_inside' => $is_inside, //是否内购(0:否,1:是)
                'card_name' => $card_name, // 身份证姓名
                'card_no' => $card_no, // 身份证号码
                'create_time' => time(),
                "give_point_type" => $give_point_type,
                'shipping_company_id' => $shipping_company_id,
                'fixed_telephone' => $fixed_telephone,
                'source_branch' => $source_branch,
                'distributor_type' => $distributor_type,
                'source_distribution' => $source_distribution,
                'parent_source_distribution' => $parent_source_distribution,
                'inviter' => $inviter,
                'traffic_acquisition_source' => $traffic_acquisition_source,
                'source_distribution_name' => $source_distribution_name
            ); // 固定电话
            // datetime NOT NULL DEFAULT 'CURRENT_TIMESTAMP' COMMENT '订单创建时间',
            if ($pay_status == 2) {
                $data_order["pay_time"] = time();
            }
            $order = new NsOrderModel();
            $order->save($data_order);
            $order_id = $order->order_id;
            $pay      = new UnifyPay();
            $pay->createPayment($shop_id, $out_trade_no, $shop_name . "订单", $shop_name . "订单", $pay_money, 1, $order_id);
            // 如果是订单自提需要添加自提相关信息
            if ($shipping_type == 2) {
                if (!empty($pick_up_id)) {
                    $pickup_model        = new NsPickupPointModel();
                    $pickup_point_info   = $pickup_model->getInfo([
                        'id' => $pick_up_id
                    ], '*');
                    $order_pick_up_model = new NsOrderPickupModel();
                    $data_pickup         = array(
                        'order_id' => $order_id,
                        'name' => $pickup_point_info['name'],
                        'address' => $pickup_point_info['address'],
                        'contact' => $pickup_point_info['address'],
                        'phone' => $pickup_point_info['phone'],
                        'city_id' => $pickup_point_info['city_id'],
                        'province_id' => $pickup_point_info['province_id'],
                        'district_id' => $pickup_point_info['district_id'],
                        'supplier_id' => $pickup_point_info['supplier_id'],
                        'longitude' => $pickup_point_info['longitude'],
                        'latitude' => $pickup_point_info['latitude'],
                        'create_time' => time()
                    );
                    $order_pick_up_model->save($data_pickup);
                }
            }
            // 满额包邮活动
            if (!empty($full_mail_array)) {
                $order_promotion_details = new NsOrderPromotionDetailsModel();
                $data_promotion_details  = array(
                    'order_id' => $order_id,
                    'promotion_id' => $full_mail_array["promotion_id"],
                    'promotion_type_id' => 2,
                    'promotion_type' => $full_mail_array["promotion_type"],
                    'promotion_name' => $full_mail_array["promotion_name"],
                    'promotion_condition' => $full_mail_array["promotion_condition"],
                    'discount_money' => $full_mail_array["discount_money"],
                    'used_time' => time()
                );
                $order_promotion_details->save($data_promotion_details);
            }

            // 满减送详情，添加满减送活动优惠情况
            if (!empty($mansong_rule_array)) {
                $mansong_rule_array = array_unique($mansong_rule_array);
                foreach ($mansong_rule_array as $k_mansong_rule => $v_mansong_rule) {
                    $order_promotion_details = new NsOrderPromotionDetailsModel();
                    $data_promotion_details  = array(
                        'order_id' => $order_id,
                        'promotion_id' => $v_mansong_rule['rule_id'],
                        'promotion_type_id' => 1,
                        'promotion_type' => 'MANJIAN',
                        'promotion_name' => '满减送活动',
                        'promotion_condition' => '满' . $v_mansong_rule['price'] . '元，减' . $v_mansong_rule['discount'],
                        'discount_money' => $v_mansong_rule['discount'],
                        'used_time' => time()
                    );
                    $order_promotion_details->save($data_promotion_details);
                }
                // 添加到对应商品项优惠满减
                if (!empty($mansong_discount_array)) {
                    foreach ($mansong_discount_array as $k => $v) {
                        $order_goods_promotion_details = new NsOrderGoodsPromotionDetailsModel();
                        $data_details                  = array(
                            'order_id' => $order_id,
                            'promotion_id' => $v[2],
                            'sku_id' => $v[0],
                            'promotion_type' => 'MANJIAN',
                            'discount_money' => $v[1],
                            'used_time' => time()
                        );
                        $order_goods_promotion_details->save($data_details);
                    }
                }

                // 添加赠品
                #todo @dai
                #   id $manson_gift_temp_array  num $gift_num_arr

                if (is_array($manson_gift_temp_array)) {
                    $manson_gift_temp_array = array_unique($manson_gift_temp_array);
                }
                if (!empty($manson_gift_temp_array)) {
                    $promotion   = new Promotion();
                    $order_goods = new OrderGoods();

//                    for($i = 0; $i < count($manson_gift_temp_array); $i++){
                    $_arr = array();
                    foreach ($manson_gift_temp_array as $key => $v) {
                        $maoson_gift_goods_sku = $promotion->getGoodsSkuByGiftId($v, $gift_num_arr[$key]);
                        array_push($_arr, $maoson_gift_goods_sku);
                    }
                    if (!empty($_arr)) {
                        // 添加订单赠品项
                        $res_order_goods = $order_goods->addOrderGiftGoods($order_id, $_arr);
                    }

//                    foreach ($manson_gift_array as $gift_id => $num) {
//                        $maoson_gift_goods_sku = $promotion->getGoodsSkuByGiftId($gift_id, 1);
//                        if (! empty($maoson_gift_goods_sku)) {
//                            // 添加订单赠品项
//                            $res_order_goods = $order_goods->addOrderGiftGoods($order_id, $maoson_gift_goods_sku);
//                        }
//                    }
//
                }
            }

            // 添加到对应商品项优惠优惠券使用详情
            if ($coupon_id > 0) {
                $coupon_details_array = $order_goods_preference->getGoodsCouponPromoteDetail($coupon_id, $coupon_money, $goods_sku_list);
                foreach ($coupon_details_array as $k => $v) {
                    $order_goods_promotion_details = new NsOrderGoodsPromotionDetailsModel();
                    $data_details                  = array(
                        'order_id' => $order_id,
                        'promotion_id' => $coupon_id,
                        'sku_id' => $v['sku_id'],
                        'promotion_type' => 'COUPON',
                        'discount_money' => $v['money'],
                        'used_time' => time()
                    );
                    $order_goods_promotion_details->save($data_details);
                }
            }

            // 使用积分
            if ($point > 0) {
                $retval_point = $account_flow->addMemberAccountData($shop_id, 1, $this->uid, 0, $point * (-1), 1, $order_id, '商城订单');
                if ($retval_point < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_POINT;
                }
            }

            if ($coin > 0) {
                $retval_point = $account_flow->addMemberAccountData($shop_id, 3, $this->uid, 0, $coin * (-1), 1, $order_id, '商城订单');
                if ($retval_point < 0) {
                    $this->order->rollback();
                    return LOW_COIN;
                }
            }

            if ($user_money > 0) {
                $retval_user_money = $account_flow->addMemberAccountData($shop_id, 2, $this->uid, 0, $user_money * (-1), 1, $order_id, '商城订单');
                if ($retval_user_money < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_USER_MONEY;
                }
            }

            if ($platform_money > 0) {
                $retval_platform_money = $account_flow->addMemberAccountData(0, 2, $this->uid, 0, $platform_money * (-1), 1, $order_id, '商城订单');
                if ($retval_platform_money < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_PLATFORM_MONEY;
                }
            }

            // 使用优惠券
            if ($coupon_id > 0) {
                $retval = $coupon->useCoupon($this->uid, $coupon_id, $order_id);
                if (!($retval > 0)) {
                    $this->order->rollback();
                    return $retval;
                }
            }

            // 使用心意卷
            // if ($is_use_card == 1) {
            //     $retval = $this->OrderUseCard($order_id, $card_id, $card_token,$card_money);
            //     if (! ($retval > 0)) {
            //         $this->order->rollback();
            //         return $retval;
            //     }
            // }

            //
            if (!empty($card_no) && !empty($card_name)) {
                $data   = array(
                    'card_no' => $card_no,
                    'card_name' => $card_name
                );
                $where  = array('uid' => $this->uid);
                $user   = new UserModel();
                $retval = $user->save($data, $where);
                if (!($retval > 0)) {
                    $this->order->rollback();
                    return $retval;
                }
            }
            // 添加订单项
            $order_goods     = new OrderGoods();
            $res_order_goods = $order_goods->addOrderGoods($order_id, $goods_sku_list, $is_inside, $distributor_type, $parent_source_distribution);
            if (!($res_order_goods > 0)) {
                $this->order->rollback();
                return $res_order_goods;
            }
            $this->addOrderAction($order_id, $this->uid, '创建订单');

            $this->order->commit();
            return $order_id;
        } catch (\Exception $e) {
            $this->order->rollback();
        }
    }

    public function shareOrderCreate($order_type, $out_trade_no, $pay_type, $shipping_type, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $goods_sku_list, $shipping_company_id, $tx_type, $card_name, $card_no, $source_branch, $source_distribution, $parent_source_distribution, $inviter, $distributor_type, $count_money, $coin, $fixed_telephone = "")
    {
        $this->order->startTrans();

        try {
            // 查询商品对应的店铺ID
            $order_goods_preference = new GoodsPreference();
            $shop_id                = $order_goods_preference->getGoodsSkuListShop($goods_sku_list);

            // 单店版查询网站内容
            $web_site  = new WebSite();
            $web_info  = $web_site->getWebSiteInfo();
            $shop_name = $web_info['title'];

            // 获取购买人信息
            $buyer      = new UserModel();
            $buyer_info = $buyer->getInfo([
                'uid' => $this->uid
            ], 'nick_name');

            // 获取订单邮费,订单自提免除运费
            if ($shipping_type == 1) {
                $order_goods_express = new GoodsExpress();
                $deliver_price       = $order_goods_express->getSkuListExpressFee($goods_sku_list, $shipping_company_id, $receiver_province, $receiver_city, $receiver_district);
                if ($deliver_price < 0) {
                    $this->order->rollback();
                    return $deliver_price;
                }
            } else {
                // 根据自提点服务费用计算
                $deliver_price = $order_goods_preference->getPickupMoney($count_money);
            }

            // 订单来源
            if (isWeixin()) {
                $order_from = 1; // 微信
            } elseif (request()->isMobile()) {
                $order_from = 2; // 手机
            } else {
                $order_from = 3; // 电脑
            }

            // 订单费用(具体计算)
            $order_money = $count_money + $deliver_price;

            if ($order_money < 0) {
                $order_money = 0;
            }

            if (!empty($buyer_invoice)) {
                // 添加税费
                $config    = new Config();
                $tax_value = $config->getConfig(0, 'ORDER_INVOICE_TAX');
                if (empty($tax_value['value'])) {
                    $tax = 0;
                } else {
                    $tax = $tax_value['value'];
                }
                $tax_money = $order_money * $tax / 100;
            } else {
                $tax_money = 0;
            }
            $order_money = $order_money + $tax_money;

            // 积分返还类型
            $config          = new ConfigModel();
            $config_info     = $config->getInfo([
                "instance_id" => $shop_id,
                "key" => "SHOPPING_BACK_POINTS"
            ], "value");
            $give_point_type = $config_info["value"];

            //uid           手机号           用户昵称       事业部
            //181,4030(原)
            //5546          13162697120      暖言         新零售事业部
            //5553          13162695763      樊心         Ponroy事业部
            //5554          13162695613      七夏         新兴品牌事业部
            //5555          13162691325      不忘初心      总代事业部
            //特殊分销来源及买家时指定特定的分销来源
            $uidArray = [5546,5553,5554,5555];
//            if (in_array($this->uid,$uidArray) && $source_distribution == 532) {
//                $source_distribution = 3375;
//            }

            //特殊分销来源及买家时user_name取值为receiver_name
            if (in_array($this->uid,$uidArray) && $source_distribution == 4560) {
                $user_name = $receiver_name;
            } else {
                $user_name = $buyer_info['nick_name'];
            }

            $source_distribution_name = '';
            if($this->uid == 5546 && $source_distribution == 4560) {
                $source_distribution_name = 'SHOPAL分销新零售事业部';
            }

            if($this->uid == 5553 && $source_distribution == 4560){
                $source_distribution_name = 'SHOPAL分销Ponroy事业部';
            }

            if($this->uid == 5554 && $source_distribution == 4560){
                $source_distribution_name = 'SHOPAL分销新兴品牌事业部';
            }

            if($this->uid == 5555 && $source_distribution == 4560){
                $source_distribution_name = 'SHOPAL分销总代事业部';
            }

            $data_order = array(
                'order_type' => $order_type,
                'order_no' => $this->createOrderNo($shop_id),
                'out_trade_no' => $out_trade_no,
                'payment_type' => $pay_type,
                'shipping_type' => $shipping_type,
                'order_from' => $order_from,
                'buyer_id' => $this->uid,
                'user_name' => $user_name,
                'buyer_ip' => $buyer_ip,
                'buyer_message' => $buyer_message,
                'buyer_invoice' => $buyer_invoice,
                'shipping_time' => $shipping_time, // datetime NOT NULL COMMENT '买家要求配送时间',
                'receiver_mobile' => $receiver_mobile, // varchar(11) NOT NULL DEFAULT '' COMMENT '收货人的手机号码',
                'receiver_province' => $receiver_province, // int(11) NOT NULL COMMENT '收货人所在省',
                'receiver_city' => $receiver_city, // int(11) NOT NULL COMMENT '收货人所在城市',
                'receiver_district' => $receiver_district, // int(11) NOT NULL COMMENT '收货人所在街道',
                'receiver_address' => $receiver_address, // varchar(255) NOT NULL DEFAULT '' COMMENT '收货人详细地址',
                'receiver_zip' => $receiver_zip, // varchar(6) NOT NULL DEFAULT '' COMMENT '收货人邮编',
                'receiver_name' => $receiver_name, // varchar(50) NOT NULL DEFAULT '' COMMENT '收货人姓名',
                'shop_id' => $shop_id, // int(11) NOT NULL COMMENT '卖家店铺id',
                'shop_name' => $shop_name, // varchar(100) NOT NULL DEFAULT '' COMMENT '卖家店铺名称',
                'goods_money' => $count_money, // decimal(19, 2) NOT NULL COMMENT '商品总价',
                'tax_money' => $tax_money, // 税费
                'order_money' => $order_money, // decimal(10, 2) NOT NULL COMMENT '订单总价',
                'point' => 0, // int(11) NOT NULL COMMENT '订单消耗积分',
                'point_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单消耗积分抵多少钱',
                'coupon_money' => 0, // _money decimal(10, 2) NOT NULL COMMENT '订单代金券支付金额',
                'coupon_id' => 0, // int(11) NOT NULL COMMENT '订单代金券id',
                'user_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单预存款支付金额',
                'promotion_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单优惠活动金额',
                'shipping_money' => $deliver_price, // decimal(10, 2) NOT NULL COMMENT '订单运费',
                'pay_money' => $order_money, // decimal(10, 2) NOT NULL COMMENT '订单实付金额',
                'refund_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单退款金额',
                'give_point' => 0, // int(11) NOT NULL COMMENT '订单赠送积分',
                'order_status' => 0, // tinyint(4) NOT NULL COMMENT '订单状态',
                'pay_status' => 0, // tinyint(4) NOT NULL COMMENT '订单付款状态',
                'shipping_status' => 0, // tinyint(4) NOT NULL COMMENT '订单配送状态',
                'review_status' => 0, // tinyint(4) NOT NULL COMMENT '订单评价状态',
                'feedback_status' => 0, // tinyint(4) NOT NULL COMMENT '订单维权状态',
                'user_platform_money' => 0, // 平台余额支付
                'coin_money' => $coin,
                'tx_type' => $tx_type,  // 交易类型（1：大贸，2：跨境）
                'is_inside' => 0, //是否内购(0:否,2:是)
                'card_name' => $card_name, // 身份证姓名
                'card_no' => $card_no, // 身份证号码
                'create_time' => time(),
                "give_point_type" => $give_point_type,
                'shipping_company_id' => $shipping_company_id,
                'fixed_telephone' => $fixed_telephone, // 固定电话
                'source_branch' => $source_branch,
                'parent_source_distribution' => $parent_source_distribution,
                'distributor_type' => $distributor_type,
                'source_distribution' => $source_distribution,
                'inviter' => $inviter,
                'source_distribution_name'=>$source_distribution_name
            );

            $order = new NsOrderModel();
            $order->save($data_order);
            $order_id = $order->order_id;

            $pay = new UnifyPay();
            $pay->createPayment($shop_id, $out_trade_no, $shop_name . "订单", $shop_name . "订单", $order_money, 1, $order_id);

            //
            if (!empty($card_no) && !empty($card_name)) {
                $data   = array(
                    'card_no' => $card_no,
                    'card_name' => $card_name
                );
                $where  = array('uid' => $this->uid);
                $user   = new UserModel();
                $retval = $user->save($data, $where);
                if (!($retval > 0)) {
                    $this->order->rollback();
                    return $retval;
                }
            }


            $goods_mansong          = new GoodsMansong();
            $mansong_array          = $goods_mansong->getShareGoodsSkuListMansong($goods_sku_list);
            $promotion_money        = 0;
            $mansong_rule_array     = array();
            $mansong_discount_array = array();

            if (!empty($mansong_array)) {
                $manson_gift_temp_array = array();
                $gift_num_arr           = array();
                foreach ($mansong_array as $k_mansong => $v_mansong) {
                    foreach ($v_mansong['discount_detail'] as $k_rule => $v_rule) {
                        $rule                     = $v_rule[1];
                        $discount_money_detail    = explode(':', $rule);
                        $mansong_discount_array[] = array(
                            $discount_money_detail[0],
                            $discount_money_detail[1],
                            $v_rule[0]['rule_id']
                        );
                        $promotion_money += $discount_money_detail[1]; // round($discount_money_detail[1],2);
                        // 添加优惠活动信息
                        $mansong_rule_array[] = $v_rule[0];

                        $gift_id = $v_rule[0]['gift_id'];

                        #todo  @dai  设置多个赠品  ......
                        if ($gift_id > 0) {
                            if (strpos($gift_id, ',') !== false) {
                                #多个赠品
                                $gift_id = explode(',', $gift_id);
                                $num     = count($gift_id);
                                for ($i = 0; $i < $num; $i++) {
                                    $_v          = explode(':', $gift_id[$i]);
                                    $gift_info   = \think\Db::name('ns_promotion_gift')->where(['gift_id' => $_v[0]])->find();
                                    $can_use_num = $gift_info['gift_num'] - $gift_info['gift_song_num'];
                                    if ($can_use_num < $_v[1]) {
                                        #原赠品不足
                                        $replace_info = \think\Db::name('ns_promotion_gift_replace')->where(['gift_id' => $_v[0]])->find();
                                        if (!empty($replace_info)) {
                                            # 有补充赠品
                                            $new_gift_info   = \think\Db::name('ns_promotion_gift')->where(['gift_id' => $replace_info['n_gift_id']])->find();
                                            $new_can_use_num = $new_gift_info['gift_num'] - $new_gift_info['gift_song_num'];
                                            if ($can_use_num > 0) {
                                                # 原赠品 + 补充赠品
                                                array_push($manson_gift_temp_array, $_v[0]);
                                                array_push($gift_num_arr, $can_use_num);

                                                $_can_use_num = (($_v[1] - $can_use_num) * $replace_info['n_num'] >= $new_can_use_num) ? $new_can_use_num : ($_v[1] - $can_use_num) * $replace_info['n_num'];
                                                if ($_can_use_num > 0) {
                                                    array_push($manson_gift_temp_array, $new_gift_info['gift_id']);
                                                    array_push($gift_num_arr, $_can_use_num);
                                                }
                                            } else {
                                                # 全补充赠品
                                                if ($new_can_use_num >= $_v[1] * $replace_info['n_num']) {
                                                    #补充赠品足够
                                                    array_push($manson_gift_temp_array, $new_gift_info['gift_id']);
                                                    array_push($gift_num_arr, $_v[1] * $replace_info['n_num']);
                                                } else {
                                                    # 补充赠品不足
                                                    if ($new_can_use_num != 0) {
                                                        array_push($manson_gift_temp_array, $new_gift_info['gift_id']);
                                                        array_push($gift_num_arr, $new_can_use_num);
                                                    }
                                                }
                                            }
                                        } else {
                                            # 无补充
                                            if ($can_use_num > 0) {
                                                array_push($manson_gift_temp_array, $_v[0]);
                                                array_push($gift_num_arr, $can_use_num);
                                            }
                                        }

                                    } else {
                                        # 原赠品充足
                                        array_push($manson_gift_temp_array, $_v[0]);
                                        array_push($gift_num_arr, $_v[1]);
                                    }
                                }
                            } else {
                                $_v          = explode(':', $gift_id);
                                $gift_info   = \think\Db::name('ns_promotion_gift')->where(['gift_id' => $_v[0]])->find();
                                $can_use_num = $gift_info['gift_num'] - $gift_info['gift_song_num'];
                                if ($can_use_num < $_v[1]) {
                                    #原赠品不足
                                    $replace_info = \think\Db::name('ns_promotion_gift_replace')->where(['gift_id' => $_v[0]])->find();
                                    if (!empty($replace_info)) {
                                        # 有补充赠品
                                        $new_gift_info   = \think\Db::name('ns_promotion_gift')->where(['gift_id' => $replace_info['n_gift_id']])->find();
                                        $new_can_use_num = $new_gift_info['gift_num'] - $new_gift_info['gift_song_num'];
                                        if ($can_use_num > 0) {
                                            # 原赠品 + 补充赠品
                                            array_push($manson_gift_temp_array, $_v[0]);
                                            array_push($gift_num_arr, $can_use_num);

                                            $_can_use_num = (($_v[1] - $can_use_num) * $replace_info['n_num'] >= $new_can_use_num) ? $new_can_use_num : ($_v[1] - $can_use_num) * $replace_info['n_num'];
                                            if ($_can_use_num > 0) {
                                                array_push($manson_gift_temp_array, $new_gift_info['gift_id']);
                                                array_push($gift_num_arr, $_can_use_num);
                                            }
                                        } else {
                                            # 全补充赠品
                                            if ($new_can_use_num >= $_v[1] * $replace_info['n_num']) {
                                                #补充赠品足够
                                                array_push($manson_gift_temp_array, $new_gift_info['gift_id']);
                                                array_push($gift_num_arr, $_v[1] * $replace_info['n_num']);
                                            } else {
                                                # 补充赠品不足
                                                if ($new_can_use_num != 0) {
                                                    array_push($manson_gift_temp_array, $new_gift_info['gift_id']);
                                                    array_push($gift_num_arr, $new_can_use_num);
                                                }
                                            }
                                        }
                                    } else {
                                        # 无补充
                                        if ($can_use_num > 0) {
                                            array_push($manson_gift_temp_array, $_v[0]);
                                            array_push($gift_num_arr, $can_use_num);
                                        }
                                    }
                                } else {
                                    # 原赠品充足
                                    array_push($manson_gift_temp_array, $_v[0]);
                                    array_push($gift_num_arr, $_v[1]);
                                }
                            }
                        }
                    }
                }

            }


            // 添加赠品
            #todo @dai
            #   id $manson_gift_temp_array  num $gift_num_arr

            if (is_array($manson_gift_temp_array)) {
                $manson_gift_temp_array = array_unique($manson_gift_temp_array);
            }
            if (!empty($manson_gift_temp_array)) {
                $promotion   = new Promotion();
                $order_goods = new OrderGoods();

//                    for($i = 0; $i < count($manson_gift_temp_array); $i++){
                $_arr = array();
                foreach ($manson_gift_temp_array as $key => $v) {
                    $maoson_gift_goods_sku = $promotion->getGoodsSkuByGiftId($v, $gift_num_arr[$key]);
                    array_push($_arr, $maoson_gift_goods_sku);
                }
                if (!empty($_arr)) {
                    // 添加订单赠品项
                    $res_order_goods = $order_goods->addOrderGiftGoods($order_id, $_arr);
                }

            }


            // 添加订单项
            $order_goods     = new OrderGoods();
            $res_order_goods = $order_goods->addShareOrderGoods($order_id, $goods_sku_list, $distributor_type, $parent_source_distribution);

            if (!($res_order_goods > 0)) {
                $this->order->rollback();
                return $res_order_goods;
            }
            $this->addOrderAction($order_id, $this->uid, '创建订单');

            $this->order->commit();
            return $order_id;
        } catch (\Exception $e) {
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    //付费会员领取免费商品
    public function vipGetGoodsOrder($order_type, $out_trade_no, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $goods_id, $source_branch)
    {
        $this->order->startTrans();
        try {
            // 查询商品sku
            $goods_sku      = new NsGoodsSkuModel();
            $info           = $goods_sku->getInfo([
                'goods_id' => $goods_id
            ], 'sku_id');
            $goods_sku_list = $info['sku_id'] . ':1';
            // 查询商品对应的店铺ID
            $order_goods_preference = new GoodsPreference();
            $shop_id                = $order_goods_preference->getGoodsSkuListShop($goods_sku_list);

            // 单店版查询网站内容
            $web_site  = new WebSite();
            $web_info  = $web_site->getWebSiteInfo();
            $shop_name = $web_info['title'];

            $coupon_money = 0; // 获取优惠券金额

            // 获取购买人信息
            $buyer      = new UserModel();
            $buyer_info = $buyer->getInfo([
                'uid' => $this->uid
            ], 'nick_name');

            $goods_money  = $order_goods_preference->getGoodsSkuListPrice($goods_sku_list); //获取商品sku列表价格
            $order_status = 0; //订单待支付

            $data_order = array(
                'order_type' => $order_type,// 订单类型
                'order_no' => $this->createOrderNo($shop_id),// 订单编号
                'out_trade_no' => $out_trade_no, // 外部交易号（商户订单号）
                'payment_type' => 1,// 支付方式
                'shipping_type' => 1,// 订单配送方式
                'order_from' => 1, // 订单来源 1:微信
                'buyer_id' => $this->uid,// 买家id
                'user_name' => $buyer_info['nick_name'],// 买家会员名称
                'buyer_ip' => 1,// 买家ip
                'buyer_message' => "", // 买家附言
                'buyer_invoice' => "", // 买家发票信息
                'shipping_time' => 0, // datetime NOT NULL COMMENT '买家要求配送时间',
                'receiver_mobile' => $receiver_mobile, // varchar(11) NOT NULL DEFAULT '' COMMENT '收货人的手机号码',
                'receiver_province' => $receiver_province, // int(11) NOT NULL COMMENT '收货人所在省',
                'receiver_city' => $receiver_city, // int(11) NOT NULL COMMENT '收货人所在城市',
                'receiver_district' => $receiver_district, // int(11) NOT NULL COMMENT '收货人所在街道',
                'receiver_address' => $receiver_address, // varchar(255) NOT NULL DEFAULT '' COMMENT '收货人详细地址',
                'receiver_zip' => $receiver_zip, // varchar(6) NOT NULL DEFAULT '' COMMENT '收货人邮编',
                'receiver_name' => $receiver_name, // varchar(50) NOT NULL DEFAULT '' COMMENT '收货人姓名',
                'shop_id' => $shop_id, // int(11) NOT NULL COMMENT '卖家店铺id',
                'shop_name' => $shop_name, // varchar(100) NOT NULL DEFAULT '' COMMENT '卖家店铺名称',
                'goods_money' => $goods_money, // decimal(19, 2) NOT NULL COMMENT '商品总价',
                'tax_money' => 0, // 税费
                'order_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单总价',
                'point' => 0, // int(11) NOT NULL COMMENT '订单消耗积分',
                'point_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单消耗积分抵多少钱',
                'coupon_money' => 0, // _money decimal(10, 2) NOT NULL COMMENT '订单代金券支付金额',
                'coupon_id' => 0, // int(11) NOT NULL COMMENT '订单代金券id',
                'user_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单预存款支付金额',
                'promotion_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单优惠活动金额',
                'shipping_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单运费',
                'pay_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单实付金额',
                'refund_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单退款金额',
                'give_point' => 0, // int(11) NOT NULL COMMENT '订单赠送积分',
                'order_status' => 0, // tinyint(4) NOT NULL COMMENT '订单状态',
                'pay_status' => 0, // tinyint(4) NOT NULL COMMENT '订单付款状态',
                'shipping_status' => 0, // tinyint(4) NOT NULL COMMENT '订单配送状态',
                'review_status' => 0, // tinyint(4) NOT NULL COMMENT '订单评价状态',
                'feedback_status' => 0, // tinyint(4) NOT NULL COMMENT '订单维权状态',
                'user_platform_money' => 0, // 平台余额支付
                'coin_money' => 0, // 购物币金额
                'tx_type' => 1,  // 交易类型（1：大贸，2：跨境）
                'card_name' => "", // 身份证姓名
                'card_no' => "", // 身份证号码
                'create_time' => time(),// 订单创建时间
                "give_point_type" => 1, // 积分返还类型 1 订单完成  2 订单收货 3  支付订单
                'shipping_company_id' => 0,// 配送物流公司ID
                'fixed_telephone' => "", //固定电话
                'source_branch' => $source_branch
            );
            $order      = new NsOrderModel();
            $order->save($data_order);
            $order_id = $order->order_id;
            $pay      = new UnifyPay();
            $pay->createPayment($shop_id, $out_trade_no, $shop_name . "订单", $shop_name . "订单", 0, 1, $order_id);
            // 添加订单项
            $order_goods     = new OrderGoods();
            $res_order_goods = $order_goods->addOrderGoods($order_id, $goods_sku_list, -1);

            if (!($res_order_goods > 0)) {
                $this->order->rollback();
                return $res_order_goods;
            }

            $member = new NsMemberModel();
            $retval = $member->save(["vip_goods" => 1], ["uid" => $this->uid]);
            if (!($retval > 0)) {
                $this->order->rollback();
                return $retval;
            }
            $this->addOrderAction($order_id, $this->uid, '创建订单');
            $this->order->commit();
            return $order_id;
        } catch (\Exception $e) {
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    //付费会员领取免费礼品
    public function vipGetGiftOrder($order_type, $out_trade_no, $goods_id, $source_branch)
    {
        $this->order->startTrans();
        try {
            // 查询商品sku
            $goods_sku      = new NsGoodsSkuModel();
            $info           = $goods_sku->getInfo([
                'goods_id' => $goods_id
            ], 'sku_id');
            $goods_sku_list = $info['sku_id'] . ':1';
            // 查询商品对应的店铺ID
            $order_goods_preference = new GoodsPreference();
            $shop_id                = $order_goods_preference->getGoodsSkuListShop($goods_sku_list);

            // 单店版查询网站内容
            $web_site  = new WebSite();
            $web_info  = $web_site->getWebSiteInfo();
            $shop_name = $web_info['title'];

            $coupon_money = 0; // 获取优惠券金额

            // 获取购买人信息
            $buyer      = new UserModel();
            $buyer_info = $buyer->getInfo([
                'uid' => $this->uid
            ], 'nick_name');

            $goods_money  = $order_goods_preference->getGoodsSkuListPrice($goods_sku_list); //获取商品sku列表价格
            $order_status = 0; //订单待支付

            $data_order = array(
                'order_type' => $order_type,// 订单类型
                'order_no' => $this->createOrderNo($shop_id),// 订单编号
                'out_trade_no' => $out_trade_no, // 外部交易号（商户订单号）
                'payment_type' => 1,// 支付方式
                'shipping_type' => 1,// 订单配送方式
                'order_from' => 1, // 订单来源 1:微信
                'buyer_id' => $this->uid,// 买家id
                'user_name' => $buyer_info['nick_name'],// 买家会员名称
                'buyer_ip' => 1,// 买家ip
                'buyer_message' => "", // 买家附言
                'buyer_invoice' => "", // 买家发票信息
                'shipping_time' => 0, // datetime NOT NULL COMMENT '买家要求配送时间',
                'shop_id' => $shop_id, // int(11) NOT NULL COMMENT '卖家店铺id',
                'shop_name' => $shop_name, // varchar(100) NOT NULL DEFAULT '' COMMENT '卖家店铺名称',
                'goods_money' => $goods_money, // decimal(19, 2) NOT NULL COMMENT '商品总价',
                'tax_money' => 0, // 税费
                'order_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单总价',
                'point' => 0, // int(11) NOT NULL COMMENT '订单消耗积分',
                'point_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单消耗积分抵多少钱',
                'coupon_money' => 0, // _money decimal(10, 2) NOT NULL COMMENT '订单代金券支付金额',
                'coupon_id' => 0, // int(11) NOT NULL COMMENT '订单代金券id',
                'user_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单预存款支付金额',
                'promotion_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单优惠活动金额',
                'shipping_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单运费',
                'pay_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单实付金额',
                'refund_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单退款金额',
                'give_point' => 0, // int(11) NOT NULL COMMENT '订单赠送积分',
                'order_status' => 0, // tinyint(4) NOT NULL COMMENT '订单状态',
                'pay_status' => 0, // tinyint(4) NOT NULL COMMENT '订单付款状态',
                'shipping_status' => 0, // tinyint(4) NOT NULL COMMENT '订单配送状态',
                'review_status' => 0, // tinyint(4) NOT NULL COMMENT '订单评价状态',
                'feedback_status' => 0, // tinyint(4) NOT NULL COMMENT '订单维权状态',
                'user_platform_money' => 0, // 平台余额支付
                'coin_money' => 0, // 购物币金额
                'tx_type' => 1,  // 交易类型（1：大贸，2：跨境）
                'card_name' => "", // 身份证姓名
                'card_no' => "", // 身份证号码
                'create_time' => time(),// 订单创建时间
                "give_point_type" => 1, // 积分返还类型 1 订单完成  2 订单收货 3  支付订单
                'shipping_company_id' => 0,// 配送物流公司ID
                'fixed_telephone' => "", //固定电话
                'source_branch' => $source_branch  //门店id
            );
            $order      = new NsOrderModel();
            $order->save($data_order);
            $order_id = $order->order_id;
            $pay      = new UnifyPay();
            $pay->createPayment($shop_id, $out_trade_no, $shop_name . "订单", $shop_name . "订单", 0, 1, $order_id);
            // 添加订单项
            $order_goods     = new OrderGoods();
            $res_order_goods = $order_goods->addOrderGoods($order_id, $goods_sku_list, -1);

            if (!($res_order_goods > 0)) {
                $this->order->rollback();
                return $res_order_goods;
            }

            $member = new NsMemberModel();
            $retval = $member->save(["vip_gift" => 1], ["uid" => $this->uid]);
            if (!($retval > 0)) {
                $this->order->rollback();
                return $retval;
            }
            $this->addOrderAction($order_id, $this->uid, '创建订单');
            $this->order->commit();
            return $order_id;
        } catch (\Exception $e) {
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    public function giftOrderCreate($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $point, $coupon_id, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $tx_type, $card_name, $card_no, $source_branch, $coin)
    {
        $this->order->startTrans();

        try {
            // 设定不使用会员余额支付
            $user_money = 0;
            // 查询商品对应的店铺ID
            $order_goods_preference = new GoodsPreference();
            $shop_id                = $order_goods_preference->getGoodsSkuListShop($goods_sku_list);
            // 单店版查询网站内容
            $web_site  = new WebSite();
            $web_info  = $web_site->getWebSiteInfo();
            $shop_name = $web_info['title'];
            // 获取优惠券金额
            $coupon       = new MemberCoupon();
            $coupon_money = $coupon->getCouponMoney($coupon_id);

            // 获取购买人信息
            $buyer      = new UserModel();
            $buyer_info = $buyer->getInfo([
                'uid' => $this->uid
            ], 'nick_name');
            // 订单商品费用

            $goods_money = $order_goods_preference->getGoodsSkuListPrice($goods_sku_list);  //订单的商品总价
            $point       = $order_goods_preference->getGoodsListExchangePoint($goods_sku_list);   //订单的积分总值

            // 积分兑换抵用金额
            $account_flow = new MemberAccount();
            /*
             * $point_money = $order_goods_preference->getPointMoney($point, $shop_id);
             */
            $point_money = 0;

            // 订单来源
            if (isWeixin()) {
                $order_from = 1; // 微信
            } elseif (request()->isMobile()) {
                $order_from = 2; // 手机
            } else {
                $order_from = 3; // 电脑
            }
            // 订单支付方式

            // 订单待支付
            $order_status = 0;
            // 购买商品获取积分数
            $give_point = $order_goods_preference->getGoodsSkuListGivePoint($goods_sku_list);
            // 订单满减送活动优惠
            $goods_mansong          = new GoodsMansong();
            $mansong_array          = $goods_mansong->getGoodsSkuListMansong($goods_sku_list);
            $promotion_money        = 0;
            $mansong_rule_array     = array();
            $mansong_discount_array = array();
            $manson_gift_array      = array(); // 赠品[id]=>数量

            if (!empty($mansong_array)) {
                $manson_gift_temp_array = array();
                $gift_num_arr           = array();
                foreach ($mansong_array as $k_mansong => $v_mansong) {
                    foreach ($v_mansong['discount_detail'] as $k_rule => $v_rule) {
                        $rule                     = $v_rule[1];
                        $discount_money_detail    = explode(':', $rule);
                        $mansong_discount_array[] = array(
                            $discount_money_detail[0],
                            $discount_money_detail[1],
                            $v_rule[0]['rule_id']
                        );
                        $promotion_money += $discount_money_detail[1]; // round($discount_money_detail[1],2);
                        // 添加优惠活动信息
                        $mansong_rule_array[] = $v_rule[0];

                        $gift_id = $v_rule[0]['gift_id'];

                        #todo  @dai  设置多个赠品  ......
                        if ($gift_id > 0) {
                            if (strpos($gift_id, ',') !== false) {
                                $gift_id = explode(',', $gift_id);
                                $num     = count($gift_id);
                                for ($i = 0; $i < $num; $i++) {
                                    $_v = explode(':', $gift_id[$i]);
                                    array_push($manson_gift_temp_array, $_v[0]);
                                    array_push($gift_num_arr, $_v[1]);
                                }
                            } else {
                                $_v = explode(':', $gift_id);
                                array_push($manson_gift_temp_array, $_v[0]);
                                array_push($gift_num_arr, $_v[1]);
                            }
                        }

                    }
                }
                $promotion_money = round($promotion_money, 2);
//                $manson_gift_array = array_count_values($manson_gift_temp_array);
                // $manson_gift_array = array('3'=>1); // 赠品[id]=>数量,暂时写死
            }


            // 订单费用(具体计算)
            $order_money = $goods_money - $promotion_money - $coupon_money - $point_money;

            if ($order_money < 0) {
                $order_money    = 0;
                $user_money     = 0;
                $platform_money = 0;
            }

            if (!empty($buyer_invoice)) {
                // 添加发票税费
                $config    = new Config();
                $tax_value = $config->getConfig(0, 'ORDER_INVOICE_TAX');
                if (empty($tax_value['value'])) {
                    $tax = 0;
                } else {
                    $tax = $tax_value['value'];
                }
                $tax_money = $order_money * $tax / 100;
            } else {
                $tax_money = 0;
            }
            $order_money = $order_money + $tax_money;

            if ($order_money < $platform_money) {
                $platform_money = $order_money;
            }
            // $card_money = round($card_money, 2);
            $pay_money = $order_money - $user_money - $platform_money;
            if ($pay_money <= 0) {
                $pay_money    = 0;
                $order_status = 0;
                $pay_status   = 0;
            } else {
                $order_status = 0;
                $pay_status   = 0;
            }

            // 积分返还类型
            $config          = new ConfigModel();
            $config_info     = $config->getInfo([
                "instance_id" => $shop_id,
                "key" => "SHOPPING_BACK_POINTS"
            ], "value");
            $give_point_type = $config_info["value"];

            // 店铺名称

            $data_order = array(
                'order_type' => $order_type, // 订单类型
                'order_no' => $this->createOrderNo($shop_id), // 订单编号
                'out_trade_no' => $out_trade_no, // 外部交易号（商户订单号）
                'payment_type' => $pay_type,// 支付方式
                'shipping_type' => $shipping_type, // 订单配送方式
                'order_from' => $order_from, // 订单来源
                'buyer_id' => $this->uid, // 买家id
                'user_name' => $buyer_info['nick_name'], // 买家会员名称
                'buyer_ip' => $buyer_ip, // 买家ip
                'buyer_message' => $buyer_message, // 买家附言
                'buyer_invoice' => $buyer_invoice, // 买家发票信息
                'shipping_time' => $shipping_time, // datetime NOT NULL COMMENT '买家要求配送时间',
                'shop_id' => $shop_id, // int(11) NOT NULL COMMENT '卖家店铺id',
                'shop_name' => $shop_name, // varchar(100) NOT NULL DEFAULT '' COMMENT '卖家店铺名称',
                'goods_money' => $goods_money, // decimal(19, 2) NOT NULL COMMENT '商品总价',
                'tax_money' => $tax_money, // 税费
                'order_money' => $order_money, // decimal(10, 2) NOT NULL COMMENT '订单总价',
                'point' => $point, // int(11) NOT NULL COMMENT '订单消耗积分',
                'point_money' => $point_money, // decimal(10, 2) NOT NULL COMMENT '订单消耗积分抵多少钱',
                'coupon_money' => $coupon_money, // _money decimal(10, 2) NOT NULL COMMENT '订单代金券支付金额',
                'coupon_id' => $coupon_id, // int(11) NOT NULL COMMENT '订单代金券id',
                'user_money' => $user_money, // decimal(10, 2) NOT NULL COMMENT '订单预存款支付金额',
                'promotion_money' => $promotion_money, // decimal(10, 2) NOT NULL COMMENT '订单优惠活动金额',
                'shipping_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单运费',
                'pay_money' => $pay_money, // decimal(10, 2) NOT NULL COMMENT '订单实付金额',
                'refund_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单退款金额',
                'give_point' => $give_point, // int(11) NOT NULL COMMENT '订单赠送积分',
                'order_status' => $order_status, // tinyint(4) NOT NULL COMMENT '订单状态',
                'pay_status' => $pay_status, // tinyint(4) NOT NULL COMMENT '订单付款状态',
                'shipping_status' => 0, // tinyint(4) NOT NULL COMMENT '订单配送状态',
                'review_status' => 0, // tinyint(4) NOT NULL COMMENT '订单评价状态',
                'feedback_status' => 0, // tinyint(4) NOT NULL COMMENT '订单维权状态',
                'user_platform_money' => $platform_money, // 平台余额支付
                'coin_money' => $coin, // 购物币金额
                'tx_type' => $tx_type,  // 交易类型（1：大贸，2：跨境）
                'card_name' => $card_name, // 身份证姓名
                'card_no' => $card_no, // 身份证号码
                'create_time' => time(), // 订单创建时间
                "give_point_type" => $give_point_type, // 积分返还类型 1 订单完成  2 订单收货 3  支付订单
                'shipping_company_id' => $shipping_company_id, // 配送物流公司ID
                'source_branch' => $source_branch,
            );
            if ($pay_status == 2) {
                $data_order["pay_time"] = time();
            }
            $order = new NsOrderModel();
            $order->save($data_order);
            $order_id = $order->order_id;
            $pay      = new UnifyPay();
            $pay->createPayment($shop_id, $out_trade_no, $shop_name . "订单", $shop_name . "订单", $pay_money, 1, $order_id);

            // 满减送详情，添加满减送活动优惠情况
            if (!empty($mansong_rule_array)) {
                $mansong_rule_array = array_unique($mansong_rule_array);
                foreach ($mansong_rule_array as $k_mansong_rule => $v_mansong_rule) {
                    $order_promotion_details = new NsOrderPromotionDetailsModel();
                    $data_promotion_details  = array(
                        'order_id' => $order_id,
                        'promotion_id' => $v_mansong_rule['rule_id'],
                        'promotion_type_id' => 1,
                        'promotion_type' => 'MANJIAN',
                        'promotion_name' => '满减送活动',
                        'promotion_condition' => '满' . $v_mansong_rule['price'] . '元，减' . $v_mansong_rule['discount'],
                        'discount_money' => $v_mansong_rule['discount'],
                        'used_time' => time()
                    );
                    $order_promotion_details->save($data_promotion_details);
                }
                // 添加到对应商品项优惠满减
                if (!empty($mansong_discount_array)) {
                    foreach ($mansong_discount_array as $k => $v) {
                        $order_goods_promotion_details = new NsOrderGoodsPromotionDetailsModel();
                        $data_details                  = array(
                            'order_id' => $order_id,
                            'promotion_id' => $v[2],
                            'sku_id' => $v[0],
                            'promotion_type' => 'MANJIAN',
                            'discount_money' => $v[1],
                            'used_time' => time()
                        );
                        $order_goods_promotion_details->save($data_details);
                    }
                }

                // 添加赠品
                if (!empty($manson_gift_temp_array)) {
                    $promotion   = new Promotion();
                    $order_goods = new OrderGoods();
//                    $manson_gift_temp_array = array_unique($manson_gift_temp_array);
                    for ($i = 0; $i < count($manson_gift_temp_array); $i++) {
                        $maoson_gift_goods_sku = $promotion->getGoodsSkuByGiftId($manson_gift_temp_array[$i], $gift_num_arr[$i]);
                        if (!empty($maoson_gift_goods_sku)) {
                            // 添加订单赠品项
                            $res_order_goods = $order_goods->addOrderGiftGoods($order_id, $maoson_gift_goods_sku);
                        }
                    }

//
//                    foreach ($manson_gift_array as $gift_id => $num) {
//                        $maoson_gift_goods_sku = $promotion->getGoodsSkuByGiftId($gift_id, $num);
//                        if (! empty($maoson_gift_goods_sku)) {
//                            // 添加订单赠品项
//                            $res_order_goods = $order_goods->addOrderGiftGoods($order_id, $maoson_gift_goods_sku);
//                        }
//                    }
                }
            }

            // 添加到对应商品项优惠优惠券使用详情
            if ($coupon_id > 0) {
                $coupon_details_array = $order_goods_preference->getGoodsCouponPromoteDetail($coupon_id, $coupon_money, $goods_sku_list);
                foreach ($coupon_details_array as $k => $v) {
                    $order_goods_promotion_details = new NsOrderGoodsPromotionDetailsModel();
                    $data_details                  = array(
                        'order_id' => $order_id,
                        'promotion_id' => $coupon_id,
                        'sku_id' => $v['sku_id'],
                        'promotion_type' => 'COUPON',
                        'discount_money' => $v['money'],
                        'used_time' => time()
                    );
                    $order_goods_promotion_details->save($data_details);
                }
            }

            // 使用积分
            if ($point > 0) {
                $retval_point = $account_flow->addMemberAccountData($shop_id, 1, $this->uid, 0, $point * (-1), 1, $order_id, '商城订单');
                if ($retval_point < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_POINT;
                }
            }

            if ($coin > 0) {
                $retval_point = $account_flow->addMemberAccountData($shop_id, 3, $this->uid, 0, $coin * (-1), 1, $order_id, '商城订单');
                if ($retval_point < 0) {
                    $this->order->rollback();
                    return LOW_COIN;
                }
            }

            if ($user_money > 0) {
                $retval_user_money = $account_flow->addMemberAccountData($shop_id, 2, $this->uid, 0, $user_money * (-1), 1, $order_id, '商城订单');
                if ($retval_user_money < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_USER_MONEY;
                }
            }

            if ($platform_money > 0) {
                $retval_platform_money = $account_flow->addMemberAccountData(0, 2, $this->uid, 0, $platform_money * (-1), 1, $order_id, '商城订单');
                if ($retval_platform_money < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_PLATFORM_MONEY;
                }
            }

            // 使用优惠券
            if ($coupon_id > 0) {
                $retval = $coupon->useCoupon($this->uid, $coupon_id, $order_id);
                if (!($retval > 0)) {
                    $this->order->rollback();
                    return $retval;
                }
            }

            //
            if (!empty($card_no) && !empty($card_name)) {
                $data   = array(
                    'card_no' => $card_no,
                    'card_name' => $card_name
                );
                $where  = array('uid' => $this->uid);
                $user   = new UserModel();
                $retval = $user->save($data, $where);
                if (!($retval > 0)) {
                    $this->order->rollback();
                    return $retval;
                }
            }

            // 添加订单项
            $order_goods     = new OrderGoods();
            $res_order_goods = $order_goods->addOrderGoods($order_id, $goods_sku_list);

            if (!($res_order_goods > 0)) {
                $this->order->rollback();
                return $res_order_goods;
            }
            $this->addOrderAction($order_id, $this->uid, '创建订单');

            $this->order->commit();
            return $order_id;
        } catch (\Exception $e) {
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    // 提交订单使用心意卷
    // function OrderUseCard($order_id, $card_id, $card_token,$card_money){
    //     $orderCard = new OrderCard();
    //     $data = $orderCard->getCard(['card_token' => $card_token]);
    //     if($data['order_id']){
    //         $res = $orderCard->deteleCard(['order_id' => $data['order_id']]);
    //         if($res){
    //             $data_close = array(
    //                 'order_status' => 5
    //             );
    //             $order_model = new NsOrderModel();
    //             $order_model->save($data_close, [
    //                 'order_id' => $data['order_id']
    //             ]);
    //         }else{
    //             return $retval = 0;
    //         }
    //     }
    //     $retval = $orderCard->useCard($order_id, $card_id, $card_token,$card_money);
    //     return $retval;
    // }

    /**
     * 订单创建（会员）
     */
    public function orderCreateVip($order_type, $out_trade_no, $goods_id, $card_name, $sex, $user_tel, $birthday, $source_branch)
    {
        $this->order->startTrans();

        try {
            // 查询商品sku
            $goods_sku      = new NsGoodsSkuModel();
            $info           = $goods_sku->getInfo([
                'goods_id' => $goods_id
            ], 'sku_id');
            $goods_sku_list = $info['sku_id'] . ':1';
            // 查询商品对应的店铺ID
            $order_goods_preference = new GoodsPreference();
            $shop_id                = $order_goods_preference->getGoodsSkuListShop($goods_sku_list);

            // 单店版查询网站内容
            $web_site  = new WebSite();
            $web_info  = $web_site->getWebSiteInfo();
            $shop_name = $web_info['title'];

            // 获取购买人信息
            $buyer        = new UserModel();
            $buyer_info   = $buyer->getInfo([
                'uid' => $this->uid
            ], 'nick_name');
            $goods_money  = $order_goods_preference->getGoodsSkuListPrice($goods_sku_list); // 订单商品费用
            $point        = $order_goods_preference->getGoodsListExchangePoint($goods_sku_list); //订单消耗积分
            $give_point   = $order_goods_preference->getGoodsSkuListGivePoint($goods_sku_list);// 订单赠送积分
            $order_status = 0; // 订单待支付
            $order_money  = $goods_money; // 订单费用(具体计算)
            if ($order_money < 0) {
                $order_money = 0;
            }

            $pay_money = $order_money;
            if ($pay_money <= 0) {
                $pay_money    = 0;
                $order_status = 0;
                $pay_status   = 0;
            } else {
                $order_status = 0;
                $pay_status   = 0;
            }

            $data_order = array(
                'order_type' => $order_type, // 订单类型
                'order_no' => $this->createOrderNo($shop_id), // 订单编号
                'out_trade_no' => $out_trade_no, // 外部交易号（商户订单号）
                'payment_type' => 1,// 支付方式
                'shipping_type' => 1, // 订单配送方式
                'order_from' => 1, // 订单来源
                'buyer_id' => $this->uid, // 买家id
                'user_name' => $buyer_info['nick_name'], // 买家会员名称
                'buyer_ip' => 1, // 买家ip
                'buyer_message' => "", // 买家附言
                'buyer_invoice' => "", // 买家发票信息
                'shipping_time' => 0, // datetime NOT NULL COMMENT '买家要求配送时间',
                'receiver_mobile' => $user_tel, // varchar(11) NOT NULL DEFAULT '' COMMENT '收货人的手机号码',
                'receiver_province' => '', // int(11) NOT NULL COMMENT '收货人所在省',
                'receiver_city' => '', // int(11) NOT NULL COMMENT '收货人所在城市',
                'receiver_district' => '', // int(11) NOT NULL COMMENT '收货人所在街道',
                'receiver_address' => '', // varchar(255) NOT NULL DEFAULT '' COMMENT '收货人详细地址',
                'receiver_zip' => '', // varchar(6) NOT NULL DEFAULT '' COMMENT '收货人邮编',
                'receiver_name' => '', // varchar(50) NOT NULL DEFAULT '' COMMENT '收货人姓名',
                'shop_id' => $shop_id, // int(11) NOT NULL COMMENT '卖家店铺id',
                'shop_name' => $shop_name, // varchar(100) NOT NULL DEFAULT '' COMMENT '卖家店铺名称',
                'goods_money' => $goods_money, // decimal(19, 2) NOT NULL COMMENT '商品总价',
                'tax_money' => 0, // 税费
                'order_money' => $order_money, // decimal(10, 2) NOT NULL COMMENT '订单总价',
                'point' => $point, // int(11) NOT NULL COMMENT '订单消耗积分',
                'point_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单消耗积分抵多少钱',
                'coupon_money' => 0, // _money decimal(10, 2) NOT NULL COMMENT '订单代金券支付金额',
                'coupon_id' => 0, // int(11) NOT NULL COMMENT '订单代金券id',
                'user_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单预存款支付金额',
                'promotion_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单优惠活动金额',
                'shipping_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单运费',
                'pay_money' => $pay_money, // decimal(10, 2) NOT NULL COMMENT '订单实付金额',
                'refund_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单退款金额',
                'give_point' => $give_point, // int(11) NOT NULL COMMENT '订单赠送积分',
                'order_status' => $order_status, // tinyint(4) NOT NULL COMMENT '订单状态',
                'pay_status' => $pay_status, // tinyint(4) NOT NULL COMMENT '订单付款状态',
                'shipping_status' => 0, // tinyint(4) NOT NULL COMMENT '订单配送状态',
                'review_status' => 0, // tinyint(4) NOT NULL COMMENT '订单评价状态',
                'feedback_status' => 0, // tinyint(4) NOT NULL COMMENT '订单维权状态',
                'user_platform_money' => 0, // 平台余额支付
                'coin_money' => 0, // 购物币金额
                'tx_type' => 1,  // 交易类型（1：大贸，2：跨境）
                'card_name' => $card_name, // 身份证姓名
                'card_no' => "", // 身份证号码
                'create_time' => time(), // 订单创建时间
                "give_point_type" => 1, // 积分返还类型 1 订单完成  2 订单收货 3  支付订单
                'shipping_company_id' => 0, // 配送物流公司ID
                'fixed_telephone' => "",
                'source_branch' => $source_branch
            );
            if ($pay_status == 2) {
                $data_order["pay_time"] = time();
            }
            $order = new NsOrderModel();
            $order->save($data_order);
            $order_id = $order->order_id;
            $pay      = new UnifyPay();
            $pay->createPayment($shop_id, $out_trade_no, $shop_name . "会员订单", $shop_name . "会员订单", $pay_money, 1, $order_id);

            // 添加订单项
            $order_goods     = new OrderGoods();
            $res_order_goods = $order_goods->addOrderGoods($order_id, $goods_sku_list);
            if (!($res_order_goods > 0)) {
                $this->order->rollback();
                return $res_order_goods;
            }

            //添加用户信息
            $data_user = array(
                'card_name' => $card_name,
                'sex' => $sex,
                'user_tel' => $user_tel,
                'birthday' => $birthday,
            );
            $where     = array('uid' => $this->uid);
            $user      = new UserModel();
            $retval    = $user->save($data_user, $where);
            if (!($retval > 0)) {
                $this->order->rollback();
                return $retval;
            }
            $this->addOrderAction($order_id, $this->uid, '会员订单');

            $this->order->commit();
            return $order_id;
        } catch (\Exception $e) {
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 订单创建（虚拟商品）
     */
    public function orderCreateVirtual($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $point, $coupon_id, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $user_telephone, $coin)
    {
        $this->order->startTrans();

        try {
            // 设定不使用会员余额支付
            $user_money = 0;
            // 查询商品对应的店铺ID
            $order_goods_preference = new GoodsPreference();
            $shop_id                = $order_goods_preference->getGoodsSkuListShop($goods_sku_list);
            // 单店版查询网站内容
            $web_site  = new WebSite();
            $web_info  = $web_site->getWebSiteInfo();
            $shop_name = $web_info['title'];
            // 获取优惠券金额
            $coupon       = new MemberCoupon();
            $coupon_money = $coupon->getCouponMoney($coupon_id);

            // 获取购买人信息
            $buyer      = new UserModel();
            $buyer_info = $buyer->getInfo([
                'uid' => $this->uid
            ], 'nick_name');
            // 订单商品费用

            $goods_money = $order_goods_preference->getGoodsSkuListPrice($goods_sku_list);
            $point       = $order_goods_preference->getGoodsListExchangePoint($goods_sku_list);

            // 积分兑换抵用金额
            $account_flow = new MemberAccount();
            $point_money  = 0;
            // 订单来源
            if (isWeixin()) {
                $order_from = 1; // 微信
            } elseif (request()->isMobile()) {
                $order_from = 2; // 手机
            } else {
                $order_from = 3; // 电脑
            }
            // 订单待支付
            $order_status = 0;
            // 购买商品获取积分数
            $give_point = $order_goods_preference->getGoodsSkuListGivePoint($goods_sku_list);
            // 订单满减送活动优惠
            $goods_mansong          = new GoodsMansong();
            $mansong_array          = $goods_mansong->getGoodsSkuListMansong($goods_sku_list);
            $promotion_money        = 0;
            $mansong_rule_array     = array();
            $mansong_discount_array = array();
            if (!empty($mansong_array)) {
                foreach ($mansong_array as $k_mansong => $v_mansong) {
                    foreach ($v_mansong['discount_detail'] as $k_rule => $v_rule) {
                        $rule                     = $v_rule[1];
                        $discount_money_detail    = explode(':', $rule);
                        $mansong_discount_array[] = array(
                            $discount_money_detail[0],
                            $discount_money_detail[1],
                            $v_rule[0]['rule_id']
                        );
                        $promotion_money += $discount_money_detail[1]; // round($discount_money_detail[1],2);
                        $mansong_rule_array[] = $v_rule[0];
                    }
                }
                $promotion_money = round($promotion_money, 2);
            }

            // 订单费用(具体计算)
            $order_money = $goods_money - $promotion_money - $coupon_money - $point_money;

            if ($order_money < 0) {
                $order_money    = 0;
                $user_money     = 0;
                $platform_money = 0;
            }

            if (!empty($buyer_invoice)) {
                // 添加税费
                $config    = new Config();
                $tax_value = $config->getConfig(0, 'ORDER_INVOICE_TAX');
                if (empty($tax_value['value'])) {
                    $tax = 0;
                } else {
                    $tax = $tax_value['value'];
                }
                $tax_money = $order_money * $tax / 100;
            } else {
                $tax_money = 0;
            }
            $order_money = $order_money + $tax_money;

            if ($order_money < $platform_money) {
                $platform_money = $order_money;
            }

            $pay_money = $order_money - $user_money - $platform_money;
            if ($pay_money <= 0) {
                $pay_money    = 0;
                $order_status = 0;
                $pay_status   = 0;
            } else {
                $order_status = 0;
                $pay_status   = 0;
            }

            // 积分返还类型
            $config          = new ConfigModel();
            $config_info     = $config->getInfo([
                "instance_id" => $shop_id,
                "key" => "SHOPPING_BACK_POINTS"
            ], "value");
            $give_point_type = $config_info["value"];

            $data_order = array(
                'order_type' => $order_type,
                'order_no' => $this->createOrderNo($shop_id),
                'out_trade_no' => $out_trade_no,
                'payment_type' => $pay_type,
                'shipping_type' => $shipping_type,
                'order_from' => $order_from,
                'buyer_id' => $this->uid,
                'user_name' => $buyer_info['nick_name'],
                'buyer_ip' => $buyer_ip,
                'buyer_message' => $buyer_message,
                'buyer_invoice' => $buyer_invoice,
                'shipping_time' => getTimeTurnTimeStamp($shipping_time), // datetime NOT NULL COMMENT '买家要求配送时间',
                'receiver_mobile' => $user_telephone, // varchar(11) NOT NULL DEFAULT '' COMMENT '收货人的手机号码',
                'receiver_province' => '', // int(11) NOT NULL COMMENT '收货人所在省',
                'receiver_city' => '', // int(11) NOT NULL COMMENT '收货人所在城市',
                'receiver_district' => '', // int(11) NOT NULL COMMENT '收货人所在街道',
                'receiver_address' => '', // varchar(255) NOT NULL DEFAULT '' COMMENT '收货人详细地址',
                'receiver_zip' => '', // varchar(6) NOT NULL DEFAULT '' COMMENT '收货人邮编',
                'receiver_name' => '', // varchar(50) NOT NULL DEFAULT '' COMMENT '收货人姓名',
                'shop_id' => $shop_id, // int(11) NOT NULL COMMENT '卖家店铺id',
                'shop_name' => $shop_name, // varchar(100) NOT NULL DEFAULT '' COMMENT '卖家店铺名称',
                'goods_money' => $goods_money, // decimal(19, 2) NOT NULL COMMENT '商品总价',
                'tax_money' => $tax_money, // 税费
                'order_money' => $order_money, // decimal(10, 2) NOT NULL COMMENT '订单总价',
                'point' => $point, // int(11) NOT NULL COMMENT '订单消耗积分',
                'point_money' => $point_money, // decimal(10, 2) NOT NULL COMMENT '订单消耗积分抵多少钱',
                'coupon_money' => $coupon_money, // _money decimal(10, 2) NOT NULL COMMENT '订单代金券支付金额',
                'coupon_id' => $coupon_id, // int(11) NOT NULL COMMENT '订单代金券id',
                'user_money' => $user_money, // decimal(10, 2) NOT NULL COMMENT '订单预存款支付金额',
                'promotion_money' => $promotion_money, // decimal(10, 2) NOT NULL COMMENT '订单优惠活动金额',
                'shipping_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单运费',
                'pay_money' => $pay_money, // decimal(10, 2) NOT NULL COMMENT '订单实付金额',
                'refund_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单退款金额',
                'give_point' => $give_point, // int(11) NOT NULL COMMENT '订单赠送积分',
                'order_status' => $order_status, // tinyint(4) NOT NULL COMMENT '订单状态',
                'pay_status' => $pay_status, // tinyint(4) NOT NULL COMMENT '订单付款状态',
                'shipping_status' => 0, // tinyint(4) NOT NULL COMMENT '订单配送状态',
                'review_status' => 0, // tinyint(4) NOT NULL COMMENT '订单评价状态',
                'feedback_status' => 0, // tinyint(4) NOT NULL COMMENT '订单维权状态',
                'user_platform_money' => $platform_money, // 平台余额支付
                'coin_money' => $coin,
                'create_time' => time(),
                "give_point_type" => $give_point_type,
                'shipping_company_id' => $shipping_company_id,
                'fixed_telephone' => ""
            ); // 固定电话
            // datetime NOT NULL DEFAULT 'CURRENT_TIMESTAMP' COMMENT '订单创建时间',
            if ($pay_status == 2) {
                $data_order["pay_time"] = time();
            }
            $order = new NsOrderModel();
            $order->save($data_order);
            $order_id = $order->order_id;
            $pay      = new UnifyPay();
            $pay->createPayment($shop_id, $out_trade_no, $shop_name . "虚拟订单", $shop_name . "虚拟订单", $pay_money, 1, $order_id);
            // 满减送详情，添加满减送活动优惠情况
            if (!empty($mansong_rule_array)) {

                $mansong_rule_array = array_unique($mansong_rule_array);
                foreach ($mansong_rule_array as $k_mansong_rule => $v_mansong_rule) {
                    $order_promotion_details = new NsOrderPromotionDetailsModel();
                    $data_promotion_details  = array(
                        'order_id' => $order_id,
                        'promotion_id' => $v_mansong_rule['rule_id'],
                        'promotion_type_id' => 1,
                        'promotion_type' => 'MANJIAN',
                        'promotion_name' => '满减送活动',
                        'promotion_condition' => '满' . $v_mansong_rule['price'] . '元，减' . $v_mansong_rule['discount'],
                        'discount_money' => $v_mansong_rule['discount'],
                        'used_time' => time()
                    );
                    $order_promotion_details->save($data_promotion_details);
                }
                // 添加到对应商品项优惠满减
                if (!empty($mansong_discount_array)) {
                    foreach ($mansong_discount_array as $k => $v) {
                        $order_goods_promotion_details = new NsOrderGoodsPromotionDetailsModel();
                        $data_details                  = array(
                            'order_id' => $order_id,
                            'promotion_id' => $v[2],
                            'sku_id' => $v[0],
                            'promotion_type' => 'MANJIAN',
                            'discount_money' => $v[1],
                            'used_time' => time()
                        );
                        $order_goods_promotion_details->save($data_details);
                    }
                }
            }
            // 添加到对应商品项优惠优惠券使用详情
            if ($coupon_id > 0) {
                $coupon_details_array = $order_goods_preference->getGoodsCouponPromoteDetail($coupon_id, $coupon_money, $goods_sku_list);
                foreach ($coupon_details_array as $k => $v) {
                    $order_goods_promotion_details = new NsOrderGoodsPromotionDetailsModel();
                    $data_details                  = array(
                        'order_id' => $order_id,
                        'promotion_id' => $coupon_id,
                        'sku_id' => $v['sku_id'],
                        'promotion_type' => 'COUPON',
                        'discount_money' => $v['money'],
                        'used_time' => time()
                    );
                    $order_goods_promotion_details->save($data_details);
                }
            }
            // 使用积分
            if ($point > 0) {
                $retval_point = $account_flow->addMemberAccountData($shop_id, 1, $this->uid, 0, $point * (-1), 1, $order_id, '商城虚拟订单');
                if ($retval_point < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_POINT;
                }
            }
            if ($coin > 0) {
                $retval_point = $account_flow->addMemberAccountData($shop_id, 3, $this->uid, 0, $coin * (-1), 1, $order_id, '商城虚拟订单');
                if ($retval_point < 0) {
                    $this->order->rollback();
                    return LOW_COIN;
                }
            }
            if ($user_money > 0) {
                $retval_user_money = $account_flow->addMemberAccountData($shop_id, 2, $this->uid, 0, $user_money * (-1), 1, $order_id, '商城虚拟订单');
                if ($retval_user_money < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_USER_MONEY;
                }
            }
            if ($platform_money > 0) {
                $retval_platform_money = $account_flow->addMemberAccountData(0, 2, $this->uid, 0, $platform_money * (-1), 1, $order_id, '商城虚拟订单');
                if ($retval_platform_money < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_PLATFORM_MONEY;
                }
            }
            // 使用优惠券
            if ($coupon_id > 0) {
                $retval = $coupon->useCoupon($this->uid, $coupon_id, $order_id);
                if (!($retval > 0)) {
                    $this->order->rollback();
                    return $retval;
                }
            }
            // 添加订单项
            $order_goods     = new OrderGoods();
            $res_order_goods = $order_goods->addOrderGoods($order_id, $goods_sku_list);
            if (!($res_order_goods > 0)) {
                $this->order->rollback();
                return $res_order_goods;
            }
            $this->addOrderAction($order_id, $this->uid, '创建虚拟订单');

            $this->order->commit();
            return $order_id;
        } catch (\Exception $e) {
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 订单创建（组合商品）
     *
     * @param unknown $order_type
     * @param unknown $out_trade_no
     * @param unknown $pay_type
     * @param unknown $shipping_type
     * @param unknown $order_from
     * @param unknown $buyer_ip
     * @param unknown $buyer_message
     * @param unknown $buyer_invoice
     * @param unknown $shipping_time
     * @param unknown $receiver_mobile
     * @param unknown $receiver_province
     * @param unknown $receiver_city
     * @param unknown $receiver_district
     * @param unknown $receiver_address
     * @param unknown $receiver_zip
     * @param unknown $receiver_name
     * @param unknown $point
     * @param unknown $coupon_id
     * @param unknown $user_money
     * @param unknown $goods_sku_list
     * @param unknown $platform_money
     * @param unknown $pick_up_id
     * @param unknown $shipping_company_id
     * @param unknown $coin
     * @param string $fixed_telephone
     */
    public function orderCreateComboPackage($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $point, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $coin, $fixed_telephone = "", $combo_package_id, $buy_num)
    {
        $this->order->startTrans();

        try {
            // 设定不使用会员余额支付
            $user_money = 0;
            // 查询商品对应的店铺ID
            $order_goods_preference = new GoodsPreference();
            $shop_id                = $order_goods_preference->getGoodsSkuListShop($goods_sku_list);
            // 单店版查询网站内容
            $web_site  = new WebSite();
            $web_info  = $web_site->getWebSiteInfo();
            $shop_name = $web_info['title'];

            // 获取组合套餐详情
            $promotion            = new Promotion();
            $combo_package_detail = $promotion->getComboPackageDetail($combo_package_id);

            // 获取购买人信息
            $buyer      = new UserModel();
            $buyer_info = $buyer->getInfo([
                'uid' => $this->uid
            ], 'nick_name');

            // 订单商品费用
            $goods_money = $order_goods_preference->getComboPackageGoodsSkuListPrice($goods_sku_list);

            // 购买套餐费用
            $combo_package_price = $combo_package_detail["combo_package_price"] * $buy_num;

            $point = $order_goods_preference->getGoodsListExchangePoint($goods_sku_list);
            // 获取订单邮费,订单自提免除运费
            if ($shipping_type == 1) {
                $order_goods_express = new GoodsExpress();
                $deliver_price       = $order_goods_express->getSkuListExpressFee($goods_sku_list, $shipping_company_id, $receiver_province, $receiver_city, $receiver_district);
                if ($deliver_price < 0) {
                    $this->order->rollback();
                    return $deliver_price;
                }
            } else {
                // 根据自提点服务费用计算
                $deliver_price = $order_goods_preference->getPickupMoney($combo_package_price);
            }

            // 积分兑换抵用金额
            $account_flow = new MemberAccount();
            /*
             * $point_money = $order_goods_preference->getPointMoney($point, $shop_id);
             */
            $point_money = 0;
            /*
             * if($point > 0)
             * {
             * //积分兑换抵用商品金额+邮费
             * $point_money = $goods_money;
             * //订单为已支付
             * if($deliver_price == 0)
             * {
             * $order_status = 1;
             * }else
             * {
             * $order_status = 0;
             * }
             *
             * //赠送积分为0
             * $give_point = 0;
             * //不享受满减送优惠
             * $promotion_money = 0;
             *
             * }else{
             */
            // 订单来源
            if (isWeixin()) {
                $order_from = 1; // 微信
            } elseif (request()->isMobile()) {
                $order_from = 2; // 手机
            } else {
                $order_from = 3; // 电脑
            }
            // 订单支付方式

            // 订单待支付
            $order_status = 0;
            // 购买商品获取积分数
            $give_point = $order_goods_preference->getGoodsSkuListGivePoint($goods_sku_list);
            // 订单优惠价格
            $promotion_money = round(($goods_money - $combo_package_price), 2);
            // 如果优惠价小于0则优惠为0 一般这种情况是因为在组合商品发布后商品价格发生变化
            $promotion_money = $promotion_money < 0 ? 0 : $promotion_money;

            $full_mail_array = array();
            // 计算订单的满额包邮
            $full_mail_model = new NsPromotionFullMailModel();
            // 店铺的满额包邮
            $full_mail_obj = $full_mail_model->getInfo([
                "shop_id" => $shop_id
            ], "*");
            $no_mail       = checkIdIsinIdArr($receiver_city, $full_mail_obj['no_mail_city_id_array']);
            if ($no_mail) {
                $full_mail_obj['is_open'] = 0;
            }
            if (!empty($full_mail_obj)) {
                $is_open          = $full_mail_obj["is_open"];
                $full_mail_money  = $full_mail_obj["full_mail_money"];
                $order_real_money = $goods_money - $promotion_money - $point_money;
                if ($is_open == 1 && $order_real_money >= $full_mail_money && $deliver_price > 0) {
                    // 符合满额包邮 邮费设置为0
                    $full_mail_array["promotion_id"]        = $full_mail_obj["mail_id"];
                    $full_mail_array["promotion_type"]      = 'MANEBAOYOU';
                    $full_mail_array["promotion_name"]      = '满额包邮';
                    $full_mail_array["promotion_condition"] = '满' . $full_mail_money . '元,包邮!';
                    $full_mail_array["discount_money"]      = $deliver_price;
                    $deliver_price                          = 0;
                }
            }

            // 订单费用(具体计算)
            $order_money = $combo_package_price + $deliver_price - $point_money;

            if ($order_money < 0) {
                $order_money    = 0;
                $user_money     = 0;
                $platform_money = 0;
            }

            if (!empty($buyer_invoice)) {
                // 添加税费
                $config    = new Config();
                $tax_value = $config->getConfig(0, 'ORDER_INVOICE_TAX');
                if (empty($tax_value['value'])) {
                    $tax = 0;
                } else {
                    $tax = $tax_value['value'];
                }
                $tax_money = $order_money * $tax / 100;
            } else {
                $tax_money = 0;
            }
            $order_money = $order_money + $tax_money;

            if ($order_money < $platform_money) {
                $platform_money = $order_money;
            }

            $pay_money = $order_money - $user_money - $platform_money;
            if ($pay_money <= 0) {
                $pay_money    = 0;
                $order_status = 0;
                $pay_status   = 0;
            } else {
                $order_status = 0;
                $pay_status   = 0;
            }

            // 积分返还类型
            $config          = new ConfigModel();
            $config_info     = $config->getInfo([
                "instance_id" => $shop_id,
                "key" => "SHOPPING_BACK_POINTS"
            ], "value");
            $give_point_type = $config_info["value"];

            // 店铺名称

            $data_order = array(
                'order_type' => $order_type,
                'order_no' => $this->createOrderNo($shop_id),
                'out_trade_no' => $out_trade_no,
                'payment_type' => $pay_type,
                'shipping_type' => $shipping_type,
                'order_from' => $order_from,
                'buyer_id' => $this->uid,
                'user_name' => $buyer_info['nick_name'],
                'buyer_ip' => $buyer_ip,
                'buyer_message' => $buyer_message,
                'buyer_invoice' => $buyer_invoice,
                'shipping_time' => $shipping_time, // datetime NOT NULL COMMENT '买家要求配送时间',
                'receiver_mobile' => $receiver_mobile, // varchar(11) NOT NULL DEFAULT '' COMMENT '收货人的手机号码',
                'receiver_province' => $receiver_province, // int(11) NOT NULL COMMENT '收货人所在省',
                'receiver_city' => $receiver_city, // int(11) NOT NULL COMMENT '收货人所在城市',
                'receiver_district' => $receiver_district, // int(11) NOT NULL COMMENT '收货人所在街道',
                'receiver_address' => $receiver_address, // varchar(255) NOT NULL DEFAULT '' COMMENT '收货人详细地址',
                'receiver_zip' => $receiver_zip, // varchar(6) NOT NULL DEFAULT '' COMMENT '收货人邮编',
                'receiver_name' => $receiver_name, // varchar(50) NOT NULL DEFAULT '' COMMENT '收货人姓名',
                'shop_id' => $shop_id, // int(11) NOT NULL COMMENT '卖家店铺id',
                'shop_name' => $shop_name, // varchar(100) NOT NULL DEFAULT '' COMMENT '卖家店铺名称',
                'goods_money' => $goods_money, // decimal(19, 2) NOT NULL COMMENT '商品总价',
                'tax_money' => $tax_money, // 税费
                'order_money' => $order_money, // decimal(10, 2) NOT NULL COMMENT '订单总价',
                'point' => $point, // int(11) NOT NULL COMMENT '订单消耗积分',
                'point_money' => $point_money, // decimal(10, 2) NOT NULL COMMENT '订单消耗积分抵多少钱',
                'coupon_money' => "", // _money decimal(10, 2) NOT NULL COMMENT '订单代金券支付金额',
                'coupon_id' => "", // int(11) NOT NULL COMMENT '订单代金券id',
                'user_money' => $user_money, // decimal(10, 2) NOT NULL COMMENT '订单预存款支付金额',
                'promotion_money' => $promotion_money, // decimal(10, 2) NOT NULL COMMENT '订单优惠活动金额',
                'shipping_money' => $deliver_price, // decimal(10, 2) NOT NULL COMMENT '订单运费',
                'pay_money' => $pay_money, // decimal(10, 2) NOT NULL COMMENT '订单实付金额',
                'refund_money' => 0, // decimal(10, 2) NOT NULL COMMENT '订单退款金额',
                'give_point' => $give_point, // int(11) NOT NULL COMMENT '订单赠送积分',
                'order_status' => $order_status, // tinyint(4) NOT NULL COMMENT '订单状态',
                'pay_status' => $pay_status, // tinyint(4) NOT NULL COMMENT '订单付款状态',
                'shipping_status' => 0, // tinyint(4) NOT NULL COMMENT '订单配送状态',
                'review_status' => 0, // tinyint(4) NOT NULL COMMENT '订单评价状态',
                'feedback_status' => 0, // tinyint(4) NOT NULL COMMENT '订单维权状态',
                'user_platform_money' => $platform_money, // 平台余额支付
                'coin_money' => $coin,
                'create_time' => time(),
                "give_point_type" => $give_point_type,
                'shipping_company_id' => $shipping_company_id,
                'fixed_telephone' => $fixed_telephone
            ); // 固定电话
            // datetime NOT NULL DEFAULT 'CURRENT_TIMESTAMP' COMMENT '订单创建时间',
            if ($pay_status == 2) {
                $data_order["pay_time"] = time();
            }
            $order = new NsOrderModel();
            $order->save($data_order);
            $order_id = $order->order_id;
            $pay      = new UnifyPay();
            $pay->createPayment($shop_id, $out_trade_no, $shop_name . "订单", $shop_name . "订单", $pay_money, 1, $order_id);

            // 订单优惠详情，添加组合套餐优惠情况
            $order_promotion_details = new NsOrderPromotionDetailsModel();
            $data_promotion_details  = array(
                'order_id' => $order_id,
                'promotion_id' => $combo_package_id,
                'promotion_type_id' => 3,
                'promotion_type' => 'ZUHETAOCAN',
                'promotion_name' => '组合套餐活动',
                'promotion_condition' => "套餐名称：" . $combo_package_detail['combo_package_name'] . "原价：" . $goods_money . "套餐价：" . $combo_package_detail['combo_package_price'] * $buy_num,
                'discount_money' => $promotion_money,
                'used_time' => time()
            );
            $order_promotion_details->save($data_promotion_details);

            // 添加到对应商品项优惠信息

            $goods_sku                    = explode(",", $goods_sku_list);
            $ns_goods_sku                 = new NsGoodsSkuModel();
            $ns_goods                     = new NsGoodsModel();
            $temp_promotion_money         = $promotion_money / $buy_num; // 单套套餐优惠价格
            $temp_goods_money             = $goods_money / $buy_num; // 单套商品总价
            $temp_promotion_surplus_money = $temp_promotion_money; // 剩余优惠

            for ($i = 0; $i < count($goods_sku); $i++) {

                $sku            = explode(":", $goods_sku[$i]);
                $sku_id         = $sku[0];
                $goods_detial   = $ns_goods_sku->getInfo([
                    "sku_id" => $sku_id
                ], "price");
                $discount_money = round($goods_detial["price"] * $temp_promotion_money / $goods_money, 2);
                if ($i == (count($goods_sku) - 1)) {
                    $discount_money = $temp_promotion_surplus_money;
                }
                if ($discount_money > $temp_promotion_surplus_money) {
                    $discount_money = $temp_promotion_surplus_money;
                }
                if ($discount_money > $goods_detial["price"]) {
                    $discount_money = $goods_detial["price"];
                }
                $order_goods_promotion_details = new NsOrderGoodsPromotionDetailsModel();
                // 商品原价/原价*优惠价
                $data_details = array(
                    'order_id' => $order_id,
                    'promotion_id' => $combo_package_id,
                    'sku_id' => $sku_id,
                    'promotion_type' => 'ZUHETAOCAN',
                    'discount_money' => $discount_money,
                    'used_time' => time()
                );
                $order_goods_promotion_details->save($data_details);

                $temp_promotion_surplus_money = $temp_promotion_surplus_money - $discount_money;
                if ($temp_promotion_surplus_money < 0) {
                    $temp_promotion_surplus_money = 0;
                }
            }

            // 如果是订单自提需要添加自提相关信息
            if ($shipping_type == 2) {
                if (!empty($pick_up_id)) {
                    $pickup_model        = new NsPickupPointModel();
                    $pickup_point_info   = $pickup_model->getInfo([
                        'id' => $pick_up_id
                    ], '*');
                    $order_pick_up_model = new NsOrderPickupModel();
                    $data_pickup         = array(
                        'order_id' => $order_id,
                        'name' => $pickup_point_info['name'],
                        'address' => $pickup_point_info['address'],
                        'contact' => $pickup_point_info['address'],
                        'phone' => $pickup_point_info['phone'],
                        'city_id' => $pickup_point_info['city_id'],
                        'province_id' => $pickup_point_info['province_id'],
                        'district_id' => $pickup_point_info['district_id'],
                        'supplier_id' => $pickup_point_info['supplier_id'],
                        'longitude' => $pickup_point_info['longitude'],
                        'latitude' => $pickup_point_info['latitude'],
                        'create_time' => time()
                    );
                    $order_pick_up_model->save($data_pickup);
                }
            }
            // 满额包邮活动
            if (!empty($full_mail_array)) {
                $order_promotion_details = new NsOrderPromotionDetailsModel();
                $data_promotion_details  = array(
                    'order_id' => $order_id,
                    'promotion_id' => $full_mail_array["promotion_id"],
                    'promotion_type_id' => 2,
                    'promotion_type' => $full_mail_array["promotion_type"],
                    'promotion_name' => $full_mail_array["promotion_name"],
                    'promotion_condition' => $full_mail_array["promotion_condition"],
                    'discount_money' => $full_mail_array["discount_money"],
                    'used_time' => time()
                );
                $order_promotion_details->save($data_promotion_details);
            }

            // 使用积分
            if ($point > 0) {
                $retval_point = $account_flow->addMemberAccountData($shop_id, 1, $this->uid, 0, $point * (-1), 1, $order_id, '商城订单');
                if ($retval_point < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_POINT;
                }
            }
            if ($coin > 0) {
                $retval_point = $account_flow->addMemberAccountData($shop_id, 3, $this->uid, 0, $coin * (-1), 1, $order_id, '商城订单');
                if ($retval_point < 0) {
                    $this->order->rollback();
                    return LOW_COIN;
                }
            }
            if ($user_money > 0) {
                $retval_user_money = $account_flow->addMemberAccountData($shop_id, 2, $this->uid, 0, $user_money * (-1), 1, $order_id, '商城订单');
                if ($retval_user_money < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_USER_MONEY;
                }
            }
            if ($platform_money > 0) {
                $retval_platform_money = $account_flow->addMemberAccountData(0, 2, $this->uid, 0, $platform_money * (-1), 1, $order_id, '商城订单');
                if ($retval_platform_money < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_PLATFORM_MONEY;
                }
            }

            // 添加订单项
            $order_goods     = new OrderGoods();
            $res_order_goods = $order_goods->addComboPackageOrderGoods($order_id, $goods_sku_list);
            if (!($res_order_goods > 0)) {
                $this->order->rollback();
                return $res_order_goods;
            }
            $this->addOrderAction($order_id, $this->uid, '创建订单');

            $this->order->commit();
            return $order_id;
        } catch (\Exception $e) {
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 订单支付
     *
     * @param unknown $order_pay_no
     * @param unknown $pay_type (10:线下支付)
     * @param unknown $status
     *            0:订单支付完成 1：订单交易完成
     * @return Exception
     */
    public function OrderPay($order_pay_no, $pay_type, $status)
    {
        $this->order->startTrans();
        try {
            // 添加订单日志,可能是多个订单
            $order_id_array = $this->order->where([
                'out_trade_no' => $order_pay_no,
                'order_status' => 0
            ])->column('order_id');
            $account        = new MemberAccount();
            $member         = new NsMemberModel();
            foreach ($order_id_array as $k => $order_id) {

                $order_info = $this->order->getInfo([
                    'order_id' => $order_id
                ], 'buyer_id,pay_money,order_type,order_no,distributor_type,source_distribution');

                //新用户绑定
                $order_distribution_info = $this->order->getInfo(['buyer_id' => $order_info['buyer_id'], 'pay_status' => 2], '*');//查询是否有有效订单
                $memberInfo = $member->getInfo(['uid' => $order_info['buyer_id']], 'distributor_type');//查询是否已经是分销者
                if (empty($order_distribution_info) && $memberInfo['distributor_type'] == 0) {
                    $member->save(['source_distribution' => $order_info['source_distribution']], ['uid' => $order_info['buyer_id']]);
                }

                // 增加会员累计消费
                $account->addMmemberConsum(0, $order_info['buyer_id'], $order_info['pay_money']);

                // 修改订单状态
                if ($order_info['order_type'] == 4) {
                    $data = array(
                        'payment_type' => $pay_type,
                        'pay_status' => 2,
                        'pay_time' => time(),
                        'order_status' => 11
                    ); // 订单转为待赠送状态
                } else {
                    $data = array(
                        'payment_type' => $pay_type,
                        'pay_status' => 2,
                        'pay_time' => time(),
                        'order_status' => 1
                    ); // 订单转为待发货状态
                }

                $order = new NsOrderModel();
                $order->save($data, [
                    'order_id' => $order_id
                ]);

                if ($pay_type == 10) {
                    // 线下支付
                    $this->addOrderAction($order_id, $this->uid, '线下支付');
                } else {
                    // 查询订单购买人ID

                    $this->addOrderAction($order_id, $order_info['buyer_id'], '订单支付');
                }

                $user      = new UserModel();
                $user_info = $user->getInfo([
                    "uid" => $order_info['buyer_id']
                ], "nick_name");
                if ($order_info['order_type'] == 2) {
                    // 虚拟商品，订单自动完成
                    $this->virtualOrderOperation($order_id, $user_info["nick_name"], $order_info['order_no']);
                    $res = $this->orderComplete($order_id);
                    if (!($res > 0)) {
                        $this->order->rollback();
                        return $res;
                    }
                } else if ($order_info['order_type'] == 5) {
                    $member->save(["is_vip" => 1, "vip_buy_time" => time()], ["uid" => $order_info['buyer_id']]);
                    $res = $this->orderComplete($order_id);
                    #会员推送逻辑
                    $this->runSa($order_info['buyer_id']);
                    if (!($res > 0)) {
                        $this->order->rollback();
                        return $res;
                    }

                } else {

                    // 根据订单id查询订单项中的赠品集合，添加赠品发放记录
                    $temp = $this->addPromotionGiftGrantRecords($order_id, $order_info['buyer_id'], $user_info["nick_name"]);
                    // 根据订单id去掉减价过的订单项分润比例,直接分润,间接分润
//                    $order_goods_model = new NsOrderGoodsModel();
//                    $order_goods_model->save(['fraction'=>0,'direct_separation'=>0,'indirect_separation'=>0],['order_id'=>$order_id,'adjust_money'=>['<',0]]);

                    if ($status == 1) {
                        // 执行订单交易完成
                        $res = $this->orderComplete($order_id);
                        if (!($res > 0)) {
                            $this->order->rollback();
                            return $res;
                        }
                    }
                }
            }
            $this->order->commit();
            return 1;
        } catch (\Exception $e) {
            $this->order->rollback();
            Log::write("订单支付出错" . $e->getMessage());
            return $e->getMessage();
        }
    }

    //分销线下支付
    public function OrderPayNew($order_id, $pay_type, $pay_way, $pay_money, $pay_pic, $payment_type, $memo)
    {
        $this->order->startTrans();
        try {
            $order_info = $this->order->getInfo([
                'order_id' => $order_id
            ], 'buyer_id,step_paid_money');

            // 增加会员累计消费
            $account = new MemberAccount();
            $account->addMmemberConsum(0, $order_info['buyer_id'], $pay_money);

            // 修改订单状态
            if ($pay_type == 1) {
                $data = array(
                    'payment_type' => $payment_type,
                    'pay_status' => 2,
                    'pay_time' => time(),
                    'order_status' => 1
                );
            } else if($pay_type == 2) {
                $data = array(
                    'payment_type' => $payment_type,
                    'step_paid_money' =>$order_info['step_paid_money']+$pay_money,
                    'pay_status' => 3,
                    'pay_time' => time(),
                    'order_status' => 1
                );
            } else {
                $data = array(
                    'payment_type' => $payment_type,
                    'step_paid_money' =>$order_info['step_paid_money']+$pay_money,
                    'pay_status' => 2,
                    'pay_time' => time()
                );
            }

            $order = new NsOrderModel();
            $order->save($data, ['order_id' => $order_id]);

            // 添加订单日志
            if ($pay_type == 1) {
                // 线下支付全款
                $this->addOrderAction($order_id, $this->uid, '线下支付全款');
            } elseif($pay_type == 2) {
                // 线下支付首款
                $this->addOrderAction($order_id, $this->uid, '线下支付首款');
            }elseif($pay_type == 3){
                // 线下支付尾款
                $this->addOrderAction($order_id, $this->uid, '线下支付尾款');
            }

            // 添加订单线下付款信息
            $orderOfflinePayModel = new BcOrderOfflinePayModel();
            $data   = array(
                'order_id' => $order_id,
                'pay_type' => $pay_type,
                'pay_way' => $pay_way,
                'pay_money' => $pay_money,
                'pay_pic' => $pay_pic,
                'memo' => $memo,
                'create_time' => time()
            );
            $retval  = $orderOfflinePayModel->save($data);

            $this->order->commit();
            return $retval;
        } catch (\Exception $e) {
            $this->order->rollback();
            Log::write("订单支付出错" . $e->getMessage());
            return $e->getMessage();
        }
    }

    /**
     * 虚拟订单，生成虚拟商品
     * 1、根据订单id查询订单项(虚拟订单项只会有一条数据)
     * 2、根据购买的商品获取虚拟商品类型信息
     * 3、根据购买的商品数量添加相应的虚拟商品数量
     */
    public function virtualOrderOperation($order_id, $buyer_nickname, $order_no)
    {
        $order_goods_model = new NsOrderGoodsModel();
        // 查询订单项信息
        $order_goods_items = $order_goods_model->getInfo([
            'order_id' => $order_id
        ], 'order_goods_id,goods_id,goods_name,buyer_id,num');
        $res               = 0;
        if (!empty($order_goods_items)) {
            $virtual_goods = new VirtualGoods();
            $goods_model   = new NsGoodsModel();
            // 根据goods_id查询虚拟商品类型
            $virtual_goods_type_id = $goods_model->getInfo([
                'goods_id' => $order_goods_items['goods_id']
            ], 'virtual_goods_type_id');
            if (!empty($virtual_goods_type_id)) {

                // 生成虚拟商品
                for ($i = 0; $i < $order_goods_items['num']; $i++) {
                    $virtual_goods_type_info = $virtual_goods->getVirtualGoodsTypeById($virtual_goods_type_id['virtual_goods_type_id']);
                    $virtual_goods_name      = $virtual_goods_type_info['virtual_goods_type_name']; // 虚拟商品名称
                    $money                   = $virtual_goods_type_info['money']; // 虚拟商品金额
                    $buyer_id                = $order_goods_items['buyer_id']; // 买家id
                    $order_goods_id          = $order_goods_items['order_goods_id']; // 关联订单项id
                    $validity_period         = $virtual_goods_type_info['validity_period']; // 有效期至
                    $start_time              = time();
                    if ($validity_period == 0) {
                        $end_time = 0;
                    } else {
                        $end_time = strtotime("+$validity_period days");
                    }
                    $use_number         = 0; // 使用次数，刚添加的默认0
                    $confine_use_number = $virtual_goods_type_info['confine_use_number'];
                    $use_status         = 0; // (-1:已失效,0:未使用,1:已使用)
                    $res                = $virtual_goods->addVirtualGoods($this->instance_id, $virtual_goods_name, $money, $buyer_id, $buyer_nickname, $order_goods_id, $order_no, $validity_period, $start_time, $end_time, $use_number, $confine_use_number, $use_status, $order_goods_items['goods_id']);
                }
            }
        }
        return $res;
    }

    /**
     * 添加订单操作日志
     * order_id int(11) NOT NULL COMMENT '订单id',
     * action varchar(255) NOT NULL DEFAULT '' COMMENT '动作内容',
     * uid int(11) NOT NULL DEFAULT 0 COMMENT '操作人id',
     * user_name varchar(50) NOT NULL DEFAULT '' COMMENT '操作人',
     * order_status int(11) NOT NULL COMMENT '订单大状态',
     * order_status_text varchar(255) NOT NULL DEFAULT '' COMMENT '订单状态名称',
     * action_time datetime NOT NULL COMMENT '操作时间',
     * PRIMARY KEY (action_id)
     *
     * @param unknown $order_id
     * @param unknown $uid
     * @param unknown $action_text
     */
    public function addOrderAction($order_id, $uid, $action_text)
    {
        $this->order->startTrans();
        try {
            $order_status = $this->order->getInfo([
                'order_id' => $order_id
            ], 'order_status');
            if ($uid != 0) {
                $user        = new UserModel();
                $user_name   = $user->getInfo([
                    'uid' => $uid
                ], 'nick_name');
                $action_name = $user_name['nick_name'];
            } else {
                $action_name = 'system';
            }

            $data_log     = array(
                'order_id' => $order_id,
                'action' => $action_text,
                'uid' => $uid,
                'user_name' => $action_name,
                'order_status' => $order_status['order_status'],
                'order_status_text' => $this->getOrderStatusName($order_id),
                'action_time' => time()
            );
            $order_action = new NsOrderActionModel();
            $order_action->save($data_log);
            $this->order->commit();
            return $order_action->action_id;
        } catch (\Exception $e) {
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    //分润发放
//    public function addOrderSeparation($order_id){
//        $this->order->startTrans();
//        try {
//
//            $this->order->commit();
//            return 1;
//        } catch (\Exception $e) {
//            $this->order->rollback();
//            return $e->getMessage();
//        }
//    }

    /**
     * 礼品送出
     * `order_id` int(11) NOT NULL DEFAULT '0' COMMENT '订单id',
     *`uid` int(11) NOT NULL DEFAULT '0' COMMENT '会员id',
     *`action_status` int(1) NOT NULL COMMENT '操作（1：送；2：收）',
     *`action_time` int(11) DEFAULT '0' COMMENT '操作时间',
     *PRIMARY KEY (`id`)
     *
     * @param unknown $order_id
     * @param unknown $uid
     * @param unknown $action_status
     */
    public function addGiftGaveGet($order_id, $uid, $action_status)
    {
        $data_log    = array(
            'order_id' => $order_id,
            'uid' => $uid,
            'action_status' => $action_status,
            'action_time' => time()
        );
        $gift_action = new NsGiftGiveGetModel();
        $gift_action->save($data_log);
        return $gift_action->id;
    }

    // 礼品订单添加地址
    public function addAdressInOrder($order_id, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name)
    {
        $data  = array(
            'receiver_mobile' => $receiver_mobile, // varchar(11) NOT NULL DEFAULT '' COMMENT '收货人的手机号码',
            'receiver_province' => $receiver_province, // int(11) NOT NULL COMMENT '收货人所在省',
            'receiver_city' => $receiver_city, // int(11) NOT NULL COMMENT '收货人所在城市',
            'receiver_district' => $receiver_district, // int(11) NOT NULL COMMENT '收货人所在街道',
            'receiver_address' => $receiver_address, // varchar(255) NOT NULL DEFAULT '' COMMENT '收货人详细地址',
            'receiver_zip' => $receiver_zip, // varchar(6) NOT NULL DEFAULT '' COMMENT '收货人邮编',
            'receiver_name' => $receiver_name, // varchar(50) NOT NULL DEFAULT '' COMMENT '收货人姓名',
            'order_status' => 1 // varchar(50) NOT NULL DEFAULT '' COMMENT '收货人姓名',
        );
        $where = array(
            'order_id' => $order_id
        );
        $order = new NsOrderModel();
        $order->save($data, $where);
        return 1;
    }

    /**
     * 获取订单当前状态 名称
     *
     * @param unknown $order_id
     */
    public function getOrderStatusName($order_id)
    {
        $order_status = $this->order->getInfo([
            'order_id' => $order_id
        ], 'order_type, order_status');
        if ($order_status['order_type'] == 4) {
            $status_array = OrderStatus::getGiftOrderCommonStatus();
        } else {
            $status_array = OrderStatus::getOrderCommonStatus();
        }
        foreach ($status_array as $k => $v) {
            if ($v['status_id'] == $order_status['order_status']) {
                return $v['status_name'];
            }
        }
        return false;
    }

    /**
     * 通过店铺id 得到订单的订单号
     *
     * @param unknown $shop_id
     */
    public function createOrderNo($shop_id)
    {
        $time_str    = date('YmdHs');
        $order_model = new NsOrderModel();
        $order_obj   = $order_model->getFirstData([
            "shop_id" => $shop_id
        ], "order_id DESC");
        $num         = 0;
        if (!empty($order_obj)) {
            $order_no_max = $order_obj["order_no"];
            if (empty($order_no_max)) {
                $num = 1;
            } else {
                if (substr($time_str, 0, 12) == substr($order_no_max, 0, 12)) {
                    $max_no = substr($order_no_max, 12, 4);
                    $num    = $max_no * 1 + 1;
                } else {
                    $num = 1;
                }
            }
        } else {
            $num = 1;
        }
        $order_no = $time_str . sprintf("%04d", $num);
        $count    = $order_model->getCount([
            'order_no' => $order_no
        ]);
        if ($count > 0) {
            return $this->createOrderNo($shop_id);
        }
        return $order_no;
    }

    /**
     * 创建订单支付编号
     *
     * @param unknown $order_id
     */
    public function createOutTradeNo()
    {
        $pay_no = new UnifyPay();
        return $pay_no->createOutTradeNo();
    }

    /**
     * 订单重新生成订单号
     *
     * @param unknown $orderid
     */
    public function createNewOutTradeNo($orderid)
    {
        $order  = new NsOrderModel();
        $new_no = $this->createOutTradeNo();
        $data   = array(
            'out_trade_no' => $new_no
        );
        $retval = $order->save($data, [
            'order_id' => $orderid
        ]);
        if ($retval) {
            return $new_no;
        } else {
            return '';
        }
    }

    /**
     * 订单发货(整体发货)(不考虑订单项)
     *
     * @param unknown $orderid
     */
    public function orderDoDelivery($orderid)
    {
        $this->order->startTrans();
        try {
            $order_item = new NsOrderGoodsModel();
            $count      = $order_item->getCount([
                'order_id' => $orderid,
                'shipping_status' => 0,
                'refund_status' => array(
                    'ELT',
                    0
                )
            ]);
            if ($count == 0) {
                $data_delivery = array(
                    'shipping_status' => 1,
                    'order_status' => 2,
                    'consign_time' => time()
                );
                $order_model   = new NsOrderModel();
                $order_model->save($data_delivery, [
                    'order_id' => $orderid
                ]);
                $this->addOrderAction($orderid, $this->uid, '订单发货');
            }

            $this->order->commit();
            return 1;
        } catch (\Exception $e) {

            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 订单收货
     *
     * @param unknown $orderid
     */
    public function OrderTakeDelivery($orderid)
    {
        $this->order->startTrans();
        try {
            $data_take_delivery = array(
                'shipping_status' => 2,
                'order_status' => 3,
                'sign_time' => time()
            );
            $order_model        = new NsOrderModel();
            $order_model->save($data_take_delivery, [
                'order_id' => $orderid
            ]);
            $this->addOrderAction($orderid, $this->uid, '订单收货');
            // 判断是否需要在本阶段赠送积分
            $this->giveGoodsOrderPoint($orderid, 2);
            $this->order->commit();
            return 1;
        } catch (\Exception $e) {

            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 订单自动收货
     *
     * @param unknown $orderid
     */
    public function orderAutoDelivery($orderid)
    {
        $this->order->startTrans();
        try {
            $data_take_delivery = array(
                'shipping_status' => 2,
                'order_status' => 3,
                'sign_time' => time()
            );
            $order_model        = new NsOrderModel();
            $order_model->save($data_take_delivery, [
                'order_id' => $orderid
            ]);

            $this->addOrderAction($orderid, 0, '订单自动收货');
            // 判断是否需要在本阶段赠送积分
            $this->giveGoodsOrderPoint($orderid, 2);
            $this->order->commit();
            return 1;
        } catch (\Exception $e) {

            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 执行订单交易完成
     *
     * @param unknown $orderid
     */
    public function orderComplete($orderid)
    {
        $this->order->startTrans();
        try {
            $data_complete = array(
                'order_status' => 4,
                "finish_time" => time()
            );
            $order_model   = new NsOrderModel();
            $order_model->save($data_complete, [
                'order_id' => $orderid
            ]);
            $this->addOrderAction($orderid, $this->uid, '交易完成');
            $this->calculateOrderGivePoint($orderid);
            $this->calculateOrderMansong($orderid);
            // 判断是否需要在本阶段赠送积分
            $this->giveGoodsOrderPoint($orderid, 1);
            $this->order->commit();
            return 1;
        } catch (\Exception $e) {

            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 统计订单完成后赠送用户积分
     *
     * @param unknown $order_id
     */
    private function calculateOrderGivePoint($order_id)
    {
        $point          = $this->order->getInfo([
            'order_id' => $order_id
        ], 'shop_id, give_point,buyer_id');
        $member_account = new MemberAccount();
        $member_account->addMemberAccountData($point['shop_id'], 1, $point['buyer_id'], 1, $point['give_point'], 1, $order_id, '订单商品赠送积分');
    }

    /**
     * 订单完成后统计满减送赠送
     *
     * @param unknown $order_id
     */
    private function calculateOrderMansong($order_id)
    {
        $order_info              = $this->order->getInfo([
            'order_id' => $order_id
        ], 'shop_id, buyer_id');
        $order_promotion_details = new NsOrderPromotionDetailsModel();
        // 查询满减送活动规则
        $list = $order_promotion_details->getQuery([
            'order_id' => $order_id,
            'promotion_type_id' => 1
        ], 'promotion_id', '');
        if (!empty($list)) {
            $promotion_mansong_rule = new NsPromotionMansongRuleModel();
            foreach ($list as $k => $v) {
                $mansong_data = $promotion_mansong_rule->getInfo([
                    'rule_id' => $v['promotion_id']
                ], 'give_coupon,give_point');
                if (!empty($mansong_data)) {
                    // 满减送赠送积分
                    if ($mansong_data['give_point'] != 0) {
                        $member_account = new MemberAccount();
                        $member_account->addMemberAccountData($order_info['shop_id'], 1, $order_info['buyer_id'], 1, $mansong_data['give_point'], 1, $order_id, '订单满减送赠送积分');
                    }
                    // 满减送赠送优惠券
                    if ($mansong_data['give_coupon'] != 0) {
                        $member_coupon = new MemberCoupon();
                        $member_coupon->UserAchieveCoupon($order_info['buyer_id'], $mansong_data['give_coupon'], 1);
                    }
                }
            }
        }
    }

    /**
     * 订单执行交易关闭
     *
     * @param unknown $orderid
     * @return Exception
     */
    public function orderClose($orderid)
    {
        $this->order->startTrans();
        try {
            $order_info  = $this->order->getInfo([
                'order_id' => $orderid
            ], 'order_status,pay_status,point, coupon_id, user_money, buyer_id,shop_id,user_platform_money, coin_money');
            $data_close  = array(
                'order_status' => 5
            );
            $order_model = new NsOrderModel();
            $order_model->save($data_close, [
                'order_id' => $orderid
            ]);
            $account_flow = new MemberAccount();
            if ($order_info['order_status'] == 0) {
                // 会员余额返还
                if ($order_info['user_money'] > 0) {
                    $account_flow->addMemberAccountData($order_info['shop_id'], 2, $order_info['buyer_id'], 1, $order_info['user_money'], 2, $orderid, '订单关闭返还用户余额');
                }
                // 平台余额返还

                if ($order_info['user_platform_money'] > 0) {
                    $account_flow->addMemberAccountData(0, 2, $order_info['buyer_id'], 1, $order_info['user_platform_money'], 2, $orderid, '商城订单关闭返还平台余额');
                }
            }

            // 积分返还
            if ($order_info['point'] > 0) {
                $account_flow->addMemberAccountData($order_info['shop_id'], 1, $order_info['buyer_id'], 1, $order_info['point'], 2, $orderid, '订单关闭返还积分');
            }

            // 购物币返还
            if ($order_info['coin_money'] > 0) {
                $coin_convert_rate = $account_flow->getCoinConvertRate();
                $account_flow->addMemberAccountData($order_info['shop_id'], 3, $order_info['buyer_id'], 1, $order_info['coin_money'] / $coin_convert_rate, 2, $orderid, '订单关闭返还购物币');
            }

            // 优惠券返还
            $coupon = new MemberCoupon();
            if ($order_info['coupon_id'] > 0) {
                $coupon->UserReturnCoupon($order_info['coupon_id']);
            }
            // 退回库存
            $order_goods      = new NsOrderGoodsModel();
            $order_goods_list = $order_goods->getQuery([
                'order_id' => $orderid
            ], '*', '');
            foreach ($order_goods_list as $k => $v) {
                $return_stock    = 0;
                $goods_sku_model = new NsGoodsSkuModel();
                $goods_sku_info  = $goods_sku_model->getInfo([
                    'sku_id' => $v['sku_id']
                ], 'goods_id, stock');
                if ($v['shipping_status'] != 1) {
                    // 卖家未发货
                    $return_stock = 1;
                } else {
                    // 卖家已发货,买家不退货
                    // if ($v['refund_type'] == 1) {   //bug修复：买家的货在退款之前有专门的入库操作，这里不需要再次入库
                    //     $return_stock = 0;
                    // } else {
                    //     $return_stock = 1;
                    // }
                    $return_stock = 0;
                }
                // 销量返回
                $goods_model = new NsGoodsModel();
                $sales_info  = $goods_model->getInfo([
                    'goods_id' => $goods_sku_info['goods_id']
                ], 'real_sales');
                $goods_model->save([
                    'real_sales' => $sales_info['real_sales'] - $v['num']
                ], [
                    "goods_id" => $goods_sku_info['goods_id']
                ]);
                // 退货返回库存
                if ($return_stock == 1) {
                    $data_goods_sku = array(
                        'stock' => $goods_sku_info['stock'] + $v['num']
                    );
                    $goods_sku_model->save($data_goods_sku, [
                        'sku_id' => $v['sku_id']
                    ]);
                    $count = $goods_sku_model->getSum([
                        'goods_id' => $goods_sku_info['goods_id']
                    ], 'stock');
                    // 商品库存增加
                    $goods_model = new NsGoodsModel();

                    $goods_model->save([
                        'stock' => $count
                    ], [
                        "goods_id" => $goods_sku_info['goods_id']
                    ]);
                }
            }
            $this->addOrderAction($orderid, $this->uid, '交易关闭');
            $this->order->commit();
            return 1;
        } catch (\Exception $e) {
            Log::write($e->getMessage());
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 订单执行交易关闭（退款:不返还积分，购物币，优惠券）
     *
     * @param unknown $orderid
     * @return Exception
     */
    public function orderCloseRefund($orderid)
    {
        $this->order->startTrans();
        try {
            $order_info  = $this->order->getInfo([
                'order_id' => $orderid
            ], 'order_status,pay_status,point, coupon_id, user_money, buyer_id,shop_id,user_platform_money, coin_money');
            $data_close  = array(
                'order_status' => -2
            );
            $order_model = new NsOrderModel();
            $order_model->save($data_close, [
                'order_id' => $orderid
            ]);
            $account_flow = new MemberAccount();
            if ($order_info['order_status'] == 0) {
                // 会员余额返还
                if ($order_info['user_money'] > 0) {
                    $account_flow->addMemberAccountData($order_info['shop_id'], 2, $order_info['buyer_id'], 1, $order_info['user_money'], 2, $orderid, '订单关闭返还用户余额');
                }
                // 平台余额返还

                if ($order_info['user_platform_money'] > 0) {
                    $account_flow->addMemberAccountData(0, 2, $order_info['buyer_id'], 1, $order_info['user_platform_money'], 2, $orderid, '商城订单关闭返还平台余额');
                }
            }

            // 退回库存
            $order_goods      = new NsOrderGoodsModel();
            $order_goods_list = $order_goods->getQuery([
                'order_id' => $orderid
            ], '*', '');
            foreach ($order_goods_list as $k => $v) {
                $return_stock    = 0;
                $goods_sku_model = new NsGoodsSkuModel();
                $goods_sku_info  = $goods_sku_model->getInfo([
                    'sku_id' => $v['sku_id']
                ], 'goods_id, stock');
                if ($v['shipping_status'] != 1) {
                    // 卖家未发货
                    $return_stock = 1;
                } else {
                    // 卖家已发货,买家不退货
                    // if ($v['refund_type'] == 1) {   //bug修复：买家的货在退款之前有专门的入库操作，这里不需要再次入库
                    //     $return_stock = 0;
                    // } else {
                    //     $return_stock = 1;
                    // }
                    $return_stock = 0;
                }
                // 销量返回
                $goods_model = new NsGoodsModel();
                $sales_info  = $goods_model->getInfo([
                    'goods_id' => $goods_sku_info['goods_id']
                ], 'real_sales');
                $goods_model->save([
                    'real_sales' => $sales_info['real_sales'] - $v['num']
                ], [
                    "goods_id" => $goods_sku_info['goods_id']
                ]);
                // 退货返回库存
                if ($return_stock == 1) {
                    $data_goods_sku = array(
                        'stock' => $goods_sku_info['stock'] + $v['num']
                    );
                    $goods_sku_model->save($data_goods_sku, [
                        'sku_id' => $v['sku_id']
                    ]);
                    $count = $goods_sku_model->getSum([
                        'goods_id' => $goods_sku_info['goods_id']
                    ], 'stock');
                    // 商品库存增加
                    $goods_model = new NsGoodsModel();

                    $goods_model->save([
                        'stock' => $count
                    ], [
                        "goods_id" => $goods_sku_info['goods_id']
                    ]);
                }
            }
            $this->addOrderAction($orderid, $this->uid, '确认退款');
            $this->order->commit();
            return 1;
        } catch (\Exception $e) {
            Log::write($e->getMessage());
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 订单状态变更
     *
     * @param unknown $order_id
     * @param unknown $order_goods_id
     */
    public function orderGoodsRefundFinish($order_id)
    {
        $orderInfo = NsOrderModel::get($order_id);
        $orderInfo->startTrans();
        try {
            $order_goods_model = new NsOrderGoodsModel();
            $total_count       = $order_goods_model->where("order_id=$order_id")->count();//订单项总数量
            $refunding_count   = $order_goods_model->where("order_id=$order_id AND refund_status<>0 AND refund_status<>5 AND refund_status>0")->count();//退款中订单项数量
            $refunded_count    = $order_goods_model->where("order_id=$order_id AND refund_status=5")->count();//退款已完成订单项数量
            $shipping_status   = $orderInfo->shipping_status;//物流状态（0：待发货；1：已发货；2：已收货；3：备货中）
            $all_refund        = 0;//订单项是否已全部退完(0:否;1:是)
            if ($refunding_count > 0) {

                $orderInfo->order_status = OrderStatus::getOrderCommonStatus()[6]['status_id']; // 退款中
            } elseif ($refunded_count == $total_count) {

                $all_refund = 1;
            } elseif ($shipping_status == OrderStatus::getShippingStatus()[0]['shipping_status']) {

                $orderInfo->order_status = OrderStatus::getOrderCommonStatus()[1]['status_id']; // 待发货
            } elseif ($shipping_status == OrderStatus::getShippingStatus()[1]['shipping_status']) {

                $orderInfo->order_status = OrderStatus::getOrderCommonStatus()[2]['status_id']; // 已发货
            } elseif ($shipping_status == OrderStatus::getShippingStatus()[2]['shipping_status']) {

                $orderInfo->order_status = OrderStatus::getOrderCommonStatus()[3]['status_id']; // 已收货
            }

            // 订单恢复正常操作
            if ($all_refund == 0) {
                $retval = $orderInfo->save();
                if ($refunding_count == 0) {
                    $this->orderDoDelivery($order_id);
                }
            } else {
                // 全部退款订单转化为交易关闭
                $retval = $this->orderCloseRefund($order_id);
            }

            $orderInfo->commit();
            return $retval;
        } catch (\Exception $e) {
            $orderInfo->rollback();
            return $e->getMessage();
        }

        return $retval;
    }

    public function orderGoodsRefundFinishes($order_id)
    {
        $orderInfo = NsOrderModel::get($order_id);
        $orderInfo->startTrans();
        try {
            $order_goods_model = new NsOrderGoodsModel();
            $total_num_count =  $order_goods_model->where("order_id=$order_id")->sum('num');//订单购买总数量

            $order_goods_list = $order_goods_model->where("order_id=$order_id")->select();
            $refunded_num_count  = 0; //订单退款总数量
            foreach ($order_goods_list as $v) {
                $order_refund_account_records_model = new NsOrderRefundAccountRecordsModel();
                $num =  $order_refund_account_records_model->where(['order_goods_id'=>$v['order_goods_id'],'refund_status'=>5])->sum('refund_require_num');//订单总数量
                $refunded_num_count += $num;
            }

            $refunding_count   = $order_goods_model->where("order_id=$order_id AND refund_status<>0 AND refund_status<>5 AND refund_status>0")->count();//退款中子订单数量
            $shipping_status   = $orderInfo->shipping_status;//物流状态（0：待发货；1：已发货；2：已收货；3：备货中）
            $all_refund        = 0;//订单是否已全部退款(0:否;1:是)

            if ($refunding_count > 0) {

                $orderInfo->order_status = OrderStatus::getOrderCommonStatus()[6]['status_id']; // 退款中

            } elseif ($refunded_num_count == $total_num_count) {

                $all_refund = 1;

            } elseif ($shipping_status == OrderStatus::getShippingStatus()[0]['shipping_status']) {

                $orderInfo->order_status = OrderStatus::getOrderCommonStatus()[1]['status_id']; // 待发货

            } elseif ($shipping_status == OrderStatus::getShippingStatus()[1]['shipping_status']) {

                $orderInfo->order_status = OrderStatus::getOrderCommonStatus()[2]['status_id']; // 已发货

            } elseif ($shipping_status == OrderStatus::getShippingStatus()[2]['shipping_status']) {

                $orderInfo->order_status = OrderStatus::getOrderCommonStatus()[3]['status_id']; // 已收货

            }

            // 订单恢复正常操作
            if ($all_refund == 0) {
                $retval = $orderInfo->save();
            } else {
                // 全部退款订单转化为已退款
                $retval = $this->orderCloseRefund($order_id);
            }
            $orderInfo->commit();
            return $retval;
        } catch (\Exception $e) {
            $orderInfo->rollback();
            return $e->getMessage();
        }

        return $retval;
    }

    /**
     * 获取礼品订单详情
     *
     * @param unknown $order_id
     */
    public function getGiftDetail($order_no)
    {
        // 查询主表
        $order_detail = $this->order->getInfo([
            "order_no" => $order_no,
            "is_deleted" => 0
        ]);
        if (empty($order_detail)) {
            return array();
        }
        // 查询订单项表
        $order_detail['order_goods'] = $this->getOrderGoods($order_detail["order_id"]);
        return $order_detail;
    }

    /**
     * 获取订单详情
     *
     * @param unknown $order_id
     */
    public function getDetail($order_id)
    {
        // 查询主表
        $order_detail = $this->order->getInfo([
            "order_id" => $order_id,
            "is_deleted" => 0
        ]);
        if (empty($order_detail)) {
            return array();
        }
        // 发票信息
        $temp_array = array();
        if ($order_detail["buyer_invoice"] != "") {
            $temp_array = explode("$", $order_detail["buyer_invoice"]);
        }
        $order_detail["buyer_invoice_info"] = $temp_array;
        if (empty($order_detail)) {
            return '';
        }
        $order_detail['payment_type_name'] = OrderStatus::getPayType($order_detail['payment_type']);
        $express_company_name              = "";
        if ($order_detail['shipping_type'] == 1) {
            $order_detail['shipping_type_name'] = '商家配送';
            $express_company                    = new NsOrderExpressCompanyModel();

            $express_obj = $express_company->getInfo([
                "co_id" => $order_detail["shipping_company_id"]
            ], "company_name");
            if (!empty($express_obj["company_name"])) {
                $express_company_name = $express_obj["company_name"];
            }
        } elseif ($order_detail['shipping_type'] == 2) {
            $order_detail['shipping_type_name'] = '门店自提';
        } else {
            $order_detail['shipping_type_name'] = '';
        }
        $order_detail["shipping_company_name"] = $express_company_name;
        // 查询订单项表
        $order_detail['order_goods'] = $this->getOrderGoods($order_id);
        if ($order_detail['payment_type'] == 6 || $order_detail['shipping_type'] == 2) {
            $order_status = OrderStatus::getSinceOrderStatus();
        } else {
            // 查询操作项
            $order_status = OrderStatus::getOrderCommonStatus();
        }
        // 查询订单提货信息表
        if ($order_detail['shipping_type'] == 2) {
            $order_pickup_model                 = new NsOrderPickupModel();
            $order_pickup_info                  = $order_pickup_model->getInfo([
                'order_id' => $order_id
            ], '*');
            $address                            = new Address();
            $order_pickup_info['province_name'] = $address->getProvinceName($order_pickup_info['province_id']);
            $order_pickup_info['city_name']     = $address->getCityName($order_pickup_info['city_id']);
            $order_pickup_info['dictrict_name'] = $address->getDistrictName($order_pickup_info['district_id']);
            $order_detail['order_pickup']       = $order_pickup_info;
        } else {
            $order_detail['order_pickup'] = '';
        }
        // 查询订单操作
        foreach ($order_status as $k_status => $v_status) {

            if ($v_status['status_id'] == $order_detail['order_status']) {
                $order_detail['operation']        = $v_status['operation'];
                $order_detail['member_operation'] = $v_status['member_operation'];
                $order_detail['status_name']      = $v_status['status_name'];
            }
        }
        // 查询订单操作日志
        $order_action                 = new NsOrderActionModel();
        $order_action_log             = $order_action->getQuery([
            'order_id' => $order_id
        ], '*', 'action_time desc');
        $order_detail['order_action'] = $order_action_log;
        if (!empty($order_detail['receiver_province']) && !empty($order_detail['receiver_city']) && !empty($order_detail['receiver_district'])) {
            $address_service         = new Address();
            $order_detail['address'] = $address_service->getAddress($order_detail['receiver_province'], $order_detail['receiver_city'], $order_detail['receiver_district']);
            $order_detail['address'] .= $order_detail["receiver_address"];
        }
        return $order_detail;
    }

    /**
     * 查询订单的订单项列表
     *
     * @param unknown $order_id
     */
    public function getOrderGoods($order_id)
    {
        $order_goods      = new NsOrderGoodsModel();
        $order_goods_list = $order_goods->all([
            'order_id' => $order_id
        ]);
        foreach ($order_goods_list as $k => $v) {
            $order_goods_list[$k]['express_info'] = $this->getOrderGoodsExpress($v['order_goods_id']);
            $shipping_status_array                = OrderStatus::getShippingStatus();
            foreach ($shipping_status_array as $k_status => $v_status) {
                if ($v['shipping_status'] == $v_status['shipping_status']) {
                    $order_goods_list[$k]['shipping_status_name'] = $v_status['status_name'];
                }
            }
            // 商品图片
            $picture                              = new AlbumPictureModel();
            $picture_info                         = $picture->get($v['goods_picture']);
            $order_goods_list[$k]['picture_info'] = $picture_info;
            if ($v['refund_status'] != 0) {
                $order_refund_status = OrderStatus::getRefundStatus();
                foreach ($order_refund_status as $k_status => $v_status) {

                    if ($v_status['status_id'] == $v['refund_status']) {
                        $order_goods_list[$k]['refund_operation'] = $v_status['refund_operation'];
                        $order_goods_list[$k]['status_name']      = $v_status['status_name'];
                    }
                }
            } else {
                $order_goods_list[$k]['refund_operation'] = '';
                $order_goods_list[$k]['status_name']      = '';
            }
        }
        return $order_goods_list;
    }

    /**
     * 获取订单的物流信息
     *
     * @param unknown $order_id
     */
    public function getOrderExpress($order_id)
    {
        $order_goods_express = new NsOrderGoodsExpressModel();
        $order_express_list  = $order_goods_express->all([
            'order_id' => $order_id
        ]);
        return $order_express_list;
    }

    /**
     * 获取订单项的物流信息
     *
     * @param unknown $order_goods_id
     * @return multitype:|Ambigous
     */
    private function getOrderGoodsExpress($order_goods_id)
    {
        $order_goods      = new NsOrderGoodsModel();
        $order_goods_info = $order_goods->getInfo([
            'order_goods_id' => $order_goods_id
        ], 'order_id,shipping_status');
        if ($order_goods_info['shipping_status'] == 0) {
            return array();
        } else {
            $order_express_list = $this->getOrderExpress($order_goods_info['order_id']);
            foreach ($order_express_list as $k => $v) {
                $order_goods_id_array = explode(",", $v['order_goods_id_array']);
                if (in_array($order_goods_id, $order_goods_id_array)) {
                    return $v;
                }
            }
            return array();
        }
    }

    /**
     * 订单价格调整
     *
     * @param unknown $order_id
     * @param unknown $goods_money
     *            调整后的商品总价
     * @param unknown $shipping_fee
     *            调整后的运费
     */
    public function orderAdjustMoney($order_id, $goods_money, $shipping_fee)
    {
        $this->order->startTrans();
        try {
            $order_model = new NsOrderModel();
            $order_info  = $order_model->getInfo([
                'order_id' => $order_id
            ], 'goods_money,shipping_money,order_money,pay_money');
            // 商品金额差额
            $goods_money_adjust  = $goods_money - $order_info['goods_money'];
            $shipping_fee_adjust = $shipping_fee - $order_info['shipping_money'];
            $order_money         = $order_info['order_money'] + $goods_money_adjust + $shipping_fee_adjust;
            $pay_money           = $order_info['pay_money'] + $goods_money_adjust + $shipping_fee_adjust;
            $data                = array(
                'goods_money' => $goods_money,
                'order_money' => $order_money,
                'shipping_money' => $shipping_fee,
                'pay_money' => $pay_money
            );
            $retval              = $order_model->save($data, [
                'order_id' => $order_id
            ]);
            $this->addOrderAction($order_id, $this->uid, '调整金额');
            $this->order->commit();
            return $retval;
        } catch (\Exception $e) {
            $this->order->rollback();
            return $e;
        }
    }

    /**
     * 获取订单整体商品金额(根据订单项)
     *
     * @param unknown $order_id
     */
    public function getOrderGoodsMoney($order_id)
    {
        $order_goods = new NsOrderGoodsModel();
        $money       = $order_goods->getSum([
            'order_id' => $order_id
        ], 'goods_money');
        if (empty($money)) {
            $money = 0;
        }
        return $money;
    }

    /**
     * 获取订单赠品
     *
     * @param unknown $order_id
     */
    public function getOrderPromotionGift($order_id)
    {
        $gift_list               = array();
        $order_promotion_details = new NsOrderPromotionDetailsModel();
        $promotion_list          = $order_promotion_details->getQuery([
            'order_id' => $order_id,
            'promotion_type_id' => 1
        ], 'promotion_id', '');
        if (!empty($promotion_list)) {
            foreach ($promotion_list as $k => $v) {
                $rule        = new NsPromotionMansongRuleModel();
                $gift        = $rule->getInfo([
                    'rule_id' => $v['promotion_id']
                ], 'gift_id');
                $gift_list[] = $gift['gift_id'];
            }
        }
        return $gift_list;
    }

    /**
     * 获取具体订单项信息
     *
     * @param unknown $order_goods_id
     *            订单项ID
     */
    public function getOrderGoodsInfo($order_goods_id)
    {
        $order_goods = new NsOrderGoodsModel();
        return $order_goods->getInfo([
            'order_goods_id' => $order_goods_id
        ], 'goods_id,goods_name,goods_money,goods_picture,shop_id');
    }

    /**
     * 通过订单id 得到该订单的世纪支付金额
     *
     * @param unknown $order_id
     */
    public function getOrderRealPayMoney($order_id)
    {
        $order_goods_model = new NsOrderGoodsModel();
        // 查询订单的所有的订单项
        $order_goods_list = $order_goods_model->getQuery([
            "order_id" => $order_id
        ], "goods_money,adjust_moneyrefund_real_money", "");
        $order_real_money = 0;
        if (!empty($order_goods_list)) {
            $order_goods_promotion = new NsOrderGoodsPromotionDetailsModel();
            foreach ($order_goods_list as $k => $order_goods) {
                $promotion_money = $order_goods_promotion->getSum([
                    'order_id' => $order_id,
                    'sku_id' => $order_goods['sku_id']
                ], 'discount_money');
                if (empty($promotion_money)) {
                    $promotion_money = 0;
                }
                // 订单项的真实付款金额
                $order_goods_real_money = $order_goods['goods_money'] + $order_goods['adjust_money'] - $order_goods['refund_real_money'] - $promotion_money;
                // 订单付款金额
                $order_real_money = $order_real_money + $order_goods_real_money;
            }
        }
        return $order_real_money;
    }

    /**
     * 订单提货
     *
     * @param unknown $order_id
     */
    public function pickupOrder($order_id, $buyer_name, $buyer_phone, $remark)
    {
        // 订单转为已收货状态
        $this->order->startTrans();
        try {
            $data_take_delivery = array(
                'shipping_status' => 2,
                'order_status' => 3,
                'sign_time' => time()
            );
            $order_model        = new NsOrderModel();
            $order_model->save($data_take_delivery, [
                'order_id' => $order_id
            ]);
            $this->addOrderAction($order_id, $this->uid, '订单提货' . '提货人：' . $buyer_name . ' ' . $buyer_phone);
            // 记录提货信息
            $order_pickup_model = new NsOrderPickupModel();
            $data_pickup        = array(
                'buyer_name' => $buyer_name,
                'buyer_mobile' => $buyer_phone,
                'remark' => $remark
            );
            $order_pickup_model->save($data_pickup, [
                'order_id' => $order_id
            ]);
            $order_goods_model = new NsOrderGoodsModel();
            $order_goods_model->save([
                'shipping_status' => 2
            ], [
                'order_id' => $order_id
            ]);
            $this->giveGoodsOrderPoint($order_id, 2);
            $this->order->commit();
            return 1;
        } catch (\Exception $e) {

            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 订单发放
     *
     * @param unknown $order_id
     */
    public function giveGoodsOrderPoint($order_id, $type)
    {
        // 判断是否需要在本阶段赠送积分
        $order_model = new NsOrderModel();
        $order_info  = $order_model->getInfo([
            "order_id" => $order_id
        ], "give_point_type,shop_id,buyer_id,give_point");
        if ($order_info["give_point_type"] == $type) {
            if ($order_info["give_point"] > 0) {
                $member_account = new MemberAccount();
                $text           = "";
                if ($order_info["give_point_type"] == 1) {
                    $text = "商城订单完成赠送积分";
                } elseif ($order_info["give_point_type"] == 2) {
                    $text = "商城订单完成收货赠送积分";
                } elseif ($order_info["give_point_type"] == 3) {
                    $text = "商城订单完成支付赠送积分";
                }
                $member_account->addMemberAccountData($order_info['shop_id'], 1, $order_info['buyer_id'], 1, $order_info['give_point'], 1, $order_id, $text);
            }
        }
    }

    /**
     * 添加订单退款账号记录
     * 创建时间：2017年10月18日 10:03:37 王永杰
     *
     * @ERROR!!!
     *
     * @see \data\api\IOrder::addOrderRefundAccountRecords()
     */
    public function addOrderRefundAccountRecords($order_goods_id, $refund_trade_no, $refund_money, $refund_way, $buyer_id, $remark)
    {
        $model = new NsOrderRefundAccountRecordsModel();

        $data = array(
            'order_goods_id' => $order_goods_id,
            'refund_trade_no' => $refund_trade_no,
            'refund_money' => $refund_money,
            'refund_way' => $refund_way,
            'buyer_id' => $buyer_id,
            'refund_time' => time(),
            'remark' => $remark
        );
        $res  = $model->save($data);
        return $res;
    }

    public function addOrderRefundAccountRecordses($order_goods_id, $refund_type, $refund_require_money, $refund_require_num, $refund_reason, $status_id, $refund_trade_no)
    {
        $model = new NsOrderRefundAccountRecordsModel();

        $data = array(
            'order_goods_id' => $order_goods_id,
            'refund_trade_no' => $refund_trade_no,
            'buyer_id' => $this->uid,
            'refund_type' => $refund_type,
            'refund_require_money' => $refund_require_money,
            'refund_require_num' => $refund_require_num,
            'refund_reason' => $refund_reason,
            'refund_status' => $status_id,
            'askfor_time' => time(),
            'update_time' => time()
        );
        $res  = $model->save($data);
        return $res;
    }

    /**
     * 根据订单id查询赠品发放记录需要的信息
     * 创建时间：2018年1月25日11:51:33
     *
     * @param unknown $order_id
     */
    public function addPromotionGiftGrantRecords($order_id, $uid, $nick_name)
    {
        $order_goods_model = new NsOrderGoodsModel(); // 订单项
        $gift_model        = new NsPromotionGiftModel(); // 赠品活动
        $gift_goods_model  = new NsPromotionGiftGoodsModel(); // 商品赠品
        $promotion         = new Promotion();

        // 查询赠品订单项
        $list = $order_goods_model->getQuery([
            'order_id' => $order_id,
            'gift_flag' => [
                '>',
                0
            ]
        ], "order_goods_id,goods_id,goods_name,goods_picture,gift_flag,shop_id", "");
        if (!empty($list)) {
            foreach ($list as $k => $v) {

                // 查询赠品id，名称
                $gift_info = $gift_model->getInfo([
                    'gift_id' => $v['gift_flag']
                ], "gift_id,gift_name");

                if (!empty($gift_info)) {

                    $type      = 1;
                    $type_name = "满减";
                    $relate_id = $v['order_goods_id']; // 关联订单id
                    $remark    = "满减送赠品";
                    $res       = $promotion->addPromotionGiftGrantRecords($v['shop_id'], $uid, $nick_name, $gift_info['gift_id'], $gift_info['gift_name'], $v['goods_name'], $v['goods_picture'], $type, $type_name, $relate_id, $remark);
                    return $res;
                }
            }
        }
    }

    /**
     * @param $uid
     * 会员
     * langsa
     */
    public function runSa($uid)
    {
        $runSa       = RunSa::instance();
        $user_info   = \think\Db::name('sys_user')->where(['uid' => $uid])->find();
        $member_info = \think\Db::name('ns_member')->where(['uid' => $uid])->find();
        #添加会员
        $memberInfo  = [
            'phone' => $user_info['user_tel'],
            'cstName' => $user_info['card_name'],          #sys_user    nick_name
            'realName' => $member_info['member_name'],      #ns_member   member_name
            'cstSrc' => 'SIT',
            'srcVal' => 'BC0001',
            'sex' => $user_info['sex'],                #sys_user    sex
            'email' => $user_info['user_email'],         #sys_user    user_email
        ];
        $record_info = \think\Db::name('bc_runsa_record')->where(['phone' => $user_info['user_tel']])->find();
        if ($record_info) return;
        $res = $runSa->addMember($memberInfo);
        #[1] => {"code":20000,"content":{"cstId":100000000010,"cstType":1,"status":2}}
        if (json_decode($res[1], true)['code'] != '20000' || json_decode($res[1], true)['content']['status'] != '2') return;
        $record_res = [
            'phone' => $user_info['user_tel'],
            'status' => 0,
            'cstId' => json_decode($res[1], true)['content']['cstId'],        #sys_user    nick_name
            'created_time' => time(),                                                 #ns_member   member_name
        ];
        #记录
        \think\Db::name('bc_runsa_record')->insert($record_res);
    }

    public function upLevel()
    {
        # 更新会员等级
        $runSa       = RunSa::instance();
        $record_list = \think\Db::name('bc_runsa_record')->where(['status' => 0])->select();
        foreach ($record_list as $v) {
            $updLevel = [
                'customer' => [
                    'id' => $v['cstId']
                ],
                'remark' => 'test-str',
                'account' => [
                    'id' => 2,
                ],
                'valDate' => date('Y-m-d H:i:s', $v['created_time'] + 365 * 86400),
                'rank' => [
                    'rankName' => null,
                    'id' => 'B',            # 降级这里参数是A
                    'rankIndex' => 2,        # 降级这里参数是1
                ]
            ];
            $runSa->updLevel($updLevel);
            \think\Db::name('bc_runsa_record')->where(['id' => $v['id']])->update(['status' => 1]);
        }
    }

    public function downLevel()
    {
        # 更新会员等级
        $runSa       = RunSa::instance();
        $record_list = \think\Db::name('bc_runsa_record')->where(['status' => 1])->select();
        foreach ($record_list as $v) {
            $updLevel = [
                'customer' => [
                    'id' => $v['cstId']
                ],
                'remark' => 'test-str',
                'account' => [
                    'id' => 2,
                ],
                'valDate' => date('Y-m-d H:i:s', $v['created_time'] + 365 * 86400),
                'rank' => [
                    'rankName' => null,
                    'id' => 'A',            # 降级这里参数是A
                    'rankIndex' => 1,        # 降级这里参数是1
                ]
            ];
            $runSa->updLevel($updLevel);
            \think\Db::name('bc_runsa_record')->where(['id' => $v['id']])->delete();
        }
    }
}