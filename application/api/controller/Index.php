<?php
/**
 * Index.php
 * Niushop商城系统 - 团队十年电商经验汇集巨献!
 * =========================================================
 * Copy right 2015-2025 山西牛酷信息科技有限公司, 保留所有权利。
 * ----------------------------------------------
 * 官方网址: http://www.niushop.com.cn
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用。
 * 任何企业和个人不允许对程序代码以任何形式任何目的再发布。
 * =========================================================
 *
 * @author  : niuteam
 * @date    : 2015.1.17
 * @version : v1.0.0.0
 */

namespace app\api\controller;

use data\service\Config;
use data\extend\WchatOauth;
use data\service\GoodsCategory;
use data\service\Platform;
use data\service\GoodsBrand;
use data\service\Goods;
use data\service\Member;
use data\service\Pintuan;
use data\service\GoodsGroup;
use data\service\Video;
use think\Session;
use think\Cache;

class Index extends BaseController
{
    /**
     * 商品楼层板块每层显示商品个数
     *
     * @var unknown
     */
    public $category_good_num = 4;

    /**
     * 商品标签板块每层显示商品个数
     *
     * @var unknown
     */
    public $recommend_goods_num = 4;

    function __construct()
    {
        parent::__construct();
    }

    // ------------------------------------------------------------
    # 首页图片列表
    public function getIndexImgList()
    {
        $input = [

            [
                'imgUrl' => 'https://static.bonnieclyde.cn/0724.jpg',
            ],

            //            [
            //                'imgUrl'   => 'https://static.bonnieclyde.cn/Homepage_04.jpg',
            //                'hasExtra' => '/member/giftPrefecture/giftPrefecture',
            //                # 跳转二级页 ..
            //            ],
            //            [
            //                'goods_id' => 254,
            //                'imgUrl' => 'https://static.bonnieclyde.cn/WechatIMG35.jpeg',
            //            ],

            [
                'imgUrl' => 'https://static.bonnieclyde.cn/19-04-banber.png',
            ],

            //            [
            //                'imgUrl' => 'https://static.bonnieclyde.cn/712-01.jpg',
            //            ],

            [
                'goods_id' => 200,
                //                'imgUrl'   => 'https://static.bonnieclyde.cn/19-04-19-1.jpg',
                'imgUrl'   => 'https://static.bonnieclyde.cn/19-05-15-01.jpg',
            ],

            [
                'goods_id' => 241,
                //                'imgUrl'   => 'https://static.bonnieclyde.cn/19-04-19-1.jpg',
                'imgUrl'   => 'https://static.bonnieclyde.cn/19-05-15-02.jpg',
            ],

            [
                'goods_id' => 253,
                //                'imgUrl'   => 'https://static.bonnieclyde.cn/19-04-19-1.jpg',
                'imgUrl'   => 'https://static.bonnieclyde.cn/19-05-15-03.jpg',
            ],

            [
                'goods_id' => 93,
                //                'imgUrl'   => 'https://static.bonnieclyde.cn/19-04-19-1.jpg',
                'imgUrl'   => 'https://static.bonnieclyde.cn/19-05-15-04.jpg',
            ],

            [
                'goods_id' => 98,
                //                'imgUrl'   => 'https://static.bonnieclyde.cn/19-04-19-1.jpg',
                'imgUrl'   => 'https://static.bonnieclyde.cn/19-05-15-05.jpg',
            ],

            [
                'goods_id' => 310,
                //                'imgUrl'   => 'https://static.bonnieclyde.cn/19-04-19-1.jpg',
                'imgUrl'   => 'https://static.bonnieclyde.cn/19-05-15-06.jpeg',
            ],

            [
                'goods_id' => 123,
                //                'imgUrl'   => 'https://static.bonnieclyde.cn/19-04-19-1.jpg',
                'imgUrl'   => 'https://static.bonnieclyde.cn/19-05-15-07.jpg',
            ],

            [
                'goods_id' => 182,
                //                'imgUrl'   => 'https://static.bonnieclyde.cn/19-04-19-1.jpg',
                'imgUrl'   => 'https://static.bonnieclyde.cn/19-05-15-08.jpg',
            ],

            [
                'goods_id' => 834,
                //                'imgUrl'   => 'https://static.bonnieclyde.cn/19-04-19-1.jpg',
                'imgUrl'   => 'https://static.bonnieclyde.cn/19-05-15-009.jpg',
//                'imgUrl'   => 'https://static.bonnieclyde.cn/19-05-15-09.jpg',
            ],

            [
                'goods_id' => 835,
                //                'imgUrl'   => 'https://static.bonnieclyde.cn/19-04-19-1.jpg',
                'imgUrl'   => 'https://static.bonnieclyde.cn/19-05-15-010.jpg',
            ],

        ];

        return $this->outout($input);
    }

