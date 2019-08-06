<?php

namespace app\admin\controller;

use data\model\BcDistributorAccountRecordsModel;
use data\model\BcDistributorStarModel;
use data\model\BcDistributorViewModel;
use data\model\NsMemberViewModel;
use data\model\NsOrderModel;
use data\service\Distributor as DistributorService;
use data\service\Events;
use data\service\Member;
use data\service\Order;
use data\service\Order as OrderService;
use data\service\DistributorStar as DistributorStarService;
use data\service\Store;
use data\service\Config as WebConfig;
use data\service\Order\OrderStatus;
use Qiniu\Auth;
use Qiniu\Cdn\CdnManager;
use Qiniu\Storage\UploadManager;
use data\service\Member as MemberService;
use data\model\BcDistributorModel;

/**
 * cms内容管理系统
 */
class Distributor extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }

    //极选师提现列表
    public function kolWithdrawList()
    {
        if (request()->isAjax()) {
            $member     = new MemberService();
            $pageindex  = request()->post('pageIndex', '');
            $user_phone = request()->post('user_phone', '');
            if ($user_phone != "") {
                $condition["mobile"] = array(
                    "like",
                    "" . $user_phone . "%"
                );
            }
            $condition["shop_id"] = $this->instance_id;
            $list                 = $member->getMemberBalanceWithdraw($pageindex, PAGESIZE, $condition, 'ask_for_date desc');
//            if (! empty($list['data'])) {
//                foreach ($list['data'] as $k => $v) {
//                    if ($_SESSION['niu']['adminuid'] == 3946) {
//                        $list['data'][$k]['show_type'] = 1;
//                    }else{
//                        $list['data'][$k]['show_type'] = 0;
//                    }
//                }
//            }
            return $list;
        } else {
            $config_service = new WebConfig();
            $data1          = $config_service->getTransferAccountsSetting($this->instance_id, 'wechat');
            $data2          = $config_service->getTransferAccountsSetting($this->instance_id, 'alipay');
            if (!empty($data1)) {
                $wechat = json_decode($data1['value'], true);
            }
            if (!empty($data2)) {
                $alipay = json_decode($data2['value'], true);
            }
            $this->assign("wechat", $wechat);
            $this->assign("alipay", $alipay);

            $child_menu_list = array(
                array(
                    'url' => "Distributor/kolWithdrawList",
                    'menu_name' => "提现列表",
                    "active" => 1
                ),
                array(
                    'url' => "Config/memberwithdrawsetting",
                    'menu_name' => "提现设置",
                    "active" => 0
                )
            );
            $this->assign("child_menu_list", $child_menu_list);
            $this->admin_user_record('查看极选师提现列表','','');
            return view($this->style . "Kol/kolWithdrawList");
        }
    }

    //通过极选师提现请求
    public function kolWithdrawAudit()
    {
        $id              = request()->post('id', '');
        $status          = request()->post('status', '');
        $transfer_type   = request()->post('transfer_type', '');
        $transfer_name   = request()->post('transfer_name', '');
        $transfer_money  = request()->post('transfer_money', '');
        $transfer_remark = request()->post('transfer_remark', '');
        $distributor     = new DistributorService();
        $retval          = $distributor->kolWithdrawAudit($this->instance_id, $id, $status, $transfer_type, $transfer_name, $transfer_money, $transfer_remark);
        $this->admin_user_record('通过极选师提现请求',$id,'');
        return $retval;
    }

    //拒绝极选师提现请求
    public function kolWithdrawRefuse()
    {
        $id          = request()->post('id', '');
        $status      = request()->post('status', '');
        $remark      = request()->post('remark', '');
        $distributor = new DistributorService();
        $retval      = $distributor->kolWithdrawRefuse($this->instance_id, $id, $status, $remark);
        if ($retval['id'] > 0) {
            $this->kolWithdrawSendTemplate($retval);
        }
        $this->admin_user_record('拒绝极选师提现请求',$id,'');
        return AjaxReturn($retval['id'], $retval);
    }

    //极选师提现审核通知
    public function kolWithdrawSendTemplate($array)
    {
        $template_id   = getWxTemplateId('kol_withdraw');
        $template_info = \think\Db::name('ns_template_push')->where(['out_trade_no' => $array['id'], 'is_send' => 0, 'warn_type' => 10])->find();
        $conf          = json_decode(\think\Db::name('sys_config')->where([
            'key' => 'SHOPAPPLET'
        ])->find()['value'], true);
        $access_token  = getAccessToken($conf['appid'], $conf['appsecret']);
        $select_url    = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;
        $openid        = $template_info['open_id'];
        $fid           = $template_info['form_id'];
        $p1            = $array['cash'];
        $p2            = $array['realname'];
        $p3            = getTimeStampTurnTime($array['ask_for_date']);
        $p4            = '微信到零钱';
        $p5            = $array['transfer_remark'];
        $page          = '/pages/member/member/member';
        $param         = <<<EOL
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
      } ,
      "keyword5": {
          "value": "$p5"
      }
  },
  "color":"#ccc"
}
EOL;
        curl_post($select_url, $param);
        $res['is_send'] = 1;
        \think\Db::name('ns_template_push')->where(['id' => $template_info['id']])->update($res);
    }

    // 极选师列表
    public function kolList()
    {
        if (request()->isAjax()) {
            $page_index    = request()->post('page_index', 1);
            $page_size     = request()->post('page_size', PAGESIZE);
            $source_branch = request()->post('source_branch', '');
            $search_text   = request()->post('search_text', '');
            $condition     = array(
                'nm.real_name|su.nick_name|su.user_tel' => array(
                    'like',
                    '%' . $search_text . '%'
                ),
                'nm.distributor_type' => [
                    [
                        ">",
                        1
                    ]
                ]
            );
            if ($source_branch != '') {
                $condition['nm.source_branch'] = $source_branch;
            }
            $distributor = new DistributorService();
            $list        = $distributor->getDistributorList($page_index, $page_size, $condition, 'create_time desc');
            return $list;
        } else {
            //来源网点列表
            $store     = new Store();
            $storeList = $store->getStore([], 'store_id,store_name', 'store_id');
            $this->assign('storeList', $storeList);
            $this->admin_user_record('查看极选师列表','','');
            return view($this->style . 'Kol/kolList');
        }
    }

    //TODO 生成极选师激活码
    public function kolActivationCode(){
        $distributorList = \think\Db::name('bc_distributor')->select();
        foreach($distributorList as $key=>$val){
            $distributorService = new DistributorService();
            $distributorModel = new BcDistributorModel();
            $distributorModel->save(['activation_code'=>$distributorService->getActivationCode($val['id'])],['id'=>$val['id']]);
        }
    }

    /**
     * 会员数据excel导出
     */
    public function kolDataExcel()
    {
        $xlsName     = "极选师数据列表";
        $xlsCell     = array(
            array('real_name', '姓名'),
            array('nick_name', '昵称'),
            array('user_tel', '手机'),
            array('create_time', '加入时间'),
            array('inviter_number_count', '邀请人数'),
            array('member_number_count', '绑定会员数'),
            array('order_number_count', '累计成交订单(笔)'),
            array('goods_money_sum', '累计成交金额(元)'),
        );
        $source_branch = request()->get('source_branch', '');
        $search_text   = request()->get('search_text', '');
        $condition     = array(
            'nm.real_name|su.nick_name|su.user_tel' => array(
                'like',
                '%' . $search_text . '%'
            ),
            'nm.distributor_type' => [
                [
                    ">",
                    1
                ]
            ]
        );
        if ($source_branch != '') {
            $condition['nm.source_branch'] = $source_branch;
        }
        $distributor = new DistributorService();
        $list        = $distributor->getDistributorList(1, 0, $condition, 'create_time desc');
        $list   = $list["data"];
        foreach ($list as $k => $v) {
            $list[$k]["create_time"] = getTimeStampTurnTime($v["create_time"]);
        }
        $this->admin_user_record('会员数据excel导出','','');

        dataExcel($xlsName, $xlsCell, $list);
    }

    //生成
    public function getWxCode()
    {
        $uid            = request()->post('uid', 0);
        $url            = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . 'wxd145d8a6e951dd1b' . "&secret=" . '9e22a3ac6f4c0ccae03a2356e710d68f';
        $res            = $this->send_post($url, '');
        $AccessToken    = json_decode($res, true);
        $AccessToken    = $AccessToken['access_token'];
        $url            = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=" . $AccessToken;
        $post_data      =
            array(
                'scene' => $uid,
                'page' => 'pages/index/index',
                'width' => 430,
            );
        $post_data      = json_encode($post_data);
        $data           = $this->send_post($url, $post_data);
        $result['url']  = $this->data_uri($data, 'image/png');
        $result['code'] = 1;
        $this->admin_user_record('生成极选师二维码',$uid,'');
        return $result;
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
     * checkKolStatus
     */
    public function checkKolStatus()
    {
        $kol = new \data\service\Kol();
        $uid = request()->post('uid', '');
        if (!is_numeric($uid)) {
            $this->error('未获取到信息');
        }
        $condition  = ['uid' => $uid];
        $kol_status = $kol->getKolInfo($condition, 'kol_status');
        if ($kol_status == 1) {
            $res = \think\Db::name('bc_kol')->where(['uid' => $uid])->update(['kol_status' => 0]);
        } else {
            $res = \think\Db::name('bc_kol')->where(['uid' => $uid])->update(['kol_status' => 1]);
        }
        if (!$res) $this->error('操作失败');
        return view($this->style . 'Kol/kolList');
    }

    /**
     * 极选师锁定
     */
    public function kolLock()
    {
        $uid    = request()->post('id', '');
        $retval = 0;
        if (!empty($uid)) {
            $distributor = new DistributorService();
            $retval      = $distributor->distributorLock($uid);
        }
        $this->admin_user_record('极选师锁定',$uid,'');
        return AjaxReturn($retval);
    }

    /**
     * 极选师解锁
     */
    public function kolUnlock()
    {
        $uid    = request()->post('id', '');
        $retval = 0;
        if (!empty($uid)) {
            $distributor = new DistributorService();
            $retval      = $distributor->distributorUnlock($uid);
        }
        $this->admin_user_record('极选师解锁',$uid,'');
        return AjaxReturn($retval);
    }

    public function update()
    {
        $order_service = new OrderService();
        $retval        = $order_service->update();
        return AjaxReturn($retval);
    }

    public function updateData()
    {
        $distributorService = new DistributorService();
        $distributorList = $distributorService->distributorList([], '*', 'create_time');
        foreach ($distributorList as $k => $v) {
            $distributorAccountList = $distributorService->distributorAccountList(['uid'=>$v['uid']], '*', 'id');
            $balance_record = 0;
            $bonus_record = 0;
            foreach ($distributorAccountList as $key => $val) {
                if($val['account_type'] == 1){
                    $balance_record += $val['money'];
                }else{
                    $bonus_record += $val['money'];
                }
                $data = [
                    'balance_record'=>$balance_record,
                    'bonus_record'=>$bonus_record
                ];
                $distributor_model = new BcDistributorAccountRecordsModel();
                $result = $distributor_model->save($data,['id'=>$val['id']]);
            }

        }
        return $result;
    }

    /**
     * 极选师订单列表
     */
    public function kolOrderList()
    {
        if (request()->isAjax()) {
            $page_index          = request()->post('page_index', 1);
            $page_size           = request()->post('page_size', PAGESIZE);
            $order_no            = request()->post('order_no', '');
            $start_date          = request()->post('start_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('start_date'));
            $end_date            = request()->post('end_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('end_date'));
            $source_branch       = request()->post('source_branch', '');
            $source_distribution = request()->post('source_distribution', '');
            $order_status        = request()->post('order_status', '');
            $search_text         = request()->post('search_text', '');
            if ($start_date != 0 && $end_date != 0) {
                $condition["no.consign_time"] = [
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
                $condition["no.consign_time"] = [
                    [
                        ">",
                        $start_date
                    ]
                ];
            } elseif ($start_date == 0 && $end_date != 0) {
                $condition["no.consign_time"] = [
                    [
                        "<",
                        $end_date
                    ]
                ];
            }

            if (!empty($search_text)) {
                $condition['bd.real_name'] = [
                    'like',
                    '%' . $search_text . '%'
                ];
            }

            if (!empty($order_no)) {
                $condition['no.order_no'] = $order_no;
            }
            if ($source_branch != '') {
                $condition['no.source_branch'] = $source_branch;
            }

            if ($source_distribution != '') {
                $condition['no.source_distribution'] = $source_distribution;
            } else {
                $condition['no.source_distribution'] = [
                    ">",
                    0
                ];
            }
            if ($order_status != '') {
                $condition['no.order_status'] = $order_status;
            } else {
                $condition['no.pay_status']   = 2;
                $condition['no.order_status'] = ['neq', 5];
            }
            $condition['no.distributor_type'] = ['>', 1];
            $condition['no.shop_id']          = $this->instance_id;
            $condition['no.is_deleted']       = 0; // 未删除订单
            $order_service                 = new OrderService();
            $list                          = $order_service->getKolOrderList($page_index, $page_size, $condition, 'create_time desc');
            return $list;
        } else {
            $status = request()->get('status', '');
            $this->assign("status", $status);
            $all_status        = OrderStatus::getKolOrderCommonStatus();
            $child_menu_list   = array();
            $child_menu_list[] = array(
                'url' => "Distributor/kolOrderList",
                'menu_name' => '全部',
                "active" => $status == '' ? 1 : 0
            );
            foreach ($all_status as $k => $v) {
                $child_menu_list[] = array(
                    'url' => "Distributor/kolOrderList?status=" . $v['status_id'],
                    'menu_name' => $v['status_name'],
                    "active" => $status == $v['status_id'] ? 1 : 0
                );
            }
            $this->assign('child_menu_list', $child_menu_list);

            //来源网点列表
            $store     = new Store();
            $storeList = $store->getStore([], 'store_id, store_code,store_name', 'store_id');
            $this->assign('storeList', $storeList);

            //极选师列表
            $distributor = new DistributorService();
            $kolList     = $distributor->getDistributor([], 'uid, real_name', 'create_time');
            $this->assign('kolList', $kolList);
            $this->admin_user_record('查看极选师订单列表','','');

            return view($this->style . "Kol/kolOrderList");
        }
    }

    //账户明细
    public function accountdetail()
    {
        if (request()->isAjax()) {
            $distributor        = new DistributorService();
            $page_index         = request()->post("page_index", 1);
            $page_size          = request()->post('page_size', PAGESIZE);
            $uid                = request()->post('kol_id');
            $from_type          = request()->post('from_type','');
            $condition['uid']   = $uid;
            $condition['money'] = ['neq', 0];
            if ($from_type != '') {
                $condition['from_type']   = $from_type;
            }
            $list = $distributor->getDistributorAccountList($page_index, $page_size, $condition, $order = '', $field = '*');
            return $list;
        }else{
            $kol_id = request()->get('kol_id', '');
            $this->assign('kol_id', $kol_id);

            $type = request()->get('type', '');
            $this->assign("type", $type);

            $all_type        = OrderStatus::getDistributorAccountRecordsType();
            $child_menu_list   = array();
            $child_menu_list[] = array(
                'url' => "Distributor/accountdetail?kol_id=" . $kol_id,
                'menu_name' => '全部',
                "active" => $type == '' ? 1 : 0
            );
            foreach ($all_type as $k => $v) {
                $child_menu_list[] = array(
                    'url' => "Distributor/accountdetail?kol_id=" . $kol_id . "&type=" . $v['type_id'],
                    'menu_name' => $v['type_name'],
                    "active" => $type == $v['type_id'] ? 1 : 0
                );
            }

            $kol_name = \think\Db::name('sys_user')->where(['uid' => $kol_id])->find()['real_name'];

            $this->assign('child_menu_list', $child_menu_list);
            $this->assign('kol_name', $kol_name);
            $this->admin_user_record('查看极选师账户明细',$kol_id,'');

            return view($this->style . 'Kol/accountDetailList');
        }
    }

    /**
     * 极选师订单数据excel导出
     */
    public function kolOrderDataExcel()
    {
        $xlsName             = "极选师订单数据列表";
        $start_date          = request()->get('start_date') == "" ? 0 : getTimeTurnTimeStamp(request()->get('start_date'));
        $end_date            = request()->get('end_date') == "" ? 0 : getTimeTurnTimeStamp(request()->get('end_date'));
        $order_no            = request()->get('order_no', '');
        $source_branch       = request()->get('source_branch', '');
        $source_distribution = request()->get('source_distribution', '');
        $order_status        = request()->get('order_status', '');
        if ($start_date != 0 && $end_date != 0) {
            $condition["consign_time"] = [
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
            $condition["consign_time"] = [
                [
                    ">",
                    $start_date
                ]
            ];
        } elseif ($start_date == 0 && $end_date != 0) {
            $condition["consign_time"] = [
                [
                    "<",
                    $end_date
                ]
            ];
        }
        if (!empty($order_no)) {
            $condition['order_no'] = $order_no;
        }
        if ($source_branch != '') {
            $condition['source_branch'] = $source_branch;
        }

        if ($source_distribution != '') {
            $condition['source_distribution'] = $source_distribution;
        } else {
            $condition['source_distribution'] = [
                ">",
                0
            ];
        }
        if ($order_status != '') {
            $condition['order_status'] = $order_status;
        } else {
            $condition['pay_status']   = 2;
            $condition['order_status'] = ['neq', 5];
        }
        $condition['distributor_type'] = ['>', 1];
        $condition['shop_id']          = $this->instance_id;
        $condition['is_deleted']       = 0; // 未删除订单
        $order_service                 = new OrderService();
        $list                          = $order_service->getKolExportOrderList($condition, 'consign_time desc');

        $arr = [];
        foreach ($list as $k => $v) {
            $v['create_time']  = date('Y-m-d H:i:s', $v['create_time']); // 创建时间
            $v['pay_time']     = (int)$v['pay_time'] > 0 ? date('Y-m-d H:i:s', $v['pay_time']) : '未付款'; //付款时间
            $v['consign_time'] = (int)$v['consign_time'] > 0 ? date('Y-m-d H:i:s', $v['consign_time']) : '未发货'; //发货时间
            $v['finish_time']  = (int)$v['finish_time'] > 0 ? date('Y-m-d H:i:s', $v['finish_time']) : '未完成'; //完成时间
            $v['tx_type']      = $v['tx_type'] == 1 ? '大贸' : '跨境'; //交易类型（1：大贸，2：跨境）
            $v['fraction']     = ($v['fraction'] * 100) . '%';
            foreach ($v['order_item_list'] as $vv) {
                $tmp = array_merge($vv, $v);
                array_push($arr, $tmp);
            }
        }

        $xlsCell = array(
            array(
                'order_no',
                '订单编号'
            ),
            array(
                'create_time',
                '下单时间'
            ),
            array(
                'pay_time',
                '付款时间'
            ),
            array(
                'consign_time',
                '发货时间'
            ),
            array(
                'finish_time',
                '完成时间'
            ),
            array(
                'user_name',
                '会员昵称'
            ),
            array(
                'tx_type',
                '物流类型'
            ),
            array(
                'status_name',
                '订单状态'
            ),
            array(
                'goods_name',
                '商品名称'
            ),
            array(
                'code',
                '商品编码'
            ),
            array(
                'product_barcode',
                '商品条形码'
            ),
            array(
                'material_code',
                'shopal物料编号'
            ),
            array(
                'price',
                '商品单价'
            ),
            array(
                'num',
                '购买数量'
            ),
            array(
                'adjust_money',
                '调整金额'
            ),
            array(
                'pay_money',
                '实际支付'
            ),
            array(
                'source_branch_name',
                '来源网点'
            ),
            array(
                'source_distribution_name',
                '分销来源'
            ),
            array(
                'parent_source_distribution_name',
                '推荐人'
            ),
            array(
                'fraction',
                '分润比例'
            ),
            array(
                'direct_separation',
                '直接分润'
            ),
            array(
                'indirect_separation',
                '间接分润'
            )
        );
        $this->admin_user_record('极选师订单数据excel导出','','');

        dataExcel($xlsName, $xlsCell, $arr);
        exit;
    }

    //分润统计
    public function orderStatistics()
    {
        if (request()->isAjax()) {
            $page_index          = request()->post('page_index', 1);
            $page_size           = request()->post('page_size', PAGESIZE);
            $source_distribution = request()->post('source_distribution', '');
            $year                = request()->post('year', '');
            $month               = request()->post('month', '');
            $sort_rule           = request()->post("sort_rule", ""); // 字段排序规则
            $search_text         = request()->post('search_text', '');

            //一个月的时间处理
            $days       = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $start_date = getTimeTurnTimeStamp($year . '-' . $month . '-01');
            $end_date   = getTimeTurnTimeStamp($year . '-' . $month . '-' . $days) + 86400;
            if ($source_distribution != '') {
                $condition['nm.uid'] = $source_distribution;
            }
            $condition['nm.distributor_type'] = [
                [
                    ">",
                    1
                ]
            ];
            $distributor                      = new DistributorService();

            #字段排序
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
                    $ids = $this->useDataOrder($start_date, $end_date, $sort_field, $sort_value);
                }
            } else {
                # 默认排序
                $sort_field = 'order_fraction_sum_yes';
                $sort_value = 'DESC';
                $ids        = $this->useDataOrder($start_date, $end_date, $sort_field, $sort_value);
            }

            if (!empty($search_text)) {
                $condition['bd.real_name'] = [
                    'like',
                    '%' . $search_text . '%'
                ];
            }

            $result['start_date'] = $start_date;
            $result['end_date']   = $end_date;
            $result['list']       = $distributor->orderStatistics($start_date, $end_date, $page_index, $page_size, $condition, "field(bd.uid,$ids)");
            return $result;
        } else {
            $year    = date('Y', time());
            $month   = date('m', time());
            $store   = new DistributorService();
            $kolList = $store->getDistributor(['is_check' => 1], 'uid, real_name', 'create_time');
            $this->assign('year', $year);
            $this->assign('month', $month);
            $this->assign('kolList', $kolList);
            $child_menu_list = array(
                array(
                    'url' => "distributor/orderstatistics",
                    'menu_name' => "分润统计",
                    "active" => 1
                ),
                array(
                    'url' => "distributor/assessmentStatistics",
                    'menu_name' => "考核统计",
                    "active" => 0
                ),
                array(
                    'url' => "distributor/invitationStatistics",
                    'menu_name' => "邀请统计",
                    "active" => 0
                )
            );
            $this->assign('child_menu_list', $child_menu_list);
            $this->admin_user_record('查看极选师分润统计','','');
            return view($this->style . 'Kol/orderStatistics');
        }
    }

    //考核统计
    public function assessmentStatistics()
    {
        if (request()->isAjax()) {
            $page_index          = request()->post('page_index', 1);
            $page_size           = request()->post('page_size', PAGESIZE);
            $year                = request()->post('year', '');
            $quarter             = request()->post('quarter', '');
            $source_distribution = request()->post('source_distribution', '');
            $sort_rule           = request()->post("sort_rule", "");        // 字段排序规则
            $search_text         = request()->post('search_text', '');

            //季度的时间处理
            if ($quarter == 1) {
                $days       = cal_days_in_month(CAL_GREGORIAN, 3, $year);
                $start_date = getTimeTurnTimeStamp($year . '-1-01');
                $end_date   = getTimeTurnTimeStamp($year . '-3-' . $days) + 86400;
            } else if ($quarter == 2) {
                $days       = cal_days_in_month(CAL_GREGORIAN, 6, $year);
                $start_date = getTimeTurnTimeStamp($year . '-4-01');
                $end_date   = getTimeTurnTimeStamp($year . '-6-' . $days) + 86400;
            } else if ($quarter == 3) {
                $days       = cal_days_in_month(CAL_GREGORIAN, 9, $year);
                $start_date = getTimeTurnTimeStamp($year . '-7-01');
                $end_date   = getTimeTurnTimeStamp($year . '-9-' . $days) + 86400;
            } else {
                $days       = cal_days_in_month(CAL_GREGORIAN, 12, $year);
                $start_date = getTimeTurnTimeStamp($year . '-10-01');
                $end_date   = getTimeTurnTimeStamp($year . '-12-' . $days) + 86400;
            }

            #字段排序
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
                    $ids = $this->useDataAssessment($start_date, $end_date, $sort_field, $sort_value);
                }
            } else {
                # 默认排序
                $sort_field = 'goods_money_sum';
                $sort_value = 'DESC';
                $ids        = $this->useDataAssessment($start_date, $end_date, $sort_field, $sort_value);
            }


            //$condition极选师条件
            if ($source_distribution != '') {
                $condition['nm.uid'] = $source_distribution;
            }

            if (!empty($search_text)) {
                $condition['bd.real_name'] = [
                    'like',
                    '%' . $search_text . '%'
                ];
            }

            $condition['nm.distributor_type'] = ['>', 1];
            $distributor                      = new DistributorService();
            $result                           = $distributor->assessmentStatistics($start_date, $end_date, $page_index, $page_size, $condition, "field(bd.uid,$ids)");
            return $result;
        } else {
            $year  = date('Y', time());
            $month = date('m', time());
            if ($month == 1 || $month == 2 || $month == 3) {
                $quarter = 1;
            } else if ($month == 4 || $month == 5 || $month == 6) {
                $quarter = 2;
            } else if ($month == 7 || $month == 8 || $month == 9) {
                $quarter = 3;
            } else {
                $quarter = 4;
            }
            $store   = new DistributorService();
            $kolList = $store->getDistributor(['is_check' => 1], 'uid, real_name', 'create_time');
            $this->assign('year', $year);
            $this->assign('quarter', $quarter);
            $this->assign('kolList', $kolList);
            $child_menu_list = array(
                array(
                    'url' => "distributor/orderstatistics",
                    'menu_name' => "分润统计",
                    "active" => 0
                ),
                array(
                    'url' => "distributor/assessmentStatistics",
                    'menu_name' => "考核统计",
                    "active" => 1
                ),
                array(
                    'url' => "distributor/invitationStatistics",
                    'menu_name' => "邀请统计",
                    "active" => 2
                )
            );
            $this->assign('child_menu_list', $child_menu_list);
            $this->admin_user_record('查看极选师考核统计','','');
            return view($this->style . 'Kol/assessmentStatistics');
        }
    }

    //邀请统计
    public function invitationStatistics()
    {
        if (request()->isAjax()) {
            $page_index          = request()->post('page_index', 1);
            $page_size           = request()->post('page_size', PAGESIZE);
            $sort_rule           = request()->post("sort_rule", ""); // 字段排序规则
            $source_distribution = request()->post('source_distribution', '');
            $search_text         = request()->post('search_text', '');

            #字段排序
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
                    $ids = $this->useDataInvitation($sort_field, $sort_value);
                }
            } else {
                # 默认排序
                $sort_field = 'count_day';
                $sort_value = 'DESC';
                $ids        = $this->useDataInvitation($sort_field, $sort_value);
            }

            # 业绩总数排序处理

            //$condition极选师条件
            if ($source_distribution != '') {
                $condition['nm.uid'] = $source_distribution;
            }
            if (!empty($search_text)) {
                $condition['nm.real_name'] = [
                    'like',
                    '%' . $search_text . '%'
                ];
            }
            $distributor = new DistributorService();
            $result      = $distributor->invitationStatistics($page_index, $page_size, $condition, "field(bd.uid,$ids)");
            return $result;
        } else {
            $store                    = new DistributorService();
            $kolList                  = $store->getDistributor(['is_check' => 1], 'uid, real_name', 'create_time');
            $conditionOne['inviter']  = ['neq', 0];
            $conditionOne1['inviter'] = ['neq', 0];
            $conditionOne2['inviter'] = ['neq', 0];
            $conditionOne3['inviter'] = ['neq', 0];

            # 当日
            $conditionOne1['created_time'] = [
                [
                    ">=",
                    mktime(0, 0, 0, date('m'), date('d'), date('Y'))
                ],
                [
                    "<",
                    mktime(0, 0, 0, date('m'), date('d') + 1, date('Y')) - 1
                ]
            ];

            # 当月
            $conditionOne2['created_time'] = [
                [
                    ">=",
                    mktime(0, 0, 0, date('m'), 1, date('Y'))
                ],
                [
                    "<",
                    mktime(23, 59, 59, date('m'), date('t'), date('Y'))
                ]
            ];

            # 本季度
            $season                        = ceil(date('n') / 3);
            $conditionOne3['created_time'] = [
                [
                    ">=",
                    strtotime(date('Y-m-01', mktime(0, 0, 0, ($season - 1) * 3 + 1, 1, date('Y'))))
                ],
                [
                    "<",
                    strtotime(date('Y-m-t', mktime(0, 0, 0, $season * 3, 1, date('Y'))))
                ]
            ];

            $count_day     = \think\Db::name('bc_distributor_info')->where($conditionOne1)->count();
            $count_month   = \think\Db::name('bc_distributor_info')->where($conditionOne2)->count();
            $count_quarter = \think\Db::name('bc_distributor_info')->where($conditionOne3)->count();
            $count_all     = \think\Db::name('bc_distributor_info')->where($conditionOne)->count();

            $this->assign('count_day', $count_day);
            $this->assign('count_month', $count_month);
            $this->assign('count_quarter', $count_quarter);
            $this->assign('count_all', $count_all);
            $this->assign('kolList', $kolList);
            $child_menu_list = array(
                array(
                    'url' => "distributor/orderstatistics",
                    'menu_name' => "分润统计",
                    "active" => 0
                ),
                array(
                    'url' => "distributor/assessmentStatistics",
                    'menu_name' => "考核统计",
                    "active" => 0
                ),
                array(
                    'url' => "distributor/invitationStatistics",
                    'menu_name' => "邀请统计",
                    "active" => 1
                )
            );
            $this->assign('child_menu_list', $child_menu_list);
            $this->admin_user_record('查看极选师邀请统计','','');
            return view($this->style . 'Kol/invitationStatistics');
        }
    }

    //极选师分润列表
    public function distributorSeparationRecordsList()
    {
        $uid                          = request()->post('uid', '');
        $start_date                   = request()->post('start_date', '');
        $end_date                     = request()->post('end_date', '');
        $condition['uid']             = $uid;
        $condition["settlement_time"] = [
            [
                ">",
                $start_date
            ],
            [
                "<",
                $end_date
            ]
        ];
        $distributor                  = new DistributorService();
        $result                       = $distributor->distributorSeparationRecordsList($condition);
        return $result;
    }

    //订单分润列表
    public function orderSeparationRecordsList()
    {
        $order_no              = request()->post('order_no', '');
        $condition['order_no'] = $order_no;
        $distributor           = new DistributorService();
        $result                = $distributor->distributorSeparationRecordsList($condition);
        return $result;
    }

    //确认出账
