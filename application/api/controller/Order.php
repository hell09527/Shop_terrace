<?php
/**
 * Order.php
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
namespace app\api\controller;

use data\model\AlbumPictureModel;
use data\model\NsCartModel;
use data\model\NsBoxModel;
use data\model\NsGoodsModel;
use data\model\NsGoodsSkuModel;
use data\model\NsOrderGoodsModel;
use data\model\NsPromotionNeigouGoodsModel;
use data\service\Config;
use data\service\Express;
use data\service\Order\OrderGoods;
use data\service\Order as OrderService;
use data\service\Order\Order as OrderOrderService;
use data\service\promotion\GoodsExpress as GoodsExpressService;
use data\service\promotion\GoodsMansong;
use data\service\Promotion;
use data\service\promotion\GoodsPreference;
use data\service\Shop;
use data\service\Member;
use data\service\Goods;
use data\service\Express as ExpressService;
use data\model\UserModel;
use think\Db;
use think\Request;
use data\model\NsOrderModel;
use data\model\NsMemberModel;
use data\service\Share as ShareService;

;

/**
 * 订单控制器
 *
 * @author Administrator
 *
 */
class Order extends BaseController
{
    /**
     * 获取订单相关数据
     */
    // 购物车参数        order_tag:cart       cart_list:155,156
    // 立即购买参数      order_tag:buy_now    order_goods_type:1      order_sku_list:20:1
    // 分享商品购买参数   order_tag:share_buy  order_goods_type:1      share_list:20:1,21:2
    public function getOrderData()
    {
        $title = "获取订单类相关数据";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $unpaid_goback     = request()->post('unpaid_goback', '');
        $order_create_flag = request()->post('order_create_flag', '');
        $combo_id          = request()->post('combo_id', '');
        $combo_buy_num     = request()->post('combo_buy_num', '');

        // 订单创建标识，表示当前生成的订单详情已经创建好了。用途：订单创建成功后，返回上一个界面的路径是当前创建订单的详情，而不是首页
        if (!empty($order_create_flag)) {
            $data = array(
                code => 10,
                data => $_SESSION['unpaid_goback']
            );
            return $this->outMessage($title, $data);
        }

        $order_tag = request()->post('order_tag', '');      // buy_now | cart | box | combination_packages
        if (empty($order_tag)) {
            return $this->outMessage($title, '', -50, '无法获取商品信息');
        }

        $order_goods_type = request()->post('order_goods_type', '');    // 0:虚拟商品|1:实物商品|2:实物礼品
        $order_sku_list   = request()->post('order_sku_list', '');
        $cart_list        = request()->post('cart_list', '');
        $box_list         = request()->post('box_list', '');
        $share_list       = request()->post('share_list', '');

        // 判断实物类型：实物商品，虚拟商品
        if ($order_tag == "buy_now" && $order_goods_type === "0") {
            // 虚拟商品
            $data = $this->virtualOrderInfo($order_sku_list);
            if ($data['code'] == -50) {
                return $this->outMessage($title, '', -50, $data['message']);
            }
        } elseif ($order_tag == "combination_packages") {
            // 组合套餐
            $data = $this->comboPackageorderInfo($order_sku_list, $combo_id, $combo_buy_num);
            if ($data['code'] == -50) {
                return $this->outMessage($title, '', -50, $data['message']);
            }
        } elseif ($order_tag == "box" || ($order_tag == "buy_now" && $order_goods_type === "2")) {
            // 实物礼品
            $data = $this->giftOrderInfo($order_tag, $order_sku_list, $box_list);
            if ($data['code'] == -50) {
                return $this->outMessage($title, '', -50, $data['message']);
            }
        } elseif ($order_tag == "share_buy" && $order_goods_type === "1") {
            // 分享商品购买
            $data = $this->shareOrderInfo($order_tag, $share_list);
            if ($data['code'] == -50) {
                return $this->outMessage($title, '', -50, $data['message']);
            }
        } else {
            // 实物商品
            $data = $this->orderInfo($order_tag, $order_sku_list, $cart_list);
            if ($data['code'] == -50) {
                return $this->outMessage($title, '', -50, $data['message']);
            }
        }
        $data['currentTime'] = time() * 1000;
        return $this->outMessage($title, $data);
    }

    // 获取默认收货地址
    public function getDefaultExpressAddress()
    {
        $title   = "取默认收货地址";
        $member  = new Member();
        $address = $member->getDefaultExpressAddress();
        return $this->outMessage($title, $address, "0", "success");
    }

    /**
     * 待付款礼品订单需要的数据
     */
    public function giftOrderInfo($order_tag, $order_sku_list, $box_list)
    {
        $member        = new Member();
        $order         = new OrderService();
        $goods_mansong = new GoodsMansong();
        $Config        = new Config();
        $promotion     = new Promotion();
        $shop_service  = new Shop();
        $User          = new UserModel();
        // 检测礼品盒
        switch ($order_tag) {
            case "buy_now":
                // 立即购买
                $res = $this->giftBuyNowSession($order_sku_list);
                if ($res['code'] == -50) {
                    return array(
                        'code' => -50,
                        'message' => $res['message']
                    );
                }
                $goods_sku_list = $res["goods_sku_list"];
                $list           = $res["list"];
                break;
            case "box":
                // 礼品盒
                $res = $this->addShoppingBoxSession($box_list);
                if ($res['code'] == -50) {
                    return array(
                        'code' => -50,
                        'message' => $res['message']
                    );
                }
                $goods_sku_list = $res["goods_sku_list"];
                $list           = $res["list"];
                break;
        }
        $data['goods_sku_list'] = $goods_sku_list;
        $discount_money         = $goods_mansong->getGoodsMansongMoney($goods_sku_list);
        $data['discount_money'] = sprintf("%.2f", $discount_money); // 总优惠

        $count_money         = $order->getGoodsSkuListPrice($goods_sku_list);
        $data['count_money'] = sprintf("%.2f", $count_money); // 商品金额

        $pick_up_money         = $order->getPickupMoney($count_money);
        $data['pick_up_money'] = $pick_up_money;

        $count_point_exchange = 0;
        foreach ($list as $k => $v) {
            $list[$k]['price']    = sprintf("%.2f", $list[$k]['price']);
            $list[$k]['subtotal'] = sprintf("%.2f", $list[$k]['price'] * $list[$k]['num']);
            if ($v["point_exchange_type"] == 1) {
                if ($v["point_exchange"] > 0) {
                    $count_point_exchange += $v["point_exchange"] * $v["num"];
                }
            }
        }
        $data['count_point_exchange'] = $count_point_exchange; //总积分
        $data['itemlist']             = $list;

        $shop_id                                   = $this->instance_id;
        $shop_config                               = $Config->getShopConfig($shop_id);
        $order_invoice_content                     = explode(",", $shop_config['order_invoice_content']);
        $shop_config['order_invoice_content_list'] = array();
        foreach ($order_invoice_content as $v) {
            if (!empty($v)) {
                array_push($shop_config['order_invoice_content_list'], $v);
            }
        }
        $data['shop_config'] = $shop_config; // 后台配置

        $member_account = $member->getMemberAccount($this->uid, $this->instance_id);
        if ($member_account['balance'] == '' || $member_account['balance'] == 0) {
            $member_account['balance'] = '0.00';
        }
        $data['member_account'] = $member_account;// 用户余额

        $coupon_list         = $order->getMemberCouponList($goods_sku_list);
        $data['coupon_list'] = $coupon_list; // 获取优惠券

        $promotion_full_mail = $promotion->getPromotionFullMail($this->instance_id);
        if (!empty($address)) {
            $no_mail = checkIdIsinIdArr($address['city'], $promotion_full_mail['no_mail_city_id_array']);
            if ($no_mail) {
                $promotion_full_mail['is_open'] = 0;
            }
        }
        $data['promotion_full_mail'] = $promotion_full_mail; // 满额包邮

        $goods_mansong_gifts         = $this->getOrderGoodsMansongGifts($goods_sku_list);
        $data['goods_mansong_gifts'] = $goods_mansong_gifts; // 赠品列表

        $user_info         = $User->getInfo(['uid' => $this->uid], 'card_no,card_name');
        $data['card_no']   = $user_info['card_no']; // 身份证号
        $data['card_name'] = $user_info['card_name']; // 身份证姓名
        return $data;

    }

    /**
     * 待付款订单需要的数据
     * 2017年6月28日 15:24:48 王永杰
     */
    public function orderInfo($order_tag, $order_sku_list, $cart_list)
    {
        $member        = new Member();
        $order         = new OrderService();
        $goods_mansong = new GoodsMansong();
        $Config        = new Config();
        $promotion     = new Promotion();
        $shop_service  = new Shop();
        $User          = new UserModel();
        // 检测购物车
        $data['order_tag'] = $order_tag;

        switch ($order_tag) {
            case "buy_now":
                // 立即购买
                $res = $this->buyNowSession($order_sku_list);
                if ($res['code'] == -50) {
                    return array(
                        'code' => -50,
                        'message' => $res['message']
                    );
                }
                $goods_sku_list = $res["goods_sku_list"];
                $list           = $res["list"];
                break;
            case "cart":
                // 加入购物车
                $res = $this->addShoppingCartSession($cart_list);
                if ($res['code'] == -50) {
                    return array(
                        'code' => -50,
                        'message' => $res['message']
                    );
                }
                $goods_sku_list = $res["goods_sku_list"];
                $list           = $res["list"];
                break;
        }
        $data['goods_sku_list'] = $goods_sku_list;

        $address = $member->getDefaultExpressAddress(); // 获取默认收货地址
        $express = 0;

        $express_company_list  = array();
        $goods_express_service = new GoodsExpressService();
        if (!empty($address)) {
            // 物流公司
            $express_company_list = $goods_express_service->getExpressCompany($this->instance_id, $goods_sku_list, $address['province'], $address['city'], $address['district']);
            if (!empty($express_company_list)) {
                foreach ($express_company_list as $v) {
                    $express = $v['express_fee']; // 取第一个运费，初始化加载运费
                    break;
                }
            }
            $data['address_is_have'] = 1;
        } else {
            $data['address_is_have'] = 0;
        }
        $count                         = $goods_express_service->getExpressCompanyCount($this->instance_id);
        $data['express_company_count'] = $count; //物流公司数量
        $data['express']               = sprintf("%.2f", $express); // 运费
        $data['express_company_list']  = $express_company_list; // 物流公司

        $discount_money         = $goods_mansong->getGoodsMansongMoney($goods_sku_list);
        $data['discount_money'] = sprintf("%.2f", $discount_money); // 总优惠

        $count_money         = $order->getGoodsSkuListPrice($goods_sku_list);
        $data['count_money'] = sprintf("%.2f", $count_money); // 商品金额

        $pick_up_money         = $order->getPickupMoney($count_money);
        $data['pick_up_money'] = $pick_up_money;

        $count_point_exchange = 0;
        $member_info    = $member->getMemberIsEmployee();
        foreach ($list as $k => $v) {
            //查看用户员工价
            $NG_MODEL       = new NsPromotionNeigouGoodsModel();
            $promotion_info = \think\Db::name('ns_promotion_mansong')->where(['is_neigou' => 1, 'status' => 1])->find();
            if($member_info == 1) {
                if ($promotion_info) {
                    $condition1['discount_id'] = $promotion_info['mansong_id'];
                    $condition1['goods_id']    = $v['goods_id'];
                    $condition1['sku_id']      = $v['sku_id'];
                    $goods_sku = new NsGoodsSkuModel();
                    $goods_sku_detail = $goods_sku->where(['sku_id'=>$v['sku_id']])
                        ->find();
                    $promotion_goods_info = $NG_MODEL->getInfo($condition1);
                    if($promotion_goods_info){
                        $list[$k]['price'] = $promotion_goods_info['n_price'] == 0 ?
                            $promotion_goods_info['n_discount'] / 10 * $goods_sku_detail['price'] :
                            $promotion_goods_info['n_price'];
                    }
                }
            }

            $list[$k]['price']    = sprintf("%.2f", $list[$k]['price']);
            $list[$k]['subtotal'] = sprintf("%.2f", $list[$k]['price'] * $list[$k]['num']);
            if ($v["point_exchange_type"] == 1) {
                if ($v["point_exchange"] > 0) {
                    $count_point_exchange += $v["point_exchange"] * $v["num"];
                }
            }
        }
        $data['count_point_exchange'] = $count_point_exchange; //总积分
        $data['itemlist']             = $list;

        $shop_id                                   = $this->instance_id;
        $shop_config                               = $Config->getShopConfig($shop_id);
        $order_invoice_content                     = explode(",", $shop_config['order_invoice_content']);
        $shop_config['order_invoice_content_list'] = array();
        foreach ($order_invoice_content as $v) {
            if (!empty($v)) {
                array_push($shop_config['order_invoice_content_list'], $v);
            }
        }
        $data['shop_config'] = $shop_config; // 后台配置

        $member_account = $member->getMemberAccount($this->uid, $this->instance_id);
        if ($member_account['balance'] == '' || $member_account['balance'] == 0) {
            $member_account['balance'] = '0.00';
        }
        $data['member_account'] = $member_account;// 用户余额

        $coupon_list         = $order->getMemberCouponList($goods_sku_list);
        $data['coupon_list'] = $coupon_list; // 获取优惠券

        $promotion_full_mail = $promotion->getPromotionFullMail($this->instance_id);
        if (!empty($address)) {
            $no_mail = checkIdIsinIdArr($address['city'], $promotion_full_mail['no_mail_city_id_array']);
            if ($no_mail) {
                $promotion_full_mail['is_open'] = 0;
            }
        }
        $data['promotion_full_mail'] = $promotion_full_mail; // 满额包邮

        $pickup_point_list         = $shop_service->getPickupPointList();
        $data['pickup_point_list'] = $pickup_point_list; // 自提地址列表

        $data['address_default'] = $address;

        $goods_mansong_gifts         = $this->getOrderGoodsMansongGifts($goods_sku_list);

        $data['goods_mansong_gifts'] = $goods_mansong_gifts; // 赠品列表

        $user_info         = $User->getInfo(['uid' => $this->uid], 'card_no,card_name');
        $data['card_no']   = $user_info['card_no']; // 身份证号
        $data['card_name'] = $user_info['card_name']; // 身份证姓名
        return $data;
    }