    # 首页图片列表 测试
    public function getIndexImgListTest()
    {
        $input = [
            [
                'imgUrl' => 'https://static.bonnieclyde.cn/shouye-20180611_02.jpg',
            ],
            [
                'imgUrl' => 'https://static.bonnieclyde.cn/shouye-20180611_03.jpg',
            ],
            [
                'imgUrl'   => 'https://static.bonnieclyde.cn/shouye-20180611_04.jpg',
                'hasExtra' => 1,  # 跳转二级页
            ],
            [
                'imgUrl' => 'https://static.bonnieclyde.cn/shouye-20180611_05.jpg',
            ],
            [
                'goods_id' => 126,
                'imgUrl'   => 'https://static.bonnieclyde.cn/shouye-20180611_06.jpg',
            ],
            [
                'goods_id' => 127,
                'imgUrl'   => 'https://static.bonnieclyde.cn/shouye-20180611_07.jpg',
            ],
            [
                'goods_id' => 125,
                'imgUrl'   => 'https://static.bonnieclyde.cn/shouye-20180611_08.jpg',
            ],
            [
                'goods_id' => 114,
                'imgUrl'   => 'https://static.bonnieclyde.cn/shouye-20180611_09.jpg',
            ],
            [
                'goods_id' => 115,
                'imgUrl'   => 'https://static.bonnieclyde.cn/shouye-20180611_10.jpg',
            ],
            [
                'imgUrl' => 'https://static.bonnieclyde.cn/shouye-20180611_11.jpg'
            ],
            [
                'goods_id' => 93,
                'imgUrl'   => 'https://static.bonnieclyde.cn/shouye-20180611_12.jpg',
            ],
            [
                'goods_id' => 98,
                'imgUrl'   => 'https://static.bonnieclyde.cn/shouye-20180611_13.jpg',
            ],
            [
                'goods_id' => 113,
                'imgUrl'   => 'https://static.bonnieclyde.cn/shouye-20180611_14.jpg',
            ],
            [
                'goods_id' => 92,
                'imgUrl'   => 'https://static.bonnieclyde.cn/shouye-20180611_15.jpg',
            ],
            [
                'goods_id' => 117,
                'imgUrl'   => 'https://static.bonnieclyde.cn/shouye-20180611_16.jpg',
            ],
            [
                'goods_id' => 120,
                'imgUrl'   => 'https://static.bonnieclyde.cn/shouye-20180611_17.jpg',
            ],
            [
                'goods_id' => 118,
                'imgUrl'   => 'https://static.bonnieclyde.cn/shouye-20180611_18.jpg',
            ],
            [
                'goods_id' => 119,
                'imgUrl'   => 'https://static.bonnieclyde.cn/shouye-20180611_19.jpg',
            ],
            [
                'imgUrl' => 'https://static.bonnieclyde.cn/shouye-20180611_20.jpg',
            ],
            //            test
            [
                'imgUrl' => 'https://static.bonnieclyde.cn/shouye-20180611_20.jpg',
            ],
            [
                'imgUrl' => 'https://static.bonnieclyde.cn/shouye-20180611_20.jpg',
            ],
            [
                'imgUrl' => 'https://static.bonnieclyde.cn/shouye-20180611_20.jpg',
            ],
            [
                'imgUrl' => 'https://static.bonnieclyde.cn/shouye-20180611_20.jpg',
            ]
        ];

        return $this->outout($input);
    }

    # 二级页图片列表
    public function getExtraImgList()
    {
        $input = [
            [
                'imgUrl' => "http://video.ushopal.com/lihe-erjiye_02.jpg",
            ],
            [
                'imgUrl' => "http://video.ushopal.com/lihe-erjiye_03.jpg",
            ],
            [
                'goods_id' => 128,
                'imgUrl'   => "http://video.ushopal.com/lihe-erjiye_04.jpg",
            ],
            [
                'imgUrl' => "http://video.ushopal.com/lihe-erjiye_05.jpg",
            ],
            [
                'imgUrl'  => "http://video.ushopal.com/lihe-erjiye_06.jpg",
                'is_back' => 1,
            ]
        ];

        return $this->outout($input, 2);
    }

