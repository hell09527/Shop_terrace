<?php
/**
 * Article.php
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
namespace app\wap\controller;

use data\service\Config;
use data\service\Goods;
use data\service\GoodsCategory;
use data\service\Member;
\think\Loader::addNamespace('data', 'data/');

/**
 * 手机端自定义模板控制器
 *
 * @author Administrator
 *        
 */
class CustomTemplate extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }

    public function customTemplateIndex()
    {
        if ($this->custom_template_is_enable == 0) {
            // 没有开启自定义模板，跳转到首页
            $this->redirect(__URL(\think\Config::get('view_replace_str.APP_MAIN') . "/Index/index"));
        }
        $id = request()->get("id", 0);
        $custom_template_info = $this->getCustomTemplate($id);
        // if (empty($custom_template_info)) {
        // 没有查询到自定义模板，跳转到首页
        // $this->redirect(__URL(\think\Config::get('view_replace_str.APP_MAIN') . "/Index/index"));
        // }
        $this->assign("custom_template", $custom_template_info);
        
        $config = new Config();
        $member = new Member();
        
        // 首页优惠券
        $coupon_list = $member->getMemberCouponTypeList($this->instance_id, $this->uid);
        $this->assign('coupon_list', $coupon_list);
        
        // 公众号配置查询
        $wchat_config = $config->getInstanceWchatConfig($this->instance_id);
        
        // 网站信息
        $web_info = $this->web_site->getWebSiteInfo();
        $is_subscribe = 0; // 标识：是否显示顶部关注 0：[隐藏]，1：[显示]
        if ($web_info["is_show_follow"] == 1) {
            // 检查是否配置过微信公众号
            if (! empty($wchat_config['value'])) {
                if (! empty($wchat_config['value']['appid']) && ! empty($wchat_config['value']['appsecret'])) {
                    // 如何判断是否关注
                    if (isWeixin()) {
                        if (! empty($this->uid)) {
                            // 检查当前用户是否关注
                            $user_sub = $this->user->checkUserIsSubscribeInstance($this->uid, $this->instance_id);
                            if ($user_sub == 0) {
                                // 未关注
                                $is_subscribe = 1;
                            }
                        }
                    }
                }
            }
        }
        $this->assign("is_subscribe", $is_subscribe);
        
        $this->assign('web_info', $web_info);
        // 公众号二维码获取
        $source_user_name = "";
        $source_img_url = "";
        $source_uid = request()->get('source_uid', '');
        if (! empty($source_uid)) {
            $_SESSION['source_uid'] = $source_uid;
            $user_info = $member->getUserInfoByUid($_SESSION['source_uid']);
            if (! empty($user_info)) {
                $source_user_name = $user_info["nick_name"];
                if (! empty($user_info["user_headimg"])) {
                    $source_img_url = $user_info["user_headimg"];
                }
            }
        }
        
        // 首页公告
        $this->assign('source_user_name', $source_user_name);
        $this->assign('source_img_url', $source_img_url);
        
        if ($id == 0) {
            return view($this->style . 'CustomTemplate/customTemplateIndex');
        } else {
            return view($this->style . 'CustomTemplate/customTemplateControl');
        }
    }

    /**
     * 获取自定义模板信息
     * 2017年8月26日 11:03:47 王永杰
     * 注：遇到自定义模块递归查询，返回的结果与原来的集合追加
     */
    public function getCustomTemplate($id = 0)
    {
        $config = new Config();
        $custom_template = array();
        if ($id === 0) {
            $template_info = $config->getDefaultWapCustomTemplate();
        } else {
            $template_info = $config->getWapCustomTemplateById($id);
        }
        $template_name = ""; // 模板名称
        if (! empty($template_info)) {
            $goods = new Goods();
            $custom_template_info = json_decode($template_info["template_data"], true);
            foreach ($custom_template_info as $k => $v) {
                $custom_template_info[$k]["style_data"] = json_decode($v["control_data"], true);
            }
            // 给数组排序
            $sort = array(
                'direction' => 'SORT_ASC', // 排序顺序标志 SORT_DESC 降序；SORT_ASC 升序
                'field' => 'sort'
            );
            $arrSort = array();
            foreach ($custom_template_info as $uniqid => $row) {
                foreach ($row as $key => $value) {
                    $arrSort[$key][$uniqid] = $value;
                }
            }
            if ($sort['direction']) {
                array_multisort($arrSort[$sort['field']], constant($sort['direction']), $custom_template_info);
            }
            foreach ($custom_template_info as $k => $v) {
                
                if ($v['control_name'] == "GoodsSearch") {
                    
                    // 商品搜索
                    $custom_template_info[$k]["style_data"]['goods_search'] = json_decode($v["style_data"]['goods_search'], true);
                } elseif ($v["control_name"] == "GoodsList") {
                    
                    // 商品列表
                    $custom_template_info[$k]["style_data"]['goods_list'] = json_decode($v["style_data"]['goods_list'], true);
                    if ($custom_template_info[$k]["style_data"]['goods_list']["goods_source"] > 0) {
                        $goods_list = $goods->getGoodsListNew(1, $custom_template_info[$k]["style_data"]['goods_list']["goods_limit_count"], [
                            "ng.category_id" => $custom_template_info[$k]["style_data"]['goods_list']["goods_source"],
                            "ng.state" => 1
                        ], "ng.sort asc,ng.create_time desc");
                        $goods_query = array();
                        if (! empty($goods_list)) {
                            $goods_query = $goods_list["data"];
                        }
                        $custom_template_info[$k]["goods_list"] = $goods_query;
                    }
                } elseif ($v["control_name"] == "ImgAd") {
                    
                    // 图片广告
                    if (trim($v["style_data"]["img_ad"]) != "") {
                        $custom_template_info[$k]["style_data"]["img_ad"] = json_decode($v["style_data"]["img_ad"], true);
                    } else {
                        $custom_template_info[$k]["style_data"]["img_ad"] = array();
                    }
                } elseif ($v["control_name"] == "NavHyBrid") {
                    
                    $custom_template_info[$k]["style_data"]["nav_hybrid"] = json_decode($v["style_data"]["nav_hybrid"], true);
                } elseif ($v["control_name"] == "GoodsClassify") {
                    
                    // 商品分类
                    if (trim($v["style_data"]["goods_classify"]) != "") {
                        $category = new GoodsCategory();
                        $category_array = json_decode($v["style_data"]["goods_classify"], true);
                        foreach ($category_array as $t => $m) {
                            $category_info = $category->getGoodsCategoryDetail($m["id"]);
                            $category_array[$t]["name"] = $category_info["short_name"];
                            $goods_list = $goods->getGoodsListNew(1, $m["show_count"], [
                                "ng.category_id" => $m["id"]
                            ], "ng.sort asc,ng.create_time desc");
                            $category_array[$t]["goods_list"] = $goods_list["data"];
                        }
                        $custom_template_info[$k]["style_data"]["goods_classify"] = $category_array;
                    } else {
                        $custom_template_info[$k]["style_data"]["goods_classify"] = array();
                    }
                } elseif ($v["control_name"] == "Footer") {
                    
                    // 底部菜单
                    if (trim($v["style_data"]["footer"]) != "") {
                        $custom_template_info[$k]["style_data"]["footer"] = json_decode($v["style_data"]["footer"], true);
                    } else {
                        $custom_template_info[$k]["style_data"]["footer"] = array();
                    }
                } elseif ($v["control_name"] == "CustomModule") {
                    
                    // 自定义模块
                    $custom_module = json_decode($v["style_data"]['custom_module'], true);
                    
                    $custom_module_list = $this->getCustomTemplate($custom_module['module_id']);
                    if (! empty($custom_module_list)) {
                        for ($i = 0; $i < count($custom_module_list['template_data']); $i ++) {
                            
                            array_push($custom_template_info, $custom_module_list['template_data'][$i]);
                        }
                    }
                } elseif ($v["control_name"] == "Coupons") {
                    
                    // 优惠券
                    $custom_template_info[$k]["style_data"]['coupons'] = json_decode($v["style_data"]['coupons'], true);
                } elseif ($v["control_name"] == "Video") {
                    
                    // 视频
                    $custom_template_info[$k]["style_data"]['video'] = json_decode($v["style_data"]['video'], true);
                } elseif ($v["control_name"] == "ShowCase") {
                    
                    // 橱窗
                    $custom_template_info[$k]["style_data"]['show_case'] = json_decode($v["style_data"]['show_case'], true);
                } elseif ($v['control_name'] == "Notice") {
                    
                    // 公告
                    $custom_template_info[$k]['style_data']['notice'] = json_decode($v['style_data']['notice'], true);
                } elseif ($v['control_name'] == "TextNavigation") {
                    
                    // 文本导航
                    $custom_template_info[$k]['style_data']['text_navigation'] = json_decode($v['style_data']['text_navigation'], true);
                } elseif ($v['control_name'] == "Title") {
                    
                    // 标题
                    $custom_template_info[$k]['style_data']['title'] = json_decode($v['style_data']['title'], true);
                } elseif ($v['control_name'] == "AuxiliaryLine") {
                    
                    // 辅助线
                    $custom_template_info[$k]['style_data']['auxiliary_line'] = json_decode($v['style_data']['auxiliary_line'], true);
                } elseif ($v['control_name'] == "AuxiliaryBlank") {
                    
                    // 辅助空白
                    $custom_template_info[$k]['style_data']['auxiliary_blank'] = json_decode($v['style_data']['auxiliary_blank'], true);
                }
            }
            $custom_template["template_name"] = $template_info["template_name"];
            $custom_template["template_data"] = $custom_template_info;
            // print_r(count($custom_template["template_data"]));
        }
        
        $member = new Member();
        if (! empty($this->uid)) {
            $coupon_list = $member->getMemberCouponTypeList($this->instance_id, $this->uid);
            $this->assign('coupon_list', $coupon_list);
        }
        return $custom_template;
    }
}