    /**
     * 待付款订单需要的数据 虚拟订单
     * 2017年6月28日 15:24:48 王永杰
     */
    public function virtualOrderInfo($order_sku_list)
    {
        if ($this->getIsOpenVirtualGoodsConfig() == 0) {
            $this->error("未开启虚拟商品功能");
        }
        $member        = new Member();
        $order         = new OrderService();
        $goods_mansong = new GoodsMansong();
        $Config        = new Config();
        $promotion     = new Promotion();
        $shop_service  = new Shop();
        $res           = $this->buyNowSession($order_sku_list);
        if ($res['code'] == -50) {
            return array(
                'code' => $res['code'],
                'message' => $res['message']
            );
        }
        $goods_sku_list         = $res["goods_sku_list"];
        $list                   = $res["list"];
        $shop_id                = $this->instance_id;
        $data['goods_sku_list'] = $goods_sku_list;

        $discount_money         = $goods_mansong->getGoodsMansongMoney($goods_sku_list);
        $data['discount_money'] = sprintf("%.2f", $discount_money);
        $count_money            = $order->getGoodsSkuListPrice($goods_sku_list);
        $data['count_money']    = sprintf("%.2f", $count_money);
        $count_point_exchange   = 0;
        foreach ($list as $k => $v) {
            $list[$k]['price']    = sprintf("%.2f", $list[$k]['price']);
            $list[$k]['subtotal'] = sprintf("%.2f", $list[$k]['price'] * $list[$k]['num']);
            if ($v["point_exchange_type"] == 1) {
                if ($v["point_exchange"] > 0) {
                    $count_point_exchange += $v["point_exchange"] * $v["num"];
                }
            }
        }
        $data['count_point_exchange']              = $count_point_exchange;
        $data['itemlist']                          = $list;
        $shop_config                               = $Config->getShopConfig($shop_id);
        $order_invoice_content                     = explode(",", $shop_config['order_invoice_content']);
        $shop_config['order_invoice_content_list'] = array();
        foreach ($order_invoice_content as $v) {
            if (!empty($v)) {
                array_push($shop_config['order_invoice_content_list'], $v);
            }
        }
        $data['shop_config'] = $shop_config;
        $member_account      = $member->getMemberAccount($this->uid, $shop_id);
        if ($member_account['balance'] == '' || $member_account['balance'] == 0) {
            $member_account['balance'] = '0.00';
        }
        $data['member_account'] = $member_account;
        $coupon_list            = $order->getMemberCouponList($goods_sku_list);
        $data['coupon_list']    = $coupon_list;
        $member                 = new Member();
        $user_telephone         = $member->getUserTelephone();
        $data['user_telephone'] = $user_telephone;

        return $data;
    }

    /**
     * 待付款订单需要的数据 组合套餐
     * 2017年11月22日 10:07:26 王永杰
     */
    public function comboPackageorderInfo($order_sku_list, $combo_id, $combo_buy_num)
    {
        $member                = new Member();
        $order                 = new OrderService();
        $goods_mansong         = new GoodsMansong();
        $Config                = new Config();
        $promotion             = new Promotion();
        $shop_service          = new Shop();
        $goods_express_service = new GoodsExpressService();
        $res                   = $this->combination_packagesSession($order_sku_list, $combo_id, $combo_buy_num); // 获取组合套餐session
        if ($res['code'] == -50) {
            return array(
                'code' => $res['code'],
                'message' => $res['message']
            );
        }
        // 套餐信息
        $combo_id              = $res["combo_id"];
        $combo_detail          = $promotion->getComboPackageDetail($combo_id);
        $data['combo_detail']  = $combo_detail;
        $data['combo_buy_num'] = $res["combo_buy_num"];
        $goods_sku_list        = $res["goods_sku_list"];
        $list                  = $res["list"];

        $goods_sku_list = trim($goods_sku_list);
        if (empty($goods_sku_list)) {
            return array(
                'code' => -50,
                'message' => '待支付订单中商品不可为空'
            );
        }
        $data['goods_sku_list'] = $goods_sku_list;
        $address                = $member->getDefaultExpressAddress(); // 获取默认收货地址
        $express                = 0;

        $express_company_list  = array();
        $goods_express_service = new GoodsExpressService();
        if (!empty($address)) {
            // 物流公司
            $express_company_list = $goods_express_service->getExpressCompany($this->instance_id, $goods_sku_list, $address['province'], $address['city'], $address['district']);
            if (!empty($express_company_list)) {
                foreach ($express_company_list as $v) {
                    $express = $v['express_fee']; // 取第一个运费，初始化加载运费
                    break;
                }
            }
            $data['address_is_have'] = 1;
        } else {
            $data['address_is_have'] = 0;
        }
        $count                         = $goods_express_service->getExpressCompanyCount($this->instance_id);
        $data['express_company_count'] = $count;
        $data['express']               = sprintf("%.2f", $express);
        $data['express_company_list']  = $express_company_list;
        $count_money                   = $order->getComboPackageGoodsSkuListPrice($goods_sku_list); // 商品金额
        $data['count_money']           = sprintf("%.2f", $count_money);
        $combo_package_price           = $combo_detail["combo_package_price"] * $res["combo_buy_num"]; // 套餐总金额
        $data['combo_package_price']   = $combo_package_price;
        $discount_money                = $count_money - ($combo_detail["combo_package_price"] * $res["combo_buy_num"]); // 计算优惠金额
        $discount_money                = $discount_money < 0 ? 0 : $discount_money;
        $data['discount_money']        = sprintf("%.2f", $discount_money);
        // 计算自提点运费
        $pick_up_money = $order->getPickupMoney($combo_package_price);
        if (empty($pick_up_money)) {
            $pick_up_money = 0;
        }
        $data['pick_up_money'] = $pick_up_money;
        $count_point_exchange  = 0;
        foreach ($list as $k => $v) {
            $list[$k]['price']    = sprintf("%.2f", $list[$k]['price']);
            $list[$k]['subtotal'] = sprintf("%.2f", $list[$k]['price'] * $list[$k]['num']);
            if ($v["point_exchange_type"] == 1) {
                if ($v["point_exchange"] > 0) {
                    $count_point_exchange += $v["point_exchange"] * $v["num"];
                }
            }
        }
        $data['itemlist']                          = $list;
        $shop_id                                   = $this->instance_id;
        $shop_config                               = $Config->getShopConfig($shop_id);
        $order_invoice_content                     = explode(",", $shop_config['order_invoice_content']);
        $shop_config['order_invoice_content_list'] = array();
        foreach ($order_invoice_content as $v) {
            if (!empty($v)) {
                array_push($shop_config['order_invoice_content_list'], $v);
            }
        }

        $data['shop_config'] = $shop_config;
        $member_account      = $member->getMemberAccount($this->uid, $this->instance_id);
        if ($member_account['balance'] == '' || $member_account['balance'] == 0) {
            $member_account['balance'] = '0.00';
        }
        $data['member_account'] = $member_account;
        $promotion_full_mail    = $promotion->getPromotionFullMail($this->instance_id);
        if (!empty($address)) {
            $no_mail = checkIdIsinIdArr($address['city'], $promotion_full_mail['no_mail_city_id_array']);
            if ($no_mail) {
                $promotion_full_mail['is_open'] = 0;
            }
        }
        $data['promotion_full_mail'] = $promotion_full_mail;
        $pickup_point_list           = $shop_service->getPickupPointList();
        $data['pickup_point_list']   = $pickup_point_list;
        $data['address_default']     = $address;

        return $data;
    }

    /**
     * 添加分享清单
     *
     * @return unknown
     */
    public function shareAdd()
    {
        $title      = "添加分享信息";
        $share_no   = request()->post('share_no', '');
        $share_id   = request()->post('share_id', '');
        $share_list = request()->post('share_list', '');
        if (empty($share_no)) {
            return $this->outMessage($title, "", '-50', "无法获编号");
        }

        if (empty($share_id)) {
            return $this->outMessage($title, "", '-50', "无法获取分享者信息");
        }

        if (empty($share_list)) {
            return $this->outMessage($title, "", '-50', "无法获取分享商品信息");
        }

        $share_service = new ShareService();
        $result        = $share_service->addShare($share_no, $share_id, $share_list);
        return $this->outMessage($title, $result);
    }

    /**
     * 点击分享清单
     *
     * @return unknown
     */
    public function shareList()
    {
        $title = "获取分享商品信息";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }

        $share_no = request()->post('share_no', '');
        if (empty($share_no)) {
            return $this->outMessage($title, "", '-50', "无法获取分享商品信息");
        }

        $share_service = new ShareService();
        $shareDetail   = $share_service->shareList($this->uid, $share_no);
        return $this->outMessage($title, $shareDetail);
    }

    /**
     * 分享清单
     *
     * @return unknown
     */
    public function share()
    {
        $title = "获取分享商品信息";
//        if (empty($this->uid)) {
//            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
//        }

        $share_sku_list = request()->post('share_sku_list', '');
        if (empty($share_sku_list)) {
            return $this->outMessage($title, "", '-50', "无法获取分享商品信息");
        }

        $share_sku_array = explode(",", $share_sku_list);
        $list            = Array();
        foreach ($share_sku_array as $v) {
            $share_list     = array();
            $order_sku_list = explode(":", $v);
            $sku_id         = $order_sku_list[0];
            $num            = $order_sku_list[1];
            $price          = $order_sku_list[2];

            // 获取商品sku信息
            $goods_sku = new \data\model\NsGoodsSkuModel();
            $sku_info  = $goods_sku->getInfo([
                'sku_id' => $sku_id
            ], '*');

            if (empty($sku_info)) {
                continue;
            }

            $goods      = new NsGoodsModel();
            $goods_info = $goods->getInfo([
                'goods_id' => $sku_info["goods_id"]
            ], 'max_buy,state,point_exchange_type,point_exchange,picture,goods_id,goods_name,source_type');

            if ($goods_info['state'] != 1) {
                continue;
            }

            $share_list["stock"]               = $sku_info['stock']; // 库存
            $share_list["sku_id"]              = $sku_info["sku_id"];
            $share_list["sku_name"]            = $sku_info["sku_name"];
            $share_list["goods_id"]            = $goods_info["goods_id"];
            $share_list["goods_name"]          = $goods_info["goods_name"];
            $share_list["max_buy"]             = $goods_info['max_buy']; // 限购数量
            $share_list['point_exchange_type'] = $goods_info['point_exchange_type']; // 积分兑换类型 0 非积分兑换 1 只能积分兑换
            $share_list['point_exchange']      = $goods_info['point_exchange']; // 积分兑换
            $share_list["source_type"]         = $goods_info["source_type"];

            // 如果购买的数量超过限购，则取限购数量
            if ($goods_info['max_buy'] != 0 && $goods_info['max_buy'] < $num) {
                $num = $goods_info['max_buy'];
            }
            // 如果购买的数量超过库存，则取库存数量
            if ($sku_info['stock'] < $num) {
                $num = $sku_info['stock'];
            }
            $share_list["num"]   = $num;
            $share_list["price"] = $price;

            // 查询当前商品是否有SKU主图
            $order_goods_service = new OrderGoods();
            $picture             = $order_goods_service->getSkuPictureBySkuId($sku_info);

            // 获取图片信息
            $album_picture_model        = new AlbumPictureModel();
            $picture_info               = $album_picture_model->get($picture == 0 ? $goods_info['picture'] : $picture);
            $share_list['picture_info'] = $picture_info;

            if (count($share_list) == 0) {
                continue;
            }
            $list[] = $share_list;
        }

        return $this->outMessage($title, $list);
    }

