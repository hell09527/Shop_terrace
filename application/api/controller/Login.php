<?php
/**
 * Login.php
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

use data\model\UserModel;
use data\service\Member;
use data\service\Pospal\Pospal;
use data\service\User;
use data\service\Config as WebConfig;
use think\Controller;
use data\service\Applet\AppletWechat;
use think\Log;
use data\service\promotion\PromoteRewardRule;
use think\Session;
use data\service\WebSite;
use data\service\Config;
use data\service\Member as MemberService;

/**
 * 前台用户登录
 *
 * @author Administrator
 *
 */
class Login extends BaseController
{

    public $auth_key = 'addexdfsdfewfscvsrdf!@#';

    public $user;

    public $web_site;

    public function __construct()
    {
        parent::__construct();
        $this->user     = new Member();
        $this->web_site = new WebSite();
    }

    /**
     * 获取微信小程序的配置信息(已废弃使用,代码暂时保留)
     *
     * @return \think\response\Json
     */
    function getWechatInfo()
    {
        $config        = new Config();
        $applet_config = $config->getInstanceAppletConfig($this->instance_id);
        $appid         = '';
        $secret        = '';
        if (!empty($applet_config["value"])) {
            $appid  = $applet_config["value"]['appid'];
            $secret = $applet_config["value"]['appsecret'];
        } else {
            return $this->outMessage("获取微信信息", '', -50, '后台未配置小程序');
        }
        $code = request()->post("code", "");
        $url  = "https://api.weixin.qq.com/sns/jscode2session";
        $url  = $url . "?appid=$appid";
        $url .= "&secret=$secret";
        $url .= "&js_code=$code&grant_type=authorization_code";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $result = curl_exec($ch);
        curl_close($ch);
        return $this->outMessage("获取微信信息", $result);
    }

    /**
     * 获取微信登录信息(已废弃使用,代码暂时保留)
     *
     * @return \think\response\Json
     */
    function getWechatInfos()
    {
        $title         = '获取微信登录信息';
        $config        = new Config();
        $applet_config = $config->getInstanceAppletConfig($this->instance_id);
        $appid         = '';
        $sessionKey    = request()->post('sessionKey', '');
        $encryptedData = request()->post('encryptedData', '');
        $iv            = request()->post('iv', '');
        $source_branch = request()->post('store_id', 0);
        $traffic_acquisition_source = request()->post('traffic_acquisition_source', 0);
        if (!empty($applet_config["value"])) {
            $appid = $applet_config["value"]['appid'];
        } else {
            return $this->outMessage($title, '', -50, '商家未配置小程序');
        }
        $wchat_applet = new WchatApplet($appid, $sessionKey);
        $errCode      = $wchat_applet->decryptData($encryptedData, $iv, $data);
        if ($errCode < 0) {
            $message = '登录失败';
            switch ($errCode) {
                case -41001:
                    $message = 'encodingAesKey 非法';
                    break;
                case -41002:
                    $message = 'aes 解密失败';
                    break;
                case -41003:
                    $message = 'buffer 非法';
                    break;
                case -41004:
                    $message = 'base64 解密失败';
                    break;
                default:
                    break;
            }
            return $this->outMessage($title, '', -50, $message);
        } else {
            // "{"openId":"oO2M95VnJRBApf1b_gTFBC5OPsBI","nickName":"贤仔","gender":1,"language":"zh_CN","city":"Jiading","province":"Shanghai","country":"China","avatarUrl":"https://wx.qlogo.cn/mmopen/vi_32/DYAIOgq83epTdia2icffEY7216hFq5Hh97YIpDgeGFNznXNIv11XibMZeia7MK3TqxIG2aRSk5afNIRLkQhGpaIaqw/0","unionId":"oAq2bs8RBelPMCgoJcC4r0pvUcNE","watermark":{"timestamp":1523520542,"appid":"wxba4f30503c1ea656"}}"
            // return $this->outMessage($title, $data);
            $data = json_decode($data);
            if (empty($data->openId)) {
                return $this->outMessage($title, '', '-50', "缺必填参数openid");
            }
            // 处理信息
            $applet_user   = new User();
            $applet_member = new Member();
            $res           = array();
            if (!empty($data->openId)) {
                $unionid     = $data->unionId;
                $res['data'] = $this->user->wchatUnionLogin($unionid);
                if ($res['data'] == 1) {
                    # todo @dai 新增同步pospal
                    $this->createLoginUser();
                    $user_info   = $applet_user->getUserDetailByUnionid($unionid);
                    $member_info = $applet_member->getMemberDetail($user_info['uid'], $user_info['instance_id']);
                    $encode      = $this->niuEncrypt(json_encode($user_info));
                    return $this->outMessage($title, array(
                        'member_info' => $member_info,
                        'token' => $encode
                    ));
                } else {
                    if ($res['data'] == USER_NBUND) {
                        return $this->wchatRegisters($data, $source_branch, $traffic_acquisition_source);
                    } else {
                        return $this->outMessage($title, $res, '-50', '用户被锁定或者登录失败!');
                    }
                }
            }
        }
    }

