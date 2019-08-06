<?php
namespace app\admin\controller;
use data\model\BcPospalUserRecordModel;
use data\model\BcPospalUserSyncRecordModel;
use data\model\BcPospalGoodsAddRecordModel;
use data\model\BcPospalGoodsUpdateRecordModel;
use data\model\BcPospalStockInfoModel;
use data\model\BcPospalTicketPushModel;
use data\model\NsMemberModel;


/**
 * 银豹log
 *
 * @author Administrator
 *
 */
class Pospal extends BaseController
{

    private $model;
    private $sync_model;
    private $sync_pro_model;
    private $sync_update_pro_model;
    private $sync_stock_info_model;
    private $ticket_push;
    private $member;

    public function __construct()
    {
        parent::__construct();
        $this->model                 = new BcPospalUserRecordModel();
        $this->sync_model            = new BcPospalUserSyncRecordModel();
        $this->sync_pro_model        = new BcPospalGoodsAddRecordModel();
        $this->sync_update_pro_model = new BcPospalGoodsUpdateRecordModel();
        $this->sync_stock_info_model = new BcPospalStockInfoModel();
        $this->ticket_push           = new BcPospalTicketPushModel();
        $this->member                = new NsMemberModel();
    }

    public function pospalIndex(){
        if (request()->isAjax()) {
            $page_index          = request()->post('page_index', 1);
            $page_size           = request()->post('page_size', PAGESIZE);
            $search_text         = request()->post('search_text', '');
            if($search_text){
                $condition     = array(
                    'nm.member_name|bpur.customer_num' => array(
                        'like',
                        '%' . $search_text . '%'
                    ),
                );
            }
            $data       = $this->model->getViewList($page_index,$page_size,$condition,'bpur.create_time desc');
            return $data;
        } else {
            $child_menu_list = array(
                array(
                    'url'       => "pospal/pospalIndex",
                    'menu_name' => "新增用户",
                    "active"    => 1
                ),
                array(
                    'url'       => "pospal/syncUserPoint",
                    'menu_name' => "积分变动",
                    "active"    => 0
                ),
            );
            $this->assign('child_menu_list', $child_menu_list);
            return view($this->style . 'AdminLog/pospalIndex');
        }
    }


    public function syncUserPoint(){
        if (request()->isAjax()) {
            $page_index          = request()->post('page_index', 1);
            $page_size           = request()->post('page_size', PAGESIZE);

            $condition['type'] = 1;
            $data       = $this->ticket_push->getViewList($page_index,$page_size,$condition,'cptp.created desc');
            if($data['data']) foreach($data['data'] as $key=>$value){
                $_v                                    = json_decode($value['body'], true);
                $user_info                             = $this->model->getInfo(['customer_uid' => $_v['customerUid']], 'user_tel,uid,customer_num');
                $member_info                           = $this->member->getInfo(['uid' => $user_info['uid']], 'member_name');
                $data['data'][$key]['member_name']     = $member_info['member_name'];
                $data['data'][$key]['customer_num']    = $user_info['customer_num'];
                $data['data'][$key]['user_tel']        = $user_info['user_tel'];
                $data['data'][$key]['create_time']     = date('Y-m-d H:i:s', $value['created']);
                $data['data'][$key]['gaintPoint']      = $_v['customerPointGaintLogs'][0]['gaintPoint'];
                $data['data'][$key]['afterGaintPoint'] = $_v['customerPointGaintLogs'][0]['afterGaintPoint'];
                $data['data'][$key]['origin']          = '销售';
            }
            return $data;
        } else {
            $child_menu_list = array(
                array(
                    'url'       => "pospal/pospalIndex",
                    'menu_name' => "新增用户",
                    "active"    => 0
                ),
                array(
                    'url'       => "pospal/syncUserPoint",
                    'menu_name' => "积分变动",
                    "active"    => 1
                ),
            );
            $this->assign('child_menu_list', $child_menu_list);
            return view($this->style . 'AdminLog/syncUserPoint');
        }
    }

    public function syncUser(){
        if (request()->isAjax()) {
            $page_index          = request()->post('page_index', 1);
            $page_size           = request()->post('page_size', PAGESIZE);
//            $search_text         = request()->post('search_text', '');
//            if($search_text){
//                $condition     = array(
//                    'nm.member_name|bpur.customer_num' => array(
//                        'like',
//                        '%' . $search_text . '%'
//                    ),
//                );
//            }
            $data       = $this->sync_model->getViewList($page_index,$page_size,'','bpusr.created desc');
            return $data;
        } else {
            return view($this->style . 'AdminLog/syncUser');
        }
    }


    public function syncProducts(){
        if (request()->isAjax()) {
            $page_index          = request()->post('page_index', 1);
            $page_size           = request()->post('page_size', PAGESIZE);
//            $search_text         = request()->post('search_text', '');
//            if($search_text){
//                $condition     = array(
//                    'nm.member_name|bpur.customer_num' => array(
//                        'like',
//                        '%' . $search_text . '%'
//                    ),
//                );
//            }

            $data       = $this->sync_pro_model->getViewList($page_index,$page_size,'','cpgar.created desc');
            return $data;
        } else {

            $child_menu_list = array(
                array(
                    'url' => "pospal/syncProducts",
                    'menu_name' => "商品新增",
                    "active" => 1
                ),
                array(
                    'url' => "pospal/syncUpdateProducts",
                    'menu_name' => "商品更新",
                    "active" => 0
                ),
            );
            $this->assign('child_menu_list', $child_menu_list);
            return view($this->style . 'AdminLog/syncProducts');
        }

    }