    # 二级页图片列表 测试
    public function getExtraImgListTest()
    {
        $input = [
            [
                'imgUrl' => "https://static.bonnieclyde.cn/erjiye-0811_02.jpg",
            ],
            [
                'imgUrl' => "https://static.bonnieclyde.cn/erjiye-0811_03.jpg",
            ],
            [
                'imgUrl' => "https://static.bonnieclyde.cn/erjiye-0811_04.jpg",
            ],
            [
                'goods_id' => 124,
                'imgUrl'   => "https://static.bonnieclyde.cn/erjiye-0811_05.jpg",
            ],
            [
                'imgUrl' => "https://static.bonnieclyde.cn/erjiye-0811_06.jpg",
            ],
            [
                'goods_id' => 126,
                'imgUrl'   => "https://static.bonnieclyde.cn/erjiye-0811_07.jpg",
            ],
            [
                'goods_id' => 127,
                'imgUrl'   => "https://static.bonnieclyde.cn/erjiye-0811_08.jpg",
            ],
            [
                'goods_id' => 125,
                'imgUrl'   => "https://static.bonnieclyde.cn/erjiye-0811_09.jpg",
            ],
            [
                'imgUrl' => "https://static.bonnieclyde.cn/erjiye-0811_10.jpg",
            ],
            [
                'imgUrl'  => "https://static.bonnieclyde.cn/erjiye-0811_11.jpg",
                'is_back' => 1,
            ]
        ];

        return $this->outout($input, 2);
    }

    private function outout($input, $type = 1)
    {
        \think\Config::set('default_return_type', 'json');
//        $ids = $this->getSelectIds($input);
        $ids = [];
        foreach ($input as $v) if (isset($v['goods_id'])) $ids[] = $v['goods_id'];
        $_infos = \think\Db::name('ns_goods')->field('goods_id, goods_name,material_code,source_type')
            ->where('goods_id', 'in', $ids)->select();
        foreach ($_infos as $v) $infos[$v['goods_id']] = $v;

        $imgList = [];
        foreach ($input as $k => $v) {
            $imgList[$k] = [
                'materialCode' => isset($v['goods_id']) && $v['goods_id'] ? $infos[$v["goods_id"]]['material_code'] : '',
                'sourceType'   => isset($v['goods_id']) && $v['goods_id'] ? $infos[$v["goods_id"]]['source_type'] : '',
                'goodsId'      => $v['goods_id'] ?: '',
                'imgUrl'       => $v['imgUrl'],
                'hasTarget'    => isset($v['goods_id']) && $v['goods_id'] ? 1 : 0,
                'hasExtra'     => isset($v['hasExtra']) ? $v['hasExtra'] : 0,
                'goodsTitle'   => isset($v['goods_id']) && $v['goods_id'] ? $infos[$v["goods_id"]]['goods_name'] : ''
            ];
            if ($type == 2) {
                $imgList[$k]['isBack'] = isset($v['is_back']) && $v['is_back'] == 1 ? 1 : 0;
            }
        }

        return $imgList;
    }
    // ------------------------------------------------------------