//    public function checkoutSubmit()
//    {
//        $id_array = request()->post('id_array', '');
//        $distributor    = new DistributorService();
//        $res            = $distributor->checkoutSubmit($id_array);
//        return AjaxReturn($res);
//    }

    //确认出账
    public function checkoutSubmit()
    {
        $id_array    = request()->post('id_array', '');
        $distributor = new DistributorService();
        $res         = $distributor->checkoutSubmit($id_array);
        return AjaxReturn($res);
    }

    //星级列表
    public function starList()
    {
        if (request()->isAjax()) {
            $distributorStar = new DistributorStarService();

            $list = $distributorStar->getStarList(1, 10000, [], 'star_type,star_grade', '*');
            return $list;
        } else {
            return view($this->style . 'Kol/starList');
        }
    }

    //添加星级
    public function addStar()
    {
        $distributorStar  = new DistributorStarService();
        $star_type        = request()->post('star_type', '');
        $star_grade       = request()->post('star_grade', '');
        $star_name        = request()->post('star_name', '');
        $star_standard    = request()->post('star_standard', '');
        $star_reward      = request()->post('star_reward', '');
        $star_description = request()->post('star_description', '');
        $retval           = $distributorStar->addStar($star_type, $star_grade, $star_name, $star_standard, $star_reward, $star_description);
        return AjaxReturn($retval);
    }

    /**
     * 查询单个星级
     */
    public function getStarDetail()
    {
        $distributorStar = new DistributorStarService();
        $star_id         = request()->post("star_id", 0);
        $info            = $distributorStar->getStarDetail($star_id);
        return $info;
    }

    /**
     * 修改星级
     */
    public function updateStar()
    {
        if (request()->isAjax()) {
            $distributorStar  = new DistributorStarService();
            $star_id          = request()->post('star_id', '');
            $star_type        = request()->post('star_type', '');
            $star_grade       = request()->post('star_grade', '');
            $star_name        = request()->post('star_name', '');
            $star_reward      = request()->post('star_reward', '');
            $star_standard    = request()->post('star_standard', '');
            $star_description = request()->post('star_description', '');
            $res              = $distributorStar->updateStar($star_id, $star_type, $star_grade, $star_name, $star_reward, $star_standard, $star_description);
            return AjaxReturn($res);
        }
    }

    //结算方式