    public function syncUpdateProducts(){
        if (request()->isAjax()) {
            $page_index          = request()->post('page_index', 1);
            $page_size           = request()->post('page_size', PAGESIZE);
//            $search_text         = request()->post('search_text', '');
//            if($search_text){
//                $condition     = array(
//                    'nm.member_name|bpur.customer_num' => array(
//                        'like',
//                        '%' . $search_text . '%'
//                    ),
//                );
//            }

            $data       = $this->sync_update_pro_model->getViewList($page_index,$page_size,'','cpgur.created desc');
            return $data;
        } else {

            $child_menu_list = array(
                array(
                    'url' => "pospal/syncProducts",
                    'menu_name' => "商品新增",
                    "active" => 0
                ),
                array(
                    'url' => "pospal/syncUpdateProducts",
                    'menu_name' => "商品更新",
                    "active" => 1
                ),
            );
            $this->assign('child_menu_list', $child_menu_list);
            return view($this->style . 'AdminLog/syncUpdateProducts');
        }

    }

    public function syncStock(){
        if (request()->isAjax()) {
            $page_index          = request()->post('page_index', 1);
            $page_size           = request()->post('page_size', PAGESIZE);
//            $search_text         = request()->post('search_text', '');
//            if($search_text){
//                $condition     = array(
//                    'nm.member_name|bpur.customer_num' => array(
//                        'like',
//                        '%' . $search_text . '%'
//                    ),
//                );
//            }

            $condition['type'] = 1;

            $data       = $this->sync_stock_info_model->getViewList($page_index,$page_size,$condition,'cpsi.created desc');
            return $data;
        } else {

            $child_menu_list = array(
                array(
                    'url' => "pospal/syncStock",
                    'menu_name' => "入库单日志",
                    "active" => 1
                ),
                array(
                    'url' => "pospal/syncUpdateStock",
                    'menu_name' => "出库单日志",
                    "active" => 0
                ),
            );
            $this->assign('child_menu_list', $child_menu_list);
            return view($this->style . 'AdminLog/syncStock');
        }

    }

    public function syncUpdateStock(){
        if (request()->isAjax()) {
            $page_index          = request()->post('page_index', 1);
            $page_size           = request()->post('page_size', PAGESIZE);
//            $search_text         = request()->post('search_text', '');
//            if($search_text){
//                $condition     = array(
//                    'nm.member_name|bpur.customer_num' => array(
//                        'like',
//                        '%' . $search_text . '%'
//                    ),
//                );
//            }
            $condition['type'] = 2;
            $data       = $this->sync_stock_info_model->getViewList($page_index,$page_size,$condition,'cpsi.created desc');
            return $data;
        } else {

            $child_menu_list = array(
                array(
                    'url' => "pospal/syncStock",
                    'menu_name' => "入库单日志",
                    "active" => 0
                ),
                array(
                    'url' => "pospal/syncUpdateStock",
                    'menu_name' => "出库单日志",
                    "active" => 1
                ),
            );
            $this->assign('child_menu_list', $child_menu_list);
            return view($this->style . 'AdminLog/syncUpdateStock');
        }

    }

    /**
     * @return \data\model\unknown|\think\response\View
     * 推送
     */
    public function ticketPush()
    {
        if (request()->isAjax()) {
            $page_index = request()->post('page_index', 1);
            $page_size  = request()->post('page_size', PAGESIZE);
//            $search_text         = request()->post('search_text', '');
//            if($search_text){
//                $condition     = array(
//                    'nm.member_name|bpur.customer_num' => array(
//                        'like',
//                        '%' . $search_text . '%'
//                    ),
//                );
//            }
            $condition['cmd']  = 'ticket.new';
            $data              = $this->ticket_push->getViewList($page_index, $page_size, $condition, 'cptp.created desc');
            return $data;
        } else {

            $child_menu_list = array(
                array(
                    'url' => "pospal/ticketPush",
                    'menu_name' => "订单推送",
                    "active" => 1
                ),
                array(
                    'url' => "pospal/productPush",
                    'menu_name' => "商品推送",
                    "active" => 0
                ),
            );
            $this->assign('child_menu_list', $child_menu_list);
            return view($this->style . 'AdminLog/ticketPush');
        }
    }


    /**
     * @return \data\model\unknown|\think\response\View
     * 推送
     */
    public function productPush(){
        if (request()->isAjax()) {
            $page_index          = request()->post('page_index', 1);
            $page_size           = request()->post('page_size', PAGESIZE);
//            $search_text         = request()->post('search_text', '');
//            if($search_text){
//                $condition     = array(
//                    'nm.member_name|bpur.customer_num' => array(
//                        'like',
//                        '%' . $search_text . '%'
//                    ),
//                );
//            }
            $condition['cmd']  = 'product.edit';
            $data       = $this->ticket_push->getViewList($page_index,$page_size, $condition,'cptp.created desc');
            return $data;
        } else {

            $child_menu_list = array(
                array(
                    'url'       => "pospal/ticketPush",
                    'menu_name' => "订单推送",
                    "active"    => 0
                ),
                array(
                    'url'       => "pospal/productPush",
                    'menu_name' => "商品推送",
                    "active"    => 1
                ),
            );
            $this->assign('child_menu_list', $child_menu_list);
            return view($this->style . 'AdminLog/productPush');
        }
    }

    # 查看货流详情
    public function seeDetail(){
        $stock_flow_id  = request()->post('stock_flow_id', '');
        $id             = request()->post('id', '');
        if(!$stock_flow_id){
            $condition['id'] = $id;
        }else{
            $condition['stock_flow_id'] = $stock_flow_id;
        }

        $lists = $this->sync_stock_info_model->getQuery($condition,'*','created desc');
        return $lists;
    }



}