    /**
     * 查询首页数据
     */
    public function getIndexData()
    {
        $title = "首页广告微展示,adv_index:首页轮播广告位,adv_new:首页新品推荐下广告位,adv_brand:首页商品推荐广告位";
        // 首页公告
        $platform = new Platform();
        $notice   = $platform->getNoticeList(1, '', [
            "shop_id" => $this->instance_id
        ], "sort");

        // 首页楼层
        $good_category     = new GoodsCategory();
        $top10Icon         = $this->top10Icon();
        $block_list        = $good_category->getGoodsCategoryBlockQuery(0, 4);
        $top_list          = $good_category->getGoodsTopQuery(0);                                   //黑科技排行榜
        $experience_list   = $good_category->getGoodsExperienceQuery(0);                            //黑科技体验专区
        $small_sample_list = $good_category->getGoodsSmallSampleQuery(0);                           //小样
        $four_list         = $good_category->getGoodsFourQuery(0, 4, 'ng.sales desc');              //所有产品销量前四
        $top_goods_list    = $good_category->getIndexGoodsQuery(0, 10, 'ng.sort asc');              //top10商品列表
        $index_goods_list  = $good_category->getIndexGoodsQuery(0, 300, 'ng.create_time desc');     //首页底部商品列表
        $new_pro           = $this->newPro();                                                       //首页新品推荐
        $goods_category_first = $good_category->getGoodsCategoryFirst();                            //获取首页商品分类楼层

        // 拼团推荐
        if ('IS_SUPPORT_PINTUAN' == 1) {
            $pintuan                          = new Pintuan();
            $pintuan_condition["npg.is_open"] = 1;
            $pintuan_condition["npg.is_show"] = 1;
            $pintuan_list                     = $pintuan->getTuangouGoodsList(1, 5, $pintuan_condition, 'npg.create_time desc');
        } else {
            $pintuan_list = [];
        }

        // 标签板块
        $goods_platform      = new Platform();
        $goods_platform_list = $goods_platform->getRecommendGoodsList(0, 4);

        // 获取当前时间
        $current_time = $this->getCurrentTime();

        // 限时折扣列表
        $goods                 = new Goods();
        $condition['status']   = 1;
        $condition['ng.state'] = 1;
        $discount_list         = $goods->getDiscountGoodsList(1, 2, $condition, 'end_time');
        if (!empty($discount_list['data'])) {
            foreach ($discount_list['data'] as $k => $v) {
                $v['discount'] = str_replace('.00', '', $v['discount']);
            }
        }

        // 首页轮播广告位
        $platform      = new Platform();
        $plat_adv_list = $platform->getPlatformAdvPositionDetail(1105);
        $base_url      = "https://" . $_SERVER['SERVER_NAME'];
        if (!empty($plat_adv_list)) {
            foreach ($plat_adv_list["adv_list"] as $k => $v) {
                if (strstr($plat_adv_list["adv_list"][$k]["adv_image"], 'https://') == false &&
                    strstr($plat_adv_list["adv_list"][$k]["adv_image"], 'http://') == false
                ) {
                    $url                                        = $base_url . "/" . $plat_adv_list["adv_list"][$k]["adv_image"];
                    $plat_adv_list["adv_list"][$k]["adv_image"] = $url;
                }
            }
        }

        // 首页优惠券
        $member      = new Member();
        $coupon_list = $member->getMemberCouponTypeList($this->instance_id, $this->uid);

        // 首页新品推荐下方广告位
        $index_adv_one = $platform->getPlatformAdvPositionDetail(1188);

        // 首页品牌推荐下方广告位
        $index_adv_two = $platform->getPlatformAdvPositionDetail(1189);

        // 分类广告位
        $adv_category = $platform->getPlatformAdvPositionDetail(6667);

        // 首页二级轮播广告位
        $adv_index_two = $platform->getPlatformAdvPositionDetail(6668);

        //首页视频
        $videoService = new Video();
        $video_index = $videoService->getVideoDetail();
        $data          = [
            'adv_index' => $plat_adv_list,
            'adv_new' => $index_adv_one,
            'adv_brand' => $index_adv_two,
            'adv_category' => $adv_category,
            'adv_index_two'=> $adv_index_two,
            'video_index' => $video_index
        ];

        $result = [
            "notice"              => $notice,
            "block_list"          => $block_list,
            "top_list"            => $top_list,
            "experience_list"     => $experience_list,
            "small_sample_list"   => $small_sample_list,
            "four_list"           => $four_list,
            "goods_platform_list" => $goods_platform_list,
            "adv_list"            => $data,
            "coupon_list"         => $coupon_list,
            "discount_list"       => $discount_list,
            "pintuan_list"        => $pintuan_list,
            "current_time"        => $current_time,
            "top_goods_list"      => $top_goods_list,
            "index_goods_list"    => $index_goods_list,
            "icon"                => $top10Icon,
            "new_pro"             => $new_pro,
            "goods_category_first"      =>$goods_category_first
        ];

        return $this->outMessage("首页数据", $result);
    }

    /**
     * 查询首页轮播图，APP用
     * 创建时间：2018年3月20日14:34:27
     *
     * @return Ambigous <\think\response\Json, string>
     */
    public function getHomePageShufflingFigureData()
    {
        // 轮播图，商品一级分类5个，商品标签
        // 首页轮播图
        $platform      = new Platform();
        $plat_adv_list = $platform->getPlatformAdvPositionDetail(1105);
        if (!empty($plat_adv_list)) {
            foreach ($plat_adv_list["adv_list"] as $k => $v) {
                if (strpos($plat_adv_list["adv_list"][$k]["adv_image"], "http") === false) {
                    $plat_adv_list["adv_list"][$k]["adv_image"] = getBaseUrl() . "/" . $plat_adv_list["adv_list"][$k]["adv_image"];
                }
            }
        }

        return $this->outMessage("APP首页轮播图", $plat_adv_list);
    }

    /**
     * 查询首页一级商品分类，APP用，限定5个
     * 创建时间：2018年3月20日14:37:53
     *
     * @return Ambigous <\think\response\Json, string>
     */
    public function getHomePageGoodsCategoryList()
    {
        $goods_category = new GoodsCategory();
        $res            = $goods_category->getGoodsCategoryList(1, 5, [
            'pid'          => 0,
            'category_pic' => [
                '<>',
                ''
            ],
            'is_visible'   => 1
        ], "sort asc", "category_id,category_name,short_name,category_pic");
        if (!empty($res['data'])) {
            foreach ($res['data'] as $k => $v) {
                if (!empty($res['data'][$k]['category_pic'])) {
                    if (strpos($res['data'][$k]['category_pic'], "http") === false) {
                        $res['data'][$k]['category_pic'] = getBaseUrl() . "/" . $res['data'][$k]['category_pic'];
                    }
                }
            }
        }

        return $this->outMessage("APP首页一级商品分类列表", $res);
    }