    /**
     * 获取微信登录信息
     *
     * @return \think\response\Json
     */
    function getWechatEncryptInfo()
    {
        $title         = '获取微信登录信息';
        $code          = request()->post("code", "");
        $encryptedData = request()->post('encryptedData', '');
        $iv            = request()->post('iv', '');
        $source_branch = request()->post('store_id', 0);
        $traffic_acquisition_source = request()->post('traffic_acquisition_source', '');
        $config        = new Config();
        $applet_config = $config->getInstanceAppletConfig($this->instance_id);
        if (!empty($applet_config["value"])) {
            $appid  = $applet_config["value"]['appid'];
            $secret = $applet_config["value"]['appsecret'];
        } else {
            return $this->outMessage("获取微信信息", '', -50, '后台未配置小程序');
        }
        $url = "https://api.weixin.qq.com/sns/jscode2session";
        $url = $url . "?appid=$appid";
        $url .= "&secret=$secret";
        $url .= "&js_code=$code&grant_type=authorization_code";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $result = curl_exec($ch);
        $result = json_decode($result);
        curl_close($ch);
        $wchat_applet = new WchatApplet($appid, $result->session_key);
        $errCode      = $wchat_applet->decryptData($encryptedData, $iv, $data);
        if ($errCode < 0) {
            $message = '登录失败';
            switch ($errCode) {
                case -41001:
                    $message = 'encodingAesKey 非法';
                    break;
                case -41002:
                    $message = 'aes 解密失败';
                    break;
                case -41003:
                    $message = 'buffer 非法';
                    break;
                case -41004:
                    $message = 'base64 解密失败';
                    break;
                default:
                    break;
            }
            return $this->outMessage($title, '', -50, $message);
        } else {
            $data = json_decode($data);
            if (empty($data->openId)) {
                return $this->outMessage($title, '', '-50', "缺必须参数openId");
            }

            if (empty($data->unionId)) {
                return $this->outMessage($title, '', '-50', "缺必须参数unionId");
            }
            // 处理信息
            $applet_user = new User();
            $res         = array();
            if (!empty($data->openId) && !empty($data->unionId)) {
                $res['data'] = $this->user->wchatUnionLogin($data->unionId);
                if ($res['data'] == 1) {
                    # todo @dai 新增同步pospal
                    $this->createLoginUser();
                    $user_info = $applet_user->getUserDetailByUnionid($data->unionId);
                    $encode    = $this->niuEncrypt(json_encode($user_info));
                    return $this->outMessage($title, array(
                        'openid' => $data->openId,
                        'token' => $encode
                    ));
                } else {
                    if ($res['data'] == USER_NBUND) {
                        return $this->wchatRegisters($data, $source_branch, $traffic_acquisition_source);
                    } else {
                        return $this->outMessage($title, $res, '-50', '用户被锁定或者登录失败!');
                    }
                }
            }
        }
    }

