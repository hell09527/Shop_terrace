<?php
/**
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
namespace data\model;

use data\model\BaseModel as BaseModel;
/**
 * 订单主表
 *   order_id int(11) NOT NULL AUTO_INCREMENT COMMENT '订单id',
 order_type tinyint(4) NOT NULL DEFAULT 1 COMMENT '订单类型',
 out_trade_no varchar(100) NOT NULL DEFAULT '0' COMMENT '外部交易号',
 payment_type tinyint(4) NOT NULL DEFAULT 0 COMMENT '支付类型。取值范围：
 WEIXIN (微信自有支付)
 WEIXIN_DAIXIAO (微信代销支付)
 ALIPAY (支付宝支付)',
 shipping_type tinyint(4) NOT NULL DEFAULT 1 COMMENT '订单配送方式',
 order_from varchar(255) NOT NULL DEFAULT '' COMMENT '订单来源',
 buyer_id int(11) NOT NULL COMMENT '买家id',
 user_name varchar(50) NOT NULL DEFAULT '' COMMENT '买家会员名称',
 pay_time datetime NOT NULL COMMENT '订单付款时间',
 buyer_ip varchar(20) NOT NULL DEFAULT '' COMMENT '买家ip',
 buyer_message varchar(255) NOT NULL DEFAULT '' COMMENT '买家附言',
 buyer_invoice varchar(255) NOT NULL DEFAULT '' COMMENT '买家发票信息',
 shipping_time datetime NOT NULL COMMENT '买家要求配送时间',
 sign_time datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '买家签收时间',
 receiver_mobile varchar(11) NOT NULL DEFAULT '' COMMENT '收货人的手机号码',
 receiver_province int(11) NOT NULL COMMENT '收货人所在省',
 receiver_city int(11) NOT NULL COMMENT '收货人所在城市',
 receiver_district int(11) NOT NULL COMMENT '收货人所在街道',
 receiver_address varchar(255) NOT NULL DEFAULT '' COMMENT '收货人详细地址',
 receiver_zip varchar(6) NOT NULL DEFAULT '' COMMENT '收货人邮编',
 receiver_name varchar(50) NOT NULL DEFAULT '' COMMENT '收货人姓名',
 shop_id int(11) NOT NULL COMMENT '卖家店铺id',
 shop_name varchar(100) NOT NULL DEFAULT '' COMMENT '卖家店铺名称',
 seller_star tinyint(4) NOT NULL DEFAULT 0 COMMENT '卖家对订单的标注星标',
 seller_memo varchar(255) NOT NULL DEFAULT '' COMMENT '卖家对订单的备注',
 consign_time datetime NOT NULL COMMENT '卖家发货时间',
 consign_time_adjust int(11) NOT NULL COMMENT '卖家延迟发货时间',
 goods_money decimal(19, 2) NOT NULL COMMENT '商品总价',
 order_money decimal(10, 2) NOT NULL COMMENT '订单总价',
 point int(11) NOT NULL COMMENT '订单消耗积分',
 point_money decimal(10, 2) NOT NULL COMMENT '订单消耗积分抵多少钱',
 coupon_money decimal(10, 2) NOT NULL COMMENT '订单代金券支付金额',
 coupon_id int(11) NOT NULL COMMENT '订单代金券id',
 user_money decimal(10, 2) NOT NULL COMMENT '订单预存款支付金额',
 promotion_money decimal(10, 2) NOT NULL COMMENT '订单优惠活动金额',
 shipping_money decimal(10, 2) NOT NULL COMMENT '订单运费',
 pay_money decimal(10, 2) NOT NULL COMMENT '订单实付金额',
 refund_money decimal(10, 2) NOT NULL COMMENT '订单退款金额',
 give_point int(11) NOT NULL COMMENT '订单赠送积分',
 order_status tinyint(4) NOT NULL COMMENT '订单状态',
 pay_status tinyint(4) NOT NULL COMMENT '订单付款状态',
 shipping_status tinyint(4) NOT NULL COMMENT '订单配送状态',
 review_status tinyint(4) NOT NULL COMMENT '订单评价状态',
 feedback_status tinyint(4) NOT NULL COMMENT '订单维权状态',
 promotion_details varchar(255) NOT NULL DEFAULT '' COMMENT '订单使用到的优惠活动详情',
 coupon_details varchar(255) NOT NULL DEFAULT '' COMMENT '订单使用到的代金券详情',
 create_time datetime NOT NULL DEFAULT 'CURRENT_TIMESTAMP' COMMENT '订单创建时间',
 finish_time datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '订单完成时间',
 */
