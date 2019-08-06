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
namespace app\api\controller;

use data\model\NsCouponModel;
use data\model\NsCouponTypeModel;
use data\service\Config;
use data\service\Member\MemberAccount as MemberAccount;
use data\service\Member as MemberService;
use data\service\Order as OrderService;
use data\service\Platform;
use data\service\promotion\PromoteRewardRule;
use Qiniu\Http\Request;
use think;
use think\Session;
use data\service\UnifyPay;
use data\service\Verification;
use data\service\Goods;
use think\Cache;

/**
 * 会员
 *
 * @author Administrator
 *
 */
class Member extends BaseController
{

    public $notice;

    public $login_verify_code;

    public function __construct()
    {
        parent::__construct();
        // 是否开启验证码
        $web_config              = new Config();
        $this->login_verify_code = $web_config->getLoginVerifyCodeConfig($this->instance_id);
        // 是否开启通知
        $instance_id                  = 0;
        $web_config                   = new Config();
        $noticeMobile                 = $web_config->getNoticeMobileConfig($instance_id);
        $noticeEmail                  = $web_config->getNoticeEmailConfig($instance_id);
        $this->notice['noticeEmail']  = $noticeEmail[0]['is_use'];
        $this->notice['noticeMobile'] = $noticeMobile[0]['is_use'];
    }

    /**
     * 查询是否开启验证码
     * @return Ambigous <\think\response\Json, \think\Response, \think\response\View, \think\response\Xml, \think\response\Redirect, \think\response\Jsonp, unknown, \think\Response>
     */
    public function getLoginVerifyCodeConfig()
    {
        $title             = "查询是否开启验证码";
        $web_config        = new Config();
        $login_verify_code = $web_config->getLoginVerifyCodeConfig(0);
        return $this->outMessage($title, $login_verify_code);
    }

    /**
     * 查询是否开启通知
     * @return Ambigous <\think\response\Json, \think\Response, \think\response\View, \think\response\Xml, \think\response\Redirect, \think\response\Jsonp, unknown, \think\Response>
     */
    public function getNoticeConfig()
    {
        $title                  = "查询通知是否开启";
        $web_config             = new Config();
        $noticeMobile           = $web_config->getNoticeMobileConfig(0);
        $noticeEmail            = $web_config->getNoticeEmailConfig(0);
        $notice['noticeEmail']  = $noticeEmail[0]['is_use'];
        $notice['noticeMobile'] = $noticeMobile[0]['is_use'];
        return $this->outMessage($title, $notice);

    }

    /**
     * 获取会员详细信息
     */
    public function getMemberDetail()
    {
        $title = "获取会员详细信息";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $member      = new MemberService();
        $member_info = $member->getMemberDetail($this->instance_id);
        return $this->outMessage($title, $member_info);
    }

    /**
     * 获取会员中心首页广告位
     */
    public function getMemberIndexAdv()
    {
        $title = "获取会员中心首页广告位";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $platform  = new Platform();
        $index_adv = $platform->getPlatformAdvPositionDetail(1152);
        return $this->outMessage($title, $index_adv);
    }

    /**
     * 添加账户流水
     */
    public function addMemberAccountData()
    {
        $title = '添加账户流水';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $member_account = new MemberAccount();
        $account_type   = request()->post('account_type', '');
        $sign           = request()->post('sign', '');
        $number         = request()->post('number', '');
        $from_type      = request()->post('from_type', '');
        $data_id        = request()->post('data_id', '');
        $text           = request()->post('text', '');
        $res            = $member_account->addMemberAccountData($this->instance_id, $account_type, $this->uid, $sign, $number, $from_type, $data_id, $text);
        return $this->outMessage($title, $res);
    }

    /*
     * 单店B2C版
     */
    public function memberIndex()
    {
        $title = "会员个人中心数据";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }

        $member   = new MemberService();
        $platform = new Platform();

        // 查询用户是否是本店核销员
        $verification_service    = new Verification();
        $is_verification         = $verification_service->getShopVerificationInfo($this->uid, $this->instance_id);
        $data['is_verification'] = $is_verification;

        // 商城是否开启虚拟商品
        $is_open_virtual_goods         = $this->getIsOpenVirtualGoodsConfig($this->instance_id);
        $data['is_open_virtual_goods'] = $is_open_virtual_goods;