    //获取微信手机号
    function getWechatMobile()
    {
        $title         = '获取微信电话';
        $code          = request()->post("code", "");
        $encryptedData = request()->post('mobileEncryptedData', '');
        $iv            = request()->post('mobileIv', '');
        $config        = new Config();
        $applet_config = $config->getInstanceAppletConfig($this->instance_id);
        if (!empty($applet_config["value"])) {
            $appid  = $applet_config["value"]['appid'];
            $secret = $applet_config["value"]['appsecret'];
        } else {
            return $this->outMessage("获取微信电话", '', -50, '后台未配置小程序');
        }
        $url = "https://api.weixin.qq.com/sns/jscode2session";
        $url = $url . "?appid=$appid";
        $url .= "&secret=$secret";
        $url .= "&js_code=$code&grant_type=authorization_code";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $result = curl_exec($ch);
        $result = json_decode($result);
        curl_close($ch);

        $wchat_applet = new WchatApplet($appid, $result->session_key);
        $errCode      = $wchat_applet->decryptData($encryptedData, $iv, $data);
        if ($errCode < 0) {
            $message = '登录失败';
            switch ($errCode) {
                case -41001:
                    $message = 'encodingAesKey 非法';
                    break;
                case -41002:
                    $message = 'aes 解密失败';
                    break;
                case -41003:
                    $message = 'buffer 非法';
                    break;
                case -41004:
                    $message = 'base64 解密失败';
                    break;
                default:
                    break;
            }
            return $this->outMessage($title, '', -50, $message);
        } else {
            $data = json_decode($data);
            if (empty($this->uid)) {
                return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
            }
            if (empty($data->purePhoneNumber)) {
                return $this->outMessage($title, "", '-50', "无法获取手机号码");
            }
            $member = new Member();
            $exist  = $member->memberIsMobile($data->purePhoneNumber);
            if ($exist) {
                return $this->outMessage($title, "", '-50', "手机号已存在");
            }
            $member = new MemberService();
            $retval = $member->modifyMobile($this->uid, $data->purePhoneNumber);
            # todo @dai 新增同步pospal
            $this->createLoginUser();
            return $this->outMessage($title, ['user_tel' => $data->purePhoneNumber], 0, "手机号获取成功");
        }
    }

    /**
     * 微信注册（二次开发）
     */
    public function wchatRegisters($info, $source_branch, $traffic_acquisition_source)
    {
        $title = "会员注册";
        // 处理信息
        $member        = new Member();
        $applet_user   = new User();
        $weapp_user    = new AppletWechat();
        $applet_member = new Member();


        $openid                = $info->openId;
        $wx_unionid            = $info->unionId;
        $wx_info['opneid']     = $info->openId;
        $wx_info['sex']        = $info->gender;
        $wx_info['headimgurl'] = $info->avatarUrl;
        $wx_info['nickname']   = $info->nickName;
        $wx_info               = json_encode($wx_info);

        $retval = $weapp_user->wchatAppUnionLogin($wx_unionid);
        if ($retval == USER_NBUND) {
            // 注册
            $result = $applet_member->registerMember('', '123456', '', '', '', '', $openid, $wx_info, $wx_unionid, $source_branch, $traffic_acquisition_source);
            if ($result > 0) {
                $user_info = $applet_user->getUserInfoByUid($result);
                # todo @dai 新增同步pospal
                $this->createLoginUser();

//                $member_info = $applet_member->getMemberDetail($user_info['instance_id']);
                $token  = array(
                    'uid' => $user_info['uid'],
                    'request_time' => time()
                );
                $encode = $this->niuEncrypt(json_encode($token));
                return $this->outMessage($title, array(
                    'openid' => $openid,
                    'token' => $encode
                ));
            } else {
                return $this->outMessage($title, '', '-50', "注册失败");
            }
        } elseif ($retval == USER_LOCK) {
            return $this->outMessage($title, '', '-50', "用户被锁定");
        }
    }

    /**
     * 微信登录
     */
    public function wechatLogin()
    {
        $title  = "会员登录";
        $openid = request()->post('openid', '');
        $info   = request()->post('wx_info', '');

        if (empty($openid)) {
            return $this->outMessage($title, '', '-50', "缺少必填参数openid");
        }

        // 处理信息
        $applet_user   = new User();
        $applet_member = new Member();
        $res           = array();
        $res['data']   = $applet_member->wchatLogin($openid);
        // 返回信息
        if ($res['data'] == 1) {
            $user_info   = $applet_user->getUserDetailByOpentid($openid);
            $member_info = $applet_member->getMemberDetail($user_info['uid'], $user_info['instance_id']);
            $encode      = $this->niuEncrypt(json_encode($user_info));
            return $this->outMessage($title, array(
                'member_info' => $member_info,
                'token' => $encode
            ));
        } else {
            if ($res['data'] == USER_NBUND) {
                return $this->wchatRegister($openid, $info);
            } else {
                return $this->outMessage($title, $res, '-50', '用户被锁定或者登录失败!');
            }
        }
    }

