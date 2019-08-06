<?php
/**
 * Order.php
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

/**
 * 订单
 */
use data\api\IOrder as IOrder;
use data\extend\Kdniao;
use data\model\AlbumPictureModel;
use data\model\CityModel;
use data\model\DistrictModel;
use data\model\NsCartModel;
use data\model\NsBoxModel;
use data\model\NsGoodsCommentModel;
use data\model\NsGoodsEvaluateModel;
use data\model\NsGoodsModel;
use data\model\NsGoodsSkuModel;
use data\model\NsOrderExpressCompanyModel;
use data\model\NsOrderGoodsExpressModel;
use data\model\NsOrderGoodsModel;
use data\model\NsOrderModel;
use data\model\BcOrderOfflinePayModel;
use data\model\NsOrderPaymentModel;
use data\model\NsOrderRefundAccountRecordsModel;
use data\model\NsOrderShopReturnModel;
use data\model\NsShopModel;
use data\model\ProvinceModel;
use data\service\BaseService;
use data\service\Goods as GoodsService;
use data\service\GoodsCalculate\GoodsCalculate;
use data\service\NfxCommissionCalculate;
use data\service\NfxUser;
use data\service\niubusiness\NbsBusinessAssistantAccount;
use data\service\Order\Order as OrderBusiness;
use data\service\Order\OrderAccount;
use data\service\Order\OrderExpress;
use data\service\Order\OrderGoods;
use data\service\Order\OrderStatus;
use data\service\Pay\AliPay;
use data\service\Pay\WeiXinPay;
use data\service\promotion\GoodsExpress;
use data\service\promotion\GoodsPreference;
use data\service\promotion\PromoteRewardRule;
use data\service\shopaccount\ShopAccount;
use OMS\Sdk\Applet\OrderModify;
use think\Db;
use think\Log;
use data\model\NsOrderPromotionDetailsModel;
use data\extend\Snoopy;
use data\service\Order\OrderCard;
use data\service\Member;
use data\model\NsGiftGiveGetModel;
use data\model\NsMemberModel;
use data\model\BcDistributorStarModel;
use data\model\BcDistributorAccountRecordsModel;
use data\model\BcDistributorModel;
use data\model\NsGoodsCategoryModel as NsGoodsCategoryModel;
use data\model\UserModel;
class Order extends BaseService implements IOrder
{
    function __construct()
    {
        parent::__construct();
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::getOrderDetail()
     */
    public function getOrderDetail($order_id)
    {
        // 查询主表信息
        $order  = new OrderBusiness();
        $detail = $order->getDetail($order_id);
        if (empty($detail)) {
            return array();
        }
        $detail['pay_status_name']      = $this->getPayStatusInfo($detail['pay_status'])['status_name'];
        $detail['shipping_status_name'] = $this->getShippingInfo($detail['shipping_status'])['status_name'];

        $express_list = $this->getOrderGoodsExpressList($order_id);
        // 未发货的订单项
        $order_goods_list = array();
        // 已发货的订单项
        $order_goods_delive = array();
        // 没有配送信息的订单项
        $order_goods_exprss = array();
        foreach ($detail["order_goods"] as $order_goods_obj) {
            $shipping_status = $order_goods_obj["shipping_status"];
            if ($shipping_status == 0) {
                // 未发货
                $order_goods_list[] = $order_goods_obj;
            } else {
                $order_goods_delive[] = $order_goods_obj;
            }
        }
        $detail["order_goods_no_delive"] = $order_goods_list;
        // 没有配送信息的订单项
        if (!empty($order_goods_delive) && count($order_goods_delive) > 0) {
            foreach ($order_goods_delive as $goods_obj) {
                $is_have        = false;
                $order_goods_id = $goods_obj["order_goods_id"];
                foreach ($express_list as $express_obj) {
                    $order_goods_id_array = $express_obj["order_goods_id_array"];
                    $goods_id_str         = explode(",", $order_goods_id_array);
                    if (in_array($order_goods_id, $goods_id_str)) {
                        $is_have = true;
                    }
                }
                if (!$is_have) {
                    $order_goods_exprss[] = $goods_obj;
                }
            }
        }
        $goods_packet_list = array();
        if (count($order_goods_exprss) > 0) {
            $packet_obj          = array(
                "packet_name" => "无需物流",
                "express_name" => "",
                "express_code" => "",
                "express_id" => 0,
                "is_express" => 0,
                "order_goods_list" => $order_goods_exprss
            );
            $goods_packet_list[] = $packet_obj;
        }
        if (!empty($express_list) && count($express_list) > 0 && count($order_goods_delive) > 0) {
            $packet_num = 1;
            foreach ($express_list as $express_obj) {
                $packet_goods_list    = array();
                $order_goods_id_array = $express_obj["order_goods_id_array"];
                $goods_id_str         = explode(",", $order_goods_id_array);
                foreach ($order_goods_delive as $delive_obj) {
                    $order_goods_id = $delive_obj["order_goods_id"];
                    if (in_array($order_goods_id, $goods_id_str)) {
                        $packet_goods_list[] = $delive_obj;
                    }
                }
                $packet_obj          = array(
                    "packet_name" => "包裹  + " . $packet_num,
                    "express_name" => $express_obj["express_name"],
                    "express_code" => $express_obj["express_no"],
                    "express_id" => $express_obj["id"],
                    "is_express" => 1,
                    "order_goods_list" => $packet_goods_list
                );
                $packet_num          = $packet_num + 1;
                $goods_packet_list[] = $packet_obj;
            }
        }
        $detail["goods_packet_list"]  = $goods_packet_list;
        $virtual_goods                = new VirtualGoods();
        $virtual_goods_list           = $virtual_goods->getVirtualGoodsListByOrderNo($detail['order_no']);
        $detail['virtual_goods_list'] = $virtual_goods_list;
        // 订单优惠类型
        $ns_order_promotion       = new NsOrderPromotionDetailsModel();
        $promotion_detail         = $ns_order_promotion->getInfo([
            "order_id" => $order_id
        ], "promotion_type");
        $detail['promotion_type'] = $promotion_detail['promotion_type'];

        //线下支付订单查询支付记录
        if($detail['payment_type'] == 10 || $detail['payment_type'] == 11){
            $order_offline_pay = new BcOrderOfflinePayModel();
            $offline_pay_list = $order_offline_pay->getQuery([
                "order_id" => $detail['order_id']
            ], "*", "id");
            $pay_type = ['1'=>'全款支付','2'=>'赊销首款','3'=>'赊销尾款'];
            $pay_way = ['1'=>'支付宝','2'=>'招商银行','99'=>'其它'];
            if(!empty($offline_pay_list)){
                foreach ($offline_pay_list as $k=>$v) {
                    $offline_pay_list[$k]['pay_type_name'] = $pay_type[$v['pay_type']];
                    $offline_pay_list[$k]['pay_way_name'] = $pay_way[$v['pay_way']];
                }
            }
        }
        $detail['offline_pay_list'] = $offline_pay_list;

        return $detail;
    }

    /**
     * 获取订单基础信息
     *
     * @param unknown $order_id
     */
    public function getOrderInfo($order_id)
    {
        $order_model = new NsOrderModel();
        $order_info  = $order_model->get($order_id);
        return $order_info;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::getExportOrderList()
     */
    public function getExportOrderList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $order_model = new NsOrderModel();
        // 查询主表
        $order_list = $order_model->pageQuery($page_index, $page_size, $condition, $order, '*');

        if (!empty($order_list['data'])) {
            foreach ($order_list['data'] as $k => $v) {
                // 查询订单项表
                $order_item      = new NsOrderGoodsModel();
                $order_item_list = $order_item->where([
                    'order_id' => $v['order_id']
                ])->select();
                // 通过sku_id查询ns_goods_sku中code
                foreach ($order_item_list as $key => $val) {
                    // 查询商品sku表开始
                    $goods_sku                     = new NsGoodsSkuModel();
                    $goods_sku_info                = $goods_sku->getInfo([
                        'sku_id' => $val['sku_id']
                    ], 'code');
                    $order_item_list[$key]['code'] = $goods_sku_info['code'];
                    // 查询商品sku结束
                }

                $province_name = "";
                $city_name     = "";
                $district_name = "";

                $province      = new ProvinceModel();
                $province_info = $province->getInfo(array(
                    "province_id" => $v["receiver_province"]
                ), "*");
                if (count($province_info) > 0) {
                    $province_name = $province_info["province_name"];
                }
                $order_list['data'][$k]['receiver_province_name'] = $province_name;
                $city                                             = new CityModel();
                $city_info                                        = $city->getInfo(array(
                    "city_id" => $v["receiver_city"]
                ), "*");
                if (count($city_info) > 0) {
                    $city_name = $city_info["city_name"];
                }
                $order_list['data'][$k]['receiver_city_name'] = $city_name;
                $district                                     = new DistrictModel();
                $district_info                                = $district->getInfo(array(
                    "district_id" => $v["receiver_district"]
                ), "*");
                if (count($district_info) > 0) {
                    $district_name = $district_info["district_name"];
                }
                $order_list['data'][$k]['receiver_district_name'] = $district_name;
                foreach ($order_item_list as $key_item => $v_item) {

                    $picture = new AlbumPictureModel();
                    // $order_item_list[$key_item]['picture'] = $picture->get($v_item['goods_picture']);
                    $goods_picture = $picture->get($v_item['goods_picture']);
                    if (empty($goods_picture)) {
                        $goods_picture = array(
                            'pic_cover' => '',
                            'pic_cover_big' => '',
                            'pic_cover_mid' => '',
                            'pic_cover_small' => '',
                            'pic_cover_micro' => '',
                            "upload_type" => 1,
                            "domain" => ""
                        );
                    }
                    $order_item_list[$key_item]['picture'] = $goods_picture;
                    if ($v_item['refund_status'] != 0) {
                        $order_refund_status = OrderStatus::getRefundStatus();
                        foreach ($order_refund_status as $k_status => $v_status) {

                            if ($v_status['status_id'] == $v_item['refund_status']) {
                                $order_item_list[$key_item]['refund_operation'] = $v_status['refund_operation'];
                                $order_item_list[$key_item]['status_name']      = $v_status['status_name'];
                            }
                        }
                    } else {
                        $order_item_list[$key_item]['refund_operation'] = '';
                        $order_item_list[$key_item]['status_name']      = '';
                    }
                }
                $order_list['data'][$k]['order_item_list'] = $order_item_list;
                $order_list['data'][$k]['operation']       = '';
                // 订单来源名称
                $order_from                                = OrderStatus::getOrderFrom($v['order_from']);
                $order_list['data'][$k]['order_from_name'] = $order_from['type_name'];
                $order_list['data'][$k]['order_from_tag']  = $order_from['tag'];
                $order_list['data'][$k]['pay_type_name']   = OrderStatus::getPayType($v['payment_type']);
                // 根据订单类型判断订单相关操作
                if ($order_list['data'][$k]['order_type'] == 1 || $order_list['data'][$k]['order_type'] == 3) {
                    if ($order_list['data'][$k]['payment_type'] == 6 || $order_list['data'][$k]['shipping_type'] == 2) {
                        $order_status = OrderStatus::getSinceOrderStatus();
                    } else {
                        $order_status = OrderStatus::getOrderCommonStatus();
                    }
                } else {
                    // 虚拟订单
                    $order_status = OrderStatus::getVirtualOrderCommonStatus();
                }

                // 查询订单操作
                foreach ($order_status as $k_status => $v_status) {
                    if ($v_status['status_id'] == $v['order_status']) {
                        $order_list['data'][$k]['operation']        = $v_status['operation'];
                        $order_list['data'][$k]['member_operation'] = $v_status['member_operation'];
                        $order_list['data'][$k]['status_name']      = $v_status['status_name'];
                        $order_list['data'][$k]['is_refund']        = $v_status['is_refund'];
                    }
                }
            }
        }

        return $order_list;
    }

    private $goodsList = [];

    public function getExportOrderListNew($condition = '', $order = '')
    {
//        $goods_sku = new NsGoodsModel(); //商品
//        $province = new ProvinceModel(); //省
//        $city = new CityModel(); //市
//        $district = new DistrictModel(); //区县
        // 查询主表
        $order_list = Db::table('ns_order')->where($condition)->order($order)->select();
        if (!empty($order_list)) {
            foreach ($order_list as $k => $v) {
//                $province_name = "";
//                $city_name     = "";
//                $district_name = "";
//
//                if (isset($this->provinceList[$v["receiver_province"]])) {
//                    $province_info = $this->provinceList[$v["receiver_province"]];
//                } else {
//                    $province_info = $province->getInfo([
//                        "province_id" => $v["receiver_province"]
//                    ], "*");
//                }
//                if ($province_info) {
//                    $province_name = $province_info["province_name"];
//                    $this->provinceList[$v["receiver_province"]] = $province_info;
//                }
//                $order_list[$k]['receiver_province_name'] = $province_name;
//
//
//                if (isset($this->cityList[$v["receiver_city"]])) {
//                    $city_info = $this->provinceList[$v["receiver_city"]];
//                } else {
//                    $city_info = $city->getInfo([
//                        "city_id" => $v["receiver_city"]
//                    ], "*");
//                }
//                if ($city_info) {
//                    $city_name = $city_info["city_name"];
//                    $this->cityList[$v["receiver_city"]] = $city_info;
//                }
//                $order_list[$k]['receiver_city_name'] = $city_name;
//
//
//                if (isset($this->districtList[$v["receiver_district"]])) {
//                    $district_info = $this->provinceList[$v["receiver_district"]];
//                } else {
//                    $district_info = $district->getInfo([
//                        "district_id" => $v["receiver_district"]
//                    ], "*");
//                }
//                if ($district_info) {
//                    $district_name = $district_info["district_name"];
//                    $this->districtList[$v["receiver_city"]] = $district_info;
//                }
//                $order_list[$k]['receiver_district_name'] = $district_name;

                // 查询订单项表
                $order_item_list = Db::table('ns_order_goods')->where(['order_id' => $v['order_id']])->select();
                // 通过sku_id查询ns_goods_sku中code
//                foreach ($order_item_list as $key => $val) {
//                    // 查询商品sku表开始
//                    if (isset($this->goodsList['good_sku_info_' . $val['goods_id']])) {
//                        $goods_sku_info = $this->goodsList['good_sku_info_' . $val['goods_id']];
//                    } else {
//                        $goods_sku_info = $goods_sku->getInfo([
//                            'goods_id' => $val['goods_id']
//                        ], 'code,product_barcode,material_code');
//
//                        $this->goodsList['good_sku_info_' . $val['goods_id']] = $goods_sku_info;
//                    }
//                    $order_item_list[$key]['code']            = $goods_sku_info['code'];
//                    $order_item_list[$key]['product_barcode'] = $goods_sku_info['product_barcode'];
//                    $order_item_list[$key]['material_code']   = $goods_sku_info['material_code'];
//                    // 查询商品sku表结束
//                }
                $order_list[$k]['order_item_list'] = $order_item_list;

                // 根据订单类型判断订单相关操作
                if ($order_list[$k]['order_type'] == 1 || $order_list[$k]['order_type'] == 3) {
                    if ($order_list[$k]['payment_type'] == 6 || $order_list[$k]['shipping_type'] == 2) {
                        $order_status = OrderStatus::getSinceOrderStatus();
                    } else {
                        $order_status = OrderStatus::getOrderCommonStatus();
                    }
                } else if ($order_list[$k]['order_type'] == 4) {
                    // 礼品订单
                    $order_status = OrderStatus::getGiftOrderCommonStatus();
                } else if ($order_list[$k]['order_type'] == 5) {
                    // 会员订单
                    $order_status = OrderStatus::getVipOrderCommonStatus();
                } else {
                    // 虚拟订单
                    $order_status = OrderStatus::getVirtualOrderCommonStatus();
                }

                // 订单状态
                foreach ($order_status as $k_status => $v_status) {
                    if ($v_status['status_id'] == $v['order_status']) {
                        $order_list[$k]['status_name'] = $v_status['status_name'];
                    }
                }

                //查询来源网点
                if ($v['source_branch'] > 0) {
                    $store                                = new Store();
                    $store_detail                         = $store->getStoreDetail($v['source_branch']);
                    $order_list[$k]['source_branch_name'] = $store_detail['store_name'];
                } else {
                    $order_list[$k]['source_branch_name'] = 'BC线上';
                }

                //查询分销来源
                if ($v['source_distribution'] > 0) {
                    $member                                     = new Member();
                    $member_detail                              = $member->getMember($v['source_distribution']);
                    $order_list[$k]['source_distribution_name'] = $member_detail['real_name'];
                } else {
                    $order_list[$k]['source_distribution_name'] = '--';
                }
            }
        }
        return $order_list;
    }

    public function exportOrder($condition,$order)
    {
        // 查询主表
        $order_list = Db::table('ns_order')->where($condition)->order($order)->select();
        if (!empty($order_list)) {
            foreach ($order_list as $k => $v) {

                // 查询订单项表
                $order_item_list = Db::table('ns_order_goods')->where(['order_id' => $v['order_id']])->select();
                $order_list[$k]['order_item_list'] = $order_item_list;

                // 根据订单类型判断订单相关操作
                $order_status = OrderStatus::getOrderCommonStatus();


                // 订单状态
                foreach ($order_status as $k_status => $v_status) {
                    if ($v_status['status_id'] == $v['order_status']) {
                        $order_list[$k]['status_name'] = $v_status['status_name'];
                    }
                }

                $province_name = "";//省
                $city_name = "";//市
                $district_name = "";//区县

                $province = new ProvinceModel();
                $province_info = $province->getInfo(array(
                    "province_id" => $v["receiver_province"]
                ), "*");
                if (count($province_info) > 0) {
                    $province_name = $province_info["province_name"];
                }
                $order_list[$k]['receiver_province_name'] = $province_name;

                $city = new CityModel();
                $city_info = $city->getInfo(array(
                    "city_id" => $v["receiver_city"]
                ), "*");
                if (count($city_info) > 0) {
                    $city_name = $city_info["city_name"];
                }
                $order_list[$k]['receiver_city_name'] = $city_name;

                $district = new DistrictModel();
                $district_info = $district->getInfo(array(
                    "district_id" => $v["receiver_district"]
                ), "*");
                if (count($district_info) > 0) {
                    $district_name = $district_info["district_name"];
                }
                $order_list[$k]['receiver_district_name'] = $district_name;

                //查询来源网点
                if ($v['source_branch'] > 0) {
                    $order_list[$k]['source_branch_name'] = '上海BC一号店';
                } else {
                    $order_list[$k]['source_branch_name'] = 'BC线上';
                }

                //查询分销来源
                if ($v['source_distribution'] > 0) {
                    $member                                     = new Member();
                    $member_detail                              = $member->getMember($v['source_distribution']);
                    $order_list[$k]['source_distribution_name'] = $member_detail['real_name'];
                } else {
                    $order_list[$k]['source_distribution_name'] = '--';
                }
            }
        }
        return $order_list;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::getOrderList()
     */
    public function getOrderList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $order_model = new NsOrderModel();
        // 查询主表
        $order_list = $order_model->pageQuery($page_index, $page_size, $condition, $order, '*');

        if (!empty($order_list['data'])) {
            foreach ($order_list['data'] as $k => $v) {
                // 查询订单项表
                $order_item      = new NsOrderGoodsModel();
                $order_item_list = $order_item->where([
                    'order_id' => $v['order_id']
                ])->select();

                // 通过goods_id查询ns_goods中code
//                foreach ($order_item_list as $key => $val) {
//                    // 查询商品sku表开始
//                    $goods_sku                                = new NsGoodsModel();
//                    $goods_sku_info                           = $goods_sku->getInfo([
//                        'goods_id' => $val['goods_id']
//                    ], 'code,product_barcode,material_code,picture');
//                    $order_item_list[$key]['code']            = $goods_sku_info['code'];
//                    $order_item_list[$key]['product_barcode'] = $goods_sku_info['product_barcode'];
//                    $order_item_list[$key]['material_code']   = $goods_sku_info['material_code'];
//                    $order_item_list[$key]['picture']         = $goods_sku_info['picture'];
//                    // 查询商品sku表结束
//                }


                $province_name = "";
                $city_name     = "";
                $district_name = "";

                $province      = new ProvinceModel();
                $province_info = $province->getInfo(array(
                    "province_id" => $v["receiver_province"]
                ), "*");
                if ($province_info) {
                    $province_name = $province_info["province_name"];
                }
                $order_list['data'][$k]['receiver_province_name'] = $province_name;
                $city                                             = new CityModel();
                $city_info                                        = $city->getInfo(array(
                    "city_id" => $v["receiver_city"]
                ), "*");
                if ($city_info) {
                    $city_name = $city_info["city_name"];
                }
                $order_list['data'][$k]['receiver_city_name'] = $city_name;
                $district                                     = new DistrictModel();
                $district_info                                = $district->getInfo(array(
                    "district_id" => $v["receiver_district"]
                ), "*");
                if ($district_info) {
                    $district_name = $district_info["district_name"];
                }
                $order_list['data'][$k]['receiver_district_name'] = $district_name;
                foreach ($order_item_list as $key_item => $v_item) {
                    //查询订单项商品图片
                    $picture = new AlbumPictureModel();
//                    $order_item_list[$key_item]['picture'] = $picture->get($v_item['goods_picture']);
//                    $goods_picture = $picture->get($v_item['picture']);
                    $goods_picture = $picture->get($v_item['goods_picture']);
                    if (empty($goods_picture)) {
                        $goods_picture = array(
                            'pic_cover' => '',
                            'pic_cover_big' => '',
                            'pic_cover_mid' => '',
                            'pic_cover_small' => '',
                            'pic_cover_micro' => '',
                            "upload_type" => 1,
                            "domain" => ""
                        );
                    }
                    $order_item_list[$key_item]['picture'] = $goods_picture;

                    //查询订单项退款操作状态
                    if ($v_item['refund_status'] != 0) {
                        $order_refund_status = OrderStatus::getRefundStatus();
                        foreach ($order_refund_status as $k_status => $v_status) {

                            if ($v_status['status_id'] == $v_item['refund_status']) {
                                $order_item_list[$key_item]['refund_operation'] = $v_status['refund_operation'];
                                $order_item_list[$key_item]['status_name']      = $v_status['status_name'];
                            }
                        }
                    } else {
                        $order_item_list[$key_item]['refund_operation'] = '';
                        $order_item_list[$key_item]['status_name']      = '';
                    }

                    //查询当前退款记录
                    $refundAccountRecordsmodel = new NsOrderRefundAccountRecordsModel();
                    if($v_item['refund_records_id'] != 0){
                        $refund_account_record = $refundAccountRecordsmodel->get($v_item['refund_records_id']);

                    }else{
                        $refund_account_record = array(
                            'refund_type'=>1,
                            'refund_require_money'=>0,
                            'refund_require_num'=>0
                        );
                    }
                    $order_item_list[$key_item]['refund_account_record'] = $refund_account_record;
                }
                $order_list['data'][$k]['order_item_list'] = $order_item_list;
                $order_list['data'][$k]['operation']       = '';
                // 订单来源名称
                $order_from                                = OrderStatus::getOrderFrom($v['order_from']);
                $order_list['data'][$k]['order_from_name'] = $order_from['type_name'];
                $order_list['data'][$k]['order_from_tag']  = $order_from['tag'];
                $order_list['data'][$k]['pay_type_name']   = OrderStatus::getPayType($v['payment_type']);
                // 根据订单类型判断订单相关操作
                if ($order_list['data'][$k]['order_type'] == 1 || $order_list['data'][$k]['order_type'] == 3) {
                    if ($order_list['data'][$k]['payment_type'] == 6 || $order_list['data'][$k]['shipping_type'] == 2) {
                        $order_status = OrderStatus::getSinceOrderStatus();
                    } else {
                        # todo @陈传烽修改 隐藏确认收货, 发货,修改地址 , 修内容 OrderStatus::getOrderCommonStatus();
                        $order_status = OrderStatus::getOrderCommonStatus();
                    }
                } else if ($order_list['data'][$k]['order_type'] == 4) {
                    $order_status = OrderStatus::getGiftOrderCommonStatus();
                } else {
                    // 虚拟订单
                    $order_status = OrderStatus::getVirtualOrderCommonStatus();
                }

                // 查询订单操作
                foreach ($order_status as $k_status => $v_status) {
                    if ($v_status['status_id'] == $v['order_status']) {
                        $order_list['data'][$k]['operation']        = $v_status['operation'];
                        $order_list['data'][$k]['member_operation'] = $v_status['member_operation'];
                        $order_list['data'][$k]['status_name']      = $v_status['status_name'];
                        $order_list['data'][$k]['is_refund']        = $v_status['is_refund'];
                    }
                }

                //查询来源网点
                if ($v['source_branch'] > 0) {
                    $store                                        = new Store();
                    $store_detail                                 = $store->getStoreDetail($v['source_branch']);
                    $order_list['data'][$k]['source_branch_name'] = $store_detail['store_name'];
                } else {
                    $order_list['data'][$k]['source_branch_name'] = 'BC线上';
                }

                //查询分销来源
                if(!empty($v['source_distribution_name'])){
                    $order_list['data'][$k]['source_distribution_name'] = $v['source_distribution_name'];
                }else{
                    if ($v['source_distribution'] > 0) {
                        $member                                             = new Member();
                        $member_detail                                      = $member->getMember($v['source_distribution']);
                        $order_list['data'][$k]['source_distribution_name'] = $member_detail['real_name'];
                    } else {
                        $order_list['data'][$k]['source_distribution_name'] = '--';
                    }
                }

                $order_payment_model = new NsOrderPaymentModel();
                $orderPaymentDetail  = $order_payment_model->getInfo([
                    'out_trade_no' => $v['out_trade_no']
                ], 'transaction_id');
                if($orderPaymentDetail){
                    $order_list['data'][$k]['transaction_id'] = $orderPaymentDetail['transaction_id'];
                }else{
                    $order_list['data'][$k]['transaction_id'] = '';
                }

            }
        }

        return $order_list;
    }

    //根据外部交易号导入已购订单   订单状态（0：待付款；1：待发货；2：已发货；3：已收货；4：已完成；5：已关闭；-1：退款中）
    public function orderDataImport($out_trade_no)
    {
        $order_model = new NsOrderModel();
        $orderDetail  = $order_model->getInfo([
            'out_trade_no' => $out_trade_no
        ], '*');
        $order_list['order_id'] = $orderDetail['order_no'];
        $order_list['create_time'] = $orderDetail['create_time'];
        $order_list['pay_finish_time'] = $orderDetail['pay_time'];
        $order_list['fee'] = $orderDetail['order_money']*100;
        if($orderDetail['order_status'] == 1){
            $order_list['status'] = 3;
        }else if($orderDetail['order_status'] == 2 || $orderDetail['order_status'] == 3){
            $order_list['status'] = 4;
        }else if($orderDetail['order_status'] == 4){
            $order_list['status'] = 100;
        }else if($orderDetail['order_status'] == -2){
            $order_list['status'] = 5;
        }else{
            return 1;
        }

        // 查询订单项表
        $order_item      = new NsOrderGoodsModel();
        $order_item_list = $order_item->where([
            'order_id' => $orderDetail['order_id']
        ])->select();
        $item_list = [];
        foreach($order_item_list as $key=>$val){
            $goodsModel = new NsGoodsModel();
            $GoodsCategoryModel = new NsGoodsCategoryModel();

            $goodsDetail = $goodsModel->getInfo([
                'goods_id' => $val['goods_id']
            ], '*');

            $categoryDetail = $GoodsCategoryModel->getInfo(['category_id' => $goodsDetail['category_id']], 'is_visible');
            if ($categoryDetail['is_visible'] == 0 && $categoryDetail['category_id'] != 39) {
                continue;
            }

            //排除 Caudalie Aveeno 的产品
            if (stripos($val['goods_name'], 'Caudalie') !== false) {
                continue;
            }

            if (stripos($val['goods_name'], '欧缇丽') !== false) {
                continue;
            }

            if (stripos($val['goods_name'], 'Aveeno') !== false) {
                continue;
            }

            if (stripos($val['goods_name'], '艾惟诺') !== false) {
                continue;
            }

            $category_list = [];
            if($goodsDetail['category_id_1'] != 0){
                $categoryDetail = $GoodsCategoryModel->get($goodsDetail['category_id_1']);
                $category_list[] = $categoryDetail['category_name'];
            }
            if($goodsDetail['category_id_3'] != 0){
                $categoryDetail = $GoodsCategoryModel->get($goodsDetail['category_id_2']);
                $category_list[] = $categoryDetail['category_name'];
            }
            if($goodsDetail['category_id_3'] != 0){
                $categoryDetail = $GoodsCategoryModel->get($goodsDetail['category_id_3']);
                $category_list[] = $categoryDetail['category_name'];
            }
            $item_list[$key]['category_list'] = $category_list;

            $item_list[$key]['item_code'] = $val['goods_id'];
            $item_list[$key]['sku_id'] = $val['sku_id'];
            $item_list[$key]['amount'] = $val['num'];
            $item_list[$key]['total_fee'] = $val['goods_money']*100;

            $picture = new AlbumPictureModel();
            $goods_picture = $picture->get($goodsDetail['picture']);
            $item_list[$key]['thumb_url'] = $goods_picture['pic_cover'];
            $item_list[$key]['title'] = $val['goods_name'];
            $item_list[$key]['unit_price'] = $val['price']*100;
            $item_list[$key]['original_price'] = $goodsDetail['price']*100;
            $item_list[$key]['item_detail_page'] = ['path'=>"/pages/goods/goodsdetail/goodsdetail?goods_id={$goodsDetail['goods_id']}&goods_name={$goodsDetail['goods_name']}"];
        }
        if(empty($item_list)){
            return 1;
        }
        $order_list['ext_info']['product_info'] = ['item_list'=>$item_list];
        $order_list['ext_info']['express_info'] = ['price'=>$orderDetail['shipping_money']*100];
        $order_list['ext_info']['brand_info'] = ['phone'=>'18117533940','contact_detail_page'=>['path'=>"pages/order/orderdetail/orderdetail?id={$orderDetail['order_id']}"]];

        $order_payment_model = new NsOrderPaymentModel();
        $orderPaymentDetail  = $order_payment_model->getInfo([
            'out_trade_no' => $out_trade_no
        ], 'transaction_id');
        if(!empty($orderPaymentDetail['transaction_id'])){
            $order_list['ext_info']['payment_method'] = 1;
            $order_list['trans_id'] = $orderPaymentDetail['transaction_id'];
        }else{
            $order_list['ext_info']['payment_method'] = 0;
        }


        $userService = new User();
        $userDetail = $userService->getUserInfoByUid($orderDetail['buyer_id']);
        $order_list['ext_info']['user_open_id'] = $userDetail['wx_openid'];
        $order_list['ext_info']['order_detail_page'] = ['path'=>"pages/order/orderdetail/orderdetail?id={$orderDetail['order_id']}"];

        $data['order_list'][] = $order_list;
        $data = json_encode($data,JSON_UNESCAPED_UNICODE);
        $config = json_decode(\think\Db::name('sys_config')->where([
            'key' => 'SHOPAPPLET'
        ])->find()['value'], true);
        $access_token = getAccessToken($config['appid'], $config['appsecret']);
        $url   = "https://api.weixin.qq.com/mall/importorder?action=add-order&is_history=0&access_token=" . $access_token;
        $result = curl_post($url, $data);
        return $result;
    }

    public function deteleOrderDataImport($condition)
    {
        $order_model = new NsOrderModel();
        $orderList = $order_model->field('*')->where($condition)->select();
        $config = json_decode(\think\Db::name('sys_config')->where([
            'key' => 'SHOPAPPLET'
        ])->find()['value'], true);
        $access_token = getAccessToken($config['appid'], $config['appsecret']);
        $url   = "https://api.weixin.qq.com/mall/deleteorder?access_token=" . $access_token;
        foreach($orderList as $val){
            $data = [];
            $data['order_id'] = $val['order_no'];
            $user_model = new UserModel();
            $userDetail = $user_model->get($val['buyer_id']);
            if($userDetail){
                $data['user_open_id'] = $userDetail['wx_openid'];
                $data = json_encode($data,JSON_UNESCAPED_UNICODE);
                $result = curl_post($url, $data);
            }else{
                continue;
            }
        }

        return $result;

    }

//    public function update()
//    {
//        $order_goods = new NsOrderGoodsModel();
//        $order_goods_list = $order_goods->where(['fraction'=> ['>',0]])->select();
//        foreach ($order_goods_list as $k => $v) {
//            $data['direct_separation'] = round($v['goods_money']*$v['fraction'],2);
////          $retval = $order_goods->save(['direct_separation'=>$direct_separation], ['order_goods_id' => $v['order_goods_id']]);
//            \think\Db::name('ns_order_goods')->where(['order_goods_id' => $v['order_goods_id']])->update($data);
//        }
//        return 1;
//    }

    public function update()
    {
        $order_model       = new NsOrderModel();
        $order_goods_model = new NsOrderGoodsModel();
        $order_list        = $order_model->where(['distributor_type' => ['>', 1]])->select();
        foreach ($order_list as $k => $v) {
            $order_goods_list = $order_goods_model->where(['order_id' => $v['order_id'], 'fraction' => ['>', 0]])->select();
            foreach ($order_goods_list as $key => $val) {
                if ($v['parent_source_distribution'] == 0) {
                    $data['direct_separation']   = round($val['goods_money'] * $val['fraction'], 2);
                    $data['indirect_separation'] = 0;
                } else {
                    $data['direct_separation']   = round($val['goods_money'] * $val['fraction'] * 0.8, 2);
                    $data['indirect_separation'] = round($val['goods_money'] * $val['fraction'] * 0.2, 2);
                }

                \think\Db::name('ns_order_goods')->where(['order_goods_id' => $val['order_goods_id']])->update($data);
            }
        }
        return 1;
    }

    //kol订单列表
    public function getKolOrderList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $order_model = new NsOrderModel();
        $user_model  = new UserModel();
        // 查询主表
        $order_list = $order_model->getViewList($page_index, $page_size, $condition, $order);
        if (!empty($order_list['data'])) {
            foreach ($order_list['data'] as $k => $v) {
                // 查询订单项表
                $order_item      = new NsOrderGoodsModel();
                $order_item_list = $order_item->where([
                    'order_id' => $v['order_id']
                ])->select();
                // 通过sku_id查询ns_goods_sku中code
                foreach ($order_item_list as $key => $val) {
                    $order_item_list[$key]['all_fraction'] = $val['fraction']; //总分润
                    //计算直接分润比例
                    if ($v['parent_source_distribution'] > 0) {
                        $order_item_list[$key]['fraction'] = $val['fraction'] * 0.8;
                    }

                    // 查询商品sku表开始
                    $goods_sku                                = new NsGoodsModel(); # todo @陈 修改查询sku 条码,等
                    $goods_sku_info                           = $goods_sku->getInfo([
                        'goods_id' => $val['goods_id']
                    ], 'code,product_barcode,material_code');
                    $order_item_list[$key]['code']            = $goods_sku_info['code'];
                    $order_item_list[$key]['product_barcode'] = $goods_sku_info['product_barcode'];
                    $order_item_list[$key]['material_code']   = $goods_sku_info['material_code'];
                    #$order_item_list[$key]['sku_name']        = $goods_sku_info['sku_name'];

                    // 查询商品sku结束

                    $picture       = new AlbumPictureModel();
                    $goods_picture = $picture->get($val['goods_picture']);
                    if (empty($goods_picture)) {
                        $goods_picture = array(
                            'pic_cover' => '',
                            'pic_cover_big' => '',
                            'pic_cover_mid' => '',
                            'pic_cover_small' => '',
                            'pic_cover_micro' => '',
                            "upload_type" => 1,
                            "domain" => ""
                        );
                    }
                    $order_item_list[$key]['picture'] = $goods_picture;

                    if ($val['refund_status'] != 0) {
                        $order_refund_status = OrderStatus::getRefundStatus();
                        foreach ($order_refund_status as $k_status => $v_status) {

                            if ($v_status['status_id'] == $val['refund_status']) {
                                $order_item_list[$key]['refund_operation'] = $v_status['refund_operation'];
                                $order_item_list[$key]['status_name']      = $v_status['status_name'];
                            }
                        }
                    } else {
                        $order_item_list[$key]['refund_operation'] = '';
                        $order_item_list[$key]['status_name']      = '';
                    }
                }
                $order_list['data'][$k]['order_item_list'] = $order_item_list;
                // 根据订单类型判断订单相关操作
                if ($order_list['data'][$k]['order_type'] == 1 || $order_list['data'][$k]['order_type'] == 3) {
                    if ($order_list['data'][$k]['payment_type'] == 6 || $order_list['data'][$k]['shipping_type'] == 2) {
                        $order_status = OrderStatus::getSinceOrderStatus();
                    } else {
                        $order_status = OrderStatus::getOrderCommonStatus();
                    }
                } else if ($order_list['data'][$k]['order_type'] == 4) {
                    $order_status = OrderStatus::getGiftOrderCommonStatus();
                } else {
                    // 虚拟订单
                    $order_status = OrderStatus::getVirtualOrderCommonStatus();
                }

                // 订单来源名称
                $order_from                                = OrderStatus::getOrderFrom($v['order_from']);
                $order_list['data'][$k]['order_from_name'] = $order_from['type_name'];
                $order_list['data'][$k]['order_from_tag']  = $order_from['tag'];
                $order_list['data'][$k]['pay_type_name']   = OrderStatus::getPayType($v['payment_type']);

                // 查询订单操作
                foreach ($order_status as $k_status => $v_status) {
                    if ($v_status['status_id'] == $v['order_status']) {
                        $order_list['data'][$k]['status_name'] = $v_status['status_name'];
                    }
                }

                //来源网点
                if ($v['source_branch'] > 0) {
                    $store                                        = new Store();
                    $store_detail                                 = $store->getStoreDetail($v['source_branch']);
                    $order_list['data'][$k]['source_branch_name'] = $store_detail['store_name'];
                } else {
                    $order_list['data'][$k]['source_branch_name'] = '--';
                }

                //极选师
                if ($v['source_distribution'] > 0) {
                    $member                                             = new Member();
                    $member_detail                                      = $member->getMember($v['source_distribution']);
                    $order_list['data'][$k]['source_distribution_name'] = $member_detail['real_name'];
                } else {
                    $order_list['data'][$k]['source_distribution_name'] = '--';
                }

                //推荐人
                if ($v['parent_source_distribution'] > 0) {
                    $member                                                    = new Member();
                    $member_detail                                             = $member->getMember($v['parent_source_distribution']);
                    $order_list['data'][$k]['parent_source_distribution_name'] = $member_detail['real_name'];
                } else {
                    $order_list['data'][$k]['parent_source_distribution_name'] = '--';
                }

                //邀请人
                if ($v['inviter'] > 0) {
                    $member                                                    = new Member();
                    $member_detail                                             = $member->getMember($v['inviter']);
                    $order_list['data'][$k]['inviter_name'] = $member_detail['real_name'];
                } else {
                    $order_list['data'][$k]['inviter_name'] = '--';
                }

                # 新增购买人信息
                $user_info                            = $user_model->getInfo(['uid' => $v['buyer_id']]);

                if (strpos($user_info['user_headimg'],'https') !== false ){
                    $order_list['data'][$k]['headimg'] = $user_info['user_headimg'];
                }else{
                    $order_list['data'][$k]['headimg'] = 'https://'.$_SERVER['HTTP_HOST'].'/'.$user_info['user_headimg'];
                }

                $order_list['data'][$k]['buyer_name'] = $user_info['nick_name'];
            }
        }


        return $order_list;
    }

