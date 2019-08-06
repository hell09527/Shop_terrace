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
 * 订单商品表
 *  order_goods_id int(11) DEFAULT NULL,
  order_id int(11) DEFAULT NULL,
  goods_id int(11) DEFAULT NULL,
  goods_name varchar(50) DEFAULT NULL,
  sku_id int(11) DEFAULT NULL,
  sku_name varchar(50) DEFAULT NULL,
  price decimal(19, 2) DEFAULT NULL,
  num varchar(255) DEFAULT NULL,
  adjust_money varchar(255) DEFAULT NULL,
  goods_money varchar(255) DEFAULT NULL,
  goods_picture varchar(255) DEFAULT NULL,
  shop_id int(11) DEFAULT NULL,
  buyer_id int(11) DEFAULT NULL,
  goods_type varchar(255) DEFAULT NULL,
  promotion_id int(11) DEFAULT NULL,
  promotion_type_id int(11) DEFAULT NULL,
  order_type varchar(255) DEFAULT NULL,
  order_status varchar(255) DEFAULT NULL,
  give_point varchar(255) DEFAULT NULL,
  shipping_status varchar(255) DEFAULT NULL,
  refund_time varchar(255) DEFAULT NULL,
  refund_type varchar(255) DEFAULT NULL,
  refund_require_money varchar(255) DEFAULT NULL,
  refund_reason varchar(255) DEFAULT NULL,
  refund_shipping_code varchar(255) DEFAULT NULL,
  refund_shipping_company varchar(255) DEFAULT NULL,
  refund_real_money varchar(255) DEFAULT NULL,
  refund_status varchar(255) DEFAULT NULL,
  memo varchar(255) DEFAULT NULL
 */
 
class NsOrderGoodsModel extends BaseModel {

    protected $table = 'ns_order_goods';
    protected $rule = [
        'order_goods_id'  =>  '',
    ];
    protected $msg = [
        'order_goods_id'  =>  '',
    ];

    public function getorderGoodsNum($where)
    {
      $num = $this->alias('nog')
      ->join('ns_order no','nog.order_id = no.order_id','left')
      ->where($where)
      ->Sum('nog.num');
      return $num;
    }

    //极选师商品销售排行榜
    public function getorderGoodsSales($where)
    {
        $list = $this->alias('nog')
            ->join('ns_order no','nog.order_id = no.order_id','left')
            ->join('ns_goods ng','nog.goods_id = ng.goods_id','left')
            ->join('sys_album_picture ng_sap', 'ng_sap.pic_id = ng.picture', 'left')
            ->where($where)
            ->field('nog.goods_id,SUM(nog.num) as sales,nog.goods_name,ng_sap.pic_cover_small')
            ->group('goods_id')
            ->order('sales desc')
            ->limit(10)
            ->select();
        return $list;
    }

    //直接分润
    public function directSeparation($where)
    {
        $direct_separation = $this->where($where)->Sum('direct_separation');
        return $direct_separation;
    }

    //间接分润
    public function indirectSeparation($where)
    {
        $indirect_separation = $this->where($where)->Sum('indirect_separation');
        return $indirect_separation;
    }


    /**
     * @param $where
     * @return mixed
     * 获取用户订单详情
     * 客服系统
     */
    public function getUserOrderInfo($where)
    {
        $list = $this->alias('nog')
            ->join('ns_goods ng','nog.goods_id = ng.goods_id','left')
            ->join('sys_album_picture ng_sap', 'ng_sap.pic_id = ng.picture', 'left')
            ->field('nog.goods_id,nog.goods_name,nog.sku_name,nog.price,nog.num,ng_sap.pic_cover,nog.refund_status')
            ->where($where)
            ->select();
        return $list;
    }
}