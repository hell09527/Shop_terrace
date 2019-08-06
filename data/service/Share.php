<?php
/**
 * Created by PhpStorm.
 * User: xiantao
 * Date: 2018/8/17
 * Time: 下午4:30
 */

namespace data\service;
use data\model\BcShareModel;
use data\model\NsGoodsModel;
use data\service\Order\OrderGoods;
use data\model\AlbumPictureModel;
class Share extends BaseService
{
    function __construct()
    {
        parent::__construct();
    }

    //添加
    public function addShare($share_no, $share_id, $share_list)
    {
        $share = new BcShareModel();
        $data  = [
            'share_no'     => $share_no,
            'share_id'     => $share_id,
            'share_list' => $share_list
        ];
        $share->save($data);
        return $share->share_no;
    }

    //点击分享
    public function shareList($uid, $share_no){
        $share = new BcShareModel();
        $shareDetail = $share->getInfo(['share_no'=>$share_no]);
        if(!empty($shareDetail)){
            if($shareDetail['winner_id'] > 0 && $shareDetail['winner_id'] != $uid){
                $share_sku_array = explode(",", $shareDetail['share_list']);
                $list = Array();
                foreach($share_sku_array as $v){
                    $share_list = array();
                    $order_sku_list = explode(":", $v);
                    $sku_id = $order_sku_list[0];
                    $num = $order_sku_list[1];

                    // 获取商品sku信息
                    $goods_sku = new \data\model\NsGoodsSkuModel();
                    $sku_info = $goods_sku->getInfo([
                        'sku_id' => $sku_id
                    ], '*');

                    if (empty($sku_info)) {
                        continue;
                    }

                    $goods = new NsGoodsModel();
                    $goods_info = $goods->getInfo([
                        'goods_id' => $sku_info["goods_id"]
                    ], 'max_buy,state,point_exchange_type,point_exchange,picture,goods_id,goods_name,source_type');

                    if ($goods_info['state'] != 1) {
                        continue;
                    }

                    $share_list["stock"] = $sku_info['stock']; // 库存
                    $share_list["sku_id"] = $sku_info["sku_id"];
                    $share_list["sku_name"] = $sku_info["sku_name"];
                    $share_list["goods_id"] = $goods_info["goods_id"];
                    $share_list["goods_name"] = $goods_info["goods_name"];
                    $share_list["max_buy"] = $goods_info['max_buy']; // 限购数量
                    $share_list['point_exchange_type'] = $goods_info['point_exchange_type']; // 积分兑换类型 0 非积分兑换 1 只能积分兑换
                    $share_list['point_exchange'] = $goods_info['point_exchange']; // 积分兑换
                    $share_list["source_type"] = $goods_info["source_type"];

                    // 如果购买的数量超过限购，则取限购数量
                    if ($goods_info['max_buy'] != 0 && $goods_info['max_buy'] < $num) {
                        $num = $goods_info['max_buy'];
                    }
                    // 如果购买的数量超过库存，则取库存数量
                    if ($sku_info['stock'] < $num) {
                        $num = $sku_info['stock'];
                    }
                    $share_list["num"] = $num;
                    $share_list["price"] = $sku_info["promote_price"];

                    // 查询当前商品是否有SKU主图
                    $order_goods_service = new OrderGoods();
                    $picture = $order_goods_service->getSkuPictureBySkuId($sku_info);

                    // 获取图片信息
                    $album_picture_model = new AlbumPictureModel();
                    $picture_info = $album_picture_model->get($picture == 0 ? $goods_info['picture'] : $picture);
                    $share_list['picture_info'] = $picture_info;

                    if (count($share_list) == 0) {
                        continue;
                    }
                    $list[] = $share_list;
                }
            }else{
                // 更新数据
                if($uid != 104){
                    $share->save(['winner_id' => $uid],['share_no' => $shareDetail['share_no']]);
                }
                $share_sku_array = explode(",", $shareDetail['share_list']);
                $list = Array();
                foreach($share_sku_array as $v){
                    $share_list = array();
                    $order_sku_list = explode(":", $v);
                    $sku_id = $order_sku_list[0];
                    $num = $order_sku_list[1];
                    $price = $order_sku_list[2];

                    // 获取商品sku信息
                    $goods_sku = new \data\model\NsGoodsSkuModel();
                    $sku_info = $goods_sku->getInfo([
                        'sku_id' => $sku_id
                    ], '*');

                    if (empty($sku_info)) {
                        continue;
                    }

                    $goods = new NsGoodsModel();
                    $goods_info = $goods->getInfo([
                        'goods_id' => $sku_info["goods_id"]
                    ], 'max_buy,state,point_exchange_type,point_exchange,picture,goods_id,goods_name,source_type');

                    if ($goods_info['state'] != 1) {
                        continue;
                    }

                    $share_list["stock"] = $sku_info['stock']; // 库存
                    $share_list["sku_id"] = $sku_info["sku_id"];
                    $share_list["sku_name"] = $sku_info["sku_name"];
                    $share_list["goods_id"] = $goods_info["goods_id"];
                    $share_list["goods_name"] = $goods_info["goods_name"];
                    $share_list["max_buy"] = $goods_info['max_buy']; // 限购数量
                    $share_list['point_exchange_type'] = $goods_info['point_exchange_type']; // 积分兑换类型 0 非积分兑换 1 只能积分兑换
                    $share_list['point_exchange'] = $goods_info['point_exchange']; // 积分兑换
                    $share_list["source_type"] = $goods_info["source_type"];

                    // 如果购买的数量超过限购，则取限购数量
                    if ($goods_info['max_buy'] != 0 && $goods_info['max_buy'] < $num) {
                        $num = $goods_info['max_buy'];
                    }
                    // 如果购买的数量超过库存，则取库存数量
                    if ($sku_info['stock'] < $num) {
                        $num = $sku_info['stock'];
                    }
                    $share_list["num"] = $num;
                    $share_list["price"] = $price;

                    // 查询当前商品是否有SKU主图
                    $order_goods_service = new OrderGoods();
                    $picture = $order_goods_service->getSkuPictureBySkuId($sku_info);

                    // 获取图片信息
                    $album_picture_model = new AlbumPictureModel();
                    $picture_info = $album_picture_model->get($picture == 0 ? $goods_info['picture'] : $picture);
                    $share_list['picture_info'] = $picture_info;

                    if (count($share_list) == 0) {
                        continue;
                    }
                    $list[] = $share_list;
                }
            }
            return $list;
        }
    }
}