    /**
     * 微信注册
     */
    public function wchatRegister($openid, $info)
    {
        $title = "会员注册";
        // 处理信息
        $member        = new Member();
        $applet_user   = new User();
        $weapp_user    = new AppletWechat();
        $applet_member = new Member();

        $wx_unionid            = '';
        $wx_info               = json_decode($info, true);
        $wx_info['opneid']     = $openid;
        $wx_info['sex']        = $wx_info['gender'];
        $wx_info['headimgurl'] = $wx_info['avatarUrl'];
        $wx_info['nickname']   = $wx_info['nickName'];
        $wx_info               = json_encode($wx_info);

        $retval = $weapp_user->wchatAppLogin($openid);
        if ($retval == USER_NBUND) {
            // 注册
            $result = $applet_member->registerMember('', '123456', '', '', '', '', $openid, $wx_info, $wx_unionid);
            if ($result > 0) {
                $user_info   = $applet_user->getUserInfoByUid($result);
                $member_info = $applet_member->getMemberDetail($user_info['instance_id']);

                # todo @dai 新增同步pospal
                $this->createLoginUser();

                $token       = array(
                    'uid' => $user_info['uid'],
                    'request_time' => time()
                );
                $encode      = $this->niuEncrypt(json_encode($token));
                return $this->outMessage($title, array(
                    'member_info' => $member_info,
                    'token' => $encode
                ));
            } else {
                return $this->outMessage($title, '', '-50', "注册失败");
            }
        } elseif ($retval == USER_LOCK) {
            return $this->outMessage($title, '', '-50', "用户被锁定");
        }
    }

    /**
     * 注册用户
     */
    public function addUser()
    {
        $title     = '注册用户';
        $user_name = request()->post('username', '');
        $password  = request()->post('password', '');
        $email     = request()->post('email', '');
        $mobile    = request()->post('mobile', '');
        $is_system = 0;
        $is_member = 1;
        $qq_openid = request()->post('qq_openid', '');
        $wx_openid = request()->post('wx_openid', '');
        $wx_info   = request()->post('wx_info', '');
        if (empty($user_name)) {
            return $this->outMessage($title, '', -50, '用户名不可为空');
        } else {
            $result = $this->user->registerMember($user_name, $password, $email, $mobile, $qq_openid, $qq_info, $wx_openid, $wx_info);
            if ($result > 0) {
                # todo @dai 新增同步pospal
                $this->createLoginUser();

                // 注册成功送优惠券
                $Config         = new WebConfig();
                $integralConfig = $Config->getIntegralConfig($this->instance_id);
                if ($integralConfig['register_coupon'] == 1) {
                    $rewardRule = new PromoteRewardRule();
                    $res        = $rewardRule->getRewardRuleDetail($this->instance_id);
                    if ($res['reg_coupon'] != 0) {
                        $member = new Member();
                        $retval = $member->memberGetCoupon($this->uid, $res['reg_coupon'], 2);
                    }
                }
                $this->user->qqLogin($qq_openid);
            }
        }

        return $this->outMessage($title, $result);
    }

    /**
     * 注册配置信息
     */
    public function registerInfo()
    {
        $title = '注册配置信息';

        $config     = new WebConfig();
        $instanceid = 0;
        // 判断是否开启邮箱注册
        $reg_config_info = $config->getRegisterAndVisit($instanceid);
        $reg_config      = json_decode($reg_config_info["value"], true);
        if ($reg_config["is_register"] != 1) {
            return $this->outMessage($title, '', -50, '抱歉，商城暂未开放注册');
        }
        if (strpos($reg_config['register_info'], "plain") === false && strpos($reg_config['register_info'], "mobile") === false) {
            return $this->outMessage($title, '', -50, '抱歉，商城暂未开放注册');
        }
        // 登录配置
        $web_config  = new WebConfig();
        $loginConfig = $web_config->getLoginConfig();

        $loginCount = 0;
        if ($loginConfig['wchat_login_config']['is_use'] == 1) {
            $loginCount++;
        }
        if ($loginConfig['qq_login_config']['is_use'] == 1) {
            $loginCount++;
        }

        $code_config = $web_config->getLoginVerifyCodeConfig($this->instance_id);

        $data = array(
            'reg_config' => $reg_config,
            'code_config' => $code_config,
            'loginCount' => $loginCount,
            'loginConfig' => $loginConfig
        );
        return $this->outMessage($title, $data);
    }

