<?php

namespace data\service;

use data\model\BcDistributorInfoModel;
use data\model\BcDistributorInfoViewModel;
use data\model\BcDistributorModel;
use data\model\BcDistributorViewModel;
use data\model\CityModel;
use data\model\DistrictModel;
use data\model\NsMemberViewModel;
use data\model\NsOrderModel;
use data\model\NsMemberModel;
use data\model\UserModel;
use data\model\ProvinceModel;
use data\model\BcDistributorStarModel;
use data\model\BcDistributorAccountRecordsModel;
use data\model\NsMemberBalanceWithdrawModel;

class Distributor extends BaseService
{

    function __construct()
    {
        parent::__construct();
    }


    /**
     * @param int $page_index
     * @param int $page_size
     * @param string $condition
     * @param string $order
     * @return data\model\multitype
     * 极选师列表
     */
    public function getDistributorList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $distributor_view = new BcDistributorViewModel();
        $result           = $distributor_view->getViewList($page_index, $page_size, $condition, $order);

        $store            = new Store();
        $memberService    = new Member();
        $order            = new NsOrderModel();
        $member           = new NsMemberModel();
        $distributor_type = [2 => '有分润门店极选师', 3 => '无分润门店极选师', 4 => '签约极选师'];
        foreach ($result['data'] as $k => $v) {
            //来源网点
            if ($v['source_branch'] > 0) {
                $store_detail                             = $store->getStoreDetail($v['source_branch']);
                $result['data'][$k]['source_branch_name'] = $store_detail['store_name'];
            } else {
                $result['data'][$k]['source_branch_name'] = '--';
            }

            //推荐人
            if ($v['source_distribution'] > 0) {
                $member_detail                                  = $memberService->getMember($v['source_distribution']);
                $result['data'][$k]['source_distribution_name'] = $member_detail['real_name'];
            } else {
                $result['data'][$k]['source_distribution_name'] = '--';
            }

            //邀请人
            if ($v['inviter'] > 0) {
                $member_detail                      = $memberService->getMember($v['inviter']);
                $result['data'][$k]['inviter_name'] = $member_detail['real_name'];
            } else {
                $result['data'][$k]['inviter_name'] = '--';
            }

            //分销类型
            $result['data'][$k]['distributor_type_name'] = $distributor_type[$v['distributor_type']];

            //累计成交订单笔数
            $conditionOne['no.source_distribution']   = $v['uid'];
            $conditionOne['no.order_status']          = [
                'in',
                [
                    1,
                    2,
                    3,
                    4,
                    -1
                ]
            ];
            $result['data'][$k]['order_number_count'] = $order->orderNumberCount($conditionOne);

            //累计成交金额
            $conditionTwo['no.source_distribution'] = $v['uid'];
            $conditionTwo['no.order_status']        = [
                'in',
                [
                    1,
                    2,
                    3,
                    4,
                    -1
                ]
            ];
            $conditionTwo['nog.refund_status']      = ['neq', 5];
            $result['data'][$k]['goods_money_sum']  = $order->goodsMoneySum($conditionTwo);

            //累计分润金额
//            $conditionThree['no.source_distribution'] = $v['uid'];
//            $conditionThree['no.distributor_type'] = [
//                'in',
//                [
//                    2,
//                    4
//                ]
//            ];
//            $conditionThree['no.order_status'] = 4;
//            $conditionThree['nog.refund_status'] = ['neq',5];
//            $result['data'][$k]['order_fraction_sum'] = $order->orderFractionSum($conditionThree);
            $distributorAccountRecords                = new BcDistributorAccountRecordsModel();
            $result['data'][$k]['order_fraction_sum'] = $distributorAccountRecords->moneySum(['uid' => $v['uid'], 'account_type' => 1, 'from_type' => ['in', [1, 2]]]);

            //绑定会员人数
            $conditionFour['nm.source_distribution']   = $v['uid'];
            $conditionFour['nm.distributor_type']      = 0;
            $result['data'][$k]['member_number_count'] = $member->memberNumberCount($conditionFour);

            //推荐极选师人数
            $conditionFive['nm.source_distribution']        = $v['uid'];
            $conditionFive['nm.distributor_type']           = ['>', 1];
            $result['data'][$k]['recommender_number_count'] = $member->memberNumberCount($conditionFive);

            //邀请极选师人数
            $conditionSix['nm.inviter']                 = $v['uid'];
            $conditionSix['nm.distributor_type']        = ['>', 1];
            $result['data'][$k]['inviter_number_count'] = $member->memberNumberCount($conditionSix);

        }
        return $result;
    }

    public function getDistributorAccountList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        $distributor_account = new BcDistributorAccountRecordsModel();
        $list                = $distributor_account->pageQuery($page_index, $page_size, $condition, $order, '*');
        return $list;
    }

    //极选师激活码
    function getActivationCode($id)
    {
        $num       = 6 - strlen($id);
        $output    = $id;
        $chars1    = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $charsLen1 = count($chars1) - 1;
        shuffle($chars1);
        for ($i = 0; $i < $num; $i++) {
            $output .= $chars1[mt_rand(0, $charsLen1)];
        }
        return $output;
    }

    # 激活码申请成功通知
    public function distributorTemplateSend($open_id, $form_id)
    {
        //请求地址
        $conf         = json_decode(\think\Db::name('sys_config')->where(['key' => 'SHOPAPPLET'])->find()['value'], true);
        $appid        = $conf['appid'];
        $secret       = $conf['appsecret'];
        $access_token = getAccessToken($appid, $secret);
        $select_url   = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;

        //所需下发的模板消息的id
        $template_id = getWxTemplateId('kol_check_over');

        //模板内容
        $p1 = 'BonnieClyde';
        $p2 = '签约极选师';
        $p3 = date('Y-m-d H:i:s', time());
        $p4 = '已通过';
        $p5 = '恭喜您成为签约极选师';

        //跳转页面
        $page = 'pages/member/kol/kol';

        $param = <<<EOL
{
  "touser":"$open_id",
  "template_id":"$template_id",
  "page":"$page",
  "form_id":"$form_id",
  "data":{
      "keyword1":{
          "value":"$p1",
          "color":"#F00"
      },
      "keyword2":{
          "value":"$p2",
          "color":"#F00"
      },
      "keyword3":{
          "value":"$p3"
      },
      "keyword4":{
          "value":"$p4",
          "color":"#173177"
      } ,
      "keyword5":{
          "value":"$p5"
      }
  },
  "color":"#ccc"
}
EOL;
        return curl_post($select_url, $param);
    }