    //极选师订单数据excel导出
    public function getKolExportOrderList($condition = '', $order = '')
    {
        $goods_sku = new NsGoodsModel(); //商品
        $order_list = Db::table('ns_order')->where($condition)->order($order)->select();
        if (!empty($order_list)) {
            foreach ($order_list as $k => $v) {
                // 查询订单项表
                $order_item_list = Db::table('ns_order_goods')->where(['order_id' => $v['order_id']])->select();
                // 通过sku_id查询ns_goods_sku中code
                foreach ($order_item_list as $key => $val) {
                    // 查询商品sku表开始
                    if (isset($this->goodsList['good_sku_info_' . $val['goods_id']])) {
                        $goods_sku_info = $this->goodsList['good_sku_info_' . $val['goods_id']];
                    } else {
                        $goods_sku_info = $goods_sku->getInfo([
                            'goods_id' => $val['goods_id']
                        ], 'code,product_barcode,material_code');

                        $this->goodsList['good_sku_info_' . $val['goods_id']] = $goods_sku_info;
                    }
                    $order_item_list[$key]['code']            = $goods_sku_info['code'];
                    $order_item_list[$key]['product_barcode'] = $goods_sku_info['product_barcode'];
                    $order_item_list[$key]['material_code']   = $goods_sku_info['material_code'];
                    // 查询商品sku结束
                }
                $order_list[$k]['order_item_list'] = $order_item_list;

                // 根据订单类型判断订单相关操作
                if ($order_list[$k]['order_type'] == 1 || $order_list[$k]['order_type'] == 3) {
                    if ($order_list[$k]['payment_type'] == 6 || $order_list[$k]['shipping_type'] == 2) {
                        $order_status = OrderStatus::getSinceOrderStatus();
                    } else {
                        $order_status = OrderStatus::getOrderCommonStatus();
                    }
                } else if ($order_list[$k]['order_type'] == 4) {
                    // 礼品订单
                    $order_status = OrderStatus::getGiftOrderCommonStatus();
                } else if ($order_list[$k]['order_type'] == 5) {
                    // 会员订单
                    $order_status = OrderStatus::getVipOrderCommonStatus();
                } else {
                    // 虚拟订单
                    $order_status = OrderStatus::getVirtualOrderCommonStatus();
                }

                // 订单状态
                foreach ($order_status as $k_status => $v_status) {
                    if ($v_status['status_id'] == $v['order_status']) {
                        $order_list[$k]['status_name'] = $v_status['status_name'];
                    }
                }

                //来源网点
                if ($v['source_branch'] > 0) {
                    $store                                        = new Store();
                    $store_detail                                 = $store->getStoreDetail($v['source_branch']);
                    $order_list[$k]['source_branch_name'] = $store_detail['store_name'];
                } else {
                    $order_list[$k]['source_branch_name'] = '--';
                }

                //极选师
                if ($v['source_distribution'] > 0) {
                    $member                                             = new Member();
                    $member_detail                                      = $member->getMember($v['source_distribution']);
                    $order_list[$k]['source_distribution_name'] = $member_detail['real_name'];
                } else {
                    $order_list[$k]['source_distribution_name'] = '--';
                }

                //推荐人
                if ($v['parent_source_distribution'] > 0) {
                    $member                                                    = new Member();
                    $member_detail                                             = $member->getMember($v['parent_source_distribution']);
                    $order_list[$k]['parent_source_distribution_name'] = $member_detail['real_name'];
                } else {
                    $order_list[$k]['parent_source_distribution_name'] = '--';
                }
            }
        }
        return $order_list;
    }