//    public function debuger($val){
//
//        $TEXT = '类型：' .gettype($val) . PHP_EOL;
//
//        $TEXT.= 'zhi :' .$val. PHP_EOL;
//
//        file_put_contents('/tmp/11.log', $TEXT, FILE_APPEND);
//    }

    /**
     * 分享商品购买需要的数据
     */
    public function shareOrderInfo($order_tag, $share_list)
    {
        $member        = new Member();
        $order         = new OrderService();
        $goods_mansong = new GoodsMansong();
        $Config        = new Config();
        $promotion     = new Promotion();
        $shop_service  = new Shop();
        $User          = new UserModel();
        // 检测购物车
        $data['order_tag'] = $order_tag;

        $res = $this->shareBuySession($share_list);

        $goods_sku_list = $res["goods_sku_list"];
        $list           = $res["list"];

        $data['goods_sku_list'] = $goods_sku_list;

        $address = $member->getDefaultExpressAddress(); // 获取默认收货地址
        $express = 0;

        $express_company_list  = array();
        $goods_express_service = new GoodsExpressService();
        if (!empty($address)) {
            // 物流公司
            $express_company_list = $goods_express_service->getExpressCompany($this->instance_id, $goods_sku_list, $address['province'], $address['city'], $address['district']);
            if (!empty($express_company_list)) {
                foreach ($express_company_list as $v) {
                    $express = $v['express_fee']; // 取第一个运费，初始化加载运费
                    break;
                }
            }
            $data['address_is_have'] = 1;
        } else {
            $data['address_is_have'] = 0;
        }
        $count                         = $goods_express_service->getExpressCompanyCount($this->instance_id);
        $data['express_company_count'] = $count; //物流公司数量
        $data['express']               = sprintf("%.2f", $express); // 运费
        $data['express_company_list']  = $express_company_list; // 物流公司

        $discount_money         = $goods_mansong->getShareGoodsMansongMoney($share_list);
        $data['discount_money'] = sprintf("%.2f", $discount_money); // 总优惠

//        $count_money = $order->getGoodsSkuListPrice($goods_sku_list);
//        $data['count_money'] = sprintf("%.2f", $count_money); // 商品金额

//        $pick_up_money = $order->getPickupMoney($count_money);
//        $data['pick_up_money'] = $pick_up_money;

        $count_point_exchange = 0;
        $count_money          = 0;
        foreach ($list as $k => $v) {
            $list[$k]['price']    = sprintf("%.2f", $list[$k]['price']);
            $list[$k]['subtotal'] = sprintf("%.2f", $list[$k]['price'] * $list[$k]['num']);
            if ($v["point_exchange_type"] == 1) {
                if ($v["point_exchange"] > 0) {
                    $count_point_exchange += $v["point_exchange"] * $v["num"];
                }
            }
            $count_money += $list[$k]['subtotal'];
        }
        $data['count_point_exchange'] = $count_point_exchange; //总积分
        $data['count_money']          = $count_money; // 商品金额
        $data['pick_up_money']        = 0;
        $data['itemlist']             = $list;

        $shop_id                                   = $this->instance_id;
        $shop_config                               = $Config->getShopConfig($shop_id);
        $order_invoice_content                     = explode(",", $shop_config['order_invoice_content']);
        $shop_config['order_invoice_content_list'] = array();
        foreach ($order_invoice_content as $v) {
            if (!empty($v)) {
                array_push($shop_config['order_invoice_content_list'], $v);
            }
        }
        $data['shop_config'] = $shop_config; // 后台配置

        $member_account = $member->getMemberAccount($this->uid, $this->instance_id);
        if ($member_account['balance'] == '' || $member_account['balance'] == 0) {
            $member_account['balance'] = '0.00';
        }
        $data['member_account'] = $member_account;// 用户余额

        # dai  新增    改价不使用优惠券
        $arr1         = explode(',',$share_list);
        $coupon_limit = 1;
        foreach($arr1 as $v){
            $_arr  = explode(':', $v);
            $price = \think\Db::name('ns_goods_sku')->where(['sku_id' => $_arr[0]])->find()['price'];

//            $this->debuger($price * 100);
//            $this->debuger($_arr[2] * 100);

            if($price * 100 != $_arr[2] * 100){
                $coupon_limit = 2;
            }
        }

        if($coupon_limit == 1){
            $coupon_list = $order->getMemberCouponList($goods_sku_list);
        }else{
            $coupon_list = [];
        }


        $data['coupon_list'] = $coupon_list; // 获取优惠券

        $promotion_full_mail = $promotion->getPromotionFullMail($this->instance_id);
        if (!empty($address)) {
            $no_mail = checkIdIsinIdArr($address['city'], $promotion_full_mail['no_mail_city_id_array']);
            if ($no_mail) {
                $promotion_full_mail['is_open'] = 0;
            }
        }
        $data['promotion_full_mail'] = $promotion_full_mail; // 满额包邮

        $pickup_point_list         = $shop_service->getPickupPointList();
        $data['pickup_point_list'] = $pickup_point_list; // 自提地址列表

        $data['address_default'] = $address;

        $goods_mansong_gifts         = $this->getShareOrderGoodsMansongGifts($share_list);
        $data['goods_mansong_gifts'] = $goods_mansong_gifts; // 赠品列表

        $user_info         = $User->getInfo(['uid' => $this->uid], 'card_no,card_name');
        $data['card_no']   = $user_info['card_no']; // 身份证号
        $data['card_name'] = $user_info['card_name']; // 身份证姓名
        return $data;
    }

    /**
     * 分享商品购买
     *
     * @return unknown
     */
    public function shareBuySession($share_list)
    {
        if (empty($share_list)) {
            return array(
                'code' => -50,
                'message' => '无法获取所选商品信息',
            ); // 没有商品
        }

        $share_arr      = explode(",", $share_list);
        $cart_list      = array();
        $list           = Array();
        $goods_sku_list = ''; // 商品skuid集合
        foreach ($share_arr as $v) {
            $order_sku_list = explode(":", $v);
            $sku_id         = $order_sku_list[0];
            $num            = $order_sku_list[1];
            $price          = $order_sku_list[2];

            // 获取商品sku信息
            $goods_sku = new \data\model\NsGoodsSkuModel();
            $sku_info  = $goods_sku->getInfo([
                'sku_id' => $sku_id
            ], '*');

            // 清除非法错误数据
            $cart = new NsCartModel();
            if (empty($sku_info)) {
                $cart->destroy([
                    'buyer_id' => $this->uid,
                    'sku_id' => $sku_id
                ]);
                continue;
            }

            // 查询当前商品是否有SKU主图
            $order_goods_service = new OrderGoods();
            $picture             = $order_goods_service->getSkuPictureBySkuId($sku_info);

            $goods      = new NsGoodsModel();
            $goods_info = $goods->getInfo([
                'goods_id' => $sku_info["goods_id"]
            ], 'max_buy,state,point_exchange_type,point_exchange,picture,goods_id,goods_name');

            if ($goods_info['state'] != 1) {
                continue;
            }

            $cart_list["stock"]               = $sku_info['stock']; // 库存
            $cart_list["sku_id"]              = $sku_info["sku_id"];
            $cart_list["sku_name"]            = $sku_info["sku_name"];
            $cart_list["goods_id"]            = $goods_info["goods_id"];
            $cart_list["goods_name"]          = $goods_info["goods_name"];
            $cart_list["max_buy"]             = $goods_info['max_buy']; // 限购数量
            $cart_list['point_exchange_type'] = $goods_info['point_exchange_type']; // 积分兑换类型 0 非积分兑换 1 只能积分兑换
            $cart_list['point_exchange']      = $goods_info['point_exchange']; // 积分兑换

            $cart_list["num"]   = $num;
            $cart_list["price"] = $price;
            // 如果购买的数量超过限购，则取限购数量
            if ($goods_info['max_buy'] != 0 && $goods_info['max_buy'] < $num) {
                $num = $goods_info['max_buy'];
            }
            // 如果购买的数量超过库存，则取库存数量
            if ($sku_info['stock'] < $num) {
                $num = $sku_info['stock'];
            }
            // 获取图片信息
            $album_picture_model       = new AlbumPictureModel();
            $picture_info              = $album_picture_model->get($picture == 0 ? $goods_info['picture'] : $picture);
            $cart_list['picture_info'] = $picture_info;

            if (count($cart_list) == 0) {
                continue;
            }
            $list[] = $cart_list;
            $goods_sku_list .= "," . $cart_list['sku_id'] . ':' . $cart_list['num'];
        }

        $res["list"]           = $list;
        $goods_sku_list        = substr($goods_sku_list, 1); // 商品sku列表
        $res["goods_sku_list"] = $goods_sku_list;
        return $res;
    }

    /**
     * 加入礼品盒
     *
     * @return unknown
     */
    public function addShoppingBoxSession($session_box_list)
    {
        // 加入礼品盒
        if ($session_box_list == "") {
            return array(
                'code' => -50,
                'message' => '无法获取所选礼品信息',
            ); // 没有商品
        }

        $box_id_arr = explode(",", $session_box_list);
        $goods      = new Goods();
        $box_list   = $goods->getBoxList($session_box_list);
        if (count($box_list) == 0) {
            return array(
                'code' => -50,
                'message' => '无法获取所选商品信息',
            ); // 没有商品
        }
        $list           = Array();
        $str_box_id     = ""; // 购物车id
        $goods_sku_list = ''; // 商品skuid集合
        for ($i = 0; $i < count($box_list); $i++) {
            if ($box_id_arr[$i] == $box_list[$i]["box_id"]) {
                $list[] = $box_list[$i];
                $str_box_id .= "," . $box_list[$i]["box_id"];
                $goods_sku_list .= "," . $box_list[$i]['sku_id'] . ':' . $box_list[$i]['num'];
            }
        }
        $goods_sku_list        = substr($goods_sku_list, 1); // 商品sku列表
        $res["list"]           = $list;
        $res["goods_sku_list"] = $goods_sku_list;
        return $res;
    }

    /**
     * 加入购物车
     *
     * @return unknown
     */
    public function addShoppingCartSession($session_cart_list)
    {
        // 加入购物车
        if ($session_cart_list == "") {
            return array(
                'code' => -50,
                'message' => '无法获取所选商品信息',
            ); // 没有商品
        }
        $cart_id_arr = explode(",", $session_cart_list);
        $goods       = new Goods();
        $cart_list   = $goods->getCartList($session_cart_list);
        if (count($cart_list) == 0) {
            return array(
                'code' => -50,
                'message' => '无法获取所选商品信息',
            ); // 没有商品
        }
        $list           = Array();
        $str_cart_id    = ""; // 购物车id
        $goods_sku_list = ''; // 商品skuid集合
        for ($i = 0; $i < count($cart_list); $i++) {
            $list[] = $cart_list[$i];
            $str_cart_id .= "," . $cart_list[$i]["cart_id"];
            $goods_sku_list .= "," . $cart_list[$i]['sku_id'] . ':' . $cart_list[$i]['num'];
        }
        $goods_sku_list        = substr($goods_sku_list, 1); // 商品sku列表
        $res["list"]           = $list;
        $res["goods_sku_list"] = $goods_sku_list;
        return $res;
    }

    /**
     * 礼品立即购买
     */
    public function giftBuyNowSession($order_sku_list)
    {
        if (empty($order_sku_list)) {
            return array(
                'code' => -50,
                'message' => '无法获取所选礼品信息',
            ); // 没有礼品
        }

        $cart_list      = array();
        $order_sku_list = explode(":", $order_sku_list);
        $sku_id         = $order_sku_list[0];
        $num            = $order_sku_list[1];

        // 获取礼品sku信息
        $goods_sku = new \data\model\NsGoodsSkuModel();
        $sku_info  = $goods_sku->getInfo([
            'sku_id' => $sku_id
        ], '*');

        // 查询当前礼品是否有SKU主图
        $order_goods_service = new OrderGoods();
        $picture             = $order_goods_service->getSkuPictureBySkuId($sku_info);

        // 清除非法错误数据
        $box = new NsBoxModel();
        if (empty($sku_info)) {
            $box->destroy([
                'buyer_id' => $this->uid,
                'sku_id' => $sku_id
            ]);
            return array(
                'code' => -50,
                'message' => '无法获取所选礼品信息',
            ); // 没有礼品返回到首页
        }
        $goods      = new NsGoodsModel();
        $goods_info = $goods->getInfo([
            'goods_id' => $sku_info["goods_id"]
        ], 'max_buy,state,point_exchange_type,point_exchange,picture,goods_id,goods_name');

        $box_list["stock"]    = $sku_info['stock']; // 库存
        $box_list["sku_id"]   = $sku_info["sku_id"];
        $box_list["sku_name"] = $sku_info["sku_name"];

        $goods_preference = new GoodsPreference();
//        $member_price = $goods_preference->getGoodsSkuMemberPrice($sku_info['sku_id'], $this->uid);
//        $box_list["price"] = $member_price < $sku_info['promote_price'] ? $member_price : $sku_info['promote_price'];
        if (!empty($this->uid)) {
            $is_vip = $goods_preference->getMemberIsVip($this->uid);
        } else {
            $is_vip = 0;
        }
        if ($is_vip == 1 && $sku_info['vip_price'] > 0) {
            $box_list["price"] = $sku_info['vip_price'];
        } else {
            $box_list["price"] = $sku_info["promote_price"];
        }
        $box_list["goods_id"]            = $goods_info["goods_id"];
        $box_list["goods_name"]          = $goods_info["goods_name"];
        $box_list["max_buy"]             = $goods_info['max_buy']; // 限购数量
        $box_list['point_exchange_type'] = $goods_info['point_exchange_type']; // 积分兑换类型 0 非积分兑换 1 只能积分兑换
        $box_list['point_exchange']      = $goods_info['point_exchange']; // 积分兑换
        if ($goods_info['state'] != 1) {
            $message = $goods_info['state'] == 0 ? '商品已下架' : '商品违规禁售';
            return array(
                'code' => -50,
                'message' => $message
            );
        }
        $box_list["num"] = $num;
        // 如果购买的数量超过限购，则取限购数量
        if ($goods_info['max_buy'] != 0 && $goods_info['max_buy'] < $num) {
            $num = $goods_info['max_buy'];
        }
        // 如果购买的数量超过库存，则取库存数量
        if ($sku_info['stock'] < $num) {
            $num = $sku_info['stock'];
        }
        // 获取图片信息
        $album_picture_model      = new AlbumPictureModel();
        $picture_info             = $album_picture_model->get($picture == 0 ? $goods_info['picture'] : $picture);
        $box_list['picture_info'] = $picture_info;

        // 获取礼品阶梯优惠信息
        $goods_service     = new Goods();
        $box_list["price"] = $goods_service->getGoodsLadderPreferentialInfo($goods_info["goods_id"], $num, $box_list["price"]);

        if (count($box_list) == 0) {
            return array(
                'code' => -50,
                'message' => '无法获取所选礼品信息',
            ); // 没有礼品返回到首页
        }
        $list[]                = $box_list;
        $goods_sku_list        = $sku_id . ":" . $num; // 礼品skuid集合
        $res["list"]           = $list;
        $res["goods_sku_list"] = $goods_sku_list;
        return $res;
    }

    /**
     * 商品立即购买
     */
    public function buyNowSession($order_sku_list)
    {
        if (empty($order_sku_list)) {
            return array(
                'code' => -50,
                'message' => '无法获取所选商品信息',
            ); // 没有商品
        }

        $cart_list      = array();
        $order_sku_list = explode(":", $order_sku_list);
        $sku_id         = $order_sku_list[0];
        $num            = $order_sku_list[1];

        // 获取商品sku信息
        $goods_sku = new \data\model\NsGoodsSkuModel();
        $sku_info  = $goods_sku->getInfo([
            'sku_id' => $sku_id
        ], '*');

        // 查询当前商品是否有SKU主图
        $order_goods_service = new OrderGoods();
        $picture             = $order_goods_service->getSkuPictureBySkuId($sku_info);

        // 清除非法错误数据
        $cart = new NsCartModel();
        if (empty($sku_info)) {
            $cart->destroy([
                'buyer_id' => $this->uid,
                'sku_id' => $sku_id
            ]);
            return array(
                'code' => -50,
                'message' => '无法获取所选商品信息',
            ); // 没有商品返回到首页
        }
        $goods      = new NsGoodsModel();
        $goods_info = $goods->getInfo([
            'goods_id' => $sku_info["goods_id"]
        ], 'max_buy,state,point_exchange_type,point_exchange,picture,goods_id,goods_name,sale_type,sale_end_time,delivery_end_time');

        $cart_list["stock"]    = $sku_info['stock']; // 库存
        $cart_list["sku_id"]   = $sku_info["sku_id"];
        $cart_list["sku_name"] = $sku_info["sku_name"];

//        $goods_preference = new GoodsPreference();
//        $member_price = $goods_preference->getGoodsSkuMemberPrice($sku_info['sku_id'], $this->uid);
//        $cart_list["price"] = $member_price < $sku_info['promote_price'] ? $member_price : $sku_info['promote_price'];

        $member = new Member();

        // 查看用户会员价
        if (!empty($this->uid)) {
            $is_vip = $member->getMemberIsVip($this->uid);
        } else {
            $is_vip = 0;
        }
        if ($is_vip == 1 && $sku_info['vip_price'] > 0) {
            $cart_list["price"] = $sku_info['vip_price'];
        } else {
            $cart_list["price"] = $sku_info["promote_price"];
        }


        #todo   @dai   员工内购
        //查看用户员工价
        $NG_MODEL       = new NsPromotionNeigouGoodsModel();
        $promotion_info = \think\Db::name('ns_promotion_mansong')->where(['is_neigou' => 1, 'status' => 1])->find();
        $member_info    = $member->getMemberIsEmployee();
        if($member_info == 1) {
            if ($promotion_info) {
                $condition1['discount_id']                = $promotion_info['mansong_id'];
                $condition1['goods_id']                   = $goods_info['goods_id'];
                $condition1['sku_id']                     = $sku_id;
                $promotion_goods_info                     = $NG_MODEL->getInfo($condition1);
                if($promotion_goods_info){
                    $n_price = $promotion_goods_info['n_price'] == 0 ?
                        $promotion_goods_info['n_discount'] * $sku_info['price'] / 10 :
                        $promotion_goods_info['n_price'];
                    $cart_list["price"] = sprintf("%.2f", $n_price);
                }
            }
        }

        # @fu
//        $is_employee = $member->getMemberIsEmployee();
//        if($is_employee == 1 && $goods_info['is_inside_sell'] == 1 ){
//            $cart_list["price"] = $sku_info['inside_price'];
//        }



        $cart_list["goods_id"]            = $goods_info["goods_id"];
        $cart_list["goods_name"]          = $goods_info["goods_name"];
        $cart_list["max_buy"]             = $goods_info['max_buy']; // 限购数量
        $cart_list['point_exchange_type'] = $goods_info['point_exchange_type']; // 积分兑换类型 0 非积分兑换 1 只能积分兑换
        $cart_list['point_exchange']      = $goods_info['point_exchange']; // 积分兑换
        $cart_list['sale_type']      = $goods_info['sale_type']; // 销售方式（1：现售；2：预售）
        $cart_list['sale_end_time']      = $goods_info['sale_end_time']; // 预售截止时间
        $cart_list['delivery_end_time']      = $goods_info['delivery_end_time']; // 预售发货提示
        if ($goods_info['state'] != 1) {
            $message = $goods_info['state'] == 0 ? '商品已下架' : '商品违规禁售';
            return array(
                'code' => -50,
                'message' => $message
            );
        }
        $cart_list["num"] = $num;
        // 如果购买的数量超过限购，则取限购数量
        if ($goods_info['max_buy'] != 0 && $goods_info['max_buy'] < $num) {
            $num = $goods_info['max_buy'];
        }
        // 如果购买的数量超过库存，则取库存数量
        if ($sku_info['stock'] < $num) {
            $num = $sku_info['stock'];
        }
        // 获取图片信息
        $album_picture_model       = new AlbumPictureModel();
        $picture_info              = $album_picture_model->get($picture == 0 ? $goods_info['picture'] : $picture);
        $cart_list['picture_info'] = $picture_info;

        // 获取商品阶梯优惠信息
        $goods_service      = new Goods();
        $cart_list["price"] = $goods_service->getGoodsLadderPreferentialInfo($goods_info["goods_id"], $num, $cart_list["price"]);

        if (count($cart_list) == 0) {
            return array(
                'code' => -50,
                'message' => '无法获取所选商品信息',
            ); // 没有商品返回到首页
        }
        $list[]                = $cart_list;
        $goods_sku_list        = $sku_id . ":" . $num; // 商品skuid集合
        $res["list"]           = $list;
        $res["goods_sku_list"] = $goods_sku_list;
        return $res;
    }

    /**
     * 组合套餐
     */
    public function combination_packagesSession($order_sku, $combo_id, $combo_buy_num)
    {
        //$order_sku = isset($_SESSION["order_sku"]) ? $_SESSION["order_sku"] : "";
        if (empty($order_sku)) {
            return array(
                'code' => -50,
                'message' => '无法获取所选商品信息',
            );
        }

        $order_sku_array = explode(",", $order_sku);
        foreach ($order_sku_array as $k => $v) {

            $cart_list      = array();
            $order_sku_list = explode(":", $v);
            $sku_id         = $order_sku_list[0];
            $num            = $order_sku_list[1];

            // 获取商品sku信息
            $goods_sku = new \data\model\NsGoodsSkuModel();
            $sku_info  = $goods_sku->getInfo([
                'sku_id' => $sku_id
            ], '*');

            // 查询当前商品是否有SKU主图
            $order_goods_service = new OrderGoods();
            $picture             = $order_goods_service->getSkuPictureBySkuId($sku_info);

            // 清除非法错误数据
            $cart = new NsCartModel();
            if (empty($sku_info)) {
                $cart->destroy([
                    'buyer_id' => $this->uid,
                    'sku_id' => $sku_id
                ]);
                return array(
                    'code' => -50,
                    'message' => '无法获取所选商品信息',
                );
            }

            $goods      = new NsGoodsModel();
            $goods_info = $goods->getInfo([
                'goods_id' => $sku_info["goods_id"]
            ], 'max_buy,state,point_exchange_type,point_exchange,picture,goods_id,goods_name');

            $cart_list["stock"]    = $sku_info['stock']; // 库存
            $cart_list["sku_name"] = $sku_info["sku_name"];

            $goods_preference                 = new GoodsPreference();
            $member_price                     = $goods_preference->getGoodsSkuMemberPrice($sku_info['sku_id'], $this->uid);
            $cart_list["price"]               = $member_price < $sku_info['price'] ? $member_price : $sku_info['price'];
            $cart_list["goods_id"]            = $goods_info["goods_id"];
            $cart_list["goods_name"]          = $goods_info["goods_name"];
            $cart_list["max_buy"]             = $goods_info['max_buy']; // 限购数量
            $cart_list['point_exchange_type'] = $goods_info['point_exchange_type']; // 积分兑换类型 0 非积分兑换 1 只能积分兑换
            $cart_list['point_exchange']      = $goods_info['point_exchange']; // 积分兑换
            if ($goods_info['state'] != 1) {
                $message = $goods_info['state'] == 0 ? '商品已下架' : '商品违规禁售';
                return array(
                    'code' => -50,
                    'message' => $message
                );
            }
            $cart_list["num"] = $num;
            // 如果购买的数量超过限购，则取限购数量
            if ($goods_info['max_buy'] != 0 && $goods_info['max_buy'] < $num) {
                $num = $goods_info['max_buy'];
            }
            // 如果购买的数量超过库存，则取库存数量
            if ($sku_info['stock'] < $num) {
                $num = $sku_info['stock'];
            }
            // 获取图片信息，如果该商品有SKU主图，就用。否则用商品主图
            $album_picture_model       = new AlbumPictureModel();
            $picture_info              = $album_picture_model->get($picture == 0 ? $goods_info['picture'] : $picture);
            $cart_list['picture_info'] = $picture_info;

            if (count($cart_list) == 0) {
                return array(
                    'code' => -50,
                    'message' => '无法获取所选商品信息',
                );
            }
            $list[]         = $cart_list;
            $goods_sku_list = $sku_id . ":" . $num; // 商品skuid集合
            $res["list"]    = $list;
        }
        $res["goods_sku_list"] = $order_sku;
        $res["combo_id"]       = $combo_id;
        $res["combo_buy_num"]  = $combo_buy_num;
        return $res;
    }

    /**
     * 创建提醒模板
     */
    public function orderWarnTemplateCreat(Request $request)
    {
        $res['openid']       = $request->param('openid');
        $res['formid']       = $request->param('formid');
        $res['price']        = $request->param('price');
        $res['out_trade_no'] = $request->param('out_trade_no');
        if ($request->param('send_type') == '8') {
            $this->pushPayVipTemplate($res);
        } else {
            $this->pushPayOrderTemplate($res);
            $exist = Db::name('ns_template_push')->where([
                'warn_type' => $request->param('warn_type'),
                'out_trade_no' => $request->param('out_trade_no'),
            ])->find();

            if ($exist) return json(['code' => 0, 'msg' => 'success']);

            Db::name('ns_template_push')->insert([
                'open_id' => $request->param('openid'),
                'form_id' => $request->param('formid'),
                'warn_type' => $request->param('warn_type'),
                'out_trade_no' => $request->param('out_trade_no'),
                'is_send' => 0,
                'created' => date('Y-m-d H:i:s', time())
            ]);

            return json(['code' => 0, 'msg' => 'success']);
        }

    }

    /**
     * 创建订单
     */
    public function orderCreate()
    {
        $title = "创建订单";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $order = new OrderService();
        // 获取支付编号
        $out_trade_no        = $order->getOrderTradeNo();
        $use_coupon          = request()->post('use_coupon', 0); // 优惠券
        $integral            = request()->post('integral', 0); // 积分
        $goods_sku_list      = request()->post('goods_sku_list', ''); // 商品列表
        $leavemessage        = request()->post('leavemessage', ''); // 留言
        $user_money          = request()->post("account_balance", 0); // 使用余额
        $pay_type            = request()->post("pay_type", 1); // 支付方式
        $buyer_invoice       = request()->post("buyer_invoice", ""); // 发票
        $pick_up_id          = request()->post("pick_up_id", 0); // 自提点
        $shipping_company_id = request()->post("shipping_company_id", 0); // 物流公司
        $shipping_time       = request()->post("shipping_time", 0); // 配送时间
        $tx_type             = request()->post("tx_type", 1); // 交易类型（1：大贸，2：跨境）
        $is_inside           = request()->post("is_inside", 0); // 是否内购（0：否，1：是）
        $card_name           = request()->post("card_name", ''); // 身份证姓名
        $card_no             = request()->post("card_no", ''); // 身份证号码
        $shipping_type       = 1; // 配送方式，1：物流，2：自提
        // $is_use_card = request()->post("is_use_card", 0); // 是否使用心意券（0：否；1：是）
        // $card_id = request()->post("card_id", ''); // 心意券id
        // $card_token = request()->post("card_token", ''); // 心意券token
        // $card_money = request()->post("card_money", 0); // 心意券价值
        $from_type   = request()->post("from_type", 0); // 0:购物车或直接购买,1:清单分享
        $count_money = request()->post("count_money", 0); // 订单总价
//        $distributor_type = request()->post("distributor_type", 0); // 分销者类型（0：默认；1：高级分销；2：门店极选师(有)；3：门店极选师(无)；4：签约极选师）

        $uid      = request()->post("uid", 0); // 分销来源:uid
        $store_id = request()->post("store_id", 0); // 来源网点:门店id
        $traffic_acquisition_source = request()->post("traffic_acquisition_source", ''); // 引流来源
        if($traffic_acquisition_source == 'undefined'){
            $traffic_acquisition_source = '';
        }

        $memberModel = new NsMemberModel();
        $member_info = $memberModel->getInfo(['uid' => $this->uid], 'source_distribution,distributor_type,source_branch,inviter');
        if ($member_info['distributor_type'] > 0) {   //当自己是分销者的情况下
            $memberDistributionDedail = ['distributor_type' => $member_info['distributor_type'], 'uid' => $this->uid, 'source_distribution' => $member_info['source_distribution'], 'source_branch' => $member_info['source_branch'], 'inviter' => $member_info['inviter']];
        } else {
            if ($member_info['source_distribution'] > 0) {    //当已经绑定分销来源的情况下
                $memberDistributionDedail = $memberModel->getInfo(['uid' => $member_info['source_distribution']], 'distributor_type,uid,source_distribution,source_branch,inviter');
            } else {
                if ($uid > 0) {
                    $memberDistributionDedail = $memberModel->getInfo(['uid' => $uid], 'distributor_type,uid,source_distribution,source_branch,inviter');
                } else {
                    $memberDistributionDedail = ['distributor_type' => 0, 'uid' => 0, 'source_distribution' => 0, 'source_branch' => $store_id, 'inviter' => 0];
                }
            }

        }
        $distributor_type           = $memberDistributionDedail['distributor_type'];
        $source_distribution        = $memberDistributionDedail['uid'];
        $parent_source_distribution = $memberDistributionDedail['source_distribution'];
        $source_branch              = $memberDistributionDedail['source_branch'];
        $inviter                    = $memberDistributionDedail['inviter'];

        if ($pick_up_id != 0) {
            $shipping_type = 2;
        }
        if ($tx_type != 2) {
            $tx_type = 1;
        }

        $member  = new Member();
        $address = $member->getDefaultExpressAddress();

        //内购订单统一公司地址
        if($is_inside == 1){
            $address['province'] = 9;
            $address['city'] = 73;
            $address['district'] =722;
            $address['address'] = '来福士广场T1-35楼';
            $address['zip_code'] = '';
        }
        $coin    = 0; // 购物币

        // 查询商品限购
        $purchase_restriction = $order->getGoodsPurchaseRestrictionForOrder($goods_sku_list);
        if (!empty($purchase_restriction)) {

            return $this->outMessage($title, '', "-50", $purchase_restriction);
        } else {

            $order_id = $order->orderCreate('1', $out_trade_no, $pay_type, $shipping_type, '1', 1, $leavemessage, $buyer_invoice, $shipping_time, $address['mobile'], $address['province'], $address['city'], $address['district'], $address['address'], $address['zip_code'], $address['consigner'], $integral, $use_coupon, 0, $goods_sku_list, $user_money, $pick_up_id, $shipping_company_id, $tx_type, $is_inside, $card_name, $card_no, $source_branch, $source_distribution, $parent_source_distribution, $inviter, $from_type, $distributor_type, $traffic_acquisition_source, $count_money, $coin, $address["phone"]);

            // 订单创建标识，表示当前生成的订单详情已经创建好了。用途：订单创建成功后，返回上一个界面的路径是当前创建订单的详情，而不是首页
            if ($order_id > 0) {
                $order->deleteCart($goods_sku_list, $this->uid);
                $data = array(
                    'out_trade_no' => $out_trade_no
                );
                return $this->outMessage($title, $data);
            } else {
                $data = array(
                    'order_id' => $order_id
                );
                return $this->outMessage($title, $data, "-10", "商品订单生成失败!");
            }
        }
    }

    //付费会员领取免费商品
    public function vipGetGoodsOrder()
    {
        $title = "付费会员领取免费商品";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }

        //判断是否可以领取
        $memberModel = new NsMemberModel();
        $member_info = $memberModel->getInfo(['uid' => $this->uid], 'is_vip, vip_goods');
        if ($member_info['is_vip'] != 1) {
            return $this->outMessage($title, "", '-50', "不是付费会员");
        }
        if ($member_info['vip_goods'] != 0) {
            return $this->outMessage($title, "", '-50', "赠品已领取");
        }

        $order         = new OrderService();
        $out_trade_no  = $order->getOrderTradeNo();// 获取支付编号
        $goods_id      = request()->post('goods_id', ''); // 商品id
        $source_branch = request()->post("store_id", 0); // 门店id
        $memberModel   = new NsMemberModel();
        $member_info   = $memberModel->getInfo(['uid' => $this->uid], 'source_branch');
        if ($source_branch == 0) {
            $source_branch = $member_info['source_branch'];
        }
        $member  = new Member();
        $address = $member->getDefaultExpressAddress();

        $order_id = $order->vipGetGoodsOrder('1', $out_trade_no, $address['mobile'], $address['province'], $address['city'], $address['district'], $address['address'], $address['zip_code'], $address['consigner'], $goods_id, $source_branch);
        // 订单创建标识，表示当前生成的订单详情已经创建好了。用途：订单创建成功后，返回上一个界面的路径是当前创建订单的详情，而不是首页
        if ($order_id > 0) {
            $data = array(
                'out_trade_no' => $out_trade_no
            );
            return $this->outMessage($title, $data);
        } else {
            $data = array(
                'order_id' => $order_id
            );
            return $this->outMessage($title, $data, "-10", "订单生成失败!");
        }
    }

    //付费会员领取免费礼品
    public function vipGetGiftOrder()
    {
        $title = "付费会员领取免费礼品";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }

        //判断是否可以领取
        $memberModel = new NsMemberModel();
        $member_info = $memberModel->getInfo(['uid' => $this->uid], 'is_vip, vip_gift');
        if ($member_info['is_vip'] != 1) {
            return $this->outMessage($title, "", '-50', "不是付费会员");
        }
        if ($member_info['vip_gift'] != 0) {
            return $this->outMessage($title, "", '-50', "礼品已领取");
        }

        $order         = new OrderService();
        $out_trade_no  = $order->getOrderTradeNo();// 获取支付编号
        $goods_id      = request()->post('goods_id', ''); // 商品id
        $source_branch = request()->post("store_id", 0); // 门店id
        $memberModel   = new NsMemberModel();
        $member_info   = $memberModel->getInfo(['uid' => $this->uid], 'source_branch');
        if ($source_branch == 0) {
            $source_branch = $member_info['source_branch'];
        }

        $result = $order->vipGetGiftOrder('4', $out_trade_no, $goods_id, $source_branch);
        // 订单创建标识，表示当前生成的订单详情已经创建好了。用途：订单创建成功后，返回上一个界面的路径是当前创建订单的详情，而不是首页
        if ($result > 0) {
            $data = array(
                'order_no' => $result
            );
            return $this->outMessage($title, $data);
        } else {
            $data = array(
                'order_id' => $result
            );
            return $this->outMessage($title, $data, "-10", "订单生成失败!");
        }
    }

    /**
     * 创建订单（礼品）
     */
    public function giftOrderCreate()
    {
        $title = "创建礼品订单";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }

        $order = new OrderService();
        // 获取支付编号
        $out_trade_no        = $order->getOrderTradeNo();
        $use_coupon          = request()->post('use_coupon', 0); // 优惠券
        $integral            = request()->post('integral', 0); // 积分
        $goods_sku_list      = request()->post('goods_sku_list', ''); // 商品列表
        $leavemessage        = request()->post('leavemessage', ''); // 留言
        $user_money          = 0; // 订单预存款支付金额
        $pay_type            = request()->post("pay_type", 1); // 支付方式
        $buyer_invoice       = ""; // 发票
        $pick_up_id          = 0; // 自提点
        $shipping_company_id = 0; // 物流公司
        $shipping_time       = 0; // 配送时间
        $tx_type             = request()->post("tx_type", 1); // 交易类型（1：大贸，2：跨境）
        $card_name           = request()->post("card_name", ''); // 身份证姓名
        $card_no             = request()->post("card_no", ''); // 身份证号码
        $shipping_type       = 1; // 配送方式，1：物流，2：自提
        $source_branch       = request()->post("store_id", 0); // 门店id
        $memberModel         = new NsMemberModel();
        $member_info         = $memberModel->getInfo(['uid' => $this->uid], 'source_branch');
        if ($source_branch == 0) {
            $source_branch = $member_info['source_branch'];
        }

        if ($tx_type != 2) {
            $tx_type = 1;
        }

        $coin           = 0; // 购物币
        $order_type     = 4; //订单类型
        $order_from     = 1; //订单来源
        $platform_money = 0;  //平台余额支付

        // 查询商品限购
        $purchase_restriction = $order->getGoodsPurchaseRestrictionForOrder($goods_sku_list);
        if (!empty($purchase_restriction)) {

            return $this->outMessage($title, '', "-50", $purchase_restriction);
        } else {

            $order_id = $order->giftOrderCreate($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, 1, $leavemessage, $buyer_invoice, $shipping_time, $integral, $use_coupon, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $tx_type, $card_name, $card_no, $source_branch, $coin);
            // 订单创建标识，表示当前生成的订单详情已经创建好了。用途：订单创建成功后，返回上一个界面的路径是当前创建订单的详情，而不是首页
            if ($order_id > 0) {
                $order->deleteBox($goods_sku_list, $this->uid);
                $data = array(
                    'out_trade_no' => $out_trade_no
                );
                return $this->outMessage($title, $data);
            } else {
                $data = array(
                    'order_id' => $order_id
                );
                return $this->outMessage($title, $data, "-10", "订单生成失败!");
            }
        }
    }

    /**
     * 创建订单（会员）
     */
    public function vipOrderCreate()
    {
        $title = '创建会员订单';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }

        //判断是否是会员
        $memberModel = new NsMemberModel();
        $member_info = $memberModel->getInfo(['uid' => $this->uid], 'is_vip');
        if ($member_info['is_vip'] == 1) {
            return $this->outMessage($title, "", '-50', "已经是会员");
        }

        $order = new OrderService();
        // 获取支付编号
        $out_trade_no = $order->getOrderTradeNo();
