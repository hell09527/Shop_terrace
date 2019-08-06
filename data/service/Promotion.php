<?php
/**
 * Promote.php
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
 * 营销
 */
use data\api\IPromotion;
use data\model\AlbumPictureModel as AlbumPictureModel;
use data\model\NsCouponGoodsModel as NsCouponGoodsModel;
use data\model\NsCouponModel as NsCouponModel;
use data\model\NsCouponTypeModel as NsCouponTypeModel;
use data\model\NsGoodsModel;
use data\model\NsGoodsSkuModel;
use data\model\NsPointConfigModel;
use data\model\NsPromotionDiscountGoodsModel;
use data\model\NsPromotionDiscountModel;
use data\model\NsPromotionFullMailModel;
use data\model\NsPromotionGiftGoodsModel;
use data\model\NsPromotionGiftModel;
use data\model\NsPromotionMansongGoodsModel;
use data\model\NsPromotionMansongModel;
use data\model\NsPromotionMansongRuleModel;
use data\model\NsPromotionNeigouGoodsModel;
use data\service\BaseService as BaseService;
use data\service\promotion\GoodsDiscount;
use data\service\promotion\GoodsMansong;
use data\model\NsComboPackagePromotionModel;
use data\model\NsGoodsViewModel;
use data\model\NsPromotionGamesModel;
use data\model\NsPromotionGameRuleModel;
use data\model\NsMemberLevelModel;
use data\model\NSPromotionGiftGrantRecordsModel;
use data\model\NsPromotionGameTypeModel;
use data\model\NsOrderModel;
use data\model\NsPromotionGamesWinningRecordsModel;
use data\model\UserModel;
use data\model\NsMemberAccountModel;
use data\service\Member\MemberAccount;
use data\service\Member\MemberCoupon;
use data\service\Order\OrderGoods;
use data\service\Order\Order as OrderService;
use data\model\NsOrderGoodsModel;

class Promotion extends BaseService implements IPromotion
{