    //kol分润统计
    public function getKolFractionSum()
    {
        //累计获得分润
//        $condition_one['no.source_distribution'] = $this->uid;
//        $condition_one['no.distributor_type']    = ['>', 1];
//        $condition_one['no.order_status']        = 4;
//        $condition_one['nog.refund_status']      = ['neq', 5];

        //待结算分润
        $condition_two['no.source_distribution'] = $this->uid;
        $condition_two['no.distributor_type']    = ['>', 1];
        $condition_two['no.order_status']        = [
            'in',
            [
                1,
                2,
                3,
                -1
            ]
        ];
        $condition_two['nog.refund_status'] = ['neq', 5];

        //待出账分润
//        $condition_three['no.source_distribution'] = $this->uid;
//        $condition_three['no.distributor_type']    = ['>', 1];
//        $condition_three['no.order_status']        = 4;
//        $condition_three['no.is_checkout']         = 0;
//        $condition_three['nog.refund_status']      = ['neq', 5];

        //已出账分润
//        $condition_four['no.source_distribution'] = $this->uid;
//        $condition_four['no.distributor_type']    = ['>', 1];
//        $condition_four['no.order_status']        = 4;
//        $condition_four['no.is_checkout']         = 1;
//        $condition_four['nog.refund_status']      = ['neq', 5];

        //绑定会员总数
        $condition_member_member['nm.source_distribution'] = $this->uid;

        //累计成交订单笔数
        $condition_order_number['no.source_distribution'] = $this->uid;
        $condition_order_number['no.distributor_type']    = ['>', 1];
        $condition_order_number['no.order_status']        = array(
            'in',
            [
                1,
                2,
                3,
                4,
                -1

            ]
        );

        //累计成交金额
        $condition_pay_money['no.source_distribution'] = $this->uid;
        $condition_pay_money['distributor_type']       = ['>', 1];
        $condition_pay_money['no.order_status']        = [
            'in',
            [
                1,
                2,
                3,
                4,
                -1
            ]
        ];
        $condition_pay_money['nog.refund_status']      = ['neq', 5];

        $condition_quarter_pay_money['no.source_distribution'] = $this->uid;
        $condition_quarter_pay_money['distributor_type']       = ['>', 1];
        $condition_quarter_pay_money['no.order_status']        = [
            'in',
            [
                1,
                2,
                3,
                4,
                -1
            ]
        ];
        $condition_quarter_pay_money['nog.refund_status']      = ['neq', 5];

        //本季度成交金额
        $year  = date('Y', time());
        $month = date('m', time());
        if ($month == 1 || $month == 2 || $month == 3) {
            $quarter = 1;
        } else if ($month == 4 || $month == 5 || $month == 6) {
            $quarter = 2;
        } else if ($month == 7 || $month == 8 || $month == 9) {
            $quarter = 3;
        } else {
            $quarter = 4;
        }

        //季度的时间处理
        if ($quarter == 1) {
            $days       = cal_days_in_month(CAL_GREGORIAN, 3, $year);
            $start_date = getTimeTurnTimeStamp($year . '-1-01');
            $end_date   = getTimeTurnTimeStamp($year . '-3-' . $days) + 86400;
        } else if ($quarter == 2) {
            $days       = cal_days_in_month(CAL_GREGORIAN, 6, $year);
            $start_date = getTimeTurnTimeStamp($year . '-4-01');
            $end_date   = getTimeTurnTimeStamp($year . '-6-' . $days) + 86400;
        } else if ($quarter == 3) {
            $days       = cal_days_in_month(CAL_GREGORIAN, 9, $year);
            $start_date = getTimeTurnTimeStamp($year . '-7-01');
            $end_date   = getTimeTurnTimeStamp($year . '-9-' . $days) + 86400;
        } else {
            $days       = cal_days_in_month(CAL_GREGORIAN, 12, $year);
            $start_date = getTimeTurnTimeStamp($year . '-10-01');
            $end_date   = getTimeTurnTimeStamp($year . '-12-' . $days) + 86400;
        }

        $condition_quarter_pay_money["no.finish_time"] = [
            [
                ">",
                $start_date
            ],
            [
                "<",
                $end_date
            ]
        ];

        $distributorSeparationRecords = new BcDistributorAccountRecordsModel();
        $order_model             = new NsOrderModel();
        $member                  = new NsMemberModel();
//        $sum_one                 = $order_model->orderFractionSum($condition_one); //累计获得分润
//        $sum_two                 = $order_model->orderFractionSum($condition_two); //待结算分润
//        $sum_three               = $order_model->orderFractionSum($condition_three); //待出账分润
//        $sum_four                = $order_model->orderFractionSum($condition_four); //已出账分润
        $sum_one = $distributorSeparationRecords->moneySum(['uid'=>$this->uid,]); //累计获得分润
        $sum_two = $order_model->orderFractionSum($condition_two); //待结算分润
        $sum_three = 0; //待出账分润
        $sum_four = $distributorSeparationRecords->moneySum(['uid'=>$this->uid,'account_type'=>1,'from_type'=>['in',[1,2]]]); //已出账分润
        $member_member_count = $member->memberNumberCount($condition_member_member); //绑定会员总数
        $order_number_count = $order_model->orderNumberCount($condition_order_number); //累计成交订单笔数
        $goods_money_sum = $order_model->goodsMoneySum($condition_pay_money); //累计成交金额
        $quarter_goods_money_sum = $order_model->goodsMoneySum($condition_quarter_pay_money); //本季度完成金额

        //星级和奖金
        $memberInfo = $member->getInfo(['uid' => $this->uid], '*');
        if ($memberInfo['distributor_type'] == 2 || $memberInfo['distributor_type'] == 3) {
            $where['star_type'] = 1;
        } else {
            $where['star_type'] = 2;
        }
        $starMOdel = new BcDistributorStarModel();
        $starList  = $starMOdel->getQuery($where, '*', 'star_standard desc');

        $star_grade          = 0; //极选师等级
        $star_standard_start = 0; //极选师省级标准
        $star_name_start     = ''; //极选师星级名称
        $star_reward         = ''; //星级奖励比例
        $bonus               = 0; //星级额外奖励金额
        $other_money         = $quarter_goods_money_sum;
        foreach ($starList as $key => $val) {
            if ($quarter_goods_money_sum > $val['star_standard'] && empty($star_name_start)) {
                $star_name_start     = $val['star_name'];
                $star_reward         = $val['star_reward'];
                $star_grade          = $val['star_grade'];
                $star_standard_start = $val['star_standard'];
            }

            if ($quarter_goods_money_sum > $val['star_standard']) {
                $bonus += ($other_money - $val['star_standard']) * $val['star_reward'];
                $other_money = $val['star_standard'];
            }
        }

        $where['star_grade'] = $star_grade + 1;
        $starInfo            = $starMOdel->getInfo($where, '*');
        $star_standard_end   = $starInfo['star_standard'];
        $star_name_end       = $starInfo['star_name']; //极选师预达星级名称
        $standard_rate       = ($quarter_goods_money_sum - $star_standard_start) / ($star_standard_end - $star_standard_start);//达标率

        $sum = [
            'sum_one' => $sum_one, //累计获得分润
            'sum_two' => $sum_two, //待结算分润
            'sum_three' => $sum_three, //待出账分润
            'sum_four' => $sum_four, //已出账分润
            'member_count' => $member_member_count, //绑定会员总数
            'order_number_count' => $order_number_count, //累计成交订单笔数
            'sum_five' => $goods_money_sum, //累计成交金额
            'goods_money_sum' => $goods_money_sum, //累计成交金额
            'quarter_goods_money_sum' => $quarter_goods_money_sum, //本季度完成金额
            'standard_rate' => $standard_rate, //达标率
            'star_name_start' => $star_name_start,//极选师星级名称
            'star_name_end' => $star_name_end,//极选师预达星级名称
            'star_reward' => $star_reward, //星级奖励比例
            'bonus' => $bonus //星级额外奖励金额
        ];
        return $sum;
    }

    //kol分润统计
    public function getKolAchievementStatistics()
    {
        $distributorSeparationRecords = new BcDistributorAccountRecordsModel();
        $order_model = new NsOrderModel();
        $member = new NsMemberModel();
        $distributor = new BcDistributorModel();

        //累计赚取金额
        $separation_sum = $distributorSeparationRecords->moneySum(['uid'=>$this->uid,'account_type'=>1,'from_type'=>['in',[1,2]]]);

        //待结算直接分润
        $condition_unsettled['no.source_distribution'] = $this->uid;
        $condition_unsettled['no.distributor_type']    = ['>', 1];
        $condition_unsettled['no.order_status']        = [
            'in',
            [
                1,
                2,
                3,
                -1
            ]
        ];
        $unsettled_separation_sum = $order_model->orderFractionSum($condition_unsettled);

        //本季与时间处理
        $year  = date('Y', time());
        $month = date('m', time());
        if ($month == 1 || $month == 2 || $month == 3) {
            $quarter = 1;
        } else if ($month == 4 || $month == 5 || $month == 6) {
            $quarter = 2;
        } else if ($month == 7 || $month == 8 || $month == 9) {
            $quarter = 3;
        } else {
            $quarter = 4;
        }
        if ($quarter == 1) {
            $days       = cal_days_in_month(CAL_GREGORIAN, 3, $year);
            $start_date = getTimeTurnTimeStamp($year . '-1-01');
            $end_date   = getTimeTurnTimeStamp($year . '-3-' . $days) + 86400;
        } else if ($quarter == 2) {
            $days       = cal_days_in_month(CAL_GREGORIAN, 6, $year);
            $start_date = getTimeTurnTimeStamp($year . '-4-01');
            $end_date   = getTimeTurnTimeStamp($year . '-6-' . $days) + 86400;
        } else if ($quarter == 3) {
            $days       = cal_days_in_month(CAL_GREGORIAN, 9, $year);
            $start_date = getTimeTurnTimeStamp($year . '-7-01');
            $end_date   = getTimeTurnTimeStamp($year . '-9-' . $days) + 86400;
        } else {
            $days       = cal_days_in_month(CAL_GREGORIAN, 12, $year);
            $start_date = getTimeTurnTimeStamp($year . '-10-01');
            $end_date   = getTimeTurnTimeStamp($year . '-12-' . $days) + 86400;
        }

        //本季度完成金额
        $condition_quarter_pay_money['no.source_distribution'] = $this->uid;
        $condition_quarter_pay_money['distributor_type']       = ['>', 1];
        $condition_quarter_pay_money['no.order_status']        = [
            'in',
            [
                1,
                2,
                3,
                4,
                -1
            ]
        ];
        $condition_quarter_pay_money["no.finish_time"] = [
            [
                ">",
                $start_date
            ],
            [
                "<",
                $end_date
            ]
        ];
        $quarter_goods_money_sum = $order_model->goodsMoneySum($condition_quarter_pay_money);

        //本季度完成订单笔数
        $condition_quarter_order_number['no.source_distribution'] = $this->uid;
        $condition_quarter_order_number['no.distributor_type']    = ['>', 1];
        $condition_quarter_order_number['no.order_status']        = array(
            'in',
            [
                1,
                2,
                3,
                4,
                -1
            ]
        );
        $condition_quarter_order_number["no.finish_time"] = [
            [
                ">",
                $start_date
            ],
            [
                "<",
                $end_date
            ]
        ];
        $quarter_order_number_count = $order_model->orderNumberCount($condition_quarter_order_number);

        //星级
        $memberInfo = $member->getInfo(['uid' => $this->uid], '*');
        if ($memberInfo['distributor_type'] == 2 || $memberInfo['distributor_type'] == 3) {
            $where['star_type'] = 1;
        } else {
            $where['star_type'] = 2;
        }
        $starMOdel = new BcDistributorStarModel();
        $starList  = $starMOdel->getQuery($where, '*', 'star_standard desc');

        $star_grade = 0; //极选师等级
        foreach ($starList as $key => $val) {
            if ($quarter_goods_money_sum > $val['star_standard']) {
                $star_grade = $val['star_grade'];break;
            }
        }

        $member_count = $member->memberNumberCount(['nm.source_distribution'=>$this->uid]); //绑定会员总数

        //累计成交订单笔数
        $condition_order_number['no.source_distribution'] = $this->uid;
        $condition_order_number['no.distributor_type']    = ['>', 1];
        $condition_order_number['no.order_status']        = array(
            'in',
            [
                1,
                2,
                3,
                4,
                -1
            ]
        );
        $order_number_count = $order_model->orderNumberCount($condition_order_number);

        //累计成交金额
        $condition_pay_money['no.source_distribution'] = $this->uid;
        $condition_pay_money['distributor_type']       = ['>', 1];
        $condition_pay_money['no.order_status']        = [
            'in',
            [
                1,
                2,
                3,
                4,
                -1
            ]
        ];
        $goods_money_sum = $order_model->goodsMoneySum($condition_pay_money); //累计成交金额


        $distributorInfo = $distributor->getInfo(['uid' => $this->uid], '*');//极选师信息
        $sum = [
            'separation_sum' => $separation_sum, //累计赚取
            'unsettled_separation_sum' => $unsettled_separation_sum, //待结算直接分润
            'quarter_goods_money_sum' => $quarter_goods_money_sum, //本季度完成金额
            'quarter_order_number_count' => $quarter_order_number_count, //本季度完成订单笔数
            'star_grade'=>$star_grade, //极选师星级
            'member_count' => $member_count, //绑定会员总数
            'order_number_count' => $order_number_count, //累计成交订单笔数
            'goods_money_sum' => $goods_money_sum, //累计成交金额
            'account_sum' => round($distributorInfo['balance']+$distributorInfo['bonus'],2) //账户总余额
        ];
        return $sum;
    }

    /**
     * 获取当前会员的礼品订单列表（我送出的）
     */
    public function getGiftOrderList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $order_model = new NsOrderModel();
        // 查询主表
        $order_list = $order_model->pageQuery($page_index, $page_size, $condition, $order, '*');

        if (!empty($order_list['data'])) {
            foreach ($order_list['data'] as $k => $v) {
                // 查询订单项表
                $order_item      = new NsOrderGoodsModel();
                $order_item_list = $order_item->where([
                    'order_id' => $v['order_id']
                ])->select();

                // 通过sku_id查询ns_goods_sku中code
                foreach ($order_item_list as $key => $val) {
                    // 查询商品sku表开始
                    $goods_sku = new NsGoodsModel(); # todo @陈 修改查询sku 条码,等

                    $goods_sku_info                           = $goods_sku->getInfo([
                        'goods_id' => $val['goods_id']
                    ], 'code,product_barcode,material_code,picture');
                    $order_item_list[$key]['code']            = $goods_sku_info['code'];
                    $order_item_list[$key]['product_barcode'] = $goods_sku_info['product_barcode'];
                    $order_item_list[$key]['material_code']   = $goods_sku_info['material_code'];
                    $order_item_list[$key]['picture']         = $goods_sku_info['picture'];
                    // 查询商品sku结束
                }

                foreach ($order_item_list as $key_item => $v_item) {

                    $picture = new AlbumPictureModel();
                    // $order_item_list[$key_item]['picture'] = $picture->get($v_item['goods_picture']);
                    #todo @陈 图片显示不了 修改
//                    $goods_pictur e = $picture->get($v_item['goods_picture']);
                    $goods_picture = $picture->get($v_item['picture']);
                    if (empty($goods_picture)) {
                        $goods_picture = array(
                            'pic_cover' => '',
                            'pic_cover_big' => '',
                            'pic_cover_mid' => '',
                            'pic_cover_small' => '',
                            'pic_cover_micro' => '',
                            "upload_type" => 1,
                            "domain" => ""
                        );
                    }
                    $order_item_list[$key_item]['picture'] = $goods_picture;
                }
                $order_list['data'][$k]['order_item_list'] = $order_item_list;
                // 订单来源名称
                $order_from                                = OrderStatus::getOrderFrom($v['order_from']);
                $order_list['data'][$k]['order_from_name'] = $order_from['type_name'];
                $order_list['data'][$k]['order_from_tag']  = $order_from['tag'];
                $order_list['data'][$k]['pay_type_name']   = OrderStatus::getPayType($v['payment_type']);
            }
        }

