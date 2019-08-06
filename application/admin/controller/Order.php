<?php
/**
 * Order.php
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

namespace app\admin\controller;

use data\extend\upgrade\Http;
use data\model\NsOrderGoodsModel;
use data\service\Address;
use data\service\Address as AddressService;
use data\service\Config;
use data\service\Express as ExpressService;
use data\service\Order\OrderGoods;
use data\service\Order\OrderStatus;
use data\service\Order as OrderService;
use data\service\Pay\AliPay;
use data\service\Pay\WeiXinPay;
use data\service\Store;
use data\service\Member as MemberService;
use data\model\NsOrderModel;
use Qiniu\Auth;
use Qiniu\Cdn\CdnManager;
use Qiniu\Storage\UploadManager;
use data\model\BcOrderOfflinePayModel;

/**
 * 订单控制器
 *
 * @author Administrator
 *
 */
class Order extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }

    //导入已购订单
    public function orderDataImport()
    {
        $order_status = request()->post('order_status', '');
        $order_ids    = request()->post("order_ids", '');
        if ($order_status == '' || $order_status == 0 || $order_status == 5) {
            $condition['order_status'] = 1;
        } else {
            $condition['order_status'] = $order_status;
        }

        if ($order_ids != "") {
            $condition["order_id"] = [
                "in",
                $order_ids
            ];
        }

        $condition['pay_status'] = 2; //已支付
        $condition['order_type'] = 1; // 商品订单
        $condition['is_deleted'] = 0; // 未删除订单
        $order_model             = new NsOrderModel();
        $orderList               = $order_model->field('out_trade_no')->where($condition)->select();
        $order_service           = new OrderService();
        foreach ($orderList as $val) {
            $result = $order_service->orderDataImport($val['out_trade_no']);
        }
        return $result;
    }

    //删除导入已购订单
    public function deteleOrderDataImport()
    {
        $order_status = request()->post('order_status', '');
        $order_ids    = request()->post("order_ids", '');
        if ($order_status != '') {
            $condition['order_status'] = $order_status;
        }
        if ($order_ids != "") {
            $condition["order_id"] = [
                "in",
                $order_ids
            ];
        }
        $condition['pay_status'] = 2; //已支付
        $condition['order_type'] = 1; // 商品订单
        $condition['is_deleted'] = 0; // 未删除订单
        $order_service           = new OrderService();
        $result                  = $order_service->deteleOrderDataImport($condition);
        return $result;
    }

    /**
     * 商品订单列表
     */
    public function orderList()
    {
        if (request()->isAjax()) {
            $page_index                 = request()->post('page_index', 1);
            $page_size                  = request()->post('page_size', PAGESIZE);
            $start_date                 = request()->post('start_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('start_date'));
            $end_date                   = request()->post('end_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('end_date'));
            $user_name                  = request()->post('user_name', '');
            $order_no                   = request()->post('order_no', '');
            $order_status               = request()->post('order_status', '');
            $receiver_mobile            = request()->post('receiver_mobile', '');
            $is_inside                  = request()->post('is_inside', '');
            $source_branch              = request()->post('source_branch', '');
            $source_distribution        = request()->post('source_distribution', '');
            $traffic_acquisition_source = request()->post('traffic_acquisition_source', '');
            $payment_type               = request()->post('payment_type', '');
            $condition['order_type']    = [
                "in",
                "1,3"
            ]; // 订单类型
            $condition['is_deleted']    = 0; // 未删除订单
            if ($start_date != 0 && $end_date != 0) {
                $condition["create_time"] = [
                    [
                        ">",
                        $start_date
                    ],
                    [
                        "<",
                        $end_date
                    ]
                ];
            } else if ($start_date != 0 && $end_date == 0) {
                $condition["create_time"] = [
                    [
                        ">",
                        $start_date
                    ]
                ];
            } else if ($start_date == 0 && $end_date != 0) {
                $condition["create_time"] = [
                    [
                        "<",
                        $end_date
                    ]
                ];
            }
            if ($order_status != '') {
                // $order_status 1 待发货
//                if ($order_status == 1) {
//                    // 订单状态为待发货实际为已经支付未完成还未发货的订单
//                    $condition['shipping_status'] = 0; // 0 待发货
//                    $condition['pay_status']      = 2; // 2 已支付
//                    $condition['order_status']    = array(
//                        'neq',
//                        4
//                    ); // 4 已完成
//                    $condition['order_status']    = array(
//                        'neq',
//                        5
//                    ); // 5 关闭订单
//                } else
                $condition['order_status'] = $order_status;
            }
            if ($payment_type != '') {
                $condition['payment_type'] = $payment_type;
            }
            if (!empty($user_name)) {
                $condition['receiver_name'] = $user_name;
            }
            if (!empty($order_no)) {
                $condition['order_no'] = $order_no;
            }
            if (!empty($receiver_mobile)) {
                $condition['receiver_mobile'] = $receiver_mobile;
            }
            if ($is_inside != '') {
                $condition['is_inside'] = $is_inside;
            }
            if ($source_branch != '') {
                $condition['source_branch'] = $source_branch;
            }
            if (!empty($source_distribution)) {
                $condition['source_distribution'] = $source_distribution;
            }
            $condition['traffic_acquisition_source'] = [
                'like',
                '%' . $traffic_acquisition_source . '%'
            ];
            $condition['shop_id']                    = $this->instance_id;
            $order_service                           = new OrderService();
            $list                                    = $order_service->getOrderList($page_index, $page_size, $condition, 'create_time desc');

            return $list;
        } else {
            $status = request()->get('status', '');
            $this->assign("status", $status);
            $all_status        = OrderStatus::getOrderCommonStatus();
            $child_menu_list   = [];
            $child_menu_list[] = [
                'url'       => "Order/orderList",
                'menu_name' => '全部',
                "active"    => $status == '' ? 1 : 0
            ];
            foreach ($all_status as $k => $v) {
                // 针对发货与提货状态名称进行特殊修改
                /*
                 * if($v['status_id'] == 1)
                 * {
                 * $status_name = '待发货/待提货';
                 * }elseif($v['status_id'] == 3){
                 * $status_name = '已收货/已提货';
                 * }else{
                 * $status_name = $v['status_name'];
                 * }
                 */
                $child_menu_list[] = [
                    'url'       => "order/orderlist?status=" . $v['status_id'],
                    'menu_name' => $v['status_name'],
                    "active"    => $status == $v['status_id'] ? 1 : 0
                ];
            }
            $this->assign('child_menu_list', $child_menu_list);
            // 获取物流公司
            $express     = new ExpressService();
            $expressList = $express->expressCompanyQuery();
            $this->assign('expressList', $expressList);

            //来源网点列表
            $store     = new Store();
            $storeList = $store->getStore([], 'store_id, store_code,store_name', 'store_id');
            $this->assign('storeList', $storeList);

            //会员来源列表
            $member                        = new MemberService();
            $condition["distributor_type"] = [
                [
                    ">",
                    0
                ]
            ];
            $distributorList               = $member->memberList($condition, 'uid, real_name', 'uid');
            $this->assign('distributorList', $distributorList);
            return view($this->style . "Order/orderList");
        }
    }

    public function orderLists()
    {
        if (request()->isAjax()) {
            $page_index      = request()->post('page_index', 1);
            $page_size       = request()->post('page_size', PAGESIZE);
            $start_date      = request()->post('start_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('start_date'));
            $end_date        = request()->post('end_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('end_date'));
            $user_name       = request()->post('user_name', '');
            $order_no        = request()->post('order_no', '');
            $order_status    = request()->post('order_status', '');
            $receiver_mobile = request()->post('receiver_mobile', '');
            $is_inside       = request()->post('is_inside', '');
            $source_branch   = request()->post('source_branch', '');
//            $source_distribution        = request()->post('source_distribution', '');
            $traffic_acquisition_source = request()->post('traffic_acquisition_source', '');
            $payment_type               = request()->post('payment_type', '');
//            $search_text                = request()->post('search_text', '');
            $condition['order_type'] = [
                "in",
                "1,3"
            ]; // 订单类型
            $condition['is_deleted'] = 0; // 未删除订单
            if ($start_date != 0 && $end_date != 0) {
                $condition["create_time"] = [
                    [
                        ">",
                        $start_date
                    ],
                    [
                        "<",
                        $end_date
                    ]
                ];
            } else if ($start_date != 0 && $end_date == 0) {
                $condition["create_time"] = [
                    [
                        ">",
                        $start_date
                    ]
                ];
            } else if ($start_date == 0 && $end_date != 0) {
                $condition["create_time"] = [
                    [
                        "<",
                        $end_date
                    ]
                ];
            }
            if ($order_status != '') {
                $condition['order_status'] = $order_status;
            }
            if ($payment_type != '') {
                $condition['payment_type'] = $payment_type;
            }
            if (!empty($user_name)) {
                $condition['receiver_name'] = $user_name;
            }
            if (!empty($order_no)) {
                $condition['order_no'] = $order_no;
            }
            if (!empty($receiver_mobile)) {
                $condition['receiver_mobile'] = $receiver_mobile;
            }
            if ($is_inside != '') {
                $condition['is_inside'] = $is_inside;
            }
            if ($source_branch != '') {
                $condition['source_branch'] = $source_branch;
            }
//            if (!empty($source_distribution)) {
//                $condition['source_distribution'] = $source_distribution;
//            }
            $condition['traffic_acquisition_source'] = [
                'like',
                '%' . $traffic_acquisition_source . '%'
            ];

//            if (!empty($search_text)) {
//                $condition1['real_name'] = [
//                    'like',
//                    '%' . $search_text . '%'
//                ];
//            }
//
//            $count_all = \think\Db::name('ns_member')->where($condition1)->select();
//
//            $ids       = [];
//            foreach($count_all as $v){
//                array_push($ids,$v['uid']);
//            }
//
//            if (!empty($ids)) {
//                $condition['source_distribution'] = [
//                    'in',
//                    $ids
//                ];
//            }

            $condition['shop_id'] = $this->instance_id;
            $order_service        = new OrderService();
            $list                 = $order_service->getOrderList($page_index, $page_size, $condition, 'create_time desc');

            return $list;
        } else {
            $status = request()->get('status', '');
            $this->assign("status", $status);
            $all_status        = OrderStatus::getOrderCommonStatus();
            $child_menu_list   = [];
            $child_menu_list[] = [
                'url'       => "Order/orderLists",
                'menu_name' => '全部',
                "active"    => $status == '' ? 1 : 0
            ];
            foreach ($all_status as $k => $v) {
                $child_menu_list[] = [
                    'url'       => "order/orderlists?status=" . $v['status_id'],
                    'menu_name' => $v['status_name'],
                    "active"    => $status == $v['status_id'] ? 1 : 0
                ];
            }
            $this->assign('child_menu_list', $child_menu_list);
            // 获取物流公司
            $express     = new ExpressService();
            $expressList = $express->expressCompanyQuery();
            $this->assign('expressList', $expressList);

            //来源网点列表
            $store     = new Store();
            $storeList = $store->getStore([], 'store_id, store_code,store_name', 'store_id');
            $this->assign('storeList', $storeList);

            //会员来源列表
//            $member                        = new MemberService();
//            $condition["distributor_type"] = [
//                [
//                    ">",
//                    0
//                ]
//            ];
//            $distributorList               = $member->memberList($condition, 'uid, real_name', 'uid');
//            $this->admin_user_record('查看商品订单列表','',$distributorList);

//            $this->assign('distributorList', $distributorList);
            return view($this->style . "Order/orderLists");
        }
    }

    /**
     * 礼品订单列表
     */
    public function giftOrderList()
    {
        if (request()->isAjax()) {
            $page_index              = request()->post('page_index', 1);
            $page_size               = request()->post('page_size', PAGESIZE);
            $start_date              = request()->post('start_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('start_date'));
            $end_date                = request()->post('end_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('end_date'));
            $user_name               = request()->post('user_name', '');
            $order_no                = request()->post('order_no', '');
            $order_status            = request()->post('order_status', '');
            $receiver_mobile         = request()->post('receiver_mobile', '');
            $source_branch           = request()->post('source_branch', '');
            $payment_type            = request()->post('payment_type', 1);
            $condition['order_type'] = 4; // 订单类型  (增加order_type = 4即礼品订单;author:Fu)
            $condition['is_deleted'] = 0; // 未删除订单
            if ($start_date != 0 && $end_date != 0) {
                $condition["create_time"] = [
                    [
                        ">",
                        $start_date
                    ],
                    [
                        "<",
                        $end_date
                    ]
                ];
            } else if ($start_date != 0 && $end_date == 0) {
                $condition["create_time"] = [
                    [
                        ">",
                        $start_date
                    ]
                ];
            } else if ($start_date == 0 && $end_date != 0) {
                $condition["create_time"] = [
                    [
                        "<",
                        $end_date
                    ]
                ];
            }
            if ($order_status != '') {
                // $order_status 1 待发货
                if ($order_status == 1) {
                    // 订单状态为待发货实际为已经支付未完成还未发货的订单
                    $condition['shipping_status'] = 0; // 0 待发货
                    $condition['pay_status']      = 2; // 2 已支付
                }
                $condition['order_status'] = $order_status;
            }
            if (!empty($payment_type)) {
                $condition['payment_type'] = $payment_type;
            }
            if (!empty($user_name)) {
                $condition['receiver_name'] = $user_name;
            }
            if (!empty($order_no)) {
                $condition['order_no'] = $order_no;
            }
            if (!empty($receiver_mobile)) {
                $condition['receiver_mobile'] = $receiver_mobile;
            }
            if ($source_branch != '') {
                $condition['source_branch'] = $source_branch;
            }
            $condition['shop_id'] = $this->instance_id;
            $order_service        = new OrderService();
            $list                 = $order_service->getOrderList($page_index, $page_size, $condition, 'create_time desc');

            return $list;
        } else {
            $status = request()->get('status', '');
            $this->assign("status", $status);
            $all_status        = OrderStatus::getGiftOrderCommonStatus();
            $child_menu_list   = [];
            $child_menu_list[] = [
                'url'       => "Order/giftOrderList",
                'menu_name' => '全部',
                "active"    => $status == '' ? 1 : 0
            ];

            foreach ($all_status as $k => $v) {
                $child_menu_list[] = [
                    'url'       => "order/giftOrderList?status=" . $v['status_id'],
                    'menu_name' => $v['status_name'],
                    "active"    => $status == $v['status_id'] ? 1 : 0
                ];
            }
            $this->assign('child_menu_list', $child_menu_list);
            $store     = new Store();
            $storeList = $store->getStore([], 'store_id, store_code,store_name', 'store_id');
            $this->assign('storeList', $storeList);
            $this->admin_user_record('查看礼品订单列表', '', $storeList);

            return view($this->style . "Order/giftOrderList");
        }

    }

    /**
     * 会员订单列表
     */
    public function vipOrderList()
    {
        if (request()->isAjax()) {
            $page_index              = request()->post('page_index', 1);
            $page_size               = request()->post('page_size', PAGESIZE);
            $start_date              = request()->post('start_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('start_date'));
            $end_date                = request()->post('end_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('end_date'));
            $user_name               = request()->post('user_name', '');
            $order_no                = request()->post('order_no', '');
            $order_status            = request()->post('order_status', '');
            $receiver_mobile         = request()->post('receiver_mobile', '');
            $source_branch           = request()->post('source_branch', '');
            $payment_type            = request()->post('payment_type', 1);
            $condition['order_type'] = 5; // 订单类型  (增加order_type = 5即会员订单;author:Fu)
            $condition['is_deleted'] = 0; // 未删除订单
            if ($start_date != 0 && $end_date != 0) {
                $condition["create_time"] = [
                    [
                        ">",
                        $start_date
                    ],
                    [
                        "<",
                        $end_date
                    ]
                ];
            } else if ($start_date != 0 && $end_date == 0) {
                $condition["create_time"] = [
                    [
                        ">",
                        $start_date
                    ]
                ];
            } else if ($start_date == 0 && $end_date != 0) {
                $condition["create_time"] = [
                    [
                        "<",
                        $end_date
                    ]
                ];
            }
            if ($order_status != '') {
                $condition['order_status'] = $order_status;
            }
            if (!empty($payment_type)) {
                $condition['payment_type'] = $payment_type;
            }
            if (!empty($user_name)) {
                $condition['receiver_name'] = $user_name;
            }
            if (!empty($order_no)) {
                $condition['order_no'] = $order_no;
            }
            if (!empty($receiver_mobile)) {
                $condition['receiver_mobile'] = $receiver_mobile;
            }
            if ($source_branch != '') {
                $condition['source_branch'] = $source_branch;
            }
            $condition['shop_id'] = $this->instance_id;
            $order_service        = new OrderService();
            $list                 = $order_service->getOrderList($page_index, $page_size, $condition, 'create_time desc');

            return $list;
        } else {
            $status = request()->get('status', '');
            $this->assign("status", $status);
            $all_status        = OrderStatus::getVipOrderCommonStatus();
            $child_menu_list   = [];
            $child_menu_list[] = [
                'url'       => "Order/vipOrderList",
                'menu_name' => '全部',
                "active"    => $status == '' ? 1 : 0
            ];
            foreach ($all_status as $k => $v) {
                $child_menu_list[] = [
                    'url'       => "order/vipOrderlist?status=" . $v['status_id'],
                    'menu_name' => $v['status_name'],
                    "active"    => $status == $v['status_id'] ? 1 : 0
                ];
            }
            $this->assign('child_menu_list', $child_menu_list);
            // 获取物流公司
            $express     = new ExpressService();
            $expressList = $express->expressCompanyQuery();
            $this->assign('expressList', $expressList);
            $store     = new Store();
            $storeList = $store->getStore([], 'store_id, store_code,store_name', 'store_id');
            $this->assign('storeList', $storeList);
            $this->admin_user_record('查看会员订单列表', '', $storeList);

            return view($this->style . "Order/vipOrderList");
        }
    }

    /**
     * 虚拟订单列表
     */
    public function virtualOrderList()
    {
        if (request()->isAjax()) {
            $page_index              = request()->post('page_index', 1);
            $page_size               = request()->post('page_size', PAGESIZE);
            $start_date              = request()->post('start_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('start_date'));
            $end_date                = request()->post('end_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('end_date'));
            $order_no                = request()->post('order_no', '');
            $order_status            = request()->post('order_status', '');
            $receiver_mobile         = request()->post('receiver_mobile', '');
            $payment_type            = request()->post('payment_type', 1);
            $condition['order_type'] = 2; // 订单类型
            $condition['is_deleted'] = 0; // 未删除订单
            if ($start_date != 0 && $end_date != 0) {
                $condition["create_time"] = [
                    [
                        ">",
                        $start_date
                    ],
                    [
                        "<",
                        $end_date
                    ]
                ];
            } else if ($start_date != 0 && $end_date == 0) {
                $condition["create_time"] = [
                    [
                        ">",
                        $start_date
                    ]
                ];
            } else if ($start_date == 0 && $end_date != 0) {
                $condition["create_time"] = [
                    [
                        "<",
                        $end_date
                    ]
                ];
            }
            if ($order_status != '') {
                $condition['order_status'] = $order_status;
            }
            if (!empty($payment_type)) {
                $condition['payment_type'] = $payment_type;
            }
            if (!empty($order_no)) {
                $condition['order_no'] = $order_no;
            }
            if (!empty($receiver_mobile)) {
                $condition['receiver_mobile'] = $receiver_mobile;
            }
            $condition['shop_id'] = $this->instance_id;
            $order_service        = new OrderService();
            $list                 = $order_service->getOrderList($page_index, $page_size, $condition, 'create_time desc');
            return $list;
        } else {
            $status = request()->get('status', '');
            $this->assign("status", $status);
            $all_status        = OrderStatus::getVirtualOrderCommonStatus();
            $child_menu_list   = [];
            $child_menu_list[] = [
                'url'       => "order/virtualorderlist",
                'menu_name' => '全部',
                "active"    => $status == '' ? 1 : 0
            ];
            foreach ($all_status as $k => $v) {
                $child_menu_list[] = [
                    'url'       => "order/virtualorderlist?status=" . $v['status_id'],
                    'menu_name' => $v['status_name'],
                    "active"    => $status == $v['status_id'] ? 1 : 0
                ];
            }
            $this->assign('child_menu_list', $child_menu_list);
            $this->admin_user_record('查看虚拟订单列表', '', $child_menu_list);

            return view($this->style . "Order/virtualOrderList");
        }
    }

    /**
     * 功能说明：获取店铺信息
     */
    public function getShopInfo()
    {
        // 获取信息
        $shopInfo['shopId']   = $this->instance_id;
        $shopInfo['shopName'] = $this->instance_name;
        // 返回信息
        return $shopInfo;
    }

    /**
     * 功能说明：获取打印出货单预览信息
     */
    public function getOrderInvoiceView()
    {
        $shop_id = $this->instance_id;
        // 获取值
        $orderIdArray = request()->get('ids', '');
        // 操作
        $order_service      = new OrderService();
        $goods_express_list = $order_service->getOrderGoodsExpressDetail($orderIdArray, $shop_id);
        // 返回信息
        return $goods_express_list;
    }

    /**
     * 功能说明：获取打印订单项预览信息
     */
    public function getOrderExpressPreview()
    {
        $shop_id = $this->instance_id;
        // 获取值
        $orderIdArray = request()->get('ids', '');
        // 操作
        $order_service      = new OrderService();
        $goods_express_list = $order_service->getOrderPrint($orderIdArray, $shop_id);
        // 返回信息
        return $goods_express_list;
    }

    /**
     * 功能说明：打印预览 发货单
     */
    public function printDeliveryPreview()
    {
        // 获取值
        $order_service = new OrderService();
        $order_ids     = request()->get('order_ids', '');
        $ShopName      = request()->get('ShopName', '');
        $shop_id       = $this->instance_id;
        $order_str     = explode(",", $order_ids);
        $order_array   = [];
        foreach ($order_str as $order_id) {
            $detail = [];
            $detail = $order_service->getOrderDetail($order_id);
            if (empty($detail)) {
                $this->error("没有获取到订单信息");
            }
            $order_array[] = $detail;
        }
        $receive_address = $order_service->getShopReturnSet($shop_id);
        $this->admin_user_record('打印预览发货单', '', '');

        $this->assign("order_print", $order_array);
        $this->assign("ShopName", $ShopName);
        $this->assign("receive_address", $receive_address);
        return view($this->style . 'Order/printDeliveryPreview');
    }

    /**
     * 打印快递单
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    // public function printExpressPreview()
    // {
    // $order_service = new OrderService();
    // $address_service = new AddressService();

    // $order_ids = request()->get('order_ids', '');
    // $ShopName = request()->get('ShopName', '');
    // $co_id = request()->get('co_id', '');

    // $shop_id = $this->instance_id;
    // $order_str = explode(",", $order_ids);
    // $order_array = array();
    // foreach ($order_str as $order_id) {
    // $detail = array();
    // $detail = $order_service->getOrderDetail($order_id);
    // if (empty($detail)) {
    // $this->error("没有获取到订单信息");
    // }
    // // $detail['address'] = $address_service->getAddress($detail['receiver_province'], $detail['receiver_city'], $detail['receiver_district']);
    // $order_array[] = $detail;
    // }
    // $express_server = new ExpressService();
    // // 物流模板信息
    // $express_shipping = $express_server->getExpressShipping($co_id);
    // // 物流打印信息
    // $express_shipping_item = $express_server->getExpressShippingItems($express_shipping["sid"]);
    // $receive_address = $order_service->getShopReturnSet($shop_id);
    // $this->assign("order_print", $order_array);
    // $this->assign("ShopName", $ShopName);
    // $this->assign("express_ship", $express_shipping);
    // $this->assign("express_item_list", $express_shipping_item);
    // $this->assign("receive_address", $receive_address);
    // return view($this->style . 'Order/printExpressPreview');
    // }
    public function printExpressPreview()
    {
        $print_order_ids = request()->get('print_order_ids', '');

        $express_server  = new ExpressService();
        $order_service   = new OrderService();
        $address_service = new AddressService();

        $print_order_id_array = explode(";", $print_order_ids);
        if (!empty($print_order_id_array) && count($print_order_id_array) > 0) {
            $order_list = "";
            foreach ($print_order_id_array as $print_order_id) {
                $print_order_list      = explode(":", $print_order_id);
                $detail                = $order_service->getOrderDetail($print_order_list[0]); // 获取订单详情
                $detail['address']     = $address_service->getAddress($detail['receiver_province'], $detail['receiver_city'], $detail['receiver_district']);
                $express_id_list       = explode(",", $print_order_list[1]); // 获取订单下包裹数
                $express_shipping_list = [];
                foreach ($express_id_list as $co_id) {
                    $express_shipping                          = $express_server->getExpressShipping($co_id); // 物流模板信息
                    $express_shipping["express_shipping_item"] = $express_shipping_item = $express_server->getExpressShippingItems($express_shipping["sid"]); // 物流打印信息
                    $express_shipping_list[]                   = $express_shipping;
                }
                $detail["express_id_list"] = $express_shipping_list;
                $order_list[]              = $detail;
            }
        }
        $receive_address = $order_service->getShopReturnSet($this->instance_id);
        $this->admin_user_record('打印快递单', '', '');
        $this->assign("receive_address", $receive_address);
        $this->assign("order_print", $order_list);
        return view($this->style . 'Order/printExpressPreview');
    }

    /**
     * 订单详情
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function orderDetail()
    {
        $order_id = request()->get('order_id', 0);
        if ($order_id == 0) {
            $this->error("没有获取到订单信息");
        }
        $order_service = new OrderService();
        $detail        = $order_service->getOrderDetail($order_id);
        if (empty($detail)) {
            $this->error("没有获取到订单信息");
        }
        if (!empty($detail['operation'])) {
            $operation_array = $detail['operation'];
            foreach ($operation_array as $k => $v) {
                if ($v["no"] == 'logistics') {
                    unset($operation_array[$k]);
                }
            }
            $detail['operation'] = $operation_array;
        }
        $this->assign("order", $detail);
        $this->admin_user_record('查看商品订单详情', $order_id, $detail);

        return view($this->style . "Order/orderDetail");
    }

    /**
     * 礼品订单详情
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function giftOrderDetail()
    {
        $order_id = request()->get('order_id', 0);
        if ($order_id == 0) {
            $this->error("没有获取到订单信息");
        }
        $order_service = new OrderService();
        $detail        = $order_service->getOrderDetail($order_id);
        if (empty($detail)) {
            $this->error("没有获取到订单信息");
        }
        if (!empty($detail['operation'])) {
            $operation_array = $detail['operation'];
            foreach ($operation_array as $k => $v) {
                if ($v["no"] == 'logistics') {
                    unset($operation_array[$k]);
                }
            }
            $detail['operation'] = $operation_array;
        }
        $this->admin_user_record('查看礼品订单详情', $order_id, $detail);

        $this->assign("order", $detail);
        return view($this->style . "Order/giftOrderDetail");
    }

    /**
     * 会员订单详情
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function vipOrderDetail()
    {
        $order_id = request()->get('order_id', 0);
        if ($order_id == 0) {
            $this->error("没有获取到订单信息");
        }
        $order_service = new OrderService();
        $detail        = $order_service->getOrderDetail($order_id);
        if (empty($detail)) {
            $this->error("没有获取到订单信息");
        }
        if (!empty($detail['operation'])) {
            $operation_array = $detail['operation'];
            foreach ($operation_array as $k => $v) {
                if ($v["no"] == 'logistics') {
                    unset($operation_array[$k]);
                }
            }
            $detail['operation'] = $operation_array;
        }
        $this->admin_user_record('查看会员订单详情', $order_id, $detail);

        $this->assign("order", $detail);
        return view($this->style . "Order/vipOrderDetail");
    }

    /**
     * 虚拟订单详情
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function virtualOrderDetail()
    {
        $order_id = request()->get('order_id', 0);
        if ($order_id == 0) {
            $this->error("没有获取到订单信息");
        }
        $order_service = new OrderService();
        $detail        = $order_service->getOrderDetail($order_id);
        if (empty($detail)) {
            $this->error("没有获取到订单信息");
        }
        if (!empty($detail['operation'])) {
            $operation_array = $detail['operation'];
            foreach ($operation_array as $k => $v) {
                if ($v["no"] == 'logistics') {
                    unset($operation_array[$k]);
                }
            }
            $detail['operation'] = $operation_array;
        }
        $this->admin_user_record('查看虚拟订单详情', $order_id, $detail);

        $this->assign("order", $detail);
        return view($this->style . "Order/virtualOrderDetail");
    }

    /**
     * 订单退款详情
     */
    public function orderRefundDetail()
    {
        $order_goods_id = request()->get('itemid', 0);
        if ($order_goods_id == 0) {
            $this->error("没有获取到退款信息");
        }
        $order_service          = new OrderService();
        $info                   = $order_service->getOrderGoodsRefundInfo($order_goods_id);
        $refund_account_records = $order_service->getOrderRefundAccountRecordsByOrderGoodsId($order_goods_id);
        $remark                 = ""; // 退款备注，只有在退款成功的状态下显示
        if (!empty($refund_account_records)) {
            if (!empty($refund_account_records['remark'])) {

                $remark = $refund_account_records['remark'];
            }
        }
        $order_goods = new OrderGoods();
        // 退款余额
        $refund_balance = $order_goods->orderGoodsRefundBalance($order_goods_id);
        $this->assign("refund_balance", sprintf("%.2f", $refund_balance));
        $this->assign('order_goods', $info);
        $this->assign("remark", $remark);
        $this->admin_user_record('查看订单退款详情', $order_goods_id, '');

        return view($this->style . "Order/orderRefundDetail");
    }

    public function orderRefundDetails()
    {
        $order_goods_id    = request()->get('order_goods_id', 0);
        $refund_records_id = request()->get('refund_records_id', 0);
        if ($refund_records_id == 0) {
            $this->error("没有获取到退款信息");
        }

        //查询子订单退款信息
        $order_service           = new OrderService();
        $orderGoodsRefundDetails = $order_service->getOrderGoodsRefundDetails($order_goods_id);
        $this->assign('orderGoodsRefundDetails', $orderGoodsRefundDetails);
        $this->admin_user_record('查看子订单退款信息', $order_goods_id, '');

        return view($this->style . "Order/orderRefundDetails");
    }


    //阿里云图片识别
//    public function ocrGeneral(){
//        $pay_type = request()->post('pay_type', '');
//        $showImg = request()->post('showImg', '');
//        $showImg = substr($showImg,strripos($showImg,",")+1);
//        $host = "http://tysbgpu.market.alicloudapi.com";
//        $path = "/api/predict/ocr_general";
//        $appcode = "e12d33e6b67c4e4b8aa2624ea8b1cbf8";
//        $headers = array();
//        array_push($headers, "Authorization:APPCODE " . $appcode);
//        //根据API的要求，定义相对应的Content-Type
//        array_push($headers, "Content-Type".":"."application/json; charset=UTF-8");
//        $bodys = json_encode(["image"=>$showImg,"configure"=>["min_size"=>16,"output_prob"=>true]]);
//        $url = $host . $path;
//
//        $curl = curl_init();
//        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
//        curl_setopt($curl, CURLOPT_URL, $url);
//        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
//        curl_setopt($curl, CURLOPT_FAILONERROR, false);
//        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($curl, CURLOPT_HEADER, false);
//        if (1 == strpos("$".$host, "https://"))
//        {
//            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
//            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
//        }
//        curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);
//        $result = json_decode(curl_exec($curl));
//        echo "<pre>";
//        print_r($result);
//        $data = [];
//        if($result->success){
//            foreach ($result->ret as $val) {
//                if($pay_type == 1){
//                    $data['pay_type'] = 1;
//                    $data['pay_type_name'] = '支付宝';
//                    $data['payer'] = $result->ret[4]->word.';'.$result->ret[5]->word.';'.$result->ret[7]->word.';'.$result->ret[8]->word;
//                    $data['payee'] = $result->ret[9]->word.';'.$result->ret[10]->word.';'.$result->ret[12]->word.';'.$result->ret[13]->word;
//                    $data['pay_no'] = $result->ret[14]->word;
//                    $data['pay_time'] = $result->ret[17]->word;
//                    $data['pay_money'] = $result->ret[20]->word;
//                    $data['abstract'] = $result->ret[21]->word;
//                }
//            }
//        }
//        return $data;
//    }

    /**
     * 交易完成
     *
     * @param unknown $orderid
     * @return Exception
     */
    public function orderComplete()
    {
        $order_service = new OrderService();
        $order_id      = request()->post('order_id', '');
        $res           = $order_service->orderComplete($order_id);
        $this->admin_user_record('订单完成', $order_id, $res);

        return AjaxReturn($res);
    }

    /**
     * 交易关闭
     */
    public function orderClose()
    {
        $order_service = new OrderService();
        $order_id      = request()->post('order_id', '');
        $res           = $order_service->orderClose($order_id);
        $this->admin_user_record('订单关闭', $order_id, $res);

        return AjaxReturn($res);
    }

    /**
     * 订单发货 所需数据
     */
    public function orderDeliveryData()
    {
        $order_service         = new OrderService();
        $express_service       = new ExpressService();
        $address_service       = new AddressService();
        $order_id              = request()->post('order_id', '');
        $order_info            = $order_service->getOrderDetail($order_id);
        $order_info['address'] = $address_service->getAddress($order_info['receiver_province'], $order_info['receiver_city'], $order_info['receiver_district']);
        $shopId                = $this->instance_id;
        // 快递公司列表
        $express_company_list = $express_service->expressCompanyQuery('shop_id = ' . $shopId, "*");
        // 订单商品项
        $order_goods_list             = $order_service->getOrderGoods($order_id);
        $data['order_info']           = $order_info;
        $data['express_company_list'] = $express_company_list;
        $data['order_goods_list']     = $order_goods_list;
        return $data;
    }

    /**
     * 订单发货
     */
    public function orderDelivery()
    {
        $order_service        = new OrderService();
        $order_id             = request()->post('order_id', '');
        $order_goods_id_array = request()->post('order_goods_id_array', '');
        $express_name         = request()->post('express_name', '');
        $shipping_type        = request()->post('shipping_type', '');
        $express_company_id   = request()->post('express_company_id', '');
        $express_no           = request()->post('express_no', '');
        if ($shipping_type == 1) {
            $res = $order_service->orderDelivery($order_id, $order_goods_id_array, $express_name, $shipping_type, $express_company_id, $express_no);
        } else {
            $res = $order_service->orderGoodsDelivery($order_id, $order_goods_id_array);
        }

        $this->admin_user_record('订单发货', $order_id, $res);

        return AjaxReturn($res);
    }

    /**
     * 获取订单大订单项
     */
    public function getOrderGoods()
    {
        $order_id         = request()->post('order_id', '');
        $order_service    = new OrderService();
        $order_goods_list = $order_service->getOrderGoods($order_id);
        $order_info       = $order_service->getOrderInfo($order_id);
        $list[0]          = $order_goods_list;
        $list[1]          = $order_info;
        return $list;
    }

    /**
     * 订单价格调整
     */
    public function orderAdjustMoney($order_id, $order_goods_id_adjust_array, $shipping_fee)
    {
        $order_id                    = request()->post('order_id', '');
        $order_goods_id_adjust_array = request()->post('order_goods_id_adjust_array', '');
        $shipping_fee                = request()->post('shipping_fee', '');
        $order_service               = new OrderService();
        $res                         = $order_service->orderMoneyAdjust($order_id, $order_goods_id_adjust_array, $shipping_fee);
        $this->admin_user_record('订单调价', $order_id, $res);

        return AjaxReturn($res);
    }

    public function test()
    {
        $order_service = new OrderService();
        $list          = $order_service->test();
        var_dump($list);
    }

    public function orderGoodsOpertion()
    {
        $order_goods    = new OrderGoods();
        $order_id       = 14;
        $order_goods_id = 35;

        // 申请退款
        $refund_type          = 2;
        $refund_require_money = 202;
        $refund_reason        = '不想买了';
        $retval               = $order_goods->orderGoodsRefundAskfor($order_id, $order_goods_id, $refund_type, $refund_require_money, $refund_reason);

        // 卖家同意退款
        // $retval = $order_goods->orderGoodsRefundAgree($order_id, $order_goods_id);

        // 卖家确认退款
        // $refund_real_money = 10;
        // $retval = $order_goods->orderGoodsConfirmRefund($order_id, $order_goods_id, $refund_real_money,0);

        // 买家退货
        // $refund_shipping_company = 8;
        // $refund_shipping_code = '545654465';
        // $retval = $order_goods->orderGoodsReturnGoods($order_id ,$order_goods_id, $refund_shipping_company, $refund_shipping_code);

        // 卖家确认收货
        // $retval = $order_goods->orderGoodsConfirmRecieve($order_id, $order_goods_id);

        // 买家取消订单
        // $retval = $order_goods->orderGoodsCancel($order_id ,$order_goods_id);

        // 卖家拒绝退款
        // $retval = $order_goods->orderGoodsRefuseForever($order_id, $order_goods_id);

        // 卖家拒绝本次退款
        // $retval = $order_goods->orderGoodsRefuseOnce($order_id, $order_goods_id);
        // $orderGoodsList = NsOrderGoodsModel::where("order_id=$order_id AND refund_status<>0 AND refund_status<>5")->select();
        // $map = array("order_id"=>$order_id, "refund_status"=>array("neq", 0), "refund_status"=>array("neq", 5));
        // $orderGoodsList = NsOrderGoodsModel::all($map);
        // $refund_count = count($orderGoodsList);
        // $orderGoodsListTotal = NsOrderGoodsModel::where("order_id=$order_id AND refund_status=5")->count();
        // $total_count = count($orderGoodsListTotal);
        // $retval = $orderGoodsListTotal;
        var_dump($retval);
    }

    /**
     * 买家申请退款
     *
     * @return Ambigous <number, \data\service\niushop\Order\Exception, \data\service\niushop\Order\Ambigous>
     */
    public function orderGoodsRefundAskfor()
    {
        $order_id             = request()->post('order_id', '');
        $order_goods_id       = request()->post('order_goods_id', '');
        $refund_type          = request()->post('refund_type', '');
        $refund_require_money = request()->post('refund_require_money', 0);
        $refund_reason        = request()->post('refund_reason', '');
        if (empty($order_id) || empty($order_goods_id) || empty($refund_type) || empty($refund_require_money) || empty($refund_reason)) {
            $this->error('缺少必需参数');
        }
        $order_service = new OrderService();
        $retval        = $order_service->orderGoodsRefundAskfor($order_id, $order_goods_id, $refund_type, $refund_require_money, $refund_reason);
        return AjaxReturn($retval);
    }

    /**
     * 买家取消退款
     *
     * @return number
     */
    public function orderGoodsCancel()
    {
        $order_id       = request()->post('order_id', '');
        $order_goods_id = request()->post('order_goods_id', '');
        if (empty($order_id) || empty($order_goods_id)) {
            $this->error('缺少必需参数');
        }
        $order_service = new OrderService();
        $retval        = $order_service->orderGoodsCancel($order_id, $order_goods_id);
        return AjaxReturn($retval);
    }

    /**
     * 买家退货
     *
     * @return Ambigous <number, \think\false, boolean, string>
     */
    public function orderGoodsReturnGoods()
    {
        $order_id       = request()->post('order_id', '');
        $order_goods_id = request()->post('order_goods_id', '');
        if (empty($order_id) || empty($order_goods_id)) {
            $this->error('缺少必需参数');
        }
        $refund_shipping_company = request()->post('refund_shipping_company', '');
        $refund_shipping_code    = request()->post('refund_shipping_code', '');
        $order_service           = new OrderService();
        $retval                  = $order_service->orderGoodsReturnGoods($order_id, $order_goods_id, $refund_shipping_company, $refund_shipping_code);
        return AjaxReturn($retval);
    }

    /**
     * 买家同意买家退款申请
     *
     * @return number
     */
    public function orderGoodsRefundAgree()
    {
        $order_id       = request()->post('order_id', '');
        $order_goods_id = request()->post('order_goods_id', '');
        if (empty($order_id) || empty($order_goods_id)) {
            $this->error('缺少必需参数');
        }
        $order_service = new OrderService();
        $retval        = $order_service->orderGoodsRefundAgree($order_id, $order_goods_id);
        $this->admin_user_record('同意退款', $order_id, $retval);

        return AjaxReturn($retval);
    }

    public function orderGoodsRefundAgrees()
    {
        $order_id          = request()->post('order_id', '');
        $order_goods_id    = request()->post('order_goods_id', '');
        $refund_records_id = request()->post('refund_records_id', '');
        if (empty($order_id) || empty($order_goods_id) || empty($refund_records_id)) {
            $this->error('缺少必需参数');
        }
        $order_service = new OrderService();
        $retval        = $order_service->orderGoodsRefundAgrees($order_id, $order_goods_id, $refund_records_id);
        return AjaxReturn($retval);
    }

    /**
     * 买家永久拒绝本次退款
     *
     * @return Ambigous <number, Exception>
     */
    public function orderGoodsRefuseForever()
    {
        $order_id       = request()->post('order_id', '');
        $order_goods_id = request()->post('order_goods_id', '');
        if (empty($order_id) || empty($order_goods_id)) {
            $this->error('缺少必需参数');
        }
        $order_service = new OrderService();
        $retval        = $order_service->orderGoodsRefuseForever($order_id, $order_goods_id);
        return AjaxReturn($retval);
    }

    public function orderGoodsRefuseForevers()
    {
        $order_id          = request()->post('order_id', '');
        $order_goods_id    = request()->post('order_goods_id', '');
        $refund_records_id = request()->post('refund_records_id', '');
        if (empty($order_id) || empty($order_goods_id)) {
            $this->error('缺少必需参数');
        }
        $order_service = new OrderService();
        $retval        = $order_service->orderGoodsRefuseForevers($order_id, $order_goods_id, $refund_records_id);
        $this->admin_user_record('永久拒绝退款', $order_id, $retval);

        return AjaxReturn($retval);
    }

    /**
     * 卖家拒绝本次退款
     *
     * @return Ambigous <number, Exception>
     */
    public function orderGoodsRefuseOnce()
    {
        $order_id       = request()->post('order_id', '');
        $order_goods_id = request()->post('order_goods_id', '');
        if (empty($order_id) || empty($order_goods_id)) {
            $this->error('缺少必需参数');
        }
        $order_service = new OrderService();
        $retval        = $order_service->orderGoodsRefuseOnce($order_id, $order_goods_id);
        $this->admin_user_record('拒绝退款', $order_id, $retval);
        return AjaxReturn($retval);
    }

    public function orderGoodsRefuseOnces()
    {
        $order_id          = request()->post('order_id', '');
        $order_goods_id    = request()->post('order_goods_id', '');
        $refund_records_id = request()->post('refund_records_id', '');
        if (empty($order_id) || empty($order_goods_id)) {
            $this->error('缺少必需参数');
        }
        $order_service = new OrderService();
        $retval        = $order_service->orderGoodsRefuseOnces($order_id, $order_goods_id, $refund_records_id);
        return AjaxReturn($retval);
    }

    /**
     * 卖家确认收货
     *
     * @return Ambigous <number, Exception>
     */
    public function orderGoodsConfirmRecieve()
    {
        $order_id       = request()->post('order_id', '');
        $order_goods_id = request()->post('order_goods_id', '');
        if (empty($order_id) || empty($order_goods_id)) {
            $this->error('缺少必需参数');
        }
        $storage_num   = request()->post("storage_num", "");
        $isStorage     = request()->post("isStorage", "");
        $goods_id      = request()->post("goods_id", '');
        $sku_id        = request()->post('sku_id', '');
        $order_service = new OrderService();
        $retval        = $order_service->orderGoodsConfirmRecieve($order_id, $order_goods_id, $storage_num, $isStorage, $goods_id, $sku_id);
        $this->admin_user_record('确认收货', $order_id, $retval);
        return AjaxReturn($retval);
    }

    public function orderGoodsConfirmRecieves()
    {
        $order_id          = request()->post('order_id', '');
        $order_goods_id    = request()->post('order_goods_id', '');
        $refund_records_id = request()->post('refund_records_id', '');
        if (empty($order_id) || empty($order_goods_id) || empty($refund_records_id)) {
            $this->error('缺少必需参数');
        }
        $storage_num   = request()->post("storage_num", "");
        $isStorage     = request()->post("isStorage", "");
        $goods_id      = request()->post("goods_id", '');
        $sku_id        = request()->post('sku_id', '');
        $order_service = new OrderService();
        $retval        = $order_service->orderGoodsConfirmRecieves($order_id, $order_goods_id, $refund_records_id, $storage_num, $isStorage, $goods_id, $sku_id);
        return AjaxReturn($retval);
    }

    /**
     * 卖家确认退款
     *
     * @return Ambigous <Exception, unknown>
     */
    public function orderGoodsConfirmRefund()
    {
        $order_id             = request()->post('order_id', '');
        $order_goods_id       = request()->post('order_goods_id', '');
        $refund_real_money    = request()->post('refund_real_money', 0); // 退款金额
        $refund_balance_money = request()->post("refund_balance_money", 0); // 退款余额
        $refund_way           = request()->post("refund_way", ""); // 退款方式
        $refund_remark        = request()->post("refund_remark", ""); // 退款备注
        if (empty($order_id) || empty($order_goods_id) || $refund_real_money === '' || empty($refund_way)) {
            $this->error('缺少必需参数');
        }
        $order_service = new OrderService();
        $retval        = $order_service->orderGoodsConfirmRefund($order_id, $order_goods_id, $refund_real_money, $refund_balance_money, $refund_way, $refund_remark);
        $this->admin_user_record('确认退款', $order_id, $retval);

        if (is_numeric($retval)) {
            return AjaxReturn($retval);
        } else {
            return [
                "code"    => 0,
                "message" => $retval
            ];
        }
    }

    public function orderGoodsConfirmRefunds()
    {
        $order_id             = request()->post('order_id', '');
        $order_goods_id       = request()->post('order_goods_id', '');
        $refund_records_id    = request()->post('refund_records_id', '');
        $refund_real_money    = request()->post('refund_real_money', 0); // 退款金额
        $refund_balance_money = request()->post("refund_balance_money", 0); // 退款余额
        $refund_way           = request()->post("refund_way", ""); // 退款方式
        $refund_remark        = request()->post("refund_remark", ""); // 退款备注
        if (empty($order_id) || empty($order_goods_id) || empty($refund_records_id) || $refund_real_money === '' || empty($refund_way)) {
            $this->error('缺少必需参数');
        }
        $order_service = new OrderService();
        $retval        = $order_service->orderGoodsConfirmRefunds($order_id, $order_goods_id, $refund_records_id, $refund_real_money, $refund_balance_money, $refund_way, $refund_remark);
        if (is_numeric($retval)) {
            return AjaxReturn($retval);
        } else {
            return [
                "code"    => 0,
                "message" => $retval
            ];
        }
    }

    /**
     * 确认退款时，查询买家实际付款金额
     */
    public function orderGoodsRefundMoney()
    {
        $order_service  = new OrderService();
        $order_goods_id = request()->post('order_goods_id', '');
        $res            = 0;
        if ($order_goods_id != '') {
            $res = $order_service->orderGoodsRefundMoney($order_goods_id);
        }
        return $res;
    }

    /**
     * 获取订单销售统计
     */
    public function getOrderAccount()
    {
        $order_service = new OrderService();
        // 获取日销售统计
        $account = $order_service->getShopOrderAccountDetail($this->instance_id);
        var_dump($account);
    }

    /**
     * 退货设置
     * 任鹏强
     */
    public function returnSetting()
    {
        $child_menu_list = [
            [
                'url'       => "express/expresscompany",
                'menu_name' => "物流公司",
                "active"    => 0
            ],
            [
                'url'       => "config/areamanagement",
                'menu_name' => "地区管理",
                "active"    => 0
            ],
            [
                'url'       => "order/returnsetting",
                'menu_name' => "商家地址",
                "active"    => 1
            ],
            [
                'url'       => "shop/pickuppointlist",
                'menu_name' => "自提点管理",
                "active"    => 0
            ],
            [
                'url'       => "shop/pickuppointfreight",
                'menu_name' => "自提点运费",
                "active"    => 0
            ],
            [
                'url'       => "config/distributionareamanagement",
                'menu_name' => "货到付款地区管理",
                "active"    => 0
            ],
            [
                'url'       => "config/expressmessage",
                'menu_name' => "物流跟踪设置",
                "active"    => 0
            ]
        ];

        $this->assign('child_menu_list', $child_menu_list);
        $order_service = new OrderService();
        $shop_id       = $this->instance_id;
        if (request()->isAjax()) {
            $address   = request()->post('address', '');
            $real_name = request()->post('real_name', '');
            $mobile    = request()->post('mobile', '');
            $zipcode   = request()->post('zipcode', '');
            $retval    = $order_service->updateShopReturnSet($shop_id, $address, $real_name, $mobile, $zipcode);
            return AjaxReturn($retval);
        } else {
            $info = $order_service->getShopReturnSet($shop_id);
            $this->assign('info', $info);
            return view($this->style . "Order/returnSetting");
        }
    }

    /**
     * 提货
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function pickupOrder()
    {
        $order_id = request()->post('order_id', '');
        if (empty($order_id)) {
            $this->error('缺少必需参数');
        }
        $buyer_name    = request()->post('buyer_name', '');
        $buyer_phone   = request()->post('buyer_phone', '');
        $remark        = request()->post('remark', '');
        $order_service = new OrderService();
        $retval        = $order_service->pickupOrder($order_id, $buyer_name, $buyer_phone, $remark);
        return AjaxReturn($retval);
    }

    /**
     * 获取物流跟踪信息
     */
    public function getExpressInfo()
    {
        $order_service  = new OrderService();
        $order_goods_id = request()->post('order_goods_id');
        $expressinfo    = $order_service->getOrderGoodsExpressMessage($order_goods_id);
        return $expressinfo;
    }

    /**
     * 添加备注
     */
    public function addMemo()
    {
        $order_service = new OrderService();
        $order_id      = request()->post('order_id');
        $memo          = request()->post('memo');
        $result        = $order_service->addOrderSellerMemo($order_id, $memo);
        $this->admin_user_record('添加备注', $order_id, '');
        return AjaxReturn($result);
    }

    /**
     * 获取订单备注信息
     *
     * @return unknown
     */
    public function getOrderSellerMemo()
    {
        $order_service = new OrderService();
        $order_id      = request()->post('order_id');
        $res           = $order_service->getOrderSellerMemo($order_id);
        return $res;
    }

    /**
     * 获取修改收货地址的信息
     *
     * @return string
     */
    public function getOrderUpdateAddress()
    {
        $order_service = new OrderService();
        $order_id      = request()->post('order_id');
        $res           = $order_service->getOrderReceiveDetail($order_id);
        return $res;
    }

    /**
     * 修改收货地址的信息
     *
     * @return string
     */
    public function updateOrderAddress()
    {
        $order_service     = new OrderService();
        $order_id          = request()->post('order_id', '');
        $receiver_name     = request()->post('receiver_name', '');
        $receiver_mobile   = request()->post('receiver_mobile', '');
        $receiver_zip      = request()->post('receiver_zip', '');
        $receiver_province = request()->post('seleAreaNext', '');
        $receiver_city     = request()->post('seleAreaThird', '');
        $receiver_district = request()->post('seleAreaFouth', '');
        $receiver_address  = request()->post('address_detail', '');
        $fixed_telephone   = request()->post("fixed_telephone", "");
        $res               = $order_service->updateOrderReceiveDetail($order_id, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name, $fixed_telephone);
        $this->admin_user_record('修改收货地址的信息', $order_id, '');
        return $res;
    }

    /**
     * 获取省列表
     */
    public function getProvince()
    {
        $address       = new Address();
        $province_list = $address->getProvinceList();
        return $province_list;
    }

    /**
     * 获取城市列表
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
     * 获取区域地址
     */
    public function getDistrict()
    {
        $address       = new Address();
        $city_id       = request()->post('city_id', 0);
        $district_list = $address->getDistrictList($city_id);
        return $district_list;
    }

    /**
     * 导出粉丝列表到excal
     */
    public function testExcel()
    {
        // 导出Excel
        $xlsName = "开门记录列表";
        $xlsCell = [
            [
                'userid',
                '用户id'
            ],
            [
                'use_name',
                '使用者姓名'
            ]
        ];
        $list    = [
            [
                "userid"   => "55",
                "use_name" => "王二小"
            ],
            [
                "userid"   => "56",
                "use_name" => "王二大"
            ]
        ];
        dataExcel($xlsName, $xlsCell, $list);
    }

    /**
     * 商品订单数据excel导出
     */
    public function orderDataExcel()
    {
        $xlsName             = "商品订单数据列表";
        $start_date          = request()->get('start_date') == "" ? 0 : getTimeTurnTimeStamp(request()->get('start_date'));
        $end_date            = request()->get('end_date') == "" ? 0 : getTimeTurnTimeStamp(request()->get('end_date'));
        $user_name           = request()->get('user_name', '');
        $order_no            = request()->get('order_no', '');
        $order_status        = request()->get('order_status', '');
        $receiver_mobile     = request()->get('receiver_mobile', '');
        $is_inside           = request()->get('is_inside', '');
        $source_branch       = request()->get('source_branch', '');
        $source_distribution = request()->get('source_distribution', '');
        $payment_type        = request()->get('payment_type', '');
        $order_ids           = request()->get("order_ids", "");

        if ($order_ids != "") {
            $condition["order_id"] = [
                "in",
                $order_ids
            ];
        }

        if ($start_date != 0 && $end_date != 0) {
            $condition["create_time"] = [
                [
                    ">",
                    $start_date
                ],
                [
                    "<",
                    $end_date
                ]
            ];
        } else if ($start_date != 0 && $end_date == 0) {
            $condition["create_time"] = [
                [
                    ">",
                    $start_date
                ]
            ];
        } else if ($start_date == 0 && $end_date != 0) {
            $condition["create_time"] = [
                [
                    "<",
                    $end_date
                ]
            ];
        }
        if ($order_status != '') {
            $condition['order_status'] = $order_status;
        }
        if ($payment_type != '') {
            $condition['payment_type'] = $payment_type;
        }
        if (!empty($user_name)) {
            $condition['receiver_name'] = $user_name;
        }
        if (!empty($order_no)) {
            $condition['order_no'] = $order_no;
        }
        if (!empty($receiver_mobile)) {
            $condition['receiver_mobile'] = $receiver_mobile;
        }
        if ($is_inside != '') {
            $condition['is_inside'] = $is_inside;
        }
        if ($source_branch != '') {
            $condition['source_branch'] = $source_branch;
        }
        if (!empty($source_distribution)) {
            $condition['source_distribution'] = $source_distribution;
        }
        $condition['shop_id']    = $this->instance_id;
        $condition['order_type'] = [
            "in",
            "1,3"
        ]; // 普通订单
        $condition['is_deleted'] = 0; // 未删除订单
        $order_service           = new OrderService();
        $list                    = $order_service->getExportOrderListNew($condition, 'create_time desc');

        foreach ($list as $k => $v) {
            $list[$k]["create_date"] = getTimeStampTurnTime($v["create_time"]); // 创建时间
//            $list[$k]["receiver_info"] = $v["receiver_name"] . "  " . $v["receiver_mobile"] . "  " . $v["fixed_telephone"] . " " . $v["receiver_province_name"] . $v["receiver_city_name"] . $v["receiver_district_name"] . $v["receiver_address"] . "  " . $v["receiver_zip"]; // 创建时间
            if ($v['shipping_type'] == 1) {
                $list[$k]["shipping_type_name"] = '商家配送';
            } else if ($v['shipping_type'] == 2) {
                $list[$k]["shipping_type_name"] = '门店自提';
            } else {
                $list[$k]["shipping_type_name"] = '';
            }
            if ($v['pay_status'] == 0) {
                $list[$k]["pay_status_name"] = '待付款';
            } else if ($v['pay_status'] == 2) {
                $list[$k]["pay_status_name"] = '已付款';
            } else if ($v['pay_status'] == 1) {
                $list[$k]["pay_status_name"] = '支付中';
            }
//            $goods_info = "";
//            foreach ($v["order_item_list"] as $t => $m) {
//                $goods_info .= "商品名称:" . $m["goods_name"] . "  规格:" . $m["sku_name"] . "  商品价格:" . $m["price"] . "  购买数量:" . $m["num"] . "  ";
//            }
//            $list[$k]["goods_info"] = $goods_info;
        }

        # todo @陈 需要修改导出格式
        $arr = [];
        foreach ($list as $k => $v) {
//            $v = $v->toArray();
            $v['pay_time']     = (int)$v['pay_time'] > 0 ? date('Y-m-d H:i:s', $v['pay_time']) : '未付款'; //付款时间
            $v['consign_time'] = (int)$v['consign_time'] > 0 ? date('Y-m-d H:i:s', $v['consign_time']) : '未发货'; //发货时间
//            $member_info = db('ns_member_account')->where([
//                'uid' => $v['buyer_id']
//            ])->find();
//            $user_info = db('sys_user')->where([
//                'uid' => $v['buyer_id']
//            ])->find();
//            $v['point_count'] = $member_info['point'];   # 剩余积分
//            $v['balance']     = $member_info['balance']; # 余额
//            $v['user_email']  = $user_info['user_email']; # 邮箱
//            $v['address'] = $v["receiver_province_name"] . $v["receiver_city_name"] . $v["receiver_district_name"] . $v["receiver_address"]; //收货地址

            # 物流信息 考虑到后期订单oms编辑, 暂以主订单第一条物流信息为标准
            $logistics_info    = db('ns_order_goods_express')->where(['order_id' => $v['order_id']])->order('id asc')->find();
            $v['express_name'] = $logistics_info['express_name']; //物流公司名称
            $v['express_no']   = $logistics_info['express_no']; //物流单号
            $v['tx_type']      = $v['tx_type'] == 1 ? '大贸' : '跨境'; //交易类型（1：大贸，2：跨境）
            $v['is_inside']    = $v['is_inside'] == 1 ? '内购' : ''; //是否内购（0：否，1：是）

            foreach ($v['order_item_list'] as $vv) {
                $tmp = array_merge($vv, $v);
                array_push($arr, $tmp);
            }
//            $arr[$k] = $v;
        }

        $xlsCell = [
            [
                'order_no',
                '订单编号'
            ],
            [
                'create_date',
                '下单时间'
            ],
            [
                'pay_time',
                '付款时间'
            ],
            [
                'consign_time',
                '发货时间'
            ],
            [
                'user_name',
                '会员昵称'
            ],
            [
                'user_tel',
                '会员手机号'
            ],
            //            array(
            //                'user_email',
            //                '会员邮箱'
            //            ),
            //            array(
            //                'point_count',
            //                '会员积分'
            //            ),
            //            array(
            //                'balance',
            //                '会员余额'
            //            ),
            [
                'receiver_name',
                '收货人姓名'
            ],
            [
                'receiver_mobile',
                '收货人电话'
            ],
            //            array(
            //                'address',
            //                '收货人地址'
            //            ),
            [
                'price',
                '商品单价'
            ],
            [
                'pay_money',
                '实际支付'
            ],
            [
                'adjust_money',
                '调整金额'
            ],
            //            array(
            //                'pay_type_name',
            //                '支付方式'
            //            ),
            [
                'source_branch_name',
                '来源网点'
            ],
            [
                'source_distribution_name',
                '分销来源'
            ],
            [
                'shipping_type_name',
                '配送方式'
            ],
            [
                'pay_status_name',
                '支付状态'
            ],
            [
                'status_name',
                '发货状态'
            ],

            [
                'express_name',
                '物流公司'
            ],
            [
                'express_no',
                '物流单号'
            ],
            [
                'tx_type',
                '物流类型'
            ],
            [
                'is_inside',
                '是否内购'
            ],
            [
                'goods_name',
                '商品名称'
            ],
            [
                'sku_name',
                '商品规格'
            ],
            [
                'code',
                '商品编码'
            ],
            [
                'product_barcode',
                '商品条形码'
            ],
            [
                'material_code',
                'shopal物料编号'
            ],
            [
                'num',
                '购买数量'
            ],

            [
                'buyer_message',
                '买家留言'
            ],
            [
                'seller_memo',
                '卖家备注'
            ]
        ];
        $this->admin_user_record('商品订单数据excel导出', '', '');

        dataExcel($xlsName, $xlsCell, $arr);
        exit;
//        dataExcel($xlsName, $xlsCell, $list);
    }

    public function orderExcel()
    {
        $condition['is_deleted']          = 0; // 未删除订单
        $condition['order_type']          = 1; // 商品订单
        $condition['source_distribution'] = ['NOT IN', '3375,4560'];//排除分销来源为SHOPAL分销
        $condition['is_inside']           = 0;//非内购
        $start_date =  getTimeTurnTimeStamp('2019-07-01 00:00:00');
        $end_date =  getTimeTurnTimeStamp('2019-07-31 23:59:59');

        if ($start_date != 0 && $end_date != 0) {
            $condition["create_time"] = [
                [
                    ">",
                    $start_date
                ],
                [
                    "<",
                    $end_date
                ]
            ];
        } else if ($start_date != 0 && $end_date == 0) {
            $condition["create_time"] = [
                [
                    ">",
                    $start_date
                ]
            ];
        } else if ($start_date == 0 && $end_date != 0) {
            $condition["create_time"] = [
                [
                    "<",
                    $end_date
                ]
            ];
        }


        $condition['order_status']        = [
            "in",
            "1,2,3,4"
        ];//排除未付款,已关闭,退款中,已退款的订单

        $order_service = new OrderService();
        $list          = $order_service->exportOrder($condition, 'create_time desc');

        $arr = [];
        foreach ($list as $k => $v) {
            $list[$k]["create_date"] = getTimeStampTurnTime($v["create_time"]); // 创建时间
            if ($v['shipping_type'] == 1) {
                $list[$k]["shipping_type_name"] = '商家配送';
            } else if ($v['shipping_type'] == 2) {
                $list[$k]["shipping_type_name"] = '门店自提';
            } else {
                $list[$k]["shipping_type_name"] = '';
            }
            if ($v['pay_status'] == 0) {
                $list[$k]["pay_status_name"] = '待付款';
            } else if ($v['pay_status'] == 2) {
                $list[$k]["pay_status_name"] = '已付款';
            } else if ($v['pay_status'] == 1) {
                $list[$k]["pay_status_name"] = '支付中';
            }
        }

        foreach ($list as $k => $v) {
            $v['pay_time']     = (int)$v['pay_time'] > 0 ? date('Y-m-d H:i:s', $v['pay_time']) : '未付款'; //付款时间
            $v['consign_time'] = (int)$v['consign_time'] > 0 ? date('Y-m-d H:i:s', $v['consign_time']) : '未发货'; //发货时间
            $v['address'] = $v["receiver_province_name"] . $v["receiver_city_name"] . $v["receiver_district_name"] . $v["receiver_address"]; //收货地址

            # 物流信息 考虑到后期订单oms编辑, 暂以主订单第一条物流信息为标准
            $v['tx_type']   = $v['tx_type'] == 1 ? '大贸' : '跨境'; //交易类型（1：大贸，2：跨境）
            $v['is_inside'] = $v['is_inside'] == 1 ? '内购' : ''; //是否内购（0：否，1：是）

            foreach ($v['order_item_list'] as $vv) {
                $tmp = array_merge($vv, $v);
                array_push($arr, $tmp);
            }
        }

        $xlsCell = [
            [
                'order_no',
                '订单编号'
            ],
            [
                'create_date',
                '下单时间'
            ],
            [
                'pay_time',
                '付款时间'
            ],
            [
                'consign_time',
                '发货时间'
            ],
            [
                'user_name',
                '会员昵称'
            ],
            [
                'user_tel',
                '会员手机号'
            ],
            [
                'receiver_name',
                '收货人姓名'
            ],
            [
                'receiver_mobile',
                '收货人电话'
            ],
            [
                'address',
                '收货人地址'
            ],
            [
                'price',
                '商品单价'
            ],
            [
                'pay_money',
                '实际支付'
            ],
            [
                'adjust_money',
                '调整金额'
            ],
            [
                'source_branch_name',
                '来源网点'
            ],
            [
                'source_distribution_name',
                '分销来源'
            ],
            [
                'shipping_type_name',
                '配送方式'
            ],
            [
                'pay_status_name',
                '支付状态'
            ],
            [
                'status_name',
                '发货状态'
            ],
            [
                'tx_type',
                '物流类型'
            ],
            [
                'is_inside',
                '是否内购'
            ],
            [
                'goods_name',
                '商品名称'
            ],
            [
                'sku_name',
                '商品规格'
            ],
            [
                'code',
                '商品编码'
            ],
            [
                'product_barcode',
                '商品条形码'
            ],
            [
                'material_code',
                'shopal物料编号'
            ],
            [
                'num',
                '购买数量'
            ],

            [
                'buyer_message',
                '买家留言'
            ],
            [
                'seller_memo',
                '卖家备注'
            ]
        ];

        dataExcel("商品订单数据列表", $xlsCell, $arr);
        exit;
    }

    /**
     * 礼品订单数据excel导出
     */
    public function giftOrderDataExcel()
    {
        $xlsName         = "礼品订单数据列表";
        $start_date      = request()->get('start_date') == "" ? 0 : getTimeTurnTimeStamp(request()->get('start_date'));
        $end_date        = request()->get('end_date') == "" ? 0 : getTimeTurnTimeStamp(request()->get('end_date'));
        $user_name       = request()->get('user_name', '');
        $order_no        = request()->get('order_no', '');
        $order_status    = request()->get('order_status', '');
        $receiver_mobile = request()->get('receiver_mobile', '');
        $source_branch   = request()->get('source_branch', '');
        $payment_type    = request()->get('payment_type', '');
        $order_ids       = request()->get("order_ids", "");

        if ($order_ids != "") {
            $condition["order_id"] = [
                "in",
                $order_ids
            ];
        }

        if ($start_date != 0 && $end_date != 0) {
            $condition["create_time"] = [
                [
                    ">",
                    $start_date
                ],
                [
                    "<",
                    $end_date
                ]
            ];
        } else if ($start_date != 0 && $end_date == 0) {
            $condition["create_time"] = [
                [
                    ">",
                    $start_date
                ]
            ];
        } else if ($start_date == 0 && $end_date != 0) {
            $condition["create_time"] = [
                [
                    "<",
                    $end_date
                ]
            ];
        }
        if (!empty($order_status)) {
            $condition['order_status'] = $order_status;
        }
        if (!empty($payment_type)) {
            $condition['payment_type'] = $payment_type;
        }
        if (!empty($user_name)) {
            $condition['receiver_name'] = $user_name;
        }
        if (!empty($order_no)) {
            $condition['order_no'] = $order_no;
        }
        if (!empty($receiver_mobile)) {
            $condition['receiver_mobile'] = $receiver_mobile;
        }
        if ($source_branch != '') {
            $condition['source_branch'] = $source_branch;
        }
        $condition['shop_id']    = $this->instance_id;
        $condition['order_type'] = 4; // 礼物订单
        $condition['is_deleted'] = 0; // 未删除订单
        $order_service           = new OrderService();
        $list                    = $order_service->getExportOrderListNew($condition, 'create_time desc');

        foreach ($list as $k => $v) {
            $list[$k]["create_date"] = getTimeStampTurnTime($v["create_time"]); // 创建时间
            if ($v['shipping_type'] == 1) {
                $list[$k]["shipping_type_name"] = '商家配送';
            } else if ($v['shipping_type'] == 2) {
                $list[$k]["shipping_type_name"] = '门店自提';
            } else {
                $list[$k]["shipping_type_name"] = '';
            }
            if ($v['pay_status'] == 0) {
                $list[$k]["pay_status_name"] = '待付款';
            } else if ($v['pay_status'] == 2) {
                $list[$k]["pay_status_name"] = '已付款';
            } else if ($v['pay_status'] == 1) {
                $list[$k]["pay_status_name"] = '支付中';
            }
        }

        $arr = [];
        foreach ($list as $k => $v) {
            $v['pay_time']     = (int)$v['pay_time'] > 0 ? date('Y-m-d H:i:s', $v['pay_time']) : '未付款'; //付款时间
            $v['consign_time'] = (int)$v['consign_time'] > 0 ? date('Y-m-d H:i:s', $v['consign_time']) : '未发货'; //发货时间
            # 物流信息 考虑到后期订单oms编辑, 暂以主订单第一条物流信息为标准
            $logistics_info    = db('ns_order_goods_express')->where(['order_id' => $v['order_id']])->order('id asc')->find();
            $v['express_name'] = $logistics_info['express_name']; //物流公司名称
            $v['express_no']   = $logistics_info['express_no']; //物流单号
            $v['tx_type']      = $v['tx_type'] == 1 ? '大贸' : '跨境'; //交易类型（1：大贸，2：跨境）

            foreach ($v['order_item_list'] as $vv) {
                $tmp = array_merge($vv, $v);
                array_push($arr, $tmp);
            }
        }

        $xlsCell = [
            [
                'order_no',
                '订单编号'
            ],
            [
                'create_date',
                '下单时间'
            ],
            [
                'pay_time',
                '付款时间'
            ],
            [
                'consign_time',
                '发货时间'
            ],
            [
                'user_name',
                '会员昵称'
            ],
            [
                'user_tel',
                '会员手机号'
            ],
            [
                'receiver_name',
                '收货人姓名'
            ],
            [
                'receiver_mobile',
                '收货人电话'
            ],
            [
                'price',
                '商品原价'
            ],
            [
                'pay_money',
                '实际支付'
            ],
            [
                'adjust_money',
                '调整金额'
            ],
            [
                'source_branch_name',
                '来源网点'
            ],
            [
                'shipping_type_name',
                '配送方式'
            ],
            [
                'pay_status_name',
                '支付状态'
            ],
            [
                'status_name',
                '发货状态'
            ],

            [
                'express_name',
                '物流公司'
            ],
            [
                'express_no',
                '物流单号'
            ],
            [
                'tx_type',
                '物流类型'
            ],

            [
                'goods_name',
                '商品名称'
            ],
            [
                'sku_name',
                '商品规格'
            ],
            [
                'code',
                '商品编码'
            ],
            [
                'product_barcode',
                '商品条形码'
            ],
            [
                'material_code',
                'shopal物料编号'
            ],
            [
                'num',
                '购买数量'
            ],
            [
                'buyer_message',
                '买家留言'
            ],
            [
                'seller_memo',
                '卖家备注'
            ]
        ];
        $this->admin_user_record('礼品订单数据excel导出', '', '');

        dataExcel($xlsName, $xlsCell, $arr);
        exit;
    }

    /**
     * 礼品订单数据excel导出
     */
    public function vipOrderDataExcel()
    {
        $xlsName         = "礼品订单数据列表";
        $start_date      = request()->get('start_date') == "" ? 0 : getTimeTurnTimeStamp(request()->get('start_date'));
        $end_date        = request()->get('end_date') == "" ? 0 : getTimeTurnTimeStamp(request()->get('end_date'));
        $user_name       = request()->get('user_name', '');
        $order_no        = request()->get('order_no', '');
        $order_status    = request()->get('order_status', '');
        $receiver_mobile = request()->get('receiver_mobile', '');
        $source_branch   = request()->get('source_branch', '');
        $payment_type    = request()->get('payment_type', '');
        $order_ids       = request()->get("order_ids", "");

        if ($order_ids != "") {
            $condition["order_id"] = [
                "in",
                $order_ids
            ];
        }

        if ($start_date != 0 && $end_date != 0) {
            $condition["create_time"] = [
                [
                    ">",
                    $start_date
                ],
                [
                    "<",
                    $end_date
                ]
            ];
        } else if ($start_date != 0 && $end_date == 0) {
            $condition["create_time"] = [
                [
                    ">",
                    $start_date
                ]
            ];
        } else if ($start_date == 0 && $end_date != 0) {
            $condition["create_time"] = [
                [
                    "<",
                    $end_date
                ]
            ];
        }
        if (!empty($order_status)) {
            $condition['order_status'] = $order_status;
        }
        if (!empty($payment_type)) {
            $condition['payment_type'] = $payment_type;
        }
        if (!empty($user_name)) {
            $condition['receiver_name'] = $user_name;
        }
        if (!empty($order_no)) {
            $condition['order_no'] = $order_no;
        }
        if (!empty($receiver_mobile)) {
            $condition['receiver_mobile'] = $receiver_mobile;
        }
        if ($source_branch != '') {
            $condition['source_branch'] = $source_branch;
        }
        $condition['shop_id']    = $this->instance_id;
        $condition['order_type'] = 5; // 会员订单
        $condition['is_deleted'] = 0; // 未删除订单
        $order_service           = new OrderService();
        $list                    = $order_service->getExportOrderListNew($condition, 'create_time desc');

        foreach ($list as $k => $v) {
            $list[$k]["create_date"] = getTimeStampTurnTime($v["create_time"]); // 创建时间
            if ($v['shipping_type'] == 1) {
                $list[$k]["shipping_type_name"] = '商家配送';
            } else if ($v['shipping_type'] == 2) {
                $list[$k]["shipping_type_name"] = '门店自提';
            } else {
                $list[$k]["shipping_type_name"] = '';
            }
            if ($v['pay_status'] == 0) {
                $list[$k]["pay_status_name"] = '待付款';
            } else if ($v['pay_status'] == 2) {
                $list[$k]["pay_status_name"] = '已付款';
            } else if ($v['pay_status'] == 1) {
                $list[$k]["pay_status_name"] = '支付中';
            }
        }
        $arr = [];
        foreach ($list as $k => $v) {
            $v['pay_time']     = (int)$v['pay_time'] > 0 ? date('Y-m-d H:i:s', $v['pay_time']) : '未付款'; //付款时间
            $v['consign_time'] = (int)$v['consign_time'] > 0 ? date('Y-m-d H:i:s', $v['consign_time']) : '未发货'; //发货时间
            # 物流信息 考虑到后期订单oms编辑, 暂以主订单第一条物流信息为标准
            $logistics_info    = db('ns_order_goods_express')->where(['order_id' => $v['order_id']])->order('id asc')->find();
            $v['express_name'] = $logistics_info['express_name']; //物流公司名称
            $v['express_no']   = $logistics_info['express_no']; //物流单号
            $v['tx_type']      = $v['tx_type'] == 1 ? '大贸' : '跨境'; //交易类型（1：大贸，2：跨境）

            foreach ($v['order_item_list'] as $vv) {
                $tmp = array_merge($vv, $v);
                array_push($arr, $tmp);
            }
        }

        $xlsCell = [
            [
                'order_no',
                '订单编号'
            ],
            [
                'create_date',
                '下单时间'
            ],
            [
                'pay_time',
                '付款时间'
            ],
            [
                'user_name',
                '会员昵称'
            ],
            [
                'receiver_mobile',
                '电话'
            ],
            [
                'price',
                '商品原价'
            ],
            [
                'pay_money',
                '实际支付'
            ],
            [
                'adjust_money',
                '调整金额'
            ],
            [
                'source_branch_name',
                '来源网点'
            ],
            [
                'pay_status_name',
                '支付状态'
            ],
            [
                'status_name',
                '订单状态'
            ],
            [
                'goods_name',
                '商品名称'
            ],
            [
                'num',
                '购买数量'
            ],

            [
                'buyer_message',
                '买家留言'
            ],
            [
                'seller_memo',
                '卖家备注'
            ]
        ];
        $this->admin_user_record('vip订单数据excel导出', '', '');

        dataExcel($xlsName, $xlsCell, $arr);
        exit;
    }

    /**
     * 订单数据excel导出
     */
    public function virtualOrderDataExcel()
    {
        $xlsName = "订单数据列表";
        $xlsCell = [
            [
                'order_no',
                '订单编号'
            ],
            [
                'create_date',
                '日期'
            ],
            [
                'receiver_info',
                '收货人信息'
            ],
            [
                'order_money',
                '订单金额'
            ],
            [
                'pay_money',
                '实际支付'
            ],
            [
                'pay_type_name',
                '支付方式'
            ],
            [
                'pay_status_name',
                '支付状态'
            ],
            [
                'goods_info',
                '商品信息'
            ],
            [
                'buyer_message',
                '买家留言'
            ],
            [
                'seller_memo',
                '卖家备注'
            ]
        ];

        $start_date      = request()->get('start_date') == "" ? 0 : getTimeTurnTimeStamp(request()->get('start_date'));
        $end_date        = request()->get('end_date') == "" ? 0 : getTimeTurnTimeStamp(request()->get('end_date'));
        $user_name       = request()->get('user_name', '');
        $order_no        = request()->get('order_no', '');
        $order_status    = request()->get('order_status', '');
        $receiver_mobile = request()->get('receiver_mobile', '');
        $payment_type    = request()->get('payment_type', '');
        $order_ids       = request()->get("order_ids", "");

        if ($order_ids != "") {
            $condition["order_id"] = [
                "in",
                $order_ids
            ];
        }

        if ($start_date != 0 && $end_date != 0) {
            $condition["create_time"] = [
                [
                    ">",
                    $start_date
                ],
                [
                    "<",
                    $end_date
                ]
            ];
        } else if ($start_date != 0 && $end_date == 0) {
            $condition["create_time"] = [
                [
                    ">",
                    $start_date
                ]
            ];
        } else if ($start_date == 0 && $end_date != 0) {
            $condition["create_time"] = [
                [
                    "<",
                    $end_date
                ]
            ];
        }
        if ($order_status != '') {
            $condition['order_status'] = $order_status;
        }
        if (!empty($payment_type)) {
            $condition['payment_type'] = $payment_type;
        }
        if (!empty($user_name)) {
            $condition['receiver_name'] = $user_name;
        }
        if (!empty($order_no)) {
            $condition['order_no'] = $order_no;
        }
        if (!empty($receiver_mobile)) {
            $condition['receiver_mobile'] = $receiver_mobile;
        }
        $condition['shop_id']    = $this->instance_id;
        $condition['order_type'] = 2; // 虚拟订单
        $order_service           = new OrderService();
        $list                    = $order_service->getOrderList(1, 0, $condition, 'create_time desc');
        $list                    = $list["data"];

        foreach ($list as $k => $v) {
            $list[$k]["create_date"]   = getTimeStampTurnTime($v["create_time"]); // 创建时间
            $list[$k]["receiver_info"] = $v["user_name"] . "  " . $v["receiver_mobile"]; // 创建时间
            if ($v['pay_status'] == 0) {
                $list[$k]["pay_status_name"] = '待付款';
            } else if ($v['pay_status'] == 2) {
                $list[$k]["pay_status_name"] = '已付款';
            }
            $goods_info = "";
            foreach ($v["order_item_list"] as $t => $m) {
                $goods_info .= "商品名称:" . $m["goods_name"] . "  规格:" . $m["sku_name"] . "  商品价格:" . $m["price"] . "  购买数量:" . $m["num"] . "  ";
            }
            $list[$k]["goods_info"] = $goods_info;
        }
        dataExcel($xlsName, $xlsCell, $list);
        exit;
    }

    public function getOrderGoodsDetialAjax()
    {
        if (request()->isAjax()) {
            $order_goods_id = request()->post("order_goods_id", '');
            $order_goods    = new OrderGoods();
            $res            = $order_goods->getOrderGoodsRefundDetail($order_goods_id);
            return $res;
        }
    }

    public function getOrderGoodsDetialsAjax()
    {
        if (request()->isAjax()) {
            $order_goods_id = request()->post("order_goods_id", '');
            $order_goods    = new OrderGoods();
            $res            = $order_goods->getOrderGoodsRefundDetails($order_goods_id);
            return $res;
        }
    }

    /**
     * 收货
     */
    public function orderTakeDelivery()
    {
        $order_service = new OrderService();
        $order_id      = request()->post('order_id', '');
        $res           = $order_service->OrderTakeDelivery($order_id);
        return AjaxReturn($res);
    }

    /**
     * 删除订单
     */
    public function deleteOrder()
    {
        if (request()->isAjax()) {
            $title         = '删除订单';
            $order_service = new OrderService();
            $order_id      = request()->post("order_id", "");
            $res           = $order_service->deleteOrder($order_id, 1, $this->instance_id);
            $this->admin_user_record($title, $order_id, $res);
            return AjaxReturn($res);
        }
    }

    /**
     * 订单退款（测试）
     */
    public function orderrefundtest()
    {
        $weixin_pay = new WeiXinPay();
        $retval     = $weixin_pay->setWeiXinRefund($refund_no, $out_trade_no, $refund_fee, $total_fee);
        var_dump($retval);
    }

    /**
     * 支付宝退款（测试）
     * 创建时间：2017年10月17日 10:26:05
     */
    public function aliPayRefundtest()
    {
        $ali_pay = new AliPay();
        $retval  = $ali_pay->aliPayRefund(date("YmdHis", time()) . rand(100000, 999999), $out_trade_no, $refund_fee);
        $this->redirect($retval);
    }

    public function aliPayTransfer()
    {
        $ali_pay = new AliPay();
        $retval  = $ali_pay->aliPayTransfer(date("YmdHis", time()) . rand(100000, 999999), '595566388@qq.com', 1);
        $this->redirect($retval);
    }

    /**
     * 查询订单项实际可退款余额
     * 创建时间：2017年10月16日 09:57:56 王永杰
     */
    public function getOrderGoodsRefundBalance()
    {
        $order_goods_id = request()->post("order_goods_id", "");
        if (!empty($order_goods_id)) {
            $order_goods    = new OrderGoods();
            $refund_balance = $order_goods->orderGoodsRefundBalance($order_goods_id);
            return $refund_balance;
        }
        return 0;
    }

    /**
     * 查询当前订单的付款方式，用于进行退款操作时，选择退款方式
     * 创建时间：2017年10月16日 10:01:55 王永杰
     */
    public function getOrderTermsOfPayment()
    {
        $order_id = request()->post("order_id", "");
        if (!empty($order_id)) {
            $order        = new OrderService();
            $payment_type = $order->getTermsOfPaymentByOrderId($order_id);
            $type         = OrderStatus::getPayType($payment_type);
            $json         = [];
            if ($type == "微信支付") {
                $temp['type_id']   = 1;
                $temp['type_name'] = "微信";
                array_push($json, $temp);
                $temp['type_id']   = 10;
                $temp['type_name'] = "线下";
                array_push($json, $temp);
            } else if ($type == "支付宝") {
                $temp['type_id']   = 2;
                $temp['type_name'] = "支付宝";
                array_push($json, $temp);
                $temp['type_id']   = 10;
                $temp['type_name'] = "线下";
                array_push($json, $temp);
            } else {
                $temp['type_id']   = 10;
                $temp['type_name'] = "线下";
                array_push($json, $temp);
            }
            return json_encode($json);
        }
        return "";
    }

    /**
     * 检测支付配置是否开启，支付配置和原路退款配置都要开启才行（配置信息也要填写）
     * 创建时间：2017年10月17日 15:00:29 王永杰
     *
     * @return boolean
     */
    public function checkPayConfigEnabled()
    {
        $type = request()->post("type", "");
        if (!empty($type)) {
            $config  = new Config();
            $enabled = $config->checkPayConfigEnabled($this->instance_id, $type);
            return $enabled;
        }
        return "";
    }

    /**
     * 获取出货商品列表
     */
    public function getShippingList()
    {
        if (request()->isAjax()) {
            $order_ids   = request()->post("order_ids", "");
            $order_goods = new OrderGoods();
            $list        = $order_goods->getShippingList($order_ids);
            return $list;
        }
    }

    /**
     * 出货单打印页面
     */
    public function printpreviewOfInvoice()
    {
        $order_ids   = request()->get("order_ids", "");
        $order_goods = new OrderGoods();
        $list        = $order_goods->getShippingList($order_ids);
        $this->assign("list", $list);
        $webSiteInfo = $this->website->getWebSiteInfo();
        if (empty($webSiteInfo["title"])) {
            $ShopName = "Niushop开源商城";
        } else {
            $ShopName = $webSiteInfo["title"];
        }
        $this->assign("ShopName", $ShopName);
        $this->assign("now_time", time());
        return view($this->style . "Order/printpreviewOfInvoice");
    }

    /**
     * 添加临时物流信息
     */
    public function addTmpExpressInformation()
    {
        $order_goods     = new OrderGoods();
        $print_order_arr = request()->post("print_order_arr", "");
        $deliver_goods   = request()->post("deliver_goods", 0);
        $print_order_arr = json_decode($print_order_arr, true);
        $res             = $order_goods->addTmpExpressInformation($print_order_arr, $deliver_goods);
        return $res;
    }

    /**
     * 获取未发货的订单
     */
    public function getNotshippedOrderList()
    {
        $order_ids = request()->post("ids", "");
        $order     = new OrderService();
        $list      = $order->getNotshippedOrderByOrderId($order_ids);
        return $list;
    }

    /**
     * 打印订单
     */
    public function printOrder()
    {
        // 网站信息
        $web_info = $webSiteInfo = $this->website->getWebSiteInfo();
        $this->assign("web_info", $web_info);
        $order_ids     = request()->get("print_order_ids", "");
        $order_service = new OrderService();
        $condition     = [
            "order_id"   => [
                "in",
                $order_ids
            ],
            "shop_id"    => $this->instance_id,
            'order_type' => [
                "in",
                "1,3"
            ]
        ];
        $list          = $order_service->getOrderList(1, 0, $condition, '');
        foreach ($list["data"] as $k => $v) {
            $order_detail                          = $order_service->getOrderDetail($v["order_id"]);
            $list["data"][$k]["goods_packet_list"] = $order_detail["goods_packet_list"];
        }
        $this->assign("order_list", $list['data']);
        return view($this->style . "Order/printOrder");
    }

    /**
     * 打印虚拟订单
     */
    public function printVirtualOrder()
    {
        // 网站信息
        $web_info = $webSiteInfo = $this->website->getWebSiteInfo();
        $this->assign("web_info", $web_info);
        $order_ids     = request()->get("print_order_ids", "");
        $order_service = new OrderService();
        $condition     = [
            "order_id"   => [
                "in",
                $order_ids
            ],
            "shop_id"    => $this->instance_id,
            'order_type' => 2
        ];
        $list          = $order_service->getOrderList(1, 0, $condition, '');
        $this->assign("order_list", $list['data']);
        return view($this->style . "Order/printVirtualOrder");
    }

    function order_refund_push($type, $id, $from_plafrom = 'BC')
    {
        require(VENDOR_PATH . 'sdk/Client.php');
        $option = ['token' => $from_plafrom];  # token 必传固定参数'BC'
        if ($type == 1) { //1:付款；2：退款
            $order_client = \order\Push::_instance($option);
        } else if ($type == 2) {
            $order_client = \refund\Push::_instance($option);
        }
        $ret = $order_client->push($id);
        // file_put_contents("signxml.txt", var_export($ret,true)."\r\n",8);
        return $ret;
    }


    //线下支付订单信息
    public function orderInfo()
    {
        $order_id     = request()->post('order_id');
        $orderModel   = new NsOrderModel();
        $order_detail = $orderModel->getInfo(["order_id" => $order_id]);
        return $order_detail;
    }

    /**
     * 线下支付
     */
    public function orderOffLinePay()
    {
        $order_service = new OrderService();
        $order_id      = request()->post('order_id', '');
        $pay_type      = request()->post('pay_type', '');
        $pay_way       = request()->post('pay_way', '');
        $pay_money     = request()->post('pay_money', '');
        $pay_pic       = request()->post('pay_pic', '');
        $memo          = request()->post('memo', '');
        $order_info    = $order_service->getOrderInfo($order_id);
        if ($order_info['payment_type'] == 6) {
            $res = $order_service->orderOffLinePay($order_id, 6, 0);
        } else {
            if ($pay_type == 1) {
                $payment_type = 10;
            } else {
                $payment_type = 11;
            }
            $res = $order_service->orderOffLinePayNew($order_id, $pay_type, $pay_way, $pay_money, $pay_pic, $payment_type, $memo);
            //$id = $order_service->addOrderOffLinePay($order_id, $pay_type, $pay_way, $pay_money, $pay_pic);
            if ($res > 0) {
                order_refund_push(1, $order_id);//推送给OMS
                if (!empty($pay_pic)) {
                    $this->cronTabUpImgDay($pay_pic, $res);
                }
            }
        }
        return AjaxReturn($res);
    }

    /**
     * @param $id_face_pros
     * @param $id_face_cons
     * @param $uid
     * 新增私密图片
     */
    public function cronTabUpImgDay($id_face_pros, $id)
    {
        $img1     = file_get_contents($id_face_pros);
        $address  = 'application/images/' . $id;
        $fileName = $address . '_线下支付图.jpg';
        file_put_contents($fileName, $img1);
        $this->upPrivateIMG(4, $fileName, $id);
    }

    //新增私密图片
    public function upPrivateIMG($type, $file_path, $id)
    {
        #type:1银行卡 2身份证正面 3身份证反面 4线下支付凭证
        $accessKey = '_xBTRsUTy2VR_qH5JNjspfBakRzTIv7YLsV3Fjup';
        $secretKey = '09F9mdbtGnCN1oTCXExVjpb2N79Qp5rgFye37CmE';

        $auth = new Auth($accessKey, $secretKey);

        // 要转码的文件所在的空间
        $bucket = 'bcids';

        //自定义上传回复的凭证 返回的数据
        $returnBody = '{"key":"$(key)","hash":"$(etag)","fsize":$(fsize),"bucket":"$(bucket)","name":"$(fname)"}';
        $policy     = [
            'returnBody' => $returnBody,
        ];

        //token过期时间
        $expires = 0;

        // 生成上传 Token
        $token = $auth->uploadToken($bucket, null, $expires, $policy, true);

        $filePath = $file_path;
        $key      = $id . time() . '_线下支付图.jpg';


        $uploadMgr = new UploadManager();   // 调用 UploadManager 的 putFile 方法进行文件的上传。

        list($ret, $err) = $uploadMgr->putFile($token, $key, $filePath);

        if ($err !== null) {
            return false;
        } else {
            $data = [
                'uid'        => $id,
                'fname'      => $key,
                'key'        => $ret['key'],
                'type'       => $type,
                'url'        => $this->getIMG($key),
                'createTime' => time()
            ];

            return \think\Db::name('bc_private_img')->insert($data);
        }
    }

    //获取图片url
    public function getIMG($file_name)
    {
        $accessKey = '_xBTRsUTy2VR_qH5JNjspfBakRzTIv7YLsV3Fjup';
        $secretKey = '09F9mdbtGnCN1oTCXExVjpb2N79Qp5rgFye37CmE';
        $auth      = new Auth($accessKey, $secretKey);
        $baseUrl   = 'http://private.bonnieclyde.cn/' . $file_name;
        $signedUrl = $auth->privateDownloadUrl($baseUrl);
        return $signedUrl;
    }


    //获取订单线下付款信息
    public function getOrderOffLinePay()
    {
        $order_id             = request()->post('order_id');
        $orderOfflinePayModel = new BcOrderOfflinePayModel();
        $orderOfflinePayInfo  = $orderOfflinePayModel->getInfo([
            'order_id' => $order_id
        ], "*");
        if (!empty($orderOfflinePayInfo)) {
            $url_info = \think\Db::name('bc_private_img')->where(['uid' => $order_id, 'type' => 4])->find();
            $time     = $url_info['createTime'] + 3600;
            if (time() >= $time) {
                $url                            = $this->useUpdateIMG($url_info, 4, $orderOfflinePayInfo['id']);
                $orderOfflinePayInfo["pay_pic"] = $url;
            } else {
                $orderOfflinePayInfo["pay_pic"] = $url_info['url'];
            }

        }
        return $orderOfflinePayInfo;
    }

    //获取支付凭证
    public function getPayPic()
    {
        $pay_id               = request()->post('dataid');
        $orderOfflinePayModel = new BcOrderOfflinePayModel();
        $orderOfflinePayInfo  = $orderOfflinePayModel->getInfo([
            'id' => $pay_id
        ], "*");
        if (!empty($orderOfflinePayInfo)) {
            $url_info = \think\Db::name('bc_private_img')->where(['uid' => $pay_id, 'type' => 4])->find();
            $time     = $url_info['createTime'] + 3600;
            if (time() >= $time) {
                $url     = $this->useUpdateIMG($url_info, 4, $pay_id);
                $pay_pic = $url;
            } else {
                $pay_pic = $url_info['url'];
            }

        }
        return $pay_pic;
    }

    //更新私密图片
    public function useUpdateIMG($file_name, $type, $pay_id)
    {
        $accessKey = '_xBTRsUTy2VR_qH5JNjspfBakRzTIv7YLsV3Fjup';
        $secretKey = '09F9mdbtGnCN1oTCXExVjpb2N79Qp5rgFye37CmE';
        $bucket    = 'bcids';

        $key                = explode('.', $file_name['fname'])[0];
        $auth               = new Auth($accessKey, $secretKey);
        $bucketManager      = new \Qiniu\Storage\BucketManager($auth);
        $srcBucket          = $bucket;
        $destBucket         = $bucket;
        $srcKey             = $key . '.jpg';
        $time               = time();
        $destKey            = $pay_id . $time . '_' . explode('_', $key)[1] . ".jpg";
        $err                = $bucketManager->move($srcBucket, $srcKey, $destBucket, $destKey, true);
        $data['fname']      = $data['key'] = $destKey;
        $data['createTime'] = $time;
        $data['url']        = $this->getIMG($destKey);
        if (!$err) {
            \think\Db::name('bc_private_img')->where(['uid' => $pay_id, 'type' => $type])->update($data);
            $this->clearQiNiu($file_name['url'], $file_name['fname']);
        }
        return $data['url'];
    }

    //更新时清除七牛云原私有图片
    public function clearQiNiu($url, $fname)
    {
        $accessKey = '_xBTRsUTy2VR_qH5JNjspfBakRzTIv7YLsV3Fjup';
        $secretKey = '09F9mdbtGnCN1oTCXExVjpb2N79Qp5rgFye37CmE';
        $auth      = new Auth($accessKey, $secretKey);
        //待刷新的文件列表和目录，文件列表最多一次100个，目录最多一次10个
        //参考文档：http://developer.qiniu.com/article/fusion/api/refresh.html
        $urls = [];
        array_push($urls, str_replace(substr($fname, -20, 16), urlencode(substr($fname, -20, 16)), $url));
        $cdnManager = new CdnManager($auth);
        list($refreshResult, $refreshErr) = $cdnManager->refreshUrls($urls);
        if ($refreshErr != null) {
            var_dump($refreshErr);
        } else {
            echo "refresh request sent\n";
            print_r($refreshResult);
        }

    }

    //更新私密图片
    public function updateIMG()
    {
        usleep(100);
        $pay_id    = request()->post("dataid");
        $file_name = \think\Db::name('bc_private_img')->where(['uid' => $pay_id, 'type' => 4])->find();
        $this->useUpdateIMG($file_name, 4, $pay_id);
    }


    //同步历史线下支付信息(pay_money pay_time)
    public function updateOrderOffLinePay()
    {
        $orderOfflinePayModel = new BcOrderOfflinePayModel();
        $orderOfflinePayList  = $orderOfflinePayModel->getQuery('', '*', 'id');
        $orderModel           = new NsOrderModel();
        foreach ($orderOfflinePayList as $k => $v) {
            $orderInfo = $orderModel->getInfo(['order_id' => $v['order_id']], 'pay_money,pay_time');
            $data      = [
                'pay_money'   => $orderInfo['pay_money'],
                'create_time' => $orderInfo['pay_time']
            ];
            $result    = $orderOfflinePayModel->update($data, ['order_id' => $v['order_id']]);

            $str = $v['order_id'] . ' ===> ' . $orderInfo['pay_money'] . ' ===> ' . $orderInfo['pay_time'] . ' ===> ' . $result .PHP_EOL;
            cdebug(
                $str, 'temp.log'
            );
        }
    }

    //同步历史线下付款图到private
    public function cronTabUpImg()
    {
        $list = \think\Db::name('bc_order_offline_pay')->select();
        foreach ($list as $v) {
            $img      = file_get_contents($v['pay_pic']);
            $address  = 'application/images/' . $v['id'];
            $fileName = $address . '_线下支付图.jpg';
            file_put_contents($fileName, $img);
            if ($this->upPrivateIMG(4, $fileName, $v['id']) == false) {
                $this->upPrivateIMG(4, $fileName, $v['id']);
            }
        }
    }
}
