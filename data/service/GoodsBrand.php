<?php
/**
 * GoodsBrand.php
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
 * 商品品牌服务层
 */
use data\service\BaseService as BaseService;
use data\model\NsGoodsBrandModel as NsGoodsBrand;
use data\model\AlbumPictureModel as AlbumPictureModel;
use data\api\IGoodsBrand as IGoodsBrand;
use think\Cache;
use data\service\Goods as GoodsService;
class GoodsBrand extends BaseService implements IGoodsBrand
{

    private $goods_brand;

    function __construct()
    {
        parent::__construct();
        $this->goods_brand = new NsGoodsBrand();
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IGoodsBrand::getGoodsBrandList()
     */
    public function getGoodsBrandList($page_index = 1, $page_size = 0, $condition = '', $order = 'sort asc', $field = '*')
    {
        $data = array($page_index, $page_size, $condition, $order, $field);
        $data = json_encode($data);
        
        $cache = Cache::tag("niu_goods_brand")->get("getGoodsBrandList".$data);
        if(empty($cache))
        {
            $list = $this->goods_brand->pageQuery($page_index, $page_size, $condition, $order, $field);
            Cache::tag("niu_goods_brand")->set("getGoodsBrandList".$data, $list);
            return $list;
        }else{
            return $cache;
        }
    
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IGoodsBrand::addOrUpdateGoodsBrand()
     */
    public function addOrUpdateGoodsBrand($brand_id, $shop_id = 0, $brand_name, $brand_initial, $brand_class, $brand_pic, $brand_recommend, $sort, $brand_category_name = '', $category_id_array = '', $brand_ads, $brand_other_ads, $category_name, $category_id_1, $category_id_2, $category_id_3,$brand_video_address)
    {
        Cache::tag("niu_goods_brand")->clear();
        $data = array(
            'shop_id' => $shop_id,
            'brand_name' => $brand_name,
            'brand_initial' => $brand_initial,
            'brand_pic' => $brand_pic,
            'brand_recommend' => $brand_recommend,
            'brand_ads' => $brand_ads,
            'brand_other_ads' => $brand_other_ads,
            'category_name' => $category_name,
            'category_id_1' => $category_id_1,
            'category_id_2' => $category_id_2,
            'category_id_3' => $category_id_3,
            'brand_video_address' => $brand_video_address
        );
        if ($brand_id == "") {
            $res = $this->goods_brand->save($data);
            $data['brand_id'] = $this->goods_brand->brand_id;
            hook("goodsBrandSaveSuccess", $data);
            return $this->goods_brand->brand_id;
        } else {
            $res = $this->goods_brand->save($data, [
                "brand_id" => $brand_id
            ]);
            $data['brand_id'] = $brand_id;
            hook("goodsBrandSaveSuccess", $data);
            return $res;
        }
        
        // TODO Auto-generated method stub
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IGoodsBrand::ModifyGoodsBrandSort()
     */
    public function ModifyGoodsBrandSort($brand_id, $sort)
    {
        Cache::tag("niu_goods_brand")->clear();
        $data = array()

        ;
        $res = $this->goods_brand->save($data, [
            'brand_id' => $brand_id
        ]);
        return $res;
        // TODO Auto-generated method stub
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IGoodsBrand::ModifyGoodsBrandRecomend()
     */
    public function ModifyGoodsBrandRecomend($brand_id, $brand_recommend)
    {
        Cache::tag("niu_goods_brand")->clear();
        $data = array()

        ;
        $res = $this->goods_brand->save($data, [
            'brand_id' => $brand_id
        ]);
        return $res;
        // TODO Auto-generated method stub
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IGoodsBrand::deleteGoodsBrand()
     */
    public function deleteGoodsBrand($brand_id_array)
    {
        Cache::tag("niu_goods_brand")->clear();
        $res = $this->goods_brand->destroy($brand_id_array);
        hook("goodsBrandDeleteSuccess", [
            'brand_id' => $brand_id_array
        ]);
        return $res;
        // TODO Auto-generated method stub
    }

    /**
     * 根据id获取商品品牌信息
     * 
     * @param unknown $module_id            
     */
    public function getGoodsBrandInfo($brand_id, $field = '*')
    {
        $cache = Cache::tag("niu_goods_brand")->get("getGoodsBrandInfo".$brand_id.'_'.$field);
        if(empty($cache))
        {
            $info = $this->goods_brand->getInfo(array(
                'brand_id' => $brand_id
            ), $field);
            Cache::tag("niu_goods_brand")->set("getGoodsBrandInfo".$brand_id.'_'.$field, $info);
            return $info;
        }else{
            return $cache;
        }
       
    }

    //获取品牌商品列表
//    public function getBrandGoodsList($brand_id,$condition,$page_index)
//    {
//        $cache = Cache::tag("niu_brand_goods")->get("getBrandGoodsInfo".$brand_id);
//        if(empty($cache))
//        {
//            $list = $this->goods_brand->getInfo(array(
//                'brand_id' => $brand_id
//            ), 'brand_name, brand_pic');
//            $goods = new GoodsService();
//            $list += $goods->getGoodsList($page_index, PAGESIZE, $condition, "ng.sort asc,ng.create_time desc");
//            Cache::store('redis')->tag("niu_brand_goods")->set("getGoodsBrandInfo".$brand_id, $list);
//            return $list;
//        }else{
//            return $cache;
//        }
//
//    }

    /**
     * 更改商品品牌排序
     *
     * @param unknown $goods_id
     * @param unknown $sort
     * @return boolean
     */
    public function updateGoodsBrandSort($brand_id, $sort)
    {
        Cache::tag('niu_goods_brand')->clear();
        return $this->goods_brand ->save([
            'sort' => $sort
        ], [
            'brand_id' => $brand_id
        ]);
    }
}