//    public function settlement(){
//        if (request()->isAjax()) {
//            $kol = new Order();
//            $page_index = request()->post('page_index', 1);
//            $page_size = request()->post('page_size', PAGESIZE);
//            $search_text = request()->post('search_text', '');
//            $condition = array(
//                'user_tel|user_email|nick_name' => array(
//                    'like',
//                    '%' . $search_text . '%'
//                ),
//                'is_kol' => 1
//            );
//
//
//        } else {
//            return view($this->style . 'Kol/settlement');
//        }
//    }


    /**
     * 极选师申请列表
     */
    public function kolApplyList()
    {
        if (request()->isAjax()) {
            $page_index  = request()->post('page_index', 1);
            $page_size   = request()->post('page_size', PAGESIZE);
            $search_text = request()->post('search_text', '');
            $condition   = array(
                'bdi.name|bdi.tel' => array(
                    'like',
                    '%' . $search_text . '%'
                ),
                'bdi.status' => 1
            );
            $distributor = new DistributorService();
            $list        = $distributor->getDistributorApplyList($page_index, $page_size, $condition, 'created_time desc');

            foreach ($list['data'] as $key => $row) {
                $is_check[$key]     = $row ['is_check'];
                $created_time[$key] = $row ['created_time'];
            }
            array_multisort($is_check, SORT_ASC, $created_time, SORT_DESC, $list['data']);
            return $list;
        } else {
            $this->admin_user_record('查看极选师申请列表','','');
            return view($this->style . 'Kol/kolApplyList');
        }
    }

    /**
     * @return \think\response\View
     * 申请详情
     */
    public function applyDetail()
    {
        $id = request()->get('id', 0);
        if (empty($id)) $this->error("没有获取到信息");
        $order_service = new DistributorService();
        $detail        = $order_service->getDistributorInfoDetail($id);
        if (empty($detail)) $this->error("没有获取到申请信息");

        if ($_SESSION['niu']['adminuid'] == 4562) {
            $detail['auth_show_type'] = 1;
        }
        #特权
        if ($_SESSION['niu']['adminuid'] == 1 || $_SESSION['niu']['adminuid'] == 5) {
            $detail['auth_show_type'] = 3;
        }

        $url_info1 = \think\Db::name('bc_private_img')->where(['uid' => $detail['uid'], 'type' => 2])->find();
        $url_info2 = \think\Db::name('bc_private_img')->where(['uid' => $detail['uid'], 'type' => 3])->find();
//        $url_info3 = \think\Db::name('bc_private_img')->where(['uid' => $detail['uid'] , 'type' => 1])->find();

        if ($detail['id_face_pros'] != '') {
            $detail['id_face_pros'] = $this->getIMG($url_info1['fname']);   #正
            $detail['id_face_cons'] = $this->getIMG($url_info2['fname']);   #反
        }

//        $detail['bank_card_pic'] = $this->getIMG($url_info3['fname']);  #银行卡


        $this->assign("apply", $detail);

        if (!empty($_SESSION['niu']['adminuid']) || $_SESSION['niu']['adminuid'] != 0) {
            $this->admin_user_record('查看极选师申请详情',$id,'');

            return view($this->style . "Kol/applyDetail");
        } else {
            return $this->error('请先登录');
        }
    }

    /**
     * @return \multitype
     * 申请反馈
     */
    public function sendView()
    {
        $data                 = request()->post();
        $distributor          = new DistributorService();
        $data['created_time'] = date('Y-m-d H:i:s', time());
        $data['desc']         = str_replace(PHP_EOL, ' ', $data['desc']);
        if (empty($data)) $this->error("没有参数");
        $info = \think\Db::name('bc_distributor_check')->where(['distributor_info_id' => $data['distributor_info_id'], 'origin' => $data['origin']])->find();
        if ($info) $this->error("请勿重复提交");
        $data['check_user'] = \think\Db::name('sys_user_admin')->where(['uid' => $_SESSION['niu']['adminuid']])->find()['admin_name'];
        $res['code']        = \think\Db::name('bc_distributor_check')->insert($data);
        $d_info             = \think\Db::name('bc_distributor_info')->where(['id' => $data['distributor_info_id']])->find();
        if ($data['is_check']  == 1) {
            $this->updateCheckStatus($data['distributor_info_id'], 1);
            $_res['distributor_type'] = 4;
            $_res['real_name']        = $d_info['name'];
            \think\Db::name('ns_member')->where(['uid' => $d_info['uid']])->update($_res);
            $this->sendTemplate_kolCheckOver($d_info['uid'], 1);
            $distributor->setInviter($d_info['uid'], $d_info['recommend_user'], $d_info['is_recommend']);
        } else {
            $this->updateCheckStatus($data['distributor_info_id'], 2);
            $this->sendTemplate_kolCheckOver($d_info['uid'], 2);
        }
        $res['code']    = '1';
        $res['message'] = '审核成功';
        $this->admin_user_record('极选师申请反馈',$data['distributor_info_id'],'');

        return $res;
    }

    /**
     * @param $distributor_info_id
     * @param $status
     * @throws \think\Exception
     * 更新审核状态
     */
    public function updateCheckStatus($distributor_info_id, $status)
    {
        if (empty($distributor_info_id)) $this->error("没有参数");
        $uid = \think\Db::name('bc_distributor_info')->where(['id' => $distributor_info_id])->find()['uid'];
        if (empty($uid)) $this->error("参数异常");
        if ($status == 1) {
            $data['is_check'] = 1;
        } else {
            $data['is_check'] = 2;
        }
        $data['update_time'] = time();
        $this->admin_user_record('更新极选师审核状态',$distributor_info_id,'');
        \think\Db::name('bc_distributor')->where(['uid' => $uid])->update($data);
    }

    /**
     * @return mixed
     * 点击下载图片
     */
    public function downPic()
    {
        $data = request()->post();
        $img1 = file_get_contents($data['img1']);
        $img2 = file_get_contents($data['img2']);
        if (empty($img1) || empty($img2)) $this->error("未检测到图片");
        $fileName1 = '身份证正面.jpg';
        $fileName2 = '身份证反面.jpg';
        file_put_contents($fileName1, $img1);
        file_put_contents($fileName2, $img2);
        $res['message'] = '下载成功';
        $res['pic1']    = $fileName1;
        $res['pic2']    = $fileName2;
        $res['code']    = '1';
        $this->admin_user_record('下载身份证图片',$img1,'');
        return $res;
    }

