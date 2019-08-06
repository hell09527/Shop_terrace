<?php
/**
 * Member.php
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

use data\service\Member as MemberService;
use data\service\User;
use data\service\Weixin;
use data\service\Supplier;
use data\service\Config as WebConfig;
use think\Db;
use data\service\Store;
use data\service\Distributor as DistributorService;
use data\model\NsOrderModel;

/**
 * 会员管理
 *
 * @author Administrator
 *
 */
class Member extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 会员列表主页
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function memberList()
    {
        $member = new MemberService();
        if (request()->isAjax()) {
            $page_index  = request()->post("page_index", 1);
            $page_size   = request()->post('page_size', PAGESIZE);
            $search_text = request()->post('search_text', '');
//            $level_id = request()->post('levelid', - 1);
            $is_vip              = request()->post('is_vip', -1);
            $source_branch       = request()->post('source_branch', '');
            $source_distribution = request()->post('source_distribution', '');
            $traffic_acquisition_source = request()->post('traffic_acquisition_source', '');
            $condition           = [
                'su.is_member' => 1,
                'su.nick_name|su.user_tel|su.user_email' => array(
                    'like',
                    '%' . $search_text . '%'
                ),

                'su.is_system' => 0
            ];
//            if ($level_id != - 1) {
//                $condition['nml.level_id'] = $level_id;
//            }
            if ($is_vip != -1) {
                $condition['nm.is_vip'] = $is_vip;
            }
            if ($source_branch != '') {
                $condition['nm.source_branch'] = $source_branch;
            }

            if ($source_distribution != '') {
                $condition['nm.source_distribution'] = $source_distribution;
            }
            $condition['nm.traffic_acquisition_source'] = [
                'like',
                '%' . $traffic_acquisition_source . '%'
            ];
            $list = $member->getMemberList($page_index, $page_size, $condition, 'su.reg_time desc');

            return $list;
        } else {
            // 查询会员等级
//            $list = $member->getMemberLevelList(1, 0);
//            $this->assign('level_list', $list);

            //网点列表
            $store     = new Store();
            $storeList = $store->getStore([], 'store_id, store_code,store_name', 'store_id');
            $this->assign('storeList', $storeList);

            //分销列表
            $member                        = new MemberService();
            $condition["distributor_type"] = [
                [
                    ">",
                    0
                ]
            ];
            $distributorList               = $member->memberList($condition, 'uid, real_name', 'uid');
            $this->assign('distributorList', $distributorList);
            return view($this->style . 'Member/memberList');
        }
    }

    //员工列表
    public function employeeList()
    {
        $member = new MemberService();
        if (request()->isAjax()) {
            $page_index = request()->post("page_index", 1);
            $page_size  = request()->post('page_size', PAGESIZE);
            $search_text = request()->post('search_text', '');
            $condition           = [
                'user_name|mobile|org_email' => [
                    'like',
                    '%' . $search_text . '%'
                ]
            ];
            $list       = $member->getEmployeeList($page_index, $page_size, $condition);
            return $list;
        } else {
            return view($this->style . 'Member/employeeList');
        }
    }

    //更新员工信息
    public function updateEmployeeData()
    {
        $member = new MemberService();
        $retval = $member->updateEmployeeData();
        return AjaxReturn($retval);
    }

    public function update()
    {
        $condition["distributor_type"] = 4;
        $member                        = new MemberService();
        $distributorList               = $member->memberList($condition, '*', 'uid');
        foreach ($distributorList as $k => $v) {
            if ($v['source_distribution'] > 0) {
                $data   = ['parent_source_distribution' => $v['source_distribution']];
                $order  = new NsOrderModel();
                $retval = $order->save($data, ['source_distribution' => $v['uid']]);
            }
        }
        return AjaxReturn($retval);
    }

    /**
     * 查询单个会员
     *
     * @return multitype:unknown
     */
    public function getMemberDetail()
    {
        $user = new User();
        $uid  = request()->post("uid", 0);
        $info = $user->getUserInfoDetail($uid);
        return $info;
    }

    /**
     * 修改会员
     */
    public function updateMember()
    {
        if (request()->isAjax()) {
            $member       = new MemberService();
            $uid          = request()->post('uid', '');
            $user_name    = request()->post('user_name', '');
            $member_level = request()->post('level_name', '');
            $password     = request()->post('user_password', '');
            $mobile       = request()->post('tel', '');
            $email        = request()->post('email', '');
            $nick_name    = request()->post('nick_name', '');
            $sex          = request()->post('sex', '');
            $status       = request()->post('status', '');
            $res          = $member->updateMemberByAdmin($uid, $user_name, $email, $sex, $status, $mobile, $nick_name, $member_level);
            return AjaxReturn($res);
        }
    }

    /**
     * 修改密码
     */
    public function updateMemberPassword()
    {
        $user     = new User();
        $userid   = request()->post('uid', '');
        $password = request()->post('user_password', '');
        $result   = $user->updateUserInfoByUserid($userid, $password);
        return AjaxReturn($result);
    }

    /**
     * 删除 会员
     *
     * @return multitype:unknown
     */
    public function deleteMember()
    {
        $member = new MemberService();
        $uid    = request()->post("uid", 0);
        $res    = $member->deleteMember($uid);
        return AjaxReturn($res);
    }

    /**
     * 获取数据库中用户列表
     */
    public function check_username()
    {
        if (request()->isAjax()) {
            // 获取数据库中的用户列表
            $username = request()->get('username', '');
            $exist    = false;
            $member   = new MemberService();
            $exist    = $member->judgeUserNameIsExistence($username);
            return $exist;
        }
    }

    /**
     * 判断用户信息是否存在
     * @return boolean
     */
    public function checkUserInfoIsExist()
    {
        if (request()->isAjax()) {
            $info = request()->post('info', '');
            $type = request()->post('type', '');
            //是否存在
            $exist  = false;
            $member = new MemberService();

            switch ($type) {
                case "email":
                    $exist = $member->memberIsEmail($info);
                    break;
                case "mobile":
                    $exist = $member->memberIsMobile($info);
                    break;
            }
            return $exist;
        }
    }

    /**
     * 添加会员信息
     */
    public function addMember()
    {
        $member       = new MemberService();
        $user_name    = request()->post('username', '');
        $nick_name    = request()->post('nickname', '');
        $password     = request()->post('password', '');
        $member_level = request()->post('level_name', '');
        $mobile       = request()->post('tel', '');
        $email        = request()->post('email', '');
        $sex          = request()->post('sex', '');
        $status       = request()->post('status', '');
        $retval       = $member->addMember($user_name, $password, $email, $sex, $status, $mobile, $member_level);
        $result       = $member->updateNickNameByUid($retval, $nick_name);
        return AjaxReturn($retval);
    }

    /**
     * 会员积分明细
     */
    public function pointdetail()
    {
        if (request()->isAjax()) {
            $page_index                     = request()->post("page_index", 1);
            $page_size                      = request()->post('page_size', PAGESIZE);
            $member_id                      = request()->post('member_id', '');
            $search_text                    = request()->post('search_text');
            $start_date                     = request()->post('start_date') == "" ? 0 : request()->post('start_date');
            $end_date                       = request()->post('end_date') == "" ? 0 : request()->post('end_date');
            $condition['nmar.uid']          = $member_id;
            $condition['nmar.account_type'] = 1;
            if ($start_date != 0 && $end_date != 0) {
                $condition["nmar.create_time"] = [
                    [
                        ">",
                        getTimeTurnTimeStamp($start_date)
                    ],
                    [
                        "<",
                        getTimeTurnTimeStamp($end_date)
                    ]
                ];
            } elseif ($start_date != 0 && $end_date == 0) {
                $condition["nmar.create_time"] = [
                    [
                        ">",
                        getTimeTurnTimeStamp($start_date)
                    ]
                ];
            } elseif ($start_date == 0 && $end_date != 0) {
                $condition["nmar.create_time"] = [
                    [
                        "<",
                        getTimeTurnTimeStamp($end_date)
                    ]
                ];
            }
            $condition['su.nick_name'] = [
                'like',
                '%' . $search_text . '%'
            ];

            $member = new MemberService();
            $list   = $member->getPointList($page_index, $page_size, $condition, $order = '', $field = '*');
            return $list;
        }
        $member_id = request()->get('member_id', '');
        $this->assign('member_id', $member_id);
        return view($this->style . 'Member/pointDetailList');
    }

    /**
     * 会员积分管理
     */
    public function pointlist()
    {
        if (request()->isAjax()) {
            $page_index                     = request()->post("page_index", 1);
            $page_size                      = request()->post('page_size', PAGESIZE);
            $search_text                    = request()->post('search_text', '');
            $form_type                      = request()->post('form_type');
            $start_date                     = request()->post('start_date') == "" ? 0 : request()->post('start_date');
            $end_date                       = request()->post('end_date') == "" ? 0 : request()->post('end_date');
            $condition['nmar.account_type'] = 1;
            if ($form_type != '') {
                $condition['nmar.from_type'] = $form_type;
            }
            if ($start_date != 0 && $end_date != 0) {
                $condition["nmar.create_time"] = [
                    [
                        ">",
                        getTimeTurnTimeStamp($start_date)
                    ],
                    [
                        "<",
                        getTimeTurnTimeStamp($end_date)
                    ]
                ];
            } elseif ($start_date != 0 && $end_date == 0) {
                $condition["nmar.create_time"] = [
                    [
                        ">",
                        getTimeTurnTimeStamp($start_date)
                    ]
                ];
            } elseif ($start_date == 0 && $end_date != 0) {
                $condition["nmar.create_time"] = [
                    [
                        "<",
                        getTimeTurnTimeStamp($end_date)
                    ]
                ];
            }
            $condition['su.nick_name'] = [
                'like',
                '%' . $search_text . '%'
            ];

            $member = new MemberService();
            $list   = $member->getPointList($page_index, $page_size, $condition, $order = '', $field = '*');
            return $list;
        }
        return view($this->style . 'Member/pointList');
    }

    /**
     * 会员余额明细
     */
    public function accountdetail()
    {
        if (request()->isAjax()) {
            $member                         = new MemberService();
            $page_index                     = request()->post("page_index", 1);
            $page_size                      = request()->post('page_size', PAGESIZE);
            $member_id                      = request()->post('member_id');
            $search_text                    = request()->post('search_text');
            $start_date                     = request()->post('start_date') == "" ? 0 : request()->post('start_date');
            $end_date                       = request()->post('end_date') == "" ? 0 : request()->post('end_date');
            $condition['nmar.uid']          = $member_id;
            $condition['nmar.account_type'] = 2;
            if ($start_date != 0 && $end_date != 0) {
                $condition["nmar.create_time"] = [
                    [
                        ">",
                        getTimeTurnTimeStamp($start_date)
                    ],
                    [
                        "<",
                        getTimeTurnTimeStamp($end_date)
                    ]
                ];
            } elseif ($start_date != 0 && $end_date == 0) {
                $condition["nmar.create_time"] = [
                    [
                        ">",
                        getTimeTurnTimeStamp($start_date)
                    ]
                ];
            } elseif ($start_date == 0 && $end_date != 0) {
                $condition["nmar.create_time"] = [
                    [
                        "<",
                        getTimeTurnTimeStamp($end_date)
                    ]
                ];
            }
            $condition['su.nick_name'] = [
                'like',
                '%' . $search_text . '%'
            ];

            $list = $member->getAccountList($page_index, $page_size, $condition, $order = '', $field = '*');
            return $list;
        }
        $member_id = request()->get('member_id', '');
        $this->assign('member_id', $member_id);
        return view($this->style . 'Member/accountDetailList');
    }

    /**
     * 会员余额管理
     */
    public function accountlist()
    {
        if (request()->isAjax()) {
            $member      = new MemberService();
            $page_index  = request()->post("page_index", 1);
            $page_size   = request()->post('page_size', PAGESIZE);
            $search_text = request()->post('search_text', '');
            $form_type   = request()->post('form_type');
            $start_date  = request()->post('start_date') == "" ? 0 : request()->post('start_date');
            $end_date    = request()->post('end_date') == "" ? 0 : request()->post('end_date');

            $condition['nmar.account_type'] = 2;
            $condition['su.nick_name']      = [
                'like',
                '%' . $search_text . '%'
            ];
            if ($start_date != 0 && $end_date != 0) {
                $condition["nmar.create_time"] = [
                    [
                        ">",
                        getTimeTurnTimeStamp($start_date)
                    ],
                    [
                        "<",
                        getTimeTurnTimeStamp($end_date)
                    ]
                ];
            } elseif ($start_date != 0 && $end_date == 0) {
                $condition["nmar.create_time"] = [
                    [
                        ">",
                        getTimeTurnTimeStamp($start_date)
                    ]
                ];
            } elseif ($start_date == 0 && $end_date != 0) {
                $condition["nmar.create_time"] = [
                    [
                        "<",
                        getTimeTurnTimeStamp($end_date)
                    ]
                ];
            }
            if ($form_type != '') {
                $condition['nmar.from_type'] = $form_type;
            }
            $list = $member->getAccountList($page_index, $page_size, $condition, $order = '', $field = '*');
            return $list;
        }

        return view($this->style . 'Member/accountList');
    }

    /**
     * 用户锁定
     */
    public function memberLock()
    {
        $uid    = request()->post('id', '');
        $retval = 0;
        if (!empty($uid)) {
            $member = new MemberService();
            $retval = $member->userLock($uid);
        }
        return AjaxReturn($retval);
    }

    /**
     * 用户解锁
     */
    public function memberUnlock()
    {
        $uid    = request()->post('id', '');
        $retval = 0;
        if (!empty($uid)) {
            $member = new MemberService();
            $retval = $member->userUnlock($uid);
        }
        return AjaxReturn($retval);
    }

    /**
     * 粉丝列表
     *
     * @return multitype:number unknown |Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function weixinFansList()
    {
        if (request()->isAjax()) {
            $page_index  = request()->post("page_index", 1);
            $page_size   = request()->post('page_size', PAGESIZE);
            $search_text = request()->post('search_text', '');
            $condition   = array(
                'nickname_decode' => array(
                    'like',
                    '%' . $search_text . '%'
                )
            );
            $weixin      = new Weixin();
            $list        = $weixin->getWeixinFansList($page_index, $page_size, $condition);
            return $list;
        } else {
            return view($this->style . 'Member/weixinFansList');
        }
    }

    /**
     * 积分、余额调整
     */
    public function addMemberAccount()
    {
        $member = new MemberService();
        $uid    = request()->post('id', '');
        $type   = request()->post('type', '');
        $num    = request()->post('num', '');
        $text   = request()->post('text', '');
        $retval = $member->addMemberAccount(0, $type, $uid, $num, $text);
        return AjaxReturn($retval);
    }

    /**
     * 设为kol
     */
    public function setKol()
    {
        $member = new MemberService();
        $uid    = request()->post('uid', '');
        $info   = \think\Db::name('sys_user')->where(['uid' => $uid])->find();
        if (!$info['user_tel']) $this->error('该用户未绑定手机号码');
        $retval = $member->setKol($uid, $info['nick_name'], $info['user_tel'], '', 1);
        if ($retval) return AjaxReturn($retval); else $this->error('操作失败');
    }

    /**
     * 分销设置
     */
    public function setDistribution()
    {
        if (request()->isAjax()) {
            $member           = new MemberService();
            $uid              = request()->post('uid', '');
            $distributor_type = request()->post('distributor_type', '');
            $name             = request()->post('name', '');
            $store_id         = request()->post('store_id', '');
            $res              = $member->setDistribution($uid, $distributor_type, $name, $store_id);
            return AjaxReturn($res);
        }
    }

    /**
     * 会员等级列表
     */
    public function memberLevelList()
    {
        $member = new MemberService();
        if (request()->isAjax()) {
            $page_index = request()->post("page_index", 1);
            $page_size  = request()->post('page_size', PAGESIZE);
            $list       = $member->getMemberLevelList($page_index, $page_size);
            return $list;
        }
        return view($this->style . 'Member/memberLevelList');
    }

    /**
     * 添加会员等级
     */
    public function addMemberLevel()
    {
        $member = new MemberService();
        if (request()->isAjax()) {
            $level_name = request()->post("level_name", '');
            // $min_integral = request()->post("min_integral", '');
            // $quota = request()->post("quota", '');
            // $upgrade = request()->post("upgrade", '');
            $goods_discount = request()->post("goods_discount", '');
            $goods_discount = $goods_discount / 100;
            $desc           = request()->post("desc", '');
            // $relation = request()->post("relation", '');
            // $res = $member->addMemberLevel($this->instance_id, $level_name, $min_integral, $quota, $upgrade, $goods_discount, $desc, $relation);
            $res = $member->addMemberLevel($this->instance_id, $level_name, $goods_discount, $desc);
            return AjaxReturn($res);
        }
        return view($this->style . 'Member/addMemberLevel');
    }

    /**
     * 修改会员等级
     */
    public function updateMemberLevel()
    {
        $member = new MemberService();
        if (request()->isAjax()) {
            $level_id   = request()->post("level_id", 0);
            $level_name = request()->post("level_name", '');
            // $min_integral = request()->post("min_integral", '');
            // $quota = request()->post("quota", '');
            // $upgrade = request()->post("upgrade", '');
            $goods_discount = request()->post("goods_discount", '');
            $goods_discount = $goods_discount / 100;
            $desc           = request()->post("desc", '');
            // $relation = request()->post("relation", '');
            // $res = $member->updateMemberLevel($level_id, $this->instance_id, $level_name, $min_integral, $quota, $upgrade, $goods_discount, $desc, $relation);
            $res = $member->updateMemberLevel($level_id, $this->instance_id, $level_name, $goods_discount, $desc);
            return AjaxReturn($res);
        }
        $level_id               = request()->get("level_id", 0);
        $info                   = $member->getMemberLevelDetail($level_id);
        $info['goods_discount'] = $info['goods_discount'] * 100;
        $this->assign('info', $info);
        return view($this->style . 'Member/updateMemberLevel');
    }

    /**
     * 删除 会员等级
     *
     * @return multitype:unknown
     */
    public function deleteMemberLevel()
    {
        $member   = new MemberService();
        $level_id = request()->post("level_id", 0);
        $res      = $member->deleteMemberLevel($level_id);
        return AjaxReturn($res);
    }

    /**
     * 修改 会员等级 单个字段
     *
     * @return multitype:unknown
     */
    public function modityMemberLevelField()
    {
        $member      = new MemberService();
        $level_id    = request()->post("level_id", 0);
        $field_name  = request()->post("field_name", '');
        $field_value = request()->post("field_value", '');
        $res         = $member->modifyMemberLevelField($level_id, $field_name, $field_value);
        return AjaxReturn($res);
    }

    /**
     * 会员提现列表
     */
    public function userCommissionWithdrawList()
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
            $uid_string = "";
            $where      = array();
            if ($user_phone != "") {
                $where["nick_name"] = array(
                    "like",
                    "%" . $user_phone . "%"
                );
            }
            if (!empty($where)) {
                $uid_string = $this->getUserUids($where);
                if ($uid_string != "") {
                    $condition["uid"] = array(
                        "in",
                        $uid_string
                    );
                } else {
                    $condition["uid"] = 0;
                }
            }
            $condition["shop_id"] = $this->instance_id;
            $list                 = $member->getMemberBalanceWithdraw($pageindex, PAGESIZE, $condition, 'ask_for_date desc');
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
                    'url' => "Member/userCommissionWithdrawList",
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
            return view($this->style . "Member/userCommissionWithdrawList");
        }
    }

    /**
     * 用户提现审核
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function userCommissionWithdrawAudit()
    {
        $id                  = request()->post('id', '');
        $status              = request()->post('status', '');
        $transfer_type       = request()->post('transfer_type', '');
        $transfer_name       = request()->post('transfer_name', '');
        $transfer_money      = request()->post('transfer_money', '');
        $transfer_remark     = request()->post('transfer_remark', '');
        $transfer_no         = request()->post('transfer_no', '');
        $transfer_account_no = request()->post('transfer_account_no', '');
        $type_id             = request()->post('type_id', '');
        $member              = new MemberService();
        $retval              = $member->MemberBalanceWithdrawAudit($this->instance_id, $id, $status, $transfer_type, $transfer_name, $transfer_money, $transfer_remark, $transfer_no, $transfer_account_no, $type_id);
        return $retval;
    }

    /**
     * 拒绝提现请求
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function userCommissionWithdrawRefuse()
    {
        $id     = request()->post('id', '');
        $status = request()->post('status', '');
        $remark = request()->post('remark', '');
        $member = new MemberService();
        $retval = $member->userCommissionWithdrawRefuse($this->instance_id, $id, $status, $remark);
        return AjaxReturn($retval);
    }

    /**
     * 查寻符合条件的数据并返回id （多个以“,”隔开）
     *
     * @param unknown $condition
     * @return string
     */
    public function getUserUids($condition)
    {
        $member     = new MemberService();
        $list       = $member->getMemberAll($condition);
        $uid_string = "";
        foreach ($list as $k => $v) {
            $uid_string = $uid_string . "," . $v["uid"];
        }
        if ($uid_string != "") {
            $uid_string = substr($uid_string, 1);
        }
        return $uid_string;
    }

    /**
     * 获取提现详情
     *
     * @return unknown
     */
    public function getWithdrawalsInfo()
    {
        $id     = request()->post('id', '');
        $member = new MemberService();
        $retval = $member->getMemberWithdrawalsDetails($id);
        return $retval;
    }

    /**
     * 供货商列表
     */
    public function supplierList()
    {
        $supplier = new Supplier();
        if (request()->isAjax()) {
            $page_index                 = request()->post("page_index", 1);
            $page_size                  = request()->post('page_size', PAGESIZE);
            $search_text                = request()->post('search_text', '');
            $condition['supplier_name'] = array('like', '%' . $search_text . '%');
            $list                       = $supplier->getSupplierList($page_index, $page_size, $condition);
            return $list;
        }
        return view($this->style . 'Member/supplierList');
    }

    /**
     * 添加供货商
     * @return multitype:unknown
     */
    public function addSupplier()
    {
        $supplier = new Supplier();
        if (request()->isAjax()) {
            $uid             = request()->post('uid', 0);
            $supplier_name   = request()->post('supplier_name', '');
            $linkman_name    = request()->post('linkman_name', '');
            $linkman_tel     = request()->post('linkman_tel', '');
            $linkman_address = request()->post('linkman_address', '');
            $desc            = request()->post('desc', '');
            $res             = $supplier->addSupplier($uid, $supplier_name, $linkman_name, $linkman_tel, $linkman_address, $desc);
            return AjaxReturn($res);
        }
        return view($this->style . 'Member/addSupplier');
    }

    /**
     * 修改代理商
     * @return multitype:unknown
     */
    public function updateSupplier()
    {
        $supplier = new Supplier();
        if (request()->isAjax()) {
            $supplier_id     = request()->post('supplier_id', 0);
            $supplier_name   = request()->post('supplier_name', '');
            $linkman_name    = request()->post('linkman_name', '');
            $linkman_tel     = request()->post('linkman_tel', '');
            $linkman_address = request()->post('linkman_address', '');
            $desc            = request()->post('desc', '');
            $res             = $supplier->updateSupplier($supplier_id, $supplier_name, $linkman_name, $linkman_tel, $linkman_address, $desc);
            return AjaxReturn($res);
        }
        $supplier_id = request()->get('supplier_id', 0);
        $info        = $supplier->getSupplierInfo($supplier_id);
        $this->assign('supplier_id', $supplier_id);
        $this->assign('info', $info);
        return view($this->style . 'Member/updateSupplier');
    }

    /**
     * 删除代理商
     * @return multitype:unknown
     */
    public function deleteSupplier()
    {
        $supplier          = new Supplier();
        $supplier_id_array = request()->post('supplier_id', 0);
        $res               = $supplier->deleteSupplier($supplier_id_array);
        return AjaxReturn($res);
    }

    /**
     * 会员数据excel导出
     */
    public function memberDataExcel()
    {
        $xlsName     = "会员数据列表";
        $xlsCell     = array(
            array('user_name', '用户名'),
            array('nick_name', '昵称'),
            array('user_tel', '手机'),
            array('card_name', '真实姓名'),
            array('birthday', '出生日期'),
            array('sex', '性别'),
            array('user_email', '邮箱'),
            array('is_vip', '会员等级'),
            array('point', '积分'),
            array('balance', '账户余额'),
            array('reg_time', '注册时间'),
            array('current_login_time', '最后登录时间'),
            array('v_buy_time', '会员购买时间'),
            array('v_over_time', '会员到期时间'),
            array('source_distribution_name', '会员来源'),
            array('traffic_acquisition_source', '引流来源'),
        );
        $search_text = request()->get('search_text', '');
//        $level_id = request()->get('levelid', -1);
        $is_vip              = request()->get('is_vip', -1);
        $source_branch       = request()->get('source_branch', '');
        $source_distribution = request()->get('source_distribution', '');
        $traffic_acquisition_source = request()->get('traffic_acquisition_source', '');
        $condition           = [
            'su.is_member' => 1,
            'su.nick_name|su.user_tel|su.user_email' => array(
                'like',
                '%' . $search_text . '%'
            ),

            'su.is_system' => 0
        ];
//        if ($level_id != - 1) {
//            $condition['nml.level_id'] = $level_id;
//        }
        if ($is_vip != -1) {
            $condition['nm.is_vip'] = $is_vip;
        }
        if ($source_branch != '') {
            $condition['nm.source_branch'] = $source_branch;
        }

        if ($source_distribution != '') {
            $condition['nm.source_distribution'] = $source_distribution;
        }
        $condition['nm.traffic_acquisition_source'] = [
            'like',
            '%' . $traffic_acquisition_source . '%'
        ];
        $member = new MemberService();
        $list   = $member->getMemberList(1, 0, $condition, 'su.reg_time desc');
        $list   = $list["data"];
        $sex    = ['保密', '男', '女'];
        $is_vip = ['普通会员', '付费会员'];
        foreach ($list as $k => $v) {
            $list[$k]["sex"]                = $sex[$v["sex"]];
            $list[$k]["is_vip"]             = $is_vip[$v["is_vip"]];
            $list[$k]["reg_time"]           = getTimeStampTurnTime($v["reg_time"]);
            $list[$k]["current_login_time"] = getTimeStampTurnTime($v["current_login_time"]);
            if ($v["vip_buy_time"]) {
                $list[$k]["v_buy_time"]  = getTimeStampTurnTime($v["vip_buy_time"]);
                $list[$k]["v_over_time"] = getTimeStampTurnTime($v["vip_buy_time"] + 86400 * 365);
            } else {
                $list[$k]["v_buy_time"]  = '';
                $list[$k]["v_over_time"] = '';
            }
        }
        dataExcel($xlsName, $xlsCell, $list);
    }

    /**
     * 更新粉丝信息
     */
    public function updateWchatFansList()
    {
        $page_index = request()->post("page_index", 0);
        $page_size  = 50;
        if ($page_index == 0) {
            //建立连接，同时获取所有用户openid
            $weixin      = new Weixin();
            $openid_list = $weixin->getWeixinOpenidList();

            if (!empty($openid_list['total'])) {
                $_SESSION['wchat_openid_list'] = $openid_list['openid_list'];
                if ($openid_list['total'] % $page_size == 0) {
                    $page_count = $openid_list['total'] / $page_size;
                } else {
                    $page_count = (int)($openid_list['total'] / $page_size) + 1;
                }
                return array(
                    'total' => $openid_list['total'],
                    'page_count' => $page_count,
                    'errcode' => '0',
                    'errorMsg' => ''
                );
            } else {
                return $openid_list;
            }
        } else {
            //对应页数更新用户粉丝信息
            $get_fans_openid_list = $_SESSION['wchat_openid_list'];
            if (!empty($get_fans_openid_list)) {
                $start                 = ($page_index - 1) * $page_size;
                $page_fans_openid_list = array_slice($get_fans_openid_list, $start, $page_size);
                if (!empty($page_fans_openid_list)) {
                    $str = '{ "user_list" : [';
                    foreach ($page_fans_openid_list as $key => $value) {
                        $str .= ' {"openid" : "' . $value . '"},';
                    }
                    $openidlist = substr($str, 0, strlen($str) - 1);
                    $openidlist .= ']}';
                    $weixin = new Weixin();
                    $result = $weixin->UpdateWchatFansList($openidlist);
                    return $result;
                } else {
                    return array(
                        'errcode' => '0',
                        'errorMsg' => 'success'
                    );
                }

            }
        }
    }

    /**
     * 获取用户日志列表
     */
    public function userOperationLogList()
    {
        if (request()->isAjax()) {
            $member     = new MemberService();
            $start_date = request()->post('start_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('start_date'));
            $end_date   = request()->post('end_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('end_date'));
            $page_index = request()->post("page_index", 1);
            $page_size  = request()->post("page_size", PAGESIZE);
            $user_name  = request()->post("search_text", "");

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
            } elseif ($start_date != 0 && $end_date == 0) {
                $condition["create_time"] = [
                    [
                        ">",
                        $start_date
                    ]
                ];
            } elseif ($start_date == 0 && $end_date != 0) {
                $condition["create_time"] = [
                    [
                        "<",
                        $end_date
                    ]
                ];
            }
            if (!empty($user_name)) {
                $condition["user_name"] = [
                    [
                        "like",
                        "%$user_name%"
                    ]
                ];
            }

            $list = $member->getUserOperationLogList($page_index, $page_size, $condition, "create_time desc");
            return $list;
        }
        return view($this->style . "Member/userOperationLogList");
    }

}