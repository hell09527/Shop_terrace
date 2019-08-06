<?php
/**
 * Goods.php
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

use data\service\Address;
use data\service\Album;
use data\service\Express as Express;
use data\service\Goods as GoodsService;
use data\service\GoodsBrand as GoodsBrand;
use data\service\GoodsCategory as GoodsCategory;
use data\service\GoodsGroup as GoodsGroup;
use data\service\Supplier;
use Qiniu\json_decode;
use think\Config;
use data\service\VirtualGoods;
use data\service\Store as StoreService;
use data\model\NsGoodsModel;
use data\model\NsGoodsBrandModel;
use think\Db;
use data\model\NsCartModel;
use data\model\NsGoodsSkuModel;

/**
 * 商品控制器
 */
class Goods extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }

    //导入想买清单
    public function cartDataImport(){
        $goodsService = new GoodsService();
        $cart = new NsCartModel();
        $cart_goods_list = $cart->getQuery([], '*', 'cart_id asc');
        if (! empty($cart_goods_list)) {
            foreach ($cart_goods_list as $k => $v) {
                $result = $goodsService->cartDataImport($v);
            }
        }
        return $result;
    }

    //同步ns_goods表的material_code到ns_goods_sku表
    public function materialCode(){
        $goodsService = new GoodsService();
        $goodsModel = new NsGoodsModel();
        $goods_list = $goodsModel->getQuery([], '*', 'goods_id asc');
        if (! empty($goods_list)) {
            foreach ($goods_list as $k => $v) {
                $result = $goodsService->materialCode($v);
            }
        }
        return $result;
    }

    //同步ns_goods_sku表的code,material_code到ns_order_goods表
    public function codeUpdate(){
        $goodsService = new GoodsService();
        $goodsSkuModel = new NsGoodsSkuModel();
        $goods_sku_list = $goodsSkuModel->getQuery([], '*', 'goods_id asc');
        if (! empty($goods_sku_list)) {
            foreach ($goods_sku_list as $k => $v) {
                $result = $goodsService->codeUpdate($v);
            }
        }
        return $result;
    }

    /**
     * 根据商品ID查询单个商品，然后进行编辑操作
     *
     * 2016年11月25日 09:42:40
     *
     * @return \data\model\niushop\NsGoodsModel
     */
    public function GoodsSelect()
    {
        $goods_detail = new GoodsService();
        $goods        = $goods_detail->getGoodsDetail(request()->get('goodsId'));
        return $goods;
    }

    //生成单品页小程序码
    public function wxCodeList()
    {
        $goods_id    = request()->get('goodsId', 0);
        $url         = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . 'wxd145d8a6e951dd1b' . "&secret=" . '9e22a3ac6f4c0ccae03a2356e710d68f';
        $res         = $this->send_post($url, '');
        $AccessToken = json_decode($res, true);
        $AccessToken = $AccessToken['access_token'];
        $url         = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=" . $AccessToken;

        //线上码
        $post_data_bc =
            array(
                'scene' => $goods_id . '&0&0',
                'page' => 'pages/goods/goodsdetail/goodsdetail',
                'width' => 430,
            );
        $post_data_bc = json_encode($post_data_bc);
        $data_bc      = $this->send_post($url, $post_data_bc);
        $code_bc      = $this->data_uri($data_bc, 'image/png');
        $this->assign('code_bc', $code_bc);

        //门店码
        $store      = new StoreService();
        $store_list = $store->getStore([], 'store_id, store_code,store_name', 'store_id');
        foreach ($store_list as $k => $v) {
            $post_data_store              =
                array(
                    'scene' => $goods_id . '&0&' . $v['store_id'],
                    'page' => 'pages/goods/goodsdetail/goodsdetail',
                    'width' => 430,
                );
            $post_data_store              = json_encode($post_data_store);
            $code_store                   = $this->send_post($url, $post_data_store);
            $store_list[$k]['code_store'] = $this->data_uri($code_store, 'image/png');
        }
        $this->assign('store_list', $store_list);

        //KOL码
//        $kol_list = \think\Db::name('bc_kol')->select();
//        foreach ($kol_list as $k => $v) {
//            $user_info                  = \think\Db::name('sys_user')->where(['uid' => $v['uid']])->find();
//            $kol_list[$k]['nick_name']  = $user_info['nick_name'];
//            $post_data_kol              =
//                array(
//                    'scene' => $goods_id . '&' . $v['id'] . '&0',
//                    'page' => 'pages/goods/goodsdetail/goodsdetail',
//                    'width' => 430,
//                );
//            $post_data_kol              = json_encode($post_data_kol);
//            $code_kol                   = $this->send_post($url, $post_data_kol);
//            $store_list[$k]['code_kol'] = $this->data_uri($code_kol, 'image/png');
//        }
//        $this->assign('kol_list', $kol_list);

        $this->admin_user_record('查看商品小程序码',$goods_id,'');

        //商品详情
        $goods        = new NsGoodsModel();
        $goods_detail = $goods->get($goods_id);
        $this->assign('goods_detail', $goods_detail);
        return view($this->style . "Goods/wxCodeList");
    }

    //生成品牌页小程序码
    public function brandWxCode()
    {
        $brand_id    = request()->get('brand_id', 0);
        $url         = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . 'wxd145d8a6e951dd1b' . "&secret=" . '9e22a3ac6f4c0ccae03a2356e710d68f';
        $res         = $this->send_post($url, '');
        $AccessToken = json_decode($res, true);
        $AccessToken = $AccessToken['access_token'];
        $url         = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=" . $AccessToken;

        //门店码
        $store      = new StoreService();
        $store_list = $store->getStore([], 'store_id, store_code,store_name', 'store_id');
        foreach ($store_list as $k => $v) {
            $post_data_store              =
                array(
                    'scene' => $brand_id . '&0&' . $v['store_id'],
                    'page' => 'pages/goods/brandlist/brandlist',
                    'width' => 430,
                );
            $post_data_store              = json_encode($post_data_store);
            $code_store                   = $this->send_post($url, $post_data_store);
            $store_list[$k]['code_store'] = $this->data_uri($code_store, 'image/png');
        }
        $this->assign('store_list', $store_list);
        $this->admin_user_record('查看品牌小程序码',$brand_id,'');

        //商品品牌详情
        $goods        = new NsGoodsBrandModel();
        $brand_detail = $goods->get($brand_id);
        $this->assign('brand_detail', $brand_detail);
        return view($this->style . "Goods/brandWxCode");
    }

    /**
     * 消息推送http
     * @param $url
     * @param $post_data
     * @return bool|string
     */
    protected function send_post($url, $post_data)
    {
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type:application/json',
                //header 需要设置为 JSON
                'content' => $post_data,
                'timeout' => 60
                //超时时间
            )
        );
        $context = stream_context_create($options);
        $result  = file_get_contents($url, false, $context);
        return $result;
    }

    /**
     * @param $contents
     * @param $mime
     * @return string
     * 二进制转图片image/png
     */
    public function data_uri($contents, $mime)
    {
        $base64 = base64_encode($contents);
        return ('data:' . $mime . ';base64,' . $base64);
    }


    /**
     * 商品列表
     */
    public function goodsList()
    {
        $goodservice = new GoodsService();
        if (request()->isAjax()) {
            $page_index         = request()->post("page_index", 1);
            $page_size          = request()->post("page_size", PAGESIZE);
            $start_date         = request()->post('start_date') == '' ? 0 : getTimeTurnTimeStamp(request()->post('start_date'));
            $end_date           = request()->post('end_date') == '' ? 0 : getTimeTurnTimeStamp(request()->post('end_date'));
            $goods_name         = request()->post('goods_name', '');
            $goods_code         = request()->post('code', '');
            $product_barcode    = request()->post('product_barcode', '');
            $material_code      = request()->post('material_code', '');
            $sale_type              = request()->post('sale_type', '');
            $state              = request()->post('state', '');
            $source_type        = request()->post('source_type', '');
            $is_black_tech      = request()->post('is_black_tech', '');
            $category_id_1      = request()->post('category_id_1', '');
            $category_id_2      = request()->post('category_id_2', '');
            $category_id_3      = request()->post('category_id_3', '');
            $selectGoodsLabelId = request()->post('selectGoodsLabelId', '');
            $supplier_id        = request()->post('supplier_id', '');
            $brand_id           = request()->post('brand_id', '');
            $stock_warning      = request()->post("stock_warning", 0); // 库存预警
            $sort_rule          = request()->post("sort_rule", ""); // 字段排序规则

            if (!empty($selectGoodsLabelId)) {
                $selectGoodsLabelIdArray = explode(',', $selectGoodsLabelId);
                $selectGoodsLabelIdArray = array_filter($selectGoodsLabelIdArray);
                $str                     = "FIND_IN_SET(" . $selectGoodsLabelIdArray[0] . ",ng.group_id_array)";
                for ($i = 1; $i < count($selectGoodsLabelIdArray); $i++) {
                    $str .= "AND FIND_IN_SET(" . $selectGoodsLabelIdArray[$i] . ",ng.group_id_array)";
                }
                $condition[""] = [
                    [
                        "EXP",
                        $str
                    ]
                ];
            }

            if ($start_date != 0 && $end_date != 0) {
                $condition["ng.create_time"] = [
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
                $condition["ng.create_time"] = [
                    [
                        ">",
                        $start_date
                    ]
                ];
            } elseif ($start_date == 0 && $end_date != 0) {
                $condition["ng.create_time"] = [
                    [
                        "<",
                        $end_date
                    ]
                ];
            }

            if ($sale_type != "") {
                $condition["ng.sale_type"] = $sale_type;
            }

            if ($state != "") {
                $condition["ng.state"] = $state;
            }
            if ($source_type != "") {
                $condition["ng.source_type"] = $source_type;
            }
            if ($is_black_tech != "") {
                $condition["ng.is_black_tech"] = $is_black_tech;
            }
            if (!empty($goods_name)) {
                $condition["ng.goods_name"] = array(
                    "like",
                    "%" . $goods_name . "%"
                );
            }
            if (!empty($goods_code)) {
                $condition["ng.code"] = array(
                    "like",
                    "%" . $goods_code . "%"
                );
            }
            if (!empty($product_barcode)) {
                $condition["ng.product_barcode"] = array(
                    "like",
                    "%" . $product_barcode . "%"
                );
            }
            if (!empty($material_code)) {
                $condition["ng.material_code"] = array(
                    "like",
                    "%" . $material_code . "%"
                );
            }
            if ($category_id_3 != "") {
                $condition["ng.category_id_3"] = $category_id_3;
            } elseif ($category_id_2 != "") {
                $condition["ng.category_id_2"] = $category_id_2;
            } elseif ($category_id_1 != "") {
                $condition["ng.category_id_1"] = $category_id_1;
            }

            if ($supplier_id != '') {
                $condition['ng.supplier_id'] = $supplier_id;
            }

            if ($brand_id != '') {
                $condition['ng.brand_id'] = $brand_id;
            }

            $condition["ng.shop_id"] = $this->instance_id;

            // 库存预警
            if ($stock_warning == 1) {
                $condition['ng.min_stock_alarm'] = array(
                    "neq",
                    0
                );
                $condition['ng.stock']           = array(
                    "exp",
                    "<= ng.min_stock_alarm"
                );
            }

            # todo @陈 修改排序 上架的显示在前面
            $order = ["ng.state" => 'DESC'];
            # todo @陈 若需要根据实际销量排序 可以判断当key为sales时 , $sort_field = real_sales
            if (!empty($sort_rule)) {
                $sort_rule_arr = explode(",", $sort_rule);
                $sort_field    = $sort_rule_arr[0];
                $sort_value    = $sort_rule_arr[1];
                if ($sort_value == "a") {
                    $sort_value = "ASC";
                } elseif ($sort_value == "d") {
                    $sort_value = "DESC";
                } else {
                    $sort_value = "";
                }

                if (!empty($sort_value)) {
                    switch ($sort_field) {
                        case "price":
                            $order['ng.price'] = $sort_value;
                            break;
                        case "stock":
                            $order['ng.stock'] = $sort_value;
                            break;
                        case "sales":
                            $order['ng.sales'] = $sort_value;
                            break;
                        case "sort":
                            $order['ng.sort'] = $sort_value;
                            break;
                    }
                }
            } else {
                // 默认时间排序
                $order['ng.create_time'] = 'desc';
            }

            $result = $goodservice->getBackStageGoodsList($page_index, $page_size, $condition, $order);

            // 根据商品分组id，查询标签名称
            foreach ($result['data'] as $k => $v) {
                if (!empty($v['group_id_array'])) {
                    $goods_group_id   = explode(',', $v['group_id_array']);
                    $goods_group_name = '';
                    foreach ($goods_group_id as $key => $val) {
                        $goods_group      = new GoodsGroup();
                        $goods_group_info = $goods_group->getGoodsGroupDetail($val);
                        if (!empty($goods_group_info)) {
                            $goods_group_name .= $goods_group_info['group_name'] . ',';
                        }
                    }
                    $goods_group_name                       = rtrim($goods_group_name, ',');
                    $result["data"][$k]['goods_group_name'] = $goods_group_name;
                }

                # 修改副本字体颜色
                $result["data"][$k]['fb_color'] = '0';
                if(substr($v['goods_name'],-7) == '-副本'){
                    $result["data"][$k]['fb_color'] = '1';
                    $result["data"][$k]['fb_name1'] =  substr($v['goods_name'],0,-7);
                    $result["data"][$k]['fb_name2'] = '-副本';
                }
            }
            return $result;
        } else {
            $goods_group = new GoodsGroup();
            $groupList   = $goods_group->getGoodsGroupList(1, 0, [
                'shop_id' => $this->instance_id,
                'pid' => 0
            ]);
            if (!empty($groupList['data'])) {
                foreach ($groupList['data'] as $k => $v) {
                    $v['sub_list'] = $goods_group->getGoodsGroupList(1, 0, 'pid = ' . $v['group_id']);
                }
            }
            $this->assign("goods_group", $groupList['data']);
            $search_info = request()->get('search_info', '');
            $this->assign("search_info", $search_info);
            // 查找一级商品分类
            $goodsCategory    = new GoodsCategory();
            $oneGoodsCategory = $goodsCategory->getGoodsCategoryListByParentId(0);
            $this->assign("oneGoodsCategory", $oneGoodsCategory);
            // 供货商列表
            $supplier      = new Supplier();
            $supplier_list = $supplier->getSupplierList();
            $this->assign("supplier_list", $supplier_list['data']);
            //商品品牌列表
            $goodsBrand       = new GoodsBrand();
            $goods_brand_list = $goodsBrand->getGoodsBrandList();
            $this->assign("goods_brand_list", $goods_brand_list['data']);
            //上下架
            $state = request()->get("state", "");
            $this->assign("state", $state);
            // 库存预警
            $stock_warning = request()->get("stock_warning", 0);
            $this->assign("stock_warning", $stock_warning);
            $child_menu_list = array(
                array(
                    'url' => "goods/goodslist",
                    'menu_name' => "商品列表",
                    "active" => 1
                ),
                array(
                    'url' => "goods/recyclelist",
                    'menu_name' => "商品回收站",
                    "active" => 0
                )
            );
            $this->admin_user_record('查看商品列表','','');
            $this->assign('child_menu_list', $child_menu_list);
            return view($this->style . "Goods/goodsList");
        }
    }

    /**
     * 获取商品规格列表
     */
    public function getGoodsSkuList()
    {
        $goods_id = request()->post('goods_id');
        $goods_sku = new NsGoodsSkuModel();
        return $goods_sku->where('goods_id',$goods_id)
            ->field('sku_id,sku_name,material_code,stock,price')
            ->select();
    }

    /**
     * 更新商品规格库存
     */
    public function updateSkuNum(){
        $sku_array       = request()->post('sku_array/A');
        $goods_id        = request()->post('goods_id');
        $sku_stock_array = array_column($sku_array,'stock');
        Db::transaction(function() use($sku_stock_array,$sku_array,$goods_id){
            $goods_sku = new NsGoodsSkuModel();
            $goods     = new NsGoodsModel();
            $goods_sku->saveAll($sku_array);

            $goods->save([
                'stock' =>array_sum($sku_stock_array)
            ], [
                'goods_id' => $goods_id
            ]);
        });

        $this->admin_user_record('修改商品库存',[],'');

        return AjaxReturn('修改成功');
    }

//    public function goodsExcel()
//    {
//        $xlsName = "sku数据列表";
//        $condition['ng.category_id_1'] =['NOT IN','24,33,37,38'];
//        $condition['ng.brand_id'] =['NOT IN','16,18,19'];
//        $goodservice = new GoodsService();
//        $list      = $goodservice->goodsExcelList($condition);
//        $xlsCell     = array(
//            array(
//                'goods_id',
//                '商品ID'
//            ),
//            array(
//                'goods_name',
//                '商品名称'
//            ),
//            array(
//                'sku_name',
//                'sku名称'
//            ),
//            array(
//                'brand_name',
//                '品牌名称'
//            ),
//            array(
//                'category_name',
//                '商品分类'
//            ),
//            array(
//                'code',
//                '商品编码'
//            ),
//            array(
//                'material_code',
//                'shopal物料编码'
//            ),
//            array(
//                'state_name',
//                '上下架'
//            ),
//            array(
//                'source_type_name',
//                '来源类型'
//            ),
//            array(
//                'goods_type_name',
//                '实物类型'
//            ),
//            array(
//                'stock',
//                '总库存'
//            )
//        );
//        dataExcel($xlsName, $xlsCell, $list);
//        exit;
//    }

    public function goodsExcel()
    {
        $xlsName            = "商品数据列表";
        $condition['ng.category_id_1'] =['NOT IN','24,33,37,38'];
        $condition['ng.brand_id'] =['NOT IN','16,18,19'];
        $goodservice = new GoodsService();
        $sku_data      = $goodservice->getGoodsDataList($condition);
        Excel($xlsName, $sku_data);
        exit;
    }

    /**
     * 商品数据excel导出
     */
//    public function goodsDataExcel()
//    {
//        $xlsName            = "商品数据列表";
//        $category_id_1      = request()->get('category_id_1', '');//一级商品分类
//        $category_id_2      = request()->get('category_id_2', '');//二级商品分类
//        $category_id_3      = request()->get('category_id_3', '');//三级商品分类
//        $goods_name         = request()->get('goods_name', '');//商品名称
//        $goods_code         = request()->get('code', '');//商品编码
////        $product_barcode    = request()->get('product_barcode', '');//商品条形码
//        $material_code      = request()->get('material_code', '');//shopal物料编码
//        $selectGoodsLabelId = request()->get('selectGoodsLabelId', '');//商品标签
//        $supplier_id        = request()->get('supplier_id', '');//供货商
//        $brand_id           = request()->get('brand_id', '');//商品品牌
//        $sale_type          = request()->get('sale_type', '');//销售方式
//        $state              = request()->get('state', '');//上下架
//        $source_type        = request()->get('source_type', '');//来源类型
//        $is_black_tech      = request()->get('is_black_tech', '');//黑科技
//        $stock_warning      = request()->get("stock_warning", 0); // 库存预警
//        $sort_rule          = request()->get("sort_rule", ""); // 字段排序规则
//        $goods_ids          = request()->get("goods_ids", "");
//
//        if ($category_id_3 != "") {
//            $condition["ng.category_id_3"] = $category_id_3;
//        } elseif ($category_id_2 != "") {
//            $condition["ng.category_id_2"] = $category_id_2;
//        } elseif ($category_id_1 != "") {
//            $condition["ng.category_id_1"] = $category_id_1;
//        }
//        if (!empty($goods_name)) {
//            $condition["ng.goods_name"] = array(
//                "like",
//                "%" . $goods_name . "%"
//            );
//        }
//        if (!empty($goods_code)) {
//            $condition["ng.code"] = array(
//                "like",
//                "%" . $goods_code . "%"
//            );
//        }
////        if (!empty($product_barcode)) {
////            $condition["ng.product_barcode"] = array(
////                "like",
////                "%" . $product_barcode . "%"
////            );
////        }
//        if (!empty($material_code)) {
//            $condition["ng.material_code"] = array(
//                "like",
//                "%" . $material_code . "%"
//            );
//        }
//        if (!empty($selectGoodsLabelId)) {
//            $selectGoodsLabelIdArray = explode(',', $selectGoodsLabelId);
//            $selectGoodsLabelIdArray = array_filter($selectGoodsLabelIdArray);
//            $str                     = "FIND_IN_SET(" . $selectGoodsLabelIdArray[0] . ",ng.group_id_array)";
//            for ($i = 1; $i < count($selectGoodsLabelIdArray); $i++) {
//                $str .= "AND FIND_IN_SET(" . $selectGoodsLabelIdArray[$i] . ",ng.group_id_array)";
//            }
//            $condition[""] = [
//                [
//                    "EXP",
//                    $str
//                ]
//            ];
//        }
//        if ($supplier_id != '') {
//            $condition['ng.supplier_id'] = $supplier_id;
//        }
//        if ($brand_id != '') {
//            $condition['ng.brand_id'] = $brand_id;
//        }
//        if ($sale_type != "") {
//            $condition["ng.sale_type"] = $sale_type;
//        }
//        if ($state != "") {
//            $condition["ng.state"] = $state;
//        }
//        if ($source_type != "") {
//            $condition["ng.source_type"] = $source_type;
//        }
//        if ($is_black_tech != "") {
//            $condition["ng.is_black_tech"] = $is_black_tech;
//        }
//        // 库存预警
//        if ($stock_warning == 1) {
//            $condition['ng.min_stock_alarm'] = array(
//                "neq",
//                0
//            );
//            $condition['ng.stock']           = array(
//                "exp",
//                "<= ng.min_stock_alarm"
//            );
//        }
//        if ($goods_ids != "") {
//            $condition["ng.goods_id"] = [
//                "in",
//                $goods_ids
//            ];
//        }
//        $condition["ng.shop_id"] = $this->instance_id;
//        //上架的显示在前面
//        $order = ["ng.state" => 'DESC'];
//        //若需要根据实际销量排序 可以判断当key为sales时 , $sort_field = real_sales
//        if (!empty($sort_rule)) {
//            $sort_rule_arr = explode(",", $sort_rule);
//            $sort_field    = $sort_rule_arr[0];
//            $sort_value    = $sort_rule_arr[1];
//            if ($sort_value == "a") {
//                $sort_value = "ASC";
//            } elseif ($sort_value == "d") {
//                $sort_value = "DESC";
//            } else {
//                $sort_value = "";
//            }
//
//            if (!empty($sort_value)) {
//                switch ($sort_field) {
//                    case "price":
//                        $order['ng.price'] = $sort_value;
//                        break;
//                    case "stock":
//                        $order['ng.stock'] = $sort_value;
//                        break;
//                    case "sales":
//                        $order['ng.sales'] = $sort_value;
//                        break;
//                    case "sort":
//                        $order['ng.sort'] = $sort_value;
//                        break;
//                }
//            }
//        } else {
//            // 默认时间排序
//            $order['ng.create_time'] = 'desc';
//        }
//
//        $goodservice = new GoodsService();
//        $result      = $goodservice->getGoodsDataList($condition, $order);
//        $xlsCell     = array(
//            array(
//                'goods_id',
//                '商品ID'
//            ),
//            array(
//                'goods_name',
//                '商品名称'
//            ),
//            array(
//                'source_type_name',
//                '来源类型'
//            ),
//            array(
//                'sale_type_name',
//                '销售方式'
//            ),
//            array(
//                'state_name',
//                '上下架'
//            ),
//            array(
//                'category_name',
//                '商品分类'
//            ),
//            array(
//                'fraction_name',
//                '分润比例'
//            ),
//            array(
//                'goods_type_name',
//                '实物类型'
//            ),
//            array(
//                'brand_name',
//                '品牌名称'
//            ),
//            array(
//                'code',
//                '商品编码'
//            ),
//            array(
//                'material_code',
//                'shopal物料编码'
//            ),
//            array(
//                'market_price',
//                '市场价'
//            ),
//            array(
//                'price',
//                '销售价'
//            ),
//            array(
//                'stock',
//                '总库存'
//            ),
//            array(
//                'min_stock_alarm',
//                '库存预警'
//            )
//        );
//
//        $this->admin_user_record('商品数据导出','','');
//
//        dataExcel($xlsName, $xlsCell, $result);
//        exit;
//    }

    public function goodsDataExcel()
    {
        $xlsName            = "商品数据列表";
        $category_id_1      = request()->get('category_id_1', '');//一级商品分类
        $category_id_2      = request()->get('category_id_2', '');//二级商品分类
        $category_id_3      = request()->get('category_id_3', '');//三级商品分类
        $goods_name         = request()->get('goods_name', '');//商品名称
        $goods_code         = request()->get('code', '');//商品编码
//        $product_barcode    = request()->get('product_barcode', '');//商品条形码
        $material_code      = request()->get('material_code', '');//shopal物料编码
        $selectGoodsLabelId = request()->get('selectGoodsLabelId', '');//商品标签
        $supplier_id        = request()->get('supplier_id', '');//供货商
        $brand_id           = request()->get('brand_id', '');//商品品牌
        $sale_type          = request()->get('sale_type', '');//销售方式
        $state              = request()->get('state', '');//上下架
        $source_type        = request()->get('source_type', '');//来源类型
        $is_black_tech      = request()->get('is_black_tech', '');//黑科技
        $stock_warning      = request()->get("stock_warning", 0); // 库存预警
        $sort_rule          = request()->get("sort_rule", ""); // 字段排序规则
        $goods_ids          = request()->get("goods_ids", "");

        if ($category_id_3 != "") {
            $condition["ng.category_id_3"] = $category_id_3;
        } elseif ($category_id_2 != "") {
            $condition["ng.category_id_2"] = $category_id_2;
        } elseif ($category_id_1 != "") {
            $condition["ng.category_id_1"] = $category_id_1;
        }
        if (!empty($goods_name)) {
            $condition["ng.goods_name"] = array(
                "like",
                "%" . $goods_name . "%"
            );
        }
        if (!empty($goods_code)) {
            $condition["ng.code"] = array(
                "like",
                "%" . $goods_code . "%"
            );
        }
//        if (!empty($product_barcode)) {
//            $condition["ng.product_barcode"] = array(
//                "like",
//                "%" . $product_barcode . "%"
//            );
//        }
        if (!empty($material_code)) {
            $condition["ng.material_code"] = array(
                "like",
                "%" . $material_code . "%"
            );
        }
        if (!empty($selectGoodsLabelId)) {
            $selectGoodsLabelIdArray = explode(',', $selectGoodsLabelId);
            $selectGoodsLabelIdArray = array_filter($selectGoodsLabelIdArray);
            $str                     = "FIND_IN_SET(" . $selectGoodsLabelIdArray[0] . ",ng.group_id_array)";
            for ($i = 1; $i < count($selectGoodsLabelIdArray); $i++) {
                $str .= "AND FIND_IN_SET(" . $selectGoodsLabelIdArray[$i] . ",ng.group_id_array)";
            }
            $condition[""] = [
                [
                    "EXP",
                    $str
                ]
            ];
        }
        if ($supplier_id != '') {
            $condition['ng.supplier_id'] = $supplier_id;
        }
        if ($brand_id != '') {
            $condition['ng.brand_id'] = $brand_id;
        }
        if ($sale_type != "") {
            $condition["ng.sale_type"] = $sale_type;
        }
        if ($state != "") {
            $condition["ng.state"] = $state;
        }
        if ($source_type != "") {
            $condition["ng.source_type"] = $source_type;
        }
        if ($is_black_tech != "") {
            $condition["ng.is_black_tech"] = $is_black_tech;
        }
        // 库存预警
        if ($stock_warning == 1) {
            $condition['ng.min_stock_alarm'] = array(
                "neq",
                0
            );
            $condition['ng.stock']           = array(
                "exp",
                "<= ng.min_stock_alarm"
            );
        }
        if ($goods_ids != "") {
            $condition["ng.goods_id"] = [
                "in",
                $goods_ids
            ];
        }
        $condition["ng.shop_id"] = $this->instance_id;
        //上架的显示在前面
        $order = ["ng.state" => 'DESC'];
        //若需要根据实际销量排序 可以判断当key为sales时 , $sort_field = real_sales
        if (!empty($sort_rule)) {
            $sort_rule_arr = explode(",", $sort_rule);
            $sort_field    = $sort_rule_arr[0];
            $sort_value    = $sort_rule_arr[1];
            if ($sort_value == "a") {
                $sort_value = "ASC";
            } elseif ($sort_value == "d") {
                $sort_value = "DESC";
            } else {
                $sort_value = "";
            }

            if (!empty($sort_value)) {
                switch ($sort_field) {
                    case "price":
                        $order['ng.price'] = $sort_value;
                        break;
                    case "stock":
                        $order['ng.stock'] = $sort_value;
                        break;
                    case "sales":
                        $order['ng.sales'] = $sort_value;
                        break;
                    case "sort":
                        $order['ng.sort'] = $sort_value;
                        break;
                }
            }
        } else {
            // 默认时间排序
            $order['ng.create_time'] = 'desc';
        }

        $goodservice = new GoodsService();
        $result      = $goodservice->getGoodsDataList($condition, $order);

        ini_set('memory_limit',"2048M");
        goodsDataExcel($xlsName,$result);
        exit;
    }

    public function getCategoryByParentAjax()
    {
        if (request()->isAjax()) {
            $parentId      = request()->post("parentId", '');
            $goodsCategory = new GoodsCategory();
            $res           = $goodsCategory->getGoodsCategoryListByParentId($parentId);
            return $res;
        }
    }

    /**
     * 创建时间：2015年6月1日09:40:10 创建人：高伟
     * 功能说明：通过ajax来的得到页面的数据
     */
    public function SelectCateGetData()
    {
        $goods_category_id   = request()->post("goods_category_id", ''); // 商品类目用
        $goods_category_name = request()->post("goods_category_name", ''); // 商品类目名称显示用
        $goods_attr_id       = request()->post("goods_attr_id", ''); // 关联商品类型ID
        $quick               = request()->post("goods_category_quick", ''); // JSON格式
        setcookie("goods_category_id", $goods_category_id, time() + 3600 * 24);
        setcookie("goods_category_name", $goods_category_name, time() + 3600 * 24);
        setcookie("goods_attr_id", $goods_attr_id, time() + 3600 * 24);
        setcookie("goods_category_quick", $quick, time() + 3600 * 24);
    }

    /**
     * 获取用户快速选择商品
     */
    public function getQuickGoods()
    {
        if (isset($_COOKIE["goods_category_quick"])) {
            return $_COOKIE["goods_category_quick"];
        } else {
            return -1;
        }
    }

    public function getGoodsGroupList()
    {
        $goods_group = new GoodsGroup();
        return $goods_group->getGroupGroup();
    }

    /**
     * 添加商品
     */
    public function addGoods()
    {
        $goods_group   = new GoodsGroup();
        $express       = new Express();
        $goods         = new GoodsService();
        $supplier      = new Supplier();
        $goodsbrand    = new GoodsBrand();
        $album         = new Album();
        $virtual_goods = new VirtualGoods();

        $goodsId   = request()->get('goodsId', 0);
        $groupList = $goods_group->getGoodsGroupList(1, 0, [
            'shop_id' => $this->instance_id
        ]);

        $supplier_list = $supplier->getSupplierList();
        $this->assign("supplier_list", $supplier_list['data']);

        $goods_attr_id = 0; // 商品类目关联id
        if (isset($_COOKIE["goods_category_id"])) {
            $this->assign("goods_category_id", $_COOKIE["goods_category_id"]);
            $name = str_replace(":", "&gt;", $_COOKIE["goods_category_name"]);
            $this->assign("goods_category_name", $name);
            $goods_attr_id = $_COOKIE["goods_attr_id"];
        } else {
            $this->assign("goods_category_id", 0); // 修改商品时，会进行查询赋值 2016年12月9日 10:54:07
            $this->assign("goods_category_name", "");
        }
        $this->assign("goods_attr_id", $goods_attr_id);
        $goods_attribute_list = $goods->getAttributeServiceList(1, 0, [
            'is_use' => 1
        ], "", "attr_id,attr_name");
        $this->assign("goods_attribute_list", $goods_attribute_list['data']); // 商品类型
        $this->assign("shipping_list", $express->shippingFeeQuery("")); // 物流
        $this->assign("group_list", $groupList['data']); // 分组
        if (empty($groupList['data'])) {
            $this->assign("group_str", '');
        } else {
            $this->assign("group_str", json_encode($groupList['data']));
        }
        $this->assign("goods_id", $goodsId);
        $this->assign("shop_type", 2);

        // 相册列表
        $detault_album_detail = $album->getDefaultAlbumDetail();
        $this->assign('detault_album_id', $detault_album_detail['album_id']);

        // 物流公司
        $expressCompanyList = $express->getExpressCompanyList(1, 0, [
            'shop_id' => $this->instance_id
        ]);
        $this->assign("expressCompanyList", $expressCompanyList['data']);

        // 虚拟商品类型
        $virtual_goods_type_list = $virtual_goods->getVirtualGoodsTypeList(1, 0);
        $this->assign("virtual_goods_type_list", $virtual_goods_type_list['data']);

        if ($goodsId > 0) {
            if (!is_numeric($goodsId)) {
                $this->error("参数错误");
            }

            $this->assign("goodsid", $goodsId);
            $goods_info = $goods->getGoodsDetail($goodsId);
            if (!empty($goods_info)) {
                $goods_info['sku_list']             = json_encode($goods_info['sku_list']);
                $goods_info['goods_group_list']     = json_encode($goods_info['goods_group_list']);
                $goods_info['img_list']             = json_encode($goods_info['img_list']);
                $goods_info['goods_attribute_list'] = json_encode($goods_info['goods_attribute_list']);
                // 判断规格数组中图片路径是id还是路径
                if (trim($goods_info['goods_spec_format']) != "") {
                    $album            = new Album();
                    $goods_spec_array = json_decode($goods_info['goods_spec_format'], true);
                    foreach ($goods_spec_array as $k => $v) {
                        foreach ($v["value"] as $t => $m) {
                            if (is_numeric($m["spec_value_data"]) && $m["spec_show_type"] == 3) {
                                $picture_detail = $album->getAlubmPictureDetail([
                                    "pic_id" => $m["spec_value_data"]
                                ]);
                                if (!empty($picture_detail)) {
                                    $goods_spec_array[$k]["value"][$t]["spec_value_data_src"] = $picture_detail["pic_cover_micro"];
                                }
                            } else
                                if (!is_numeric($m["spec_value_data"]) && $m["spec_show_type"] == 3) {
                                    $goods_spec_array[$k]["value"][$t]["spec_value_data_src"] = $m["spec_value_data"];
                                }
                        }
                    }
                    $goods_spec_format               = json_encode($goods_spec_array, JSON_UNESCAPED_UNICODE);
                    $goods_info['goods_spec_format'] = $goods_spec_format;
                }
                $extent_sort = count($goods_info["extend_category"]);
                $this->assign("extent_sort", $extent_sort);
                if ($goods_info["group_id_array"] == "") {
                    $this->assign("edit_group_array", array());
                } else {
                    $this->assign("edit_group_array", explode(",", $goods_info["group_id_array"]));
                }
                /**
                 * 当前cookie中存的goodsid
                 */
                $update_goods_id = isset($_COOKIE["goods_update_goodsid"]) ? $_COOKIE["goods_update_goodsid"] : 0;
                if ($update_goods_id == $goodsId) {
                    // $category_name = str_replace(":", "&gt;", $_COOKIE["goods_category_name"]);
                    $category_name               = str_replace(":", "", $_COOKIE["goods_category_name"]);
                    $goods_info["category_id"]   = $_COOKIE["goods_category_id"];
                    $goods_info["category_name"] = $category_name;
                }
                $goods_info['description'] = str_replace(PHP_EOL, '', $goods_info['description']);
                $goods_info['fraction']    = $goods_info['fraction'] * 100;
                $this->assign("goods_info", $goods_info);
                // 规格数据转json
                if (!empty($goods_info["sku_picture_array"])) {
                    $sku_picture_array_str = json_encode($goods_info["sku_picture_array"]);
                } else {
                    $sku_picture_array_str = '';
                }
                $this->assign("sku_picture_array_str", $sku_picture_array_str);

                $brand_info               = $goodsbrand->getGoodsBrandInfo($goods_info['brand_id'], 'brand_id,brand_name');
                $goods_info['brand_info'] = $brand_info;

                // 商品阶梯优惠
                $ladder_preferential = $goods->getGoodsLadderPreferential([
                    "goods_id" => $goodsId
                ]);
                $this->assign("ladder_preferential", $ladder_preferential);

                return view($this->style . "Goods/selectCategoryNextUpdate");
            } else {
                $this->error("商品不存在");
            }
        } else {
            return view($this->style . 'Goods/selectCategoryNext');
        }
    }

    /**
     * 获取商品品牌列表，商品编辑时用到
     * 创建时间：2017年11月11日 09:59:06 王永杰
     */
    public function getGoodsBrandList()
    {
        $page_index  = request()->post("page_index", 1);
        $page_size   = request()->post('page_size', PAGESIZE);
        $brand_name  = request()->post("brand_name", "");
        $search_name = request()->post("search_name", "");
        $brand_id    = request()->post("brand_id", "");
        // 排除当前选中的品牌，然后模糊查询
        $condition = array(
            'shop_id' => $this->instance_id,
            'brand_name|brand_initial' => array(
                [
                    "like",
                    "%$search_name%"
                ],
                [
                    'eq',
                    $brand_name
                ],
                'or'
            )
        );
        // 判断当时编辑商品还是添加商品，如果存在品牌id，则排除该品牌，防止搜索结果出现重复数据
        if (!empty($brand_id)) {
            $condition['brand_id'] = [
                'neq',
                $brand_id
            ];
        }
        $goodsbrand       = new GoodsBrand();
        $goods_brand_list = $goodsbrand->getGoodsBrandList($page_index, $page_size, $condition, '', 'brand_id,brand_name');
        return $goods_brand_list;
    }

    /**
     * 根据商品类型id查询，商品规格信息
     * 2017年6月5日 17:36:09 wyj
     */
    public function getGoodsSpecListByAttrId()
    {
        $goods                = new GoodsService();
        $condition["attr_id"] = request()->post("attr_id", 0);
        $list                 = $goods->getGoodsAttrSpecQuery($condition);
        return $list;
    }

    /**
     * 创建时间：2015年5月28日11:19:30 创建人：高伟
     * 功能说明：通过节点的ID查询得到某个节点下的子集
     */
    public function getChildCateGory()
    {
        $categoryID     = request()->post('categoryID', '');
        $goods_category = new GoodsCategory();
        $list           = $goods_category->getGoodsCategoryListByParentId($categoryID);
        return $list;
    }

    /**
     * 修改商品
     */
    public function updataGoods()
    {
        return view($this->style . "Goods/addGoods");
    }

    /**
     * 删除商品
     */
    public function deleteGoods()
    {
        $goods_ids   = request()->post('goods_ids');
        $goodservice = new GoodsService();
        $retval      = $goodservice->deleteGoods($goods_ids);
        $this->admin_user_record('删除商品',$goods_ids,'');
        return AjaxReturn($retval);
    }

    /**
     * 推广商品
     */
    public function spreadGoods()
    {
        $goods_id = request()->post('goods_id');
        echo $goods_id;
        exit;
        $goodservice = new GoodsService();
        $retval      = $goodservice->deleteGoods($goods_ids);
        return AjaxReturn($retval);
    }

    /**
     * 删除回收站商品
     */
    public function emptyDeleteGoods()
    {
        $goods_ids    = request()->post('goods_ids');
        $goodsservice = new GoodsService();
        $res          = $goodsservice->deleteRecycleGoods($goods_ids);
        $this->admin_user_record('删除回收站商品',$goods_ids,'');
        return AjaxReturn($res);
    }

    /**
     * 商品品牌列表
     */
    public function goodsBrandList()
    {
        if (request()->isAjax()) {
            $page_index    = request()->post("page_index", 1);
            $page_size     = request()->post("page_size", PAGESIZE);
            $search_text   = request()->post("search_text", "");
            $goodsbrand    = new GoodsBrand();
            $result        = $goodsbrand->getGoodsBrandList($page_index, $page_size, [
                'shop_id' => $this->instance_id,
                'brand_name' => array(
                    "like",
                    "%" . $search_text . "%"
                )
            ], "sort asc"); //brand_initial asc 改为了 sort asc  @author:Fu
            $goodsCatefory = new GoodsCategory();
            foreach ($result['data'] as $v) {
                $v['category_id_1_name'] = !empty($goodsCatefory->getName($v['category_id_1'])['category_name']) ? $goodsCatefory->getName($v['category_id_1'])['category_name'] : "";
                $v['category_id_2_name'] = !empty($goodsCatefory->getName($v['category_id_2'])['category_name']) ? $goodsCatefory->getName($v['category_id_2'])['category_name'] : "";
                $v['category_id_3_name'] = !empty($goodsCatefory->getName($v['category_id_3'])['category_name']) ? $goodsCatefory->getName($v['category_id_3'])['category_name'] : "";
            }
            return $result;
        } else {
            return view($this->style . "Goods/goodsBrandList");
        }
    }

    /**
     * 添加商品品牌
     */
    public function addGoodsBrand()
    {
        if (request()->isAjax()) {
            $goodsbrand          = new GoodsBrand();
            $shop_id             = $this->instance_id;
            $brand_name          = request()->post('brand_name', '');
            $brand_initial       = request()->post('brand_initial', '');
            $brand_pic           = request()->post('brand_pic', '');
            $brand_recommend     = request()->post('brand_recommend', '');
            $category_name       = request()->post('category_name', '');
            $category_id_1       = request()->post('category_id_1', 0);
            $category_id_2       = request()->post('category_id_2', 0);
            $category_id_3       = request()->post('category_id_3', 0);
            $sort                = 100;
            $brand_category_name = '';
            $category_id_array   = 1;
            $brand_ads           = request()->post('brand_ads', '');
            $brand_other_ads     = request()->post('brand_other_ads', '');
            $brand_video_address     = request()->post('brand_video_address', '');
            $res                 = $goodsbrand->addOrUpdateGoodsBrand('', $shop_id, $brand_name, $brand_initial, '', $brand_pic, $brand_recommend, $sort, $brand_category_name, $category_id_array, $brand_ads, $brand_other_ads, $category_name, $category_id_1, $category_id_2, $category_id_3,$brand_video_address);
            $this->admin_user_record('添加商品品牌',$res,'');

            return AjaxReturn($res);
        } else {
            $goodscategory = new GoodsCategory();
            $list          = $goodscategory->getGoodsCategoryListByParentId(0);
            $this->assign('goods_category_list', $list);

            return view($this->style . "Goods/addGoodsBrand");
        }
    }

    /**
     * 选择商品分类
     */
    function changeCategory()
    {
        $pid  = request()->post('pid', 0);
        $list = array();
        if ($pid > 0) {
            $goodscategory = new GoodsCategory();
            $list          = $goodscategory->getGoodsCategoryListByParentId($pid);
        }
        return $list;
    }

    /**
     * 修改商品品牌
     */
    public function updateGoodsBrand()
    {
        $goodsbrand = new GoodsBrand();
        if (request()->isAjax()) {
            $brand_id            = request()->post('brand_id', '');
            $brand_name          = request()->post('brand_name', '');
            $brand_initial       = request()->post('brand_initial', '');
            $brand_pic           = request()->post('brand_pic', '');
            $brand_recommend     = request()->post('brand_recommend', 0);
            $category_name       = request()->post('category_name', '');
            $category_id_1       = request()->post('category_id_1', 0);
            $category_id_2       = request()->post('category_id_2', 0);
            $category_id_3       = request()->post('category_id_3', 0);
            $sort                = 100;
            $brand_category_name = '';
            $category_id_array   = 1;
            $shopid              = $this->instance_id;
            $brand_ads           = request()->post('brand_ads', '');
            $brand_other_ads     = request()->post('brand_other_ads', '');
            $brand_video_address = request()->post('brand_video_address', '');
            $res                 = $goodsbrand->addOrUpdateGoodsBrand($brand_id, $shopid, $brand_name, $brand_initial, '', $brand_pic, $brand_recommend, $sort, $brand_category_name, $category_id_array, $brand_ads, $brand_other_ads, $category_name, $category_id_1, $category_id_2, $category_id_3,$brand_video_address);
            $this->admin_user_record('修改商品品牌',$res,'');
            return AjaxReturn($res);
        } else {
            $brand_id = request()->get('brand_id', '');
            if (!is_numeric($brand_id)) {
                $this->error('未获取到信息');
            }
            $brand_info = $goodsbrand->getGoodsBrandInfo($brand_id);
            if (empty($brand_info)) {
                return $this->error("没有查询到商品品牌信息");
            }
            $this->assign('brand_info', $brand_info);
            $goodscategory = new GoodsCategory();
            $list          = $goodscategory->getGoodsCategoryListByParentId(0);
            $this->assign('goods_category_list', $list);

            return view($this->style . "Goods/editGoodsBrand");
        }
    }

    /**
     * 删除商品品牌
     */
    public function deleteGoodsBrand()
    {
        $brand_id   = request()->post('brand_id', '');
        $goodsbrand = new GoodsBrand();
        $res        = $goodsbrand->deleteGoodsBrand($brand_id);
        $this->admin_user_record('删除商品品牌',$brand_id,'');
        return AjaxReturn($res);
    }

    /**
     * 商品分类列表
     */
    public function goodsCategoryList()
    {
        $goods_category = new GoodsCategory();
        $one_list       = $goods_category->getCategoryTreeUseInAdmin();
        $this->assign("category_list", $one_list);
        return view($this->style . "Goods/goodsCategoryList");
    }

    /**
     * 添加商品分类
     */
    public function addGoodsCategory()
    {
        $goodscate = new GoodsCategory();
        if (request()->isAjax()) {
            $category_name = request()->post("category_name", '');
            $pid           = request()->post("pid", '');
            $is_visible    = request()->post('is_visible', '');
            $keywords      = request()->post("keywords", '');
            $description   = request()->post("description", '');
            $sort          = request()->post("sort", '');
            $category_pic  = request()->post('category_pic', '');
            $category_ads  = request()->post('category_ads', '');
            $attr_id       = request()->post("attr_id", 0);
            $attr_name     = request()->post("attr_name", '');
            $short_name    = request()->post("short_name", '');
            $result        = $goodscate->addOrEditGoodsCategory(0, $category_name, $short_name, $pid, $is_visible, $keywords, $description, $sort, $category_pic, $category_ads, $attr_id, $attr_name);
            $this->admin_user_record('添加商品分类',$result,'');
            return AjaxReturn($result);
        } else {
            $category_list = $goodscate->getGoodsCategoryTree(0);
            $this->assign('category_list', $category_list);
            $goods              = new GoodsService();
            $goodsAttributeList = $goods->getAttributeServiceList(1, 0);
            $this->assign("goodsAttributeList", $goodsAttributeList['data']);

            return view($this->style . "Goods/addGoodsCategory");
        }
    }

    /**
     * 修改商品分类
     */
    public function updateGoodsCategory()
    {
        $goodscate = new GoodsCategory();
        if (request()->isAjax()) {
            $category_id          = request()->post("category_id", '');
            $category_name        = request()->post("category_name", '');
            $short_name           = request()->post("short_name", '');
            $pid                  = request()->post("pid", '');
            $is_visible           = request()->post('is_visible', '');
            $keywords             = request()->post("keywords", '');
            $description          = request()->post("description", '');
            $sort                 = request()->post("sort", '');
            $attr_id              = request()->post("attr_id", 0);
            $attr_name            = request()->post("attr_name", '');
            $category_pic         = request()->post('category_pic', '');
            $category_ads         = request()->post('category_ads', '');
            $goods_category_quick = request()->post("goods_category_quick", '');
            if ($goods_category_quick != '') {
                setcookie("goods_category_quick", $goods_category_quick, time() + 3600 * 24);
            }
            $result = $goodscate->addOrEditGoodsCategory($category_id, $category_name, $short_name, $pid, $is_visible, $keywords, $description, $sort, $category_pic, $category_ads, $attr_id, $attr_name);
            $this->admin_user_record('修改商品分类',$category_id,'');
            return AjaxReturn($result);
        } else {
            $category_id = request()->get('category_id', '');
            $result      = $goodscate->getGoodsCategoryDetail($category_id);
            $this->assign("data", $result);
            // 查询比当前等级高的 分类
            if ($result['level'] == 1) {
                $chile_list = $goodscate->getGoodsCategoryTree($category_id);
                if (empty($chile_list)) {
                    $category_list = $goodscate->getGoodsCategoryTree(0);
                } else {
                    $is_have = false;
                    foreach ($chile_list as $k => $v) {
                        if ($v["level"] == 3) {
                            $is_have = true;
                        }
                    }
                    if ($is_have) {
                        $category_list = array();
                    } else {
                        $category_list = $goodscate->getGoodsCategoryListByParentId(0);
                    }
                }
            } else
                if ($result['level'] == 2) {
                    $chile_list = $goodscate->getGoodsCategoryListByParentId($category_id);
                    if (empty($chile_list)) {
                        $category_list = $goodscate->getGoodsCategoryTree(0);
                    } else {
                        $category_list = $goodscate->getGoodsCategoryListByParentId(0);
                    }
                } else
                    if ($result['level'] == 3) {
                        $category_list = $goodscate->getGoodsCategoryTree(0);
                    }
            foreach ($category_list as $k => $v) {
                if ($v["category_id"] == $category_id && $category_id !== 0) {
                    unset($category_list[$k]);
                } else {
                    if (isset($v["child_list"])) {
                        $temp_array = $v["child_list"];
                        foreach ($temp_array as $t => $m) {
                            if ($m["category_id"] == $category_id && $category_id !== 0) {
                                unset($temp_array[$t]);
                            }
                        }
                        sort($temp_array);
                        $category_list[$k]["child_list"] = $temp_array;
                    }
                }
            }
            sort($category_list);
            $this->assign('category_list', $category_list);
            $goods              = new GoodsService();
            $goodsAttributeList = $goods->getAttributeServiceList(1, 0);
            $this->assign("goodsAttributeList", $goodsAttributeList['data']);

            return view($this->style . "Goods/updateGoodsCategory");
        }
    }

    /**
     * 删除商品分类
     */
    public function deleteGoodsCategory()
    {
        $goodscate   = new GoodsCategory();
        $category_id = request()->post('category_id', '');
        $res         = $goodscate->deleteGoodsCategory($category_id);
        if ($res > 0) {
            $goods_category_quick = request()->post("goods_category_quick", '');
            if ($goods_category_quick != '') {
                setcookie("goods_category_quick", $goods_category_quick, time() + 3600 * 24);
            }
        }
        $this->admin_user_record('删除商品分类',$category_id,'');
        return AjaxReturn($res);
    }

    /**
     * 创建时间：2015年6月10日15:25:14 创建人：高伟
     * 修改时间：2017年5月24日 15:49:10 王永杰
     * 功能说明：查询商品属性
     */
    public function getGoodsAttributeList()
    {
        $goods                = new GoodsService();
        $condition['shop_id'] = $this->instance_id;
        $provList             = $goods->getGoodsAttributeList($condition, '*', 'create_time desc');
        return $provList;
    }

    /**
     * 创建时间：2015年6月1日17:17:53 创建人：高伟
     * 功能说明：商品属性规格获取
     */
    public function CateGoryPropsGet()
    {
        $name        = request()->post('name', '');
        $goodservice = new GoodsService();
        $res         = $goodservice->addGoodsSpec($name);
        return $res;
    }

    /**
     * 创建时间：2015年6月1日17:17:53 创建人：高伟
     * 功能说明：商品属性规格值获取
     */
    public function CateGoryPropvaluesGet()
    {
        $propId      = request()->post('propId', '');
        $value       = request()->post('value', '');
        $goodservice = new GoodsService();
        $res         = $goodservice->addGoodsSpecValue($propId, $value);
        return $res;
    }

    /**
     * 设置规格属性是否启用
     */
    public function setIsvisible()
    {
        if (request()->isAjax()) {
            $spec_id     = request()->post('spec_id', '');
            $is_visible  = request()->post('is_visible', '');
            $goodservice = new GoodsService();
            $retval      = $goodservice->updateGoodsSpecIsVisible($spec_id, $is_visible);
            return AjaxReturn($retval);
        }
    }

    /**
     * 复制商品
     */
    public function copyGoods()
    {
        $goods_id    = request()->post('goods_id', '');
        $goodservice = new GoodsService();
        $res         = $goodservice->copyGoodsInfo($goods_id);
        $this->admin_user_record('拷贝商品',$goods_id,'');
        if ($res > 0) {
            $goodsId = $res;

            $url        = Config::get('view_replace_str.APP_MAIN') . '/Goods/goodsDetail?id=' . $goodsId;
            $pay_qrcode = getQRcode($url, 'upload/goods_qrcode', 'goods_qrcode_' . $goodsId);
            $wxcode = $this->getGoodsWxCode($goodsId);

            $goodservice->goods_code_make($goodsId, $pay_qrcode, $wxcode['url_online'],$wxcode['url_store']);

        }
        return AjaxReturn($res);
    }

    //批量复制商品
    public function batchCopyGoods(){
        $goods_ids = request()->post('goods_ids', '');
        $category_id1 = request()->post('category_id1', '');
        $name_add_type = request()->post('name_add_type', '');
        $name = request()->post('name', '');
        $goodservice = new GoodsService();
        $res = $goodservice->batchCopyGoodsInfo($goods_ids,$category_id1,$name_add_type,$name);
        return AjaxReturn($res);
    }

    /**
     * 创建时间：2015年6月12日09:50:07 创建人：高伟
     * 功能说明：添加或更新商品时 ajax调用的函数
     */
    public function GoodsCreateOrUpdate()
    {
        $res     = 0;
        $product = request()->post('product', '');
        $qrcode  = request()->post('is_qrcode', ''); // 1代表 需要创建 二维码 0代表不需要
        if (!empty($product)) {
            $product     = json_decode($product, true);
            $shopId      = $this->instance_id;
            $goodservice = new GoodsService();
            if ($product['is_black_tech'] !== '4') $product['new_pic'] = '';
            $res = $goodservice->addOrEditGoods(
                $product["goodsId"], // 商品Id
                $product["title"], // 商品标题
                $shopId, $product["categoryId"], // 商品类目
                $category_id_1 = 0, $category_id_2 = 0, $category_id_3 = 0, $product["supplierId"], $product["brandId"], $product["groupArray"], // 商品分组
                $product['source_type'], $product['goods_type'], $product["market_price"], $product["price"], // 商品现价
                $product["vip_price"], $product["point_exchange_type"], $product['integration_available_use'], $product['integration_available_give'], $is_member_discount = 0, $product["shipping_fee"], $product["shipping_fee_id"], $product["stock"], $product['max_buy'], $product['min_buy'], $product["minstock"], $product["base_sales"], $collects = 0, $star = 0, $evaluates = 0, $product["province_id"], $product["city_id"], $product["picture"], $product['key_words'], $product["introduction"], // 商品简介，促销语
                $product["description"], $product['qrcode'], // 商品二维码
                $product['is_vip'], $product['is_black_tech'], $product['material_black'], $product['use_black'], $product['effect_black'], $product['selling_point'], trim($product['code']), trim($product['material_code']), trim($product['fraction']), $product["display_stock"], $is_hot = 0, $is_recommend = 0, $is_new = 0, $sort = $product['sort'], $product["imageArray"], $product["skuArray"], $product["is_sale"], '', // $product["sku_img_array"]
                $product['goods_attribute_id'], $product['goods_attribute'], $product['goods_spec_format'], $product['goods_weight'], $product['goods_volume'], $product['shipping_fee_type'], $product['categoryExtendId'], $product["sku_picture_vlaues"], $product['virtual_goods_type_id'], $product['production_date'], $product['shelf_life'], $product['ladder_preference'], $product['goods_video_address'], $product['new_pic'],
                $product['sale_type'],$product['sale_end_time'],$product['delivery_end_time'] //预售
            );
            // sku编码分组
            if ($res > 0 && $qrcode == 1) {
                $goodsId    = $res;
                $url        = __URL(Config::get('view_replace_str.APP_MAIN') . '/goods/goodsdetail?id=' . $goodsId);
                $pay_qrcode = getQRcode($url, 'upload/goods_qrcode', 'goods_qrcode_' . $goodsId);

                $wxcode = $this->getGoodsWxCode($goodsId);

                $goodservice->goods_code_make($goodsId, $pay_qrcode, $wxcode['url_online'],$wxcode['url_store']);
            }
        }
        return $res;
    }

    public function getGoodsWxCode($goods_id){
        $url         = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . 'wxd145d8a6e951dd1b' . "&secret=" . '9e22a3ac6f4c0ccae03a2356e710d68f';
        $res         = $this->send_post($url, '');
        $AccessToken = json_decode($res, true);
        $AccessToken = $AccessToken['access_token'];
        $url         = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=" . $AccessToken;

        //线上码
        $post_data_bc =
            array(
                'scene' => $goods_id . '&0&0',
                'page' => 'pages/goods/goodsdetail/goodsdetail',
                'width' => 430,
            );
        $post_data_bc = json_encode($post_data_bc);
        $data_bc      = $this->send_post($url, $post_data_bc);
        $code_bc      = $this->data_uri($data_bc, 'image/png');
        $url_online = $this->base64_image_content($code_bc,'upload/goods_wxcode/online',$goods_id);

        //门店码
        $post_data_store              =
            array(
                'scene' => $goods_id . '&0&1',
                'page' => 'pages/goods/goodsdetail/goodsdetail',
                'width' => 430,
            );
        $post_data_store              = json_encode($post_data_store);
        $code_store                   = $this->send_post($url, $post_data_store);
        $code_store = $this->data_uri($code_store, 'image/png');
        $url_store = $this->base64_image_content($code_store,'upload/goods_wxcode/store_1',$goods_id);
        $data = [
            'url_online'=>$url_online,
            'url_store'=>$url_store
        ];
        return $data;
    }

    // todo 生成商品小程序码
    public function updateGoodsWxCode(){
        //商品列表
        $goods = new NsGoodsModel();
        $list = $goods->getQuery(['goods_id'=>['>',1358]],'goods_id','goods_id');

        foreach($list as $val){
            $url         = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . 'wxd145d8a6e951dd1b' . "&secret=" . '9e22a3ac6f4c0ccae03a2356e710d68f';
            $res         = $this->send_post($url, '');
            $AccessToken = json_decode($res, true);
            $AccessToken = $AccessToken['access_token'];
            $url         = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=" . $AccessToken;

            //线上码
            $post_data_bc =
                array(
                    'scene' => $val['goods_id'] . '&0&0',
                    'page' => 'pages/goods/goodsdetail/goodsdetail',
                    'width' => 430,
                );
            $post_data_bc = json_encode($post_data_bc);
            $data_bc      = $this->send_post($url, $post_data_bc);
            $code_bc      = $this->data_uri($data_bc, 'image/png');
            $url_online = $this->base64_image_content($code_bc,'upload/goods_wxcode/online',$val['goods_id']);

            //门店码
            $post_data_store              =
                array(
                    'scene' => $val['goods_id'] . '&0&1',
                    'page' => 'pages/goods/goodsdetail/goodsdetail',
                    'width' => 430,
                );
            $post_data_store              = json_encode($post_data_store);
            $code_store                   = $this->send_post($url, $post_data_store);
            $code_store = $this->data_uri($code_store, 'image/png');
            $url_store = $this->base64_image_content($code_store,'upload/goods_wxcode/store_1',$val['goods_id']);
            $goods  = new GoodsService();
            $goods->goods_wxcode_make($val['goods_id'],$url_online,$url_store);
        }
    }

    function base64_image_content($base64_image_content,$path, $goods_id){

        //匹配出图片的格式
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)){

            $type = $result[2];
            $new_file = $path."/";
            if(!file_exists($new_file)){
                //检查是否有该文件夹，如果没有就创建，并给予最高权限
                mkdir($new_file, 0777,true);
            }
            $new_file = $new_file.'goods_wxcode_'.$goods_id.".".$type;
            if (file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_image_content)))){
                return '/'.$new_file;
            }else{
                return '';
            }
        }else{
            return '';
        }
    }

