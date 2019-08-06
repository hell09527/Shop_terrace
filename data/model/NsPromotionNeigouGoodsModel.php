<?php
namespace data\model;

use data\model\BaseModel as BaseModel;
/**
 * 限时折扣商品表
 *  discount_goods_id int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
discount_id int(11) NOT NULL COMMENT '对应活动',
start_time datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '开始时间',
end_time datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '结束时间',
goods_id int(11) NOT NULL COMMENT '商品ID',
status tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态',
n_price varchar(50) NOT NULL COMMENT '内购价格',
PRIMARY KEY (discount_goods_id)
 */
class NsPromotionNeigouGoodsModel extends BaseModel {

    protected $table = 'ns_promotion_neigou_goods';
    protected $rule = [
        'discount_goods_id'  =>  '',
    ];
    protected $msg = [
        'discount_goods_id'  =>  '',
    ];

    /**
     * 查询商品的视图
     * @param unknown $condition
     * @param unknown $field
     * @param unknown $order
     * @return unknown
     */
    public function getGoodsViewQueryField($condition, $field, $order=""){
        $viewObj = $this->alias('npng')
            ->join('ns_goods_sku ngs','npng.sku_id = ngs.sku_id','left')
            ->field($field);
        $list = $viewObj->where($condition)
            ->order($order)
            ->select();
        return $list;
    }

}