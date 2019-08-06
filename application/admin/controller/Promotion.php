<?php
/**
 * Promotion.php
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
namespace app\admin\controller;

use data\model\NsGoodsModel;
use data\model\NsGoodsSkuModel;
use data\model\NsPromotionDiscountGoodsViewModel;
use data\model\NsPromotionMansongGoodsModel;
use data\model\NsPromotionMansongModel;
use data\model\NsPromotionNeigouGoodsModel;
use data\service\Address;
use data\service\Config;
use data\service\GoodsCategory;
use data\service\promotion\PromoteRewardRule;
use data\service\Promotion as PromotionService;
use data\service\Member;

/**
 * 营销控制器
 *
 * @author Administrator
 *
 */
class Promotion extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 优惠券类型列表
     *
     * @return multitype:number unknown |Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function couponTypeList()
    {
        if (request()->isAjax()) {
            $page_index  = request()->post("page_index", 1);
            $page_size   = request()->post("page_size", PAGESIZE);
            $search_text = request()->post('search_text', '');
            $coupon      = new PromotionService();
            $condition   = array(
                'shop_id' => $this->instance_id,
                'coupon_name' => array(
                    'like',
                    '%' . $search_text . '%'
                )
            );
            $list        = $coupon->getCouponTypeList($page_index, $page_size, $condition, 'start_time desc');
            return $list;
        } else {
            $this->admin_user_record('查看优惠券类型列表','','');
            return view($this->style . "Promotion/couponTypeList");
        }
    }

    /**
     * 删除优惠券类型
     */
    public function delcoupontype()
    {
        $coupon_type_id = request()->post('coupon_type_id', '');
        if (empty($coupon_type_id)) {
            $this->error("没有获取到优惠券信息");
        }
        $coupon = new PromotionService();
        $res    = $coupon->deletecouponType($coupon_type_id);
        $this->admin_user_record('删除优惠券类型',$coupon_type_id,'');
        return AjaxReturn($res);
    }

    /**
     * 添加优惠券类型
     */
    public function addCouponType()
    {
        if (request()->isAjax()) {
            #获取参数
            $coupon_name        = request()->post('coupon_name', '');
            $money              = request()->post('money', '');
            $count              = request()->post('count', '');
            $max_fetch          = request()->post('max_fetch', '');
            $at_least           = request()->post('at_least', '');
            $need_user_level    = request()->post('need_user_level', '');
            $range_type         = request()->post('range_type', '');
            $start_time         = request()->post('start_time', '');
            $end_time           = request()->post('end_time', '');
            $is_show            = request()->post('is_show', '');
            $is_show_detail     = request()->post('is_show_detail', '');
            $send_type          = request()->post('send_type', '');
            $limit_repeated_use = request()->post('limit_repeated_use', '');
            $use_type           = request()->post('use_type', '');
            $get_after_days     = request()->post('get_after_days', '');
            $pay_money_get      = request()->post('pay_money_get', '');
            $goods_list         = request()->post('goods_list', '');

            $coupon = new PromotionService();
            $retval = $coupon->addCouponType($coupon_name, $money, $count, $max_fetch, $at_least, $need_user_level, $range_type, $start_time, $end_time, $is_show, $is_show_detail, $goods_list, $send_type, $limit_repeated_use, $use_type, $get_after_days, $pay_money_get);
            $this->admin_user_record('添加优惠券类型',$retval,'');

            return AjaxReturn($retval);
        } else {
            // 查找一级商品分类
            $goodsCategory    = new GoodsCategory();
            $oneGoodsCategory = $goodsCategory->getGoodsCategoryListByParentId(0);

            $this->assign("oneGoodsCategory", $oneGoodsCategory);
            return view($this->style . "Promotion/addCouponType");
        }
    }

    public function updateCouponType()
    {
        $coupon = new PromotionService();
        if (request()->isAjax()) {
            $coupon_type_id     = request()->post('coupon_type_id', '');
            $coupon_name        = request()->post('coupon_name', '');
            $money              = request()->post('money', '');
            $count              = request()->post('count', '');
            $repair_count       = request()->post('repair_count', '');
            $max_fetch          = request()->post('max_fetch', '');
            $at_least           = request()->post('at_least', '');
            $need_user_level    = request()->post('need_user_level', '');
            $range_type         = request()->post('range_type', '');
            $start_time         = request()->post('start_time', '');
            $end_time           = request()->post('end_time', '');
            $is_show            = request()->post('is_show', '');
            $is_show_detail     = request()->post('is_show_detail', '');
            $send_type          = request()->post('send_type', '');
            $limit_repeated_use = request()->post('limit_repeated_use', '');
            $use_type           = request()->post('use_type', '');
            $get_after_days     = request()->post('get_after_days', '');
            $pay_money_get      = request()->post('pay_money_get', '');
            $goods_list         = request()->post('goods_list', '');

            $retval = $coupon->updateCouponType($coupon_type_id, $coupon_name, $money, $count, $repair_count, $max_fetch, $at_least, $need_user_level, $range_type, $start_time, $end_time, $is_show, $is_show_detail, $goods_list, $send_type, $limit_repeated_use, $use_type, $get_after_days, $pay_money_get);
            $this->admin_user_record('更新优惠券类型',$retval,'');

            return AjaxReturn($retval);
        } else {
            $coupon_type_id = request()->get('coupon_type_id', 0);
            if ($coupon_type_id == 0) {
                $this->error("没有获取到类型");
            }
            $coupon_type_data = $coupon->getCouponTypeDetail($coupon_type_id);
            $goods_id_array   = array();
            foreach ($coupon_type_data['goods_list'] as $k => $v) {
                $goods_id_array[]                                       = $v['goods_id'];
                $coupon_type_data['goods_list'][$k]['category_id_name'] = \think\Db::name('ns_goods_category')->where(['category_id' => $v['category_id_1']])->find()['category_name'];
                if ($v['goods_type'] == 0) {
                    $coupon_type_data['goods_list'][$k]['goods_type'] = '虚拟商品';
                } else if ($v['goods_type'] == 1) {
                    $coupon_type_data['goods_list'][$k]['goods_type'] = '实物商品';
                } else if ($v['goods_type'] == 2) {
                    $coupon_type_data['goods_list'][$k]['goods_type'] = '实物礼品';
                } else {
                    $coupon_type_data['goods_list'][$k]['goods_type'] = '会员';
                }
            }

            $coupon_type_data['goods_id_array'] = $goods_id_array;

            $this->assign("coupon_type_info", $coupon_type_data);

            // 查找一级商品分类
            $goodsCategory    = new GoodsCategory();
            $oneGoodsCategory = $goodsCategory->getGoodsCategoryListByParentId(0);

            $this->assign("oneGoodsCategory", $oneGoodsCategory);

            return view($this->style . "Promotion/updateCouponType");
        }
    }

    /**
     * 获取优惠券详情
     */
    public function getCouponTypeInfo()
    {
        $coupon           = new PromotionService();
        $coupon_type_id   = request()->post('coupon_type_id', '');
        $coupon_type_data = $coupon->getCouponTypeDetail($coupon_type_id);
        return $coupon_type_data;
    }

    /**
     * 功能：积分管理
     * 创建：左骐羽
     * 时间：2016年12月8日15:02:16
     */
    public function pointConfig()
    {
        $pointConfig = new PromotionService();
        if (request()->isAjax()) {
            $convert_rate = request()->post('convert_rate', '');
            $is_open      = request()->post('is_open', 0);
            $desc         = request()->post('desc', 0);
            $retval       = $pointConfig->setPointConfig($convert_rate, $is_open, $desc);
            return AjaxReturn($retval);
        }
        $pointconfiginfo = $pointConfig->getPointConfig();
        $this->assign("pointconfiginfo", $pointconfiginfo);
        $this->admin_user_record('查看积分管理','','');

        return view($this->style . "Promotion/pointConfig");
    }

    /**
     * 赠品列表
     * wzy
     */
    public function giftList()
    {
        $child_menu_list = array(
            array(
                'url' => "Promotion/giftList",
                'menu_name' => "赠品列表",
                "active" => 1
            ),
            array(
                'url' => "promotion/giftGrantRecordsList",
                'menu_name' => "赠品发放记录",
                "active" => 0
            )
        );

        $this->assign("child_menu_list", $child_menu_list);

        if (request()->isAjax()) {
            $page_index  = request()->post("page_index", 1);
            $page_size   = request()->post("page_size", PAGESIZE);
            $search_text = request()->post('search_text');
            $type        = request()->post("type", 0);
            $gift        = new PromotionService();
            $condition   = array(
                'shop_id' => $this->instance_id,
                'gift_name' => array(
                    'like',
                    '%' . $search_text . '%'
                )
            );

            if ($type == 1) {
                $condition["start_time"] = [
                    "LT",
                    time()
                ];
                $condition["end_time"]   = [
                    "GT",
                    time()
                ];
            }

            $list = $gift->getPromotionGiftList($page_index, $page_size, $condition);
            return $list;
        }
        $this->admin_user_record('查看赠品列表','','');

        return view($this->style . "Promotion/giftList");
    }

    /**
     * 赠品发放记录列表
     * 创建时间：2018年1月25日16:11:39 全栈小学生
     *
     * @return Ambigous <\data\model\multitype:unknown, multitype:unknown number >|Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function giftGrantRecordsList()
    {
        $child_menu_list = array(
            array(
                'url' => "Promotion/giftList",
                'menu_name' => "赠品列表",
                "active" => 0
            ),
            array(
                'url' => "promotion/giftGrantRecordsList",
                'menu_name' => "赠品发放记录",
                "active" => 1
            )
        );

        $this->assign("child_menu_list", $child_menu_list);
        if (request()->isAjax()) {
            $page_index                 = request()->post("page_index", 1);
            $page_size                  = request()->post("page_size", PAGESIZE);
            $search_text                = request()->post("search_text", "");
            $condition['pgr.gift_name'] = [
                'like',
                "%$search_text%"
            ];
            $gift                       = new PromotionService();
            $list                       = $gift->getPromotionGiftGrantRecordsList($page_index, $page_size, $condition, "pgr.id desc");
            return $list;
        }
        $this->admin_user_record('查看赠品发放记录列表','','');

        return view($this->style . "Promotion/giftGrantRecordsList");
    }

    /**
     * 添加赠品
     *
     * @return \think\response\View
     */
    public function addGift()
    {
        if (request()->isAjax()) {
            $shop_id    = $this->instance_id;
            $gift_name  = request()->post('gift_name', ''); // 赠品活动名称
            $gift_num   = request()->post('gift_num', ''); // 赠品活动数量
            $start_time = request()->post('start_time', ''); // 赠品活动开始时间
            $end_time   = request()->post('end_time', ''); // 赠品活动结束时间
            $sku_id     = request()->post('sku_id', ''); // 要赠送的sku_id
            $days       = request()->post('days', ''); // 领取有效期/天（0表示不限），2.0版本不用
            $max_num    = request()->post('max_num', ''); // 领取限制(次/人 (0表示不限领取次数))，2.0版本不用
            $gift       = new PromotionService();
            $res        = $gift->addPromotionGift($shop_id, $gift_name, $gift_num, $start_time, $end_time, $days, $max_num, $sku_id);
            $this->admin_user_record('添加赠品',$res,'');
            return AjaxReturn($res);
        }
        // 查找一级商品分类
        $goodsCategory    = new GoodsCategory();
        $oneGoodsCategory = $goodsCategory->getGoodsCategoryListByParentId(0);

        $this->assign("oneGoodsCategory", $oneGoodsCategory);
        return view($this->style . "Promotion/addGift");
    }

    /**
     * 修改赠品
     *
     * @return \think\response\View
     */
    public function updateGift()
    {
        $gift = new PromotionService();
        if (request()->isAjax()) {
            $gift_id    = request()->post('gift_id', '');
            $shop_id    = $this->instance_id;
            $gift_name  = request()->post('gift_name', '');
            $gift_num   = request()->post('gift_num', '');
            $start_time = request()->post('start_time', '');
            $end_time   = request()->post('end_time', '');
            $days       = request()->post('days', '');
            $max_num    = request()->post('max_num', '');
            $sku_id     = request()->post('sku_id', '');
            $n_gift_id  = request()->post('n_gift_id', '');
            $n_num      = request()->post('n_num', '');
            if(!empty($n_gift_id)){
                $replace_info = \think\Db::name('ns_promotion_gift_replace')->where(['gift_id' => $gift_id])->find();
                if(empty($replace_info)){
                    $data['gift_id']     = $gift_id;
                    $data['n_gift_id']   = $n_gift_id;
                    $data['n_num']       = $n_num;
                    $data['create_time'] = time();
                    \think\Db::name('ns_promotion_gift_replace')->insert($data);
                }else{
                    $data['n_gift_id']   = $n_gift_id;
                    $data['n_num']       = $n_num;
                    $data['update_time'] = time();
                    \think\Db::name('ns_promotion_gift_replace')->where(['gift_id' => $gift_id])->update($data);
                }
            }else{
                \think\Db::name('ns_promotion_gift_replace')->where(['gift_id' => $gift_id])->delete();
            }
            $res        = $gift->updatePromotionGift($gift_id, $shop_id, $gift_name, $gift_num, $start_time, $end_time, $days, $max_num, $sku_id);
            $this->admin_user_record('修改赠品',$res,'');
            return AjaxReturn($res);
        } else {
            $gift_id = request()->get('gift_id', 0);
            if (!is_numeric($gift_id)) {
                $this->error('未获取到信息');
            }
            $info              = $gift->getPromotionGiftDetail($gift_id);
            $condition["end_time"] = [
                "gt",
                time()
            ];
            $condition["gift_id"] = [
                "neq",
                $gift_id
            ];
            $replace   = \think\Db::name('ns_promotion_gift')->where($condition)->select();
            $n_gift_id = \think\Db::name('ns_promotion_gift_replace')->where(['gift_id' => $gift_id])->find()['n_gift_id'];
            $n_num     = \think\Db::name('ns_promotion_gift_replace')->where(['gift_id' => $gift_id])->find()['n_num'];
            // 查找一级商品分类
            $goodsCategory    = new GoodsCategory();
            $oneGoodsCategory = $goodsCategory->getGoodsCategoryListByParentId(0);
            $this->assign("oneGoodsCategory", $oneGoodsCategory);
            $this->assign('info', $info);
            $this->assign('n_gift_id', $n_gift_id);
            $this->assign('n_num', $n_num);
            $this->assign('replace', $replace);
            return view($this->style . "Promotion/updateGift");
        }
    }

    /**
     * 获取赠品 详情
     *
     * @param unknown $gift_id
     */
    public function getGiftInfo($gift_id)
    {
        $gift = new PromotionService();
        $info = $gift->getPromotionGiftDetail($gift_id);
        $this->admin_user_record('查看赠品详情',$gift_id,'');
        return $info;
    }

    /**
     * 删除赠品
     *
     * @return unknown[]
     */
    public function deleteGift()
    {
        $gift_id = request()->post("gift_id", 0);
        $gift    = new PromotionService();
        $res     = $gift->deletePromotionGift($gift_id);
        $this->admin_user_record('删除赠品',$gift_id,'');
        return $res;
    }

    /**
     * 满减送 列表
     */
    public function mansongList()
    {
        if (request()->isAjax()) {
            $page_index  = request()->post("page_index", 1);
            $page_size   = request()->post('page_size', PAGESIZE);
            $search_text = request()->post('search_text', '');
            $status      = request()->post('status', '');
            $condition   = array(
                'shop_id' => $this->instance_id,
                'mansong_name' => array(
                    'like',
                    '%' . $search_text . '%'
                )
            );
            $mansong     = new PromotionService();
            if ($status !== '-1') {
                $condition['status'] = $status;
                $list                = $mansong->getPromotionMansongList($page_index, $page_size, $condition);
            } else {
                $list = $mansong->getPromotionMansongList($page_index, $page_size, $condition);
            }
            return $list;
        }

        $status = request()->get('status', -1);
        $this->assign("status", $status);
        $child_menu_list = array(
            array(
                'url' => "promotion/mansonglist",
                'menu_name' => "全部",
                "active" => $status == '-1' ? 1 : 0
            ),
            array(
                'url' => "promotion/mansonglist?status=0",
                'menu_name' => "未发布",
                "active" => $status == 0 ? 1 : 0
            ),
            array(
                'url' => "promotion/mansonglist?status=1",
                'menu_name' => "进行中",
                "active" => $status == 1 ? 1 : 0
            ),
            array(
                'url' => "promotion/mansonglist?status=3",
                'menu_name' => "已关闭",
                "active" => $status == 3 ? 1 : 0
            ),
            array(
                'url' => "promotion/mansonglist?status=4",
                'menu_name' => "已结束",
                "active" => $status == 4 ? 1 : 0
            )
        );
        $this->assign('child_menu_list', $child_menu_list);
        $this->admin_user_record('查看满减送列表','','');
        return view($this->style . "Promotion/mansongList");
    }

    /**
     * 添加满减送活动
     *
     * @return \think\response\View
     */
    public function addMansong()
    {
        $mansong = new PromotionService();
        if (request()->isAjax()) {
            $mansong_name   = request()->post('mansong_name', '');
            $start_time     = request()->post('start_time', '');
            $end_time       = request()->post('end_time', '');
            $shop_id        = $this->instance_id;
            $type           = request()->post('type', '');
            $range_type     = request()->post('range_type', '');
            $rule           = request()->post('rule', '');
            $goods_sku_arr  = request()->post('goods_sku_arr', '');
            $is_neigou      = request()->post('is_neigou', '');
            $res            = $mansong->addPromotionMansong($mansong_name, $start_time, $end_time, $shop_id, '', $type, $range_type, $rule, $goods_sku_arr , $is_neigou);
            $this->admin_user_record('添加满减送活动',$res,'');

            return AjaxReturn($res);
        } else {
            // 查找一级商品分类
            $goodsCategory    = new GoodsCategory();
            $oneGoodsCategory = $goodsCategory->getGoodsCategoryListByParentId(0);
            $this->assign("oneGoodsCategory", $oneGoodsCategory);

            return view($this->style . "Promotion/addMansong");
        }
    }

    public function addMansongGoods(){

        # 需添加的商品编码
        $category_id ='52,43,44,45,46,47,48,49,51,50,34';

        $condition= ['category_id_1' =>['in', $category_id]];

        $goods = \think\Db::name('ns_goods')->where($condition)->select();

        foreach ( $goods as $val ){
            $sku = \think\Db::name('ns_goods_sku')->where(['goods_id' => $val['goods_id']])->select();
            foreach($sku as $v){
                $data= [
                    'mansong_id' => 81,
                    'goods_id'      => $v['goods_id'],
                    'sku_id'        => $v['sku_id'],
                    'goods_name'    => $val['goods_name'],
                    'sku_name'      => $v['sku_name'],
                    'goods_picture' => $val['picture'],
                    'start_time'    => '1562763952',
                    'end_time'      => '1567266945',
                    'status'        => 1,
                    'sku_num'       => $v['sku_num'],
                ];
                \think\Db::name('ns_promotion_mansong_goods')->insert($data);
            }
        }
    }

    /**
     * 修改 满减送活动
     */
    public function updateMansong()
    {
        $mansong = new PromotionService();
        if (request()->isAjax()) {
            $mansong_id     = request()->post('mansong_id', '');
            $mansong_name   = request()->post('mansong_name', '');
            $start_time     = request()->post('start_time', '');
            $end_time       = request()->post('end_time', '');
            $shop_id        = $this->instance_id;
            $type           = request()->post('type', '');
            $range_type     = request()->post('range_type', '');
            $rule           = request()->post('rule', '');
            $goods_sku_arr  = request()->post('goods_sku_arr', '');
            $is_neigou      = request()->post('is_neigou', '');
            $res            = $mansong->updatePromotionMansong($mansong_id, $mansong_name, $start_time, $end_time, $shop_id, '', $type, $range_type, $rule, $goods_sku_arr , $is_neigou);
            $this->admin_user_record('修改满减送活动',$res,'');

            return AjaxReturn($res);
        } else {
            $mansong_id = request()->get('mansong_id', '');
            if (!is_numeric($mansong_id)) {
                $this->error('未获取到信息');
            }
            $info             = $mansong->getPromotionMansongDetail($mansong_id);
            $condition        = array(
                'shop_id' => $this->instance_id
            );
            foreach($info['goods_list'] as $kk => $vv){
                $mansong_goods                 = new NsPromotionMansongGoodsModel();
                $info['goods_list'][$kk]['select_sku_arr'] = $mansong_goods->getGoodsViewQueryField([
                    'npmg.goods_id' => $vv['goods_id'],'npmg.mansong_id' => $vv['mansong_id']
                ], 'npmg.sku_id,npmg.sku_name,npmg.sku_picture,ngs.price,ngs.stock,ngs.material_code', '');
            }

            foreach($info['rule'] as $k0=>$v0){
                $gift_arr = \think\Db::name('ns_promotion_gift')->select();
                if(empty($gift_arr)){continue;}
                foreach($gift_arr as $k2=>$v2){
                    $rule_info = \think\Db::name('ns_promotion_mansong_rule')->where(['rule_id' => $v0['rule_id']])->find();
                    if(empty($rule_info)) continue;
                    $gift_arr[$k2]['is_check'] = 0;
                    $gift_arr[$k2]['n_num']    = '';
                    if(strpos($rule_info['gift_id'], ',') !== false){
                        $ids        = explode(',', $rule_info['gift_id']);
                        foreach($ids as $key=>$vo) {
                            $_ids       = explode(':', $vo);
                            if($_ids[0] == $v2['gift_id']) {
                                $gift_arr[$k2]['is_check'] = 1;
                                $gift_arr[$k2]['n_num'] = $_ids[1];
                            }
                        }
                    }else{
                        $_ids       = explode(':',  $rule_info['gift_id']);
                        if($_ids[0] == $v2['gift_id']) {
                            $gift_arr[$k2]['is_check'] = 1;
                            $gift_arr[$k2]['n_num']    = $_ids[1];
                        }
                    }
                }
                $info['rule'][$k0]['gift_arr'] = $gift_arr;
            }

            $coupon_type_list = $mansong->getCouponTypeList(1, 0, $condition);
            $gift_list        = $mansong->getPromotionGiftList(1, 0, $condition);
            // 查找一级商品分类
            $goodsCategory    = new GoodsCategory();
            $oneGoodsCategory = $goodsCategory->getGoodsCategoryListByParentId(0);
            $this->assign("oneGoodsCategory", $oneGoodsCategory);
            $this->assign('coupon_type_list', $coupon_type_list);
            $this->assign('gift_list', $gift_list);
            $this->assign('mansong_info', $info);
            return view($this->style . "Promotion/updateMansong");
        }
    }

    /**
     * 获取限时折扣；列表
     */
    public function getDiscountList()
    {
        if (request()->isAjax()) {
            $page_index  = request()->post("page_index", 1);
            $page_size   = request()->post('page_size', PAGESIZE);
            $search_text = request()->post('search_text', '');
            $status      = request()->post('status', '');
            $discount    = new PromotionService();

            $condition = array(
                'shop_id' => $this->instance_id,
                'discount_name' => array(
                    'like',
                    '%' . $search_text . '%'
                )
            );
            if ($status !== '-1') {
                $condition['status'] = $status;
                $list                = $discount->getPromotionDiscountList($page_index, $page_size, $condition);
            } else {
                $list = $discount->getPromotionDiscountList($page_index, $page_size, $condition);
            }

            return $list;
        }

        $status = request()->get('status', -1);
        $this->assign("status", $status);
        $child_menu_list = array(
            array(
                'url' => "promotion/getdiscountList",
                'menu_name' => "全部",
                "active" => $status == '-1' ? 1 : 0
            ),
            array(
                'url' => "promotion/getdiscountList?status=0",
                'menu_name' => "未发布",
                "active" => $status == 0 ? 1 : 0
            ),
            array(
                'url' => "promotion/getdiscountList?status=1",
                'menu_name' => "进行中",
                "active" => $status == 1 ? 1 : 0
            ),
            array(
                'url' => "promotion/getdiscountList?status=3",
                'menu_name' => "已关闭",
                "active" => $status == 3 ? 1 : 0
            ),
            array(
                'url' => "promotion/getdiscountList?status=4",
                'menu_name' => "已结束",
                "active" => $status == 4 ? 1 : 0
            )
        );
        $this->assign('child_menu_list', $child_menu_list);
        $this->admin_user_record('查看限时折扣列表','','');

        return view($this->style . "Promotion/getDiscountList");
    }

    /**
     * 添加限时折扣
     */
    public function addDiscount()
    {
        if (request()->isAjax()) {
            $discount       = new PromotionService();
            $discount_name  = request()->post('discount_name', '');
            $start_time     = request()->post('start_time', '');
            $end_time       = request()->post('end_time', '');
            $remark         = '';
            $goods_sku_arr  = request()->post('goods_sku_arr', '');
            $retval         = $discount->addPromotiondiscount($discount_name, $start_time, $end_time, $remark, $goods_sku_arr);
            $this->admin_user_record('添加限时折扣',$retval,'');

            return AjaxReturn($retval);
        }
        // 查找一级商品分类
        $goodsCategory    = new GoodsCategory();
        $oneGoodsCategory = $goodsCategory->getGoodsCategoryListByParentId(0);
        $this->assign("oneGoodsCategory", $oneGoodsCategory);

        return view($this->style . "Promotion/addDiscount");
    }

    /**
     * 修改限时折扣
     */
    public function updateDiscount()
    {
        if (request()->isAjax()) {
            $discount       = new PromotionService();
            $discount_id    = request()->post('discount_id', '');
            $discount_name  = request()->post('discount_name', '');
            $start_time     = request()->post('start_time', '');
            $end_time       = request()->post('end_time', '');
            $remark         = '';
            $goods_sku_arr  = request()->post('goods_sku_arr', '');
            $retval         = $discount->updatePromotionDiscount($discount_id, $discount_name, $start_time, $end_time, $remark, $goods_sku_arr);
            $this->admin_user_record('修改限时折扣',$retval,'');

            return AjaxReturn($retval);
        }
        $info           = $this->getDiscountDetail();
        $discount_model = new NsPromotionDiscountGoodsViewModel();
        if (!empty($info['goods_list'])) {
            foreach ($info['goods_list'] as $k => $v) {
                $goods_info = \think\Db::name('ns_goods')->where(['goods_id' => $v['goods_id']])->find();
                $category   = \think\Db::name('ns_goods_category')->where(['category_id' => $goods_info['category_id_1']])->find();
                if($goods_info['goods_type'] == 0){
                    $info['goods_list'][$k]['goods_type'] = '虚拟商品';
                }elseif($goods_info['goods_type'] == 1){
                    $info['goods_list'][$k]['goods_type'] = '实物商品';
                }elseif($goods_info['goods_type'] == 2) {
                    $info['goods_list'][$k]['goods_type'] = '实物礼品';
                }else{
                    $info['goods_list'][$k]['goods_type'] = '会员';
                }
                $info['goods_list'][$k]['category_id_name'] = $category['category_name'];
                $goods_id_array[]                           = $v['goods_id'];
                $info['goods_list'][$k]['select_sku_arr'] = $discount_model->getGoodsViewQueryField([
                    'npdg.goods_id' => $v['goods_id'],'npdg.discount_id' => $v['discount_id']
                ], 'npdg.sku_id,npdg.sku_name,npdg.sku_picture,npdg.discount,ngs.price,ngs.stock,ngs.material_code', '');
                $info['goods_list'][$k]['sku_list'] = $info['goods_list'][$k]['select_sku_arr'];
            }
        }
        $info['goods_id_array'] = $goods_id_array;
        // 查找一级商品分类
        $goodsCategory    = new GoodsCategory();
        $oneGoodsCategory = $goodsCategory->getGoodsCategoryListByParentId(0);
        $this->assign("oneGoodsCategory", $oneGoodsCategory);
        $this->assign("info", $info);
        return view($this->style . "Promotion/updateDiscount");
    }

    /**
     * 获取限时折扣详情
     */
    public function getDiscountDetail()
    {
        $discount_id = request()->get('discount_id', '');
        if (!is_numeric($discount_id)) {
            $this->error("没有获取到折扣信息");
        }
        $discount = new PromotionService();
        $detail   = $discount->getPromotionDiscountDetail($discount_id);
        return $detail;
    }

    /**
     * 获取满减送详情
     */
    public function getMansongDetail()
    {
        $mansong_id = request()->get('mansong_id', '');
        if (!is_numeric($mansong_id)) {
            $this->error("没有获取到满减送信息");
        }
        $mansong = new PromotionService();
        $detail  = $mansong->getPromotionMansongDetail($mansong_id);

        return $detail;
    }

    /**
     * 删除限时折扣
     */
    public function delDiscount()
    {
        $discount_id = request()->post('discount_id', '');
        if (empty($discount_id)) {
            $this->error("没有获取到折扣信息");
        }
        $discount = new PromotionService();
        $res      = $discount->delPromotionDiscount($discount_id);
        $this->admin_user_record('删除限时折扣',$discount_id,'');

        return AjaxReturn($res);
    }

    /**
     * 关闭正在进行的限时折扣
     */
    public function closeDiscount()
    {
        $discount_id = request()->post('discount_id', '');
        if (!is_numeric($discount_id)) {
            $this->error("没有获取到折扣信息");
        }
        $discount = new PromotionService();
        $res      = $discount->closePromotionDiscount($discount_id);
        $this->admin_user_record('关闭正在进行的限时折扣',$discount_id,'');

        return AjaxReturn($res);
    }

    /**
     * 删除满减送活动
     *
     * @return unknown[]
     */
    public function delMansong()
    {
        $mansong_id = request()->post('mansong_id', '');
        if (empty($mansong_id)) {
            $this->error("没有获取到满减送信息");
        }
        $mansong = new PromotionService();
        $res     = $mansong->delPromotionMansong($mansong_id);
        $this->admin_user_record('删除满减送活动',$mansong_id,'');

        return AjaxReturn($res);
    }

    /**
     * 关闭满减送活动
     *
     * @return unknown[]
     */
    public function closeMansong()
    {
        $mansong_id = request()->post('mansong_id', '');
        if (!is_numeric($mansong_id)) {
            $this->error("没有获取到满减送信息");
        }
        $mansong = new PromotionService();
        $res     = $mansong->closePromotionMansong($mansong_id);
        $this->admin_user_record('关闭满减送活动',$mansong_id,'');

        return AjaxReturn($res);
    }

    /**
     * 满额包邮
     */
    public function fullShipping()
    {
        $full = new PromotionService();
        if (request()->isAjax()) {
            $is_open                   = request()->post('is_open', '');
            $full_mail_money           = request()->post('full_mail_money', '');
            $no_mail_province_id_array = request()->post('no_mail_province_id_array', '');
            $no_mail_city_id_array     = request()->post("no_mail_city_id_array", '');
            $res                       = $full->updatePromotionFullMail(0, $is_open, $full_mail_money, $no_mail_province_id_array, $no_mail_city_id_array);
            return AjaxReturn($res);
        } else {
            $info = $full->getPromotionFullMail($this->instance_id);
            $this->assign("info", $info);
            $existing_address_list['province_id_array'] = explode(',', $info['no_mail_province_id_array']);
            $existing_address_list['city_id_array']     = explode(',', $info['no_mail_city_id_array']);
            $address                                    = new Address();
            // 目前只支持省市，不支持区县，在页面上不会体现 2017年9月14日 19:18:08 王永杰
            $address_list = $address->getAreaTree($existing_address_list);
            $this->assign("address_list", $address_list);
            $no_mail_province_id_array = array();
            if (count($existing_address_list['province_id_array']) > 0) {
                foreach ($existing_address_list['province_id_array'] as $v) {
                    if (!empty($v)) {
                        $no_mail_province_id_array[] = $address->getProvinceName($v);
                    }
                }
            }
            $no_mail_province = "";
            if (count($no_mail_province_id_array) > 0) {
                $no_mail_province = implode(',', $no_mail_province_id_array);
            }
            $this->assign("no_mail_province", $no_mail_province);
            $this->admin_user_record('查看满额包邮','','');

            return view($this->style . "Promotion/fullShipping");
        }
    }

    /**
     * 单店基础版积分奖励
     */
    public function integral()
    {
        $rewardRule = new PromoteRewardRule();
        if (request()->isAjax()) {
            $shop_id                  = $this->instance_id;
            $sign_point               = request()->post('sign_point', 0);
            $share_point              = request()->post('share_point', 0);
            $reg_member_self_point    = request()->post('reg_member_self_point', 0);
            $reg_member_one_point     = 0;
            $reg_member_two_point     = 0;
            $reg_member_three_point   = 0;
            $reg_promoter_self_point  = 0;
            $reg_promoter_one_point   = 0;
            $reg_promoter_two_point   = 0;
            $reg_promoter_three_point = 0;
            $reg_partner_self_point   = 0;
            $reg_partner_one_point    = 0;
            $reg_partner_two_point    = 0;
            $reg_partner_three_point  = 0;
            $click_point              = request()->post("click_point", 0);
            $comment_point            = request()->post("comment_point", 0);

            $reg_coupon     = request()->post("reg_coupon", 0);
            $click_coupon   = request()->post("click_coupon", 0);
            $comment_coupon = request()->post("comment_coupon", 0);
            $sign_coupon    = request()->post("sign_coupon", 0);
            $share_coupon   = request()->post("share_coupon", 0);

            $res = $rewardRule->setPointRewardRule($shop_id, $sign_point, $share_point, $reg_member_self_point, $reg_member_one_point, $reg_member_two_point, $reg_member_three_point, $reg_promoter_self_point, $reg_promoter_one_point, $reg_promoter_two_point, $reg_promoter_three_point, $reg_partner_self_point, $reg_partner_one_point, $reg_partner_two_point, $reg_partner_three_point, $click_point, $comment_point, $reg_coupon, $click_coupon, $comment_coupon, $sign_coupon, $share_coupon);
            return AjaxReturn($res);
        }
        $res            = $rewardRule->getRewardRuleDetail($this->instance_id);
        $Config         = new Config();
        $integralConfig = $Config->getIntegralConfig($this->instance_id);
        $coupon         = new PromotionService();
        $condition      = array(
            'shop_id' => $this->instance_id,
            'start_time' => array(
                'ELT',
                time()
            ),
            'end_time' => array(
                'EGT',
                time()
            )
        );
        $couponlist     = $coupon->getCouponTypeList($page_index, $page_size, $condition, 'start_time desc');
        $this->assign("res", $res);
        $this->assign("integralConfig", $integralConfig);
        $this->assign("couponlist", $couponlist['data']);
        return view($this->style . "Promotion/integral");
    }

    /**
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function setIntegralAjax()
    {
        $register       = request()->post('register', 0);
        $sign           = request()->post('sign', 0);
        $share          = request()->post('share', 0);
        $reg_coupon     = request()->post('reg_coupon', 0);
        $click_coupon   = request()->post('click_coupon', 0);
        $comment_coupon = request()->post('comment_coupon', 0);
        $sign_coupon    = request()->post('sign_coupon', 0);
        $share_coupon   = request()->post('share_coupon', 0);
        $Config         = new Config();
        $retval         = $Config->SetIntegralConfig($this->instance_id, $register, $sign, $share, $reg_coupon, $click_coupon, $comment_coupon, $sign_coupon, $share_coupon);
        return AjaxReturn($retval);
    }

    /**
     * 组合套餐列表
     * 创建时间：2017年12月4日 17:55:38 王永杰
     */
    public function comboPackagePromotionList()
    {
        if (request()->isAjax()) {
            $promotionService                = new PromotionService();
            $page_index                      = request()->post("page_index", 1);
            $page_size                       = request()->post("page_size", PAGESIZE);
            $combo_package_name              = request()->post("search_text", "");
            $condition["combo_package_name"] = array(
                "like",
                "%$combo_package_name%"
            );
            $list                            = $promotionService->getComboPackageList($page_index, $page_size, $condition);
            return $list;
        }
        return view($this->style . "Promotion/comboPackagePromotionList");
    }

    /**
     * 组合套餐编辑
     * 创建时间：2017年12月4日 18:05:19 王永杰
     */
    public function comboPackagePromotionEdit()
    {
        $id               = request()->get("id", 0);
        $promotionService = new PromotionService();
        $info             = $promotionService->getComboPackageDetail($id);
        $this->assign("info", $info);
        $this->assign("id", $id);
        return view($this->style . "Promotion/comboPackagePromotionEdit");
    }

    /**
     * 添加或编辑组合套餐
     */
    public function addOrEditComboPackage()
    {
        $promotionService    = new PromotionService();
        $id                  = request()->post("id", 0);
        $combo_package_name  = request()->post("combo_package_name", "");
        $combo_package_price = request()->post("combo_package_price", "");
        $goods_id_array      = request()->post("goods_id_array", "");
        $is_shelves          = request()->post("is_shelves", 1);
        $original_price      = request()->post("original_price", "");
        $save_the_price      = request()->post("save_the_price", "");

        $res = $promotionService->addOrEditComboPackage($id, $combo_package_name, $combo_package_price, $goods_id_array, $is_shelves, $this->instance_id, $original_price, $save_the_price);
        return AjaxReturn($res);
    }

    /**
     * 删除组合套餐
     */
    public function deleteComboPackage()
    {
        $promotionService = new PromotionService();
        $ids              = request()->post("ids", "");
        $res              = $promotionService->deleteComboPackage($ids);
        return AjaxReturn($res);
    }

    /**
     * 营销活动列表
     */
    public function promotionGamesList()
    {
        if (request()->isAjax()) {

            $promotionService = new PromotionService();

            $page_index  = request()->post("page_index", 1);
            $page_size   = request()->post("page_size", PAGESIZE);
            $search_text = request()->post("search_text", '');

            $condition = array();
            if (!empty($search_text)) {
                $condition['name'] = array(
                    'like',
                    '%' . $search_text . '%'
                );
            }
            $promotion_games_list = $promotionService->getPromotionGamesList($page_index, $page_size, $condition);
            return $promotion_games_list;
        }
        return view($this->style . "Games/promotionGamesList");
    }

    /**
     * 营销活动类型列表
     *
     * @return \think\response\View
     */
    public function promotionGameTypeList()
    {
        $promotionService = new PromotionService();
        $game_type_list   = $promotionService->getPromotionGameTypeList(1, 0, '', 'is_complete desc');
        $this->assign('game_type_list', $game_type_list['data']);
        return view($this->style . "Games/promotionGameTypeList");
    }

    /**
     * 添加营销活动
     */
    public function addPromotionGame()
    {
        $this->promotionGameInit();
        $this->assign('game_id', 0);
        return view($this->style . "Games/addPromotionGame");
    }

    /**
     * 添加修改互动游戏
     */
    public function addUpdatePromotionGame()
    {
        if (request()->isAjax()) {

            $promotionService = new PromotionService();
            $game_id          = request()->post('game_id', '');
            $name             = request()->post('game_name', '');
            $type             = request()->post('game_type', '');
            $member_level     = request()->post('member_level', '');
            $points           = request()->post('points', '');
            $start_time       = request()->post('start_time', '');
            $end_time         = request()->post('end_time', '');
            $remark           = request()->post('remark', '');
            $winning_rate     = request()->post('winning_rate', '');
            $no_winning_des   = request()->post('no_winning_des', '');
            $rule_json        = request()->post('rule_array', '');
            $activity_images  = request()->post('activity_images', ''); // 活动图片

            $res = $promotionService->addUpdatePromotionGame($game_id, $this->instance_id, $name, $type, $member_level, $points, $start_time, $end_time, $remark, $winning_rate, $no_winning_des, $rule_json, $activity_images);
            return AjaxReturn($res);
        }
    }

    /**
     * 修改互动游戏
     */
    public function updatePromotionGame()
    {
        $this->promotionGameInit();

        $game_id = request()->get('game_id', '');
        $this->assign('game_id', $game_id);
        $promotionService = new PromotionService();
        $game_info        = $promotionService->getPromotionGameDetail($game_id);
        $this->assign('game_info', $game_info);
        return view($this->style . "Games/updatePromotionGame");
    }

    /**
     * 修改添加互动游戏页面加载项
     */
    public function promotionGameInit()
    {
        $promotionService = new PromotionService();
        // 活动类型
        $game_type      = request()->get('game_type', '');
        $game_type_info = $promotionService->getPromotionGameTypeInfo($game_type);
        $this->assign('game_type', $game_type);
        $this->assign('game_type_info', $game_type_info);

        // 会员等级
        $member_service    = new Member();
        $member_level_list = $member_service->getMemberLevelList();
        $this->assign('level_list', $member_level_list['data']);

        // 优惠劵列表
        $coupon_condition = array(
            'start_time' => array(
                'lt',
                time()
            ),
            'end_time' => array(
                'gt',
                time()
            )
        );
        $coupon_type_list = $promotionService->getCouponTypeInfoList(1, 0, $coupon_condition);
        $this->assign('coupon_type_list', $coupon_type_list['data']);

        // 赠品列表
        $gift_condition = array(
            'start_time' => array(
                'lt',
                time()
            ),
            'end_time' => array(
                'gt',
                time()
            )
        );
        $gift_list      = $promotionService->getPromotionGiftList(1, 0, $gift_condition);
        $this->assign('gift_list', $gift_list['data']);
    }

    /**
     * 删除互动游戏
     */
    public function delPromotionGame()
    {
        if (request()->isAjax()) {

            $promotionService = new PromotionService();
            $game_id          = request()->post('game_id', '');
            $res              = $promotionService->delPromotionGame($game_id);
            return AjaxReturn($res);
        }
    }

    /**
     * 关闭互动游戏
     */
    public function closePromotionGame()
    {
        if (request()->isAjax()) {
            $promotionService = new PromotionService();
            $game_id          = request()->post('game_id', '');
            $res              = $promotionService->closePromotionGame($game_id);
            return AjaxReturn($res);
        }
    }

    /**
     * 营销游戏奖项列表
     * 创建时间：2018年2月1日09:59:57 王永杰
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function promotionGamesAwardList()
    {
        $game_id = request()->get("game_id", "");
        if (empty($game_id)) {
            $this->error("缺少参数game_id");
        }
        $promotionService = new PromotionService();
        $game_detail      = $promotionService->getPromotionGameDetail($game_id);
        $this->assign("game_detail", $game_detail);
        return view($this->style . "Games/promotionGamesAwardList");
    }

    /**
     * 获奖记录
     * 创建时间：2018年2月1日11:38:06
     *
     * @return Ambigous <\data\service\Ambigous, \data\model\unknown, \data\model\multitype:unknown, multitype:unknown number >|Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function promotionGamesAccessRecords()
    {
        if (request()->isAjax()) {
            $page_index  = request()->post("page_index", 1);
            $page_size   = request()->post("page_size", PAGESIZE);
            $search_text = request()->post("search_text", "");
            $is_winning  = request()->post("is_winning", "");
            $game_id     = request()->post("game_id", "");
            $condition   = array();
            if (!empty($search_text)) {
                $condition['np_pgwr.nick_name'] = [
                    'like',
                    "%" . $search_text . "%"
                ];
            }
            if ($is_winning !== "") {

                $condition['np_pgwr.is_winning'] = $is_winning;
            }
            if ($game_id !== "") {

                $condition['np_pgwr.game_id'] = $game_id;
            }
            $promotionService = new PromotionService();
            $res              = $promotionService->getUserPromotionGamesWinningRecords($page_index, $page_size, $condition);
            return $res;
        } else {

            $game_id = request()->get("game_id", "");
            if (empty($game_id)) {
                $this->error("缺少参数game_id");
            }
            $this->assign("game_id", $game_id);
        }

        return view($this->style . "Games/promotionGamesAccessRecords");
    }


    /**
     * @throws \think\Exception
     * 判读优惠券是否在有效期  cronTab  五分钟一次
     */
    public function cronTabCouponDays()
    {
        $coupon_lists = \think\Db::name('ns_coupon')->where(['state' => 1])->select();

        foreach ($coupon_lists as $v) {
            $days = floor((time() - $v['fetch_time']) / 86400);
            if ($days > $v['get_after_days']) {
                $res['state'] = 3;
                \think\Db::name('ns_coupon')->where(['coupon_id' => $v['coupon_id']])->update($res);
            }
        }
    }

    /**
     * 添加内购活动
     *
     * @return \think\response\View
     */
    public function addNeiGou()
    {
//        $condition = [
//            "is_neigou" => 1,
//        ];
//        $condition['status'] = array(
//            'in',
//            [0,1]
//        );
//
//        $info = \think\Db::name('ns_promotion_mansong')->where($condition)->find();
//
//        if($info) $this->error("存在正在进行或未开始的内购活动");

        $mansong = new PromotionService();
        if (request()->isAjax()) {
            $mansong_name   = request()->post('mansong_name', '');
            $start_time     = request()->post('start_time', '');
            $end_time       = request()->post('end_time', '');
            $shop_id        = $this->instance_id;
            $type           = request()->post('type', '');
            $range_type     = request()->post('range_type', '');
            $rule           = request()->post('rule', '');
            $goods_sku_array  = request()->post('goods_sku_array', '');
            $is_neigou      = request()->post('is_neigou', '');
            $res            = $mansong->addPromotionNeiGou($mansong_name, $start_time, $end_time, $shop_id, '', $type, $range_type, $rule, $goods_sku_array , $is_neigou);
            $this->admin_user_record('添加内购活动',$res,'');

            return AjaxReturn($res);
        } else {
            // 查找一级商品分类
            $goodsCategory    = new GoodsCategory();
            $oneGoodsCategory = $goodsCategory->getGoodsCategoryListByParentId(0);
            $this->assign("oneGoodsCategory", $oneGoodsCategory);

            return view($this->style . "Promotion/addNeiGou");
        }
    }

    /**
     * 修改 内购活动
     */
    public function updateNeiGou()
    {
        $mansong = new PromotionService();
        if (request()->isAjax()) {
            $mansong_id     = request()->post('mansong_id', '');
            $mansong_name   = request()->post('mansong_name', '');
            $start_time     = request()->post('start_time', '');
            $end_time       = request()->post('end_time', '');
            $shop_id        = $this->instance_id;
            $type           = request()->post('type', '');
            $range_type     = request()->post('range_type', '');
            $rule           = request()->post('rule', '');
            $goods_sku_arr  = request()->post('goods_sku_arr', '');
            $is_neigou      = request()->post('is_neigou', '');
            $res            = $mansong->updatePromotionNeiGou($mansong_id, $mansong_name, $start_time, $end_time, $shop_id, '', $type, $range_type, $rule, $goods_sku_arr , $is_neigou);
            $this->admin_user_record('修改内购活动',$res,'');

            return AjaxReturn($res);
        } else {
            $mansong_id = request()->get('mansong_id', '');
            if (!is_numeric($mansong_id)) {
                $this->error('未获取到信息');
            }
            $info           = $mansong->getPromotionMansongDetail($mansong_id);
            foreach($info['goods_list'] as $key=>$v){
                $goods_info = \think\Db::name('ns_goods')->where(['goods_id' => $v['goods_id']])->find();
                $category   = \think\Db::name('ns_goods_category')->where(['category_id' => $goods_info['category_id_1']])->find();
                if($goods_info['goods_type'] == 0){
                    $info['goods_list'][$key]['goods_type'] = '虚拟商品';
                }elseif($goods_info['goods_type'] == 1){
                    $info['goods_list'][$key]['goods_type'] = '实物商品';
                }elseif($goods_info['goods_type'] == 2) {
                    $info['goods_list'][$key]['goods_type'] = '实物礼品';
                }else{
                    $info['goods_list'][$key]['goods_type'] = '会员';
                }
                $info['goods_list'][$key]['category_id_name'] = $category['category_name'];
                $mansong_goods                                = new NsPromotionNeigouGoodsModel();
                $info['goods_list'][$key]['select_sku_arr'] = $mansong_goods->getGoodsViewQueryField([
                    'npng.goods_id' => $v['goods_id'],'npng.discount_id' => $v['discount_id']
                ], 'npng.sku_id,npng.sku_name,npng.sku_picture,npng.n_price,npng.n_discount,npng.sku_num,npng.use_num,ngs.price,ngs.stock,ngs.material_code', '');
            }

            foreach($info['rule'] as $k0=>$v0){
                $gift_arr = \think\Db::name('ns_promotion_gift')->select();
                if(empty($gift_arr)){continue;}
                foreach($gift_arr as $k2=>$v2){
                    $rule_info = \think\Db::name('ns_promotion_mansong_rule')->where(['rule_id' => $v0['rule_id']])->find();
                    if(empty($rule_info)) continue;
                    $gift_arr[$k2]['is_check'] = 0;
                    $gift_arr[$k2]['n_num']    = '';
                    if(strpos($rule_info['gift_id'], ',') !== false){
                        $ids        = explode(',', $rule_info['gift_id']);
                        foreach($ids as $key=>$vo) {
                            $_ids       = explode(':', $vo);
                            if($_ids[0] == $v2['gift_id']) {
                                $gift_arr[$k2]['is_check'] = 1;
                                $gift_arr[$k2]['n_num']    = $_ids[1];
                            }
                        }
                    }else{
                        $_ids       = explode(':',  $rule_info['gift_id']);
                        if($_ids[0] == $v2['gift_id']) {
                            $gift_arr[$k2]['is_check'] = 1;
                            $gift_arr[$k2]['n_num']    = $_ids[1];
                        }
                    }
                }
                $info['rule'][$k0]['gift_arr'] = $gift_arr;
            }

            $condition        = array(
                'shop_id' => $this->instance_id
            );
            $coupon_type_list = $mansong->getCouponTypeList(1, 0, $condition);
            $gift_list        = $mansong->getPromotionGiftList(1, 0, $condition);

            //满送赠品
            $mansong_list = \think\Db::name('ns_promotion_mansong_rule')->where(['mansong_id' => $mansong_id])->select();

            // 查找一级商品分类
            $goodsCategory    = new GoodsCategory();
            $oneGoodsCategory = $goodsCategory->getGoodsCategoryListByParentId(0);
            $this->assign("oneGoodsCategory", $oneGoodsCategory);
            $this->assign('coupon_type_list', $coupon_type_list);
            $this->assign('mansong_list', $mansong_list);
            $this->assign('gift_list', $gift_list);
            $this->assign('mansong_info', $info);
            return view($this->style . "Promotion/updateNeiGou");
        }
    }

    /**
     * @return bool
     * 验证
     */
    public function checkNeiGou(){
        $condition = [
            "is_neigou" => 1,
        ];
        $condition['status'] = array(
            'in',
            [0,1]
        );

        $info = \think\Db::name('ns_promotion_mansong')->where($condition)->find();

        if($info){
            return 2;
        } else {
            return 1;
        }
    }


    /**
     * 活动商品数据excel导出
     */
    public function promotionExportExcel()
    {
        $discount_id  = request()->get('mansong_id', '');
        $arr          = $this->getGoodsData($discount_id);
        $mansong_info = \think\Db::name('ns_promotion_mansong')->where(['mansong_id' => $discount_id])->find();
        $xlsName      = $mansong_info['mansong_name'] . "商品数据列表";
        if($mansong_info['is_neigou'] == 1){
            $xlsCell = array(
                array(
                    'goods_id',
                    '商品id'
                ),
                array(
                    'material_code',
                    'shopal物料编码'
                ),
                array(
                    'goods_name',
                    '商品名称'
                ),
                array(
                    'category_name',
                    '商品分类'
                ),
                array(
                    'stock',
                    '库存'
                ),
                array(
                    'price',
                    '原价'
                ),
                array(
                    'n_price',
                    '内购价格'
                )
            );
        }else{
            $xlsCell = array(
                array(
                    'goods_id',
                    '商品id'
                ),
                array(
                    'material_code',
                    'shopal物料编码'
                ),
                array(
                    'goods_name',
                    '商品名称'
                ),
                array(
                    'category_name',
                    '商品分类'
                ),
                array(
                    'stock',
                    '库存'
                ),
                array(
                    'price',
                    '价格'
                )
            );
        }
        $this->admin_user_record('活动商品数据excel导出',$discount_id,'');

        dataExcel($xlsName, $xlsCell, $arr);
        exit;
    }


    public function getGoodsData($discount_id){
        # 内购活动商品导出
        $lists       = \think\Db::name('ns_promotion_neigou_goods')->where(['discount_id' => $discount_id])->select();
        foreach($lists as $key=>$v){
            #商品名称 商品分类 商品shopal编码 商品内购价格 原价格 库存
            $goods_info                   = \think\Db::name('ns_goods')->where(['goods_id' => $v['goods_id']])->find();
            $category_info                = \think\Db::name('ns_goods_category')->where(['category_id' => $goods_info['category_id']])->find();
            $lists[$key]['goods_name']    = $goods_info['goods_name'];
            $lists[$key]['material_code'] = $goods_info['material_code'];
            $lists[$key]['price']         = $goods_info['price'];
            $lists[$key]['stock']         = $goods_info['stock'];
            $lists[$key]['category_name'] = $category_info['category_name'].'('.$category_info['short_name'].')';
            $lists[$key]['stock']         = $goods_info['stock'];
        }
        return $lists;
    }



}