//        $goods_sku_list = request()->post('goods_sku_list', ''); // 商品列表
        $goods_id  = request()->post('goods_id', 0); // 商品id
        $card_name = request()->post('card_name', ''); // 真实姓名
        $sex       = request()->post('sex', 0); // 性别
        $user_tel  = request()->post("user_tel", ""); // 电话号码
        $birthday  = request()->post("birthday", 0); // 生日
        if ($birthday) {
            $birthday = strtotime($birthday);
        }
        $source_branch = request()->post("store_id", 0); // 门店id
        $memberModel   = new NsMemberModel();
        $member_info   = $memberModel->getInfo(['uid' => $this->uid], 'source_branch');
        if ($source_branch == 0) {
            $source_branch = $member_info['source_branch'];
        }

        $order_id = $order->orderCreateVip('5', $out_trade_no, $goods_id, $card_name, $sex, $user_tel, $birthday, $source_branch);
        if ($order_id > 0) {
            $data = array(
                'out_trade_no' => $out_trade_no
            );
            return $this->outMessage($title, $data);
        } else {
            $data = array(
                'order_id' => $order_id
            );
            return $this->outMessage($title, $data);
        }
    }

    /**
     * 创建订单（虚拟商品）
     */
    public function virtualOrderCreate()
    {
        $title = '创建虚拟商品订单';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        if ($this->getIsOpenVirtualGoodsConfig() == 0) {
            return $this->outMessage($title, '', -10, '未开启虚拟商品功能');
        }
        $order = new OrderService();
        // 获取支付编号
        $out_trade_no       = $order->getOrderTradeNo();
        $use_coupon         = request()->post('use_coupon', 0); // 优惠券
        $integral           = request()->post('integral', 0); // 积分
        $goods_sku_list     = request()->post('goods_sku_list', ''); // 商品列表
        $leavemessage       = request()->post('leavemessage', ''); // 留言
        $user_money         = request()->post("account_balance", 0); // 使用余额
        $pay_type           = request()->post("pay_type", 1); // 支付方式
        $buyer_invoice      = request()->post("buyer_invoice", ""); // 发票
        $user_telephone     = request()->post("user_telephone", ""); // 电话号码
        $express_company_id = 0; // 物流公司
        $shipping_type      = 1; // 配送方式，1：物流，2：自提
        $pick_up_id         = 0;
        $member             = new Member();
        $address            = $member->getDefaultExpressAddress();
        $shipping_time      = date("Y-m-d H:i:s", time());

        // 查询商品限购
        $purchase_restriction = $order->getGoodsPurchaseRestrictionForOrder($goods_sku_list);
        if (!empty($purchase_restriction)) {
            $res = array(
                "code" => 0,
                "message" => $purchase_restriction
            );
            return $this->outMessage($title, '', -50, $purchase_restriction);
        } else {
            $order_id = $order->orderCreateVirtual('2', $out_trade_no, $pay_type, $shipping_type, '1', 1, $leavemessage, $buyer_invoice, $shipping_time, $integral, $use_coupon, 0, $goods_sku_list, $user_money, $pick_up_id, $express_company_id, $user_telephone);
            if ($order_id > 0) {
                $order->deleteCart($goods_sku_list, $this->uid);
                $data = array(
                    'out_trade_no' => $out_trade_no
                );
                return $this->outMessage($title, $data);
            } else {
                $data = array(
                    'order_id' => $order_id
                );
                return $this->outMessage($title, $data);
            }
        }
    }

    /**
     * 创建订单（组合商品）
     */
    public function comboPackageOrderCreate()
    {
        $title = '创建组合商品订单';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $order = new OrderService();
        // 获取支付编号
        $out_trade_no       = $order->getOrderTradeNo();
        $use_coupon         = request()->post('use_coupon', 0); // 优惠券
        $integral           = request()->post('integral', 0); // 积分
        $goods_sku_list     = request()->post('goods_sku_list', ''); // 商品列表
        $leavemessage       = request()->post('leavemessage', ''); // 留言
        $user_money         = request()->post("account_balance", 0); // 使用余额
        $pay_type           = request()->post("pay_type", 1); // 支付方式
        $buyer_invoice      = request()->post("buyer_invoice", ""); // 发票
        $pick_up_id         = request()->post("pick_up_id", 0); // 自提点
        $shipping_type      = 1; // 配送方式，1：物流，2：自提
        $shipping_time      = request()->post("shipping_time", 0); // 配送时间
        $express_company_id = request()->post("express_company_id", 0); // 物流公司
        $combo_package_id   = request()->post("combo_package_id", 0); // 组合套餐id
        $buy_num            = request()->post("buy_num", 1); // 购买套数

        if ($pick_up_id != 0) {
            $shipping_type = 2;
        }
        $member  = new Member();
        $address = $member->getDefaultExpressAddress();
        $coin    = 0; // 购物币

        // 查询商品限购
        $purchase_restriction = $order->getGoodsPurchaseRestrictionForOrder($goods_sku_list);
        if (!empty($purchase_restriction)) {
            $res = array(
                "code" => 0,
                "message" => $purchase_restriction
            );
            return $this->outMessage($title, '', -50, $purchase_restriction);
        } else {
            $order_id = $order->orderCreateComboPackage("3", $out_trade_no, $pay_type, $shipping_type, "1", 1, $leavemessage, $buyer_invoice, $shipping_time, $address['mobile'], $address['province'], $address['city'], $address['district'], $address['address'], $address['zip_code'], $address['consigner'], $integral, 0, $goods_sku_list, $user_money, $pick_up_id, $express_company_id, $coin, $address["phone"], $combo_package_id, $buy_num);
            if ($order_id > 0) {
                $order->deleteCart($goods_sku_list, $this->uid);
                $data = array(
                    'out_trade_no' => $out_trade_no
                );
                return $this->outMessage($title, $data);
            } else {
                $data = array(
                    'order_id' => $order_id
                );
                return $this->outMessage($title, $data);
            }
        }
    }

    //极选师分润统计
    public function getKolFractionSum()
    {
        $title = "极选师分润统计";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取登录信息");
        }

        $order = new OrderService();
        $list  = $order->getKolFractionSum();
        return $this->outMessage($title, $list);
    }

    //极选师分润统计
    public function getKolAchievementStatistics()
    {
        $title = "极选师分润统计";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取登录信息");
        }

        $order = new OrderService();
        $list  = $order->getKolAchievementStatistics();
        return $this->outMessage($title, $list);
    }

    /**
     * 极选师订单统计
     */
    public function getKolOrderList()
    {
        $title = "极选师订单统计";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取登录信息");
        }
        $page_index = request()->post("page", 1);
        $start_date = request()->post('start_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('start_date'));
        $end_date   = request()->post('end_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('end_date')) + 86400;

        $condition['source_distribution'] = $this->uid;
        if ($start_date != 0 && $end_date != 0) {
            $condition["no.create_time"] = [
                [
                    ">",
                    $start_date
                ],
                [
                    "<",
                    $end_date
                ]
            ];
        } elseif ($start_date != 0 && $end_date == 0) {
            $condition["no.create_time"] = [
                [
                    ">",
                    $start_date
                ]
            ];
        } elseif ($start_date == 0 && $end_date != 0) {
            $condition["no.create_time"] = [
                [
                    "<",
                    $end_date
                ]
            ];
        }
        $condition['distributor_type'] = ['>', 1];
        $condition['pay_status']       = 2;
        if (!empty($this->shop_id)) {
            $condition['shop_id'] = $this->shop_id;
        }
        $condition['is_deleted'] = 0; // 未删除订单
        $order                   = new OrderService();
        $order_list              = $order->getKolOrderList($page_index, PAGESIZE, $condition, 'create_time desc');
        return $this->outMessage($title, $order_list);
    }

    /**
     * 获取当前会员的订单列表
     */
    public function getOrderList()
    {
        $title = "获取会员订单列表";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $page_index = request()->post("page", 1);
        $status     = request()->post('status', '');

        $condition['buyer_id']   = $this->uid;
        $condition['is_deleted'] = 0;
        if (!empty($this->shop_id)) {
            $condition['shop_id'] = $this->shop_id;
        }

        if ($status != 'all') {
            switch ($status) {
                case 0:
                    $condition['order_status'] = 0;
                    break;
                case 1:
                    $condition['order_status'] = 1;
                    break;
                case 2:
                    $condition['order_status'] = 2;
                    break;
                case 3:
                    $condition['order_status'] = array(
                        'in',
                        '3,4'
                    );
                    break;
                case 4:
                    $condition['order_status'] = array(
                        'in',
                        [
                            -1,
                            -2
                        ]
                    );
                    break;
                case 5:
                    $condition['order_status'] = array(
                        'in',
                        '3,4'
                    );
                    $condition['is_evaluate']  = array(
                        'in',
                        '0,1'
                    );
                    break;
                default:
                    break;
            }
        }
//        $condition['order_type'] = 1;
        $condition['order_type'] = array(
            'in',
            '1,5'
        );

        // 还要考虑状态逻辑
        $order      = new OrderService();
        $order_list = $order->getOrderList($page_index, PAGESIZE, $condition, 'create_time desc');
        return $this->outMessage($title, $order_list);
    }

    /**
     * 获取当前会员的礼品订单列表（我送出的）
     */
    public function getGiftOrderList()
    {
        $title = "我送出的礼品订单列表";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $page_index = request()->post("page", 1);
        $status     = request()->post('status', '');

        $condition['buyer_id']   = $this->uid;
        $condition['is_deleted'] = 0;
        if (!empty($this->shop_id)) {
            $condition['shop_id'] = $this->shop_id;
        }
        $condition['order_status'] = array(
            'in',
            '0,1,2,3,4,5,-1,-2,11'
        );
        $condition['order_type']   = 4;

        // 还要考虑状态逻辑
        $order      = new OrderService();
        $order_list = $order->getGiftOrderList($page_index, PAGESIZE, $condition, 'create_time desc');
        return $this->outMessage($title, $order_list);
    }

    /**
     * 获取当前会员的礼品订单列表（我领取的）
     */
    public function getGiftOrderGetList()
    {
        $title = "我领取的礼品订单列表";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $page_index                      = request()->post("page", 1);
        $condition['nggg.uid']           = $this->uid;
        $condition['nggg.action_status'] = 2;
        if (!empty($this->shop_id)) {
            $condition['no.shop_id'] = $this->shop_id;
        }
        $condition['no.order_type'] = 4;
        // 还要考虑状态逻辑
        $order      = new OrderService();
        $order_list = $order->getGiftOrderGetList($page_index, PAGESIZE, $condition, 'create_time desc');
        return $this->outMessage($title, $order_list);
    }


    /**
     * 获取当前会员的虚拟订单列表
     */
    public function myVirtualOrderList()
    {
        $title = "获取会员虚拟订单列表";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }

        if ($this->getIsOpenVirtualGoodsConfig() == 0) {
            return $this->outMessage($title, "", '-50', "未开启虚拟商品功能");
        }

        $status                  = request()->post('status', 'all');
        $condition['buyer_id']   = $this->uid;
        $condition['is_deleted'] = 0;
        $condition['order_type'] = 2;
        if ($this->instance_id != null) {
            $condition['shop_id'] = $this->instance_id;
        }

        if ($status != 'all') {
            switch ($status) {
                case 0:
                    $condition['order_status'] = 0;
                    break;
                case 5:
                    $condition['order_status'] = array(
                        'in',
                        '3,4'
                    );
                    $condition['is_evaluate']  = array(
                        'in',
                        '0,1'
                    );
                    break;
            }
        }
        $page_index = request()->post("page", 1);
        // 还要考虑状态逻辑
        $order      = new OrderService();
        $order_list = $order->getOrderList($page_index, PAGESIZE, $condition, 'create_time desc');
        return $this->outMessage($title, $order_list);
    }

    /**
     * 订单详情
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function orderDetail()
    {
        $title = "获取订单详情";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $order_id      = request()->post('order_id', 0);
        $order_service = new OrderService();
        $detail        = $order_service->getOrderDetail($order_id);
        if (empty($detail)) {
            return $this->outMessage($title, "", '-50', "无法获取订单信息");
        }
        return $this->outMessage($title, $detail);

    }

//    获取礼品订单详情
    public function giftOrderDetail()
    {
        $title = "获取礼品订单详情";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $order_no        = request()->post('order_no', 0);
        $order_service   = new OrderOrderService();
        $detail          = $order_service->getGiftDetail($order_no);
        $detail["img01"] = "https://static.bonnieclyde.cn/gift_01.jpg";
        $detail["img03"] = "https://static.bonnieclyde.cn/gift_03.jpg";
        if (empty($detail)) {
            return $this->outMessage($title, "", '-50', "无法获取订单信息");
        }
        return $this->outMessage($title, $detail);
    }


    /**
     * 赠送礼品
     */
    public function giftGive()
    {
        $title = '赠送礼品';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $order_id      = request()->post('order_id', 0); // 订单id
        $order_service = new OrderOrderService();
        $action_id     = $order_service->addGiftGaveGet($order_id, $this->uid, 1);
        return $this->outMessage($title, $action_id);
    }

    /**
     * 确定领取礼品
     */
    public function giftGet()
    {
        $title = "确定领取礼品";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $order_id    = request()->post('order_id', 0); // 订单id
        $order_model = new NsOrderModel();
        $order_info  = $order_model->getInfo(['order_id' => $order_id], 'order_status');
        if (empty($order_info)) {
            return $this->outMessage($title, "", '-50', "订单id错误");
        } else if ($order_info['order_status'] == 11) {
            $order_service = new OrderService();
            $res           = $order_service->giftGet($order_id, 2);
            $ret           = order_refund_push(1, $order_id);
            return $this->outMessage($title, $res, "0", "礼品领取成功");
        } else {
            return $this->outMessage($title, "", '-50', "礼品可能已被领取");
        }

    }

    /**
     * 礼品领取页面
     */
    public function giftGetDetail()
    {
        $title       = '礼品领取页面';
        $order_id    = request()->post('order_id', 0); // 订单id
        $condition   = array(
            'no.order_id' => $order_id
        );
        $order_model = new NsOrderModel();
        $list        = $order_model->getOrderBuyer($condition);
        if ($list) {
            $list[0]['img01'] = "https://static.bonnieclyde.cn/gift_01.jpg";
            $list[0]['img03'] = "https://static.bonnieclyde.cn/gift_03.jpg";
            return $this->outMessage($title, $list[0]);
        } else {
            return $this->outMessage($title, '', "-50", "礼品领取页面请求失败");
        }

    }

    /**
     * 物流详情
     */
    public function orderExpress()
    {
        $title    = "订单物流信息";
        $order_id = request()->post('orderId', 0);
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        if (empty($order_id)) {
            return $this->outMessage($title, "", '-50', "没有获取到订单信息");
        }
        $order_service = new OrderService();
        $detail        = $order_service->getOrderDetail($order_id);
        if (empty($detail)) {
            return $this->outMessage($title, "", '-50', "没有获取到订单信息");
        }
        // 获取物流跟踪信息
        $order_service = new OrderService();
        return $this->outMessage($title, $detail);
    }

    /**
     * 查询包裹物流信息
     * 2017年6月24日 10:42:34 王永杰
     */
    public function getOrderGoodsExpressMessage()
    {
        $title      = "物流包裹信息";
        $express_id = request()->post("express_id", 0); // 物流包裹id
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $res = -1;
        if ($express_id) {
            $order_service = new OrderService();
            $res           = $order_service->getOrderGoodsExpressMessage($express_id);
            $res           = array_reverse($res);
        }
        return $this->outMessage($title, $res);
    }

    /**
     * 订单项退款详情
     */
    public function refundDetail()
    {
        $title          = "退款详情";
        $order_goods_id = request()->post('order_goods_id', 0);
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        if (empty($order_goods_id)) {
            return $this->outMessage($title, "", '-50', "无法获取订单信息");
        }
        $order_service = new OrderService();
        $detail        = $order_service->getOrderGoodsRefundInfo($order_goods_id);
        $refund_money  = $order_service->orderGoodsRefundMoney($order_goods_id);

        // 余额退款
        $order_goods_service = new OrderGoods();
        $refund_balance      = $order_goods_service->orderGoodsRefundBalance($order_goods_id);
        // 查询店铺默认物流地址
        $express = new Express();
        $address = $express->getDefaultShopExpressAddress($this->instance_id);
        // 查询商家地址
        $shop_info = $order_service->getShopReturnSet($this->instance_id);
        $data      = array(
            'refund_detail' => $detail,
            'refund_money' => $refund_money,
            'refund_balance' => $refund_balance,
            'shop_espress_address' => $address,
            'shop_address' => $shop_info
        );
        return $this->outMessage($title, $data);
    }

    public function refundDetails()
    {
        $title          = "退款详情";
        $order_goods_id = request()->post('order_goods_id', 0);
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        if (empty($order_goods_id)) {
            return $this->outMessage($title, "", '-50', "无法获取订单信息");
        }

        //查询订单项退款
        $order_service = new OrderService();
        $detail        = $order_service->getOrderGoodsRefundDetails($order_goods_id);

        //查询店铺默认物流地址
        $express = new Express();
        $address = $express->getDefaultShopExpressAddress($this->instance_id);

        //查询商家地址
        $shop_info = $order_service->getShopReturnSet($this->instance_id);
        $data      = array(
            'refund_detail' => $detail,
            'shop_espress_address' => $address,
            'shop_address' => $shop_info
        );
        return $this->outMessage($title, $data);
    }

    /**
     * 申请退款
     */
    public function orderGoodsRefundAskfor()
    {
        $title = "申请退款";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $order_id = request()->post('order_id', 0);
        if (empty($order_id)) {
            return $this->outMessage($title, "", '-50', "无法获取订单信息");
        }
        $order_goods_id       = request()->post('order_goods_id', 0);
        $refund_type          = request()->post('refund_type', 1);
        $refund_require_money = request()->post('refund_require_money', 0);
        $refund_reason        = request()->post('refund_reason', '');
        $order_service        = new OrderService();
        $retval               = $order_service->orderGoodsRefundAskfor($order_id, $order_goods_id, $refund_type, $refund_require_money, $refund_reason);
        return $this->outMessage($title, $retval);
    }

    public function orderGoodsRefundAskfors()
    {
        $title = "申请退款";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $order_id = request()->post('order_id', 0);
        if (empty($order_id)) {
            return $this->outMessage($title, "", '-50', "无法获取订单信息");
        }
        $order_goods_id       = request()->post('order_goods_id', 0);
        $refund_type          = request()->post('refund_type', 1);
        $refund_require_money = request()->post('refund_require_money', 0);
        $refund_require_num = request()->post('refund_require_num', 0);
        $refund_reason        = request()->post('refund_reason', '');
        $order_service        = new OrderService();
        $retval               = $order_service->orderGoodsRefundAskfors($order_id, $order_goods_id, $refund_type, $refund_require_money, $refund_require_num, $refund_reason);
        return $this->outMessage($title, $retval);
    }

    /**
     * 买家撤销退款
     */
    public function orderGoodsCancels()
    {
        $title = "撤销退款";
        $order_id       = request()->post('order_id', '');
        $order_goods_id = request()->post('order_goods_id', '');
        $refund_records_id = request()->post('refund_records_id', '');
        if (empty($order_id) || empty($order_goods_id || empty($refund_records_id))) {
            $this->error('缺少必需参数');
        }
        $order_service = new OrderService();
        $retval        = $order_service->orderGoodsCancels($order_id, $order_goods_id, $refund_records_id);
        return $this->outMessage($title, $retval);
    }

    /**
     * 买家退货
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function orderGoodsRefundExpress()
    {

        $title = "买家退货";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $order_id = request()->post('order_id', 0);
        if (empty($order_id)) {
            return $this->outMessage($title, "", '-50', "无法获取订单");
        }
        $order_goods_id         = request()->post('order_goods_id', 0);
        $refund_express_company = request()->post('refund_express_company', '');
        $refund_shipping_no     = request()->post('refund_shipping_no', 0);
        $refund_reason          = request()->post('refund_reason', '');
        $order_service          = new OrderService();
        $retval                 = $order_service->orderGoodsReturnGoods($order_id, $order_goods_id, $refund_express_company, $refund_shipping_no);
        return $this->outMessage($title, $retval);
    }

    public function orderGoodsRefundExpresses()
    {

        $title = "买家退货";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $order_id = request()->post('order_id', 0);
        if (empty($order_id)) {
            return $this->outMessage($title, "", '-50', "无法获取订单");
        }
        $order_goods_id         = request()->post('order_goods_id', 0);
        $refund_records_id         = request()->post('refund_records_id', 0);
        $refund_express_company = request()->post('refund_express_company', '');
        $refund_shipping_no     = request()->post('refund_shipping_no', 0);
        $refund_reason          = request()->post('refund_reason', '');
        $order_service          = new OrderService();
        $retval                 = $order_service->orderGoodsReturnGoodses($order_id, $order_goods_id, $refund_records_id, $refund_express_company, $refund_shipping_no);
        return $this->outMessage($title, $retval);
    }

    /**
     * 交易关闭
     */
    public function orderClose()
    {
        $title = "关闭订单";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $order_service = new OrderService();
        $order_id      = request()->post('order_id', '');
        if (empty($order_id)) {
            return $this->outMessage($title, "", '-50', "无法获取订单");
        }
        $res = $order_service->orderClose($order_id);
        return $this->outMessage($title, $res);
    }

    /**
     * 收货
     */
    public function orderTakeDelivery()
    {
        $title = "订单收货";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $order_service = new OrderService();
        $order_id      = request()->post('order_id', '');
        if (empty($order_id)) {
            return $this->outMessage($title, "", '-50', "无法获取订单");
        }
        $res = $order_service->OrderTakeDelivery($order_id);
        return $this->outMessage($title, $res);
    }

    /**
     * 删除订单
     */
    public function deleteOrder()
    {
        $title = "删除订单";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }

        $order_service = new OrderService();
        $order_id      = request()->post("order_id", "");
        if (empty($order_id)) {
            return $this->outMessage($title, "", '-50', "无法获取订单信息");
        }
        $res = $order_service->deleteOrder($order_id, 2, $this->uid);
        return $this->outMessage($title, $res);

    }

    //修改订单地址
    public function modifyOrderAddress()
    {
        $title = "修改订单地址";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }

        $order_no = request()->post("order_no", "");
        if (empty($order_no)) {
            return $this->outMessage($title, "", '-50', "无法获取订单信息");
        }

        $order_service = new OrderService();
        $res = $order_service->modifyOrderAddress($order_no);
        if($res){
            return $this->outMessage($title, $res);
        }else{
            return $this->outMessage($title, "", '-100', "商品已出库,不能修改地址");
        }
    }

    /**
     * 评价订单详情
     */
    public function reviewCommodity()
    {
        $title = '评价订单详情';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $order_id = request()->post('orderId', '');
        // 判断该订单是否是属于该用户的
        $order_service              = new OrderService();
        $condition['order_id']      = $order_id;
        $condition['buyer_id']      = $this->uid;
        $condition['review_status'] = 0;
        $condition['order_status']  = array(
            'in',
            '3,4'
        );
        $order_count                = $order_service->getUserOrderCountByCondition($condition);
        if ($order_count == 0) {
            return $this->outMessage($title, "", '-50', "对不起,您无权进行此操作");
        }
        $order            = new OrderOrderService();
        $list             = $order->getOrderGoods($order_id);
        $orderDetail      = $order->getDetail($order_id);
        $data['order_no'] = $orderDetail['order_no'];
        $data['list']     = $list;

        return $this->outMessage($title, $data);
    }

    /**
     * 商品评价提交
     * 创建：李吉
     * 创建时间：2017-02-16 15:22:59
     */
    public function addGoodsEvaluate()
    {
        $title = "评价商品提交";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $order              = new OrderService();
        $member             = new Member();
        $order_id           = request()->post('order_id', '');
        $order_no           = request()->post('order_no', '');
        $order_id           = intval($order_id);
        $order_no           = intval($order_no);
        $goods              = request()->post('goodsEvaluate', '');
        $goodsEvaluateArray = json_decode($goods);
        $dataArr            = array();
        foreach ($goodsEvaluateArray as $key => $goodsEvaluate) {
//            if ($this->checkContraband($goodsEvaluate->content) > 0) return $this->outMessage($title, "", '-100', "包含违禁词");
            $orderGoods = $order->getOrderGoodsInfo($goodsEvaluate->order_goods_id);
            $data       = array(
                'order_id' => $order_id,
                'order_no' => $order_no,
                'order_goods_id' => intval($goodsEvaluate->order_goods_id),

                'goods_id' => $orderGoods['goods_id'],
                'goods_name' => $orderGoods['goods_name'],
                'goods_price' => $orderGoods['goods_money'],
                'goods_image' => $orderGoods['goods_picture'],
                'shop_id' => $orderGoods['shop_id'],
                'shop_name' => "默认",
                'content' => $goodsEvaluate->content,
                'addtime' => time(),
                'image' => $goodsEvaluate->imgs,

                // 'explain_first' => $goodsEvaluate->explain_first,
                'member_name' => $member->getMemberDetail()['member_name'],
                'explain_type' => $goodsEvaluate->explain_type,
                'uid' => $this->uid,
                'is_anonymous' => $goodsEvaluate->is_anonymous,
                'scores' => intval($goodsEvaluate->scores)
            );
            $dataArr[]  = $data;
        }

        $retval = $order->addGoodsEvaluate($dataArr, $order_id);
        return $this->outMessage($title, $retval);
    }

    /**
     * 商品-追加评价提交数据
     * 创建：李吉
     * 创建时间：2017-02-16 15:22:59
     */
    public function addGoodsEvaluateAgain()
    {
        $title = "追评商品提交";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $order              = new OrderService();
        $order_id           = request()->post('order_id', '');
        $order_no           = request()->post('order_no', '');
        $order_id           = intval($order_id);
        $order_no           = intval($order_no);
        $goods              = request()->post('goodsEvaluate', '');
        $goodsEvaluateArray = json_decode($goods);

        $result = 1;
        foreach ($goodsEvaluateArray as $key => $goodsEvaluate) {
//            if ($this->checkContraband($goodsEvaluate->content) > 0) return $this->outMessage($title, "", '-100', "包含违禁词");
            $res = $order->addGoodsEvaluateAgain($goodsEvaluate->content, $goodsEvaluate->imgs, $goodsEvaluate->order_goods_id);
            if ($res == false) {
                $result = false;
                break;
            }
        }
        if ($result == 1) {
            $data   = array(
                'is_evaluate' => 2
            );
            $result = $order->modifyOrderInfo($data, $order_id);
        }

        return $this->outMessage($title, $result);
    }


    /**
     * 获取订单商品满就送赠品，重复赠品累加数量
     * 创建时间：2018年1月24日12:34:10 王永杰
     */
    public function getOrderGoodsMansongGifts($goods_sku_list)
    {
        $res           = array();
        $promotion     = new Promotion();
        $goods_mansong = new GoodsMansong();
        $mansong_array = $goods_mansong->getGoodsSkuListMansong($goods_sku_list);
        if (!empty($mansong_array)) {
            foreach ($mansong_array as $k => $v) {
                foreach ($v['discount_detail'] as $discount_k => $discount_v) {
                    if (!empty($discount_v[0]['gift_id'])) {
                        #todo  @dai  设置多个赠品  ......
                        if(strpos($discount_v[0]['gift_id'],',') !== false){
                            $v   = explode(',', $discount_v[0]['gift_id']);
                            $num = count($v);
                            for ($i = 0; $i < $num; $i++) {
                                $_v = explode(':',$v[$i]);
                                #todo 判断赠品数量
                                $gift_info   = \think\Db::name('ns_promotion_gift')->where(['gift_id' => $_v[0]])->find();
                                $can_use_num = $gift_info['gift_num'] - $gift_info['gift_song_num'];    #可使用数量
                                if($can_use_num < $_v[1]) {
                                    # 原赠品不足
                                    $replace_info  = \think\Db::name('ns_promotion_gift_replace')->where(['gift_id' => $_v[0]])->find();
                                    if (!empty($replace_info)) {
                                        #有补充赠品 ...
                                        $new_gift_info   = \think\Db::name('ns_promotion_gift')->where(['gift_id' => $replace_info['n_gift_id']])->find();
                                        $new_can_use_num = $new_gift_info['gift_num'] - $new_gift_info['gift_song_num'];
                                        if ($can_use_num > 0) {
                                            # 原赠品 + 补充赠品
                                            $new_detail          = $promotion->getPromotionGiftDetail($_v[0]);
                                            $new_detail['count'] = $can_use_num;
                                            if($new_detail['count'] != 0) array_push($res, $new_detail);

                                            $new_detail          = $promotion->getPromotionGiftDetail($new_gift_info['gift_id']);
                                            $new_detail['count'] = (($_v[1] - $can_use_num) * $replace_info['n_num'] >= $new_can_use_num) ? $new_can_use_num : ($_v[1] - $can_use_num) * $replace_info['n_num'];
                                            if($new_detail['count'] != 0) array_push($res, $new_detail);
                                        } else {
                                            # 全补充赠品
                                            if ($new_can_use_num >= $_v[1] * $replace_info['n_num']) {
                                                #补充赠品足够
                                                $new_detail          = $promotion->getPromotionGiftDetail($new_gift_info['gift_id']);
                                                $new_detail['count'] = $_v[1] * $replace_info['n_num'];
                                                if($new_detail['count'] != 0) array_push($res, $new_detail);
                                            }else{
                                                # 补充赠品不足
                                                $new_detail          = $promotion->getPromotionGiftDetail($new_gift_info['gift_id']);
                                                $new_detail['count'] = $new_can_use_num;
                                                if($new_detail['count'] != 0) array_push($res, $new_detail);
                                            }
                                        }
                                    } else {
                                        #无补充赠品
                                        if ($can_use_num > 0) {
                                            $new_detail          = $promotion->getPromotionGiftDetail($_v[0]);
                                            $new_detail['count'] = $can_use_num;
                                            array_push($res, $new_detail);
                                        }
                                    }

                                }else{
                                    #原赠品足够
                                    $detail          = $promotion->getPromotionGiftDetail($_v[0]);
                                    $detail['count'] = $_v[1];
                                    array_push($res, $detail);
                                }
                            }
                        }else{
                            #单个赠品
                            $_v          = explode(':', $discount_v[0]['gift_id']);
                            $gift_info   = \think\Db::name('ns_promotion_gift')->where(['gift_id' => $_v[0]])->find();
                            $can_use_num = $gift_info['gift_num'] - $gift_info['gift_song_num'];    #可使用数量
                            if($can_use_num < $_v[1]) {
                                # 原赠品不足
                                $replace_info  = \think\Db::name('ns_promotion_gift_replace')->where(['gift_id' => $_v[0]])->find();
                                if (!empty($replace_info)) {
                                    #有补充赠品 ...
                                    $new_gift_info   = \think\Db::name('ns_promotion_gift')->where(['gift_id' => $replace_info['n_gift_id']])->find();
                                    $new_can_use_num = $new_gift_info['gift_num'] - $new_gift_info['gift_song_num'];
                                    if ($can_use_num > 0) {
                                        # 原赠品 + 补充赠品
                                        $new_detail          = $promotion->getPromotionGiftDetail($_v[0]);
                                        $new_detail['count'] = $can_use_num;
                                        if($new_detail['count'] != 0) array_push($res, $new_detail);

                                        $new_detail          = $promotion->getPromotionGiftDetail($_v[0]);
                                        $new_detail['count'] = (($_v[1] - $can_use_num) * $replace_info['n_num'] >= $new_can_use_num) ? $new_can_use_num : ($_v[1] - $can_use_num) * $replace_info['n_num'];
                                        if($new_detail['count'] != 0) array_push($res, $new_detail);
                                    } else {
                                        # 全补充赠品
                                        if ($new_can_use_num >= $_v[1] * $replace_info['n_num']) {
                                            #补充赠品足够
                                            $new_detail          = $promotion->getPromotionGiftDetail($new_gift_info['gift_id']);
                                            $new_detail['count'] = $_v[1] * $replace_info['n_num'];
                                            if($new_detail['count'] != 0) array_push($res, $new_detail);
                                        }else{
                                            # 补充赠品不足
                                            $new_detail          = $promotion->getPromotionGiftDetail($new_gift_info['gift_id']);
                                            $new_detail['count'] = $new_can_use_num * $replace_info['n_num'];
                                            if($new_detail['count'] != 0) array_push($res, $new_detail);
                                        }
                                    }
                                } else {
                                    #无补充赠品
                                    if ($can_use_num > 0) {
                                        $new_detail          = $promotion->getPromotionGiftDetail($_v[0]);
                                        $new_detail['count'] = $can_use_num;
                                        array_push($res, $new_detail);
                                    }
                                }
                            }else{
                                #原赠品足够
                                $detail          = $promotion->getPromotionGiftDetail($_v[0]);
                                $detail['count'] = $_v[1];
                                array_push($res, $detail);
                            }
                        }
                    }
                }
            }
        }

        // 去重
        $result = array();
        foreach ($res as $value) {
             //查看有没有重复项  
             if(isset($res[$value['gift_id']])){
                 unset($value['gift_id']);  //有：销毁  
             }else{
                 $result[$value['gift_id']] = $value;
             }
        }

        return $result;
    }

    /**
     * 清单分享:获取订单商品满就送赠品，重复赠品累加数量
     * 创建时间：
     */
    public function getShareOrderGoodsMansongGifts($goods_sku_list)
    {
        $res           = array();
        $promotion     = new Promotion();
        $goods_mansong = new GoodsMansong();
        $mansong_array = $goods_mansong->getShareGoodsSkuListMansong($goods_sku_list);
        if (!empty($mansong_array)) {
            foreach ($mansong_array as $k => $v) {
                foreach ($v['discount_detail'] as $discount_k => $discount_v) {
                    if (!empty($discount_v[0]['gift_id'])) {
                        #todo  @dai  设置多个赠品  ......
                        if(strpos($discount_v[0]['gift_id'],',') !== false){
                            $v   = explode(',', $discount_v[0]['gift_id']);
                            $num = count($v);
                            for ($i = 0; $i < $num; $i++) {
                                $_v = explode(':',$v[$i]);
                                #todo 判断赠品数量
                                $gift_info   = \think\Db::name('ns_promotion_gift')->where(['gift_id' => $_v[0]])->find();
                                $can_use_num = $gift_info['gift_num'] - $gift_info['gift_song_num'];    #可使用数量
                                if($can_use_num < $_v[1]) {
                                    # 原赠品不足
                                    $replace_info  = \think\Db::name('ns_promotion_gift_replace')->where(['gift_id' => $_v[0]])->find();
                                    if (!empty($replace_info)) {
                                        #有补充赠品 ...
                                        $new_gift_info   = \think\Db::name('ns_promotion_gift')->where(['gift_id' => $replace_info['n_gift_id']])->find();
                                        $new_can_use_num = $new_gift_info['gift_num'] - $new_gift_info['gift_song_num'];
                                        if ($can_use_num > 0) {
                                            # 原赠品 + 补充赠品
                                            $new_detail          = $promotion->getPromotionGiftDetail($_v[0]);
                                            $new_detail['count'] = $can_use_num;
                                            if($new_detail['count'] != 0) array_push($res, $new_detail);

                                            $new_detail          = $promotion->getPromotionGiftDetail($new_gift_info['gift_id']);
                                            $new_detail['count'] = (($_v[1] - $can_use_num) * $replace_info['n_num'] >= $new_can_use_num) ? $new_can_use_num : ($_v[1] - $can_use_num) * $replace_info['n_num'];
                                            if($new_detail['count'] != 0) array_push($res, $new_detail);
                                        } else {
                                            # 全补充赠品
                                            if ($new_can_use_num >= $_v[1] * $replace_info['n_num']) {
                                                #补充赠品足够
                                                $new_detail          = $promotion->getPromotionGiftDetail($new_gift_info['gift_id']);
                                                $new_detail['count'] = $_v[1] * $replace_info['n_num'];
                                                if($new_detail['count'] != 0) array_push($res, $new_detail);
                                            }else{
                                                # 补充赠品不足
                                                $new_detail          = $promotion->getPromotionGiftDetail($new_gift_info['gift_id']);
                                                $new_detail['count'] = $new_can_use_num;
                                                if($new_detail['count'] != 0) array_push($res, $new_detail);
                                            }
                                        }
                                    } else {
                                        #无补充赠品
                                        if ($can_use_num > 0) {
                                            $new_detail          = $promotion->getPromotionGiftDetail($_v[0]);
                                            $new_detail['count'] = $can_use_num;
                                            array_push($res, $new_detail);
                                        }
                                    }

                                }else{
                                    #原赠品足够
                                    $detail          = $promotion->getPromotionGiftDetail($_v[0]);
                                    $detail['count'] = $_v[1];
                                    array_push($res, $detail);
                                }
                            }
                        }else{
                            #单个赠品
                            $_v          = explode(':', $discount_v[0]['gift_id']);
                            $gift_info   = \think\Db::name('ns_promotion_gift')->where(['gift_id' => $_v[0]])->find();
                            $can_use_num = $gift_info['gift_num'] - $gift_info['gift_song_num'];    #可使用数量
                            if($can_use_num < $_v[1]) {
                                # 原赠品不足
                                $replace_info  = \think\Db::name('ns_promotion_gift_replace')->where(['gift_id' => $_v[0]])->find();
                                if (!empty($replace_info)) {
                                    #有补充赠品 ...
                                    $new_gift_info   = \think\Db::name('ns_promotion_gift')->where(['gift_id' => $replace_info['n_gift_id']])->find();
                                    $new_can_use_num = $new_gift_info['gift_num'] - $new_gift_info['gift_song_num'];
                                    if ($can_use_num > 0) {
                                        # 原赠品 + 补充赠品
                                        $new_detail          = $promotion->getPromotionGiftDetail($_v[0]);
                                        $new_detail['count'] = $can_use_num;
                                        if($new_detail['count'] != 0) array_push($res, $new_detail);

                                        $new_detail          = $promotion->getPromotionGiftDetail($_v[0]);
                                        $new_detail['count'] = (($_v[1] - $can_use_num) * $replace_info['n_num'] >= $new_can_use_num) ? $new_can_use_num : ($_v[1] - $can_use_num) * $replace_info['n_num'];
                                        if($new_detail['count'] != 0) array_push($res, $new_detail);
                                    } else {
                                        # 全补充赠品
                                        if ($new_can_use_num >= $_v[1] * $replace_info['n_num']) {
                                            #补充赠品足够
                                            $new_detail          = $promotion->getPromotionGiftDetail($new_gift_info['gift_id']);
                                            $new_detail['count'] = $_v[1] * $replace_info['n_num'];
                                            if($new_detail['count'] != 0) array_push($res, $new_detail);
                                        }else{
                                            # 补充赠品不足
                                            $new_detail          = $promotion->getPromotionGiftDetail($new_gift_info['gift_id']);
                                            $new_detail['count'] = $new_can_use_num * $replace_info['n_num'];
                                            if($new_detail['count'] != 0) array_push($res, $new_detail);
                                        }
                                    }
                                } else {
                                    #无补充赠品
                                    if ($can_use_num > 0) {
                                        $new_detail          = $promotion->getPromotionGiftDetail($_v[0]);
                                        $new_detail['count'] = $can_use_num;
                                        array_push($res, $new_detail);
                                    }
                                }
                            }else{
                                #原赠品足够
                                $detail          = $promotion->getPromotionGiftDetail($_v[0]);
                                $detail['count'] = $_v[1];
                                array_push($res, $detail);
                            }
                        }
                    }
                }
            }
        }

        // 去重
        $result = array();
        foreach ($res as $value) {
            //查看有没有重复项
            if(isset($res[$value['gift_id']])){
                unset($value['gift_id']);  //有：销毁
            }else{
                $result[$value['gift_id']] = $value;
            }
        }

        return $result;
    }


    private function check_allow_api_parameter($parameters)
    {
        foreach ($parameters as $v) if ($this->request->param($v, '') === '') return $v;
        return false;
    }

    /**
     * OMS发货
     */
    public function orderDelivery(\think\Request $request)
    {
        $allow_ip = [
            '47.92.195.146',
            '47.92.1.182'
        ];

        if (!in_array($request->ip(), $allow_ip)) die('deny ip');

        $shipping_type = $request->param('shipping_type', '');

        if ($shipping_type == 1) {
            $check = $this->check_allow_api_parameter(['tid', 'order_goods_id_array', 'express', 'express_no']);
        } else {
            $check = $this->check_allow_api_parameter(['tid', 'order_goods_id_array']);
        }

        # todo OMS发货人预留  NsOrderGoodsExpressModel: $user_name = $request->param('checker', 'OMS');
        # demo: express: YTO  express_no:1805092136000003  tid: 2018051014040001, order_goods_id_array: 205, checker: admin
        if ($check) return json(['code' => -50, 'msg' => 'missing parameter from ' . $check]);

        $order_service = new OrderService();
        $order_info    = $order_service->getOrderId($request->param('tid')); # 订单信息

        if (!$order_info) return json(['code' => -50, 'msg' => 'tid is error']); # 无效订单号

//        $order_goods_id_array = $request->param('order_goods_id_array');

        //配合oms修改过的特殊订单发货回传
        $order_goods          = new OrderGoods();
        $order_goods_id_array = $order_goods->getOrderGoodsIdArray($order_info['order_id']);


        # todo 注释代码预留 检查子订单 oms会编辑订单, step1 oms添加不存在的订单项忽略操作.
//        $allow = [];
//        $order_goods_id_array = explode(',', $request->param('order_goods_id_array'));
//        foreach ($order_goods_id_array as $k => $order_goods_id) {
//            $order_goods_id = (int) $order_goods_id;
////            print_r(\think\Db::table('ns_order_goods')->find(48));
//            if(\think\Db::table('ns_order_goods')->where([
//                'order_id' => $order_info['order_id'],
//                'order_goods_id' => $order_goods_id,
//            ])->find()) array_push($allow, $order_goods_id);
//        }
//        $order_goods_id_array = implode(',', $allow);

        if ($shipping_type == 1) {
            $expressCompany = new ExpressService();
            $express_array  = $expressCompany->getExpressCompany($request->param('express')); # 物流公司信息 STO
            # 本地基础数据库物流公司未填全
            if (!$express_array) return json(['code' => -50, 'msg' => 'need add ' . $request->param('express') . ' logisics company data from ns_db']);

            $res = $order_service->orderDeliveryOms($order_info['order_id'], $order_goods_id_array, $express_array['company_name'], 1, $express_array['co_id'], $request->param('express_no'));
        } else {
            $res = $order_service->orderGoodsDelivery($order_info['order_id'], $order_goods_id_array);
        }
        return json(['code' => 0, 'msg' => 'success', 'data' => $res]);
    }

    #创建礼品通知模板
    public function getGiftTemplate(Request $request)
    {
        Db::name('ns_template_push')->insert([
            'open_id' => $request->param('openid'),
            'form_id' => $request->param('formid'),
            'warn_type' => 5,
            'out_trade_no' => $request->param('out_trade_no'),
            'is_send' => 0,
            'created' => date('Y-m-d H:i:s', time())
        ]);

        return json(['code' => 0, 'msg' => 'success']);
    }


    # 礼物领取通知
    public function pushGetGiftTemplate(Request $request)
    {
        $where     = [
            'warn_type' => 5,
            'is_send' => 0,
            'out_trade_no' => $request->param('out_trade_no'),
        ];
        $templates = \think\Db::name('ns_template_push')->where($where)->find();
//        $template_id = 'EuBq12R7_lt2UXd4unkGrtoXeeL9cVbmxoon143qEdA'; # dev
//        $template_id = 'Fd_1K7XdGKlRU95FBKi4c6b0vb-NY0dgULDSBvGP2wg'; # prod
        $template_id = getWxTemplateId('gift_send');
        $user_info   = \think\Db::name('ns_member')->where(['uid' => $request->param('uid')])->find();
        # 该用户不存在
        if (!$user_info) return;
        $conf = json_decode(\think\Db::name('sys_config')->where([
            'key' => 'SHOPAPPLET'
        ])->find()['value'], true);

        $appid  = $conf['appid'];
        $secret = $conf['appsecret'];

        $access_token = getAccessToken($appid, $secret);
        $select_url   = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;

        $openid = $templates['open_id'];
        $fid    = $templates['form_id'];

        $p1 = $user_info['member_name'];
        $p2 = date("Y-m-d H:i", time());
        $p3 = '您的礼品已被领取完毕,商家将尽快发货';

        $page = 'pages/member/giftPrefecture/giftPrefecture';

        $param = <<<EOL
{
  "touser": "$openid",
  "template_id": "$template_id",
  "page": "$page",
  "form_id": "$fid",
  "data": {
      "keyword1": {
          "value": "$p1",
          "color": "#F00"
      },
      "keyword2": {
          "value": "$p2",
          "color": "#F00"
      },
      "keyword3": {
          "value": "$p3"
      }
  },
  "color":"#ccc"
}
EOL;

        curl_post($select_url, $param);
//        \think\Db::name('ns_template_push')->where(['id'=>$template['id']])->delete();
    }

    # 会员购买通知
    public function pushPayVipTemplate($request)
    {
        $template_id = getWxTemplateId('pay_vip');
        $order       = \think\Db::name('ns_order')->where(['out_trade_no' => $request['out_trade_no']])->find();
        if ($order['pay_status'] !== 2) {
            return;
        }
        # 该订单不存在
        if (!$order) return;
        $conf = json_decode(\think\Db::name('sys_config')->where([
            'key' => 'SHOPAPPLET'
        ])->find()['value'], true);

        $appid  = $conf['appid'];
        $secret = $conf['appsecret'];

        $access_token = getAccessToken($appid, $secret);
        $select_url   = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;

        $openid = $request['openid'];
        $fid    = $request['formid'];

        $p1 = 'BC尊享会员';
        $p2 = $request['price'];
        $p3 = date("Y-m-d", $order['create_time']);
        $p4 = '12个月';
        $p5 = '如有疑问，可打开小程序联系客服。';

        $page = 'pages/payMembers/memberZone/memberZone';

        $param = <<<EOL
{
  "touser": "$openid",
  "template_id": "$template_id",
  "page": "$page",
  "form_id": "$fid",
  "data": {
      "keyword1": {
          "value": "$p1",
          "color": "#F00"
      },
      "keyword2": {
          "value": "$p2",
          "color": "#F00"
      },
      "keyword3": {
          "value": "$p3"
      },
      "keyword4": {
          "value": "$p4",
          "color": "#173177"
      } ,
      "keyword5": {
          "value": "$p5",
          "color": "#173177"
      }
  },
  "color":"#ccc"
}
EOL;

        curl_post($select_url, $param);
//        \think\Db::name('ns_template_push')->where(['id'=>$template['id']])->delete();
    }

    # 订单支付成功通知
    public function pushPayOrderTemplate($request)
    {
//        warn_type = 6
        $template_id = getWxTemplateId('pay_order');
        $order       = \think\Db::name('ns_order')->where(['out_trade_no' => $request['out_trade_no']])->find();
        if ($order['pay_status'] !== 2) {
            return;
        }
        # 该订单不存在
        if (!$order) return;

        #判断是否有优惠券
        $coupon = \think\Db::name('ns_coupon')->where(['create_order_id' => $order['order_id'], 'state' => 1 , 'uid' => $this->uid])->find();
        if (empty($coupon)) {
            $desc = '加VIP客服号（微信ID：bonnieclydegogogo），不定期推荐限量产品和专属折扣！';
        } else {
            $desc = '加VIP客服号（微信ID：bonnieclydegogogo），不定期推荐限量产品和专属折扣！(恭喜您获得优惠券!)';
        }


        $conf = json_decode(\think\Db::name('sys_config')->where([
            'key' => 'SHOPAPPLET'
        ])->find()['value'], true);

        $appid  = $conf['appid'];
        $secret = $conf['appsecret'];

        $access_token = getAccessToken($appid, $secret);
        $select_url   = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;

        $openid = $request['openid'];
        $fid    = $request['formid'];

        $p1 = $order['order_no'];
        $p2 = date("Y-m-d", $order['create_time']);
        $p3 = $order['pay_money'];
        $p4 = '微信支付';
        $p5 = $desc;

//        $page = 'pages/order/myorderlist/myorderlist?status=2';
        $page = 'pages/member/member/member';

        $param = <<<EOL
{
  "touser": "$openid",
  "template_id": "$template_id",
  "page": "$page",
  "form_id": "$fid",
  "data": {
      "keyword1": {
          "value": "$p1",
          "color": "#F00"
      },
      "keyword2": {
          "value": "$p2",
          "color": "#F00"
      },
      "keyword3": {
          "value": "$p3"
      },
      "keyword4": {
          "value": "$p4",
          "color": "#173177"
      } ,
      "keyword5": {
          "value": "$p5",
          "color": "#173177"
      }
  },
  "color":"#ccc"
}
EOL;

        curl_post($select_url, $param);
//        \think\Db::name('ns_template_push')->where(['id'=>$template['id']])->delete();
    }

    # 获取优惠券到期模版
    public function getCouponTemplate(Request $request)
    {
//        warn_type=7
        Db::name('ns_template_push')->insert([
            'open_id' => $request->param('openid'),
            'form_id' => $request->param('formid'),
            'warn_type' => 7,
            'out_trade_no' => $request->param('coupon_id'), #优惠券id
            'is_send' => 0,
            'created' => date('Y-m-d H:i:s', time())
        ]);

        return json(['code' => 0, 'msg' => 'success']);
    }


    # 获取试用新品上架到期模版
    public function getTrialGoodsTemplate(Request $request)
    {
//        warn_type = 9
//        $info = \think\Db::name('ns_template_push')->where(['out_trade_no'=>$request->param('uid'),'warn_type' => 9])->find();
//        if($info) return;
        Db::name('ns_template_push')->insert([
            'open_id' => $request->param('openid'),
            'form_id' => $request->param('formid'),
            'out_trade_no' => 123, #用户id
            'warn_type' => 9,
            'is_send' => 0,
            'created' => date('Y-m-d H:i:s', time())
        ]);
        return json(['code' => 0, 'msg' => 'success']);
    }


    #创建到货通知模板
    public function getUpProTemplate(Request $request)
    {
        #warn_type = 11;
        if (Db::name('ns_template_push')->where(['out_trade_no' => $request->param('goods_id'), 'open_id' => $request->param('openid'), 'warn_type' => 11])->find()) return;
        Db::name('ns_template_push')->insert([
            'open_id' => $request->param('openid'),
            'form_id' => $request->param('formid'),
            'uid' => $request->param('uid'),
            'warn_type' => 11,
            'out_trade_no' => $request->param('goods_id'),
            'is_send' => 0,
            'created' => date('Y-m-d H:i:s', time())
        ]);

        return json(['code' => 0, 'msg' => 'success']);
    }

    public function checkContraband($content)
    {
        $badword = require('badword.src.php');
        $m       = 0;
        for ($i = 0; $i < count($badword); $i++) {    //根据数组元素数量执行for循环
            //应用substr_count检测文章的标题和内容中是否包含敏感词      111
            if (substr_count($content, $badword [$i]) > 0) {
                $m++;
            }
        }
        return $m;
    }


    # todo @dai 支付成功赠送优惠券逻辑
    public function giveFullOfGifts()
    {
        $title        = '支付成功赠送优惠券';
        $out_trade_no = request()->post('out_trade_no', '0');
        if (empty($out_trade_no)) return $this->outMessage($title, "", '-1', "参数为空");

        #$out_trade_no = '153112844962171000';       // 测试

        #已支付订单
        $order_info = \think\Db::name('ns_order')->where(['pay_status' => 2, 'out_trade_no' => $out_trade_no])->find();
        if (empty($order_info)) return $this->outMessage($title, "", '-2', "订单异常");


        #获取该订单所有子订单
        $order_goods_lists = \think\Db::name('ns_order_goods')->where(['order_id' => $order_info['order_id']])->select();

        #订单中有打折商品 不能获得
        foreach ($order_goods_lists as $v) {
            if ($v['adjust_money'] < 0) return $this->outMessage($title, "", '-4', "订单中有打折商品");
        }

        #列出send_type = 2 消费获取 的所有优惠券类型
        $coupon_type_lists = \think\Db::name('ns_coupon_type')->where(['send_type' => 2])->select();

        foreach ($coupon_type_lists as $key => $val) {

            #排除总金额未达到
            if ($order_info['pay_money'] < $val['pay_money_get']) continue;

            $coupon_lists = \think\Db::name('ns_coupon')->where(['coupon_type_id' => $val['coupon_type_id'], 'state' => 0])->select();
            if (empty($coupon_lists)) continue;

            #range_type = 1 全场商品使用
            if ($val['range_type'] == 1) {
                #全场商品通用

                #领取 更新信息
                $res['uid']             = $this->uid;
                $res['state']           = 1;
                $res['create_order_id'] = $order_info['order_id'];
                $res['fetch_time']      = time();
                \think\Db::name('ns_coupon')->where(['coupon_id' => $coupon_lists['0']['coupon_id']])->update($res);

            } else {
                #部分商品可用

                #拿到哪些商品可用
                $coupon_goods_lists = \think\Db::name('ns_coupon_goods')->where(['coupon_type_id' => $val['coupon_type_id']])->select();

                #todo ...
                foreach ($coupon_goods_lists as $k0 => $v0) {

                    $price_count = 0; #商品总额

                    foreach ($order_goods_lists as $k1 => $v1) {
                        #统计某个商品总额
                        if ($v0['goods_id'] == $v1['goods_id']) $price_count += $v1['goods_money'];
                    }

                    if ($price_count >= $val['pay_money_get']) {
                        #领取 更新信息
                        $res['uid']             = $this->uid;
                        $res['state']           = 1;
                        $res['create_order_id'] = $order_info['order_id'];
                        $res['fetch_time']      = time();
                        \think\Db::name('ns_coupon')->where(['coupon_id' => $coupon_lists['0']['coupon_id']])->update($res);
                    }
                }
            }
        }
    }


}