    /**
     * 查询首页商品标签列表，APP用
     * 创建时间：2018年3月20日15:10:10
     *
     * @return Ambigous <\think\response\Json, string>
     */
    public function getHomePageGoodsGroupList()
    {
        $goods_group             = new GoodsGroup();
        $page_index              = request()->post("page_index", 1);
        $page_size               = request()->post("page_size", PAGESIZE);
        $condition               = [];
        $condition['is_visible'] = 1;
        $condition['group_pic']  = [
            '<>',
            ''
        ];
        $res                     = $goods_group->getGoodsGroupList($page_index, $page_size, $condition, "sort asc", $field = 'group_id,group_name,group_pic,group_dec');
        if (!empty($res['data'])) {
            foreach ($res['data'] as $k => $v) {
                if (!empty($res['data'][$k]['group_pic'])) {
                    if (strpos($res['data'][$k]['group_pic'], "http") === false) {
                        $res['data'][$k]['group_pic'] = getBaseUrl() . "/" . $res['data'][$k]['group_pic'];
                    }
                }
            }
        }

        return $this->outMessage("APP首页商品标签列表", $res);
    }

    /**
     * 根据商品标签id查询商品标签信息以及旗下的商品列表实体
     * 创建时间：2018年3月21日15:04:53
     */
    public function getGoodsListByGoodsGroupId()
    {
        $goods      = new Goods();
        $group_id   = request()->post("group_id", 1);
        $page_index = request()->post("page_index", 1);
        $page_size  = request()->post("page_size", PAGESIZE);
        $res        = $goods->getGroupGoodsListForApp($page_index, $page_size, $group_id, "goods_id,goods_name,introduction,picture,group_id_array,promotion_price");

        return $this->outMessage("APP指定商品标签下的商品列表", $res);
    }

    /**
     * 得到当前时间戳的毫秒数
     *
     * @return number
     */
    public function getCurrentTime()
    {
        $time = time();
        $time = $time * 1000;

        return $time;
    }

    /**
     * 获取分享相关票据
     */
    public function getShareTicket($url)
    {
        $title     = "获取微信票据";
        $config    = new Config();
        $auth_info = $config->getInstanceWchatConfig(0);
        // 获取票据
        if (!empty($auth_info['value']['appid'])) {
            // 针对单店版获取微信票据
            $wexin_auth                 = new WchatOauth();
            $signPackage['appId']       = $auth_info['value']['appid'];
            $signPackage['jsTimesTamp'] = time();
            $signPackage['jsNonceStr']  = $wexin_auth->get_nonce_str();
            $jsapi_ticket               = $wexin_auth->jsapi_ticket();
            $signPackage['ticket']      = $jsapi_ticket;
            $Parameters                 = "jsapi_ticket=" . $signPackage['ticket'] . "&noncestr=" . $signPackage['jsNonceStr'] . "&timestamp=" . $signPackage['jsTimesTamp'] . "&url=" . $url;
            $signPackage['jsSignature'] = sha1($Parameters);

            return $this->outMessage($title, $signPackage);
        } else {
            $signPackage = [
                'appId'       => '',
                'jsTimesTamp' => '',
                'jsNonceStr'  => '',
                'ticket'      => '',
                'jsSignature' => ''
            ];

            return $this->outMessage($title, $signPackage, '-9001', "当前微信没有配置!");
        }
    }