//    /**
//     * @return mixed
//     * 点击下载图片
//     */
//    public function downBankPic()
//    {
//        $data     = request()->post();
//        $bank_pic = file_get_contents($data['bank_pic']);
//        if (empty($bank_pic)) $this->error("未检测到图片");
//        $fileName = '银行卡图片.jpg';
//        file_put_contents($fileName, $bank_pic);
//        $res['message']  = '下载成功';
//        $res['bank_pic'] = $fileName;
//        $res['code']     = '1';
//        return $res;
//    }


    # 审核通知
    public function sendTemplate_kolCheckOver($uid, $type)
    {
//        $template_id = '6sLBsjNpfYjYNHfNAaokZsCN31JxnYoCWL1fJ_SzoCM'; # prod
//        $template_id = getWxTemplateId('kol_check_over');

        $template_id = getWxTemplateId('kol_check_over');


        if ($type == 1) {
            $info = '已通过';
            $desc = '恭喜您成为签约极选师';
            $page = 'pages/member/kol/kol';
        } else {
            $info = '未通过';
            $desc = '很遗憾您未通过审核';
            $page = 'pages/index/index';
        }


        $template_info = \think\Db::name('ns_template_push')->where(['uid' => $uid, 'is_send' => 0, 'warn_type' => 30])->find();
        $create_time   = $template_info['created'];

        $conf = json_decode(\think\Db::name('sys_config')->where([
            'key' => 'SHOPAPPLET'
        ])->find()['value'], true);

        $appid  = $conf['appid'];
        $secret = $conf['appsecret'];

        $access_token = getAccessToken($appid, $secret);
        $select_url   = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;

        $openid = $template_info['open_id'];
        $fid    = $template_info['form_id'];

        $p1 = 'BonnieClyde';
        $p2 = '签约极选师';
        $p3 = $create_time;
        $p4 = $info;
        $p5 = $desc;

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
      } ,
      "keyword5": {
          "value": "$p5"
      }
  },
  "color":"#ccc"
}
EOL;
        curl_post($select_url, $param);
        $res['is_send'] = 1;
        \think\Db::name('ns_template_push')->where(['id' => $template_info['id']])->update($res);
    }

    public function cronTabUpImg()
    {
        $list = \think\Db::name('bc_distributor_info')->select();

        foreach ($list as $v) {
            $img1 = file_get_contents($v['id_face_pros']);
            $img2 = file_get_contents($v['id_face_cons']);
//            $img3      = file_get_contents($v['bank_card_pic']);
            $address   = 'application/images/' . $v['uid'];
            $fileName1 = $address . '_身份证正面.jpg';
            $fileName2 = $address . '_身份证反面.jpg';
//            $fileName3 = $address . '_银行卡照片.jpg';
            file_put_contents($fileName1, $img1);
            file_put_contents($fileName2, $img2);
//            file_put_contents($fileName3, $img3);
//            if($this->upPrivateIMG(1 , $fileName3 ,$v['uid']) == false){
//                $this->upPrivateIMG(1 , $fileName3 ,$v['uid']);
//            }
            if ($this->upPrivateIMG(2, $fileName1, $v['uid']) == false) {
                $this->upPrivateIMG(2, $fileName1, $v['uid']);
            }
            if ($this->upPrivateIMG(3, $fileName2, $v['uid']) == false) {
                $this->upPrivateIMG(3, $fileName2, $v['uid']);
            }
        }

    }

    /**
     * @param $type
     * @param $file_path
     * @param $uid
     * @return bool|int|string
     * @throws \Exception
     * 上传私密空间图片
     */
    public function upPrivateIMG($type, $file_path, $uid)
    {
        #type    1:银行卡   2:身份证正 3:身份证反

        $accessKey = '_xBTRsUTy2VR_qH5JNjspfBakRzTIv7YLsV3Fjup';
        $secretKey = '09F9mdbtGnCN1oTCXExVjpb2N79Qp5rgFye37CmE';

        $auth = new Auth($accessKey, $secretKey);

        // 要转码的文件所在的空间
        $bucket = 'bcids';

        //自定义上传回复的凭证 返回的数据
        $returnBody = '{"key":"$(key)","hash":"$(etag)","fsize":$(fsize),"bucket":"$(bucket)","name":"$(fname)"}';
        $policy     = array(
            'returnBody' => $returnBody,
        );

        //token过期时间
        $expires = 0;

        // 生成上传 Token
        $token = $auth->uploadToken($bucket, null, $expires, $policy, true);

        $filePath = $file_path;


//        if($type == 1){
//            $key = $uid.time().'_银行卡照片.jpg';
//        }else

        if ($type == 2) {
            $key = $uid . time() . '_身份证正面.jpg';
        } else {
            $key = $uid . time() . '_身份证反面.jpg';
        }

        $uploadMgr = new UploadManager();   // 调用 UploadManager 的 putFile 方法进行文件的上传。

        list($ret, $err) = $uploadMgr->putFile($token, $key, $filePath);

        if ($err !== null) {
            return false;
        } else {
            $data = [
                'uid' => $uid,
                'fname' => $key,
                'key' => $ret['key'],
                'type' => $type,
                'url' => $this->getIMG($key),
                'createTime' => time()
            ];

            return \think\Db::name('bc_private_img')->insert($data);
        }
    }


    /**
     * @param $file_name
     * @return string
     * 获取图片url
     */
    public function getIMG($file_name)
    {
        $accessKey = '_xBTRsUTy2VR_qH5JNjspfBakRzTIv7YLsV3Fjup';
        $secretKey = '09F9mdbtGnCN1oTCXExVjpb2N79Qp5rgFye37CmE';
        $auth      = new Auth($accessKey, $secretKey);
        $baseUrl   = 'http://private.bonnieclyde.cn/' . $file_name;
        $signedUrl = $auth->privateDownloadUrl($baseUrl);
        return $signedUrl;
    }


    /**
     * @throws \think\Exception
     * 更新图片url
     */
    public function updateIMG()
    {
//        $type = request()->post('type', '');
        $uid = request()->post('uid', '');

        # 1 : 银行卡
//        if($type == 1){
//            $file_name = \think\Db::name('bc_private_img')->where(['uid' => $uid , 'type' => 1])->find();
//            $this->useUpdateIMG($file_name , 1 ,$uid);
//        }else{
        $file_name1 = \think\Db::name('bc_private_img')->where(['uid' => $uid, 'type' => 2])->find(); #正
        $file_name2 = \think\Db::name('bc_private_img')->where(['uid' => $uid, 'type' => 3])->find(); #反
        $this->useUpdateIMG($file_name1, 2, $uid);
        $this->useUpdateIMG($file_name2, 3, $uid);
//        }
    }

    /**
     * @param $file_name
     * @param $type
     * @param $uid
     * @throws \think\Exception
     */
    public function useUpdateIMG($file_name, $type, $uid)
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
        $destKey            = $uid . $time . '_' . explode('_', $key)[1] . ".jpg";
        $err                = $bucketManager->move($srcBucket, $srcKey, $destBucket, $destKey, true);
        $data['fname']      = $data['key'] = $destKey;
        $data['createTime'] = $time;
        $data['url']        = $this->getIMG($destKey);
        if (!$err) {
            \think\Db::name('bc_private_img')->where(['uid' => $uid, 'type' => $type])->update($data);
            $this->clearQiNiu($file_name['url'], $file_name['fname']);
        }
    }


    /**
     * @param $url
     * @param $fname
     * 刷新七牛缓存
     */
    public function clearQiNiu($url, $fname)
    {
        $accessKey = '_xBTRsUTy2VR_qH5JNjspfBakRzTIv7YLsV3Fjup';
        $secretKey = '09F9mdbtGnCN1oTCXExVjpb2N79Qp5rgFye37CmE';
        $auth      = new Auth($accessKey, $secretKey);
        //待刷新的文件列表和目录，文件列表最多一次100个，目录最多一次10个
        //参考文档：http://developer.qiniu.com/article/fusion/api/refresh.html
        $urls = array();
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

    /**
     * 新增url
     */
    public function cronTabUpImgDay()
    {
        $list = \think\Db::name('bc_authentication_info')->select();
        $info = $list[count($list) - 1];
        $img1 = file_get_contents($info['id_face_pros']);
        $img2 = file_get_contents($info['id_face_cons']);
//        $img3      = file_get_contents($info['bank_card_pic']);
        $address   = 'application/images/' . $info['uid'];
        $fileName1 = $address . '_身份证正面.jpg';
        $fileName2 = $address . '_身份证反面.jpg';
//        $fileName3 = $address . '_银行卡照片.jpg';
        file_put_contents($fileName1, $img1);
        file_put_contents($fileName2, $img2);
//        file_put_contents($fileName3, $img3);
//        $this->upPrivateIMG(1 , $fileName3 ,$info['uid']);
        $this->upPrivateIMG(2, $fileName1, $info['uid']);
        $this->upPrivateIMG(3, $fileName2, $info['uid']);
    }

//    public function checkUPIMG(){
//        $info_list = \think\Db::name('bc_distributor_info')->select();
//        $img_list  = \think\Db::name('bc_private_img')->select();
//        $count     = count($info_list);
//        $img_count = count($img_list);
//        if ($count > $img_count / 3) {
//            $this->cronTabUpImgDay();
//        }
//    }

    # 业绩总数排序处理
    public function useDataOrder($start_date, $end_date, $sort_field, $sort_value)
    {
        $distributorAccountRecords = new BcDistributorAccountRecordsModel();
        $distributor_list          = \think\Db::name('bc_distributor')->select();
        $arr                       = [];
        //$whereYes已结算条件
        $whereYes["settlement_time"] = [
            [
                ">",
                $start_date
            ],
            [
                "<",
                $end_date
            ]
        ];
        $whereYes['account_type']    = 1;
        $whereYes['from_type']       = ['in', [1, 2]];
        foreach ($distributor_list as $k => $v) {
            $whereYes['uid'] = $v['uid'];
            if ($sort_field == 'order_number_count_no') {
                continue;
            } elseif ($sort_field == 'order_fraction_sum_no') {
                continue;
            } elseif ($sort_field == 'order_number_count_yes') {
                #已出账订单统计
                $arr[$k]['uid']                    = $v['uid'];
                $arr[$k]['order_number_count_yes'] = $distributorAccountRecords->numberCount($whereYes);
            } elseif ($sort_field == 'order_fraction_sum_yes') {
                #已出账分润统计
                $arr[$k]['uid']                    = $v['uid'];
                $arr[$k]['order_fraction_sum_yes'] = $distributorAccountRecords->moneySum($whereYes);
            } else {
                continue;
            }
        }

        if ($sort_field == 'order_fraction_sum_yes') {
            foreach ($arr as $key => $row) {
                $created_time[$key] = $row ['order_fraction_sum_yes'];
            }
        } else if ($sort_field == 'order_number_count_yes') {
            foreach ($arr as $key => $row) {
                $created_time[$key] = $row ['order_number_count_yes'];
            }
        }

        if ($sort_value == 'DESC') {
            array_multisort($created_time, SORT_DESC, $arr);
        } else {
            array_multisort($created_time, SORT_ASC, $arr);
        }

        $ids = '';
        foreach ($arr as $v) {
            $ids .= $v['uid'] . ',';
        }
        $ids = rtrim($ids, ',');
        return $ids;
    }


    # 邀请总数排序处理
    public function useDataInvitation($sort_field, $sort_value)
    {
        $member           = new NsMemberViewModel();
        $distributor_list = \think\Db::name('bc_distributor')->select();
        $arr              = [];
        if ($sort_field == 'count_day') {
            # 当日
            $conditionOne['bd.create_time'] = [
                [
                    ">=",
                    mktime(0, 0, 0, date('m'), date('d'), date('Y'))
                ],
                [
                    "<",
                    mktime(0, 0, 0, date('m'), date('d') + 1, date('Y')) - 1
                ]
            ];
        } elseif ($sort_field == 'count_month') {
            # 当月
            $conditionOne['bd.create_time'] = [
                [
                    ">=",
                    mktime(0, 0, 0, date('m'), 1, date('Y'))
                ],
                [
                    "<",
                    mktime(23, 59, 59, date('m'), date('t'), date('Y'))
                ]
            ];
        } elseif ($sort_field == 'count_quarter') {
            # 本季度
            $season                         = ceil(date('n') / 3);
            $conditionOne['bd.create_time'] = [
                [
                    ">=",
                    strtotime(date('Y-m-01', mktime(0, 0, 0, ($season - 1) * 3 + 1, 1, date('Y'))))
                ],
                [
                    "<",
                    strtotime(date('Y-m-t', mktime(0, 0, 0, $season * 3, 1, date('Y'))))
                ]
            ];
        } elseif ($sort_field == 'count_last_day') {
            # 昨天
            $conditionOne['bd.create_time'] = [
                [
                    ">=",
                    mktime(0, 0, 0, date('m'), date('d') - 1, date('Y'))
                ],
                [
                    "<",
                    mktime(0, 0, 0, date('m'), date('d'), date('Y')) - 1
                ]
            ];
        } else {
            # all
            $conditionOne = [];
        }

        foreach ($distributor_list as $k => $v) {
            $arr[$k]['uid']   = $conditionOne['nm.inviter'] = $v['uid'];
            $arr[$k]['count'] = $member->getInvitationCount($conditionOne);

        }
        foreach ($arr as $key => $row) {
            $created_time[$key] = $row ['count'];
        }
        if ($sort_value == 'DESC') {
            array_multisort($created_time, SORT_DESC, $arr);
        } else {
            array_multisort($created_time, SORT_ASC, $arr);
        }

        $ids = '';
        foreach ($arr as $v) {
            $ids .= $v['uid'] . ',';
        }
        $ids = rtrim($ids, ',');
        return $ids;
    }

    public function useDataAssessment($start_date, $end_date, $sort_field, $sort_value)
    {
        $order            = new NsOrderModel();
        $starMOdel        = new BcDistributorStarModel();
        $distributor_view = new BcDistributorViewModel();
        $result           = $distributor_view->getViewList(1, '10000', '', '');
        $arr              = [];
        foreach ($result['data'] as $k => $v) {
            //考核期销售额
            $conditionOne['no.order_status']        = 4;
            $conditionOne['nog.refund_status']      = array('neq', 5);
            $conditionOne['no.source_distribution'] = $v['uid'];
            $conditionOne['no.distributor_type']    = $v['distributor_type'];
            $conditionOne["no.finish_time"]         = [
                [
                    ">",
                    $start_date
                ],
                [
                    "<",
                    $end_date
                ]
            ];

            $result['data'][$k]['goods_money_sum'] = $order->goodsMoneySum($conditionOne);
            $result['data'][$k]['goods_order_sum'] = $order->goodsOrderSum($conditionOne);
            if ($sort_field == 'star') {
                //星级
                if ($v['distributor_type'] == 2 || $v['distributor_type'] == 3) {
                    $where['star_type'] = 1;
                } else {
                    $where['star_type'] = 2;
                }
                $starList                   = $starMOdel->getQuery($where, '*', 'star_standard desc');
                $result['data'][$k]['star'] = '';
                foreach ($starList as $key => $val) {
                    if ($result['data'][$k]['goods_money_sum'] > $val['star_standard'] && empty($result['data'][$k]['star'])) {
                        $arr[$k]['uid']  = $v['uid'];
                        $arr[$k]['star'] = $val['star_grade'];
                    }
                }
            } elseif ($sort_field == 'goods_money_sum') {
                $arr[$k]['uid']             = $v['uid'];
                $arr[$k]['goods_money_sum'] = $result['data'][$k]['goods_money_sum'];
            }elseif ($sort_field == 'goods_order_sum') {
                $arr[$k]['uid']             = $v['uid'];
                $arr[$k]['goods_order_sum'] = $result['data'][$k]['goods_order_sum'];
            } elseif ($sort_field == 'bonus') {
                continue;
                $starList                    = $starMOdel->getQuery($where, '*', 'star_standard desc');
                $other_money                 = $result['data'][$k]['goods_money_sum'];
                $result['data'][$k]['bonus'] = 0;
                foreach ($starList as $key => $val) {
                    if ($result['data'][$k]['goods_money_sum'] > $val['star_standard']) {
                        $arr[$k]['bonus'] += ($other_money - $val['star_standard']) * $val['star_reward'];
                        $other_money = $val['star_standard'];
                    }
                    $arr[$k]['uid'] = $v['uid'];
                }
            } else {
                continue;
            }
        }

        if ($sort_field == 'star') {
            foreach ($arr as $key => $row) {
                $created_time[$key] = $row['star'];
            }
        } else if ($sort_field == 'goods_money_sum') {
            foreach ($arr as $key => $row) {
                $created_time[$key] = $row['goods_money_sum'];
            }
        } else if ($sort_field == 'goods_order_sum') {
            foreach ($arr as $key => $row) {
                $created_time[$key] = $row['goods_order_sum'];
            }
        } else {
            foreach ($arr as $key => $row) {
                $created_time[$key] = $row['bonus'];
            }
        }

        if ($sort_value == 'DESC') {
            array_multisort($created_time, SORT_DESC, $arr);
        } else {
            array_multisort($created_time, SORT_ASC, $arr);
        }


        $ids = '';
        foreach ($arr as $v) {
            $ids .= $v['uid'] . ',';
        }
        $ids = rtrim($ids, ',');
        return $ids;
    }


    public function cronTabUpImg1()
    {
        $list = \think\Db::name('bc_distributor_info')->select();

        foreach ($list as $v) {
            if(empty($v['id_face_pros'])) continue;
            $img_list = \think\Db::name('bc_private_img')->where(['uid' => $v['uid']])->find();
            if(empty($img_list)){
                $img1 = file_get_contents($v['id_face_pros']);
                $img2 = file_get_contents($v['id_face_cons']);
                $address   = 'application/images/' . $v['uid'];
                $fileName1 = $address . '_身份证正面.jpg';
                $fileName2 = $address . '_身份证反面.jpg';
                file_put_contents($fileName1, $img1);
                file_put_contents($fileName2, $img2);
                if ($this->upPrivateIMG(2, $fileName1, $v['uid']) == false) {
                    $this->upPrivateIMG(2, $fileName1, $v['uid']);
                }
                if ($this->upPrivateIMG(3, $fileName2, $v['uid']) == false) {
                    $this->upPrivateIMG(3, $fileName2, $v['uid']);
                }
            }
        }

    }

}