        // 判断是否开启了签到送积分
        $config                 = new Config();
        $integralconfig         = $config->getIntegralConfig($this->instance_id);
        $data['integralConfig'] = $integralconfig;

        // 判断用户是否签到
        $dataMember     = new MemberService();
        $isSign         = $dataMember->getIsMemberSign($this->uid, $this->instance_id);
        $data['isSign'] = $isSign;

        // 待支付订单数量
        $order               = new OrderService();
        $unpaidOrder         = $order->getOrderNumByOrderStatu([
            'order_status' => 0,
            "buyer_id" => $this->uid,
            'order_type' => array(
                "in",
                "1,3"
            )
        ]);
        $data['unpaidOrder'] = $unpaidOrder;

        // 待发货订单数量
        $shipmentPendingOrder         = $order->getOrderNumByOrderStatu([
            'order_status' => 1,
            "buyer_id" => $this->uid,
            'order_type' => array(
                "in",
                "1,3"
            )
        ]);
        $data['shipmentPendingOrder'] = $shipmentPendingOrder;

        // 待收货订单数量
        $goodsNotReceivedOrder         = $order->getOrderNumByOrderStatu([
            'order_status' => 2,
            "buyer_id" => $this->uid,
            'order_type' => array(
                "in",
                "1,3"
            )
        ]);
        $data['goodsNotReceivedOrder'] = $goodsNotReceivedOrder;

        // 退款订单
        $refundOrder         = $order->getOrderNumByOrderStatu([
            'order_status' => array('in', [-1, -2]),
            "buyer_id" => $this->uid,
            'order_type' => array(
                "in",
                "1,3"
            )
        ]);
        $data['refundOrder'] = $refundOrder;

        // 待赠送礼品订单数量
        $giftGiveOrder         = $order->getOrderNumByOrderStatu([
            'order_status' => 11,
            "buyer_id" => $this->uid,
            'order_type' => 4
        ]);
        $data['giftGiveOrder'] = $giftGiveOrder;

