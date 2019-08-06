<?php
namespace data\model;

use data\model\BaseModel as BaseModel;
/**
 * 礼品赠送领取表
 * `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
 * `order_id` int(11) NOT NULL DEFAULT '0' COMMENT '订单id',
 * `uid` int(11) NOT NULL DEFAULT '0' COMMENT '会员id',
 * `action_status` int(1) NOT NULL COMMENT '操作（1：送；2：收）',
 * `action_time` int(11) DEFAULT '0' COMMENT '操作时间',
 * PRIMARY KEY (`id`)
 */
class NsGiftGiveGetModel extends BaseModel {

    protected $table = 'ns_gift_give_get';
    protected $rule = [
        'id'  =>  '',
    ];
    protected $msg = [
        'id'  =>  '',
    ];

    public function getGiftOrderGet($page_index, $page_size, $condition, $order){
        $count = $this->alias("nggg")
            ->join("ns_order no","nggg.order_id = no.order_id","left")
            ->field('no.*, nggg.id, nggg.action_time')
            ->where($condition)
            ->group('nggg.order_id')->count();
        $viewObj = $this->alias("nggg")
            ->join("ns_order no","nggg.order_id = no.order_id","left")
            ->field('no.*, nggg.id, nggg.action_time')
            ->group('nggg.order_id');
        $list = $this->viewPageQuery($viewObj, $page_index, $page_size, $condition, $order);
        if ($page_size == 0) {
            $page_count = 1;
        }else{
            if ($count % $page_size == 0) {
                $page_count = $count / $page_size;
            } else {
                $page_count = (int)($count / $page_size) + 1;
            }
        }
        return array(
            'data' => $list,
            'total_count' => $count,
            'page_count' => $page_count
        );
    }
}