class NsOrderModel extends BaseModel {

    protected $table = 'ns_order';
    protected $rule = [
        'order_id'  =>  '',
    ];
    protected $msg = [
        'order_id'  =>  '',
    ];

    public function getDiscountOrder($condition)
    {
        $list = $this->alias('no')
        ->join('ns_order_goods nog','no.order_id = nog.order_id','left')
        ->join('ns_promotion_discount_goods npdg','nog.goods_id = npdg.goods_id','left')
        ->field('no.order_id')
        ->where($condition)
        ->select();
        return $list;
    }

    public function getOrderBuyer($condition)
    {
        $list = $this->alias('no')
        ->join('ns_member nm','no.buyer_id = nm.uid','left')
        ->join('sys_user su',' nm.uid = su.uid','left')
        ->field('no.order_id, no.order_no, no.buyer_message, no.order_status, nm.member_name,su.user_headimg')
        ->where($condition)
        ->select();
        return $list;
    }

    //累计成交订单笔数
    public function orderNumberCount($condition)
    {
        $count = $this->alias('no')->where($condition)->count();
        return $count;
    }

    //分润统计
    public function orderFractionSum($condition)
    {
        $fractionMoneySum = $this->alias('no')
            ->join('ns_order_goods nog','no.order_id = nog.order_id','left')
            ->where($condition)
            ->Sum('nog.direct_separation');
        return $fractionMoneySum;
    }

    //累计成交金额
    public function goodsMoneySum($condition)
    {
        $goodsMoneySum = $this->alias('no')
            ->join('ns_order_goods nog','no.order_id = nog.order_id','left')
            ->where($condition)
            ->Sum('nog.goods_money');
        return $goodsMoneySum;
    }

    //累计成交金额笔数
    public function goodsOrderSum($condition)
    {
        $goodsMoneySum = $this->alias('no')
            ->join('ns_order_goods nog','no.order_id = nog.order_id','left')
            ->where($condition)
            ->count();
        return $goodsMoneySum;
    }

    /**
     * @param $condition
     * @param $order
     * @return mixed
     * 获取用户订单详情
     * 客服系统
     */
    public function getUserOrderInfo($condition,$order)
    {
        $list = $this->alias('no')
            ->join('sys_city sc','sc.city_id = no.receiver_city','left')
            ->join('sys_province sp',' sp.province_id = no.receiver_province','left')
            ->join('sys_district sd',' sd.district_id = no.receiver_district','left')
            ->field('sp.province_name, sc.city_name, sd.district_name,no.receiver_address, no.order_status,no.pay_money,no.pay_time,no.create_time,no.shipping_money,no.promotion_money,no.buyer_message,no.order_no,no.order_id,no.receiver_name,no.receiver_mobile,no.seller_memo')
            ->where($condition)
            ->order($order)
            ->select();
        return $list;
    }

    public function getViewList($page_index, $page_size, $condition, $order){

        $queryList = $this->getViewQuery($page_index, $page_size, $condition, $order);
        $queryCount = $this->getViewCount($condition);
        $list = $this->setReturnList($queryList, $queryCount, $page_size);
        return $list;
    }

    /**
     * 获取列表
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param unknown $order
     * @return \data\model\multitype:number
     */
    public function getViewQuery($page_index, $page_size, $condition, $order)
    {
        //设置查询视图
        $viewObj = $this->alias('no')
            ->join('bc_distributor bd','bd.uid= no.source_distribution','left')
            ->field('no.*');
        $list = $this->viewPageQuery($viewObj, $page_index, $page_size, $condition, $order);
        return $list;
    }
    /**
     * 获取列表数量
     * @param unknown $condition
     * @return \data\model\unknown
     */
    public function getViewCount($condition)
    {
        $viewObj = $this->alias('no')
            ->join('bc_distributor bd','bd.uid= no.source_distribution','left')
            ->field('no.order_id');
        $count = $this->viewCount($viewObj,$condition);
        return $count;
    }
}