//    function base64_image_content($base64_image_content,$dir, $goods_id){
//
//        $new_file = APP_PATH . '../upload/goods_wxcode/' .$dir. '/';
//
//
//        //匹配出图片的格式
//        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)){
//
//            $type = $result[2];
////            $new_file = $path."/";
////            if(!file_exists($new_file)){
////                //检查是否有该文件夹，如果没有就创建，并给予最高权限
////                mkdir($new_file, 0777,true);
////            }
//            $new_file = $new_file.'goods_wxcode_'.$goods_id.".".$type;
//            if (file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_image_content)))){
//                return '/'.$new_file;
//            }else{
//                return '';
//            }
//        }else{
//            return '';
//        }
//    }

    /**
     * 获取省列表，商品添加时用户可以设置商品所在地
     * 创建人：王永杰
     * 创建时间：2017年2月22日 16:01:26
     */
    public function getProvince()
    {
        $address       = new Address();
        $province_list = $address->getProvinceList();
        return $province_list;
    }

    /**
     * 获取城市列表
     * 创建人：王永杰
     * 创建时间：2017年2月22日 16:01:56
     *
     * @return Ambigous <multitype:\think\static , \think\false, \think\Collection, \think\db\false, PDOStatement, string, \PDOStatement, \think\db\mixed, boolean, unknown, \think\mixed, multitype:, array>
     */
    public function getCity()
    {
        $address     = new Address();
        $province_id = request()->post('province_id', 0);
        $city_list   = $address->getCityList($province_id);
        return $city_list;
    }

    /**
     * 商品分组列表
     */
    public function goodsGroupList()
    {
        if (request()->isAjax()) {
            $goodsgroup = new GoodsGroup();
            $page_index = request()->post("page_index", 1);
            $page_size  = request()->post("page_size", PAGESIZE);
            $condition  = array();
            $list       = $goodsgroup->getGoodsGroupList($page_index, $page_size, $condition, "pid, sort");
            return $list;
        } else {

            return view($this->style . "Goods/goodsGroupList");
        }
    }

    /**
     * 添加商品分组
     */
    public function addGoodsGroup()
    {
        $goodsgroup = new GoodsGroup();
        if (request()->isAjax()) {
            $shop_id    = $this->instance_id;
            $group_name = request()->post('group_name', '');
            $pid        = request()->post('pid', 0);
            $is_visible = request()->post('is_visible', '');
            $sort       = request()->post('sort', '');
            $group_pic  = request()->post('group_pic', '');
            $result     = $goodsgroup->addOrEditGoodsGroup(0, $shop_id, $group_name, $pid, $is_visible, $sort, $group_pic);
            $this->admin_user_record('添加商品分组',$result,'');
            return AjaxReturn($result);
        } else {
            return view($this->style . "Goods/addGoodsGroup");
        }
    }

    /**
     * 修改商品分类
     */
    public function updateGoodsGroup()
    {
        $goodsgroup = new GoodsGroup();
        if (request()->isAjax()) {
            $group_id   = request()->post('group_id', '');
            $shop_id    = $this->instance_id;
            $group_name = request()->post('group_name', '');
            $pid        = request()->post('pid', '');
            $is_visible = request()->post('is_visible', '');
            $sort       = request()->post('sort', '');
            $group_pic  = request()->post('group_pic', '');
            $result     = $goodsgroup->addOrEditGoodsGroup($group_id, $shop_id, $group_name, $pid, $is_visible, $sort, $group_pic);
            $this->admin_user_record('修改商品分类',$group_id,'');
            return AjaxReturn($result);
        } else {
            $group_id = request()->get('group_id', '');
            $result   = $goodsgroup->getGoodsGroupDetail($group_id);
            $this->assign("data", $result);

            return view($this->style . "Goods/updateGoodsGroup");
        }
    }

    /**
     * 删除商品分类
     */
    public function deleteGoodsGroup()
    {
        $goodsgroup = new GoodsGroup();
        $group_id   = request()->post('group_id', '');
        if (!is_numeric($group_id)) {
            $this->error('未获取到信息');
        }
        $res = $goodsgroup->deleteGoodsGroup($group_id, $this->instance_id);
        $this->admin_user_record('删除商品分类',$group_id,'');
        return AjaxReturn($res);
    }

    /**
     * 修改 商品 分类 单个字段
     */
    public function modifyGoodsCategoryField()
    {
        $goodscate  = new GoodsCategory();
        $fieldid    = request()->post('fieldid', '');
        $fieldname  = request()->post('fieldname', '');
        $fieldvalue = request()->post('fieldvalue', '');
        $res        = $goodscate->ModifyGoodsCategoryField($fieldid, $fieldname, $fieldvalue);
        $this->admin_user_record('修改商品分类单个字段',$res,'');
        return $res;
    }

    /**
     * 修改 商品 分组 单个字段
     */
    public function modifyGoodsGroupField()
    {
        $goodsgroup = new GoodsGroup();
        $fieldid    = request()->post('fieldid', '');
        $fieldname  = request()->post('fieldname', '');
        $fieldvalue = request()->post('fieldvalue', '');
        $res        = $goodsgroup->ModifyGoodsGroupField($fieldid, $fieldname, $fieldvalue);
        $this->admin_user_record('修改商品分组单个字段',$res,'');
        return $res;
    }

    /**
     * 商品上架
     */
    public function modifyGoodsOnline()
    {
        $condition    = request()->post('goods_ids', '');
        $goods_detail = new GoodsService();
        $result       = $goods_detail->ModifyGoodsOnline($condition);
        $this->admin_user_record('商品上架',$condition,'');
        return AjaxReturn($result);
    }

    /**
     * 商品下架
     */
    public function modifyGoodsOffline()
    {
        $condition    = request()->post('goods_ids', '');
        $goods_detail = new GoodsService();
        $result       = $goods_detail->ModifyGoodsOffline($condition);
        $this->admin_user_record('商品下架',$condition,'');
        return AjaxReturn($result);
    }

    /**
     * 获取限时折扣商品
     *
     * @return unknown
     */
    public function getDiscountGoodsList()
    {
        $page_index = request()->post("page_index", 1);
        $page_size = request()->post("page_size", PAGESIZE);
        $search_text = request()->post("goods_name", "");
        $material_code = request()->post("material_code", "");
        $category_id_1 = request()->post('category_id_1', '');
        $category_id_2 = request()->post('category_id_2', '');
        $category_id_3 = request()->post('category_id_3', '');
        $condition = array(
            "goods_name" => [
                "like",
                "%$search_text%"
            ],
            "stock" => [
                "GT",
                0
            ]
        );

        if ($category_id_3 != "") {
            $condition["category_id_3"] = $category_id_3;
        } elseif ($category_id_2 != "") {
            $condition["category_id_2"] = $category_id_2;
        } elseif ($category_id_1 != "") {
            $condition["category_id_1"] = $category_id_1;
        }
        $goods_detail = new GoodsService();
        $result = $goods_detail->getDiscountList($page_index, $page_size, $condition,'','*',$material_code);
        return $result;
    }

    /**
     * 获取筛选后的商品
     *
     * @return unknown
     */
    public function getSearchGoodsList()
    {
        $page_index    = request()->post("page_index", 1);
        $page_size     = request()->post("page_size", PAGESIZE);
        $search_text   = request()->post("search_text", "");
        $search_sku    = request()->post("search_sku", "");
        $search_goods_sku = request()->post("search_goods_sku", "");
        $is_have_sku   = request()->post("is_have_sku", 1);
        $goods_type    = request()->post("goods_type", "");
        $category_id_1 = request()->post('category_id_1', '');
        $category_id_2 = request()->post('category_id_2', '');
        $category_id_3 = request()->post('category_id_3', '');

        $condition = array(
            "goods_name" => [
                "like",
                "%$search_text%"
            ],
            "material_code" => [
                "like",
                "%$search_sku%"
            ],
            "stock" => [
                "GT",
                0
            ]
        );

        if ($is_have_sku == 0) {
            $condition["goods_spec_format"] = '[]';
        }
        if (!empty($goods_type)) {
            $condition["goods_type"] = $goods_type;
        }

        if ($category_id_3 != "") {
            $condition["category_id_3"] = $category_id_3;
        } elseif ($category_id_2 != "") {
            $condition["category_id_2"] = $category_id_2;
        } elseif ($category_id_1 != "") {
            $condition["category_id_1"] = $category_id_1;
        }
        $goods_detail = new GoodsService();
        $result = $goods_detail->getSearchGoodsList($page_index, $page_size, $condition,'create_time desc','goods_id,goods_name,category_id,category_id_1,category_id_2,category_id_3,brand_id,goods_type,price,stock,picture', $search_goods_sku);
        return $result;
    }

    /**
     * 获取营销商品
     *
     * @return unknown
     */
    public function getPromotionGoodsList()
    {
        $page_index = request()->post("page_index", 1);
        $page_size = request()->post("page_size", PAGESIZE);
        $goods_name = request()->post("goods_name", "");
        $material_code = request()->post("material_code", "");
        $category_id_1 = request()->post('category_id_1', '');
        $category_id_2 = request()->post('category_id_2', '');
        $category_id_3 = request()->post('category_id_3', '');
        $condition = array(
            "goods_name" => [
                "like",
                "%$goods_name%"
            ],
            "material_code" => [
                "like",
                "%$material_code%"
            ],
            "stock" => [
                "GT",
                0
            ]
        );

        if ($category_id_3 != "") {
            $condition["category_id_3"] = $category_id_3;
        } elseif ($category_id_2 != "") {
            $condition["category_id_2"] = $category_id_2;
        } elseif ($category_id_1 != "") {
            $condition["category_id_1"] = $category_id_1;
        }
        $goods_detail = new GoodsService();
        $result = $goods_detail->getPromotionGoodsList($page_index, $page_size, $condition);
        return $result;
    }

    /**
     * 获取 商品分组一级分类
     *
     * @return Ambigous <number, unknown>
     */
    public function getGoodsGroupFristLevel()
    {
        $goods_group = new GoodsGroup();
        $list        = $goods_group->getGoodsGroupListByParentId($this->instance_id, 0);
        return $list;
    }

    /**
     * 修改分组
     */
    public function ModifyGoodsGroup()
    {
        $goods_id     = request()->post('goods_id', '');
        $goods_type   = request()->post('goods_type', '');
        $goods_detail = new GoodsService();
        $result       = $goods_detail->ModifyGoodsGroup($goods_id, $goods_type);
        $this->admin_user_record('修改分组',$goods_id,'');
        return AjaxReturn($result);
    }

    /**
     * 分润设置
     */
    public function modifygoodsFraction()
    {
        $goods_ids    = request()->post('goods_id', '');
        $fraction     = request()->post('fraction', '');
        $goods_detail = new GoodsService();
        $result       = $goods_detail->modifygoodsFraction($goods_ids, $fraction);
        $this->admin_user_record('分润设置',$goods_ids,'');
        return AjaxReturn($result);
    }

    /**
     * 修改推荐商品
     */
    public function ModifyGoodsRecommend()
    {
        $goods_ids      = request()->post('goods_id', '');
        $recommend_type = request()->post('recommend_type', '');
        $result         = $this->updateIsBlackTech($goods_ids, $recommend_type);
//        $goods_detail = new GoodsService();
//        $result = $goods_detail->ModifyGoodsRecommend($goods_ids, $recommend_type);
        $this->admin_user_record('修改推荐商品',$goods_ids,'');
        return AjaxReturn($result, 'SUCCESS');
    }

    /**
     * 商品属性
     */
    public function goodsSpecList()
    {
        $goods = new GoodsService();
        if (request()->isAjax()) {
            $page_index = request()->post('page_index', 1);
            $page_size  = request()->post('page_size', PAGESIZE);
            $list       = $goods->getGoodsSpecList($page_index, $page_size, '', 'sort asc, create_time desc');
            return $list;
        }
        return view($this->style . 'Goods/goodsSpecList');
    }

    /**
     * 修改商品规格单个属性值
     */
    public function setGoodsSpecField()
    {
        $goods       = new GoodsService();
        $spec_id     = request()->post("id", '');
        $field_name  = request()->post("name", '');
        $field_value = request()->post("value", '');
        $retval      = $goods->modifyGoodsSpecField($spec_id, $field_name, $field_value);
        $this->admin_user_record('修改商品规格单个属性值',$spec_id,'');
        return AjaxReturn($retval);
    }

    /**
     * 添加规格
     */
    public function addGoodsSpec()
    {
        $goods = new GoodsService();
        if (request()->isAjax()) {
            $spec_name      = request()->post('spec_name', '');
            $is_visible     = request()->post('is_visible', '');
            $sort           = request()->post('sort', '');
            $show_type      = request()->post('show_type', '');
            $spec_value_str = request()->post('spec_value_str', '');
            $attr_id        = request()->post('attr_id', 0);
            $is_screen      = request()->post('is_screen', 0);
            $res            = $goods->addGoodsSpecService($this->instance_id, $spec_name, $show_type, $is_visible, $sort, $spec_value_str, $attr_id, $is_screen);
            $this->admin_user_record('添加规格',$res,'');
            return AjaxReturn($res);
        }
        return view($this->style . 'Goods/addGoodsSpec');
    }

    /**
     * 修改规格
     *
     * @return multitype:unknown
     */
    public function updateGoodsSpec()
    {
        $goods   = new GoodsService();
        $spec_id = request()->get('spec_id', '');
        if (request()->isAjax()) {
            $spec_id        = request()->post('spec_id', '');
            $spec_name      = request()->post('spec_name', '');
            $is_visible     = request()->post('is_visible', '');
            $show_type      = request()->post('show_type', '');
            $sort           = request()->post('sort', '');
            $spec_value_str = request()->post('spec_value_str', '');
            $is_screen      = request()->post('is_screen', 0);
            $res            = $goods->updateGoodsSpecService($spec_id, $this->instance_id, $spec_name, $show_type, $is_visible, $sort, $spec_value_str, $is_screen);
            $this->admin_user_record('添加规格',$spec_id,'');
            return AjaxReturn($res);
        }
        $detail = $goods->getGoodsSpecDetail($spec_id);
        $this->assign('info', $detail);
        return view($this->style . 'Goods/updateGoodsSpec');
    }

    /**
     * 修改商品规格属性
     * 备注：编辑商品时，也用到了这个方法，公共的啊 2017年6月5日 19:39:35 王永杰
     */
    public function modifyGoodsSpecValueField()
    {
        $goods         = new GoodsService();
        $spec_value_id = request()->post("spec_value_id", '');
        $field_name    = request()->post('field_name', '');
        $field_value   = request()->post('field_value', '');
        $retval        = $goods->modifyGoodsSpecValueField($spec_value_id, $field_name, $field_value);
        $this->admin_user_record('修改商品规格属性',$spec_value_id,'');
        return AjaxReturn($retval);
    }

    /**
     * 删除商品规格
     */
    public function deleteGoodsSpec()
    {
        $spec_id = request()->post('spec_id', 0);
        $goods   = new GoodsService();
        $res     = $goods->deleteGoodsSpec($spec_id);
        $this->admin_user_record('删除商品规格',$spec_id,'');
        return AjaxReturn($res);
    }

    /**
     * 删除商品规格属性
     */
    public function deleteGoodsSpecValue()
    {
        $goods         = new GoodsService();
        $spec_id       = request()->post('spec_id', 0);
        $spec_value_id = request()->post('spec_value_id', 0);

        $res = $goods->deleteGoodsSpecValue($spec_id, $spec_value_id);
        $this->admin_user_record('删除商品规格属性',$spec_id,'');
        return AjaxReturn($res);
    }

    /**
     * 商品类型
     */
    public function attributelist()
    {
        if (request()->isAjax()) {
            $page_index        = request()->post('page_index', 1);
            $page_size         = request()->post('page_size', 0);
            $goods             = new GoodsService();
            $goodsEvaluateList = $goods->getAttributeServiceList($page_index, $page_size, '', 'sort');
            return $goodsEvaluateList;
        }
        return view($this->style . "Goods/attributelist");
    }

    /**
     * 添加一条商品属性值
     */
    public function addAttributeServiceValue()
    {
        $goods   = new GoodsService();
        $attr_id = request()->post('attr_id', '');

        $res = $goods->addAttributeValueService($attr_id, '', 1, 255, 1, '');
        $this->admin_user_record('添加商品属性值',$res,'');
        return AjaxReturn($res);
    }

    /**
     * 添加商品类型
     */
    public function addAttributeService()
    {
        $goods      = new GoodsService();
        $goodsguige = $goods->getGoodsSpecList(1, 0, '', 'sort desc');
        $this->assign('goodsguige', $goodsguige);
        if (request()->isAjax()) {
            $attr_name      = request()->post('attr_name', '');
            $is_use         = request()->post('is_visible', '');
            $sort           = request()->post('sort', '');
            $spec_id_array  = request()->post('select_box', '');
            $value_string   = request()->post('data_obj_str', '');
            $goodsAttribute = $goods->addAttributeService($attr_name, $is_use, $spec_id_array, $sort, $value_string);
            $this->admin_user_record('添加商品类型',$goodsAttribute,'');
            return AjaxReturn($goodsAttribute);
        }
        return view($this->style . 'Goods/addGoodsAttribute');
    }

    /**
     * 删除一条商品类型属性
     */
    public function deleteAttributeValue()
    {
        $goods         = new GoodsService();
        $attr_id       = request()->post('attr_id', 0);
        $attr_value_id = request()->post('attr_value_id', 0);
        $res           = $goods->deleteAttributeValueService($attr_id, $attr_value_id);
        $this->admin_user_record('删除一条商品类型属性',$attr_id,'');
        return AjaxReturn($res);
    }

    /**
     * 修改商品类型
     */
    public function updateGoodsAttribute()
    {
        $goods   = new GoodsService();
        $attr_id = request()->get('attr_id', '');
        if (request()->isAjax()) {
            $attr_id       = request()->post('attr_id', '');
            $attr_name     = request()->post('attr_name', '');
            $is_use        = request()->post('is_visible', '');
            $sort          = request()->post('sort', '');
            $spec_id_array = request()->post('select_box', '');
            $value_string  = request()->post('data_obj_str', '');
            $res           = $goods->updateAttributeService($attr_id, $attr_name, $is_use, $spec_id_array, $sort, $value_string);
            $this->admin_user_record('修改商品类型',$attr_id,'');
            return AjaxReturn($res);
        }
        $attribute_detail = $goods->getAttributeServiceDetail($attr_id);
        $this->assign('info', $attribute_detail);
        $goodsguige = $goods->getGoodsSpecList(1, 0, '', 'sort desc');
        $this->assign('goodsguige', $goodsguige);
        $this->assign('attr_id', $attr_id);
        return view($this->style . 'Goods/updateGoodsAttribute');
    }

    /**
     * 修改商品类型单个属性
     */
    public function setAttributeField()
    {
        $goods       = new GoodsService();
        $attr_id     = request()->post("id");
        $field_name  = request()->post("name");
        $field_value = request()->post("value");
        $reval       = $goods->modifyAttributeFieldService($attr_id, $field_name, $field_value);
        $this->admin_user_record('修改商品类型单个属性',$attr_id,'');
        return AjaxReturn($reval);
    }

    /**
     * 实时更新属性值
     */
    public function modifyAttributeValueService()
    {
        $goodsattribute = new GoodsService();
        $attr_value_id  = request()->post('attr_value_id');
        $field_name     = request()->post('field_name');
        $field_value    = request()->post('field_value');
        $res            = $goodsattribute->modifyAttributeValueService($attr_value_id, $field_name, $field_value);
        // 修改成功后修改商品属性表属性排序
        if ($res) {
            if ($field_name == "sort") {
                $res = $goodsattribute->updateGoodsAttributeSort($attr_value_id, $field_value, $this->instance_id);
            }
        }
        return $res;
    }

    /**
     * 删除商品类型
     */
    public function deleteAttr()
    {
        $attr_id = request()->post('attr_id');
        $goods   = new GoodsService();
        $res     = $goods->deleteAttributeService($attr_id);
        $this->admin_user_record('删除商品类型',$attr_id,'');
        return AjaxReturn($res);
    }

    /**
     * 商品评论
     */
    public function goodscomment()
    {
        if (request()->isAjax()) {
            $page_index = request()->post('page_index');
            $page_size  = request()->post('page_size');

            $search                  = request()->post('search');
            $condition['goods_name'] = array(
                'like',
                "%" . $search . "%"
            );

            $member_name  = request()->post('member_name', '');
            $start_date   = request()->post('start_date') == '' ? 0 : getTimeTurnTimeStamp(request()->post('start_date'));
            $end_date     = request()->post('end_date') == '' ? 0 : getTimeTurnTimeStamp(request()->post('end_date'));
            $explain_type = request()->post('explain_type', '');
            $is_check    = request()->post('is_check', '');
            if ($start_date != 0 && $end_date != 0) {
                $condition["addtime"] = [
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
                $condition["addtime"] = [
                    [
                        ">",
                        $start_date
                    ]
                ];
            } elseif ($start_date == 0 && $end_date != 0) {
                $condition["addtime"] = [
                    [
                        "<",
                        $end_date
                    ]
                ];
            }
            if ($explain_type != "") {
                $condition["explain_type"] = $explain_type;
            }
            if (!empty($member_name)) {
                $condition["member_name"] = array(
                    "like",
                    "%" . $member_name . "%"
                );
            }
            if ($is_check != "") {
                $condition["is_check"] = $is_check;
            }

            $goods             = new GoodsService();
            $goodsEvaluateList = $goods->getGoodsEvaluateList($page_index, $page_size, $condition, 'addtime desc');
            return $goodsEvaluateList;
        }else{
            $is_check = request()->get('is_check',0);
            $this->assign("is_check", $is_check);
            $child_menu_list = array(
                array(
                    'url' => "goods/goodscomment?is_check=0",
                    'menu_name' => "未审核",
                    "active"    => $is_check == 0 ? 1 : 0
                ),
                array(
                    'url' => "goods/goodscomment?is_check=1",
                    'menu_name' => "已通过",
                    "active"    => $is_check == 1 ? 1 : 0
                )
            );
            $this->assign('child_menu_list', $child_menu_list);
            return view($this->style . "Goods/goodsComment");
        }
    }

    //生成单品页小程序码
    public function getWxCode()
    {
        $goods_id            = request()->post('goods_id', 0);
        $url            = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . 'wxd145d8a6e951dd1b' . "&secret=" . '9e22a3ac6f4c0ccae03a2356e710d68f';
        $res            = $this->send_post($url, '');
        $AccessToken    = json_decode($res, true);
        $AccessToken    = $AccessToken['access_token'];
        $url            = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=" . $AccessToken;
        $post_data      =
            array(
                'scene' => $goods_id,
                'page' => 'pages/goods/goodsdetail/goodsdetail',
                'width' => 430,
            );
        $post_data      = json_encode($post_data);
        $data           = $this->send_post($url, $post_data);
        $result['url']  = $this->data_uri($data, 'image/png');
        $result['code'] = 1;
        return $result;
    }

    /**
     * 添加商品评价回复
     */
    public function replyEvaluateAjax()
    {
        if (request()->isAjax()) {
            $id           = request()->post('evaluate_id');
            $replyType    = request()->post('replyType');
            $replyContent = request()->post('evaluate_reply');
            $goods        = new GoodsService();
            $res          = $goods->addGoodsEvaluateReply($id, $replyContent, $replyType);
            $this->admin_user_record('添加商品评价回复',$res,'');
            return AjaxReturn($res);
        }
    }

    /**
     * 设置评价的显示状态
     */
    public function setEvaluteShowStatuAjax()
    {
        if (request()->isAjax()) {
            $id    = request()->post('evaluate_id');
            $goods = new GoodsService();
            $res   = $goods->setEvaluateShowStatu($id);
            $this->admin_user_record('设置评价的显示状态',$id,'');
            return AjaxReturn($res);
        }
    }


    /**
     * 设置审核状态
     */
    public function setEvaluteCheckAjax()
    {
        if (request()->isAjax()) {
            $id    = request()->post('evaluate_id');
            $goods = new GoodsService();
            $res   = $goods->setEvaluteCheckAjax($id);
            $this->admin_user_record('设置审核状态',$id,'');
            return AjaxReturn($res);
        }
    }


    /**
     * 设置精选状态
     */
    public function setEvaluteFirstAjax()
    {
        if (request()->isAjax()) {
            $id    = request()->post('evaluate_id');
            $goods = new GoodsService();
            $res   = $goods->setEvaluteFirstAjax($id);
            $this->admin_user_record('设置精选状态',$id,'');
            return AjaxReturn($res);
        }
    }

    /**
     * 删除评价
     */
    public function deleteEvaluateAjax()
    {
        if (request()->isAjax()) {
            $id    = request()->post('evaluate_id');
            $goods = new GoodsService();
            $res   = $goods->deleteEvaluate($id);
            $this->admin_user_record('删除评价',$id,'');
            return AjaxReturn($res);
        }
    }

    /**
     * 添加 一条商品规格属性
     * 备注：编辑商品的时候也需要添加规格值，方法不能限制死，要共用 2017年6月6日 10:13:30 王永杰
     */
    public function addGoodsSpecValue()
    {
        $goods           = new GoodsService();
        $spec_id         = request()->post("spec_id", 0); // 规格id
        $spec_value_name = request()->post("spec_value_name", ""); // 规则值
        $spec_value_data = request()->post("spec_value_data", ""); // 规格值对应的颜色值、图片路径
        $is_visible      = 1; // 是否可见，第一次添加，默认可见
        $res             = $goods->addGoodsSpecValueService($spec_id, $spec_value_name, $spec_value_data, $is_visible, '');
        return AjaxReturn($res);
    }

    /**
     * 商品规格dialog插件
     */
    public function controlDialogSku()
    {
        $attr_id = request()->get("attr_id", 0);
        $this->assign("attr_id", $attr_id);
        return view($this->style . 'Goods/controlDialogSku');
    }

    /**
     * 商品回收站列表
     */
    public function recycleList()
    {
        if (request()->isAjax()) {
            $goodservice   = new GoodsService();
            $page_index    = request()->post("page_index", 1);
            $page_size     = request()->post("page_size", PAGESIZE);
            $start_date    = request()->post('start_date') == '' ? 0 : getTimeTurnTimeStamp(request()->post('start_date'));
            $end_date      = request()->post('end_date') == '' ? 0 : getTimeTurnTimeStamp(request()->post('end_date'));
            $goods_name    = request()->post('goods_name', '');
            $category_id_1 = request()->post('category_id_1', '');
            $category_id_2 = request()->post('category_id_2', '');
            $category_id_3 = request()->post('category_id_3', '');
            if ($start_date != 0 && $end_date != 0) {
                $condition["ng.create_time"] = [
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
                $condition["ng.create_time"] = [
                    [
                        ">",
                        $start_date
                    ]
                ];
            } elseif ($start_date == 0 && $end_date != 0) {
                $condition["ng.create_time"] = [
                    [
                        "<",
                        $end_date
                    ]
                ];
            }
            if (!empty($goods_name)) {
                $condition["ng.goods_name"] = array(
                    "like",
                    "%" . $goods_name . "%"
                );
            }
            if ($category_id_3 != "") {
                $condition["ng.category_id_3"] = $category_id_3;
            } elseif ($category_id_2 != "") {
                $condition["ng.category_id_2"] = $category_id_2;
            } elseif ($category_id_1 != "") {
                $condition["ng.category_id_1"] = $category_id_1;
            }
            $condition["ng.shop_id"] = $this->instance_id;
            $result                  = $goodservice->getGoodsDeletedList($page_index, $page_size, $condition, "ng.create_time desc");
            return $result;
        } else {
            $search_info = request()->post('search_info', '');
            $this->assign("search_info", $search_info);
            // 查找一级商品分类
            $goodsCategory    = new GoodsCategory();
            $oneGoodsCategory = $goodsCategory->getGoodsCategoryListByParentId(0);
            $this->assign("oneGoodsCategory", $oneGoodsCategory);

            $child_menu_list = array(
                array(
                    'url' => "goods/goodslist",
                    'menu_name' => "商品列表",
                    "active" => 0
                ),
                array(
                    'url' => "goods/recyclelist",
                    'menu_name' => "商品回收站",
                    "active" => 1
                )
            );
            $this->assign('child_menu_list', $child_menu_list);

            return view($this->style . 'Goods/recycleList');
        }
    }

    /**
     * 回收站商品恢复
     */
    public function regainGoodsDeleted()
    {
        if (request()->isAjax()) {
            $goods_ids = request()->post('goods_ids');
            $goods     = new GoodsService();
            $res       = $goods->regainGoodsDeleted($goods_ids);
            return AjaxReturn($res);
        }
    }

    /**
     * 商品分类选择
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function dialogSelectCategory()
    {
        $category_id = request()->get("category_id", 0);
        $goodsid     = request()->get("goodsid", 0);
        $flag        = request()->get("flag", 'category');
        // 扩展分类标签id,用户回调方法
        $box_id = request()->get("box_id", '');
        // 已选择扩展分类(用于控制重复选择)
        $category_extend_id = request()->get("category_extend_id", '');
        if (!empty($category_extend_id) && $category_id != 0) {
            $category_extend_id = explode(",", $category_extend_id);
            foreach ($category_extend_id as $k => $v) {
                if ($v == $category_id) {
                    unset($category_extend_id[$k]);
                }
            }
            sort($category_extend_id);
            $category_extend_id = implode(',', $category_extend_id);
        }
        $this->assign("flag", $flag);
        $this->assign("goodsid", $goodsid);
        $this->assign("box_id", $box_id);
        $this->assign("category_extend_id", $category_extend_id);

        $goods_category = new GoodsCategory();
        $list           = $goods_category->getGoodsCategoryListByParentId(0);
        $this->assign("cateGoryList", $list);
        $category_select_ids   = "";
        $category_select_names = "";
        if ($category_id != 0) {
            $category_select_result = $goods_category->getParentCategory($category_id);
            $category_select_ids    = $category_select_result["category_ids"];
            $category_select_names  = $category_select_result["category_names"];
        }
        $this->assign("category_select_ids", $category_select_ids);
        $this->assign("category_select_names", $category_select_names);
        return view($this->style . 'Goods/dialogSelectCategory');
    }

    /**
     * 更改商品排序
     */
    public function updateGoodsSortAjax()
    {
        if (request()->isAjax()) {
            $goods_id = request()->post("goods_id", "");
            $sort     = request()->post("sort", "");
            $goods    = new GoodsService();
            $res      = $goods->updateGoodsSort($goods_id, $sort);
            $this->admin_user_record('更改商品排序',$goods_id,'');
            return AjaxReturn($res);
        }
    }

    /**
     * 更改商品品牌排序
     */
    public function updateGoodsBrandSortAjax()
    {
        if (request()->isAjax()) {
            $brand_id = request()->post("brand_id", "");
            $sort     = request()->post("sort", "");
            $goods    = new GoodsBrand();
            $res      = $goods->updateGoodsBrandSort($brand_id, $sort);
            $this->admin_user_record('更改商品品牌排序',$brand_id,'');
            return AjaxReturn($res);
        }
    }

    /**
     * 生成商品二维码
     */
    public function updateGoodsQrcode()
    {
        $goods_ids = request()->post('goods_id', '');
        $goods_ids = explode(',', $goods_ids);
        if (!empty($goods_ids) && is_array($goods_ids)) {
            foreach ($goods_ids as $v) {
                $url = __URL(Config::get('view_replace_str.APP_MAIN') . '/goods/goodsdetail?id=' . $v);
                try {
                    $pay_qrcode = getQRcode($url, 'upload/goods_qrcode', 'goods_qrcode_' . $v);
                } catch (\Exception $e) {
                    return AjaxReturn(UPLOAD_FILE_ERROR);
                }
                $goods  = new GoodsService();
                $result = $goods->goods_QRcode_make($v, $pay_qrcode);
            }
        }
        $this->admin_user_record('生成商品二维码',$goods_ids,'');
        return AjaxReturn($result);
    }

    /**
     * 查询条件下的商品分组列表
     *
     * @return unknown
     */
    public function getGoodsGroupQuery()
    {
        $goodsgroup              = new GoodsGroup();
        $text                    = request()->post("search", "");
        $condition["group_name"] = array(
            'like',
            "%{$text}%"
        );
        $list                    = $goodsgroup->getGoodsGroupQueryList($condition);
        return $list;
    }

    /**
     * 修改商品名称或促销语
     */
    public function ajaxEditGoodsNameOrIntroduction()
    {
        if (request()->isAjax()) {
            $goods      = new GoodsService();
            $goods_id   = request()->post("goods_id", "");
            $up_type    = request()->post("up_type", "");
            $up_content = request()->post("up_content", "");
            $res        = $goods->updateGoodsNameOrIntroduction($goods_id, $up_type, $up_content);
            $this->admin_user_record('修改商品名称或促销语',$goods_id,'');
            return AjaxReturn($res);
        }
    }

    /**
     * 虚拟商品类型列表
     */
    public function virtualGoodsTypeList()
    {
        if (request()->isAjax()) {

            $virtual_goods = new VirtualGoods();
            $page_index    = request()->post("page_index", 1);
            $page_size     = request()->post("page_size", PAGESIZE);
            $search_name   = request()->post("search_name", "");
            $condition     = array();
            if (!empty($search_name)) {
                $condition['virtual_goods_type_name'] = array(
                    "like",
                    "%$search_name%"
                );
            }
            $res = $virtual_goods->getVirtualGoodsTypeList($page_index, $page_size, $condition);
            return $res;
        }
        return view($this->style . 'Goods/virtualGoodsTypeList');
    }

    /**
     * 编辑虚拟商品类型
     */
    public function editVirtualGoodsType()
    {
        $virtual_goods         = new VirtualGoods();
        $virtual_goods_type_id = request()->get("virtual_goods_type_id", 0);
        if (request()->isAjax()) {
            $virtual_goods_type_id   = request()->post("virtual_goods_type_id", 0); // 虚拟商品类型id
            $virtual_goods_group_id  = request()->post("virtual_goods_group_id", ""); // 关联虚拟商品分组id
            $virtual_goods_type_name = request()->post("virtual_goods_type_name", ""); // 虚拟商品类型名称
            $validity_period         = request()->post("validity_period", 0); // 有效期/天(0表示不限制)
            $is_enabled              = request()->post("is_enabled", 1); // 是否开启
            $money                   = request()->post("money", ""); // 金额
            $config_info             = request()->post("config_info", ""); // 配置信息JSON（API接口，参数）
            $confine_use_number      = request()->post("confine_use_number", 0); // 限制使用次数
            $res                     = $virtual_goods->editVirtualGoodsType($virtual_goods_type_id, $virtual_goods_group_id, $virtual_goods_type_name, $validity_period, $is_enabled, $money, $config_info, $confine_use_number);
            $this->admin_user_record('编辑虚拟商品类型',$virtual_goods_type_id,'');
            return AjaxReturn($res);
        }
        $virtual_goods_type = $virtual_goods->getVirtualGoodsTypeById($virtual_goods_type_id);
        if (!empty($virtual_goods_type)) {
            $virtual_goods_type['config_info'] = json_decode($virtual_goods_type['config_info'], true);
        }
        $this->assign("virtual_goods_type", $virtual_goods_type);
        $this->assign("virtual_goods_type_id", $virtual_goods_type_id);

        return view($this->style . "Goods/editVirtualGoodsType");
    }

    /**
     * 删除虚拟商品类型
     */
    public function deleteVirtualGoodsType()
    {
        $virtual_goods         = new VirtualGoods();
        $virtual_goods_type_id = request()->post("virtual_goods_type_id", "");
        $res                   = $virtual_goods->deleteVirtualGoodsType($virtual_goods_type_id);
        $this->admin_user_record('删除虚拟商品类型',$virtual_goods_type_id,'');
        return AjaxReturn($res);
    }

    /**
     * 设置虚拟商品类型启用禁用
     *
     * @return boolean
     */
    public function setVirtualGoodsTypeIsEnabled()
    {
        $virtual_goods         = new VirtualGoods();
        $virtual_goods_type_id = request()->post("virtual_goods_type_id", "");
        $is_enabled            = request()->post("is_enabled", 1);
        $res                   = $virtual_goods->setVirtualGoodsTypeIsEnabled($virtual_goods_type_id, $is_enabled);
        return AjaxReturn($res);
    }

    /**
     * 获取添加活动商品列表
     */
    public function getSelectGoodslist()
    {
        $goods              = new GoodsService();
        $page_index         = request()->post("page_index", 1);
        $page_size          = request()->post("page_size", 0);
        $goods_name         = request()->post('goods_name', '');
        $material_code      = request()->post('material_code', '');
        $goods_id_array     = request()->post("goods_id_array", "");
        $type               = request()->post("type", "");
        $condition          = array();
        $condition["state"] = 1;
        if (!empty($goods_name)) {
            $condition["goods_name"] = array(
                "like",
                "%" . $goods_name . "%"
            );
        }
        if (!empty($material_code)) {
            $condition["material_code"] = $material_code;
        }
        $condition["state"]               = 1;
        $condition["point_exchange_type"] = 0;
        $condition["stock"]               = array(
            ">",
            0
        );
        $condition["goods_type"]          = 1;
        if (!empty($goods_id_array)) {
            if ($type == "select") {
                $condition["goods_id"] = array(
                    "not in",
                    $goods_id_array
                );
            } else
                if ($type == "selected") {
                    $condition["goods_id"] = array(
                        "in",
                        $goods_id_array
                    );
                }
        }
        $list = $goods->getSelectGoodsList($page_index, $page_size, $condition, "sort asc,create_time desc", "goods_id,goods_name,stock,promotion_price,price,material_code");
        return $list;
    }

    /**
     * 删除已上传的视频
     * 赵海雷
     */
    function delSelectedVideo()
    {
        $src = request()->post('src');
        $res = 1;
        if (!empty($src)) {
            $res = unlink($src);
        }

        return $res;
    }


    # 试用新品上架通知
    public function pushTrialGoodsTemplate($goods_id)
    {
        # warm_type = 9
        $template_id = getWxTemplateId('trial_pro_up');
        $goods       = \think\Db::name('ns_goods')->where([
            'goods_id' => $goods_id
        ])->find();
        # 非小样
        if ($goods['is_black_tech'] !== 3) {
            return;
        }
        # 该商品不存在
        if (!$goods) return;
        $conf = json_decode(\think\Db::name('sys_config')->where([
            'key' => 'SHOPAPPLET'
        ])->find()['value'], true);

        $appid  = $conf['appid'];
        $secret = $conf['appsecret'];

        $access_token = getAccessToken($appid, $secret);
        $select_url   = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;
        $where        = [
            'warn_type' => 9,
            'is_send' => 0,
        ];
        $templates    = \think\Db::name('ns_template_push')->where($where)->select();
        foreach ($templates as $item) {
            $openid = $item['open_id'];
            $fid    = $item['form_id'];

            $p1 = '本周黑科技体验';
            $p2 = $goods['goods_name'];
            $p3 = $goods['selling_point'];
            $p4 = '跳转到付邮试用单品';

            $page = 'pages/goods/goodsdetail/goodsdetail?goods_id=' . $goods['goods_id'] . '&goods_name=' . $goods['goods_name'];

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
      }
  },
  "color":"#ccc"
}
EOL;
            curl_post($select_url, $param);
        }
    }

    public function getGoodsFieldInfo()
    {
        $id = request()->post('id');
        if ($id) {
            $data = \think\Db::name('ns_goods')->where(['goods_id' => $id])->find();
        }
        return $data;
    }

    /**
     * @param $goods_id
     * @param $type
     * @return int|string|void
     * @throws \think\Exception
     * 修改
     */
    public function updateIsBlackTech($goods_id, $type)
    {
        if (empty($goods_id)) return;
        if ($type == '0,1') {
            $is_black_tech['is_black_tech'] = '1';     #排行榜
        } else if ($type == '1,0') {
            $is_black_tech['is_black_tech'] = '0';     #常规商品
        } else {
            return;
        }
        $data = \think\Db::name('ns_goods')->where(['goods_id' => $goods_id])->update($is_black_tech);
        $code = 1;
        return $code;
    }

    /**
     * 修改库存
     */
    public function updateGoodsStockAjax()
    {
        if (request()->isAjax()) {
            if ($this->checkStockAuth() !== 1) {
                return AjaxReturn(NO_AITHORITY,'权限不足');
             }else{
                $goods_id = request()->post("goods_id", "");
                $stock    = request()->post("stock", "");
                $goods    = new GoodsService();
                $res      = $goods->updateGoodsStock($goods_id, $stock);
                $res      = $goods->updateGoodsSkuStock($goods_id, $stock);
                $this->admin_user_record('修改库存',$goods_id,'');
                return AjaxReturn($res);
            }
        }
    }


    /**
     * @return int
     * 权限判断
     */
    public function checkStockAuth(){
        $admin_uid = $_SESSION['niu']['adminuid'];
        $group_id  = \think\Db::name('sys_user_admin')->where(['uid' => $admin_uid])->find()['group_id_array'];
        if($group_id == 2 || $admin_uid == 1){
            return 1;
        }else{
            return 2;
        }
    }

    #获取规格详情
    public function getSkuDetail(){
        $sku_id = request()->post("sku_id", "");
        if(empty($sku_id)) return;
        $sku = new NsGoodsSkuModel();
        $sku_info  = $sku->getInfo([
            "sku_id" => $sku_id
        ], "sku_id,sku_name,stock,material_code");
        return AjaxReturn(1,$sku_info);
    }

    public function testapi(){
        $arr = [
            '8436568071862',
            '8436568071930',
            '8436568071954',
            '8436568071800',
            '8436568071916',
            '8436568071831',
            '8436568071794',
            '8436568071787',
            '8436002995655',
            '8436568073507',
            '8436534712935',
            '8436568074818',
            '8436568075549',
            '8436568071848',
            '8436568071947',
            '8436568075600',
            '8436534717138',
            '8436002995747',
            '8436002996515',
            '8436534714649',
            '8436534719835',
            '8436568071794',
            '8436568071862',
            '8436568071787',
            '8436568071800',
            '8436568071954',
            '8436568071930',
            '8436568071978'
        ];

        $condition = array(
            "product_barcode" => [
                "in",
                $arr
            ],
        );

        $arr_ = [];
        $lists  = \think\Db::name('ns_goods')->where($condition)->field(['goods_id','goods_name','picture'])->order('goods_id desc')->group('product_barcode')->select();
        foreach($lists as $key=>$v){
            $sku_id  = \think\Db::name('ns_goods_sku')->where(['goods_id'=>$v['goods_id']])->find()['sku_id'];
            $arr_[$key]['sku_id'] = $sku_id;
            $arr_[$key]['goods_id'] = $v['goods_id'];
            $arr_[$key]['goods_name'] = $v['goods_name'];
            $arr_[$key]['goods_picture'] = $v['picture'];
            $arr_[$key]['sku_picture'] = $v['picture'];
        }

        foreach($arr_ as $vo){
            $data_goods   = array(
                'discount_id'   => 73,
                'goods_id'      => $vo['goods_id'],
                'sku_id'        => $vo['sku_id'],
                'n_price'       => 10000,
                'sku_num'       => 1,
                'status'        => 1,
                'goods_name'    => $vo['goods_name'],
                'sku_name'      => '',
                'goods_picture' => $vo['goods_picture'],
                'sku_picture'   => $vo['goods_picture'],
                'start_time'    => '',
                'end_time'      => ''
            );

            $data_goods_1 = array(
                'mansong_id'    => 73,
                'goods_id'      => $vo['goods_id'],
                'sku_id'        => $vo['sku_id'],
                'sku_num'       => 1,
                'goods_name'    => $vo['goods_name'],
                'sku_name'      => '',
                'goods_picture' => $vo['goods_picture'],
                'sku_picture'   => $vo['goods_picture'],
                'status'        => 1, // 状态重新设置
                'start_time'    => '',
                'end_time'      => ''
            );
            \think\Db::name('ns_promotion_mansong_goods')->insert($data_goods_1);
            \think\Db::name('ns_promotion_neigou_goods')->insert($data_goods);

        }




    }

}