    /**
     * 获取首页相关推荐商品
     */
    public function getIndexReconmmendGoods()
    {
        $title = "获取首页相关推荐商品,goods_category_block:首页商品分类楼层,goods_platform_recommend:首页推荐商品列表,goods_brand_list:首页品牌相关列表，显示6个,current_time:当前时间,goods_hot_list:首页商城热卖,goods_recommend_list:首页商城推荐商品列表,goods_discount_list:首页限时周口商品列表";
        // 首页商品分类楼层
        $shop_id       = 0;
        $good_category = new GoodsCategory();
        $block_list    = $good_category->getGoodsCategoryBlockQuery($shop_id, 4);

        // 首页新品推荐列表
        $goods_platform      = new Platform();
        $goods_platform_list = $goods_platform->getRecommendGoodsList($shop_id, 4);

        // 品牌列表
        $goods_brand      = new GoodsBrand();
        $goods_brand_list = $goods_brand->getGoodsBrandList(1, 6, '', 'sort');

        // 限时折扣列表
        $goods                 = new Goods();
        $condition['status']   = 1;
        $condition['ng.state'] = 1;
        $discount_list         = $goods->getDiscountGoodsList(1, 6, $condition, 'end_time');

        foreach ($discount_list['data'] as $k => $v) {
            $v['discount'] = str_replace('.00', '', $v['discount']);
        }
        // 获取当前时间
        $current_time = $this->getCurrentTime();

        // 首页商城热卖
        $val['is_hot']  = 1;
        $goods_hot_list = $goods_platform->getPlatformGoodsList(1, 0, $val);

        // 首页商城推荐
        $val1['is_recommend'] = 1;
        $goods_recommend_list = $goods_platform->getPlatformGoodsList(1, 0, $val1);
        $data                 = [
            'goods_category_block'     => $block_list,
            'goods_platform_recommend' => $goods_platform_list,
            'goods_brand_list'         => $goods_brand_list['data'],
            'goods_discount_list'      => $discount_list['data'],
            'current_time'             => $current_time,
            'goods_hot_list'           => $goods_hot_list['data'],
            'goods_recommend_list'     => $goods_recommend_list['data'],
            'is_support_pintuan'       => 'IS_SUPPORT_PINTUAN'
        ];

        return $this->outMessage($title, $data);
    }

    /**
     * 获取限时折扣相关数据
     */
    public function getDiscountData()
    {
        $title = "获取限时折扣相关数据,discount_adv:限时折扣广告位,goods_category_list:限时折扣需要查询一级分类,current_time:获取当前时间";
        // 限时折扣广告位
        $platform      = new Platform();
        $discounts_adv = $platform->getPlatformAdvPositionDetail(1163);
        // 限时折扣商品一级分类数据
        $goods_category        = new GoodsCategory();
        $goods_category_list_1 = $goods_category->getGoodsCategoryList(1, 0, [
            "is_visible" => 1,
            "level"      => 1
        ]);
        $current_time          = time() * 1000;
        $data                  = [
            'discount_adv'        => $discounts_adv,
            'goods_category_list' => $goods_category_list_1,
            'current_time'        => $current_time
        ];

        return $this->outMessage($title, $data);
    }

    /**
     * 获取限时折扣页面商品数据
     */
    public function getDiscountGoods()
    {
        $title = "获取限时折扣的商品列表，需要必填参数对应商品分类category_id";
        // 对应商品分类id
        $category_id = request()->post('category_id', '0');
        // 对应分页
        $page_index            = request()->post("page", 1);
        $goods                 = new Goods();
        $condition['status']   = 1;
        $condition['ng.state'] = 1;
        if (!empty($category_id)) {
            $condition['category_id_1'] = $category_id;
        }
        $discount_list = $goods->getDiscountGoodsList($page_index, PAGESIZE, $condition, "ng.sort desc,ng.goods_id desc");
        $sort          = array();
        foreach ($discount_list['data'] as $k => $v) {
            $v['discount']        = str_replace('.00', '', $v['discount']);
            $v['promotion_price'] = str_replace('.00', '', $v['promotion_price']);
            $v['price']           = str_replace('.00', '', $v['price']);
            $sort[]               = $v['discount_goods_id'];
        }
        array_multisort($sort, SORT_DESC, $discount_list['data']);
        return $this->outMessage($title, $discount_list);
    }

    /**
     * 公告详情
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function noticeContent()
    {
        $title          = '公告详情';
        $notice_id      = request()->post('id', '');
        $goods_platform = new Platform();
        $notice_info    = $goods_platform->getNoticeDetail($notice_id);

        if (empty($notice_info)) {
            return $this->outMessage($title, '', -50, '未获取到公告信息');
        }

        // 上一篇
        $prev_info = $goods_platform->getNoticeList(1, 1, [
            "id" => [
                "<",
                $notice_id
            ]
        ], "id desc");

        // 下一篇
        $next_info = $goods_platform->getNoticeList(1, 1, [
            "id" => [
                ">",
                $notice_id
            ]
        ], "id asc");

        $prev_info = [];
        $next_info = [];

        if (!empty($prev_info['data']) && !empty($prev_info['data'][0]) && !empty($prev_info['data'][0]['id'])) {
            unset($prev_info['data'][0]['notice_content']);
            unset($next_info['data'][0]['notice_content']);
        }
        $data = [
            'notice_info' => $notice_info,
            'prev_info'   => $prev_info['data'][0],
            'next_info'   => $next_info['data'][0]
        ];

        return $this->outMessage($title, $data);
    }

    /**
     * 公告列表
     */
    public function noticeList()
    {
        $title          = '公告列表';
        $page           = request()->post("page", 1);
        $goods_platform = new Platform();
        $article_list   = $goods_platform->getNoticeList($page, 0, '', 'sort desc');

        return $this->outMessage($title, $article_list);
    }