        return $order_list;
    }

    /**
     * 获取当前会员的礼品订单列表（我领取的）
     */
    public function getGiftOrderGetList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $giftGiveGet_model = new NsGiftGiveGetModel();
        $order_list        = $giftGiveGet_model->getGiftOrderGet($page_index, $page_size, $condition, $order);
        if (!empty($order_list['data'])) {
            foreach ($order_list['data'] as $k => $v) {
                // 查询订单项表
                $order_item      = new NsOrderGoodsModel();
                $order_item_list = $order_item->where([
                    'order_id' => $v['order_id']
                ])->select();

                // 通过sku_id查询ns_goods_sku中code
                foreach ($order_item_list as $key => $val) {
                    // 查询商品sku表开始
                    $goods_sku = new NsGoodsModel();

                    $goods_sku_info                           = $goods_sku->getInfo([
                        'goods_id' => $val['goods_id']
                    ], 'code,product_barcode,material_code,picture');
                    $order_item_list[$key]['code']            = $goods_sku_info['code'];
                    $order_item_list[$key]['product_barcode'] = $goods_sku_info['product_barcode'];
                    $order_item_list[$key]['material_code']   = $goods_sku_info['material_code'];
                    $order_item_list[$key]['picture']         = $goods_sku_info['picture'];
                    // 查询商品sku结束
                }

                foreach ($order_item_list as $key_item => $v_item) {

                    $picture = new AlbumPictureModel();
                    // $order_item_list[$key_item]['picture'] = $picture->get($v_item['goods_picture']);
//                    $goods_picture = $picture->get($v_item['goods_picture']);
                    $goods_picture = $picture->get($v_item['picture']);
                    if (empty($goods_picture)) {
                        $goods_picture = array(
                            'pic_cover' => '',
                            'pic_cover_big' => '',
                            'pic_cover_mid' => '',
                            'pic_cover_small' => '',
                            'pic_cover_micro' => '',
                            "upload_type" => 1,
                            "domain" => ""
                        );
                    }
                    $order_item_list[$key_item]['picture'] = $goods_picture;
                }
                $order_list['data'][$k]['order_item_list'] = $order_item_list;
                // 订单来源名称
                $order_from                                = OrderStatus::getOrderFrom($v['order_from']);
                $order_list['data'][$k]['order_from_name'] = $order_from['type_name'];
                $order_list['data'][$k]['order_from_tag']  = $order_from['tag'];
                $order_list['data'][$k]['pay_type_name']   = OrderStatus::getPayType($v['payment_type']);
            }
        }
        return $order_list;

    }

    public function giftGet($order_id, $action_status)
    {
        $order_model = new NsOrderModel();
        $order       = new OrderBusiness();
        $member      = new Member();
        $order_model->startTrans();
        try {
            $address = $member->getDefaultExpressAddress();
            $order->addAdressInOrder($order_id, $address['mobile'], $address['province'], $address['city'], $address['district'], $address['address'], $address['zip_code'], $address['consigner']);
            $action_id = $order->addGiftGaveGet($order_id, $this->uid, $action_status);
            $order_model->commit();
            return $action_id;
        } catch (\Exception $e) {
            $order_model->rollback();
            return $e->getMessage();
        }
    }

    /*
     * 订单创建（实物商品）
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderCreate()
     */
    public function orderCreate($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $point, $coupon_id, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $tx_type, $is_inside, $card_name, $card_no, $source_branch, $source_distribution, $parent_source_distribution, $inviter, $from_type, $distributor_type, $traffic_acquisition_source, $count_money, $coin = 0, $fixed_telephone = "")
    {
        $order = new OrderBusiness();
        if ($pay_type == 4) {
            // 如果是货到付款 判断当前地址是否符合货到付款的地址
            $address = new Address();
            $result  = $address->getDistributionAreaIsUser(0, $receiver_province, $receiver_city, $receiver_district);
            if (!$result) {
                return ORDER_CASH_DELIVERY;
            }
        }
        if ($from_type == 1) {
            $retval = $order->shareOrderCreate($order_type, $out_trade_no, $pay_type, $shipping_type, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $goods_sku_list, $shipping_company_id, $tx_type, $card_name, $card_no, $source_branch, $source_distribution, $parent_source_distribution, $inviter, $distributor_type, $count_money, $coin, $fixed_telephone);
        } else {
            $retval = $order->orderCreate($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $point, $coupon_id, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $tx_type, $is_inside, $card_name, $card_no, $source_branch, $source_distribution, $parent_source_distribution, $inviter, $distributor_type, $traffic_acquisition_source, $coin, $fixed_telephone);
        }

        runhook("Notify", "orderCreate", array(
            "order_id" => $retval
        ));
        // 针对特殊订单执行支付处理
        if ($retval > 0) {
            hook('orderCreateSuccess', [
                'order_id' => $retval
            ]);
            // 货到付款
            if ($pay_type == 4) {
                $this->orderOnLinePay($out_trade_no, 4);
                $this->pushOrderIdByOutTradeNo($out_trade_no); //订单推送给OMS
                $this->orderDataImport($out_trade_no); //订单推送给小程序
            } else {
                $order_model = new NsOrderModel();
                $order_info  = $order_model->getInfo([
                    'order_id' => $retval
                ], '*');
                if (!empty($order_info)) {
                    if ($order_info['user_platform_money'] != 0) {
                        if ($order_info['pay_money'] == 0) {
                            $this->orderOnLinePay($out_trade_no, 5);
                            $this->pushOrderIdByOutTradeNo($out_trade_no); //订单推送给OMS
                            $this->orderDataImport($out_trade_no); //订单推送给小程序
                        }
                    } else {

                        if ($order_info['pay_money'] == 0) {
                            $this->orderOnLinePay($out_trade_no, 1); // 默认微信支付
                            $this->pushOrderIdByOutTradeNo($out_trade_no); //订单推送给OMS
                            $this->orderDataImport($out_trade_no); //订单推送给小程序
                        }
                    }
                }
            }
        }

        return $retval;
        // TODO Auto-generated method stub
    }

    //付费会员领取免费商品
    public function vipGetGoodsOrder($order_type, $out_trade_no, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $goods_id, $source_branch)
    {
        $order  = new OrderBusiness();
        $retval = $order->vipGetGoodsOrder($order_type, $out_trade_no, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $goods_id, $source_branch);
        runhook("Notify", "orderCreate", array(
            "order_id" => $retval
        ));

        // 针对特殊订单执行支付处理
        if ($retval > 0) {
            hook('orderCreateSuccess', [
                'order_id' => $retval
            ]);

            $order_model = new NsOrderModel();
            $order_info  = $order_model->getInfo([
                'order_id' => $retval
            ], '*');
            if (!empty($order_info)) {
                $this->orderOnLinePay($out_trade_no, 1); // 默认微信支付
                $this->pushOrderIdByOutTradeNo($out_trade_no); //订单推送
            }
        }

        return $retval;
        // TODO Auto-generated method stub
    }

    //付费会员领取免费礼品
    public function vipGetGiftOrder($order_type, $out_trade_no, $goods_id, $source_branch)
    {
        $order  = new OrderBusiness();
        $retval = $order->vipGetGiftOrder($order_type, $out_trade_no, $goods_id, $source_branch);
        runhook("Notify", "orderCreate", array(
            "order_id" => $retval
        ));

        // 针对特殊订单执行支付处理
        if ($retval > 0) {
            hook('orderCreateSuccess', [
                'order_id' => $retval
            ]);

            $order_model = new NsOrderModel();
            $order_info  = $order_model->getInfo([
                'order_id' => $retval
            ], '*');
            if (!empty($order_info)) {
                $this->orderOnLinePay($out_trade_no, 1); // 默认微信支付
                return $order_info['order_no'];
            }
        } else {
            return $retval;
        }
        // TODO Auto-generated method stub
    }

    /*
     * 订单创建（礼品）
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderCreate()
     */
    public function giftOrderCreate($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $point, $coupon_id, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $tx_type, $card_name, $card_no, $source_branch, $coin = 0)
    {
        $order = new OrderBusiness();

        $retval = $order->giftOrderCreate($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $point, $coupon_id, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $tx_type, $card_name, $card_no, $source_branch, $coin);
        runhook("Notify", "orderCreate", array(
            "order_id" => $retval
        ));
        // 针对特殊订单执行支付处理
        if ($retval > 0) {
            hook('orderCreateSuccess', [
                'order_id' => $retval
            ]);
            $order_model = new NsOrderModel();
            $order_info  = $order_model->getInfo([
                'order_id' => $retval
            ], '*');
            if (!empty($order_info)) {
                if ($order_info['user_platform_money'] != 0) {
                    if ($order_info['pay_money'] == 0) {
                        $this->orderOnLinePay($out_trade_no, 5);
                    }
                } else {

                    if ($order_info['pay_money'] == 0) {
                        $this->orderOnLinePay($out_trade_no, 1); // 默认微信支付
                    }
                }
            }
        }

        return $retval;
    }

    /*
     * 订单创建（会员）
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderCreateVip()
     */
    public function orderCreateVip($order_type, $out_trade_no, $goods_id, $card_name, $sex, $user_tel, $birthday, $source_branch)
    {
        $order  = new OrderBusiness();
        $retval = $order->orderCreateVip($order_type, $out_trade_no, $goods_id, $card_name, $sex, $user_tel, $birthday, $source_branch);
        runhook("Notify", "orderCreate", array(
            "order_id" => $retval
        ));
        if ($retval > 0) {
            hook('orderCreateSuccess', [
                'order_id' => $retval
            ]);
        }
        return $retval;
    }

    /*
     * 订单创建（虚拟商品）
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderCreate()
     */
    public function orderCreateVirtual($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $point, $coupon_id, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $user_telephone, $coin = 0)
    {
        $order  = new OrderBusiness();
        $retval = $order->orderCreateVirtual($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $point, $coupon_id, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $user_telephone, $coin);
        runhook("Notify", "orderCreate", array(
            "order_id" => $retval
        ));
        // 针对特殊订单执行支付处理
        if ($retval > 0) {
            hook('orderCreateSuccess', [
                'order_id' => $retval
            ]);
            $order_model = new NsOrderModel();
            $order_info  = $order_model->getInfo([
                'order_id' => $retval
            ], '*');
            if (!empty($order_info)) {
                if ($order_info['user_platform_money'] != 0) {
                    if ($order_info['pay_money'] == 0) {
                        $this->orderOnLinePay($out_trade_no, 5);
                    }
                } else {

                    if ($order_info['pay_money'] == 0) {
                        $this->orderOnLinePay($out_trade_no, 1); // 默认微信支付
                    }
                }
            }
        }

        return $retval;
    }

    /**
     * 创建订单（组合套餐）
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
     * @param unknown $user_money
     * @param unknown $goods_sku_list
     * @param unknown $platform_money
     * @param unknown $pick_up_id
     * @param unknown $shipping_company_id
     * @param number $coin
     * @param string $fixed_telephone
     * @param unknown $combo_package_id
     * @param unknown $buy_num
     */
    public function orderCreateComboPackage($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $point, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $coin = 0, $fixed_telephone = "", $combo_package_id, $buy_num)
    {
        $order = new OrderBusiness();
        if ($pay_type == 4) {
            // 如果是货到付款 判断当前地址是否符合货到付款的地址
            $address = new Address();
            $result  = $address->getDistributionAreaIsUser(0, $receiver_province, $receiver_city, $receiver_district);
            if (!$result) {
                return ORDER_CASH_DELIVERY;
            }
        }
        $retval = $order->orderCreateComboPackage($order_type, $out_trade_no, $pay_type, $shipping_type, $order_from, $buyer_ip, $buyer_message, $buyer_invoice, $shipping_time, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $point, $user_money, $goods_sku_list, $platform_money, $pick_up_id, $shipping_company_id, $coin, $fixed_telephone = "", $combo_package_id, $buy_num);
        runhook("Notify", "orderCreate", array(
            "order_id" => $retval
        ));
        // 针对特殊订单执行支付处理
        if ($retval > 0) {
            hook('orderCreateSuccess', [
                'order_id' => $retval
            ]);
            // 货到付款
            if ($pay_type == 4) {
                $this->orderOnLinePay($out_trade_no, 4);
            } else {
                $order_model = new NsOrderModel();
                $order_info  = $order_model->getInfo([
                    'order_id' => $retval
                ], '*');
                if (!empty($order_info)) {
                    if ($order_info['user_platform_money'] != 0) {
                        if ($order_info['pay_money'] == 0) {
                            $this->orderOnLinePay($out_trade_no, 5);
                        }
                    } else {

                        if ($order_info['pay_money'] == 0) {
                            $this->orderOnLinePay($out_trade_no, 1); // 默认微信支付
                        }
                    }
                }
            }
        }

        return $retval;
        // TODO Auto-generated method stub
    }

    /**
     * 查询订单中的商品是否有限购，如果有限购，则查询是否有购买记录等
     */
    public function getGoodsPurchaseRestrictionForOrder($goods_sku_list)
    {
        $array              = array();
        $res_array          = array();
        $messages           = "";
        $ns_goods_sku_model = new NsGoodsSkuModel();
        if (!empty($goods_sku_list)) {

            $goods_sku_list_array = explode(",", $goods_sku_list);
            foreach ($goods_sku_list_array as $k => $v) {
                $sku_data  = explode(":", $v);
                $sku_id    = $sku_data[0];
                $sku_count = $sku_data[1];

                $goods_sku_info = $ns_goods_sku_model->getInfo([
                    "sku_id" => $sku_id
                ], "goods_id");

                if (!empty($goods_sku_info['goods_id'])) {
                    array_push($array, $goods_sku_info['goods_id'] . ":" . $sku_count . ":" . $sku_id);
                }
            }
        }
        if (count($array) > 0) {
            $goods_service  = new GoodsService();
            $ns_goods_model = new NsGoodsModel();
            foreach ($array as $k => $v) {
                $data              = explode(":", $array[$k]);
                $goods_id          = $data[0];
                $num               = $data[1];
                $sku_id            = $data[2];
                $goods_name        = $ns_goods_model->getInfo([
                    'shop_id' => $this->instance_id,
                    "goods_id" => $goods_id
                ], "goods_name");
                $sku_name          = $ns_goods_sku_model->getInfo([
                    'sku_id' => $sku_id
                ], "sku_name");
                $res               = $goods_service->getGoodsPurchaseRestrictionForCurrentUser($goods_id, $num, "order");
                $res['goods_name'] = $goods_name['goods_name'] . $sku_name['sku_name'];
                array_push($res_array, $res);
            }
        }
        if (count($res_array) > 0) {
            foreach ($res_array as $k => $v) {
                if ($res_array[$k]['code'] == 0) {
                    $messages = "您购买的商品“" . $res_array[$k]['goods_name'] . "”限购" . $res_array[$k]['value'] . "件";
                    break;
                }
            }
        }
        return $messages;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IOrder::getOrderTradeNo()
     */
    public function getOrderTradeNo()
    {
        $order = new OrderBusiness();
        $no    = $order->createOutTradeNo();
        return $no;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderDelivery()
     */
    public function orderDelivery($order_id, $order_goods_id_array, $express_name, $shipping_type, $express_company_id, $express_no)
    {
        $order_express = new OrderExpress();
        $retval        = $order_express->delivey($order_id, $order_goods_id_array, $express_name, $shipping_type, $express_company_id, $express_no);
        if ($retval) {
            $extra = [
                'express_name' => $express_name,
                'express_no' => $express_no
            ];
            sendTemplate($order_id, $extra);
            $params = [
                'order_id' => $order_id,
                'order_goods_id_array' => $order_goods_id_array,
                'express_name' => $express_name,
                'shipping_type' => $shipping_type,
                'express_company_id' => $express_company_id,
                'express_no' => $express_no
            ];
            hook('orderDeliverySuccess', $params);
        }
        return $retval;
    }

    //OMS发货（需要物流）
    public function orderDeliveryOms($order_id, $order_goods_id_array, $express_name, $shipping_type, $express_company_id, $express_no)
    {
        $order_express = new OrderExpress();
        $retval        = $order_express->deliveyOms($order_id, $order_goods_id_array, $express_name, $shipping_type, $express_company_id, $express_no);

        if ($retval) {
            $extra = [
                'express_name' => $express_name,
                'express_no' => $express_no
            ];
            sendTemplate($order_id, $extra);
            $params = [
                'order_id' => $order_id,
                'order_goods_id_array' => $order_goods_id_array,
                'express_name' => $express_name,
                'shipping_type' => $shipping_type,
                'express_company_id' => $express_company_id,
                'express_no' => $express_no
            ];
            hook('orderDeliverySuccess', $params);
        }
        return $retval;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderGoodsDelivery()
     */
    public function orderGoodsDelivery($order_id, $order_goods_id_array)
    {
        $order_goods = new OrderGoods();
        $retval      = $order_goods->orderGoodsDelivery($order_id, $order_goods_id_array);
        if ($retval) {
            $params = [
                'order_id' => $order_id,
                'order_goods_id_array' => $order_goods_id_array
            ];
            hook('orderDeliverySuccess', $params);
        }
        return $retval;
        // TODO Auto-generated method stub
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderClose()
     */
    public function orderClose($order_id)
    {
        $order  = new OrderBusiness();
        $retval = $order->orderClose($order_id);
        if ($retval) {
            hook("orderCloseSuccess", [
                'order_id' => $order_id
            ]);
        }
        return $retval;
        // TODO Auto-generated method stub
    }

    /*
     * 订单完成的函数
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderComplete()
     */
    public function orderComplete($orderid)
    {
        $order  = new OrderBusiness();
        $retval = $order->orderComplete($orderid);
        try {
            // 结算订单的分销佣金
            $this->updateOrderCommission($orderid);
            // 处理店铺的账户资金
            $this->dealShopAccount_OrderComplete("", $orderid);
            // 处理平台的账户资金
            $this->updateAccountOrderComplete($orderid);
            // 更新会员的等级
            $user_service = new User();
            $order_model  = new NsOrderModel();
            $order_detail = $order_model->getInfo([
                "order_id" => $orderid
            ], "shop_id, buyer_id");
            $user_service->updateUserLevel($order_detail["shop_id"], $order_detail["buyer_id"]);

            runhook("Notify", "orderComplete", array(
                "order_id" => $orderid
            ));
        } catch (\Exception $e) {
            Log::write($e->getMessage());
        }
        if ($retval) {
            hook("orderComplateSuccess", [
                'order_id' => $orderid
            ]);
        }
        return $retval;
    }

    /**
     * 订单在线支付
     * (non-PHPdoc)
     *
     * @see \data\api\IOrder::orderOnLinePay()
     */
    public function orderOnLinePay($order_pay_no, $pay_type)
    {
        $order  = new OrderBusiness();
        $retval = $order->OrderPay($order_pay_no, $pay_type, 0);
        try {
            if ($retval > 0) {
                // 计算店铺内部的分销佣金
                $this->orderCommissionCalculate($order_pay_no);
                // 处理店铺的账户资金
                $this->dealShopAccount_OrderPay($order_pay_no);
                // 处理平台的资金账户
                $this->dealPlatformAccountOrderPay($order_pay_no);

                $order_model = new NsOrderModel();
                $condition   = " out_trade_no=" . $order_pay_no;
                $order_list  = $order_model->getQuery($condition, "order_id", "");
                foreach ($order_list as $k => $v) {
                    runhook("Notify", "orderPay", array(
                        "order_id" => $v["order_id"]
                    ));
                    // 判断是否需要在本阶段赠送积分
                    $order = new OrderBusiness();
                    $res   = $order->giveGoodsOrderPoint($v["order_id"], 3);
                }
            }
        } catch (\Exception $e) {

            Log::write($e->getMessage());
        }
        if ($retval) {
            $pay_type_name = OrderStatus::getPayType($pay_type);
            hook('orderOnLinePaySuccess', [
                'order_pay_no' => $order_pay_no
            ]);
            runhook('Notify', 'orderRemindBusiness', [
                "out_trade_no" => $order_pay_no,
                "shop_id" => 0
            ]); // 订单提醒
        }
        return $retval;
    }

    /**
     * 订单线下支付
     * (non-PHPdoc)
     *
     * @see \data\api\IOrder::orderOffLinePay()
     */
    public function orderOffLinePay($order_id, $pay_type, $status)
    {
        $order = new OrderBusiness();

        $new_no = $this->getOrderNewOutTradeNo($order_id);
        if ($new_no) {
            $retval = $order->OrderPay($new_no, $pay_type, $status);
            if ($retval > 0) {
                $pay = new UnifyPay();
                $pay->offLinePay($new_no, $pay_type);
                // 计算店铺的佣金情况
                $this->orderCommissionCalculate('', $order_id);
                // 处理店铺的账户资金
                $this->dealShopAccount_OrderPay('', $order_id);
                // 处理平台的资金账户
                $this->dealPlatformAccountOrderPay('', $order_id);
                // 判断是否需要在本阶段赠送积分
                $order         = new OrderBusiness();
                $res           = $order->giveGoodsOrderPoint($order_id, 3);
                $pay_type_name = OrderStatus::getPayType($pay_type);
                hook('orderOffLinePaySuccess', [
                    'order_id' => $order_id
                ]);
                runhook('Notify', 'orderRemindBusiness', [
                    "out_trade_no" => $new_no,
                    "shop_id" => 0
                ]); // 订单提醒
            }
            return $retval;
        } else {
            return 0;
        }
    }

    //订单线下支付(包括线下分期即赊销)
    public function orderOffLinePayNew($order_id, $pay_type, $pay_way, $pay_money, $pay_pic, $payment_type, $memo)
    {

        $order = new OrderBusiness();
        $new_no = $this->getOrderNewOutTradeNo($order_id);
        if ($new_no) {
            $retval = $order->OrderPayNew($order_id, $pay_type, $pay_way, $pay_money, $pay_pic, $payment_type, $memo);
            if ($retval > 0 && $pay_type != 2) {
                $pay = new UnifyPay();
                $pay->offLinePay($new_no, $payment_type);
            }
            return $retval;
        }else{
            return 0;
        }
    }

    /**
     * 添加订单线下付款信息
     */
    public function addOrderOffLinePay($order_id,$pay_type, $pay_way,$pay_money,$pay_pic)
    {
        $orderOfflinePayModel = new BcOrderOfflinePayModel();
        $data   = array(
            'order_id' => $order_id,
            'pay_type' => $pay_type,
            'pay_way' => $pay_way,
            'pay_money' => $pay_money,
            'pay_pic' => $pay_pic,
            'create_time' => time()
        );
        $retval  = $orderOfflinePayModel->save($data);
        return $retval;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IOrder::getOrderNewOutTradeNo()
     */
    public function getOrderNewOutTradeNo($order_id)
    {
        //获取原订单重新生成订单号
        $order_model  = new NsOrderModel();
        $out_trade_no = $order_model->getInfo([
            'order_id' => $order_id
        ], 'out_trade_no');

        //订单重新生成订单号
        $order        = new OrderBusiness();
        $new_no       = $order->createNewOutTradeNo($order_id);

        //更新ns_order_payment表out_trade_no
        $pay          = new UnifyPay();
        $pay->modifyNo($out_trade_no['out_trade_no'], $new_no);
        return $new_no;
    }

    /**
     * 订单调整金额(non-PHPdoc)
     *
     * @see \data\api\IOrder::orderMoneyAdjust()
     */
    public function orderMoneyAdjust($order_id, $order_goods_id_adjust_array, $shipping_fee)
    {
        // 调整订单
        $order_goods = new OrderGoods();
        $retval      = $order_goods->orderGoodsAdjustMoney($order_goods_id_adjust_array);

        if ($retval >= 0) {
            // 计算整体商品调整金额
            $new_no            = $this->getOrderNewOutTradeNo($order_id);
            $order             = new OrderBusiness();
            $order_goods_money = $order->getOrderGoodsMoney($order_id);
            $retval_order      = $order->orderAdjustMoney($order_id, $order_goods_money, $shipping_fee);
            $order_model       = new NsOrderModel();
            $order_money       = $order_model->getInfo([
                'order_id' => $order_id
            ], 'pay_money');
            $pay               = new UnifyPay();
            $pay->modifyPayMoney($new_no, $order_money['pay_money']);
            hook("orderMoneyAdjustSuccess", [
                'order_id' => $order_id,
                'order_goods_id_adjust_array' => $order_goods_id_adjust_array,
                'shipping_fee' => $shipping_fee
            ]);
            return $retval_order;
        } else {
            return $retval;
        }
    }

    /**
     * 查询订单
     *
     * @ERROR!!!
     *
     * @see \data\api\IOrder::orderQuery()
     */
    public function orderQuery($where = "", $field = "*")
    {
        $order = new OrderBusiness();
        return $order->where($where)
            ->field($field)
            ->select();
    }

    /**
     * 查询订单项退款信息(non-PHPdoc)
     *
     * @see \data\api\IOrder::getOrderGoodsRefundInfo()
     */
    public function getOrderGoodsRefundInfo($order_goods_id)
    {
        $order_goods      = new OrderGoods();
        $order_goods_info = $order_goods->getOrderGoodsRefundDetail($order_goods_id);
        return $order_goods_info;
    }
    public function getOrderGoodsRefundDetails($order_goods_id)
    {
        $order_goods      = new OrderGoods();
        $order_goods_info = $order_goods->getOrderGoodsRefundDetails($order_goods_id);
        return $order_goods_info;
    }

    public function test()
    {
        // $res = $order_express->getSkuGroup('466:1,467:2,468:2,462:1');
        // $res = $order_express->getGoodsShippingExpressFee(1, 5, 1, 22);
        // $res = $order_express->getSkuListExpressFee('466:1,467:2,468:2,462:1', 1, 22);
        // $res = $order_express->getGoodsSkuListPrice('466:1,467:2,468:2,462:1');
        /*
         * $order_goods = new OrderGoods();
         * $res = $order_goods->addOrderGoods(12, '466:1,467:2,468:2');
         */
        // 订单创建
        $order = new OrderBusiness();
        $res   = $order->orderCreate(1, '201611208115651112', 4, 1, 1, '127.0.0.1', '', '', '0000-0-0', '15234151502', 4, 14, 209, '小店', '', '测试', 0, 527, 0, '1145:2', 0, 0);
        return $res;

        /*
         * //订单发货
         * $order_express = new OrderExpress();
         * $res = $order_express->delivey(3, '16,17', "测试包裹", 1, 1, 'as0000100');
         */
        /*
         * $order = new OrderBusiness();
         * $res = $order->addOrderAction(3, 1, '订单发货');
         * /* //订单关闭
         * $order = new OrderBusiness();
         * $res = $order->orderClose(4);
         * return $res;
         */
        /*
         * //线下支付
         * $res = $this->orderOffLinePay(4, 1, 0);
         * return $res;
         */
        /*
         * //线上支付
         * $res = $this->orderOnLinePay('1481177387568', 2);
         * return $res;
         */
        /*
         * $order_goods = new OrderGoods();
         * $res = $order_goods->orderGoodsRefundAskfor(71, 162, 1, 1, '不想买');
         * return $res;
         */
        // 调整金额
        /*
         * $res = $this->orderMoneyAdjust(24, '52,-20;53,-20', 5);
         * return $res;
         */
        /*
         * //计算退款实际可退金额
         * $order_goods = new OrderGoods();
         * $res = $order_goods->orderGoodsRefundMoney(37);
         * return $res;
         */
        /*
         * $order = new GoodsPreference();
         * $order->
         */
        /*
         * $goods_mansong = new GoodsMansong();
         * $mansong_array = $goods_mansong->getGoodsSkuListMansong('443:2,445:5,467:2');
         * return $mansong_array;
         */
    }

    /**
     * 查询订单的订单项列表
     *
     * @param unknown $order_id
     */
    public function getOrderGoods($order_id)
    {
        $order = new OrderBusiness();
        return $order->getOrderGoods($order_id);
    }

    /**
     * 查询订单的订单项列表
     *
     * @param unknown $order_id
     */
    public function getOrderGoodsInfo($order_goods_id)
    {
        $order                             = new OrderBusiness();
        $picture                           = new AlbumPictureModel();
        $order_goods_info                  = $order->getOrderGoodsInfo($order_goods_id);
        $order_goods_info['goods_picture'] = $picture->get($order_goods_info['goods_picture'])['pic_cover'];
        return $order_goods_info;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::addOrder()
     */
    public function addOrder($data)
    {
        // TODO Auto-generated method stub
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderGoodsRefundAskfor()
     */
    public function orderGoodsRefundAskfor($order_id, $order_goods_id, $refund_type, $refund_require_money, $refund_reason)
    {
        $order_goods = new OrderGoods();
        $retval      = $order_goods->orderGoodsRefundAskfor($order_id, $order_goods_id, $refund_type, $refund_require_money, $refund_reason);
        if ($retval) {
            $params = [
                'order_id' => $order_id,
                'order_goods_id' => $order_goods_id,
                'refund_type' => $refund_type,
                'refund_require_money' => $refund_require_money,
                'refund_reason' => $refund_reason
            ];
            runhook("Notify", "orderRefoundBusiness", [
                "shop_id" => 0,
                "order_id" => $order_id
            ]); // 商家退款提醒
            hook('orderGoodsRefundAskforSuccess', $params);
        }
        return $retval;
        // TODO Auto-generated method stub
    }

    public function orderGoodsRefundAskfors($order_id, $order_goods_id, $refund_type, $refund_require_money, $refund_require_num, $refund_reason)
    {
        $order_goods = new OrderGoods();
        $retval      = $order_goods->orderGoodsRefundAskfors($order_id, $order_goods_id, $refund_type, $refund_require_money, $refund_require_num, $refund_reason);
        if ($retval) {
            $params = [
                'order_id' => $order_id,
                'order_goods_id' => $order_goods_id,
                'refund_type' => $refund_type,
                'refund_require_money' => $refund_require_money,
                'refund_reason' => $refund_reason
            ];
            runhook("Notify", "orderRefoundBusiness", [
                "shop_id" => 0,
                "order_id" => $order_id
            ]); // 商家退款提醒
            hook('orderGoodsRefundAskforSuccess', $params);
        }
        return $retval;
    }

    //礼物订单超过七天未领取订单项自动变为买家申请退款
    public function giftOrderGoodsRefundAskfor($order_id, $order_goods_id, $refund_type, $refund_require_money, $refund_reason)
    {
        $order_goods = new OrderGoods();
        $retval      = $order_goods->giftOrderGoodsRefundAskfor($order_id, $order_goods_id, $refund_type, $refund_require_money, $refund_reason);
        return $retval;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderGoodsCancel()
     */
    public function orderGoodsCancel($order_id, $order_goods_id)
    {
        $order_goods = new OrderGoods();
        $retval      = $order_goods->orderGoodsCancel($order_id, $order_goods_id);
        if ($retval) {
            hook("orderGoodsCancelSuccess", [
                'order_id' => $order_id,
                'order_goods_id' => $order_goods_id
            ]);
        }
        return $retval;
        // TODO Auto-generated method stub
    }

    public function orderGoodsCancels($order_id, $order_goods_id, $refund_records_id)
    {
        $order_goods = new OrderGoods();
        $retval      = $order_goods->orderGoodsCancels($order_id, $order_goods_id, $refund_records_id);
        if ($retval) {
            hook("orderGoodsCancelSuccess", [
                'order_id' => $order_id,
                'order_goods_id' => $order_goods_id
            ]);
        }
        return $retval;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderGoodsReturnGoods()
     */
    public function orderGoodsReturnGoods($order_id, $order_goods_id, $refund_shipping_company, $refund_shipping_code)
    {
        $order_goods = new OrderGoods();
        $retval      = $order_goods->orderGoodsReturnGoods($order_id, $order_goods_id, $refund_shipping_company, $refund_shipping_code);
        if ($retval) {
            $params = [
                'order_id' => $order_id,
                'order_goods_id' => $order_goods_id,
                'refund_shipping_company' => $refund_shipping_company,
                'refund_shipping_code' => $refund_shipping_code
            ];
            hook("orderGoodsReturnGoodsSuccess", $params);
        }
        return $retval;
        // TODO Auto-generated method stub
    }

    public function orderGoodsReturnGoodses($order_id, $order_goods_id, $refund_records_id, $refund_shipping_company, $refund_shipping_code)
    {
        $order_goods = new OrderGoods();
        $retval      = $order_goods->orderGoodsReturnGoodses($order_id, $order_goods_id, $refund_records_id, $refund_shipping_company, $refund_shipping_code);
        if ($retval) {
            $params = [
                'order_id' => $order_id,
                'order_goods_id' => $order_goods_id,
                'refund_shipping_company' => $refund_shipping_company,
                'refund_shipping_code' => $refund_shipping_code
            ];
            hook("orderGoodsReturnGoodsSuccess", $params);
        }
        return $retval;
        // TODO Auto-generated method stub
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderGoodsRefundAgree()
     */
    public function orderGoodsRefundAgree($order_id, $order_goods_id)
    {
        $order_goods = new OrderGoods();
        $retval      = $order_goods->orderGoodsRefundAgree($order_id, $order_goods_id);
        if ($retval) {
            hook("orderGoodsRefundAgreeSuccess", [
                'order_id' => $order_id,
                'order_goods_id' => $order_goods_id
            ]);
        }
        return $retval;
        // TODO Auto-generated method stub
    }

    public function orderGoodsRefundAgrees($order_id, $order_goods_id, $refund_records_id)
    {
        $order_goods = new OrderGoods();
        $retval      = $order_goods->orderGoodsRefundAgrees($order_id, $order_goods_id,$refund_records_id);
        if ($retval) {
            hook("orderGoodsRefundAgreeSuccess", [
                'order_id' => $order_id,
                'order_goods_id' => $order_goods_id
            ]);
        }
        return $retval;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderGoodsRefuseForever()
     */
    public function orderGoodsRefuseForever($order_id, $order_goods_id)
    {
        $order_goods = new OrderGoods();
        $retval      = $order_goods->orderGoodsRefuseForever($order_id, $order_goods_id);
        if ($retval) {
            hook("orderGoodsRefuseForeverSuccess", [
                'order_id' => $order_id,
                'order_goods_id' => $order_goods_id
            ]);
        }
        return $retval;
        // TODO Auto-generated method stub
    }

    public function orderGoodsRefuseForevers($order_id, $order_goods_id, $refund_records_id)
    {
        $order_goods = new OrderGoods();
        $retval      = $order_goods->orderGoodsRefuseForevers($order_id, $order_goods_id, $refund_records_id);
        if ($retval) {
            hook("orderGoodsRefuseForeverSuccess", [
                'order_id' => $order_id,
                'order_goods_id' => $order_goods_id
            ]);
        }
        return $retval;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderGoodsRefuseOnce()
     */
    public function orderGoodsRefuseOnce($order_id, $order_goods_id)
    {
        $order_goods = new OrderGoods();
        $retval      = $order_goods->orderGoodsRefuseOnce($order_id, $order_goods_id);
        if ($retval) {
            hook("orderGoodsRefuseOnceSuccess", [
                'order_id' => $order_id,
                'order_goods_id' => $order_goods_id
            ]);
        }
        return $retval;
        // TODO Auto-generated method stub
    }

    public function orderGoodsRefuseOnces($order_id, $order_goods_id, $refund_records_id)
    {
        $order_goods = new OrderGoods();
        $retval      = $order_goods->orderGoodsRefuseOnces($order_id, $order_goods_id, $refund_records_id);
        if ($retval) {
            hook("orderGoodsRefuseOnceSuccess", [
                'order_id' => $order_id,
                'order_goods_id' => $order_goods_id
            ]);
        }
        return $retval;
        // TODO Auto-generated method stub
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderGoodsConfirmRecieve()
     */
    public function orderGoodsConfirmRecieve($order_id, $order_goods_id, $storage_num, $isStorage, $goods_id, $sku_id)
    {
        $order_goods = new OrderGoods();
        $retval      = $order_goods->orderGoodsConfirmRecieve($order_id, $order_goods_id, $storage_num, $isStorage, $goods_id, $sku_id);
        if ($retval) {
            hook("orderGoodsConfirmRecieveSuccess", [
                'order_id' => $order_id,
                'order_goods_id' => $order_goods_id
            ]);
        }
        return $retval;
        // TODO Auto-generated method stub
    }

    public function orderGoodsConfirmRecieves($order_id, $order_goods_id, $refund_records_id, $storage_num, $isStorage, $goods_id, $sku_id)
    {
        $order_goods = new OrderGoods();
        $retval      = $order_goods->orderGoodsConfirmRecieves($order_id, $order_goods_id, $refund_records_id, $storage_num, $isStorage, $goods_id, $sku_id);
        if ($retval) {
            hook("orderGoodsConfirmRecieveSuccess", [
                'order_id' => $order_id,
                'order_goods_id' => $order_goods_id
            ]);
        }
        return $retval;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderGoodsConfirmRefund()
     */
    public function orderGoodsConfirmRefund($order_id, $order_goods_id, $refund_real_money, $refund_balance_money, $refund_way, $refund_remark)
    {
        $order_model = new NsOrderModel();
        $order_info  = $order_model->getInfo([
            "order_id" => $order_id
        ], "pay_money,refund_money");

        $order_refund_chai     = ($order_info['pay_money'] - $order_info['refund_money']) * 100;
        $order_refund_chai     = $order_refund_chai + 1000;
        $refund_real_money_ext = $refund_real_money * 100;
        $refund_real_money_ext = $refund_real_money_ext + 1000;
        if ($order_refund_chai < $refund_real_money_ext) {
            return "实际退款超过订单支付金额，退款失败";
        } else {

            $refund_trade_no = date("YmdHis", time()) . rand(100000, 999999);
            // 在线原路退款（微信/支付宝）
            $refund = $this->onlineOriginalRoadRefund($order_id, $refund_real_money, $refund_way, $refund_trade_no, $order_info['pay_money']);
            if ($refund['is_success'] == 1) {

                $order_goods = new OrderGoods();
                $retval      = $order_goods->orderGoodsConfirmRefund($order_id, $order_goods_id, $refund_real_money, $refund_balance_money, $refund_trade_no, $refund_way, $refund_remark);

                // 重新计算订单的佣金情况
                $this->updateCommissionMoney($order_id, $order_goods_id);

                // 计算店铺的账户
                $this->updateShopAccount_OrderRefund($order_goods_id);
                $this->updateShopAccount_OrderComplete($order_id);

                // 计算平台的账户
                $this->updateAccountOrderRefund($order_goods_id);
                $this->updateAccountOrderComplete($order_id);

                if ($retval) {
                    hook("orderGoodsConfirmRefundSuccess", [
                        'order_id' => $order_id,
                        'order_goods_id' => $order_goods_id,
                        'refund_real_money' => $refund_real_money
                    ]);
                }
                return $retval;
            } else {
                return $refund['msg'];
            }
        }
    }

    public function orderGoodsConfirmRefunds($order_id, $order_goods_id, $refund_records_id, $refund_real_money, $refund_balance_money, $refund_way, $refund_remark)
    {
        $order_model = new NsOrderModel();
        $order_info  = $order_model->getInfo([
            "order_id" => $order_id
        ], "pay_money,refund_money");

        $order_refund_chai     = ($order_info['pay_money'] - $order_info['refund_money']) * 100;
        $order_refund_chai     = $order_refund_chai + 1000;
        $refund_real_money_ext = $refund_real_money * 100;
        $refund_real_money_ext = $refund_real_money_ext + 1000;
        if ($order_refund_chai < $refund_real_money_ext) {
            return "实际退款超过订单支付金额，退款失败";
        } else {
            $refundAccountRecordsmodel = new NsOrderRefundAccountRecordsModel();
            $refundAccountRecord = $refundAccountRecordsmodel->getInfo(['id' => $refund_records_id], 'refund_trade_no,refund_require_num');
            // 在线原路退款（微信/支付宝）
            $refund = $this->onlineOriginalRoadRefund($order_id, $refund_real_money, $refund_way, $refundAccountRecord['refund_trade_no'], $order_info['pay_money']);
            if ($refund['is_success'] == 1) {
                $order_goods = new OrderGoods();
                $retval      = $order_goods->orderGoodsConfirmRefunds($order_id, $order_goods_id, $refund_records_id, $refund_real_money,  $refundAccountRecord['refund_require_num'], $refund_balance_money, $refundAccountRecord['refund_trade_no'], $refund_way, $refund_remark);
                if ($retval) {
                    hook("orderGoodsConfirmRefundSuccess", [
                        'order_id' => $order_id,
                        'order_goods_id' => $order_goods_id,
                        'refund_real_money' => $refund_real_money
                    ]);
                }
                return $retval;
            } else {
                return $refund['msg'];
            }
        }
    }

    /**
     * /**
     * 在线原路退款（微信、支付宝）
     * 创建时间：2017年10月17日 10:32:00 王永杰
     *
     * @param 订单id $order_id
     * @param 退款金额 $refund_fee
     * @param 退款方式 （1：微信，2：支付宝，10：线下） $refund_way
     * @param 退款交易号 $refund_trade_no
     * @param 订单总金额 $total_fee
     * @return number[]|string[]|\data\extend\weixin\成功时返回，其他抛异常|mixed[]
     */
    private function onlineOriginalRoadRefund($order_id, $refund_fee, $refund_way, $refund_trade_no, $total_fee)
    {
        // 1.根据订单id查询外部交易号
        $order_model  = new NsOrderModel();
        $out_trade_no = $order_model->getInfo([
            'order_id' => $order_id
        ], "out_trade_no");

        // 2.根据外部交易号查询trade_no（交易号）支付宝支付会返回一个交易号，微信传空
        $ns_order_payment_model = new NsOrderPaymentModel();
        $trade_no               = $ns_order_payment_model->getInfo([
            "out_trade_no" => $out_trade_no['out_trade_no']
        ], 'trade_no');

        // 3.根据用户选择的退款方式，进行不同的原路退款操作
        if ($refund_way == 1) {

            // 微信退款

            $weixin_pay = new WeiXinPay();
            $retval     = $weixin_pay->setWeiXinRefund($refund_trade_no, $out_trade_no['out_trade_no'], $refund_fee * 100, $total_fee * 100);
        } elseif ($refund_way == 2) {

            // 支付宝退款
            $ali_pay = new AliPay();
            $retval  = $ali_pay->aliPayRefund($refund_trade_no, $trade_no['trade_no'], $refund_fee);
        } else {

            // 线下操作，直接通过
            $retval = array(
                "is_success" => 1,
                'msg' => ""
            );
        }

        return $retval;
    }

    /**
     * 获取对应sku列表价格
     *
     * @param unknown $goods_sku_list
     */
    public function getGoodsSkuListPrice($goods_sku_list)
    {
        $goods_preference = new GoodsPreference();
        $money            = $goods_preference->getGoodsSkuListPrice($goods_sku_list);
        return $money;
    }

    /**
     * 获取组合商品sku列表价格
     *
     * @param unknown $goods_sku_list
     * @return unknown
     */
    public function getComboPackageGoodsSkuListPrice($goods_sku_list)
    {
        $goods_preference = new GoodsPreference();
        $money            = $goods_preference->getComboPackageGoodsSkuListPrice($goods_sku_list);
        return $money;
    }

    /**
     * 获取邮费
     *
     * @param unknown $goods_sku_list
     * @param unknown $province
     * @param unknown $city
     * @return Ambigous <unknown, number>
     */
    public function getExpressFee($goods_sku_list, $express_company_id, $province, $city, $district)
    {
        $goods_express = new GoodsExpress();
        $fee           = $goods_express->getSkuListExpressFee($goods_sku_list, $express_company_id, $province, $city, $district);
        return $fee;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IOrder::orderGoodsRefundMoney()
     */
    public function orderGoodsRefundMoney($order_goods_id)
    {
        $order_goods = new OrderGoods();
        $money       = $order_goods->orderGoodsRefundMoney($order_goods_id);
        return $money;
    }

    /**
     * 获取用户可使用优惠券
     *
     * @param unknown $goods_sku_list
     */
    public function getMemberCouponList($goods_sku_list)
    {
        $goods_preference = new GoodsPreference();
        $coupon_list      = $goods_preference->getMemberCouponList($goods_sku_list);
        return $coupon_list;
    }

    /**
     * 查询商品列表可用积分数
     *
     * @param unknown $goods_sku_list
     */
    public function getGoodsSkuListUsePoint($goods_sku_list)
    {
        $point                = 0;
        $goods_sku_list_array = explode(",", $goods_sku_list);
        foreach ($goods_sku_list_array as $k => $v) {

            $sku_data    = explode(':', $v);
            $sku_id      = $sku_data[0];
            $goods       = new Goods();
            $goods_id    = $goods->getGoodsId($sku_id);
            $goods_model = new NsGoodsModel();
            $point_use   = $goods_model->getInfo([
                'goods_id' => $goods_id
            ], 'point_exchange_type,point_exchange');
            if ($point_use['point_exchange_type'] == 1) {
                $point += $point_use['point_exchange'];
            }
        }
        return $point;
    }

    /**
     *
     * @ERROR!!!
     *
     * @see \data\api\IOrder::OrderTakeDelivery()
     */
    public function OrderTakeDelivery($order_id)
    {
        $order = new OrderBusiness();
        $res   = $order->OrderTakeDelivery($order_id);
        if ($res) {
            hook("orderTakeDeliverySuccess", [
                'order_id' => $order_id
            ]);
        }
        return $res;
    }

    /**
     * 删除购物车中的数据
     * 修改时间：2017年5月26日 14:35:38 王永杰
     * 首先要查询当前商品在购物车中的数量，如果商品数量等于1则删除，如果商品数量大于1个，则减少该商品的数量
     * (non-PHPdoc)
     *
     * @see \data\api\IOrder::deleteCart()
     */
    public function deleteCart($goods_sku_list, $uid)
    {
        $cart                 = new NsCartModel();
        $goods_sku_list_array = explode(",", $goods_sku_list);
        foreach ($goods_sku_list_array as $k => $v) {
            $sku_data = explode(':', $v);
            $sku_id   = $sku_data[0];
            $info     = $cart->getInfo([
                'buyer_id' => $uid,
                'sku_id' => $sku_id
            ], "num,cart_id");
            // $num = $info['num'];
            $cart_id = $info['cart_id'];
            $cart->destroy([
                'buyer_id' => $uid,
                'sku_id' => $sku_id
            ]);
            // if ($num == 1) {
            // // 购物车中该商品数量为1的话就删除
            // } else {
            // // 修改商品数量
            // $data["num"] = $num - 1;
            // $cart->update($data, [
            // 'cart_id' => $cart_id
            // ]);
            // }
        }
        $_SESSION["user_cart"] = '';
    }

    /**
     * 删除礼品盒中的数据
     * 首先要查询当前商品在礼品盒中的数量，如果礼品数量等于1则删除，如果礼品数量大于1个，则减少该商品的数量
     * (non-PHPdoc)
     *
     * @see \data\api\IOrder::deleteBox()
     */
    public function deleteBox($goods_sku_list, $uid)
    {
        $box                  = new NsBoxModel();
        $goods_sku_list_array = explode(",", $goods_sku_list);
        foreach ($goods_sku_list_array as $k => $v) {
            $sku_data = explode(':', $v);
            $sku_id   = $sku_data[0];
            $info     = $box->getInfo([
                'buyer_id' => $uid,
                'sku_id' => $sku_id
            ], "num,box_id");
            $box_id   = $info['box_id'];
            $box->destroy([
                'buyer_id' => $uid,
                'sku_id' => $sku_id
            ]);
        }
        $_SESSION["user_box"] = '';
    }

    /**
     *
     * @ERROR!!!
     *
     * @see \data\api\IOrder::getOrderCount()
     */
    public function getOrderCount($condition)
    {
        $order = new NsOrderModel();
        $count = $order->where($condition)->count();
        return $count;
    }

    /**
     *
     * @ERROR!!!
     *
     * @see \data\api\IOrder::getPayMoneySum()
     */
    public function getPayMoneySum($condition)
    {
//        p($condition);
        $order_model = new NsOrderModel();
        $money_sum   = $order_model->where($condition)->sum('order_money');
        return $money_sum;
    }

    /**
     *
     * @ERROR!!!
     *
     * @see \data\api\IOrder::getGoodsNumSum()
     */
    public function getGoodsNumSum($condition)
    {
        $order_model = new NsOrderModel();
        $order_list  = $order_model->where($condition)->select();
        $goods_sum   = 0;
        foreach ($order_list as $k => $v) {
            $order_goods = new NsOrderGoodsModel();
            $goods_sum += $order_goods->where([
                'order_id' => $v['order_id']
            ])->sum('num');
        }
        return $goods_sum;
    }

    /**
     * 获取具体配送状态信息
     *
     * @param unknown $shipping_status_id
     * @return Ambigous <NULL, multitype:string >
     */
    public static function getShippingInfo($shipping_status_id)
    {
        $shipping_status = OrderStatus::getShippingStatus();
        $info            = null;
        foreach ($shipping_status as $shipping_info) {
            if ($shipping_status_id == $shipping_info['shipping_status']) {
                $info = $shipping_info;
                break;
            }
        }
        return $info;
    }

    /**
     * 获取具体支付状态信息
     *
     * @param unknown $pay_status_id
     * @return multitype:multitype:string |string
     */
    public static function getPayStatusInfo($pay_status_id)
    {
        $pay_status = OrderStatus::getPayStatus();
        $info       = null;
        foreach ($pay_status as $pay_info) {
            if ($pay_status_id == $pay_info['pay_status']) {
                $info = $pay_info;
                break;
            }
        }
        return $info;
    }

    /**
     * 获取订单各状态数量
     */
    public static function getOrderStatusNum($condition = '')
    {
        $order                           = new NsOrderModel();
        $orderStatusNum['all']           = $order->where($condition)->count(); // 全部
        $condition['order_status']       = 0; // 待付款
        $orderStatusNum['wait_pay']      = $order->where($condition)->count();
        $condition['order_status']       = 1; // 待发货
        $orderStatusNum['wait_delivery'] = $order->where($condition)->count();
        $condition['order_status']       = 2; // 待收货
        $orderStatusNum['wait_recieved'] = $order->where($condition)->count();
        $condition['order_status']       = 3; // 已收货
        $orderStatusNum['recieved']      = $order->where($condition)->count();
        $condition['order_status']       = 4; // 交易成功
        $orderStatusNum['success']       = $order->where($condition)->count();
        $condition['order_status']       = 5; // 已关闭
        $orderStatusNum['closed']        = $order->where($condition)->count();
        $condition['order_status']       = -1; // 退款中
        $orderStatusNum['refunding']     = $order->where($condition)->count();
        $condition['order_status']       = -2; // 已退款
        $orderStatusNum['refunded']      = $order->where($condition)->count();
        $condition['order_status']       = array(
            'in',
            '3,4'
        ); // 已收货
        $condition['is_evaluate']        = 0; // 未评价
        $orderStatusNum['wait_evaluate'] = $order->where($condition)->count(); // 待评价

        return $orderStatusNum;
    }

    /**
     * 商品评价-添加
     *
     * @param unknown $dataList
     *            评价内容的 数组
     * @return Ambigous <multitype:, \think\false>
     */
    public function addGoodsEvaluate($dataArr, $order_id)
    {
        $goodsEvaluate = new NsGoodsEvaluateModel();
        $goods         = new NsGoodsModel();
        $res           = $goodsEvaluate->saveAll($dataArr);
        $result        = false;

        if ($res != false) {
            // 修改订单评价状态
            $order  = new NsOrderModel();
            $data   = array(
                'is_evaluate' => 1
            );
            $result = $order->save($data, [
                'order_id' => $order_id
            ]);

            $this->commentPoint($order_id);
        }
        foreach ($dataArr as $item) {
            $good_info   = $goods->get($item['goods_id']);
            $evaluates   = $good_info['evaluates'] + 1;
            $star        = $good_info['star'] + $item['scores'];
            $match_point = $star / $evaluates;
            $match_ratio = $match_point / 5 * 100;
            $data        = array(
                'evaluates' => $evaluates,
                'star' => $star,
                'match_point' => $match_point,
                'match_ratio' => $match_ratio
            );
            $goods->update($data, [
                'goods_id' => $item['goods_id']
            ]);
        }
        hook("goodsEvaluateSuccess", [
            'order_id' => $order_id,
            'data' => $dataArr
        ]);
        return $result;
    }

    /**
     * 商品评价-回复
     *
     * @param unknown $explain_first
     *            评价内容
     * @param unknown $ordergoodsid
     *            订单项ID
     * @return Ambigous <number, \think\false>
     */
    public function addGoodsEvaluateExplain($explain_first, $order_goods_id)
    {
        $goodsEvaluate = new NsGoodsEvaluateModel();
        $data          = array(
            'explain_first' => $explain_first
        );
        $res           = $goodsEvaluate->save($data, [
            'order_goods_id' => $order_goods_id
        ]);
        hook("goodsEvaluateExplainSuccess", [
            'order_goods_id' => $order_goods_id,
            'explain_first' => $explain_first
        ]);
        return $res;
    }

    /**
     * 商品评价-追评
     *
     * @param unknown $again_content
     *            追评内容
     * @param unknown $againImageList
     *            传入追评图片的 数组
     * @param unknown $ordergoodsid
     *            订单项ID
     * @return Ambigous <number, \think\false>
     */
    public function addGoodsEvaluateAgain($again_content, $againImageList, $order_goods_id)
    {
        $goodsEvaluate = new NsGoodsEvaluateModel();
        $data          = array(
            'again_content' => $again_content,
            'again_addtime' => time(),
            'again_image' => $againImageList
        );
        $res           = $goodsEvaluate->save($data, [
            'order_goods_id' => $order_goods_id
        ]);
        hook("goodsEvaluateAgainSuccess", [
            'again_content' => $again_content,
            'againImageList' => $againImageList,
            'order_goods_id' => $order_goods_id
        ]);
        return $res;
    }

    /**
     * 商品评价-追评回复
     *
     * @param unknown $again_explain
     *            追评的 回复内容
     * @param unknown $ordergoodsid
     *            订单项ID
     * @return Ambigous <number, \think\false>
     */
    public function addGoodsEvaluateAgainExplain($again_explain, $order_goods_id)
    {
        $goodsEvaluate = new NsGoodsEvaluateModel();
        $data          = array(
            'again_explain' => $again_explain
        );
        $res           = $goodsEvaluate->save($data, [
            'order_goods_id' => $order_goods_id
        ]);
        hook("goodsEvaluateAgainExplainSuccess", [
            'order_goods_id' => $order_goods_id,
            'again_explain' => $again_explain
        ]);
        return $res;
    }

    /**
     * 获取指定订单的评价信息
     *
     * @param unknown $orderid
     *            订单ID
     */
    public function getOrderEvaluateByOrder($order_id)
    {
        $goodsEvaluate         = new NsGoodsEvaluateModel();
        $condition['order_id'] = $order_id;
        $field                 = 'order_id, order_no, order_goods_id, goods_id, goods_name, goods_price, goods_image, shop_id, shop_name, content, addtime, image, explain_first, member_name, uid, is_anonymous, scores, again_content, again_addtime, again_image, again_explain';
        return $goodsEvaluate->getQuery($condition, $field, 'order_goods_id ASC');
    }

    /**
     * 获取指定会员的评价信息
     *
     * @param unknown $uid
     *            会员ID
     */
    public function getOrderEvaluateByMember($uid)
    {
        $goodsEvaluate    = new NsGoodsEvaluateModel();
        $condition['uid'] = $uid;
        $field            = 'order_id, order_no, order_goods_id, goods_id, goods_name, goods_price, goods_image, shop_id, shop_name, content, addtime, image, explain_first, member_name, uid, is_anonymous, scores, again_content, again_addtime, again_image, again_explain';
        return $goodsEvaluate->getQuery($condition, $field, 'order_goods_id ASC');
    }

    /**
     * 评价信息 分页
     *
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param unknown $order
     * @return number
     */
    public function getOrderEvaluateDataList($page_index, $page_size, $condition, $order)
    {
        $goodsEvaluate = new NsGoodsEvaluateModel();
        return $goodsEvaluate->pageQuery($page_index, $page_size, $condition, $order, "*");
    }

    /**
     * 获取评价列表
     *
     * @param unknown $page_index
     *            页码
     * @param unknown $page_size
     *            页大小
     * @param unknown $condition
     *            条件
     * @param unknown $order
     *            排序
     * @return multitype:number unknown
     */
    public function getOrderEvaluateList($page_index, $page_size, $condition, $order)
    {
        $goodsEvaluate = new NsGoodsEvaluateModel();
        $field         = 'order_id, order_no, order_goods_id, goods_id, goods_name, goods_price, goods_image, shop_id, shop_name, content, addtime, image, explain_first, member_name, uid, is_anonymous, scores, again_content, again_addtime, again_image, again_explain';
        return $goodsEvaluate->pageQuery($page_index, $page_size, $condition, $order, $field);
    }

    /**
     * 修改订单数据
     *
     * @param unknown $order_id
     * @param unknown $data
     */
    public function modifyOrderInfo($data, $order_id)
    {
        $order = new NsOrderModel();
        return $order->save($data, [
            'order_id' => $order_id
        ]);
    }

    /**
     * 判断店铺类型
     *
     * @param unknown $shop_id
     */
    private function getShopTypeDetail($shop_id)
    {
        $shop_model  = new NsShopModel();
        $shop_detail = $shop_model->get($shop_id);
        if (empty($shop_detail)) {
            return 0;
        } else {
            return $shop_detail["shop_type"];
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IOrder::getShopOrderAccountList()
     */
    public function getShopOrderAccountList($shop_id, $start_time, $end_time, $page_index, $page_size)
    {
        $order_account = new OrderAccount();
        $list          = $order_account->getShopOrderSumList($shop_id, $start_time, $end_time, $page_index, $page_size);
        return $list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IOrder::getShopOrderRefundList()
     */
    public function getShopOrderRefundList($shop_id, $start_time, $end_time, $page_index, $page_size)
    {
        $order_account = new OrderAccount();
        $list          = $order_account->getShopOrderRefundList($shop_id, $start_time, $end_time, $page_index, $page_size);
        return $list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IOrder::getShopOrderStatics()
     */
    public function getShopOrderStatics($shop_id, $start_time, $end_time)
    {
        $order_account     = new OrderAccount();
        $order_sum         = $order_account->getShopOrderSum($shop_id, $start_time, $end_time);
        $order_refund_sum  = $order_account->getShopOrderSumRefund($shop_id, $start_time, $end_time);
        $order_sum_account = $order_sum - $order_refund_sum;
        $array             = array(
            'order_sum' => $order_sum,
            'order_refund_sum' => $order_refund_sum,
            'order_account' => $order_sum_account
        );
        return $array;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IOrder::getShopOrderAccountDetail()
     */
    public function getShopOrderAccountDetail($shop_id)
    {
        // 获取总销售统计
        $account_all = $this->getShopOrderStatics($shop_id, '2015-1-1', '3050-1-1');
        // 获取今日销售统计
        $date_day_start = date("Y-m-d", time());
        $date_day_end   = date("Y-m-d H:i:s", time());
        $account_day    = $this->getShopOrderStatics($shop_id, $date_day_start, $date_day_end);
        // 获取周销售统计（7天）
        $date_week_start = date('Y-m-d', strtotime('-7 days'));
        $date_week_end   = $date_day_end;
        $account_week    = $this->getShopOrderStatics($shop_id, $date_week_start, $date_week_end);
        // 获取月销售统计(30天)
        $date_month_start = date('Y-m-d', strtotime('-30 days'));
        $date_month_end   = $date_day_end;
        $account_month    = $this->getShopOrderStatics($shop_id, $date_month_start, $date_month_end);
        $array            = array(
            'day' => $account_day,
            'week' => $account_week,
            'month' => $account_month,
            'all' => $account_all
        );
        return $array;
    }

    /*
     * (non-PHPdoc)
     *
     * @see \data\api\IOrder::getShopAccountCountInfo()
     */
    public function getShopAccountCountInfo($shop_id)
    {
        // 本月第一天
        $date_month_start = getTimeTurnTimeStamp(date('Y-m-d', strtotime('-30 days')));
        $date_month_end   = getTimeTurnTimeStamp(date("Y-m-d H:i:s", time()));
        // 下单金额
        $order_account             = new OrderAccount();
        $condition["create_time"]  = [
            [
                ">=",
                $date_month_start
            ],
            [
                "<=",
                $date_month_end
            ]
        ];
        $condition['order_status'] = array(
            'NEQ',
            0
        );
        $condition['order_status'] = array(
            'NEQ',
            5
        );
        if ($shop_id != 0) {
            $condition['shop_id'] = array(
                'NEQ',
                0
            );
        }
        $order_money = $order_account->getShopSaleSum($condition);
        // var_dump($order_money);
        // 下单会员
        $order_user_num = $order_account->getShopSaleUserSum($condition);
        // 下单量
        $order_num = $order_account->getShopSaleNumSum($condition);
        // 下单商品数
        $order_goods_num = $order_account->getShopSaleGoodsNumSum($condition);
        // 平均客单价
        if ($order_user_num > 0) {
            $user_money_average = $order_money / $order_user_num;
        } else {
            $user_money_average = 0;
        }
        // 平均价格
        if ($order_goods_num > 0) {
            $goods_money_average = $order_money / $order_goods_num;
        } else {
            $goods_money_average = 0;
        }
        $array = array(
            "order_money" => sprintf('%.2f', $order_money),
            "order_user_num" => $order_user_num,
            "order_num" => $order_num,
            "order_goods_num" => $order_goods_num,
            "user_money_average" => sprintf('%.2f', $user_money_average),
            "goods_money_average" => sprintf('%.2f', $goods_money_average)
        );
        return $array;
    }

    /*
     * (non-PHPdoc)
     *
     * @see \data\api\IOrder::getShopGoodsSalesList()
     */
    public function getShopGoodsSalesList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $goods_model = new NsGoodsModel();
        $tmp_array   = $condition;
        if (!empty($condition["order_status"])) {
            $order_condition["order_status"] = $condition["order_status"];
            unset($tmp_array["order_status"]);
        }
        $goods_list = $goods_model->pageQuery($page_index, $page_size, $tmp_array, $order, 'goods_name,material_code,price,clicks,goods_id,category_id_1,user_click');
        // 条件
        $start_date                     = getTimeTurnTimeStamp(date('Y-m-d', strtotime('-30 days')));
        $end_date                       = getTimeTurnTimeStamp(date("Y-m-d H:i:s", time()));
        $order_condition['create_time'] = [
            'between',
            [
                $start_date,
                $end_date
            ]
        ];
        $order_condition["shop_id"]     = $condition["shop_id"];
        $goods_calculate                = new GoodsCalculate();
        // 得到条件内的订单项
        $order_goods_list = $goods_calculate->getOrderGoodsSelect($order_condition);
        // 不加条件
        $order_goods_list_no_limit = $goods_calculate->getOrderGoodsSelectNoLimit();
        // 遍历商品
        foreach ($goods_list["data"] as $k => $v) {
            $sales_num     = 0;
            $sales_money   = 0;
            $order_num     = 0;
            $order_pay_num = 0;
            foreach ($order_goods_list as $vv) {
                if ($vv['goods_id'] == $v['goods_id']) $sales_num = $sales_num + $vv["num"];
                if ($vv['goods_id'] == $v['goods_id']) $sales_money = $sales_money + ($vv["goods_money"] - $vv["adjust_money"]);
            }
            foreach ($order_goods_list_no_limit as $vo) {
                if (in_array($v['goods_id'], $vo['goods_id'])) {
                    $order_num++;
                }
                if (in_array($v['goods_id'], $vo['goods_id']) && $vo['order_status'] != 0 && $vo['order_status'] != 5) {
                    $order_pay_num++;
                }
            }
            $data["sales_num"]   = $sales_num;
            $data["sales_money"] = $sales_money;
            if (!empty($v['clicks'])) {
                $data['day_order_proportion'] = number_format($order_num / $v['clicks'], 2, '.', '') * 100 . '%';
                $data['day_pay_proportion']   = number_format($order_pay_num / $v['clicks'], 2, '.', '') * 100 . '%';
            }
            $goods_list["data"][$k]["sales_info"]    = $data;
            $goods_list["data"][$k]["category_name"] = \think\Db::name('ns_goods_category')->where(['category_id' => $v['category_id_1']])->find()['category_name'];
        }
        return $goods_list;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::getShopGoodsSalesAll()
     */
    public function getShopGoodsSalesQuery($shop_id, $start_date, $end_date, $condition)
    {
        // TODO Auto-generated method stub
        // 商品
        $goods_model = new NsGoodsModel();
        $goods_list  = $goods_model->getQuery($condition, "*", '');
        // 订单项
        $condition['create_time']        = [
            'between',
            [
                $start_date,
                $end_date
            ]
        ];
        $order_condition["create_time"]  = [
            [
                ">=",
                $start_date
            ],
            [
                "<=",
                $end_date
            ]
        ];
        $order_condition['order_status'] = array(
            'NEQ',
            0
        );
        $order_condition['order_status'] = array(
            'NEQ',
            5
        );
        if ($shop_id != '') {
            $order_condition["shop_id"] = $shop_id;
        }
        $goods_calculate  = new GoodsCalculate();
        $order_goods_list = $goods_calculate->getOrderGoodsSelect($order_condition);
        // 遍历商品
        foreach ($goods_list as $k => $v) {
            $data                          = array();
            $goods_sales_num               = $goods_calculate->getGoodsSalesNum($order_goods_list, $v["goods_id"]);
            $goods_sales_money             = $goods_calculate->getGoodsSalesMoney($order_goods_list, $v["goods_id"]);
            $goods_list[$k]["sales_num"]   = $goods_sales_num;
            $goods_list[$k]["sales_money"] = $goods_sales_money;
        }
        return $goods_list;
    }

    /**
     * 查询一段时间内的店铺下单金额
     *
     * @param unknown $shop_id
     * @param unknown $start_date
     * @param unknown $end_date
     * @return Ambigous <\data\service\Order\unknown, number, unknown>
     */
    public function getShopSaleSum($condition)
    {
        $order_account = new OrderAccount();
        $sales_num     = $order_account->getShopSaleSum($condition);
        return $sales_num;
    }

    /**
     * 查询一段时间内的店铺下单量
     *
     * @param unknown $shop_id
     * @param unknown $start_date
     * @param unknown $end_date
     * @return unknown
     */
    public function getShopSaleNumSum($condition)
    {
        $order_account = new OrderAccount();
        $sales_num     = $order_account->getShopSaleNumSum($condition);
        return $sales_num;
    }

    /**
     * ***********************************************店铺账户--Start******************************************************
     */
    /**
     * 订单支付的时候 调整店铺账户
     *
     * @param string $order_out_trade_no
     * @param number $order_id
     */
    private function dealShopAccount_OrderPay($order_out_trade_no = "", $order_id = 0)
    {
    }

    /**
     * 订单完成的时候调整账户金额
     *
     * @param string $order_out_trade_no
     * @param number $order_id
     */
    private function dealShopAccount_OrderComplete($order_out_trade_no = "", $order_id = 0)
    {
    }

    /**
     * 订单支付
     *
     * @param unknown $order_id
     */
    private function updateShopAccount_OrderPay($order_id)
    {
        $order_model  = new NsOrderModel();
        $shop_account = new ShopAccount();
        $order        = new OrderBusiness();
        $order_model->startTrans();
        try {
            $order_obj = $order_model->get($order_id);
            // 订单的实际付款金额
            $pay_money = $order->getOrderRealPayMoney($order_id);
            // 订单的支付方式
            $payment_type = $order_obj["payment_type"];
            // 店铺id
            $shop_id = $order_obj["shop_id"];
            // 订单号
            $order_no = $order_obj["order_no"];
            // 处理订单的营业总额
            $shop_account->addShopAccountProfitRecords(getSerialNo(), $shop_id, $pay_money, 1, $order_id, "店铺订单支付金额" . $pay_money . "元, 订单号为：" . $order_no . ", 支付方式【线下支付】。");
            if ($payment_type != ORDER_REFUND_STATUS) {
                // 在线支付 处理店铺的入账总额
                $shop_account->addShopAccountMoneyRecords(getSerialNo(), $shop_id, $pay_money, 1, $order_id, "店铺订单支付金额" . $pay_money . "元, 订单号为：" . $order_no . ", 支付方式【在线支付】, 已入店铺账户。");
            }
            // 处理平台的利润分成
            $this->addShopOrderAccountRecords($order_id, $order_no, $shop_id, $pay_money);
            $order_model->commit();
        } catch (\Exception $e) {
            $order_model->rollback();
        }
    }

    /**
     * 订单项退款
     *
     * @param unknown $order_goods_id
     */
    private function updateShopAccount_OrderRefund($order_goods_id)
    {
    }

    /**
     * 订单完成
     *
     * @param unknown $order_id
     */
    private function updateShopAccount_OrderComplete($order_id)
    {
    }

    /**
     * ***********************************************店铺账户--End******************************************************
     */

    /**
     * ***********************************************平台账户计算--Start******************************************************
     */
    /**
     * 订单支付时处理 平台的账户
     *
     * @param string $order_out_trade_no
     * @param number $order_id
     */
    public function dealPlatformAccountOrderPay($order_out_trade_no = "", $order_id = 0)
    {
    }

    /**
     * 处理平台的利润抽成
     *
     * @param unknown $order_id
     * @param unknown $order_no
     * @param unknown $shop_id
     * @param unknown $pay_money
     */
    private function addShopOrderAccountRecords($order_id, $order_no, $shop_id, $pay_money)
    {
    }

    /**
     * 订单退款 更新平台抽取提成
     *
     * @param unknown $order_id
     * @param unknown $order_goods_id
     * @param unknown $shop_id
     */
    private function updateShopOrderGoodsReturnRecords($order_id, $order_goods_id, $shop_id)
    {
    }

    /**
     * 订单支付成功后处理 平台账户
     *
     * @param unknown $orderid
     */
    private function updateAccountOrderPay($order_id)
    {
        $order_model  = new NsOrderModel();
        $shop_account = new ShopAccount();
        $order        = new OrderBusiness();
        $order_model->startTrans();
        try {
            $order_obj = $order_model->get($order_id);
            // 订单的实际付款金额
            $pay_money = $order->getOrderRealPayMoney($order_id);
            // 订单的支付方式
            $payment_type = $order_obj["payment_type"];
            // 店铺id
            $shop_id = $order_obj["shop_id"];
            // 订单号
            $order_no = $order_obj["order_no"];
            if ($payment_type != ORDER_REFUND_STATUS) {
                // 在线支付 处理平台的资金账户
                $shop_account->addAccountOrderRecords($shop_id, $pay_money, 1, $order_id, "店铺订单支付金额" . $pay_money . "元, 订单号为：" . $order_no . ", 支付方式【在线支付】。");
            }
            $order_model->commit();
        } catch (\Exception $e) {
            $order_model->rollback();
        }
    }

    /**
     * 订单完成时 处理平台的利润抽成
     *
     * @param unknown $order_id
     */
    private function updateAccountOrderComplete($order_id)
    {
    }

    /**
     * 订单退款 更细平台的订单支付金额
     *
     * @param unknown $order_goods_id
     */
    private function updateAccountOrderRefund($order_goods_id)
    {
    }

    /**
     * ***********************************************平台账户计算--End******************************************************
     */

    /**
     * ***********************************************订单的佣金计算--Start******************************************************
     */

    /**
     * 支付后续佣金操作
     *
     * @param unknown $order_out_trade_no
     * @param unknown $order_id
     */
    private function orderCommissionCalculate($order_out_trade_no = "", $order_id = 0)
    {
        // 针对非基础电商版
        if (NS_VERSION != NS_VER_B2C) {
            if ($order_out_trade_no != "" && $order_id == 0) {
                $order_model = new NsOrderModel();
                $condition   = " out_trade_no=" . $order_out_trade_no;
                $order_list  = $order_model->getQuery($condition, "order_id", "");
                foreach ($order_list as $k => $v) {
                    $this->oneOrderCommissionCalculate($v["order_id"]);
                }
            } else
                if ($order_out_trade_no == "" && $order_id != 0) {
                    $this->oneOrderCommissionCalculate($order_id);
                }
        }
    }

    /**
     * 处理单个 订单佣金计算
     *
     * @param unknown $order_id
     */
    private function oneOrderCommissionCalculate($order_id)
    {
        if (NS_VERSION != NS_VER_B2C) {
            $commissionCalculate = new NfxCommissionCalculate($order_id);
            // 分销佣金计算
            $res = $commissionCalculate->orderdistributionCommission();
            // 区域代理计算
            $res = $commissionCalculate->orderRegionAgentCommission();
            // 股东分红计算
            $res = $commissionCalculate->orderPartnerCommission();
        }
    }

    public function partent_test()
    {
        $commissionCalculate = new NfxCommissionCalculate(476);
        $res                 = $commissionCalculate->orderPartnerCommission();
    }

    /**
     * 订单退款成功后需要重新计算订单的佣金
     *
     * @param unknown $order_id
     * @param unknown $order_goods_id
     */
    public function updateCommissionMoney($order_id, $order_goods_id)
    {
        // 单店基础版不进行计算
        if (NS_VERSION != NS_VER_B2C) {
            $commissionCalculate = new NfxCommissionCalculate($order_id, $order_goods_id);
            // 重新计算分销佣金
            $commissionCalculate->updateOrderDistributionCommission();
            // 重新计算股东分红
            $commissionCalculate->updateOrderPartnerCommission();
            // 重新计算区域代理佣金
            $commissionCalculate->updateOrderRegionAgentCommission();
            // 订单退款成功后 发放佣金
            $this->updateOrderCommission($order_id);
        }
    }

    /**
     * 订单完成交易进行 佣金结算
     *
     * @param unknown $order_id
     */
    private function updateOrderCommission($order_id)
    {
        if (NS_VERSION != NS_VER_B2C) {
            $order_model = new NsOrderModel();
            $order_model->startTrans();
            try {
                $shop_obj     = $order_model->get($order_id);
                $order_sataus = $shop_obj["order_status"];
                // 判断当前订单的状态是否 已经交易完成 或者 已退款的状态
                if ($order_sataus == ORDER_COMPLETE_SUCCESS || $order_sataus == ORDER_COMPLETE_REFUND || $order_sataus == ORDER_COMPLETE_SHUTDOWN) {
                    // 得到订单的店铺id
                    $shop_id = $shop_obj["shop_id"];
                    // 得到订单用户id
                    $uid          = $shop_obj["buyer_id"];
                    $user_service = new NfxUser();
                    // 发放订单的三级分销佣金
                    $user_service->updateCommissionDistributionIssue($order_id);
                    // 更新当前用户的分销商等级
                    $user_service->updatePromoterLevel($uid, $shop_id);
                    // /发放订单的区域代理佣金
                    $user_service->updateCommissionRegionAgentIssue($order_id);
                    // 发放订单的股东分红佣金
                    $user_service->updateCommissionPartnerIssue($order_id);
                    // 更新用户的股东等级
                    $user_service->updatePartnerLevel($uid, $shop_id);
                }
                $order_model->commit();
            } catch (\Exception $e) {
                $order_model->rollback();
            }
        }
    }

    /**
     * ***********************************************订单的佣金计算--End******************************************************
     */

    /**
     * ***********************************************招商员的账户计算--Start******************************************************
     */
    /**
     * 招商员的订单佣金计算
     *
     * @param string $order_out_trade_no
     * @param number $order_id
     */
    private function AssistantOrderCommissionCalculate($order_out_trade_no = "", $order_id = 0)
    {
    }

    /**
     * 订单退款 更新佣金金额
     *
     * @param unknown $order_id
     */
    private function UpdateAssistantOrderCommissionRefund($order_id)
    {
        $Assistant_account_service = new NbsBusinessAssistantAccount();
        $Assistant_account_service->updateOrderBusinessAssistant($order_id);
    }

    /**
     * 订单交易完成发放订单的佣金
     *
     * @param unknown $order_id
     */
    private function UpdateAssistantOrderCommission($order_id)
    {
    }

    /**
     * ***********************************************招商员的账户计算--End******************************************************
     */
    /**
     * 查询店铺的退货设置
     * (non-PHPdoc)
     *
     * @see \data\api\IShop::getShopReturnSet()
     */
    public function getShopReturnSet($shop_id)
    {
        $shop_return     = new NsOrderShopReturnModel();
        $shop_return_obj = $shop_return->get($shop_id);
        if (empty($shop_return_obj)) {
            $data = array(
                "shop_id" => $shop_id,
                "create_time" => time()
            );
            $shop_return->save($data);
            $shop_return_obj = $shop_return->get($shop_id);
        }
        return $shop_return_obj;
    }

    /**
     *
     * 更新店铺的退货信息
     * (non-PHPdoc)
     *
     * @see \data\api\IShop::updateShopReturnSet()
     */
    public function updateShopReturnSet($shop_id, $address, $real_name, $mobile, $zipcode)
    {
        $shop_return = new NsOrderShopReturnModel();
        $data        = array(
            "shop_address" => $address,
            "seller_name" => $real_name,
            "seller_mobile" => $mobile,
            "seller_zipcode" => $zipcode,
            "modify_time" => time()
        );
        $result_id   = $shop_return->save($data, [
            "shop_id" => $shop_id
        ]);
        return $result_id;
    }

    /**
     * 得到订单的发货信息
     *
     * @param unknown $order_ids
     */
    public function getOrderGoodsExpressDetail($order_ids, $shop_id)
    {
        $order_goods_model   = new NsOrderGoodsModel();
        $order_model         = new NsOrderModel();
        $order_goods_express = new NsOrderGoodsExpressModel();
        // 查询订单的订单项的商品信息
        $order_goods_list = $order_goods_model->where(" order_id in ($order_ids)")->select();

        for ($i = 0; $i < count($order_goods_list); $i++) {
            $order_id                         = $order_goods_list[$i]["order_id"];
            $order_goods_id                   = $order_goods_list[$i]["order_goods_id"];
            $order_obj                        = $order_model->get($order_id);
            $order_goods_list[$i]["order_no"] = $order_obj["order_no"];
            $goods_express_obj                = $order_goods_express->where("FIND_IN_SET($order_goods_id,order_goods_id_array)")->select();
            if (!empty($goods_express_obj)) {
                $order_goods_list[$i]["express_company"] = $goods_express_obj[0]["express_company"];
                $order_goods_list[$i]["express_no"]      = $goods_express_obj[0]["express_no"];
            } else {
                $order_goods_list[$i]["express_company"] = "";
                $order_goods_list[$i]["express_no"]      = "";
            }
        }
        return $order_goods_list;
    }

    /**
     * 通过订单id 得到 该订单的发货物流
     *
     * @param unknown $order_id
     */
    public function getOrderGoodsExpressList($order_id)
    {
        $order_goods_express_model = new NsOrderGoodsExpressModel();
        $express_list              = $order_goods_express_model->getQuery([
            "order_id" => $order_id
        ], "*", "");
        return $express_list;
    }

    /**
     * 订单提货(non-PHPdoc)
     *
     * @see \data\api\IOrder::pickupOrder()
     */
    public function pickupOrder($order_id, $buyer_name, $buyer_phone, $remark)
    {
        $order  = new OrderBusiness();
        $retval = $order->pickupOrder($order_id, $buyer_name, $buyer_phone, $remark);
        return $retval;
    }

    /**
     * 查询订单项的物流信息
     *
     * @param unknown $order_goods_id
     */
//    public function getOrderGoodsExpressMessage($express_id)
//    {
//        try {
//            $order_express_model = new NsOrderGoodsExpressModel();
//            $express_obj         = $order_express_model->get($express_id);
//            if (!empty($express_obj)) {
//                $order_id    = $express_obj["order_id"];
//                $order_model = new NsOrderModel();
//                // 订单编号
//                $order_obj = $order_model->get($order_id);
//                $order_no  = $order_obj["order_no"];
//                $shop_id   = $order_obj["shop_id"];
//                // 物流公司信息
//                $express_company_id    = $express_obj["express_company_id"];
//                $express_company_model = new NsOrderExpressCompanyModel();
//                $express_company_obj   = $express_company_model->get($express_company_id);
//                // 快递公司编号
//                $express_no = $express_company_obj["express_no"];
//                // 快递单号
//                $send_no = $express_obj["express_no"];
//
//                // 快递接口配置
//                $config         = new Config();
//                $express_config = $config->getOrderExpressMessageConfig($shop_id);
//                if ($express_config["value"]["type"] == 1) {
//                    // 快递鸟
//                    $kdniao = new Kdniao($shop_id);
//                    $data   = array(
//                        "OrderCode" => $order_no,
//                        "ShipperCode" => $express_no,
//                        "LogisticCode" => $send_no
//                    );
//                    $result = $kdniao->getOrderTracesByJson(json_encode($data));
//                } else
//                    if ($express_config["value"]["type"] == 2) {
//                        // 快递100
//                        $snoopy = new Snoopy();
//                        $result = $snoopy->getOrderTracesJson($express_no, $send_no, $shop_id);
//                    }
//
//                // 订单操作
//                $order_time_arr = $order_model->getInfo([
//                    'order_id' => $express_obj["order_id"]
//                ], "create_time,pay_time,consign_time");
//                $retval         = array();
//                if (!empty($order_time_arr['create_time'])) {
//                    $retval[0] = array(
//                        "AcceptTime" => date("Y-m-d H:i:s", $order_time_arr['create_time']),
//                        "AcceptStation" => "您的订单已提交，请尽快完成支付"
//                    );
//                    if (!empty($order_time_arr['pay_time'])) {
//                        $retval[1] = array(
//                            "AcceptTime" => date("Y-m-d H:i:s", $order_time_arr['pay_time']),
//                            "AcceptStation" => "您的订单已支付完成，请等待卖家发货"
//                        );
//                        if (!empty($order_time_arr['consign_time'])) {
//                            $retval[2] = array(
//                                "AcceptTime" => date("Y-m-d H:i:s", $order_time_arr['consign_time']),
//                                "AcceptStation" => "您的订单已发货"
//                            );
//                        }
//                    }
//                }
//                $express_info = json_decode($result, true);
//
//                if (!empty($express_info)) {
//                    if ($express_config["value"]["type"] == 1) {
//                        // 快递鸟
//                        if ($express_info["Success"]) {
//                            foreach ($express_info['Traces'] as $v) {
//                                array_push($retval, $v);
//                            }
//                        } else {
//                            return array(
//                                "Success" => false,
//                                "Reason" => $express_info['Reason']
//                            );
//                        }
//                    } else
//                        if ($express_config["value"]["type"] == 2) {
//                            // 快递100
//                            if (!empty($express_info["status"]) && $express_info["status"] == 1) {
//                                foreach ($express_info['data'] as $v) {
//                                    $v_arr                  = array();
//                                    $v_arr["AcceptTime"]    = $v["time"];
//                                    $v_arr["AcceptStation"] = $v["context"];
//                                    array_push($retval, $v_arr);
//                                }
//                            } else {
//                                return array(
//                                    "Success" => false,
//                                    "Reason" => $express_info['message']
//                                );
//                            }
//                        }
//                }
//                return array(
//                    "Success" => true,
//                    "Reason" => "",
//                    "Traces" => array_reverse($retval)
//                );
//            } else {
//                return array(
//                    "Success" => false,
//                    "Reason" => "订单物流信息有误!"
//                );
//            }
//        } catch (\Exception $e) {
//            return array(
//                "Success" => false,
//                "Reason" => "订单物流信息有误!"
//            );
//        }
//    }

    public function getOrderGoodsExpressMessage($express_id)
    {
        try {
            $order_express_model = new NsOrderGoodsExpressModel();
            $express_obj         = $order_express_model->get($express_id);
            if (!empty($express_obj)) {
                // 物流公司信息
                $express_company_id    = $express_obj["express_company_id"];
                $express_company_model = new NsOrderExpressCompanyModel();
                $express_company_obj   = $express_company_model->get($express_company_id);
                // 快递公司编号
                $express_no = $express_company_obj["hundred_express_no"];
                // 快递单号
                $send_no = $express_obj["express_no"];
                // 快递100
                $snoopy = new Snoopy();
                $result = $snoopy->getOrderTracesJson($express_no, $send_no);
                $express_info = json_decode($result, true);
                $retval = array();
                if (!empty($express_info)) {
                    if (!empty($express_info["status"]) && $express_info["status"] == 200) {
                        foreach ($express_info['data'] as $v) {
                            $v_arr                  = array();
                            $v_arr["AcceptTime"]    = $v["time"];
                            $v_arr["AcceptStation"] = $v["context"];
                            array_push($retval, $v_arr);
                        }
                    } else {
                        return array(
                            "Success" => false,
                            "Reason" => $express_info['message']
                        );
                    }
                }
                // 订单操作
                $order_model = new NsOrderModel();
                $order_time_arr = $order_model->getInfo([
                    'order_id' => $express_obj["order_id"]
                ], "create_time,pay_time,consign_time");
                if (!empty($order_time_arr['consign_time'])) {
                    $retval[] = array(
                        "AcceptTime" => date("Y-m-d H:i:s", $order_time_arr['consign_time']),
                        "AcceptStation" => "您的订单已发货"
                    );
                }
                if (!empty($order_time_arr['pay_time'])) {
                    $retval[] = array(
                        "AcceptTime" => date("Y-m-d H:i:s", $order_time_arr['pay_time']),
                        "AcceptStation" => "您的订单已支付完成，请等待卖家发货"
                    );

                }
                if (!empty($order_time_arr['create_time'])) {
                    $retval[] = array(
                        "AcceptTime" => date("Y-m-d H:i:s", $order_time_arr['create_time']),
                        "AcceptStation" => "您的订单已提交，请尽快完成支付"
                    );
                }
                return array(
                    "Success" => true,
                    "Reason" => "",
                    "Traces" => $retval
                );
            } else {
                return array(
                    "Success" => false,
                    "Reason" => "订单物流信息有误!"
                );
            }
        } catch (\Exception $e) {
            return array(
                "Success" => false,
                "Reason" => "订单物流信息有误!"
            );
        }
    }

    /**
     * 添加卖家对订单的备注
     *
     * @param unknown $order_goods_id
     */
    public function addOrderSellerMemo($order_id, $memo)
    {
        $order  = new NsOrderModel();
        $data   = array(
            'seller_memo' => $memo
        );
        $retval = $order->save($data, [
            'order_id' => $order_id
        ]);
        return $retval;
    }

    /**
     * 获取订单备注信息
     *
     * @ERROR!!!
     *
     * @see \data\api\IOrder::getOrderRemark()
     */
    public function getOrderSellerMemo($order_id)
    {
        $order       = new NsOrderModel();
        $res         = $order->getQuery([
            'order_id' => $order_id
        ], "seller_memo", '');
        $seller_memo = "";
        if (!empty($res[0]['seller_memo'])) {
            $seller_memo = $res[0]['seller_memo'];
        }
        return $seller_memo;
    }

    /**
     * 得到订单的收货地址
     *
     * @param unknown $order_id
     * @return unknown
     */
    public function getOrderReceiveDetail($order_id)
    {
        $order = new NsOrderModel();
        $res   = $order->getInfo([
            'order_id' => $order_id
        ], "order_id,receiver_mobile,receiver_province,receiver_city,receiver_district,receiver_address,receiver_zip,receiver_name,fixed_telephone", '');
        return $res;
    }

    /**
     * 更新订单的收货地址
     *
     * @param unknown $order_id
     * @param unknown $receiver_mobile
     * @param unknown $receiver_province
     * @param unknown $receiver_city
     * @param unknown $receiver_district
     * @param unknown $receiver_address
     * @param unknown $receiver_zip
     * @param unknown $receiver_name
     */
    public function updateOrderReceiveDetail($order_id, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $fixed_telephone = "")
    {
        $order  = new NsOrderModel();
        $data   = array(
            'receiver_mobile' => $receiver_mobile,
            'receiver_province' => $receiver_province,
            'receiver_city' => $receiver_city,
            'receiver_district' => $receiver_district,
            'receiver_address' => $receiver_address,
            'receiver_zip' => $receiver_zip,
            'receiver_name' => $receiver_name,
            'fixed_telephone' => $fixed_telephone
        );
        $retval = $order->save($data, [
            'order_id' => $order_id
        ]);
        return $retval;
    }

    /**
     * 获取自提点运费
     *
     * @param unknown $goods_sku_list
     */
    public function getPickupMoney($goods_sku_list_price)
    {
        $goods_preference = new GoodsPreference();
        $pick_money       = $goods_preference->getPickupMoney($goods_sku_list_price);
        return $pick_money;
    }

    public function getOrderNumByOrderStatu($condition)
    {
        $order = new NsOrderModel();
        return $order->getCount($condition);
    }

    /**
     * 评论送积分
     */
    public function commentPoint($order_id)
    {
        // 给记录表添加记录
        $goods_comment = new NsGoodsCommentModel();
        $rewardRule    = new PromoteRewardRule();
        // 查询评论赠送积分数量，然后叠加
        $shop_id = $this->instance_id;
        $uid     = $this->uid;
        $info    = $rewardRule->getRewardRuleDetail($shop_id);
        $data    = array(
            'shop_id' => $shop_id,
            'uid' => $uid,
            'order_id' => $order_id,
            'status' => 1,
            'number' => $info['comment_point'],
            'create_time' => time()
        );
        $retval  = $goods_comment->save($data);
        if ($retval > 0) {
            // 给总记录表加记录
            $result = $rewardRule->addMemberPointData($shop_id, $uid, $info['comment_point'], 20, '评论赠送积分');
        }
    }

    /**
     *
     * 查询会员的某个订单的条数
     * (non-PHPdoc)
     *
     * @see \data\api\IOrder::getUserOrderDetailCount()
     */
    public function getUserOrderDetailCount($user_id, $order_id)
    {
        $order_count = 0;
        $orderModel  = new NsOrderModel();
        $condition   = array(
            "buyer_id" => $user_id,
            "order_id" => $order_id
        );
        $order_count = $orderModel->getCount($condition);
        return $order_count;
    }

    /**
     * 查询会员某个条件的订单的条数
     *
     * @param unknown $condition
     * @return \data\model\unknown
     */
    public function getUserOrderCountByCondition($condition)
    {
        $order_count = 0;
        $orderModel  = new NsOrderModel();
        $order_count = $orderModel->getCount($condition);
        return $order_count;
    }

    /**
     * 查询会员某个条件下的订单商品数量
     *
     * @param unknown $condition
     * @return \data\model\unknown
     */
    public function getUserOrderGoodsCountByCondition($condition)
    {
        $order_goods = new NsOrderGoodsModel();
        $order_count = $order_goods->getCount($condition);
        return $order_count;
    }

    /**
     * 删除订单
     *
     * @param unknown $order_id
     *            订单id
     * @param unknown $operator_type
     *            操作人类型 1商家 2用户
     * @param unknown $operator_id
     *            操作人id
     */
    public function deleteOrder($order_id, $operator_type, $operator_id)
    {
        $order_model    = new NsOrderModel();
        $data           = array(
            "is_deleted" => 1,
            "operator_type" => $operator_type,
            "operator_id" => $operator_id
        );
        $order_id_array = explode(',', $order_id);
        if ($operator_type == 1) {
            // 商家删除 目前之针对已关闭订单
            $res = $order_model->save($data, [
                "order_status" => 5,
                "order_id" => [
                    "in",
                    $order_id_array
                ],
                "shop_id" => $operator_id
            ]);
        } elseif ($operator_type == 2) {
            // 用户删除
            $res = $order_model->save($data, [
                "order_status" => 5,
                "order_id" => [
                    "in",
                    $order_id_array
                ],
                "buyer_id" => $operator_id
            ]);
        }
        return 1;
    }

    public function modifyOrderAddress($order_no)
    {
        $member             = new Member();
        $address            = $member->getDefaultExpressAddress();
        $address_info_array = explode("&nbsp;", $address["address_info"]);

        # 地址修改推送oms
        $client = (new OrderModify())
            ->setTid($order_no)
            ->setReceiverName($address["consigner"])
            ->setReceiverZip($address["zip_code"])
            ->setReceiverMobile($address['mobile'])
            ->setReceiverAddress($address['address'])
            ->setProvinceName($address_info_array[0])
            ->setCityName($address_info_array[1])
            ->setDistrictName($address_info_array[2]);
        $ret    = omsSdk($client);

        if ($ret['code'] == 0) {
            $order_model = new NsOrderModel();
            $order_model->save(
                [
                    "receiver_province" => $address["province"],
                    "receiver_city"     => $address["city"],
                    "receiver_district" => $address["district"],
                    "receiver_address"  => $address["address"],
                    "receiver_name"     => $address["consigner"],
                    "receiver_mobile"   => $address["mobile"],
                    "receiver_zip"      => $address["zip_code"]

                ],
                ["order_no" => $order_no]
            );
            return 1;
        } else {
            return 0;
        }

    }

    /**
     * 根据外部交易号查询订单编号，为了兼容多店版。所以返回一个数组
     * 创建时间：2017年10月9日 18:27:19 王永杰
     *
     * @ERROR!!!
     *
     * @see \data\api\IOrder::getOrderNoByOutTradeNo()
     */
    public function getOrderNoByOutTradeNo($out_trade_no)
    {
        if (!empty($out_trade_no)) {
            $order_model = new NsOrderModel();
            $list        = $order_model->getQuery([
                'out_trade_no' => $out_trade_no
            ], 'order_no', '');
            return $list;
        }
        return [];
    }

    /**
     *
     * 根据外部交易号查询订单状态
     * 创建时间：2017年10月13日 14:32:02 王永杰
     *
     * @ERROR!!!
     *
     * @see \data\api\IOrder::getOrderStatusByOutTradeNo()
     */
    public function getOrderStatusByOutTradeNo($out_trade_no)
    {
        if (!empty($out_trade_no)) {
            $order_model  = new NsOrderModel();
            $order_status = $order_model->getInfo([
                'out_trade_no' => $out_trade_no
            ], 'order_status', '');
            return $order_status;
        }
        return 0;
    }

    /**
     * 根据订单查询付款方式，，用于进行退款操作时，选择退款方式
     * 创建时间：2017年10月16日 10:10:56 王永杰
     *
     * @ERROR!!!
     *
     * @see \data\api\IOrder::getTermsOfPaymentByOrderId()
     */
    public function getTermsOfPaymentByOrderId($order_id)
    {
        if (!empty($order_id)) {
            $order_model = new NsOrderModel();
            $order_info  = $order_model->getInfo([
                'order_id' => $order_id
            ], "out_trade_no,pay_money");

            // 如果订单实际支付金额为0，则只能进行线下
            if ($order_info['pay_money'] == 0) {
                return 10; // 线下退款id为10
            }
            // 准确的查询出付款方式
            $ns_order_payment_model = new NsOrderPaymentModel();
            $pay_type               = $ns_order_payment_model->getInfo([
                "out_trade_no" => $order_info['out_trade_no']
            ], 'pay_type');

            return $pay_type['pay_type'];
        }
        return 0;
    }

    /**
     * 根据订单项id查询订单退款账户记录
     * 创建时间：2017年10月18日 17:32:30 王永杰
     *
     * @ERROR!!!
     *
     * @see \data\api\IOrder::getOrderRefundAccountRecordsByOrderGoodsId()
     */
    public function getOrderRefundAccountRecordsByOrderGoodsId($order_goods_id)
    {
        $model = new NsOrderRefundAccountRecordsModel();
        $info  = $model->getInfo([
            "order_goods_id" => $order_goods_id
        ], "*");
        return $info;
    }

    public function getOrderRefundAccountRecordsById($id)
    {
        $model = new NsOrderRefundAccountRecordsModel();
        $info  = $model->getInfo([
            "id" => $id
        ], "*");
        return $info;
    }

    /**
     * 获取快递单打印内容
     *
     * @param unknown $order_ids
     * @param unknown $shop_id
     */
    public function getOrderPrint($order_ids, $shop_id)
    {
        $order_goods_model   = new NsOrderGoodsModel();
        $order_model         = new NsOrderModel();
        $order_goods_express = new NsOrderGoodsExpressModel();
        // 查询订单的订单项的商品信息
        $order_id_array   = explode(',', $order_ids);
        $order_goods_list = array();
        foreach ($order_id_array as $order_id) {
            $order_express_list = $order_goods_express->getQuery([
                "order_id" => $order_id
            ], "*", "");
            if (!empty($order_express_list) && count($order_express_list) > 0) {
                $express_order_goods_ids = "";
                foreach ($order_express_list as $order_express_obj) {
                    $order_goods_id_array = $order_express_obj["order_goods_id_array"];
                    if (!empty($express_order_goods_ids)) {
                        $express_order_goods_ids .= "," . $order_goods_id_array;
                    } else {
                        $express_order_goods_ids = $order_goods_id_array;
                    }
                    $order_goods_list_print = $order_goods_model->where("FIND_IN_SET(order_goods_id, '$order_goods_id_array') and order_id=$order_id and shop_id=$shop_id")->select();
                    $order_print_item       = $this->dealPrintOrderGoodsList($order_id, $order_goods_list_print, 1, $order_express_obj["express_company_id"], $order_express_obj["express_name"], $order_express_obj["express_no"], $order_express_obj["id"]);
                    $order_goods_list[]     = $order_print_item;
                }
                $order_goods_list_print = $order_goods_model->where("FIND_IN_SET(order_goods_id, '$express_order_goods_ids')=0 and order_id=$order_id and shop_id=$shop_id")->select();
                if (!empty($order_goods_list_print) && count($order_goods_list_print) > 0) {
                    $order_print_item   = $this->dealPrintOrderGoodsList($order_id, $order_goods_list_print, 0, 0, "", "");
                    $order_goods_list[] = $order_print_item;
                }
            } else {
                $order_goods_list_print = $order_goods_model->where("order_id=$order_id and shop_id=$shop_id")->select();
                $order_print_item       = $this->dealPrintOrderGoodsList($order_id, $order_goods_list_print, 0, 0, "", "");
                $order_goods_list[]     = $order_print_item;
            }
        }
        return $order_goods_list;
    }

    /**
     * 处理订单打印数据
     *
     * @param unknown $order_id
     * @param unknown $order_goods_list_print
     * @param unknown $is_express
     * @param unknown $express_company_id
     * @param unknown $express_company_name
     * @param unknown $express_no
     * @param number $express_id
     */
    public function dealPrintOrderGoodsList($order_id, $order_goods_list_print, $is_express, $express_company_id, $express_company_name, $express_no, $express_id = 0)
    {
        $order_goods_item_obj   = array();
        $order_goods_ids        = "";
        $print_goods_array      = array();
        $is_print               = 1;
        $tmp_express_company    = "";
        $tmp_express_company_id = 0;
        $tmp_express_no         = "";
        foreach ($order_goods_list_print as $k => $order_goods_print_obj) {
            $print_goods_array[] = array(
                "goods_id" => $order_goods_print_obj["goods_id"],
                "goods_name" => $order_goods_print_obj["goods_name"],
                "sku_id" => $order_goods_print_obj["sku_id"],
                "sku_name" => $order_goods_print_obj["sku_name"]
            );
            if (!empty($order_goods_ids)) {
                $order_goods_ids = $order_goods_ids . "," . $order_goods_print_obj["order_goods_id"];
            } else {
                $order_goods_ids = $order_goods_print_obj["order_goods_id"];
            }
            if ($k == 0) {
                $tmp_express_company    = $order_goods_print_obj["tmp_express_company"];
                $tmp_express_company_id = $order_goods_print_obj["tmp_express_company_id"];
                $tmp_express_no         = $order_goods_print_obj["tmp_express_no"];
            }
        }
        if (empty($tmp_express_company) || empty($tmp_express_company_id) || empty($tmp_express_no)) {
            $is_print = 0;
        }
        if ($is_express == 0) {
            $express_company_id   = $tmp_express_company_id;
            $express_company_name = $tmp_express_company;
            $express_no           = $tmp_express_no;
        }
        $order_model          = new NsOrderModel();
        $order_obj            = $order_model->get($order_id);
        $order_goods_item_obj = array(
            "order_id" => $order_id,
            "order_goods_ids" => $order_goods_ids,
            "goods_array" => $print_goods_array,
            "is_devlier" => $is_express,
            "is_print" => $is_print,
            "express_company_id" => $express_company_id,
            "express_company_name" => $express_company_name,
            "express_no" => $express_no,
            "order_no" => $order_obj["order_no"],
            "express_id" => $express_id
        );
        return $order_goods_item_obj;
    }

    /**
     * 通过订单id获取未发货订单项
     *
     * @param unknown $order_ids
     */
    public function getNotshippedOrderByOrderId($order_ids)
    {
        $order_goods_model = new NsOrderGoodsModel();
        $order_id_array    = explode(',', $order_ids);
        $order_goods_list  = array();
        foreach ($order_id_array as $order_id) {
            $order_goods_list_print = $order_goods_model->getQuery([
                "order_id" => $order_id,
                "shipping_status" => 0,
                "refund_status" => 0
            ], "*", "");
            $order_goods_item       = $this->dealPrintOrderGoodsList($order_id, $order_goods_list_print, 0, $order_goods_list_print[0]["tmp_express_company_id"], $order_goods_list_print[0]["tmp_express_company"], $order_goods_list_print[0]["tmp_express_no"]);
            $order_goods_list[]     = $order_goods_item;
        }
        return $order_goods_list;
    }

    /**
     * 根据外部交易号查询订单编号并推送
     */
    public function pushOrderIdByOutTradeNo($out_trade_no)
    {
        $order_model = new NsOrderModel();
        $order_info  = $order_model->getInfo([
            'out_trade_no' => $out_trade_no
        ], 'order_id,from_plafrom');
        if (!empty($order_info)) {
            $ret = order_refund_push(1, $order_info['order_id'], $order_info['from_plafrom']);
        }
    }

    /**
     * 根据外部交易号查询心意卡card_token并推送
     */
    // public function pushCardToken($out_trade_no)
    // {
    //     $order_model = new NsOrderModel();
    //     $order_info = $order_model->getInfo([
    //         'out_trade_no' => $out_trade_no
    //     ], 'order_id,is_use_card');

    //     if($order_info && $order_info['is_use_card'] == 1){
    //         $orderCard = new OrderCard();
    //         $data_card = $orderCard->getCard(['order_id' => $order_info['order_id']]);
    //         if($data_card){
    //             $url = XING_SERVICE_HOST;
    //             print_r($url);exit;
    //             $post_data['card_token'] = $data_card['card_token'];
    //             $post_data['timestamp']   = time();
    //             $post_data['sign'] = md5($post_data['card_token'].$post_data['timestamp'].'shopalbc2018');
    //             $ret = json_decode(curl_post($url,$post_data));
    //             if($ret->code == 0){
    //                 $result = $orderCard->updateCard(['status' => 1], ['id' => $data_card['id']]);
    //             }
    //             return $ret;
    //         }
    //     }
    // }

    /**
     * 根据order_no获取order_id
     */
    public function getOrderId($order_no)
    {
        $order_model = new NsOrderModel();
        $order_info  = $order_model->getInfo([
            'order_no' => $order_no
        ], 'order_id');
        return $order_info;
    }

    public function getCountData($days)
    {
        #下单率


        #点击数 clicks


        #支付成功率
        #pay_status 2

    }
}