    /**
     * 注册账户
     */
    public function register()
    {
        $title     = '注册账户';
        $member    = new Member();
        $user_name = request()->post('username', '');
        $password  = request()->post('password', '');
        $email     = request()->post('email', '');
        $mobile    = request()->post('mobile', '');
        $retval_id = $member->registerMember($user_name, $password, $email, $mobile, '', '', '', '', '');
        if ($retval_id > 0) {
            // 注册成功送优惠券
            $Config         = new WebConfig();
            $integralConfig = $Config->getIntegralConfig($this->instance_id);
            if ($integralConfig['register_coupon'] == 1) {
                $rewardRule = new PromoteRewardRule();
                $res        = $rewardRule->getRewardRuleDetail($this->instance_id);
                if ($res['reg_coupon'] != 0) {
                    $member = new Member();
                    $retval = $member->memberGetCoupon($retval_id, $res['reg_coupon'], 2);
                }
            }
            $applet_user   = new User();
            $applet_member = new Member();
            $user_info     = $applet_user->getUserInfoByUid($retval_id);
            $member_info   = $applet_member->getMemberDetail($user_info['instance_id']);

            # todo @dai 新增同步pospal
            $this->createLoginUser();

            $token         = array(
                'uid' => $user_info['uid'],
                'request_time' => time()
            );
            $encode        = $this->niuEncrypt(json_encode($token));
            $data          = array(
                'member_info' => $member_info,
                'token' => $encode
            );
            return $this->outMessage($title, $data);
        } else {
            $msg      = "注册失败";
            $res_ajax = AjaxReturn($retval_id);
            if (!empty($res_ajax)) {
                if (!empty($res_ajax['message'])) {
                    $msg = $res_ajax['message'];
                }
            }
            return $this->outMessage($title, '', -50, $msg);
        }
    }

    /**
     * 检测手机号是否已经注册
     *
     * @return Ambigous <number, \data\model\unknown>
     */
    public function checkMobileIsHas()
    {
        $title  = '检测手机号是否注册';
        $mobile = request()->post('mobile', '');
        if (!empty($mobile)) {
            $count = $this->user->checkMobileIsHas($mobile);
        } else {
            $count = 0;
        }
        return $this->outMessage($title, $count);
    }

    /**
     * 登录信息
     */
    public function index()
    {
        $title = '登录配置信息';

        // 没有登录首先要获取上一页
        $config     = new WebConfig();
        $instanceid = 0;
        // 登录配置
        $web_config  = new WebConfig();
        $loginConfig = $web_config->getLoginConfig();

        $loginCount = 0;
        if ($loginConfig['wchat_login_config']['is_use'] == 1) {
            $loginCount++;
        }
        if ($loginConfig['qq_login_config']['is_use'] == 1) {
            $loginCount++;
        }
        $data['loginCount']  = $loginCount;
        $data['loginConfig'] = $loginConfig;
        return $this->outMessage($title, $data);
    }

    /**
     * 登录
     *
     * @return multitype:unknown
     */
    public function Login()
    {
        $title     = '会员登录';
        $user_name = request()->post('username', '');
        $password  = request()->post('password', '');
        $mobile    = request()->post('mobile', '');
        if (!empty($user_name)) {
            $retval = $this->user->login($user_name, $password);
        } else {
            $retval = $this->user->login($mobile, $password);
        }
        if ($retval > 0) {
            $model         = $this->getRequestModel();
            $uid           = Session::get($model . 'uid');
            $applet_user   = new User();
            $applet_member = new Member();
            $user_info     = $applet_user->getUserInfoByUid($uid);
            $member_info   = $applet_member->getMemberDetail($user_info['instance_id']);
            $token         = array(
                'uid' => $user_info['uid'],
                'request_time' => time()
            );
            $encode        = $this->niuEncrypt(json_encode($token));
            $data          = array(
                'member_info' => $member_info,
                'token' => $encode
            );
            return $this->outMessage($title, $data, 1);
        } else {
            return $this->outMessage($title, '', $retval);
        }
    }