    /**
     * 获取网址前缀，APP用
     * 创建时间：2018年3月27日16:57:49
     *
     * @return string
     */
    public function getSitePrefix()
    {
        return getBaseUrl();
    }

    /**
     * 获取验证码
     */
    public function getVertification()
    {
        $title = '获取验证码';
        $key   = request()->post('key', '-504*504');
        $key   = md5('@' . $key . '*');
        $code  = '';
        for ($i = 0; $i < 4; $i++) {
            $code .= (int)rand(0, 9);
        }
        Cache::set($key, $code, 60);

        return $this->outMessage($title, $code);
    }



    ####### top10 信息列表 #######

    /**
     * @return \think\response\Json
     * 封面图片暂时写死   需要更换
     */
    public function top10Icon()
    {
        $icon = 'https://static.bonnieclyde.cn/WechatIMG11.jpeg';
        return $icon;
    }

    /**
     * @return \think\response\Json
     * 详情图片暂时写死   需要更换
     */
    public function top10DetailIcon()
    {
        $icon = 'https://static.bonnieclyde.cn/shouye-20180611_20.jpg';
        return $icon;
    }

    /**
     * @return \think\response\Json
     * top详情
     */
    public function top10Detail()
    {
        $good_category = new GoodsCategory();
        $list['data']  = $good_category->getIndexGoodsQuery(0, 10, 'ng.sort asc');  //top10商品列表
        $list['icon']  = $this->top10DetailIcon();
        return $this->outMessage('热门活动数据详情', $list);
    }

    /**
     * @return \think\response\Json
     * 首页新品推荐数据
     */
    public function newPro()
    {
        $goods = new Goods();
        $res   = $goods->getGoodsQueryLimit([
            'ng.is_black_tech' => 4,
            'ng.state'         => 1
        ], "ng.goods_id,ng.new_pic,ng.goods_name,ng.introduction,ng.material_code,ng.source_type", 1000);
        return $res;
    }

    /**
     * @return \think\response\Json
     * 分类列表
     */
    public function categoryLists()
    {
        $category_list = \think\Db::name('ns_goods_category')->where(['is_visible' => 1, 'pid' => 0])->order('sort asc')->select();
        return $this->outMessage('品牌分类列表', $category_list);
    }

    /**
     * 分类 品牌 商品 融合
     */
    public function branchPro()
    {
        $category_id = request()->post('category_id', 0);
        $page_index  = request()->post("page_index", 1);
        $search_text = request()->post("search_text", '');
        $goods       = new Goods();

        if (empty($category_id) || $category_id == 0) {
            $condition = array(
                "ng.state"         => 1,
                "ng.goods_type"    => 1
            );

            $condition['ngc.is_visible'] = 1;
            if (!empty($search_text)) {
                $condition["ng.goods_name"] = array(
                    'like',
                    '%' . $search_text . '%'
                );
            }
            # ,ng.price asc
            $pro = $goods->getGoodsList($page_index, 10, $condition, 'ng.sales desc');
        } else {
            $this->userClick($category_id);
            $this->activityClick($category_id);
            $category        = \think\Db::name('ns_goods_category')->where(['category_id' => $category_id])->find();
//            $category_1_list = \think\Db::name('ns_goods_category')->where(['pid' => $category_id, 'is_visible' => 1])->order('sort asc')->select();
//            $_ids            = '';
//
//            foreach ($category_1_list as $v) {
//                $_ids .= $v['category_id'] . ',';
//            }
//            $_ids         = rtrim($_ids, ',');
            $condition    = array(
                'ng.category_id' => array(
                    'eq',
                    $category_id
                ),
                'ng.state'         => array(
                    'in',
                    1
                ),
                'ng.goods_type'    => array(
                    'not in',
                    2
                )
            );

//            $condition['ngc.is_visible'] = 1;
            $category_pic  = $category['category_pic'];
            $category_name = $category['category_name'];

            if (!empty($search_text)) {
                $condition["ng.goods_name"] = array(
                    'like',
                    '%' . $search_text . '%'
                );
            }
            # ,ng.price asc
            $pro = $goods->getGoodsList($page_index, 10, $condition, "ng.sales desc");
        }

        $list = [
            'category_pic'  => $category_pic,
            'category_name' => $category_name,
            'pro'           => $pro
        ];
        return $this->outMessage('品牌分类数据', $list);
    }