        return $this->outMessage($title, $data);
    }

    /**
     * 会员地址管理
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function getMemberAddressList()
    {
        $title = "获取会员地址";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $applet_member = new MemberService();
        $addresslist   = $applet_member->getMemberExpressAddressList(1, 0, ['uid' => $this->uid]);
        return $this->outMessage($title, $addresslist);
    }

    /**
     * 获取本地会员地址管理*
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function addMemberLocalAddress()
    {
        $title = "添加会员地址,注意传入省市区id";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $applet_member = new MemberService();
        $consigner     = request()->post('consigner', '');
        $mobile        = request()->post('mobile', '');
        if (empty($mobile)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数mobile");
        }
        $phone    = request()->post('phone', '');
        $province = request()->post('province', '');
        if (empty($province)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数province");
        } else {
            $province = \think\Db::table('sys_province')->where(['province_name' => $province])->find()['province_id'];
//            $province = \think\Db::table('sys_province')->where('province_name', $province)->column('province_id')?:'';
        }

        $city = request()->post('city', '');
        if (empty($city)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数city");
        } else {
//            $city = \think\Db::table('sys_city')->where('city_name',$city)->column('city_id')?:'';
            $city = \think\Db::table('sys_city')->where(['city_name' => $city])->find()['city_id'];
        }

        $district = request()->post('district', '');
        if (empty($district)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数district");
        } else {
//            $district = \think\Db::table('sys_district')->where('district_name',$district)->column('district_id')?:'';
            $district = \think\Db::table('sys_district')->where(['district_name' => $district])->find()['district_id'];
        }

        $address = request()->post('address', '');
        if (empty($address)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数address");
        }
        $zip_code = request()->post('zip_code', '');
        $alias    = request()->post('alias', '');


        $retval = $applet_member->addMemberLocalAddress($consigner, $mobile, $phone, $province, $city, $district, $address, $zip_code, $alias);
        return $this->outMessage($title, $retval);
    }

    /**
     * 添加地址
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function addMemberAddress()
    {
        $title = "添加会员地址,注意传入省市区id";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $applet_member = new MemberService();
        $consigner     = request()->post('consigner', '');
        $mobile        = request()->post('mobile', '');
        if (empty($mobile)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数mobile");
        }
        $phone    = request()->post('phone', '');
        $province = request()->post('province', '');
        if (empty($province)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数province");
        }
        $city = request()->post('city', '');
        if (empty($city)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数city");
        }
        $district = request()->post('district', '');
        $address  = request()->post('address', '');
        if (empty($address)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数address");
        }
        $zip_code = request()->post('zip_code', '');
        $alias    = request()->post('alias', '');
        $retval   = $applet_member->addMemberExpressAddress($consigner, $mobile, $phone, $province, $city, $district, $address, $zip_code, $alias);
        return $this->outMessage($title, $retval);

    }

    /**
     * 修改会员地址
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >|Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function updateMemberAddress()
    {
        $title = "修改会员地址,注意传入省市区id";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $applet_member = new MemberService();
        $id            = request()->post('id', '');
        if (empty($id)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数id");
        }
        $consigner = request()->post('consigner', '');
        $mobile    = request()->post('mobile', '');
        if (empty($mobile)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数mobile");
        }
        $phone    = request()->post('phone', '');
        $province = request()->post('province', '');
        if (empty($province)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数province");
        }
        $city = request()->post('city', '');
        if (empty($city)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数city");
        }
        $district = request()->post('district', '');
        $address  = request()->post('address', '');
        if (empty($address)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数address");
        }
        $zip_code = request()->post('zip_code', '');
        $alias    = request()->post('alias', '');
        $retval   = $applet_member->updateMemberExpressAddress($id, $consigner, $mobile, $phone, $province, $city, $district, $address, $zip_code, $alias);
        return $this->outMessage($title, $retval);
    }

    /**
     * 获取用户地址详情
     *
     * @return Ambigous <\think\static, multitype:, \think\db\false, PDOStatement, string, \think\Model, \PDOStatement, \think\db\mixed, multitype:a r y s t i n g Q u e \ C l o , \think\db\Query, NULL>
     */
    public function getMemberAddressDetail()
    {
        $title = "获取用户地址详情";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $address_id    = request()->post('id', 0);
        $applet_member = new MemberService();
        $info          = $applet_member->getMemberExpressAddressDetail($address_id);
        return $this->outMessage($title, $info);
    }

    /**
     * 会员地址删除
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function memberAddressDelete()
    {
        $title = "删除会员地址";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $id            = request()->post('id', '');
        $applet_member = new MemberService();
        $res           = $applet_member->memberAddressDelete($id);
        return $this->outMessage($title, $res);

    }

    /**
     * 修改会员默认地址
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function updateAddressDefault()
    {
        $title         = "修改默认会员地址";
        $id            = request()->post('id', '');
        $applet_member = new MemberService();
        $res           = $applet_member->updateAddressDefault($id);
        return $this->outMessage($title, $res);
    }

    /**
     * 获取会员积分余额账户情况
     */
    public function getMemberAccount()
    {
        // 获取店铺的积分列表
        $title         = "获取会员账户,分为平台账户和店铺会员账户";
        $applet_member = new MemberService();
        $account_list  = $applet_member->getShopAccountListByUser($this->uid, 1, 0);
        return $this->outMessage($title, $account_list);
    }

    /**
     * 会员账户流水
     */
    public function getMemberAccountRecordsList()
    {
        $title = "获取会员账户流水,分为平台账户和店铺会员账户,余额只有平台账户account_type:1积分2余额";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $shop_id                        = request()->post("shop_id", 0);
        $account_type                   = request()->post("account_type", 1);
        $condition['nmar.shop_id']      = $shop_id;
        $condition['nmar.uid']          = $this->uid;
        $condition['nmar.account_type'] = $account_type;
        // 查看用户在该商铺下的积分消费流水
        $member            = new MemberService();
        $member_point_list = $member->getAccountList(1, 0, $condition);
        return $this->outMessage($title, $member_point_list);
    }

    /**
     * 余额提现记录
     */
    public function balanceWithdraw()
    {
        $title = "获取会员提现记录";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        // 该店铺下的余额提现记录
        $member               = new MemberService();
        $uid                  = $this->uid;
        $shopid               = 0;
        $condition['uid']     = $uid;
        $condition['shop_id'] = $shopid;
        $withdraw_list        = $member->getMemberBalanceWithdraw(1, 0, $condition);
        foreach ($withdraw_list['data'] as $k => $v) {
            if ($v['status'] == 1) {
                $withdraw_list['data'][$k]['status'] = '已同意';
            } else
                if ($v['status'] == 0) {
                    $withdraw_list['data'][$k]['status'] = '已申请';
                } else {
                    $withdraw_list['data'][$k]['status'] = '已拒绝';
                }
        }
        return $this->outMessage($title, $withdraw_list);
    }

    /**
     * 会员优惠券
     */
    public function memberCoupon()
    {
        $title = "会员优惠券列表";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $member       = new MemberService();
        $type         = request()->post('status', '');
        $shop_id      = $this->instance_id;
        $counpon_list = $member->getMemberCounponList($type, $shop_id);
        foreach ($counpon_list as $key => $item) {
            if ($item['use_type'] == 2) {
                $times                                 = $item['get_after_days'] * 86400 + $item['fetch_time'];
                $counpon_list[$key]['coupon_end_time'] = date("Y-m-d", $times);
            } else {
                $counpon_list[$key]['start_time'] = date("Y-m-d", $item['start_time']);
                $counpon_list[$key]['end_time']   = date("Y-m-d", $item['end_time']);
            }

        }
        return $this->outMessage($title, $counpon_list);

    }

    /**
     * 修改密码
     */
    public function modifyPassword()
    {
        $title = "会员修改密码";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $member       = new MemberService();
        $uid          = $this->uid;
        $old_password = request()->post('old_password', '');
        $new_password = request()->post('new_password', '');
        $retval       = $member->ModifyUserPassword($uid, $old_password, $new_password);
        return $this->outMessage($title, $retval);
    }

    /**
     * 修改邮箱
     */
    public function modifyEmail()
    {
        $title = "会员修改邮箱";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $member = new MemberService();
        $uid    = $this->uid;
        $email  = request()->post('email', '');
        if (empty($email)) {
            return $this->outMessage($title, "", '-50', "无法获取邮箱信息");
        }
        $retval = $member->modifyEmail($uid, $email);
        return $this->outMessage($title, $retval);
    }

    /**
     * 修改手机
     */
    public function modifyMobile()
    {
        $title = "会员修改手机";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $uid    = $this->uid;
        $mobile = request()->post('mobile', '');
        if (empty($mobile)) {
            return $this->outMessage($title, "", '-50', "无法获取手机号码");
        }
        $member = new MemberService();
        $retval = $member->modifyMobile($uid, $mobile);
        return $this->outMessage($title, $retval);
    }

    /**
     * 修改昵称
     *
     * @return unknown[]
     */
    public function modifyNickName()
    {
        $title = "会员修改昵称";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $uid      = $this->uid;
        $nickname = request()->post('nickname', '');
        if (empty($nickname)) {
            return $this->outMessage($title, "", '-50', "无法获取昵称信息");
        }
        $member = new MemberService();
        $retval = $member->modifyNickName($uid, $nickname);
        return $this->outMessage($title, $retval);
    }

    /**
     * 积分兑换余额
     *
     * @return \think\response\View
     */
    public function ajaxIntegralExchangeBalance()
    {
        $title = "积分兑换余额";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $point   = request()->post('amount', 0);
        $point   = (float)$point;
        $shop_id = request()->post('shop_id', 0);
        $result  = $this->user->memberPointToBalance($this->uid, $shop_id, $point);
        return $this->outMessage($title, $result);
    }

    /**
     * 获取提现配置
     */
    public function getBalanceConfig()
    {
        $title  = "获取提现配置";
        $config = new Config();
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }

        $balanceConfig = $config->getBalanceWithdrawConfig($this->instance_id);
        $balanceConfig = $balanceConfig['value']['withdraw_account'];
        return $this->outMessage($title, $balanceConfig);
    }

    /**
     * 账户详情
     */
    public function accountInfo()
    {
        $title = "会员银行账户详情";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $id = request()->post('id', 0);
        if (empty($id) || !is_numeric($id)) {
            return $this->outMessage($title, "", '-50', "无法获取账户详情");
        }
        $member       = new MemberService();
        $account_info = $member->getMemberBankAccountDetail($id);
        return $this->outMessage($title, $account_info);
    }


    /**
     * 账户列表
     * 任鹏强
     * 2017年3月13日10:52:59
     */
    public function accountList()
    {
        $title = "会员银行账户列表";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $member       = new MemberService();
        $account_list = $member->getMemberBankAccount();
        return $this->outMessage($title, $account_list);
    }

    /**
     * 添加账户
     */
    public function addAccount()
    {
        $title = "添加会员银行账户";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }

        $member            = new MemberService();
        $uid               = $this->uid;
        $realname          = request()->post('realname', '');
        $mobile            = request()->post('mobile', '');
        $account_type      = request()->post('account_type', '1');
        $account_type_name = request()->post('account_type_name', '');
        $account_number    = request()->post('account_number', '');
        $branch_bank_name  = request()->post('branch_bank_name', '');
        if (!empty($account_type)) {
            if ($account_type == 2 || $account_type == 3) {
                if (empty($realname) || empty($mobile) || empty($account_type) || empty($account_type_name)) {
                    return $this->outMessage($title, -1);
                }
            } else {
                if (empty($realname) || empty($mobile) || empty($account_type) || empty($account_type_name) || empty($account_number) || empty($branch_bank_name)) {
                    return $this->outMessage($title, -2);
                }
            }
        } else {
            return $this->outMessage($title, -3);
        }
        $retval = $member->addMemberBankAccount($uid, $account_type, $account_type_name, $branch_bank_name, $realname, $account_number, $mobile);
        return $this->outMessage($title, $retval);
    }

    /**
     * 修改账户信息
     */
    public function updateAccount()
    {
        $title = "修改账户信息";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $member            = new MemberService();
        $uid               = $this->uid;
        $account_id        = request()->post('id', '');
        $realname          = request()->post('realname', '');
        $mobile            = request()->post('mobile', '');
        $account_type      = request()->post('account_type', '1');
        $account_type_name = request()->post('account_type_name', '');
        $account_number    = request()->post('account_number', '');
        $branch_bank_name  = request()->post('branch_bank_name', '');
        if (!empty($account_type)) {
            if ($account_type == 2 || $account_type == 3) {
                if (empty($realname) || empty($mobile) || empty($account_type) || empty($account_type_name)) {
                    return $this->outMessage($title, -1);
                }
            } else {
                if (empty($realname) || empty($mobile) || empty($account_type) || empty($account_type_name) || empty($account_number) || empty($branch_bank_name)) {
                    return $this->outMessage($title, -2);
                }
            }
        } else {
            return $this->outMessage($title, -3);
        }
        $retval = $member->updateMemberBankAccount($account_id, $account_type, $account_type_name, $branch_bank_name, $realname, $account_number, $mobile);
        return $this->outMessage($title, $retval);
    }

    /**
     * 删除账户信息
     */
    public function delAccount()
    {
        $title = "删除账户信息";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $applet_member = new MemberService();
        $account_id    = request()->post('id', '');
        if (empty($account_id)) {
            return $this->outMessage($title, "", '-50', "无法获取账户信息");
        }
        $retval = $applet_member->delMemberBankAccount($account_id);
        return $this->outMessage($title, $retval);
    }

    /**
     * 设置默认账户
     */
    public function checkAccount()
    {
        $title = "设置选中账户";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $member     = new MemberService();
        $uid        = $this->uid;
        $account_id = request()->post('id', '');
        $retval     = $member->setMemberBankAccountDefault($uid, $account_id);
        return $this->outMessage($title, $retval);
    }

    /**
     * 用户签到
     */
    public function signIn()
    {
        $title = "用户签到";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $rewardRule = new PromoteRewardRule();
        $retval     = $rewardRule->memberSign($this->uid, $this->instance_id);
        return $this->outMessage($title, $retval);
    }

    /**
     * 分享送积分
     */
    public function shareGivePoint()
    {
        $title = "分享送积分";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $rewardRule = new PromoteRewardRule();
        $retval     = $rewardRule->memberShareSendPoint($this->instance_id, $this->uid);
        return $this->outMessage($title, $retval);
    }

    /**
     * 用户充值余额
     */
    public function recharge()
    {
        $title = "用户充值余额";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $pay    = new UnifyPay();
        $pay_no = $pay->createOutTradeNo();
        return $this->outMessage($title, $pay_no);
    }

    /**
     * 创建充值订单
     */
    public function createRechargeOrder()
    {
        $title = "创建充值订单";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $recharge_money = request()->post('recharge_money', 0);
        if ($recharge_money <= 0) {
            return $this->outMessage($title, "", '-50', "支付金额必须大于0");
        }
        $out_trade_no = request()->post('out_trade_no', '');
        if (empty($out_trade_no)) {
            return $this->outMessage($title, "", '-50', "支付流水号不能为空");
        }
        $member = new MemberService();
        $retval = $member->createMemberRecharge($recharge_money, $this->uid, $out_trade_no);
        return $this->outMessage($title, $retval);
    }

    /**
     * 申请提现页面数据
     */
    public function toWithdrawInfo()
    {
        $title = "申请提现页面数据";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $member       = new MemberService();
        $account_list = $member->getMemberBankAccount(1);
        // 获取会员余额
        $uid           = $this->uid;
        $members       = new MemberAccount();
        $account       = $members->getMemberBalance($uid);
        $shop_id       = $this->instance_id;
        $config        = new Config();
        $balanceConfig = $config->getBalanceWithdrawConfig($shop_id);
        //dump($balanceConfig);
        $withdraw_cash_min = $balanceConfig['value']["withdraw_cash_min"];
        $poundage          = $balanceConfig['value']["withdraw_multiple"];
        $withdraw_message  = $balanceConfig['value']["withdraw_message"];

        $data = array(
            'withdraw_message' => $withdraw_message,
            'account_list' => $account_list,
            'poundage' => $poundage,
            'withdraw_cash_min' => $withdraw_cash_min,
            'account' => $account,
        );
        return $this->outMessage($title, $data);
    }

    /**
     * 申请提现
     */
    public function toWithdraw()
    {
        $title = "申请提现页面数据";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $bank_account_id = request()->post('bank_account_id', '');
        if (empty($bank_account_id)) {
            return $this->outMessage($title, "", '-50', "无法获取账户信息");
        }
        $withdraw_no = time() . rand(111, 999);
        $cash        = request()->post('cash', '');
        $shop_id     = $this->instance_id;
        $member      = new MemberService();
        $retval      = $member->addMemberBalanceWithdraw($shop_id, $withdraw_no, $this->uid, $bank_account_id, $cash);
        return $this->outMessage($title, $retval);
    }

    /**
     * 绑定时发送短信验证码或邮件验证码
     *
     * @return number[]|string[]|string|mixed
     */
    function sendBindCode()
    {
        $title             = '发送验证码';
        $params['email']   = request()->post('email', '');
        $params['mobile']  = request()->post('mobile', '');
        $params['user_id'] = $this->uid;
        $type              = request()->post("type", '');
        $vertification     = request()->post('vertification', '');
        $key               = request()->post('key', '');
        $params['shop_id'] = 0;
        if ($this->login_verify_code["value"]["pc"] == 1) {
            $res = $this->check_code($vertification, $key);
            if ($res < 0) {
                $result = [
                    'code' => -5,
                    'message' => '验证码错误'
                ];
                return $this->outMessage($title, $result);
            }
            $data = array(
                'vertification' => $vertification,
                'key' => $key
            );
        }

        if ($type == 'email') {
            $hook = runhook('Notify', 'bindEmail', $params);
        } elseif ($type == 'mobile') {
            $hook = runhook('Notify', 'bindMobile', $params);
        }
        if (!empty($hook) && !empty($hook['param'])) {

            $result = [
                'code' => 0,
                'message' => '发送成功',
                'params' => $hook['param']
            ];
        } else {

            $result = [
                'code' => -1,
                'message' => '发送失败'
            ];
        }
        return $this->outMessage($title, $result);
    }

    /**
     * 检测验证码是否正确
     */
    public function check_code($code, $key)
    {
        $key              = md5('@' . $key . '*');
        $verificationCode = Cache::get($key);
        if ($code != $verificationCode || empty($code)) {
            return -1;
        } else {
            Cache::set($key, '');
            return 1;
        }
    }

    /**
     * 更改用户头像
     */
    public function modifyFace()
    {
        $title = '更换用户头像';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $member = new MemberService();

        $user_headimg = request()->post('user_headimg', '');
        if (empty($user_headimg)) {
            return $this->outMessage($title, "", '-50', "无法获取用户头像信息");
        }
        $res = $member->ModifyUserHeadimg($this->uid, $user_headimg);
        return $this->outMessage($title, $res);
    }

    /**
     * 我的收藏
     */
    public function myCollection()
    {
        $title = '我的收藏';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $member    = new MemberService();
        $page      = request()->post('page', '1');
        $type      = request()->post('type', 0);
        $condition = array(
            "nmf.fav_type" => 'goods',
            "nmf.uid" => $this->uid
        );
        if ($type == 1) {//获取本周内收藏的商品
            $start_time            = mktime(0, 0, 0, date("m"), date("d") - date("w") + 1, date("Y"));
            $end_time              = mktime(23, 59, 59, date("m"), date("d") - date("w") + 7, date("Y"));
            $condition["fav_time"] = array("between", $start_time . "," . $end_time);
        } else if ($type == 2) {//获取本月内收藏的商品
            $start_time            = mktime(0, 0, 0, date("m"), 1, date("Y"));
            $end_time              = mktime(23, 59, 59, date("m"), date("t"), date("Y"));
            $condition["fav_time"] = array("between", $start_time . "," . $end_time);
        } else if ($type == 3) {//获取本年内收藏的商品
            $start_time            = strtotime(date("Y", time()) . "-1" . "-1");
            $end_time              = strtotime(date("Y", time()) . "-12" . "-31");
            $condition["fav_time"] = array("between", $start_time . "," . $end_time);
        }

        $goods_collection_list = $member->getMemberGoodsFavoritesList($page, PAGESIZE, $condition, "fav_time desc");
        foreach ($goods_collection_list['data'] as $k => $v) {
            $v['fav_time'] = date("Y-m-d H:i:s", $v['fav_time']);
        }
        return $this->outMessage($title, $goods_collection_list);
    }

    /**
     * 添加收藏
     */
    public function favoritesGoodsorshop()
    {
        $title = '添加收藏';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $fav_id   = request()->post('fav_id', '');
        $fav_type = request()->post('fav_type', '');
        $log_msg  = request()->post('log_msg', '');
        $member   = new MemberService();
        $result   = $member->addMemberFavouites($fav_id, $fav_type, $log_msg);
        return $this->outMessage($title, $result);
    }

    /**
     * 取消收藏
     */
    public function cancelFavorites()
    {
        $title = '取消收藏';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $fav_id   = request()->post('fav_id', '');
        $fav_type = request()->post('fav_type', '');
        $member   = new MemberService();
        $result   = $member->deleteMemberFavorites($fav_id, $fav_type);
        return $this->outMessage($title, $result);
    }

    /**
     * 我的足迹
     */
    public function newMyPath()
    {
        $title = '我的足迹';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $good             = new Goods();
        $data             = request()->post();
        $condition        = [];
        $condition["uid"] = $this->uid;
        if (!empty($data['category_id']))
            $condition['category_id'] = $data['category_id'];

        $order = 'create_time desc';
        $list  = $good->getGoodsBrowseList($data['page_index'], $data['page_size'], $condition, $order, $field = "*");

        foreach ($list['data'] as $key => $val) {
            $month        = ltrim(date('m', $val['create_time']), '0');
            $day          = ltrim(date('d', $val['create_time']), '0');
            $val['month'] = $month;
            $val['day']   = $day;
        }

        return $this->outMessage($title, $list);
    }

    /**
     * 删除我的足迹
     */
    public function delMyPath()
    {
        $title = '删除足迹';
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $type  = request()->post('type');
        $value = request()->post('value');

        if ($type == 'browse_id')
            $condition['browse_id'] = $value;

        if ($type != 'browse_id' || empty($value)) {
            return $this->outMessage($title, "", '-10', "删除失败，无法获取该足迹信息");
        }
        $good = new Goods();
        $res  = $good->deleteGoodsBrowse($condition);

        return $this->outMessage($title, $res);
    }


    # todo 何晓诗 清除手机号码
    public function checkTelForHe()
    {
        $uid             = 138;
        $res['user_tel'] = '';
        $info            = \think\Db::name('sys_user')->where(['uid' => $uid])->update($res);
    }


    # 优惠券分享界面
    public function receiveCoupon()
    {
        $title = "优惠券分享列表";
        if (empty($this->uid)) return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        $coupon      = new MemberService\MemberCoupon();
        $coupon_list = $coupon->getCouponTypeList();
        foreach ($coupon_list as $key => $item) {
            if ($item['use_type'] == 2) {
                $times                                = $item['get_after_days'] * 86400 + $item['fetch_time'];
                $coupon_list[$key]['coupon_end_time'] = date("Y-m-d", $times);
            } else {
                $coupon_list[$key]['start_time'] = date("Y-m-d", $item['start_time']);
                $coupon_list[$key]['end_time']   = date("Y-m-d", $item['end_time']);
            }

        }
        if (empty($coupon_list)) {
            return $this->outMessage($title, "", '-1', "暂无优惠券数据");
        } else {
            return $this->outMessage($title, $coupon_list);
        }

    }

    # 领取优惠券展示
    public function getReceiveCouponDetail()
    {
        $title          = "优惠券领取列表";
        $coupon_type_id = request()->post('coupon_type_id', '');
        if (empty($coupon_type_id)) return $this->outMessage($title, "", '-100', "参数异常");
        $condition = array(
            'end_time' => array(
                'EGT',
                time()
            ),
            'shop_id' => $this->instance_id,
            'coupon_type_id' => $coupon_type_id
        );

        $coupon_detail = \think\Db::name('ns_coupon_type')->where($condition)->find();

        if (empty($coupon_detail)) {
            return $this->outMessage($title, "", '-1', "暂无优惠券可领取");
        }
        $coupon_info = \think\Db::name('ns_coupon')->where(['coupon_type_id' => $coupon_type_id])->select();

        $coupon_detail['coupon_id']      = $coupon_info[0]['coupon_id'];
        $coupon_detail['coupon_code']    = $coupon_info[0]['coupon_code'];
        $coupon_detail['money']          = $coupon_info[0]['money'];
        $coupon_detail['use_type']       = $coupon_info[0]['use_type'];
        $coupon_detail['get_after_days'] = $coupon_info[0]['get_after_days'];
        $coupon_detail['pay_money_get']  = $coupon_info[0]['pay_money_get'];
        $coupon_detail['start_time']     = $coupon_info[0]['start_time'];
        $coupon_detail['end_time']       = $coupon_info[0]['end_time'];

        if ($coupon_detail['use_type'] == 2) {
            $times                            = $coupon_detail['get_after_days'] * 86400 + $coupon_detail['fetch_time'];
            $coupon_detail['coupon_end_time'] = date("Y-m-d", $times);
        } else {
            $coupon_detail['start_time'] = date("Y-m-d", $coupon_detail['start_time']);
            $coupon_detail['end_time']   = date("Y-m-d", $coupon_detail['end_time']);
        }

        return $this->outMessage($title, $coupon_detail);
    }

    # 领取优惠券
    public function getReceiveCoupon()
    {
        $title          = "优惠券领取";
        $coupon_type_id = request()->post('coupon_type_id', '');
        $origin_uid     = request()->post('uid', '');
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        if (empty($coupon_type_id) || empty($origin_uid)) {
            return $this->outMessage($title, "", '-100', "参数异常");
        }
        $coupon      = new MemberService\MemberCoupon();
        $counpon_res = $coupon->getAchieveCoupon($this->uid, $coupon_type_id, 5, $origin_uid);
        if ($counpon_res > 0) {
            return $this->outMessage($title, $counpon_res, '1', '领取成功');
        } else if ($counpon_res == '-2019') {
            return $this->outMessage($title, $counpon_res, '0', '已到个人领取上限');
        } else {
            return $this->outMessage($title, $counpon_res, '-1', '优惠券已被领取完');
        }
    }

    /**
     * 更新会员信息
     */
    public function updateMemberDetail()
    {
        $title     = "更新会员信息";
        $wx_info   = request()->post('wx_info', '');
        $avatarUrl = request()->post('avatarUrl', '');
        $nickName  = request()->post('nickName', '');


        if (empty($wx_info)) {
            return $this->outMessage($title, "", '-50', "参数为空");
        }
        $dat['wx_info']      = $wx_info;
        $dat['user_headimg'] = $avatarUrl;
        $dat['nick_name']    = $nickName;
        $dat1['member_name'] = $nickName;

        $coupon_info  = \think\Db::name('sys_user')->where(['uid' => $this->uid])->update($dat);
        $coupon_info1 = \think\Db::name('ns_member')->where(['uid' => $this->uid])->update($dat1);

        if ($coupon_info > 0) {
            return $this->outMessage($title, $coupon_info, '1', '更新成功');
        } else {
            return $this->outMessage($title, $coupon_info, '0', '更新失败');
        }
    }

}