//极选师
    public function distributorList($condition, $field, $order)
    {
        $distributorModel = new BcDistributorModel();
        return $distributorModel->getQuery($condition, $field, $order);
    }

    //极选师流水
    public function distributorAccountList($condition, $field, $order)
    {
        $distributor_account = new BcDistributorAccountRecordsModel();
        $list                = $distributor_account->getQuery($condition, $field, $order);
        return $list;
    }

    //业绩统计
    public function orderStatistics($start_date, $end_date, $page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $distributor_view = new BcDistributorViewModel();
        $result           = $distributor_view->getViewList($page_index, $page_size, $condition, $order);

        $distributorAccountRecords = new BcDistributorAccountRecordsModel();
        foreach ($result['data'] as $k => $v) {
            //$whereNo未出账条件
            $whereNo['uid']             = $v['uid'];
            $whereNo["settlement_time"] = [
                [
                    ">",
                    $start_date
                ],
                [
                    "<",
                    $end_date
                ]
            ];

            //$whereYes已结算条件
            $whereYes['uid']                              = $v['uid'];
            $whereYes["settlement_time"]                  = [
                [
                    ">",
                    $start_date
                ],
                [
                    "<",
                    $end_date
                ]
            ];
            $whereYes['account_type']                     = 1;
            $whereYes['from_type']                        = ['in', [1, 2]];
            $result['data'][$k]['order_number_count_no']  = 0;  //未出账订单统计(笔)
            $result['data'][$k]['order_fraction_sum_no']  = 0;  //未出账分润统计(元)
            $result['data'][$k]['order_number_count_yes'] = $distributorAccountRecords->numberCount($whereYes);  //已出账订单统计(笔)
            $result['data'][$k]['order_fraction_sum_yes'] = $distributorAccountRecords->moneySum($whereYes);  //已出账分润统计(元)
        }
        return $result;
    }

    //考核统计
    public function assessmentStatistics($start_date, $end_date, $page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $distributor_view = new BcDistributorViewModel();
        $result           = $distributor_view->getViewList($page_index, $page_size, $condition, $order);
        $order            = new NsOrderModel();
        $starMOdel        = new BcDistributorStarModel();

        foreach ($result['data'] as $k => $v) {
            $v['wx_info'] = stripslashes(htmlspecialchars_decode($v['wx_info']));
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
            $result['data'][$k]['goods_money_sum']  = $order->goodsMoneySum($conditionOne);
            $result['data'][$k]['goods_order_sum']  = $order->goodsOrderSum($conditionOne);  #成交笔数

            //星级
            if ($v['distributor_type'] == 2 || $v['distributor_type'] == 3) {
                $where['star_type'] = 1;
            } else {
                $where['star_type'] = 2;
            }
            $starList = $starMOdel->getQuery($where, '*', 'star_standard desc');

            $result['data'][$k]['star']  = '';
            $result['data'][$k]['bonus'] = 0;
            $other_money                 = $result['data'][$k]['goods_money_sum'];
            foreach ($starList as $key => $val) {
                if ($result['data'][$k]['goods_money_sum'] > $val['star_standard'] && empty($result['data'][$k]['star'])) {
                    $result['data'][$k]['star'] = $val['star_name'];
                }

                if ($result['data'][$k]['goods_money_sum'] > $val['star_standard']) {
                    $result['data'][$k]['bonus'] += ($other_money - $val['star_standard']) * $val['star_reward'];
                    $other_money = $val['star_standard'];
                }
            }
        }
        return $result;
    }

    //邀请统计
    public function invitationStatistics($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $distributor_view = new BcDistributorViewModel();
        $member           = new NsMemberViewModel();
        $result           = $distributor_view->getViewList($page_index, $page_size, $condition, $order);
        foreach ($result['data'] as $k => $v) {
            $conditionOne['nm.inviter']  = $v['uid'];
            $conditionOne1['nm.inviter'] = $v['uid'];
            $conditionOne2['nm.inviter'] = $v['uid'];
            $conditionOne3['nm.inviter'] = $v['uid'];
            $conditionOne4['nm.inviter'] = $v['uid'];

            # 当日
            $conditionOne1['bd.create_time'] = [
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
            $conditionOne2['bd.create_time'] = [
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
            $season                          = ceil(date('n') / 3);
            $conditionOne3['bd.create_time'] = [
                [
                    ">=",
                    strtotime(date('Y-m-01', mktime(0, 0, 0, ($season - 1) * 3 + 1, 1, date('Y'))))
                ],
                [
                    "<",
                    strtotime(date('Y-m-t', mktime(0, 0, 0, $season * 3, 1, date('Y'))))
                ]
            ];

            # 昨天
            $conditionOne4['bd.create_time'] = [
                [
                    ">=",
                    mktime(0, 0, 0, date('m'), date('d') - 1, date('Y'))
                ],
                [
                    "<",
                    mktime(0, 0, 0, date('m'), date('d'), date('Y')) - 1]
            ];

            $result['data'][$k]['count_day']      = $member->getInvitationCount($conditionOne1);
            $result['data'][$k]['count_month']    = $member->getInvitationCount($conditionOne2);
            $result['data'][$k]['count_quarter']  = $member->getInvitationCount($conditionOne3);
            $result['data'][$k]['count_last_day'] = $member->getInvitationCount($conditionOne4);
            $result['data'][$k]['count_all']      = $member->getInvitationCount($conditionOne);
        }
        return $result;
    }

    //出账订单列表
    public function distributorSeparationRecordsList($condition)
    {
        $distributorAccountRecords = new BcDistributorAccountRecordsModel();
        $result                    = $distributorAccountRecords->distributorSeparationRecordsList($condition);
        return $result;
    }

    //确认出账
//    public function checkoutSubmit($id_array)
//    {
//        $id_array = explode(',', $id_array);
//        foreach ($id_array as $k => $id) {
//            $id = (int) $id;
//            $data = array(
//                'is_checkout' => 1,
//                'checkout_time' => time()
//            );
//            $bcDistributorSeparationRecords = new BcDistributorSeparationRecordsModel();
//            $retval = $bcDistributorSeparationRecords->save($data, [
//                'id' => $id
//            ]);
//        }
//        return $retval;
//    }

    //确认出账
    public function checkoutSubmit($id_array)
    {
        $id_array = explode(',', $id_array);
        foreach ($id_array as $k => $id) {
            $id   = (int)$id;
            $data = array(
                'is_checkout' => 1,
                'checkout_time' => time()
            );

            $distributorAccountRecords = new BcDistributorAccountRecordsModel();
            $retval                    = $distributorAccountRecords->save($data, ['id' => $id]); //分润出账

            if ($retval) {
                $separationRecordsInfo = $distributorAccountRecords->getInfo(['id' => $id], '*');
                $distributor_model     = new BcDistributorModel();
                $distributor_model->where(['uid' => $separationRecordsInfo['uid']])->setInc('balance', $separationRecordsInfo['separation_money']); //增加余额

                $count = $distributorAccountRecords->getCount(['order_no' => $separationRecordsInfo['order_no'], 'is_checkout' => 0]);
                if ($count == 0) {
                    $order_model = new NsOrderModel();
                    $order_model->save(['is_settlement' => 2], ['order_no' => $separationRecordsInfo['order_no']]); //更新分润状态
                }
            }
        }
        return $retval;
    }

    //获取单条kol
    public function getKolInfo($condition, $field)
    {
        $distributor_info = new BcDistributorModel();
        return $distributor_info->getInfo($condition, $field);
    }

    public function getDistributorDetail($uid)
    {
        $distributor_model  = new BcDistributorModel();
        $distributor_detail = $distributor_model->get($uid);
        return $distributor_detail;
    }

    /**
     * kol锁定
     */
    public function distributorLock($uid)
    {
        $distributor = new BcDistributorModel();
        $retval      = $distributor->save([
            'status' => 0
        ], [
            'uid' => $uid
        ]);
        return $retval;
    }

    /**
     * kol解锁
     */
    public function distributorUnlock($uid)
    {
        $distributor = new BcDistributorModel();
        $retval      = $distributor->save([
            'status' => 1
        ], [
            'uid' => $uid
        ]);
        return $retval;
    }

    //kol
    public function getDistributor($condition, $field, $order)
    {
        $distributor = new BcDistributorModel();
        return $distributor->getQuery($condition, $field, $order);
    }


    /**
     * @param int $page_index
     * @param int $page_size
     * @param string $condition
     * @param string $order
     * @return data\model\multitype
     * 极选师申请列表
     */
    public function getDistributorApplyList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $distributor_info_view = new BcDistributorInfoViewModel();
        $result                = $distributor_info_view->getViewList($page_index, $page_size, $condition, $order);
        foreach ($result['data'] as $key => $v) {
            $real_name  = \think\Db::name('sys_user')->where(['uid' => $v['recommend_user']])->find()['real_name'];
            $real_name1 = \think\Db::name('sys_user')->where(['uid' => $v['inviter']])->find()['real_name'];
            if (empty($real_name)) {
                $real_name = \think\Db::name('ns_member')->where(['uid' => $v['recommend_user']])->find()['real_name'];
            }
            if (empty($real_name1)) {
                $real_name1 = \think\Db::name('ns_member')->where(['uid' => $v['inviter']])->find()['real_name'];
            }
            $result['data'][$key]['recommend_user_name'] = $real_name;
            $result['data'][$key]['inviter_user_name']   = $real_name1;
            $result['data'][$key]['created_time']        = date('Y-m-d H:i:s', $result['data'][$key]['created_time']);
        }
        return $result;
    }

    /**
     * @param $id
     * @return static
     * 获取申请详情
     */
    public function getDistributorInfoDetail($id)
    {
        $distributor_model  = new BcDistributorInfoModel();
        $distributor_detail = $distributor_model->get($id);
        $real_name          = \think\Db::name('sys_user')->where(['uid' => $distributor_detail['recommend_user']])->find()['real_name'];
        $real_name1         = \think\Db::name('sys_user')->where(['uid' => $distributor_detail['inviter']])->find()['real_name'];
        if (empty($real_name)) {
            $real_name = \think\Db::name('ns_member')->where(['uid' => $distributor_detail['recommend_user']])->find()['real_name'];
        }
        if (empty($real_name1)) {
            $real_name1 = \think\Db::name('ns_member')->where(['uid' => $distributor_detail['inviter']])->find()['real_name'];
        }
        $distributor_detail['recommend_user_name'] = $real_name;
        $distributor_detail['inviter_user_name']   = $real_name1;

        #人事
        $distributor_detail['check_info_1'] = \think\Db::name('bc_distributor_check')->where(['distributor_info_id' => $distributor_detail['id'], 'origin' => 1])->find();
        #主管
        $distributor_detail['check_info_2'] = \think\Db::name('bc_distributor_check')->where(['distributor_info_id' => $distributor_detail['id'], 'origin' => 2])->find();
        $distributor_detail['is_check']     = \think\Db::name('bc_distributor')->where(['uid' => $distributor_detail['uid']])->find()['is_check'];
        if ($distributor_detail['is_check'] == '0') {
            $distributor_detail['check_status'] = '待审核';
        } elseif ($distributor_detail['is_check'] == '1') {
            $distributor_detail['check_status'] = '审核通过';
        } else {
            $distributor_detail['check_status'] = '审核未通过';
        }

        if(empty($distributor_detail['id_face_pros'])){
            $distributor_detail['id_face_pros'] = \think\Db::name('bc_authentication_info')->where(['uid' => $distributor_detail['uid']])->find()['id_face_pros'];
            $distributor_detail['id_face_cons'] = \think\Db::name('bc_authentication_info')->where(['uid' => $distributor_detail['uid']])->find()['id_face_cons'];
        }

        return $distributor_detail;
    }

    //极选师账户 明细 配置
    public function getDistributorAccountDetail($condition)
    {
        $account = $this->getDistributorAccount($condition['uid']); //极选师账户

        //明细
        $distributorAccountRecords = new BcDistributorAccountRecordsModel();
        $accountRecords            = $distributorAccountRecords->getQuery($condition, '*', 'settlement_time desc');
        $date_array                = [];
        foreach ($accountRecords as $key => $val) {
            if (!in_array(getTimeStampTurnTimeByYmd($val['settlement_time']), $date_array)) {
                $date_array[] = getTimeStampTurnTimeByYmd($val['settlement_time']);
            }
        }
        $accountRecordsDate = [];
        foreach ($date_array as $k => $v) {
            foreach ($accountRecords as $key => $val) {
                if ($v == getTimeStampTurnTimeByYmd($val['settlement_time'])) {
                    $val['settlement_time']                     = getTimeStampTurnTime($val['settlement_time']);
                    $accountRecordsDate[$k]['date']             = $v;
                    $accountRecordsDate[$k]['accountRecords'][] = $val;
                }
            }
        }

        //极选师提现配置
        $beginToday            = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $endToday              = mktime(0, 0, 0, date('m'), date('d') + 1, date('Y')) - 1;
        $where["ask_for_date"] = [
            [
                "egt",
                $beginToday
            ],
            [
                "elt",
                $endToday
            ]
        ];
        $where["uid"]          = $condition['uid'];
        $balance_withdraw      = new NsMemberBalanceWithdrawModel();
        $cashSum               = $balance_withdraw->getSum($where, 'cash');
        $key                   = 'WITHDRAW_BALANCE';
        $config                = new Config();
        $info                  = $config->getConfig($this->instance_id, $key);
        if (!empty($info) && !empty($info['value'])) {
            $value  = json_decode($info['value'], true);
            $config = [
                'withdraw_cash_min' => $value['withdraw_cash_min'],
                'withdraw_cash_max' => $value['withdraw_cash_max'],
                'withdraw_cash_sum' => $cashSum
            ];
        } else {
            $config = [
                'withdraw_cash_min' => 1,
                'withdraw_cash_max' => 2000,
                'withdraw_cash_sum' => $cashSum
            ];
        }

        $id_info = \think\Db::name('bc_authentication_info')->where(['uid' => $this->uid])->find();
        if(empty($id_info)){
            $idCard       = '';
            $real_name    = '';
        }else{
            $idCard       = $id_info['idCard'];
            $real_name    = $id_info['real_name'];
        }
        $data = [
            'account'            => $account,
            'accountRecordsDate' => $accountRecordsDate,
            'config'             => $config,
            'switchover'         => ['switchover'   => $this->identityDistinguish(),
                                     'idCard'       => $idCard,
                                     'real_name'    => $real_name]
        ];
        return $data;
    }

    //获取极选师账户
    public function getDistributorAccount($uid)
    {
        $distributor = new BcDistributorModel();
        $accountInfo = $distributor->getInfo(['uid' => $uid], 'balance,bonus');
        return $accountInfo;
    }

    //极选师账户申请提现
//    public function addMemberBalanceWithdraw($shop_id, $withdraw_no, $uid, $account_type, $cash)
//    {
//        // 获取本店的提现设置
//        $config = new Config();
//        $withdraw_info = $config->getBalanceWithdrawConfig($shop_id);
//        // 判断是否余额提现设置是否为空 是否启用
//        if (empty($withdraw_info) || $withdraw_info['is_use'] == 0) {
//            return USER_WITHDRAW_NO_USE;
//        }
//        // 最底提现额判断
//        if ($cash < $withdraw_info['value']["withdraw_cash_min"]) {
//            return USER_WITHDRAW_MIN;
//        }
//        // 最高提现额判断
//        if ($cash > $withdraw_info['value']["withdraw_cash_max"]) {
//            return USER_WITHDRAW_MAX;
//        }
//        // 判断极选师当前余额
//        $accout = $this->getDistributorAccount($uid);
//        if($account_type == 1){
//            if ($accout['balance'] <= 0) {
//                return ORDER_CREATE_LOW_PLATFORM_MONEY;
//            }
//            if ($accout['balance'] < $cash || $cash <= 0) {
//                return ORDER_CREATE_LOW_PLATFORM_MONEY;
//            }
//        }else{
//            if ($accout['bonus'] <= 0) {
//                return ORDER_CREATE_LOW_PLATFORM_MONEY;
//            }
//            if ($accout['bonus'] < $cash || $cash <= 0) {
//                return ORDER_CREATE_LOW_PLATFORM_MONEY;
//            }
//        }
//
//        // 获取提现账户信息
//        $user = new UserModel();
//        $user_info = $user->getInfo([
//            'uid' => $uid
//        ], '*');
//
//        if($account_type == 1){
//            $memo = '分润提现';
//        }else{
//            $memo = '奖金提现';
//        }
//
//        // 添加提现记录
//        $balance_withdraw = new NsMemberBalanceWithdrawModel();
//        $data = array(
//            'shop_id' => $shop_id,
//            'withdraw_no' => $withdraw_no,
//            'uid' => $uid,
//            'bank_name' => '微信',
//            'account_type' => $account_type,
//            'account_number' => '',
//            'realname' => $user_info['real_name'],
//            'mobile' => $user_info['user_tel'],
//            'cash' => $cash,
//            'ask_for_date' => time(),
//            'status' => 0,
//            'memo' => $memo
//        );
//        $balance_withdraw->save($data);
//        //添加账户流水
//        $this->addDistributorAccountRecordsData($uid, $balance_withdraw->id, $account_type, 3, $memo, - $cash);
//        return $balance_withdraw->id;
//    }

    //添加账户流水
//    public function addDistributorAccountRecordsData($uid, $data_id, $account_type, $from_type, $memo, $money)
//    {
//        if (empty($uid)) {
//            return 1;
//        }
//        $distributorAccountRecords = new BcDistributorAccountRecordsModel();
//        $distributorAccountRecords->startTrans();
//        try {
//            $data = array(
//                'uid' => $uid,
//                'data_id' => $data_id,
//                'order_no' => '',
//                'account_type' => $account_type,
//                'from_type' => $from_type,
//                'money' =>$money,
//                'text' => $memo,
//                'settlement_time' => time()
//            );
//            $retval = $distributorAccountRecords->save($data);
//            // 更新对应极选师余额
//            $distributor = new BcDistributorModel();
//            $all_info = $distributor->getInfo(['uid' => $uid], '*');
//            if($account_type == 1){
//                $all_account = $all_info['balance'];
//                $data_distributor = array(
//                    'balance' => $all_account + $money
//                );
//            }else{
//                $all_account = $all_info['bonus'];
//                $data_distributor = array(
//                    'bonus' => $all_account + $money
//                );
//            }
//            $distributor->save($data_distributor, ['uid' => $uid]);
//            $distributorAccountRecords->commit();
//            return 1;
//        } catch (\Exception $e) {
//            $distributorAccountRecords->rollback();
//            return $e->getMessage();
//        }
//    }

    //极选师账户申请提现
    public function addMemberBalanceWithdraw($shop_id, $withdraw_no, $uid, $account_type, $cash)
    {
        // 获取本店的提现设置
        $config        = new Config();
        $withdraw_info = $config->getBalanceWithdrawConfig($shop_id);

        //极选师当日已提现额度
        $beginToday            = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $endToday              = mktime(0, 0, 0, date('m'), date('d') + 1, date('Y')) - 1;
        $where["ask_for_date"] = [
            [
                "egt",
                $beginToday
            ],
            [
                "elt",
                $endToday
            ]
        ];
        $where["uid"]          = $uid;
        $balance_withdraw      = new NsMemberBalanceWithdrawModel();
        $cashSum               = $balance_withdraw->getSum($where, 'cash');


        // 判断是否余额提现设置是否为空 是否启用
        if (empty($withdraw_info) || $withdraw_info['is_use'] == 0) {
            return USER_WITHDRAW_NO_USE;
        }
        // 最底提现额判断
        if ($cash < $withdraw_info['value']["withdraw_cash_min"]) {
            return USER_WITHDRAW_MIN;
        }
        // 最高提现额判断
        if ($cash > ($withdraw_info['value']["withdraw_cash_max"] - $cashSum)) {
            return USER_WITHDRAW_MAX;
        }
        // 判断极选师当前余额
        $accout = $this->getDistributorAccount($uid);
        if ($account_type == 1) {
            if ($accout['balance'] <= 0) {
                return ORDER_CREATE_LOW_PLATFORM_MONEY;
            }
            if ($accout['balance'] < $cash || $cash <= 0) {
                return ORDER_CREATE_LOW_PLATFORM_MONEY;
            }
        } else {
            if ($accout['bonus'] <= 0) {
                return ORDER_CREATE_LOW_PLATFORM_MONEY;
            }
            if ($accout['bonus'] < $cash || $cash <= 0) {
                return ORDER_CREATE_LOW_PLATFORM_MONEY;
            }
        }

        // 获取用户信息
        $user      = new UserModel();
        $user_info = $user->getInfo([
            'uid' => $uid
        ], '*');

        // 获取账户信息
        $distributor = new BcDistributorModel();
        $all_info    = $distributor->getInfo(['uid' => $uid], '*');

        if ($account_type == 1) {
            $memo      = '分润提现';
            $from_type = 3;
            $balance   = $all_info['balance'] - $cash;
            $bonus     = $all_info['bonus'];
        } else {
            $memo      = '奖金提现';
            $from_type = 5;
            $balance   = $all_info['balance'];
            $bonus     = $all_info['bonus'] - $cash;
        }

        // 添加提现记录
        $balance_withdraw = new NsMemberBalanceWithdrawModel();
        $balance_withdraw->startTrans();
        try {
            $data = array(
                'shop_id' => $shop_id,
                'withdraw_no' => $withdraw_no,
                'uid' => $uid,
                'bank_name' => '微信',
                'account_type' => $account_type,
                'account_number' => '',
                'realname' => $user_info['real_name'],
                'mobile' => $user_info['user_tel'],
                'cash' => $cash,
                'ask_for_date' => time(),
                'status' => 0,
                'memo' => $memo
            );
            $balance_withdraw->save($data);
            $id = $balance_withdraw->id;
            //添加账户流水
            $distributorAccountRecords = new BcDistributorAccountRecordsModel();
            $data_records              = array(
                'uid' => $uid,
                'data_id' => $id,
                'order_no' => '',
                'account_type' => $account_type,
                'from_type' => $from_type,
                'money' => -$cash,
                'text' => $memo,
                'settlement_time' => time(),
                'balance_record' => $balance,
                'bonus_record' => $bonus
            );
            $res                       = $distributorAccountRecords->save($data_records);
            if ($res > 0) {
                // 更新对应极选师余额
                $data_distributor = array(
                    'balance' => $balance,
                    'bonus' => $bonus
                );
                $ret              = $distributor->save($data_distributor, ['uid' => $uid]);
                if ($ret > 0) {
                    $retval = $this->kolWithdrawAudit($this->instance_id, $id, 1, 2, '微信', $cash, $memo);
                    if ($retval['code'] < 0) {
                        $balance_withdraw->rollback();
                    } else {
                        $balance_withdraw->commit();
                    }
                    return $retval;
                } else {
                    $balance_withdraw->rollback();
                    return array(
                        "data" => '',
                        "code" => '-50',
                        "message" => '极选师余额更新失败'
                    );
                }
            } else {
                $balance_withdraw->rollback();
                return array(
                    "data" => '',
                    "code" => '-50',
                    "message" => '账户流水添加失败'
                );
            }
        } catch (\Exception $e) {
            $balance_withdraw->rollback();
        }
    }

    //通过极选师提现请求
    public function kolWithdrawAudit($shop_id, $id, $status, $transfer_type, $transfer_name, $transfer_money, $transfer_remark)
    {
        // 查询转账的信息
        $member_balance_withdraw      = new NsMemberBalanceWithdrawModel();
        $member_balance_withdraw_info = $member_balance_withdraw->getInfo([
            'id' => $id
        ], '*');
        $transfer_status              = 0;
        $transfer_result              = "";
        if ($member_balance_withdraw_info["transfer_status"] != 1 && $member_balance_withdraw_info["status"] != 1 && $status != -1) {
            // 线上微信转账
            $user            = new UserModel();
            $userinfo        = $user->getInfo([
                'uid' => $member_balance_withdraw_info['uid']
            ]);
            $openid          = $userinfo["wx_openid"];
            $realname        = $userinfo["real_name"];
            $transfer_remark = $transfer_remark . '-极选师-' . $realname;
            $unify           = new UnifyPay();
            $wechat_retval   = $unify->wechatTransfers($openid, $member_balance_withdraw_info["withdraw_no"], $transfer_money * 100, $realname, $transfer_remark);
            $transfer_result = $wechat_retval["msg"];
            if ($wechat_retval["is_success"] > 0) {
                $transfer_status = 1;
            } else {
                $transfer_status = -1;
            }
        }
        if ($transfer_status != -1) {
            $member_balance_withdraw = new NsMemberBalanceWithdrawModel();
            $retval                  = $member_balance_withdraw->where([
                "shop_id" => $shop_id,
                "id" => $id
            ])->update([
                "status" => $status,
                "transfer_type" => $transfer_type,
                "transfer_name" => $transfer_name,
                "transfer_money" => $transfer_money,
                "transfer_status" => $transfer_status,
                "transfer_remark" => $transfer_remark,
                "transfer_result" => $transfer_result,
                "payment_date" => time(),
                "modify_date" => time()
            ]);
            return array(
                "data" => $transfer_status,
                "code" => 1,
                "message" => $transfer_result
            );
        } else {
            return array(
                "data" => '',
                "code" => '-50',
                "message" => $transfer_result
            );
        }
    }

    //拒绝极选师提现请求
    public function kolWithdrawRefuse($shop_id, $id, $status, $remark)
    {
        $member_balance_withdraw      = new NsMemberBalanceWithdrawModel();
        $retval                       = $member_balance_withdraw->where(array(
            "shop_id" => $shop_id,
            "id" => $id
        ))->update(array(
            "status" => $status,
            "transfer_remark" => $remark,
            "modify_date" => time()
        ));
        $member_balance_withdraw      = new NsMemberBalanceWithdrawModel();
        $member_balance_withdraw_info = $member_balance_withdraw->getInfo([
            'id' => $id
        ], '*');
        if ($retval > 0 && $status == -1) {
            //添加账户流水
            if ($member_balance_withdraw_info['account_type'] == 1) {
                $memo = '分润提现退回';
            } else {
                $memo = '奖金提现退回';
            }
            $this->addDistributorAccountRecordsData($member_balance_withdraw_info['uid'], $id, $member_balance_withdraw_info['account_type'], 4, $memo, $member_balance_withdraw_info["cash"]);
        }
        return $member_balance_withdraw_info;
    }


    /**
     * @param string $uid
     * @return string
     * 身份识别
     * 0:未认证  1:已认证
     */
    public function identityDistinguish($uid = '')
    {
        if (empty($uid)) {
            $uid = $this->uid;
        }
        $info = \think\Db::name('bc_authentication_info')->where(['uid' => $uid])->find();
        #未上传身份证信息
        if (empty($info['id_face_pros']) || empty($info['id_face_cons'])) {
            return '0';
        } else {
            return '1';
        }
    }

    /**
     * @param $uid
     * @param $is_recommend
     * @param $i_uid
     * @return int|string
     * @throws \think\Exception
     * 设置inviter $SourceDistribution
     * 审核通过  同步member数据
     */
    public function setInviter($uid,$i_uid,$is_recommend)
    {
        if($is_recommend == 1){
            $info['source_distribution'] = $i_uid;
            $info['inviter'] = 0;
        }else{
            $info['inviter'] = $i_uid;
            $info['source_distribution'] = 0;
        }

        $res = \think\Db::name('ns_member')->where(['uid' => $uid])->update($info);
        return $res;
    }


}