    /**
     * @return \think\response\Json
     * 商品品牌列表
     */
    public function getGoodsBrandListRecommend()
    {
        $title       = "商品品牌列表";
        $goods_brand = new GoodsBrand();
        $list        = $goods_brand->getGoodsBrandList(1, 0, ['brand_recommend' => 1], 'sort asc', 'brand_id,brand_name,brand_ads,brand_pic,is_show,sort');
        $list['pic'] = 'https://static.bonnieclyde.cn/IMG1.png';
        return $this->outMessage($title, $list);
    }

    /**
     * @return \think\response\Json
     * 品牌页搜索商品
     */
    public function searchBranchPro()
    {
        $goods_name = request()->post('goods_name', '0');
        if (!empty($goods_name)) {
            $condition["goods_name"] = array(
                'like',
                '%' . $goods_name . '%'
            );
        }
        $condition['state'] = 1;
        $goods_list         = \think\Db::name('ns_goods')->where($condition)->select();
        foreach ($goods_list as $key => $v) {
            $pic                         = \think\Db::name('sys_album_picture')->where(['pic_id' => $v['picture']])->find();
            $goods_list[$key]['picture'] = $pic['pic_cover'];
        }
        return $this->outMessage('商品详情数据', $goods_list);
    }

    /**
     * @param $category_id
     * @throws \think\Exception
     * 点击量
     */
    public function activityClick($category_id)
    {
        if (empty($category_id)) return;
        $info  = \think\Db::name('ns_goods_category')->where(['category_id' => $category_id])->find();
        $click = $info['click'] + 1;
        \think\Db::name('ns_goods_category')->where([
            'category_id' => $category_id,
        ])->update(['click' => $click]);
    }


    /**
     * @param $category_id
     * @throws \think\Exception
     * 分类用户访问逻辑
     */
    public function userClick($category_id)
    {
        $record_info   = \think\Db::name('bc_click_record')->where(['uid' => $this->uid, 'type' => 3, 'click_id' => $category_id])->order('click_time desc')->limit(1)->find();
        $user_info     = \think\Db::name('sys_user')->where(['uid' => $this->uid])->find();
        $category_info = \think\Db::name('ns_goods_category')->where(['category_id' => $category_id])->find();
        if (empty($user_info)) return;
        $record_res = [
            'uid'           => $this->uid,
            'last_login_ip' => $user_info['last_login_ip'],
            'click_id'      => $category_id,
            'type'          => 3,
            'click_time'    => time(),
        ];
        if (empty($record_info)) {
            \think\Db::name('bc_click_record')->insert($record_res);
            $user_click_num = $category_info['user_click'] + 1;
            \think\Db::name('ns_goods_category')->where([
                'category_id' => $category_id,
            ])->update(['user_click' => $user_click_num]);
        } else {
            $now_date  = date('Y-m-d');
            $last_date = date('Y-m-d', $record_info['click_time']);
            if ($now_date !== $last_date) {
                $user_click_num = $category_info['user_click'] + 1;
                \think\Db::name('ns_goods_category')->where([
                    'category_id' => $category_id,
                ])->update(['user_click' => $user_click_num]);
                \think\Db::name('bc_click_record')->insert($record_res);
            }
        }
    }



    public function getIndexPro(){
        $page_index       = request()->post("page_index", 1);
        $page_size        = request()->post("page_size", PAGESIZE);
        $good_category    = new GoodsCategory();
        $index_goods_list = $good_category->getIndexGoodsQueryPage($page_index, $page_size, 'ng.sales desc');      //首页底部商品列表
        return $this->outMessage('商品列表数据', $index_goods_list);


    }


    public function addgoods(){
        $condition['state']        = 1;
        $condition['category_id']  = [
            'not in',
            [33,34,37,38]
        ];
        $goods_data = \think\Db::name('ns_goods')->where($condition)->select();

        foreach ($goods_data as $v){
            $sku_info = \think\Db::name('ns_goods_sku')->where(['goods_id' => $v['goods_id']])->find();
            $data = [
              'mansong_id'    => '77',
              'goods_id'      => $v['goods_id'],
              'sku_id'        => empty($sku_info['sku_id']) ? '' : $sku_info['sku_id'],
              'goods_name'    => $v['goods_name'],
              'sku_name'      => '',
              'goods_picture' => empty($v['picture']) ? '' : $v['picture'],
              'sku_picture'   => '',
              'status'        => 0,
              'start_time'    => '',
              'end_time'      => '',
              'sku_num'       => 100,
              'use_num'       => 0,
            ];

            \think\Db::name('ns_promotion_mansong_goods')->insert($data);
        }

    }
}