    function __construct()
    {
        parent::__construct();
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromotion::getCouponTypeList()
     */
    public function getCouponTypeList($page_index = 1, $page_size = 0, $condition = '', $order = 'create_time asc')
    {
        $coupon_type      = new NsCouponTypeModel();
        $coupon_type_list = $coupon_type->pageQuery($page_index, $page_size, $condition, $order, 'coupon_type_id, coupon_name, money, count, max_fetch, at_least, need_user_level, range_type, start_time, end_time, create_time, update_time,is_show,send_type');
        foreach ($coupon_type_list['data'] as $key => $v) {
            $coupon_type_list['data'][$key]['send_type'] = $v['send_type'] == 1 ? '自主领取' : '消费获取';
            #已领用
            $condition1["uid"]                                = [
                [
                    "NEQ",
                    ''
                ]
            ];
            $condition1["coupon_type_id"]                     = [
                [
                    "EQ",
                    $v['coupon_type_id']
                ]
            ];
            $coupon_type_list['data'][$key]['coupon_count_1'] = \think\Db::name('ns_coupon')->where($condition1)->count();
            #已使用
            $coupon_type_list['data'][$key]['coupon_count_2'] = \think\Db::name('ns_coupon')->where(['state' => 2, 'coupon_type_id' => $v['coupon_type_id']])->count();
        }
        /*
         * if(!empty($coupon_type_list['data']))
         * foreach ($coupon_type_list['data'] as $k => $v)
         * {
         * if($v['range_type'] == 0) //部分产品
         * {
         * $coupon_goods = new NsCouponGoodsModel();
         * $goods_list = $coupon_goods->getCouponTypeGoodsList($v['coupon_type_id']);
         * $coupon_type_list['data'][$k]['goods_list'] = $goods_list;
         * }
         * }
         */
        //
        return $coupon_type_list;
        // TODO Auto-generated method stub
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromotion::deletecouponType()
     */
    public function deletecouponType($coupon_type_id)
    {
        $coupon      = new NsCouponModel();
        $coupon_type = new NsCouponTypeModel();
        $coupon_type->startTrans();
        try {
            $condition['coupon_type_id'] = $coupon_type_id;
            $condition['state']          = 1;
            $coupon_count                = $coupon->getcount($condition);
            if ($coupon_count > 0) {
                $coupon_type->rollback();
                return -1;
            }
            $coupon_type->destroy($coupon_type_id);
            $coupon_type->commit();
            return 1;
        } catch (\Exception $e) {
            $coupon_type->rollback();
            return $e->getMessage();
        }
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\ICoupon::getCouponTypeDetail()
     */
    public function getCouponTypeDetail($coupon_type_id)
    {
        $coupon_type  = new NsCouponTypeModel();
        $data         = $coupon_type->get($coupon_type_id);
        $coupon_goods = new NsCouponGoodsModel();
        $goods_list   = $coupon_goods->getCouponTypeGoodsList($coupon_type_id);
        $goods        = new NsGoodsModel();

        foreach ($goods_list as $k => $v) {
            $picture               = new AlbumPictureModel();
            $pic_info              = array();
            $pic_info['pic_cover'] = '';
            if (!empty($v['picture'])) {
                $pic_info = $picture->get($v['picture']);
            }
            $goods_info = $goods->getInfo([
                'goods_id' => $v['goods_id']
            ], 'price, stock,goods_type,category_id_1,material_code');

            $goods_list[$k]['picture_info']  = $pic_info;
            $goods_list[$k]['material_code'] = $goods_info['material_code'];
        }
        $data['goods_list'] = $goods_list;
        return $data;
        // TODO Auto-generated method stub
    }

    /*
     * (non-PHPdoc)
     *
     * @see \data\api\ICoupon::addCouponType()
     */
    public function addCouponType($coupon_name, $money, $count, $max_fetch, $at_least, $need_user_level, $range_type, $start_time, $end_time, $is_show, $is_show_detail, $goods_list, $send_type, $limit_repeated_use, $use_type, $get_after_days, $pay_money_get)
    {
        $coupon_type = new NsCouponTypeModel();
        $error       = 0;
        $coupon_type->startTrans();
        try {
            // 添加优惠券类型表
            /**
             * coupon_type_id int(11) NOT NULL AUTO_INCREMENT COMMENT '优惠券类型Id',
             * shop_id int(11) NOT NULL DEFAULT 1 COMMENT '店铺ID',
             * coupon_name varchar(50) NOT NULL DEFAULT '' COMMENT '优惠券名称',
             * money decimal(10, 2) NOT NULL COMMENT '发放面额',
             * count int(11) NOT NULL COMMENT '发放数量',
             * max_fetch int(11) NOT NULL DEFAULT 0 COMMENT '每人最大领取个数 0无限制',
             * at_least decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '满多少元使用 0代表无限制',
             * need_user_level tinyint(4) NOT NULL DEFAULT 0 COMMENT '领取人会员等级',
             * range_type tinyint(4) NOT NULL DEFAULT 1 COMMENT '使用范围0部分产品使用 1全场产品使用',
             * start_time datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '有效日期开始时间',
             * end_time datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '有效日期结束时间',
             * create_time datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '创建时间',
             */
            $data = array(
                'shop_id' => $this->instance_id,
                'coupon_name' => $coupon_name,
                'money' => $money,
                'count' => $count,
                'max_fetch' => $max_fetch,
                'at_least' => $at_least,
                'need_user_level' => $need_user_level,
                'range_type' => $range_type,
                'start_time' => getTimeTurnTimeStamp($start_time),
                'end_time' => getTimeTurnTimeStamp($end_time),
                'is_show' => $is_show,
                'is_show_detail' => $is_show_detail,
                'send_type' => $send_type,
                'limit_repeated_use' => $limit_repeated_use,
                'use_type' => $use_type,
                'get_after_days' => $get_after_days,
                'pay_money_get' => $pay_money_get,
                "create_time" => time()
            );
            $coupon_type->save($data);
            $coupon_type_id = $coupon_type->coupon_type_id;
            // 添加类型商品表
            if ($range_type == 0 && !empty($goods_list)) {
                $goods_list_array = explode(',', $goods_list);
                foreach ($goods_list_array as $k => $v) {
                    $data_coupon_goods = array(
                        'coupon_type_id' => $coupon_type_id,
                        'goods_id' => $v
                    );
                    $coupon_goods      = new NsCouponGoodsModel();
                    $retval            = $coupon_goods->save($data_coupon_goods);
                }
            }
            // 添加优惠券表
            if ($count > 0) {
                for ($i = 0; $i < $count; $i++) {
                    /**
                     * coupon_id int(11) NOT NULL AUTO_INCREMENT COMMENT '优惠券id',
                     * coupon_type_id int(11) NOT NULL COMMENT '优惠券类型id',
                     * shop_id int(11) NOT NULL COMMENT '店铺Id',
                     * coupon_code varchar(255) NOT NULL DEFAULT '' COMMENT '优惠券编码',
                     * uid int(11) NOT NULL COMMENT '领用人',
                     * use_order_id int(11) NOT NULL COMMENT '优惠券使用订单id',
                     * create_order_id int(11) NOT NULL DEFAULT 0 COMMENT '创建订单id(优惠券只有是完成订单发放的优惠券时才有值)',
                     * money decimal(10, 2) NOT NULL COMMENT '面额',
                     * fetch_time datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '领取时间',
                     * use_time datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '使用时间',
                     * state tinyint(4) NOT NULL DEFAULT 0 COMMENT '优惠券状态 0未领用 1已领用（未使用） 2已使用 3已过期',
                     */
                    $data_coupon = array(
                        'coupon_type_id' => $coupon_type_id,
                        'shop_id' => $this->instance_id,
                        'coupon_code' => time() . rand(111, 999),
                        'uid' => 0,
                        'create_order_id' => 0,
                        'money' => $money,
                        'state' => 0,
                        'send_type' => $send_type,
                        'limit_repeated_use' => $limit_repeated_use,
                        'use_type' => $use_type,
                        'get_after_days' => $get_after_days,
                        'pay_money_get' => $pay_money_get,
                        "start_time" => getTimeTurnTimeStamp($start_time),
                        "end_time" => getTimeTurnTimeStamp($end_time)
                    );
                    $coupon      = new NsCouponModel();
                    $retval      = $coupon->save($data_coupon);
                }
            }
            $coupon_type->commit();
            return 1;
        } catch (\Exception $e) {
            $coupon_type->rollback();
            return $e->getMessage();
        }
        return 0;
        // TODO Auto-generated method stub
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromotion::updateCouponType()
     */
    public function updateCouponType($coupon_type_id, $coupon_name, $money, $count, $repair_count, $max_fetch, $at_least, $need_user_level, $range_type, $start_time, $end_time, $is_show, $is_show_detail, $goods_list, $send_type, $limit_repeated_use, $use_type, $get_after_days, $pay_money_get)
    {
        $coupon_type = new NsCouponTypeModel();
        $error       = 0;
        $coupon_type->startTrans();
        try {
            // 更新优惠券类型表
            /**
             * coupon_type_id int(11) NOT NULL AUTO_INCREMENT COMMENT '优惠券类型Id',
             * shop_id int(11) NOT NULL DEFAULT 1 COMMENT '店铺ID',
             * coupon_name varchar(50) NOT NULL DEFAULT '' COMMENT '优惠券名称',
             * money decimal(10, 2) NOT NULL COMMENT '发放面额',
             * count int(11) NOT NULL COMMENT '发放数量',
             * max_fetch int(11) NOT NULL DEFAULT 0 COMMENT '每人最大领取个数 0无限制',
             * at_least decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '满多少元使用 0代表无限制',
             * need_user_level tinyint(4) NOT NULL DEFAULT 0 COMMENT '领取人会员等级',
             * range_type tinyint(4) NOT NULL DEFAULT 1 COMMENT '使用范围0部分产品使用 1全场产品使用',
             * start_time datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '有效日期开始时间',
             * end_time datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '有效日期结束时间',
             * create_time datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '创建时间',
             */
            $data = array(
                'shop_id' => $this->instance_id,
                'coupon_name' => $coupon_name,
                'money' => $money,
                'count' => $count + $repair_count,
                'max_fetch' => $max_fetch,
                'at_least' => $at_least,
                'need_user_level' => $need_user_level,
                'range_type' => $range_type,
                'send_type' => $send_type,
                'limit_repeated_use' => $limit_repeated_use,
                'use_type' => $use_type,
                'get_after_days' => $get_after_days,
                'pay_money_get' => $pay_money_get,
                'start_time' => getTimeTurnTimeStamp($start_time),
                'end_time' => getTimeTurnTimeStamp($end_time),
                'is_show' => $is_show,
                'is_show_detail' => $is_show_detail
            );
            $coupon_type->save($data, [
                'coupon_type_id' => $coupon_type_id
            ]);
            // 更新类型商品表
            $coupon_goods = new NsCouponGoodsModel();
            $coupon_goods->destroy([
                'coupon_type_id' => $coupon_type_id
            ]);
            if ($range_type == 0 && !empty($goods_list)) {
                $goods_list_array = explode(',', $goods_list);
                foreach ($goods_list_array as $k => $v) {
                    $data_coupon_goods = array(
                        'coupon_type_id' => $coupon_type_id,
                        'goods_id' => $v
                    );
                    $coupon_goods      = new NsCouponGoodsModel();
                    $retval            = $coupon_goods->save($data_coupon_goods);
                }
            }
            // 添加优惠券表
            if ($repair_count > 0) {
                for ($i = 0; $i < $repair_count; $i++) {
                    $data_coupon = array(
                        'coupon_type_id' => $coupon_type_id,
                        'shop_id' => $this->instance_id,
                        'coupon_code' => time() . rand(111, 999),
                        'uid' => 0,
                        'create_order_id' => 0,
                        'money' => $money,
                        'state' => 0,
                        'send_type' => $send_type,
                        'limit_repeated_use' => $limit_repeated_use,
                        'use_type' => $use_type,
                        'get_after_days' => $get_after_days,
                        'pay_money_get' => $pay_money_get,
                        'start_time' => getTimeTurnTimeStamp($start_time),
                        'end_time' => getTimeTurnTimeStamp($end_time)
                    );
                    $coupon      = new NsCouponModel();
                    $retval      = $coupon->save($data_coupon);
                }
            }
            // 修改优惠券时，更新优惠券的使用状态，金额
            $coupon                             = new NsCouponModel();
            $coupon_condition['state']          = array(
                'in',
                [
                    0,
                    3
                ]
            ); // 未领用或者已过期的优惠券
            $coupon_condition['coupon_type_id'] = $coupon_type_id;
            $coupon->save([
                'end_time' => getTimeTurnTimeStamp($end_time),
                'start_time' => getTimeTurnTimeStamp($start_time),
                'state' => 0,
                'money' => $money
            ], $coupon_condition);
            $coupon_type->commit();
            return 1;
        } catch (\Exception $e) {
            $coupon_type->rollback();
            return 0;
        }
        return 0;
        // TODO Auto-generated method stub
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\ICoupon::getTypeCouponList()
     */
    public function getTypeCouponList($coupon_type_id, $get_type = 0, $use_type = 0)
    {
        $coupon    = new NsCouponModel();
        $condition = array(
            'coupon_type_id' => $coupon_type_id,
            'state' => $use_type
        );
        $list      = $coupon->pageQuery(1, 0, $condition, '', '*');
        return $list['data'];
        // TODO Auto-generated method stub
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\ICoupon::useCoupon()
     */
    public function useCoupon($uid, $coupon_id, $order_id)
    {
        $coupon = new NsCouponModel();
        $data   = array(
            'use_order_id' => $order_id,
            'state' => 2
        );
        $res    = $coupon->save($data, [
            'coupon_id' => $coupon_id
        ]);
        return $res;
        // TODO Auto-generated method stub
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\ICoupon::getCouponDetail()
     */
    public function getCouponDetail($coupon_id)
    {
        // TODO Auto-generated method stub
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMemberAccountFlow::getPointConfig()
     */
    public function getPointConfig()
    {
        $point_model = new NsPointConfigModel();
        $count       = $point_model->where([
            'shop_id' => $this->instance_id
        ])->count();
        if ($count > 0) {
            $info = $point_model->get([
                'shop_id' => $this->instance_id
            ]);
        } else {
            $data        = array(
                'shop_id' => $this->instance_id,
                'is_open' => 0,
                'desc' => '',
                'create_time' => time()
            );
            $point_model = new NsPointConfigModel();
            $res         = $point_model->save($data);
            $info        = $point_model->get([
                'shop_id' => $this->instance_id
            ]);
        }

        return $info;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMemberAccountFlow::setPointConfig()
     */
    public function setPointConfig($convert_rate, $is_open, $desc)
    {
        $point_model = new NsPointConfigModel();
        $data        = array(
            'convert_rate' => $convert_rate,
            'is_open' => $is_open,
            'desc' => $desc,
            'modify_time' => time()
        );
        $retval      = $point_model->save($data, [
            'shop_id' => $this->instance_id
        ]);
        return $retval;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IPromote::getPromotionGiftList()
     */
    public function getPromotionGiftList($page_index = 1, $page_size = 0, $condition = '', $order = 'create_time desc')
    {
        $promotion_gift = new NsPromotionGiftModel();
        $list           = $promotion_gift->pageQuery($page_index, $page_size, $condition, $order, '*');
        if (!empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                #todo @dai 新增赠品替换
                $replace_info                   = \think\Db::name('ns_promotion_gift_replace')->where(['gift_id' => $v['gift_id']])->find();
                $list['data'][$k]['is_replace'] = 0;
                if (!empty($replace_info)) {
                    $list['data'][$k]['replace_name'] = \think\Db::name('ns_promotion_gift')->where(['gift_id' => $replace_info['n_gift_id']])->find()['gift_name'];
                    $list['data'][$k]['replace_num']  = $replace_info['n_num'];
                    $list['data'][$k]['is_replace']   = 1;
                }

                $start_time = $v['start_time'];
                $end_time   = $v['end_time'];
                if ($end_time < time()) {
                    $list['data'][$k]['type']      = 2;
                    $list['data'][$k]['type_name'] = '已结束';
                } elseif ($start_time > time()) {
                    $list['data'][$k]['type']      = 0;
                    $list['data'][$k]['type_name'] = '未开始';
                } elseif ($start_time <= time() && time() <= $end_time) {
                    $list['data'][$k]['type']      = 1;
                    $list['data'][$k]['type_name'] = '进行中';
                }
            }
        }
        return $list;
        // TODO Auto-generated method stub
    }

    /*
     * 添加赠品活动
     * 修改时间：2018年1月24日10:45:28
     * (non-PHPdoc)
     * @see \data\api\IPromote::addPromotionGift()
     */
    public function addPromotionGift($shop_id, $gift_name, $gift_num, $start_time, $end_time, $days, $max_num, $sku_id)
    {
        $promotion_gift = new NsPromotionGiftModel();
        $promotion_gift->startTrans();
        try {
            if (empty($gift_name)) {
                return array(
                    "code"    => 0,
                    "message" => '赠品活动名称不能为空'
                );
            }

            // 判断库存
            if (!empty($gift_num) && !empty($sku_id)) {
                $sku      = new NsGoodsSkuModel();
                $sku_info = $sku->getInfo([
                    'sku_id' => $sku_id
                ], 'sku_name,picture,stock');
                if ($gift_num > $sku_info['stock']) {
                    return GOODS_NUM_GIFT;
                }

            }

            $data_gift = array(
                'gift_name'   => $gift_name,
                'gift_num'    => $gift_num,
                'shop_id'     => $shop_id,
                'start_time'  => getTimeTurnTimeStamp($start_time),
                'end_time'    => getTimeTurnTimeStamp($end_time),
                'days'        => $days,
                'max_num'     => $max_num,
                'create_time' => time()
            );
            $promotion_gift->save($data_gift);

            $gift_id = $promotion_gift->gift_id;

            // 当前功能只能选择一种商品
            $promotion_gift_goods = new NsPromotionGiftGoodsModel();

            if (!empty($sku_id)) {
                $count = $promotion_gift_goods->getCount([
                    "sku_id" => $sku_id
                ]);
                if ($count > 0) {
                    return GOODS_HAVE_BEEN_GIFT;
                }
            }

            // 查询商品名称图片
            // $goods = new NsGoodsModel();
            // $goods_info = $goods->getInfo([
            //     'goods_id' => $goods_id
            // ], 'goods_name,picture');
            $data_sku = array(
                'gift_id'       => $gift_id,
                'sku_id'        => $sku_id,
                'sku_name'      => $sku_info['sku_name'],
                'sku_picture'   => $sku_info['picture']
            );

            $promotion_gift_goods->save($data_sku);
            $promotion_gift->commit();

            return $gift_id;
        } catch (\Exception $e) {
            $promotion_gift->rollback();
            return $e->getMessage();
        }
        // TODO Auto-generated method stub
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IPromote::updatePromotionGift()
     */
    public function updatePromotionGift($gift_id, $shop_id, $gift_name, $gift_num, $start_time, $end_time, $days, $max_num, $sku_id)
    {
        $promotion_gift = new NsPromotionGiftModel();
        $promotion_gift->startTrans();
        try {
            if (empty($gift_name)) {
                return array(
                    "code" => 0,
                    "message" => '赠品活动名称不能为空'
                );
            }
            $data_gift = array(
                'gift_name'   => $gift_name,
                'gift_num'    => $gift_num,
                'shop_id'     => $shop_id,
                'start_time'  => getTimeTurnTimeStamp($start_time),
                'end_time'    => getTimeTurnTimeStamp($end_time),
                'days'        => $days,
                'max_num'     => $max_num,
                'modify_time' => time()
            );
            $promotion_gift->save($data_gift, [
                'gift_id' => $gift_id
            ]);
            // 当前功能只能选择一种商品
            $promotion_gift_goods = new NsPromotionGiftGoodsModel();

            if (!empty($goods_id)) {
                $count = $promotion_gift_goods->getCount([
                    "sku_id"  => $sku_id,
                    "gift_id" => array("neq", $gift_id)
                ]);
                if ($count > 0) {
                    return GOODS_HAVE_BEEN_GIFT;
                }
            }

            $promotion_gift_goods->destroy([
                'gift_id' => $gift_id
            ]);
            // 查询规格名称图片
            $sku                = new NsGoodsSkuModel();
            $sku_info           = $sku->getInfo([
                'sku_id' => $sku_id
            ], 'sku_name,picture');
            $data_sku           = array(
                'gift_id'       => $gift_id,
                'sku_id'        => $sku_id,
                'sku_name'      => $sku_info['sku_name'],
                'sku_picture'   => $sku_info['picture']
            );
            $promotion_gift_goods = new NsPromotionGiftGoodsModel();
            $promotion_gift_goods->save($data_sku);
            $promotion_gift->commit();
            return 1;
        } catch (\Exception $e) {
            $promotion_gift->rollback();
            return $e->getMessage();
        }

        // TODO Auto-generated method stub
    }

    /**
     * @param \data\api\unknown $gift_id
     * @return static
     * 获取 赠品详情
     */
    public function getPromotionGiftDetail($gift_id)
    {
        $promotion_gift       = new NsPromotionGiftModel();
        $data                 = $promotion_gift->get($gift_id);
        $promotion_gift_goods = new NsPromotionGiftGoodsModel();

        $gift_goods = $promotion_gift_goods->get([
            'gift_id' => $gift_id
        ]);

        $picture               = new AlbumPictureModel();
        $goods                 = new NsGoodsModel();
        $sku                   = new NsGoodsSkuModel();
        $pic_info              = array();
        $pic_info['pic_cover'] = '';

        $sku_info  = $sku->getInfo([
            'sku_id' => $gift_goods['sku_id']
        ], 'price, stock,goods_id');

        if (!empty($gift_goods['sku_picture'])) {
            $pic_info = $picture->get($gift_goods['sku_picture']);
        }else{
            $_goods = $goods->get($sku_info['goods_id']);
            $pic_info = $picture->get($_goods['picture']);
        }

        $gift_goods['picture_info']     = $pic_info;
        $gift_goods["price"]            = "";
        $gift_goods["stock"]            = "";
        $gift_goods["category_id_name"] = "";
        $gift_goods["goods_type"]       = "";

        $goods_info = $goods->getInfo([
            'goods_id' => $sku_info['goods_id']
        ], 'price, stock,goods_type,category_id_1,material_code,goods_name');

        if (!empty($goods_info)) {
            $gift_goods["price"]         = $goods_info["price"];
            $gift_goods["material_code"] = $goods_info["material_code"];
            $gift_goods["stock"]         = $goods_info["stock"];
            $gift_goods["sku_stock"]     = $sku_info["stock"];
            $gift_goods["goods_name"]    = $goods_info["goods_name"];
            $gift_goods["goods_id"]      = $sku_info['goods_id'];

            $gift_goods['category_id_name'] = \think\Db::name('ns_goods_category')->where(['category_id' => $goods_info['category_id_1']])->find()['category_name'];
            if ($goods_info['goods_type'] == 0) {
                $gift_goods['goods_type'] = '虚拟商品';
            } else if ($goods_info['goods_type'] == 1) {
                $gift_goods['goods_type'] = '实物商品';
            } else if ($goods_info['goods_type'] == 2) {
                $gift_goods['goods_type'] = '实物礼品';
            } else {
                $gift_goods['goods_type'] = '会员';
            }
        }

        $data['gift_goods'] = $gift_goods;
        return $data;
    }

    /**
     * 根据赠品id，返回商品规格
     *
     * @param unknown $gift_id
     * @param unknown $number
     */
    public function getGoodsSkuByGiftId($gift_id, $num)
    {
        $res                        = "";
        $promotion_gift_goods_model = new NsPromotionGiftGoodsModel();
        $sku_id                     = $promotion_gift_goods_model->getInfo([
            'gift_id' => $gift_id
        ], "sku_id");
//        if (!empty($sku_id['goods_id'])) {
//            $goods_sku_model = new NsGoodsSkuModel();
//            $sku_id          = $goods_sku_model->getInfo([
//                'goods_id' => $goods_id['goods_id']
//            ], 'sku_id');
        if (!empty($sku_id)) {
            $res = $sku_id['sku_id'] . ":" . $num;
        }
//        }
        return $res;
    }

    /**
     * 删除赠品
     * 创建时间：2018年1月24日11:45:50 王永杰
     * (non-PHPdoc)
     *
     * @see \data\api\IPromotion::deletePromotionGift()
     */
    public function deletePromotionGift($gift_id)
    {
        $res            = array();
        $promotion_gift = new NsPromotionGiftModel();
        $info           = $promotion_gift->getInfo([
            'gift_id' => $gift_id
        ], "start_time,end_time");
        if (!empty($info)) {
            // 未开始、已结束的赠品不能删除
            if ($info['start_time'] > time() || time() > $info['end_time']) {

                $promotion_gift_goods = new NsPromotionGiftGoodsModel();
                $promotion_gift->destroy([
                    'gift_id' => $gift_id
                ]);
                $promotion_gift_goods->destroy([
                    'gift_id' => $gift_id
                ]);
                $res['code']    = 1;
                $res['message'] = "赠品删除成功";
            } else {
                $res['code']    = 0;
                $res['message'] = '进行中的赠品不能删除';
            }
        } else {
            $res['code']    = 0;
            $res['message'] = '赠品不存在';
        }
        return $res;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromote::getPromotionMansongList()
     */
    public function getPromotionMansongList($page_index = 1, $page_size = 0, $condition = '', $order = 'create_time desc')
    {
        $promotion_mansong = new NsPromotionMansongModel();
        $list              = $promotion_mansong->pageQuery($page_index, $page_size, $condition, $order, '*');
        if (!empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                if ($v['status'] == 0) {
                    $list['data'][$k]['status_name'] = '未开始';
                }
                if ($v['status'] == 1) {
                    $list['data'][$k]['status_name'] = '进行中';
                }
                if ($v['status'] == 2) {
                    $list['data'][$k]['status_name'] = '已取消';
                }
                if ($v['status'] == 3) {
                    $list['data'][$k]['status_name'] = '已失效';
                }
                if ($v['status'] == 4) {
                    $list['data'][$k]['status_name'] = '已结束';
                }
            }
        }
        return $list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromote::addPromotionMansong()
     */
    public function addPromotionMansong($mansong_name, $start_time, $end_time, $shop_id, $remark, $type, $range_type, $rule, $goods_sku_arr, $is_neigou)
    {
        $promot_mansong = new NsPromotionMansongModel();
        $goods_mansong  = new GoodsMansong();
        $promot_mansong->startTrans();
        try {
            $err        = 0;
            $count_quan = $goods_mansong->getQuanmansong($start_time, $end_time);
            if ($count_quan > 0 && $range_type == 1) {
                $err = 1;
            }
            $shop_name = $this->instance_name;
            $data      = array(
                'mansong_name' => $mansong_name,
                'start_time'   => getTimeTurnTimeStamp($start_time),
                'end_time'     => getTimeTurnTimeStamp($end_time),
                'shop_id'      => $shop_id,
                'shop_name'    => $shop_name,
                'status'       => 0, // 状态重新设置
                'remark'       => $remark,
                'type'         => $type,
                'range_type'   => $range_type,
                'is_neigou'    => $is_neigou,
                'create_time'  => time()
            );
            $promot_mansong->save($data);
            $mansong_id = $promot_mansong->mansong_id;
            // 添加活动规则表
            $rule_array = explode(';', $rule);
            foreach ($rule_array as $k => $v) {
                $get_rule            = explode(',', $v);
                $get_rule[5]         = rtrim(strtr($get_rule[5], '/', ','), ',');
                $data_rule           = array(
                    'mansong_id'    => $mansong_id,
                    'price'         => $get_rule[0],
                    'discount'      => $get_rule[1],
                    'free_shipping' => $get_rule[2],
                    'give_point'    => $get_rule[3],
                    'give_coupon'   => $get_rule[4],
                    'gift_id'       => $get_rule[5],
                    'discount_rate' => $get_rule[6]
                );
                $promot_mansong_rule = new NsPromotionMansongRuleModel();
                $res                 = $promot_mansong_rule->save($data_rule);

                if (!empty($get_rule[5])) {
                    $rule_dat = explode(',', $get_rule[5]);
                    for ($i = 0; $i < count($rule_dat); $i++) {
                        $_sku_id              = \think\Db::name('ns_promotion_gift_goods')->where(['gift_id' => explode(':', $rule_dat[$i])[0]])->find()['sku_id'];
                        $_data['rule_id']     = $res;
                        $_data['sku_id']      = $_sku_id;
                        $_data['num']         = explode(':', $rule_dat[$i])[1];
                        $_data['create_time'] = time();
                        \think\Db::name('ns_promotion_rule_goods')->insert($_data);
                    }
                }
            }

            // 满减送商品表
            if ($range_type == 0 && !empty($goods_sku_arr)) {
                // 部分商品
                $goods_id_array = explode(',', $goods_sku_arr);
                foreach ($goods_id_array as $k => $v) {
                    $v_arr = explode(':',$v);
                    $promotion_mansong_goods = new NsPromotionMansongGoodsModel();
                    // 查询商品名称图片
                    $goods      = new NsGoodsModel();
                    $goods_info = $goods->getInfo([
                        'goods_id' => $v_arr[0]
                    ], 'goods_name,picture');
                    // 查询规格名称图片
                    $sku      = new NsGoodsSkuModel();
                    $sku_info = $sku->getInfo([
                        'sku_id' => $v_arr[1]
                    ], 'sku_name,picture');
                    $data_goods = array(
                        'mansong_id'    => $mansong_id,
                        'goods_id'      => $v_arr[0],
                        'sku_id'        => $v_arr[1],
                        'goods_name'    => $goods_info['goods_name'],
                        'sku_name'      => $sku_info['sku_name'],
                        'goods_picture' => $goods_info['picture'],
                        'sku_picture'   => $sku_info['picture'],
                        'status'        => 0, // 状态重新设置
                        'start_time'    => getTimeTurnTimeStamp($start_time),
                        'end_time'      => getTimeTurnTimeStamp($end_time)
                    );
                    $count      = $goods_mansong->getGoodsIsMansong($v_arr[1], $start_time, $end_time);
                    if ($count > 0) {
                        $err = 1;
                    }
                    $promotion_mansong_goods->save($data_goods);
                }
            }
            if ($err > 0) {
                $promot_mansong->rollback();
                return ACTIVE_REPRET;
            } else {
                $promot_mansong->commit();
                return $mansong_id;
            }
        } catch (\Exception $e) {
            $promot_mansong->rollback();
            return $e->getMessage();
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromote::updatePromotionMansong()
     */
    public function updatePromotionMansong($mansong_id, $mansong_name, $start_time, $end_time, $shop_id, $remark, $type, $range_type, $rule, $goods_sku_arr, $is_neigou)
    {
        $promot_mansong = new NsPromotionMansongModel();
        $promot_mansong->startTrans();
        try {
            $err       = 0;
            $shop_name = $this->instance_name;
            $data      = array(
                'mansong_name' => $mansong_name,
                'start_time'   => getTimeTurnTimeStamp($start_time),
                'end_time'     => getTimeTurnTimeStamp($end_time),
                'shop_id'      => $this->instance_id,
                'shop_name'    => $shop_name,
                'status'       => 0, // 状态重新设置
                'remark'       => $remark,
                'type'         => $type,
                'range_type'   => $range_type,
                'is_neigou'    => $is_neigou,
                'create_time'  => time()
            );
            $promot_mansong->save($data, [
                'mansong_id' => $mansong_id
            ]);
            // 添加活动规则表
            $promot_mansong_rule = new NsPromotionMansongRuleModel();
            $promot_mansong_rule->destroy([
                'mansong_id' => $mansong_id
            ]);

            # 添加活动规则表
            \think\Db::name('ns_promotion_mansong_rule')->where(['mansong_id' => $mansong_id])->delete();
            $rule_array = explode(';', $rule);
            foreach ($rule_array as $k => $v) {
                $get_rule            = explode(',', $v);
                $get_rule[5]         = rtrim(strtr($get_rule[5], '/', ','), ',');
                $data_rule           = array(
                    'mansong_id'    => $mansong_id,
                    'price'         => $get_rule[0],
                    'discount'      => $get_rule[1],
                    'free_shipping' => $get_rule[2],
                    'give_point'    => $get_rule[3],
                    'give_coupon'   => $get_rule[4],
                    'gift_id'       => $get_rule[5],
                    'discount_rate' => $get_rule[6]
                );
                $promot_mansong_rule = new NsPromotionMansongRuleModel();
                $res                 = $promot_mansong_rule->save($data_rule);
            }

            if (!empty($get_rule[5])) {
                $rule_dat = explode(',', $get_rule[5]);
                for ($i = 0; $i < count($rule_dat); $i++) {
                    $_sku_id              = \think\Db::name('ns_promotion_gift_goods')->where(['gift_id' => explode(':', $rule_dat[$i])[0]])->find()['sku_id'];
                    $_data['rule_id']     = $res;
                    $_data['sku_id']      = $_sku_id;
                    $_data['num']         = explode(':', $rule_dat[$i])[1];
                    $_data['create_time'] = time();
                    \think\Db::name('ns_promotion_rule_goods')->insert($_data);
                }
            }

            // 满减送商品表
            if ($range_type == 0 && !empty($goods_sku_arr)) {
                // 部分商品
                $goods_id_array          = explode(',', $goods_sku_arr);
                $promotion_mansong_goods = new NsPromotionMansongGoodsModel();
                $promotion_mansong_goods->destroy([
                    'mansong_id' => $mansong_id
                ]);
                foreach ($goods_id_array as $k => $v) {
                    $v_arr = explode(':',$v);
                    // 数量检查
                    $goods_mansong = new GoodsMansong();
                    $count         = $goods_mansong->getGoodsIsMansong($v_arr[1], $start_time, $end_time);
                    if ($count > 0) {
                        $err = 1;
                    }
                    $promotion_mansong_goods = new NsPromotionMansongGoodsModel();
                    $goods                   = new NsGoodsModel();
                    $sku                     = new NsGoodsSkuModel();
                    #获取商品信息
                    $goods_info              = $goods->getInfo([
                        'goods_id' => $v_arr[0]
                    ], 'goods_name,picture');
                    #获取规格信息
                    $sku_info              = $sku->getInfo([
                        'sku_id' => $v_arr[1]
                    ], 'sku_name,picture');
                    $data_goods              = array(
                        'mansong_id'    => $mansong_id,
                        'goods_id'      => $v[0],
                        'sku_id'        => $v[1],
                        'goods_name'    => $goods_info['goods_name'],
                        'sku_name'      => $sku_info['sku_name'],
                        'goods_picture' => $goods_info['picture'],
                        'sku_picture'   => $sku_info['picture'],
                        'status'        => 0, // 状态重新设置
                        'start_time'    => getTimeTurnTimeStamp($start_time),
                        'end_time'      => getTimeTurnTimeStamp($end_time)
                    );
                    $promotion_mansong_goods->save($data_goods);
                }
            }
            if ($err > 0) {
                $promot_mansong->rollback();
                return ACTIVE_REPRET;
            } else {

                $promot_mansong->commit();
                return 1;
            }
        } catch (\Exception $e) {
            $promot_mansong->rollback();
            return $e->getMessage();
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromote::getPromotionMansongDetail()
     */
    public function getPromotionMansongDetail($mansong_id)
    {
        $promotion_mansong   = new NsPromotionMansongModel();
        $data                = $promotion_mansong->get($mansong_id);
        $promot_mansong_rule = new NsPromotionMansongRuleModel();
        $rule_list           = $promot_mansong_rule->pageQuery(1, 0, 'mansong_id = ' . $mansong_id, 'price desc', '*');

        foreach ($rule_list['data'] as $k => $v) {
            $rule_list['data'][$k]['gift_arr'] = [];
            if ($v['free_shipping'] == 1) {
                $rule_list['data'][$k]['free_shipping_name'] = "是";
            } else {
                $rule_list['data'][$k]['free_shipping_name'] = "否";
            }
            if ($v['give_coupon'] == 0) {
                $rule_list['data'][$k]['coupon_name'] = '';
            } else {
                $coupon_type                          = new NsCouponTypeModel();
                $coupon_name                          = $coupon_type->getInfo([
                    'coupon_type_id' => $v['give_coupon']
                ], 'coupon_name');
                $rule_list['data'][$k]['coupon_name'] = $coupon_name['coupon_name'];
            }
            if ($v['gift_id'] == 0) {
                $rule_list['data'][$k]['gift_name'] = '';
            } else {
                $gift       = new NsPromotionGiftModel();
                $_gift_name = '';
                if (strpos($v['gift_id'], ',') !== false) {
                    $ids = explode(',', $v['gift_id']);
                    foreach ($ids as $key => $vo) {
                        $_ids      = explode(':', $vo);
                        $gift_name = $gift->getInfo([
                            'gift_id' => $_ids[0]
                        ], 'gift_name');
                        $_gift_name .= $gift_name['gift_name'] . ' * ' . $_ids[1] . '件' . ',';
                    }
                    $_gift_name = substr($_gift_name, 0, -1);
                } else {
                    $_ids       = explode(':', $v['gift_id']);
                    $gift_name  = $gift->getInfo([
                        'gift_id' => $_ids[0]
                    ], 'gift_name');
                    $_gift_name = $gift_name['gift_name'] . ' * ' . $_ids[1] . '件';
                }
                $rule_list['data'][$k]['gift_name'] = $_gift_name;
            }
        }
        $data['rule']           = $rule_list['data'];
        $data['goods_list']     = [];
        $data['goods_id_array'] = [];
        if ($data['range_type'] == 0) {
            if ($data['is_neigou'] == 1) {
                $mansong_goods = new NsPromotionNeigouGoodsModel();
                $list          = $mansong_goods->getQuery([
                    'discount_id' => $mansong_id
                ], '*', '', 'goods_id');
                $sku_list      = $mansong_goods->getQuery([
                    'discount_id' => $mansong_id
                ], '*', '');
            } else {
                $mansong_goods = new NsPromotionMansongGoodsModel();
                $list          = $mansong_goods->getQuery([
                    'mansong_id' => $mansong_id
                ], '*', '', 'goods_id');
                $sku_list      = $mansong_goods->getQuery([
                    'mansong_id' => $mansong_id
                ], '*', '');
            }
            $data['sku_id_array'] = array_column($sku_list,'sku_id');

            if (!empty($list)) {
                foreach ($list as $k => $v) {
                    $goods                 = new NsGoodsModel();
                    $goods_info            = $goods->getInfo([
                        'goods_id' => $v['goods_id']
                    ], 'price, stock, material_code,goods_type,category_id_1');
                    $picture               = new AlbumPictureModel();
                    $pic_info              = array();
                    $pic_info['pic_cover'] = '';
                    if (!empty($v['goods_picture'])) {
                        $pic_info              = $picture->get($v['goods_picture']);
                        $v['category_id_name'] = \think\Db::name('ns_goods_category')->where(['category_id' => $goods_info['category_id_1']])->find()['category_name'];
                        if ($goods_info['goods_type'] == 0) {
                            $v['goods_type'] = '虚拟商品';
                        } else if ($goods_info['goods_type'] == 1) {
                            $v['goods_type'] = '实物商品';
                        } else if ($goods_info['goods_type'] == 2) {
                            $v['goods_type'] = '实物礼品';
                        } else {
                            $v['goods_type'] = '会员';
                        }
                    }
                    $v['picture_info']  = $pic_info;
                    $v['price']         = $goods_info['price'];
                    $v['stock']         = $goods_info['stock'];
                    $v['material_code'] = $goods_info['material_code'];
                }
            }
            $data['goods_list'] = $list;
            $goods_id_array     = array();
            foreach ($list as $k => $v) {
                $goods_id_array[] = $v['goods_id'];
            }
            $data['goods_id_array'] = $goods_id_array;
        }
        return $data;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromote::addPromotiondiscount()
     */
    public function addPromotiondiscount($discount_name, $start_time, $end_time, $remark, $goods_sku_arr)
    {
        $promotion_discount = new NsPromotionDiscountModel();
        $promotion_discount->startTrans();
        try {

            $shop_name = $this->instance_name;
            $data      = array(
                'discount_name' => $discount_name,
                'start_time'    => getTimeTurnTimeStamp($start_time),
                'end_time'      => getTimeTurnTimeStamp($end_time),
                'shop_id'       => $this->instance_id,
                'shop_name'     => $shop_name,
                'status'        => 0,
                'remark'        => $remark,
                'create_time'   => time()
            );
            $promotion_discount->save($data);
            $discount_id              = $promotion_discount->discount_id;
            $goods_id_array           = explode(',', $goods_sku_arr);
            $promotion_discount_goods = new NsPromotionDiscountGoodsModel();
            $promotion_discount_goods->destroy([
                'discount_id' => $discount_id
            ]);
            foreach ($goods_id_array as $k => $v) {
                // 添加检测考虑商品在一个时间段内只能有一种活动
                $promotion_discount_goods = new NsPromotionDiscountGoodsModel();
                $discount_info            = explode(':', $v);
                $goods_discount           = new GoodsDiscount();
                $count                    = $goods_discount->getGoodsIsDiscount($discount_info[1], $start_time, $end_time);
                // 查询商品名称图片
                if ($count > 0) {
                    $promotion_discount->rollback();
                    return ACTIVE_REPRET;
                }
                $sku        = new NsGoodsSkuModel();
                $goods        = new NsGoodsModel();
                # sku 信息
                $sku_info   = $sku->getInfo([
                    'sku_id' => $discount_info[1]
                ], 'sku_name,picture');
                # 商品信息
                $goods_info   = $goods->getInfo([
                    'goods_id' => $discount_info[0]
                ], 'goods_name,picture');

                $data_goods = array(
                    'discount_id'   => $discount_id,
                    'goods_id'      => $discount_info[0],
                    'sku_id'        => $discount_info[1],
                    'discount'      => $discount_info[2],
                    'status'        => 0,
                    'start_time'    => getTimeTurnTimeStamp($start_time),
                    'end_time'      => getTimeTurnTimeStamp($end_time),
                    'sku_name'      => $sku_info['sku_name'],
                    'goods_name'    => $goods_info['goods_name'],
                    'sku_picture'   => $sku_info['picture'],
                    'goods_picture' => $goods_info['picture']
                );
                $promotion_discount_goods->save($data_goods);
            }
            $promotion_discount->commit();
            return $discount_id;
        } catch (\Exception $e) {
            $promotion_discount->rollback();
            return $e->getMessage();
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromote::updatePromotionDiscount()
     */
    public function updatePromotionDiscount($discount_id, $discount_name, $start_time, $end_time, $remark, $goods_sku_arr)
    {
        $promotion_discount = new NsPromotionDiscountModel();
        $promotion_discount->startTrans();
        try {

            $shop_name = $this->instance_name;
            $data      = array(
                'discount_name' => $discount_name,
                'start_time'    => getTimeTurnTimeStamp($start_time),
                'end_time'      => getTimeTurnTimeStamp($end_time),
                'shop_id'       => $this->instance_id,
                'shop_name'     => $shop_name,
                'status'        => 0,
                'remark'        => $remark,
                'create_time'   => time()
            );
            $promotion_discount->save($data, [
                'discount_id' => $discount_id
            ]);
            $goods_id_array           = explode(',', $goods_sku_arr);
            $promotion_discount_goods = new NsPromotionDiscountGoodsModel();
            $promotion_discount_goods->destroy([
                'discount_id' => $discount_id
            ]);
            foreach ($goods_id_array as $k => $v) {
                $promotion_discount_goods = new NsPromotionDiscountGoodsModel();
                $discount_info            = explode(':', $v);
                $goods_discount           = new GoodsDiscount();
                $count                    = $goods_discount->getGoodsIsDiscount($discount_info[1], $start_time, $end_time);
                //判断是否参加
                if ($count > 0) {
                    $promotion_discount->rollback();
                    return ACTIVE_REPRET;
                }
                // 查询商品名称图片
                $goods      = new NsGoodsModel();
                $goods_info = $goods->getInfo([
                    'goods_id' => $discount_info[0]
                ], 'goods_name,picture');
                // 查询规格名称图片
                $sku      = new NsGoodsSkuModel();
                $sku_info = $sku->getInfo([
                    'sku_id' => $discount_info[1]
                ], 'sku_name,picture');

                $data_goods = array(
                    'discount_id'   => $discount_id,
                    'goods_id'      => $discount_info[0],
                    'sku_id'        => $discount_info[1],
                    'discount'      => $discount_info[2],
                    'status'        => 0,
                    'start_time'    => getTimeTurnTimeStamp($start_time),
                    'end_time'      => getTimeTurnTimeStamp($end_time),
                    'goods_name'    => $goods_info['goods_name'],
                    'sku_name'      => $sku_info['sku_name'],
                    'goods_picture' => $goods_info['picture'],
                    'sku_picture'   => $sku_info['picture']
                );
                $promotion_discount_goods->save($data_goods);
            }
            $promotion_discount->commit();
            return $discount_id;
        } catch (\Exception $e) {
            $promotion_discount->rollback();
            return $e->getMessage();
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromote::closePromotionDiscount()
     */
    public function closePromotionDiscount($discount_id)
    {
        $promotion_discount = new NsPromotionDiscountModel();
        $promotion_discount->startTrans();
        try {
            $retval = $promotion_discount->save([
                'status' => 3
            ], [
                'discount_id' => $discount_id
            ]);
            if ($retval == 1) {
                $goods = new NsGoodsModel();

                $data_goods    = array(
                    'promotion_type' => 2,
                    'promote_id' => $discount_id
                );
                $goods_id_list = $goods->getQuery($data_goods, 'goods_id', '');
                if (!empty($goods_id_list)) {

                    foreach ($goods_id_list as $k => $goods_id) {
                        $goods_info = $goods->getInfo([
                            'goods_id' => $goods_id['goods_id']
                        ], 'promotion_type,price');
                        $goods->save([
                            'promotion_price' => $goods_info['price']
                        ], [
                            'goods_id' => $goods_id['goods_id']
                        ]);
                        $goods_sku      = new NsGoodsSkuModel();
                        $goods_sku_list = $goods_sku->getQuery([
                            'goods_id' => $goods_id['goods_id']
                        ], 'price,sku_id', '');
                        foreach ($goods_sku_list as $k_sku => $sku) {
                            $goods_sku      = new NsGoodsSkuModel();
                            $data_goods_sku = array(
                                'promote_price' => $sku['price']
                            );
                            $goods_sku->save($data_goods_sku, [
                                'sku_id' => $sku['sku_id']
                            ]);
                        }
                    }
                }
                $goods->save([
                    'promotion_type' => 0,
                    'promote_id' => 0
                ], $data_goods);
                $promotion_discount_goods = new NsPromotionDiscountGoodsModel();
                $retval                   = $promotion_discount_goods->save([
                    'status' => 3
                ], [
                    'discount_id' => $discount_id
                ]);
            }
            $promotion_discount->commit();
            return $retval;
        } catch (\Exception $e) {
            $promotion_discount->rollback();
            return $e->getMessage();
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromote::getPromotionDiscountList()
     */
    public function getPromotionDiscountList($page_index = 1, $page_size = 0, $condition = '', $order = 'create_time desc')
    {
        $promotion_discount = new NsPromotionDiscountModel();
        $list               = $promotion_discount->pageQuery($page_index, $page_size, $condition, $order, '*');
        return $list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromote::getPromotionDiscountDetail()
     */
    public function getPromotionDiscountDetail($discount_id)
    {
        # todo @陈 显示折扣商品图片显示不一样
        $promotion_discount       = new NsPromotionDiscountModel();
        $promotion_detail         = $promotion_discount->get($discount_id);
        $promotion_discount_goods = new NsPromotionDiscountGoodsModel();

        $promotion_goods_list = $promotion_discount_goods->getQuery([
            'discount_id' => $discount_id
        ], '*', '','goods_id');

        if (!empty($promotion_goods_list)) {
            foreach ($promotion_goods_list as $k => $v) {
                $goods      = new NsGoodsModel();
                $goods_info = $goods->getInfo([
                    'goods_id' => $v['goods_id']
                ], 'price, stock, picture,category_id_1'); # ++ picture

                $picture               = new AlbumPictureModel();
                $pic_info              = array();
                $pic_info['pic_cover'] = '';
                if (!empty($v['goods_picture'])) {
                    $pic_info = $picture->get($goods_info['picture']);  # +-  $v['goods_picture'] => $goods_info['picture']
                }
                $v['picture_info'] = $pic_info;
                $v['price']        = $goods_info['price'];
                $v['stock']        = $goods_info['stock'];
                //商品分类
                $categoryInfo = \think\Db::name('ns_goods_category')->where(['category_id' => $goods_info['category_id_1']])->find();
                if(!empty($categoryInfo)){
                    $promotion_goods_list[$k]['category_name'] = $categoryInfo['category_name'];
                }else{
                    $promotion_goods_list[$k]['category_name'] = '';
                }
            }
        }
        $promotion_detail['goods_list'] = $promotion_goods_list;
        return $promotion_detail;
    }

    /**
     *
     * @ERROR!!!
     *
     * @see \data\api\IPromote::delPromotionDiscount()
     */
    public function delPromotionDiscount($discount_id)
    {
        $promotion_discount       = new NsPromotionDiscountModel();
        $promotion_discount_goods = new NsPromotionDiscountGoodsModel();
        $promotion_discount->startTrans();
        try {
            $discount_id_array = explode(',', $discount_id);
            foreach ($discount_id_array as $k => $v) {
                $promotion_detail = $promotion_discount->get($discount_id);
                if ($promotion_detail['status'] == 1) {
                    $promotion_discount->rollback();
                    return -1;
                }
                $promotion_discount->destroy($v);
                $promotion_discount_goods->destroy([
                    'discount_id' => $v
                ]);
            }
            $promotion_discount->commit();
            return 1;
        } catch (\Exception $e) {
            $promotion_discount->rollback();
            return $e->getMessage();
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromote::closePromotionDiscount()
     */
    public function closePromotionMansong($mansong_id)
    {
        $promotion_mansong = new NsPromotionMansongModel();
        $retval            = $promotion_mansong->save([
            'status' => 3
        ], [
            'mansong_id' => $mansong_id,
            'shop_id' => $this->instance_id
        ]);
        if ($retval == 1) {
            $promotion_mansong_goods = new NsPromotionMansongGoodsModel();

            $retval = $promotion_mansong_goods->save([
                'status' => 3
            ], [
                'mansong_id' => $mansong_id
            ]);
        }
        return $retval;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromote::closePromotionDiscount()
     */
    public function delPromotionMansong($mansong_id)
    {
        $promotion_mansong       = new NsPromotionMansongModel();
        $promotion_mansong_goods = new NsPromotionMansongGoodsModel();
        $promot_mansong_rule     = new NsPromotionMansongRuleModel();
        $promotion_mansong->startTrans();
        try {
            $mansong_id_array = explode(',', $mansong_id);
            foreach ($mansong_id_array as $k => $v) {
                $status = $promotion_mansong->getInfo([
                    'mansong_id' => $v
                ], 'status');
                if ($status['status'] == 1) {
                    $promotion_mansong->rollback();
                    return -1;
                }
                $promotion_mansong->destroy($v);
                $promotion_mansong_goods->destroy([
                    'mansong_id' => $v
                ]);
                $promot_mansong_rule->destroy([
                    'mansong_id' => $v
                ]);
            }
            $promotion_mansong->commit();
            return 1;
        } catch (Exception $e) {
            $promotion_mansong->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 得到店铺的满额包邮信息
     * (non-PHPdoc)
     *
     * @see \data\api\IPromote::getPromotionFullMail()
     */
    public function getPromotionFullMail($shop_id)
    {
        $promotion_fullmail = new NsPromotionFullMailModel();
        $mail_count         = $promotion_fullmail->getCount([
            "shop_id" => $shop_id
        ]);
        if ($mail_count == 0) {
            $data = array(
                'shop_id' => $shop_id,
                'is_open' => 0,
                'full_mail_money' => 0,
                'no_mail_province_id_array' => '',
                'no_mail_city_id_array' => '',
                'create_time' => time()
            );
            $promotion_fullmail->save($data);
        }
        $mail_obj = $promotion_fullmail->getInfo([
            "shop_id" => $shop_id
        ]);
        return $mail_obj;
    }

    /**
     * 更新或添加满额包邮的信息
     * (non-PHPdoc)
     *
     * @see \data\api\IPromote::updatePromotionFullMail()
     */
    public function updatePromotionFullMail($shop_id, $is_open, $full_mail_money, $no_mail_province_id_array, $no_mail_city_id_array)
    {
        $full_mail_model = new NsPromotionFullMailModel();
        $data            = array(
            'is_open' => $is_open,
            'full_mail_money' => $full_mail_money,
            'modify_time' => time(),
            'no_mail_province_id_array' => $no_mail_province_id_array,
            'no_mail_city_id_array' => $no_mail_city_id_array
        );
        $full_mail_model->save($data, [
            "shop_id" => $shop_id
        ]);
        return 1;
    }

    /**
     * 添加或编辑组合套餐
     */
    public function addOrEditComboPackage($id, $combo_package_name, $combo_package_price, $goods_id_array, $is_shelves, $shop_id, $original_price, $save_the_price)
    {
        $data           = array(
            "combo_package_name" => $combo_package_name,
            "combo_package_price" => $combo_package_price,
            "goods_id_array" => $goods_id_array,
            "is_shelves" => $is_shelves,
            "shop_id" => $shop_id,
            "original_price" => $original_price,
            "save_the_price" => $save_the_price
        );
        $nsComboPackage = new NsComboPackagePromotionModel();
        if ($id == 0) {
            $data["create_time"] = time();
            $nsComboPackage->save($data);
            return $nsComboPackage->id;
        } else
            if ($id > 0) {
                $data["update_time"] = time();
                $res                 = $nsComboPackage->save($data, [
                    "id" => $id,
                    "shop_id" => $shop_id
                ]);
                return $res;
            }
    }

    /**
     * 获取组合套餐详情
     */
    public function getComboPackageDetail($id)
    {
        $nsComboPackage = new NsComboPackagePromotionModel();
        $info           = $nsComboPackage->getInfo([
            "id" => $id
        ]);
        return $info;
    }

    /**
     * 获取组合套餐列表
     *
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param string $order
     * @param string $field
     * @return number[]|unknown[]
     */
    public function getComboPackageList($page_index, $page_size, $condition, $order = "", $field = "*")
    {
        $nsComboPackage = new NsComboPackagePromotionModel();
        $list           = $nsComboPackage->pageQuery($page_index, $page_size, $condition, $order, $field);
        return $list;
    }

    /**
     * 删除组合套餐
     */
    public function deleteComboPackage($ids)
    {
        $nsComboPackage = new NsComboPackagePromotionModel();
        $res            = $nsComboPackage->destroy([
            "id" => array(
                "in",
                $ids
            )
        ]);
        return $res;
    }

    /**
     * 获取组合套餐商品列表
     *
     * @param unknown $goods_id
     */
    public function getComboPackageGoodsArray($goods_id)
    {
        $nsComboPackage = new NsComboPackagePromotionModel();
        $condition      = "FIND_IN_SET($goods_id, goods_id_array ) AND is_shelves = 1";
        $list           = $nsComboPackage->getQuery($condition, "*", "");
        $goods          = new NsGoodsViewModel();
        foreach ($list as $k => $v) {
            $main_goods             = $goods->getGoodsViewQuery(1, 1, [
                "ng.state" => 1,
                "ng.goods_id" => $goods_id,
                "ng.goods_type" => 1
            ], "");
            $list[$k]["main_goods"] = $main_goods[0];
            $goods_array            = $goods->getGoodsViewQuery(1, 0, [
                "ng.state" => 1,
                "ng.goods_id" => array(
                    array(
                        "in",
                        $v["goods_id_array"]
                    ),
                    array(
                        "neq",
                        $goods_id
                    )
                ),
                "ng.goods_type" => 1
            ], "");
            $goods_count            = $goods->getGoodsrViewCount([
                "ng.goods_id" => array(
                    array(
                        "in",
                        $v["goods_id_array"]
                    ),
                    array(
                        "neq",
                        $goods_id
                    )
                ),
                "ng.goods_type" => 1
            ]);
            // 计算原价
            $list[$k]["original_price"] = $goods->getSum([
                "goods_id" => array(
                    "in",
                    $v["goods_id_array"]
                ),
                "goods_type" => 1
            ], "price");

            $list[$k]["save_the_price"] = $list[$k]["original_price"] - $v["combo_package_price"];
            $list[$k]["goods_array"]    = $goods_array;
            // 如果套餐中有商品已下架，则整个套餐都不予显示
            if (count($goods_array) != $goods_count) {
                unset($list[$k]);
            }
        }
        return $list;
    }

    /**
     * 获取指定组合套餐商品列表
     *
     * @param 组合套餐id $id
     * @param 当前访问的goods_id $curr_goods_id
     * @return list
     */
    public function getComboPackageGoodsById($id, $curr_goods_id)
    {
        $combo_package_model     = new NsComboPackagePromotionModel();
        $combo_package_condition = "id = $id AND is_shelves = 1";
        $combo_package           = $combo_package_model->getInfo($combo_package_condition, "id,combo_package_name,combo_package_price,goods_id_array,is_shelves,shop_id,original_price,save_the_price", "");
        if (!empty($combo_package)) {
            // 查询组合套餐中的商品信息
            $goods_condition             = "goods_id in(" . $combo_package['goods_id_array'] . ")";
            $combo_package['goods_list'] = array();
            if (!empty($curr_goods_id)) {
                $curr_goods = $this->getCollatingGoodsDetail($curr_goods_id);
                array_push($combo_package['goods_list'], $curr_goods);
            }
            $goods_id_array = explode(",", $combo_package['goods_id_array']);
            foreach ($goods_id_array as $k => $v) {
                if ($v != $curr_goods_id) {
                    $item = $this->getCollatingGoodsDetail($v);
                    array_push($combo_package['goods_list'], $item);
                }
            }
        }
        return $combo_package;
    }

    public function getCollatingGoodsDetail($goods_id)
    {
        $goods                             = new Goods();
        $curr_goods                        = $goods->getGoodsDetail($goods_id);
        $default_gallery_img               = $curr_goods["img_list"][0]["pic_cover_big"];
        $curr_goods['default_gallery_img'] = $default_gallery_img;
        $spec_list                         = $curr_goods["spec_list"];
        if (!empty($spec_list)) {
            $album = new Album();
            foreach ($spec_list as $k => $v) {
                foreach ($v["value"] as $t => $m) {
                    if ($m["spec_show_type"] == 3) {
                        if (is_numeric($m["spec_value_data"])) {
                            $picture_detail = $album->getAlubmPictureDetail([
                                "pic_id" => $m["spec_value_data"]
                            ]);

                            if (!empty($picture_detail)) {
                                $spec_list[$k]["value"][$t]["picture_id"]              = $picture_detail['pic_id'];
                                $spec_list[$k]["value"][$t]["spec_value_data"]         = $picture_detail["pic_cover_micro"];
                                $spec_list[$k]["value"][$t]["spec_value_data_big_src"] = $picture_detail["pic_cover_big"];
                            } else {
                                $spec_list[$k]["value"][$t]["spec_value_data"]         = '';
                                $spec_list[$k]["value"][$t]["spec_value_data_big_src"] = '';
                                $spec_list[$k]["value"][$t]["picture_id"]              = 0;
                            }
                        } else {
                            $spec_list[$k]["value"][$t]["spec_value_data_big_src"] = $m["spec_value_data"];
                            $spec_list[$k]["value"][$t]["picture_id"]              = 0;
                        }
                    }
                }
            }
            $curr_goods['spec_list'] = $spec_list;
        }
        return $curr_goods;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromotion::getCouponTypeList()
     */
    public function getCouponTypeInfoList($page_index = 1, $page_size = 0, $condition = '', $order = '', $uid = 0)
    {
        $coupon_type      = new NsCouponTypeModel();
        $coupon           = new NsCouponModel();
        $coupon_type_list = $coupon_type->pageQuery($page_index, $page_size, $condition, $order, 'coupon_type_id, coupon_name, money, count, max_fetch, at_least, need_user_level, range_type, start_time, end_time, create_time, update_time,is_show');
        foreach ($coupon_type_list['data'] as $k => $v) {
            // 剩余数量
            $surplus_num                                 = $coupon->getCount([
                "coupon_type_id" => $v["coupon_type_id"],
                "state" => 0
            ]);
            $coupon_type_list["data"][$k]["surplus_num"] = $surplus_num;
            // 当前用户已领取数量
            $received_num = 0;
            if (!empty($uid)) {
                $received_num = $coupon->getCount([
                    "coupon_type_id" => $v["coupon_type_id"],
                    "uid" => $uid
                ]);
            }
            $coupon_type_list["data"][$k]["received_num"] = $received_num;
            // 计算优惠券未领取百分比
            $surplus_percentage = 0;
            if ($v["count"] > 0) {
                $surplus_percentage = floor($surplus_num / $v["count"] * 100);
            }
            $coupon_type_list["data"][$k]["surplus_percentage"] = $surplus_percentage;
        }
        return $coupon_type_list;
        // TODO Auto-generated method stub
    }

    /**
     * 获取营销游戏列表
     *
     * @param number $page_index
     * @param number $page_size
     * @param string $condition
     * @param string $order
     */
    public function getPromotionGamesList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $promotion_games = new NsPromotionGamesModel();
        $list            = $promotion_games->pageQuery($page_index, $page_size, $condition, $order, '*');

        foreach ($list['data'] as $item) {

            $game_type_info         = $this->getPromotionGameTypeInfo($item['game_type']);
            $item['game_type_name'] = $game_type_info['type_name'];

            switch ($item['status']) {
                case 0:
                    $item['status_name'] = '未开始';
                    break;
                case 1:
                    $item['status_name'] = '已开始';
                    break;
                case -1:
                    $item['status_name'] = '已结束';
                    break;
                default:
                    break;
            }
        }
        return $list;
    }

    /**
     * 营销游戏列表
     *
     * @param unknown $game_type
     */
    public function getPromotionGameTypeInfo($game_type)
    {
        $game_type_model = new NsPromotionGameTypeModel();
        $info            = $game_type_model->getInfo([
            'game_type' => $game_type
        ], '*');
        return $info;
    }

    /**
     * 获取营销游戏详情
     *
     * @param unknown $game_id
     */
    public function getPromotionGameDetail($game_id)
    {
        $promotion_games      = new NsPromotionGamesModel();
        $game_info            = $promotion_games->getInfo([
            'game_id' => $game_id
        ], '*');
        $promotion_games_rule = new NsPromotionGameRuleModel();
        $rule_list            = $promotion_games_rule->getQuery([
            'game_id' => $game_id
        ], '*', '');

        $game_info['rule'] = $rule_list;
        return $game_info;
    }

    /**
     * 添加营销游戏
     *
     * @param unknown $shop_id
     * @param unknown $name
     * @param unknown $type
     * @param unknown $member_level
     * @param unknown $points
     * @param unknown $start_time
     * @param unknown $end_time
     * @param unknown $remark
     * @param unknown $rule_array
     */
    public function addUpdatePromotionGame($game_id, $shop_id, $name, $type, $member_level, $points, $start_time, $end_time, $remark, $winning_rate, $no_winning_des, $rule_json, $activity_images)
    {
        $promotion_games = new NsPromotionGamesModel();
        $promotion_games->startTrans();

        try {
            $member_level_model = new NsMemberLevelModel();
            if ($member_level == 0) {
                $level_name = '所有用户';
            } else {
                $level_info = $member_level_model->getInfo([
                    'level_id' => $member_level
                ], 'level_name');
                $level_name = $level_info['level_name'];
            }

            $data = array(
                'shop_id' => $shop_id,
                'name' => $name,
                'game_type' => $type,
                'member_level' => $member_level,
                'level_name' => $level_name,
                'points' => $points,
                'start_time' => getTimeTurnTimeStamp($start_time),
                'end_time' => getTimeTurnTimeStamp($end_time),
                'remark' => $remark,
                'winning_rate' => $winning_rate,
                'no_winning_des' => $no_winning_des,
                'activity_images' => $activity_images
            );

            if (empty($game_id)) {

                $game_id = $promotion_games->save($data);
            } else {

                $promotion_games->save($data, [
                    'game_id' => $game_id
                ]);
            }

            // 删除已有的规则
            $this->delPromotionGameRule($game_id);

            // 添加规则表
            $rule_array = json_decode($rule_json, true);
            foreach ($rule_array as $item) {

                $this->addPromotionGameRule($game_id, $item['rule_name'], $item['rule_num'], $item['type'], $item['type_value'], $item['points'], $item['coupon_type_id'], $item['hongbao'], $item['gift_id']);
            }

            $promotion_games->commit();

            return 1;
        } catch (\Exception $e) {

            $promotion_games->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 添加活动规则
     *
     * @param unknown $game_id
     * @param unknown $rule_num
     * @param unknown $type
     * @param unknown $sum
     * @param string $remark
     */
    public function addPromotionGameRule($game_id, $rule_name, $rule_num, $type, $type_value, $points, $coupon_type_id, $hongbao, $gift_id, $remark = '')
    {
        $game_rule_model = new NsPromotionGameRuleModel();
        $data            = array(
            'game_id' => $game_id,
            'rule_name' => $rule_name,
            'rule_num' => $rule_num,
            'remaining_number' => $rule_num, // 剩余奖品数量
            'type' => $type,
            'remark' => $remark,
            'points' => $points,
            'coupon_type_id' => $coupon_type_id,
            'hongbao' => $hongbao,
            'gift_id' => $gift_id,
            'type_value' => $type_value,
            'create_time' => time()
        );

        $res = $game_rule_model->save($data);
        return $res;
    }

    /**
     * 删除活动规则
     *
     * @param unknown $game_id
     */
    public function delPromotionGameRule($game_id)
    {
        $game_rule_model = new NsPromotionGameRuleModel();
        $res             = $game_rule_model->destroy([
            'game_id' => $game_id
        ]);
        return $res;
    }

    /**
     *
     * @param unknown $game_id
     * @param unknown $shop_id
     * @param unknown $name
     * @param unknown $type
     * @param unknown $member_level
     * @param unknown $points
     * @param unknown $start_time
     * @param unknown $end_time
     * @param unknown $remark
     * @param unknown $rule_array
     */
    public function updatePromotionGame($game_id, $shop_id, $name, $type, $member_level, $points, $start_time, $end_time, $remark, $rule_array)
    {
        $promotion_games    = new NsPromotionGamesModel();
        $member_level_model = new NsMemberLevelModel();
        if ($member_level == 0) {
            $level_name = '所有用户';
        } else {
            $level_info = $member_level_model->getInfo([
                'level_id' => $member_level
            ], 'level_name');
            $level_name = $level_info['level_name'];
        }

        $data = array(
            'shop_id' => $shop_id,
            'name' => $name,
            'type' => $type,
            'member_level' => $member_level,
            'level_name' => $level_name,
            'points' => $points,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'remark' => $remark
        );
        $promotion_games->save($data, [
            'game_id' => $game_id
        ]);
        $promotion_games_rule = new NsPromotionGameRuleModel();
        $promotion_games_rule->destroy([
            'game_id' => $game_id
        ]);
        // 添加规则表
    }

    /**
     * 添加赠品发放记录
     * 创建时间：2018年1月25日11:28:18
     *
     * (non-PHPdoc)
     *
     * @see \data\api\IPromotion::addPromotionGiftGrantRecords()
     */
    public function addPromotionGiftGrantRecords($shop_id, $uid, $nick_name, $gift_id, $gift_name, $goods_name, $goods_picture, $type, $type_name, $relate_id, $remark)
    {
        $res = array();
        // 验证
        if (empty($nick_name) || empty($gift_name) || empty($goods_name) || empty($type_name)) {
            $res['code']    = 0;
            $res['message'] = '缺少必要参数';
        } else {

            $model                 = new NSPromotionGiftGrantRecordsModel();
            $data                  = array();
            $data['shop_id']       = $shop_id;
            $data['uid']           = $uid;
            $data['nick_name']     = $nick_name;
            $data['gift_id']       = $gift_id;
            $data['gift_name']     = $gift_name;
            $data['goods_picture'] = $goods_picture;
            $data['goods_name']    = $goods_name;
            $data['type']          = $type;
            $data['type_name']     = $type_name;
            $data['relate_id']     = $relate_id;
            $data['remark']        = $remark;
            $data['create_time']   = time();
            $res['code']           = $model->save($data);
            $res['message']        = '添加赠品发放记录成功';
        }
        return $res;
    }

    /**
     * 获取商品查询数量，分页用
     * 创建时间：2018年1月4日16:52:45
     *
     * @param unknown $condition
     * @return unknown
     */
    public function getPromotionGiftGrantRecordsQueryCount($condition, $where_sql = "")
    {
        $model   = new NSPromotionGiftGrantRecordsModel();
        $viewObj = $model->alias('pgr');
        if (!empty($where_sql)) {
            $count = $model->viewCountNew($viewObj, $condition, $where_sql);
        } else {
            $count = $model->viewCount($viewObj, $condition);
        }
        return $count;
    }

    /**
     * 获取赠品发放记录列表
     * 创建时间：2018年1月25日15:47:43
     * (non-PHPdoc)
     *
     * @see \data\api\IPromotion::getPromotionGiftGrantRecordsList()
     */
    public function getPromotionGiftGrantRecordsList($page_index, $page_size, $condition, $order)
    {
        $condition_sql = "";
        $model         = new NSPromotionGiftGrantRecordsModel();
        $viewObj       = $model->alias("pgr")
            ->join('sys_album_picture ng_sap', 'ng_sap.pic_id = pgr.goods_picture', 'left')
            ->field("pgr.id,pgr.shop_id,pgr.uid,pgr.nick_name,pgr.gift_id,pgr.gift_name,pgr.goods_name,pgr.type,pgr.type_name,pgr.relate_id,pgr.remark,pgr.create_time,ng_sap.pic_cover_mid,ng_sap.pic_id,ng_sap.pic_cover_small");

        $queryList = $model->viewPageQueryNew($viewObj, $page_index, $page_size, $condition, $condition_sql, $order);

        $queryCount = $this->getPromotionGiftGrantRecordsQueryCount($condition);

        $list = $model->setReturnList($queryList, $queryCount, $page_size);

        return $list;
    }

    /**
     * 获取营销游戏类型列表
     *
     * @param number $page_index
     * @param number $page_size
     * @param string $condition
     * @param string $order
     * @param string $field
     */
    public function getPromotionGameTypeList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        $game_type_model = new NsPromotionGameTypeModel();
        $game_type_list  = $game_type_model->pageQuery($page_index, $page_size, $condition, $order, $field);
        return $game_type_list;
    }

    /**
     * 删除营销游戏
     *
     * @param unknown $game_id
     */
    public function delPromotionGame($game_id)
    {
        $condition       = array(
            'game_id' => $game_id
        );
        $promotion_games = new NsPromotionGamesModel();
        $game_rule       = new NsPromotionGameRuleModel();
        $game_rule->destroy($condition);
        $res = $promotion_games->destroy($condition);
        return $res;
    }

    /**
     * 获取中奖记录表
     *
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param unknown $order
     * @param unknown $field
     * @return number[]|unknown[]
     */
    public function getPromotionGameWinningRecordsList($page_index, $page_size, $condition, $order, $field)
    {
        $WinningRecords = new NsPromotionGamesWinningRecordsModel();
        $list           = $WinningRecords->pageQuery($page_index, $page_size, $condition, $order, $field);
        return $list;
    }

    /**
     * 获取奖项
     *
     * @param unknown $game_id
     */
    public function getRandAward($game_id)
    {
        // 获取游戏详情
        $promotionGameDetail = $this->getPromotionGameDetail($game_id);
        if (!empty($promotionGameDetail)) {
            // 中奖概率 按百分比
            $winning_rate = round($promotionGameDetail["winning_rate"]);
            // 取一个 1 到 100 的随机数 如果这个数组小于概率则通过 第一步
            $rand_num = mt_rand(1, 100);
            if ($rand_num <= $winning_rate) {
                $rule_list = $promotionGameDetail["rule"];
                $retval    = $this->getRandAwardRules($rule_list);
                if (count($retval) > 0) {
                    return $result = array(
                        "is_winning" => 1,
                        "winning_info" => $retval,
                        "no_winning_instruction" => $promotionGameDetail["no_winning_des"]
                    );
                } else {
                    return $result = array(
                        "is_winning" => 0,
                        "no_winning_instruction" => $promotionGameDetail["no_winning_des"],
                        "winning_info" => [
                            "rule_id" => 0
                        ]
                    );
                }
            } else {
                return $result = array(
                    "is_winning" => 0,
                    "no_winning_instruction" => $promotionGameDetail["no_winning_des"],
                    "winning_info" => [
                        "rule_id" => 0
                    ]
                );
            }
        } else {
            return null;
        }
    }

    /**
     * 获取随机奖项
     *
     * @param unknown $rule_list
     */
    public function getRandAwardRules($rule_list)
    {
        $result = array();
        if (count($rule_list) > 0) {
            $new_rule_list = array();
            foreach ($rule_list as $k => $v) {
                if ($v["remaining_number"] > 0) {
                    for ($i = 0; $i < $v["remaining_number"]; $i++) {
                        $rule            = [
                            "rule_id" => $v["rule_id"],
                            "type" => $v["type"],
                            "coupon_type_id" => $v["coupon_type_id"],
                            "points" => $v["points"],
                            "hongbao" => $v["hongbao"],
                            "gift_id" => $v["gift_id"],
                            "rule_name" => $v["rule_name"],
                            "type_value" => $v["type_value"]
                        ];
                        $new_rule_list[] = $rule;
                    }
                }
            }
            if (count($new_rule_list) > 0) {
                $result = array_rand($new_rule_list, 1);
                $result = $new_rule_list[$result];
            } else {
                return $result;
            }
        }
        return $result;
    }

    /**
     * 添加营销游戏获奖记录
     * code码 -1：出错，0：未中奖，1：已中奖
     * 创建时间：2018年1月30日16:02:51 王永杰
     */
    public function addPromotionGamesWinningRecords($uid, $shop_id, $game_id, $rule_id)
    {
        if (empty($uid)) {
            return array(
                'code' => -1,
                'message' => '缺少参数$uid'
            );
        }
        if (empty($game_id)) {
            return array(
                'code' => -1,
                'message' => '缺少参数$game_id'
            );
        }
        try {
            $game_winning_model     = new NsPromotionGamesWinningRecordsModel(); // 获奖记录
            $games_model            = new NsPromotionGamesModel(); // 营销游戏
            $game_rule_model        = new NsPromotionGameRuleModel(); // 营销游戏规则
            $user                   = new UserModel();
            $member_account         = new NsMemberAccountModel();
            $member_account_service = new MemberAccount();
            $member_coupon          = new MemberCoupon();

            $game_winning_model->startTrans();

            $game_condition             = array();
            $game_condition['game_id']  = $game_id;
            $game_condition['end_time'] = [
                ">",
                time()
            ];
            $game_info                  = $games_model->getInfo($game_condition, "name,game_type,points");
            if (empty($game_info)) {
                return array(
                    'code' => -1,
                    'message' => '游戏不存在，或者已经结束啦'
                );
            }

            // 消耗积分
            $member_account_info = $member_account->getInfo([
                'uid' => $uid
            ], "point");

            // 剩余积分
            $residual_integral = $residual_integral = $member_account_info['point'] - $game_info['points'];
            if ($residual_integral < 0) {
                return array(
                    'code' => -1,
                    'message' => '积分不足，无法参与活动'
                );
            }

            // 消耗积分
            $member_account_service->addMemberAccountData($shop_id, 1, $uid, 0, -$game_info['points'], 11, $game_id, "参与【" . $game_info['name'] . "】营销游戏消耗" . $game_info['points'] . "积分");

            $nick_name = "";

            // 获取用户昵称
            $user_info = $user->getInfo([
                'uid' => $uid
            ], 'nick_name');
            if (!empty($user_info)) {
                if (!empty($user_info['nick_name'])) {
                    $nick_name = $user_info['nick_name'];
                }
            }

            $is_use                    = 0; // 使用状态 0：未使用，1：已使用（除了赠品，其余奖项直接已使用）
            $coupon_id                 = 0; // 赠送优惠券id
            $is_winning                = 0; // 是否中奖，0：未中奖，1：已中奖
            $remark                    = "【" . $user_info['nick_name'] . "】未中奖";
            $associated_gift_record_id = 0; // 关联赠品记录id，后续领取赠品用，默认为0
            $data                      = array();

            // 检测是否中奖
            if ($rule_id > 0) {
                $is_winning     = 1;
                $game_rule_info = $game_rule_model->getInfo([
                    'rule_id' => $rule_id,
                    'game_id' => $game_id
                ], "rule_id,rule_num,remaining_number,type,coupon_type_id,points,hongbao,gift_id,rule_name");
                if (empty($game_rule_info)) {
                    $game_winning_model->rollback();
                    return array(
                        'code' => -1,
                        'message' => '游戏奖项不存在'
                    );
                }

                // 判断奖品有没有
                if ($game_rule_info['remaining_number'] <= 0) {
                    $game_winning_model->rollback();
                    return array(
                        'code' => -1,
                        'message' => '奖品已发放完啦'
                    );
                }

                $reward_content = ""; // 奖励内容
                if ($game_rule_info['type'] == 1) {

                    // 送积分
                    $reward_content = "奖励" . $game_rule_info['points'] . "积分";
                    $member_account_service->addMemberAccountData($shop_id, 1, $uid, 0, $game_rule_info['points'], 11, $game_id, "参与【" . $game_info['name'] . "】营销游戏，获得【" . $game_rule_info['rule_name'] . "】作为奖励，赠送" . $game_rule_info['points'] . "积分");
                    $is_use = 1;
                } elseif ($game_rule_info['type'] == 2) {

                    // 送优惠券
                    $member_coupon_res = $member_coupon->UserAchieveCoupon($uid, $game_rule_info['coupon_type_id'], 4);
                    if ($member_coupon_res > 0) {
                        $coupon_model      = new NsCouponModel();
                        $coupon_info       = $coupon_model->getInfo([
                            "coupon_id" => $member_coupon_res
                        ], "coupon_type_id");
                        $coupon_type_model = new NsCouponTypeModel();
                        $coupon_type_info  = $coupon_type_model->getInfo([
                            "coupon_type_id" => $coupon_info['coupon_type_id']
                        ], "coupon_name");
                        $reward_content    = "奖励【" . $coupon_type_info['coupon_name'] . "】";
                        $coupon_id         = $member_coupon_res;
                        $is_use            = 1; // 使用状态 0：未使用，1：已使用（除了赠品，其余奖项直接已使用）
                    } else {
                        $is_winning = 0;
                    }
                } elseif ($game_rule_info['type'] == 3) {

                    // 送红包（余额）
                    $is_use         = 1; // 使用状态 0：未使用，1：已使用（除了赠品，其余奖项直接已使用）
                    $reward_content = "奖励￥" . $game_rule_info['hongbao'] . "余额红包";
                    $member_account_service->addMemberAccountData($shop_id, 2, $uid, 0, $game_rule_info['hongbao'], 11, $game_id, "参与【" . $game_info['name'] . "】营销游戏，获得【" . $game_rule_info['rule_name'] . "】作为奖励，赠送" . $game_rule_info['hongbao'] . "余额红包");
                } elseif ($game_rule_info['type'] == 4) {

                    // 送赠品
                    $promotion_gift_model                 = new NsPromotionGiftModel();
                    $promotion_gift_condition             = array();
                    $promotion_gift_condition['gift_id']  = $game_rule_info['gift_id'];
                    $promotion_gift_condition['end_time'] = [
                        '>',
                        time()
                    ];
                    $promotion_gift_info                  = $promotion_gift_model->getInfo($promotion_gift_condition, "gift_id,gift_name");
                    if (!empty($promotion_gift_info)) {

                        $promotion_gift_goods_model = new NsPromotionGiftGoodsModel();
                        $promotion_gift_godos_info  = $promotion_gift_goods_model->getInfo([
                            'gift_id' => $promotion_gift_info['gift_id']
                        ], "goods_name,goods_picture");

                        // 判断要赠送的商品是否存在
                        if (!empty($promotion_gift_godos_info)) {

                            $type                = 2; // 领取类型1.满减2.游戏
                            $type_name           = "游戏";
                            $gift_records_remark = "参与营销游戏送赠品";
                            $relate_id           = 0; // 中奖之后先记录起来，在个人中心里的中奖记录中继续领取，生成订单
                            $reward_content .= "奖励赠品【" . $promotion_gift_godos_info['goods_name'] . "】";
                            $gift_grant_records_res = $this->addPromotionGiftGrantRecords($shop_id, $uid, $nick_name, $promotion_gift_info['gift_id'], $promotion_gift_info['gift_name'], $promotion_gift_godos_info['goods_name'], $promotion_gift_godos_info['goods_picture'], $type, $type_name, $relate_id, $gift_records_remark);
                            if ($gift_grant_records_res['code'] > 0) {
                                $associated_gift_record_id = $gift_grant_records_res['code'];
                            } else {
                                // 赠品发放记录添加异常，未中奖
                                $is_winning = 0;
                            }
                        } else {
                            // 要赠送的商品不存在，未中奖
                            $is_winning = 0;
                        }
                    } else {

                        // 赠品活动已结束，未中奖
                        $is_winning = 0;
                    }
                }

                // 游戏活动数量减少一次
                $remaining_number = $game_rule_info['remaining_number'] - 1;
                $game_rule_model->save([
                    'remaining_number' => $remaining_number
                ], [
                    'rule_id' => $rule_id,
                    'game_id' => $game_id
                ]);

                if ($is_winning == 1) {
                    $remark = "【" . $nick_name . "】获得" . $game_rule_info['rule_name'] . "，" . $reward_content;
                }

                $data['uid']                       = $uid; // 用户id
                $data['shop_id']                   = $shop_id; // 店铺id
                $data['is_use']                    = $is_use; // 是否使用,除了赠品外，其余的都是已使用
                $data['game_id']                   = $game_id; // 活动id
                $data['game_type']                 = $game_info['game_type']; // 游戏类型1.大转盘2.刮刮乐3.九宫格
                $data['type']                      = $game_rule_info['type']; // 奖励类型1.积分2.优惠券3.红包4.赠品...
                $data['points']                    = $game_rule_info['points']; // 奖励积分
                $data['hongbao']                   = $game_rule_info['hongbao']; // 红包数（余额）
                $data['coupon_id']                 = $coupon_id; // 奖励优惠券
                $data['gift_id']                   = $game_rule_info['gift_id']; // 赠品id
                $data['remark']                    = $remark; // 说明
                $data['is_winning']                = $is_winning; // 该次是否中奖 0未中奖 1中奖
                $data['nick_name']                 = $nick_name; // 会员昵称
                $data['add_time']                  = time(); // 添加时间
                $data['rule_id']                   = $game_rule_info['rule_id']; // 奖项id
                $data['associated_gift_record_id'] = $associated_gift_record_id; // 关联赠品记录id，后续领取赠品用，默认为0
            } else {

                $data['uid']                       = $uid; // 用户id
                $data['shop_id']                   = $shop_id; // 店铺id
                $data['is_use']                    = $is_use; // 是否使用,除了赠品外，其余的都是已使用
                $data['game_id']                   = $game_id; // 活动id
                $data['game_type']                 = $game_info['game_type']; // 游戏类型1.大转盘2.刮刮乐3.九宫格
                $data['type']                      = 0; // 奖励类型1.积分2.优惠券3.红包4.赠品...
                $data['points']                    = 0; // 奖励积分
                $data['hongbao']                   = 0; // 红包数（余额）
                $data['coupon_id']                 = $coupon_id; // 奖励优惠券
                $data['gift_id']                   = 0; // 赠品id
                $data['remark']                    = $remark; // 说明
                $data['is_winning']                = $is_winning; // 该次是否中奖 0未中奖 1中奖
                $data['nick_name']                 = $nick_name; // 会员昵称
                $data['add_time']                  = time(); // 添加时间
                $data['rule_id']                   = 0; // 奖项id
                $data['associated_gift_record_id'] = $associated_gift_record_id; // 关联赠品记录id，后续领取赠品用，默认为0
            }
            $res = $game_winning_model->save($data);
            if ($res > 0) {

                $game_winning_model->commit();
                return array(
                    'code' => $is_winning,
                    'message' => '添加获奖记录成功'
                );
            } else {

                $game_winning_model->rollback();
                return array(
                    'code' => $is_winning,
                    'message' => '添加获奖记录失败'
                );
            }
        } catch (\Exception $e) {
            $game_winning_model->rollback();
        }
    }

    /**
     * 会员获取赠品
     * 创建时间：2018年1月31日19:44:48
     *
     * @param 用户id $uid
     * @param 赠品记录id $gift_records_id
     * @param 收货人的手机号码 $receiver_mobile
     * @param 收货人所在省 $receiver_province
     * @param 收货人所在城市 $receiver_city
     * @param 收货人所在街道 $receiver_district
     * @param 收货人详细地址 $receiver_address
     * @param 收货人邮编 $receiver_zip
     * @param 收货人姓名 $receiver_name
     * @param 买家附言 $buyer_message
     * @param 固定电话 $fixed_telephone
     * @return string|Ambigous
     */
    public function userAchieveGift($uid, $gift_records_id, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $buyer_message, $fixed_telephone = "")
    {
        $gift_records_model = new NSPromotionGiftGrantRecordsModel();
        $gift_records_info  = $gift_records_model->getInfo([
            'id' => $gift_records_id
        ], '*');
        if (empty($gift_records_info)) {
            return $result = array(
                "code" => 0,
                "message" => '信息不存在'
            );
        } elseif ($gift_records_info['uid'] != $uid) {
            return $result = array(
                "code" => 0,
                "message" => '领取人错误'
            );
        } elseif ($gift_records_info['relate_id'] != 0) {
            return $result = array(
                "code" => 0,
                "message" => '赠品已领取'
            );
        }
        $gift_records_model->startTrans();
        try {
            $promotion_gift_goods_model = new NsPromotionGiftGoodsModel();
            $promotion_gift_goods_info  = $promotion_gift_goods_model->getInfo([
                'gift_id' => $gift_records_info['gift_id']
            ], "goods_id,goods_name");
            if (!empty($promotion_gift_goods_info['goods_id'])) {
                $goods_sku_model = new NsGoodsSkuModel();
                $sku_info        = $goods_sku_model->getInfo([
                    'goods_id' => $promotion_gift_goods_info['goods_id']
                ], 'sku_id, sku_name,stock');
            } else {
                return $result = array(
                    "code" => 0,
                    "message" => '商品信息丢失'
                );
            }
            // 单店版查询网站内容
            $web_site  = new WebSite();
            $web_info  = $web_site->getWebSiteInfo();
            $shop_name = $web_info['title'];
            // 添加订单
            $order_model   = new NsOrderModel();
            $order_service = new OrderService();
            $data_order    = array(
                'order_type' => 1,
                'order_no' => $order_service->createOrderNo($gift_records_info['shop_id']),
                'out_trade_no' => '',
                'payment_type' => 1,
                'shipping_type' => 1,
                'order_from' => 1,
                'buyer_id' => $uid,
                'user_name' => $gift_records_info['nick_name'],
                'buyer_ip' => '',
                'buyer_message' => $buyer_message,
                'buyer_invoice' => '',
                'shipping_time' => '', // datetime NOT NULL COMMENT '买家要求配送时间',
                'receiver_mobile' => $receiver_mobile, // varchar(11) NOT NULL DEFAULT '' COMMENT '收货人的手机号码',
                'receiver_province' => $receiver_province, // int(11) NOT NULL COMMENT '收货人所在省',
                'receiver_city' => $receiver_city, // int(11) NOT NULL COMMENT '收货人所在城市',
                'receiver_district' => $receiver_district, // int(11) NOT NULL COMMENT '收货人所在街道',
                'receiver_address' => $receiver_address, // varchar(255) NOT NULL DEFAULT '' COMMENT '收货人详细地址',
                'receiver_zip' => $receiver_zip, // varchar(6) NOT NULL DEFAULT '' COMMENT '收货人邮编',
                'receiver_name' => $receiver_name, // varchar(50) NOT NULL DEFAULT '' COMMENT '收货人姓名',
                'shop_id' => $gift_records_info['shop_id'], // int(11) NOT NULL COMMENT '卖家店铺id',
                'shop_name' => $shop_name, // varchar(100) NOT NULL DEFAULT '' COMMENT '卖家店铺名称',
                'goods_money' => 0, // decimal(19, 2) NOT NULL COMMENT '商品总价',
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
                'order_status' => 1, // tinyint(4) NOT NULL COMMENT '订单状态',
                'pay_status' => 1, // tinyint(4) NOT NULL COMMENT '订单付款状态',
                'shipping_status' => 0, // tinyint(4) NOT NULL COMMENT '订单配送状态',
                'review_status' => 0, // tinyint(4) NOT NULL COMMENT '订单评价状态',
                'feedback_status' => 0, // tinyint(4) NOT NULL COMMENT '订单维权状态',
                'user_platform_money' => 0, // 平台余额支付
                'coin_money' => 0,
                'create_time' => time(),
                "give_point_type" => 0,
                'shipping_company_id' => 0,
                'fixed_telephone' => $fixed_telephone
            );
            $order_model->save($data_order);
            $order_id        = $order_model->order_id;
            $order_goods     = new OrderGoods();
            $res_order_goods = $order_goods->addOrderGiftGoods($order_id, $sku_info['sku_id'] . ':1');
            // 订单项
            $order_goods_module = new NsOrderGoodsModel();
            $order_goods_id     = $order_goods_module->getInfo([
                "order_id" => $order_id
            ], "order_goods_id")["order_goods_id"];

            if ($res_order_goods > 0) {
                // 订单赠品发放记录 关联订单项id
                $gift_records_model->save([
                    'relate_id' => $order_goods_id
                ], [
                    'id' => $gift_records_id
                ]);
                // 获奖记录表更新使用状态
                $ns_winning_records = new NsPromotionGamesWinningRecordsModel();
                $ns_winning_records->save([
                    "is_use" => 1
                ], [
                    "associated_gift_record_id" => $gift_records_id
                ]);
            } else {
                $gift_records_model->rollback();
                return $result = array(
                    "code" => 0,
                    "message" => '赠品创建订单失败'
                );
            }
            $order_service->addOrderAction($order_id, $uid, '创建赠品订单');
            $gift_records_model->commit();
            return $result = array(
                "code" => 1,
                "message" => '奖品领取成功！请到我的订单中查看'
            );
        } catch (\Exception $e) {
            $gift_records_model->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 根据主键关闭营销游戏
     * 创建时间：2018年1月31日17:54:58 王永杰
     *
     * @param unknown $game_id
     */
    public function closePromotionGame($game_id)
    {
        $promotion_games_model = new NsPromotionGamesModel();
        $res                   = $promotion_games_model->save([
            "status" => -2
        ], [
            'game_id' => $game_id
        ]);
        return $res;
    }

    /**
     *
     * 获取用户的中奖记录
     * 创建时间：2018年1月31日19:40:33
     *
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param string $order
     * @return Ambigous <\data\model\unknown, \data\model\multitype:unknown, multitype:unknown number >
     */
    public function getUserPromotionGamesWinningRecords($page_index, $page_size, $condition, $order = "np_pgwr.id desc")
    {
        $model = new NsPromotionGamesWinningRecordsModel();
        $res   = $model->getUserPromotionGamesWinningRecordsViewList($page_index, $page_size, $condition, $order);
        return $res;
    }


    # todo   @dai  新增员工内购


    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromote::addPromotionNeiGou()
     */
    public function addPromotionNeiGou($mansong_name, $start_time, $end_time, $shop_id, $remark, $type, $range_type, $rule, $goods_sku_array, $is_neigou)
    {
        $promot_mansong = new NsPromotionMansongModel();
        $promot_mansong->startTrans();
        try {
            $err       = 0;
            $shop_name = $this->instance_name;

            $data = array(
                'mansong_name' => $mansong_name,
                'start_time'  => getTimeTurnTimeStamp($start_time),
                'end_time'    => getTimeTurnTimeStamp($end_time),
                'shop_id'     => $shop_id,
                'shop_name'   => $shop_name,
                'status'      => 0, // 状态重新设置
                'remark'      => $remark,
                'type'        => $type,
                'range_type'  => $range_type,
                'is_neigou'   => $is_neigou,
                'create_time' => time()
            );
            $promot_mansong->save($data);
            $mansong_id = $promot_mansong->mansong_id;

            # 添加活动规则表
            $rule_array = explode(';', $rule);
            foreach ($rule_array as $k => $v) {
                $get_rule            = explode(',', $v);
                $get_rule[4]         = rtrim(strtr($get_rule[4], '/', ','), ',');
                $data_rule           = array(
                    'mansong_id'    => $mansong_id,
                    'price'         => $get_rule[0],
                    'discount'      => $get_rule[1],
                    'free_shipping' => $get_rule[2],
                    'give_coupon'   => $get_rule[3],
                    'gift_id'       => $get_rule[4]
                );
                $promot_mansong_rule = new NsPromotionMansongRuleModel();
                $res                 = $promot_mansong_rule->save($data_rule);
            }

            if (!empty($get_rule[4])) {
                $rule_dat = explode(',', $get_rule[4]);
                for ($i = 0; $i < count($rule_dat); $i++) {
                    $_sku_id              = \think\Db::name('ns_promotion_gift_goods')->where(['gift_id' => explode(':', $rule_dat[$i])[0]])->find()['sku_id'];
                    $_data['rule_id']     = $res;
                    $_data['sku_id']      = $_sku_id;
                    $_data['num']         = explode(':', $rule_dat[$i])[1];
                    $_data['create_time'] = time();
                    \think\Db::name('ns_promotion_rule_goods')->insert($_data);
                }
            }
            # todo  换内购表
            if ($range_type == 0 && !empty($goods_sku_array)) {
                #部分商品
                $goods_sku_array = explode(',', $goods_sku_array);
                foreach ($goods_sku_array as $k => $v) {
                    $promotion_neigou_goods = new NsPromotionNeigouGoodsModel();
                    $discount_info          = explode(':', $v);

                    #goods_id:sku_id:n_price

                    # 查询商品名称图片
                    $goods      = new NsGoodsModel();
                    $goods_info = $goods->getInfo([
                        'goods_id' => $discount_info[0]
                    ], 'goods_name,picture');

                    # 查询sku名称图片
                    $sku      = new NsGoodsSkuModel();
                    $sku_info = $sku->getInfo([
                        'sku_id' => $discount_info[1]
                    ], 'sku_name,picture');

                    $data_goods   = array(
                        'discount_id'   => $mansong_id,
                        'goods_id'      => $discount_info[0],
                        'sku_id'        => $discount_info[1],
                        'n_price'       => 0,
                        'n_discount'    => $discount_info[2],
                        'sku_num'       => $discount_info[3],
                        'status'        => 0,
                        'goods_name'    => $goods_info['goods_name'],
                        'sku_name'      => $sku_info['sku_name'],
                        'goods_picture' => $goods_info['picture'],
                        'sku_picture'   => $sku_info['picture'],
                        'start_time'    => getTimeTurnTimeStamp($start_time),
                        'end_time'      => getTimeTurnTimeStamp($end_time)
                    );
//                    $data_goods_1 = array(
//                        'mansong_id'    => $mansong_id,
//                        'goods_id'      => $discount_info[0],
//                        'sku_id'        => $discount_info[1],
//                        'sku_num'       => $discount_info[3],
//                        'goods_name'    => $goods_info['goods_name'],
//                        'sku_name'      => $sku_info['sku_name'],
//                        'goods_picture' => $goods_info['picture'],
//                        'sku_picture'   => $sku_info['picture'],
//                        'status'        => 0, // 状态重新设置
//                        'start_time'    => getTimeTurnTimeStamp($start_time),
//                        'end_time'      => getTimeTurnTimeStamp($end_time)
//                    );
//                    \think\Db::name('ns_promotion_mansong_goods')->insert($data_goods_1);
                    $promotion_neigou_goods->save($data_goods);
                }
            }
            if ($err > 0) {
                $promot_mansong->rollback();
                return ACTIVE_REPRET;
            } else {
                $promot_mansong->commit();
                return $mansong_id;
            }
        } catch (\Exception $e) {
            $promot_mansong->rollback();
            return $e->getMessage();
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromote::updatePromotionNeiGou()
     */
    public function updatePromotionNeiGou($discount_id, $mansong_name, $start_time, $end_time, $shop_id, $remark, $type, $range_type, $rule, $goods_sku_arr, $is_neigou)
    {
        $promot_mansong = new NsPromotionMansongModel();

        $promot_mansong->startTrans();
        try {

            $shop_name = $this->instance_name;
            $data      = array(
                'mansong_name' => $mansong_name,
                'start_time'   => getTimeTurnTimeStamp($start_time),
                'end_time'     => getTimeTurnTimeStamp($end_time),
                'shop_id'      => $shop_id,
                'shop_name'    => $shop_name,
                'status'       => 0, // 状态重新设置
                'remark'       => $remark,
                'type'         => $type,
                'range_type'   => $range_type,
                'is_neigou'    => $is_neigou,
                'create_time'  => time()
            );
            $promot_mansong->save($data, [
                'mansong_id' => $discount_id
            ]);
            $goods_id_array = explode(',', $goods_sku_arr);

            # todo  ..........  编辑赠品待完成

            # 添加活动规则表
            \think\Db::name('ns_promotion_mansong_rule')->where(['mansong_id' => $discount_id])->delete();
            $rule_array = explode(';', $rule);
            foreach ($rule_array as $k => $v) {
                $get_rule            = explode(',', $v);
                $get_rule[4]         = rtrim(strtr($get_rule[4], '/', ','), ',');
                $data_rule           = array(
                    'mansong_id'    => $discount_id,
                    'price'         => $get_rule[0],
                    'discount'      => $get_rule[1],
                    'free_shipping' => $get_rule[2],
                    'give_coupon'   => $get_rule[3],
                    'gift_id'       => $get_rule[4]
                );
                $promot_mansong_rule = new NsPromotionMansongRuleModel();
                $res                 = $promot_mansong_rule->save($data_rule);
            }

            if (!empty($get_rule[4])) {
                $rule_dat = explode(',', $get_rule[4]);
                for ($i = 0; $i < count($rule_dat); $i++) {
                    $_sku_id              = \think\Db::name('ns_promotion_gift_goods')->where(['gift_id' => explode(':', $rule_dat[$i])[0]])->find()['sku_id'];
                    $_data['rule_id']     = $res;
                    $_data['sku_id']      = $_sku_id;
                    $_data['num']         = explode(':', $rule_dat[$i])[1];
                    $_data['create_time'] = time();
                    \think\Db::name('ns_promotion_rule_goods')->insert($_data);
                }
            }

            \think\Db::name('ns_promotion_neigou_goods')->where(['discount_id' => $discount_id])->delete();
            \think\Db::name('ns_promotion_mansong_goods')->where(['mansong_id' => $discount_id])->delete();
            foreach ($goods_id_array as $k => $v) {
                $discount_info = explode(':', $v);

                // 查询商品名称图片
                $goods      = new NsGoodsModel();
                $goods_info = $goods->getInfo([
                    'goods_id' => $discount_info[0]
                ], 'goods_name,picture');

                # 查询sku名称图片
                $sku      = new NsGoodsSkuModel();
                $sku_info = $sku->getInfo([
                    'sku_id' => $discount_info[1]
                ], 'sku_name,picture');

                $data_goods   = array(
                    'discount_id'   => $discount_id,
                    'goods_id'      => $discount_info[0],
                    'sku_id'        => $discount_info[1],
                    'n_price'       => 0,
                    'n_discount'    => $discount_info[2],
                    'sku_num'       => $discount_info[3],
                    'goods_name'    => $goods_info['goods_name'],
                    'sku_name'      => $sku_info['sku_name'],
                    'goods_picture' => $goods_info['picture'],
                    'sku_picture'   => $sku_info['picture'],
                    'status'        => 0,
                    'start_time'    => getTimeTurnTimeStamp($start_time),
                    'end_time'      => getTimeTurnTimeStamp($end_time)
                );
                $data_goods_1 = array(
                    'mansong_id'    => $discount_id,
                    'goods_id'      => $discount_info[0],
                    'goods_name'    => $goods_info['goods_name'],
                    'sku_name'      => $sku_info['sku_name'],
                    'goods_picture' => $goods_info['picture'],
                    'sku_picture'   => $sku_info['picture'],
                    'status'        => 0,                                   // 状态重新设置
                    'start_time'    => getTimeTurnTimeStamp($start_time),
                    'end_time'      => getTimeTurnTimeStamp($end_time)
                );
                \think\Db::name('ns_promotion_mansong_goods')->insert($data_goods_1);
                \think\Db::name('ns_promotion_neigou_goods')->insert($data_goods);
            }
            $promot_mansong->commit();
            return $discount_id;
        } catch (\Exception $e) {
            $promot_mansong->rollback();
            return $e->getMessage();
        }
    }


    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromote::addPromotionMansong()
     */
    public function addPromotionMansongActiviy($mansong_id , $mansong_name, $start_time, $end_time, $shop_id, $remark, $type, $rule, $is_neigou)
    {

        $promot_mansong = new NsPromotionMansongModel();
        $goods_mansong  = new GoodsMansong();
        $promot_mansong->startTrans();
        try {
            $err        = 0;
//            $count_quan = $goods_mansong->getQuanmansong($start_time, $end_time);
//            if ($count_quan > 0 && $range_type == 1) {
//                $err = 1;
//            }

            $shop_name = $this->instance_name;
            $data      = array(
                'mansong_name' => $mansong_name,
                'start_time'   => getTimeTurnTimeStamp($start_time),
                'end_time'     => getTimeTurnTimeStamp($end_time),
                'shop_id'      => $shop_id,
                'shop_name'    => $shop_name,
                'status'       => 0, // 状态重新设置
                'remark'       => $remark,
                'type'         => $type,
                'is_neigou'    => $is_neigou,
                'create_time'  => time()
            );

            if( $mansong_id > 0 ) {
                $promot_mansong->save($data,['mansong_id' => $mansong_id]);
            } else {
                $promot_mansong->save($data);
                $mansong_id = $promot_mansong->mansong_id;
            }

            // 添加活动规则表   todo.... save

            $rule_array = explode(';', $rule);
            foreach ($rule_array as $k => $v) {
                $get_rule            = explode(',', $v);
                $get_rule[5]         = rtrim(strtr($get_rule[5], '/', ','), ',');
                $data_rule           = array(
                    'mansong_id'    => $mansong_id,
                    'price'         => $get_rule[0],
                    'discount'      => $get_rule[1],
                    'free_shipping' => $get_rule[2],
                    'give_point'    => $get_rule[3],
                    'give_coupon'   => $get_rule[4],
                    'gift_id'       => $get_rule[5]
                );
                $promot_mansong_rule = new NsPromotionMansongRuleModel();

                if( $mansong_id > 0 ){
                    $res = $promot_mansong_rule->save($data_rule,['mansong_id' => $mansong_id]);

                } else {

                    $res = $promot_mansong_rule->save($data_rule);

                }

                if (!empty($get_rule[5])) {
                    $rule_dat = explode(',', $get_rule[5]);
                    for ($i = 0; $i < count($rule_dat); $i++) {
                        $_sku_id              = \think\Db::name('ns_promotion_gift_goods')->where(['gift_id' => explode(':', $rule_dat[$i])[0]])->find()['sku_id'];
                        $_data['rule_id']     = $res;
                        $_data['sku_id']      = $_sku_id;
                        $_data['num']         = explode(':', $rule_dat[$i])[1];
                        $_data['create_time'] = time();
                        \think\Db::name('ns_promotion_rule_goods')->insert($_data);
                    }
                }
            }

            if ($err > 0) {
                $promot_mansong->rollback();
                return ACTIVE_REPRET;
            } else {
                $promot_mansong->commit();
                return $mansong_id;
            }
        } catch (\Exception $e) {
            $promot_mansong->rollback();
            return $e->getMessage();
        }
    }

    public function addMansongGoods($mansong_id,$sku_id,$start_time,$end_time){
        # 满减送商品表
        $goods_mansong  = new GoodsMansong();

        $goods_mansong->startTrans();
        try {
            $err       = 0;
            // 部分商品
            $promotion_mansong_goods = new NsPromotionMansongGoodsModel();
            // 查询商品名称图片
            $goods      = new NsGoodsModel();

            // 查询规格名称图片
            $sku      = new NsGoodsSkuModel();
            $sku_info = $sku->getInfo([
                'sku_id' => $sku_id
            ], 'goods_id,sku_name,picture');

            $goods_info = $goods->getInfo([
                'goods_id' => $sku_info['goods_id']
            ], 'goods_name,picture');

            $data_goods = array(
                'mansong_id'    => $mansong_id,
                'goods_id'      => $sku_info['goods_id'],
                'sku_id'        => $sku_id,
                'goods_name'    => $goods_info['goods_name'],
                'sku_name'      => $sku_info['sku_name'],
                'goods_picture' => $goods_info['picture'],
                'sku_picture'   => $sku_info['picture'],
                'status'        => 0, // 状态重新设置
                'start_time'    => getTimeTurnTimeStamp($start_time),
                'end_time'      => getTimeTurnTimeStamp($end_time)
            );
            $count      = $goods_mansong->getGoodsIsMansong($sku_id, $start_time, $end_time);
            if ($count > 0) {
                $err = 1;
            }
            $promotion_mansong_goods->save($data_goods);

            if ($err > 0) {
                $goods_mansong->rollback();
                return ACTIVE_REPRET;
            } else {

                $goods_mansong->commit();
                return 1;
            }
        } catch (\Exception $e) {
            $goods_mansong->rollback();
            return $e->getMessage();
        }
    }
}