    /**
     * 注册手机号验证码验证
     * 任鹏强
     * 2017年6月17日16:26:46
     *
     * @return multitype:number string
     */
    public function register_check_code()
    {
        $title      = '注册手机号验证码验证';
        $send_param = request()->post('send_param', '');
        $param      = request()->post('mobileVerificationCode', '');

        if ($send_param == $param && $send_param != '') {
            $retval = [
                'code' => 0,
                'message' => "验证码一致"
            ];
        } else {
            $retval = [
                'code' => 1,
                'message' => "验证码不一致"
            ];
        }
        return $this->outMessage($title, $retval);
    }

    /**
     * 系统加密方法
     *
     * @param string $data
     *            要加密的字符串
     * @param string $key
     *            加密密钥
     * @param int $expire
     *            过期时间 单位 秒
     * @return string
     * @author 麦当苗儿 <zuojiazi@vip.qq.com>
     */
    public function niuEncrypt($data, $key = '', $expire = 0)
    {
        $key  = md5(empty($key) ? $this->auth_key : $key);
        $data = base64_encode($data);
        $x    = 0;
        $len  = strlen($data);
        $l    = strlen($key);
        $char = '';

        for ($i = 0; $i < $len; $i++) {
            if ($x == $l)
                $x = 0;
            $char .= substr($key, $x, 1);
            $x++;
        }

        $str = sprintf('%010d', $expire ? $expire + time() : 0);

        for ($i = 0; $i < $len; $i++) {
            $str .= chr(ord(substr($data, $i, 1)) + (ord(substr($char, $i, 1))) % 256);
        }
        return str_replace(array(
            '+',
            '/',
            '='
        ), array(
            '-',
            '_',
            ''
        ), base64_encode($str));
    }

    /**
     * 返回信息
     *
     * @param unknown $res
     * @return \think\response\Json
     */
    public function outMessage($title, $data, $code = 0, $message = "success")
    {
        $api_result         = array();
        $api_result["code"] = $code;
        if ($data === "") {
            $data = null;
        }
        $api_result['data']    = $data;
        $api_result['message'] = $message;
        $api_result['title']   = $title;

        if ($api_result) {
            return json_encode($api_result);
        } else {
            abort(404);
        }
    }

    /**
     * 判断手机号是否存在
     */
    public function mobile()
    {
        // 获取数据库中的用户列表
        $title       = '判断手机号是否存在';
        $user_mobile = request()->post('mobile', '');
        $member      = new Member();
        $exist       = $member->memberIsMobile($user_mobile);
        return $this->outMessage($title, $exist);
    }

    /**
     * 判断邮箱是否存在
     */
    public function email()
    {
        // 获取数据库中的用户列表
        $title      = '判断邮箱是否存在';
        $user_email = request()->post('email', '');
        $member     = new Member();
        $exist      = $member->memberIsEmail($user_email);
        return $this->outMessage($title, $exist);
    }


    # 同步新用户到pospal
    private function createLoginUser(){
        if( $_SERVER['HTTP_HOST'] !== 'www.bonnieclyde.cn' ) return true;
        $title  = '同步用户到pospal';
        $pospal = new Pospal();
        $user   = new UserModel();
        $uid    = $this->uid;
        if(!$uid) return $this->outMessage($title, '', -50, '抱歉,该用户不存在');
        $condition['uid'] = $uid;
        $member_info      = $user->getInfo($condition, 'user_tel,sex,real_name,nick_name');
        # 只同步有手机号码的用户
        if($member_info['user_tel']){
            $number      = str_pad($uid, 6, "0", STR_PAD_LEFT);
            $userInfo = [
                'categoryName' => '会员卡',
                'number'       => 'BC'.$number,
                'name'         => $member_info['nick_name'],
                'phone'        => $member_info['user_tel'],
            ];
            if(!$pospal->getRecordInfo($member_info['user_tel'])) $pospal->createLoginUser($userInfo,$uid,$member_info['user_tel']);
        }
    }


}