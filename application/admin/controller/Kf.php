<?php

namespace app\admin\controller;

\think\Loader::addNamespace('data', 'data/');
use data\model\NsOrderGoodsModel;
use data\model\NsOrderModel;
use think\Controller;
use think\Session;

/**
 * Class Kf
 * @package app\admin\controller
 * 客服后台 kf5系统模块
 */
class Kf extends Controller
{
    public $style;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return \think\response\View
     * 获取用户信息
     */
    public function kfUserInfo()
    {
        #限制来源url
        if ($_SERVER["HTTP_REFERER"] !== 'https://shopal.kf5.com/agent/') {
            $this->error('404');
        }
        $current_user_phone = request()->get("current_user_phone", "");     #客服手机号
        $request_ip         = $_SERVER['REMOTE_ADDR'];                      #登录ip

        $user_id = request()->get("user_id", "");
        if (empty($user_id)) {
            return;
        }
        $openid      = $this->requestKfApi($user_id);
        if($openid == 1){
            return view($this->style . "kf/kfEmptyUser");
        }
        $user_info   = \think\Db::name('sys_user')->where(['wx_openid' => $openid])->find();
        $member_info = \think\Db::name('ns_member')->where(['uid' => $user_info['uid']])->find();

        if ($member_info['distributor_type'] != 0) {
            $user_info['real_name'] = $user_info['real_name'] . '(极选师)';
        }
        $user_info['reg_time']           = date('Y-m-d H:i:s', $user_info['reg_time']);
        $user_info['current_login_time'] = date('Y-m-d H:i:s', $user_info['current_login_time']);

        $wx_info = stripslashes(html_entity_decode($user_info['wx_info'])); //$info是传递过来的json字符串
        $wx_info = json_decode($wx_info, TRUE);

        $user_info['avatarUrl'] = empty($wx_info['avatarUrl']) ? $wx_info['headimgurl'] : $wx_info['avatarUrl'];
        $this->assign("user_info", $user_info);
        return view($this->style . "kf/kfUserInfo");
    }

    /**
     * @return mixed|\think\response\View
     * 获取订单信息
     */
    public function kfOrderInfo()
    {
        $user_id            = request()->get("user_id", "");                #kf用户id
        $current_user_phone = request()->get("current_user_phone", "");     #客服手机号
        $http_referer       = $_SERVER["HTTP_REFERER"];                     #来源url
        $request_ip         = $_SERVER['REMOTE_ADDR'];                      #登录ip

        if (!empty(request()->post("http_referer", ""))) {
            $http_referer = request()->post("http_referer", "");
        } else {
            $http_referer = $http_referer;
        }
        if ($http_referer != 'https://shopal.kf5.com/agent/') {
            $this->error('404');
        }

        if (request()->isAjax()) {
            $order_no           = request()->post("order_no", "");
            $user_id1           = request()->post("user_id", "");
            $request_ip         = request()->post("request_ip", "");
            $current_user_phone = request()->post("current_user_phone", "");
            $res                = $this->registerIpLimit($request_ip, $current_user_phone);

            if ($res != 1) {
                $order_lists = 1;
            } else {
                $order_lists = $this->getKfOrderInfo($user_id1, $order_no);
            }
            return $order_lists;
        }
        $this->assign("user_id", $user_id);
        $this->assign("http_referer", $http_referer);
        $this->assign("current_user_phone", $current_user_phone);
        $this->assign("request_ip", $request_ip);
        return view($this->style . "kf/kfOrderInfo");
    }

    /**
     * @param $user_id
     * @param $order_no
     * @return mixed
     * 融合订单信息
     */
    public function getKfOrderInfo($user_id, $order_no)
    {
        $openid      = $this->requestKfApi($user_id);
        $user_info   = \think\Db::name('sys_user')->where(['wx_openid' => $openid])->find();
        $order       = new NsOrderModel();
        $order_goods = new NsOrderGoodsModel();

        $condition['buyer_id']     = $user_info['uid'];
        $condition['order_status'] = array(
            'neq',
            5
        ); // 5 关闭订单
        if ($order_no) {
            $condition['order_no'] = $order_no;
        }
        $order_lists = $order->getUserOrderInfo($condition, 'create_time desc');
        foreach ($order_lists as $key => $val) {
            if ($val['order_status'] == 0) {
                $order_lists[$key]['order_status'] = '待付款';
            } elseif ($val['order_status'] == 1) {
                $order_lists[$key]['order_status'] = '待发货';
            } elseif ($val['order_status'] == 2) {
                $order_lists[$key]['order_status'] = '已发货';
            } elseif ($val['order_status'] == 3) {
                $order_lists[$key]['order_status'] = '已收货';
            } elseif ($val['order_status'] == 4) {
                $order_lists[$key]['order_status'] = '已完成';
            } elseif ($val['order_status'] == 5) {
                $order_lists[$key]['order_status'] = '已关闭';
            } elseif ($val['order_status'] == '-1') {
                $order_lists[$key]['order_status'] = '退款中';
            } elseif ($val['order_status'] == '-2') {
                $order_lists[$key]['order_status'] = '已退款';
            }
            $condition1['order_id']          = $val['order_id'];
            $order_goods_lists               = $order_goods->getUserOrderInfo($condition1);
            $order_lists[$key]['goods_info'] = $order_goods_lists;
        }
        return $order_lists;
    }

    /**
     * @param $user_id
     * @return mixed
     * 获取openid
     */
    public function requestKfApi($user_id)
    {
        require(VENDOR_PATH . 'APIV2_SDK/Client.php');
        $yourDomain = 'shopal.kf5.com';
        $email      = 'jarod@ushopal.com';
        $token      = '22d03c5a73d8a1c3dbf68a1feb08da';

        $test = new \Client($yourDomain, $email);
        $test->setAuth('token', $token);

        #查询用户信息
        $data   = $test->users()->find($user_id);
        $openid = json_decode(json_encode($data), true)['user']['wechat_miniprogram_openid'];

        $user_info = \think\Db::name('sys_user')->where(['wx_openid' => $openid])->find();

        if(empty($user_info)){
            return 1;
        }

        $wx_info = stripslashes(html_entity_decode($user_info['wx_info'])); //$info是传递过来的json字符串
        $wx_info = json_decode($wx_info, TRUE);

        if (empty(json_decode(json_encode($data), true)['user']['photo'])) {
            #修改用户信息
            $info = [
                'name'  => empty($user_info['nick_name']) ? $wx_info['nickname'] : $user_info['nick_name'],
                'photo' => empty($wx_info['avatarUrl']) ? $wx_info['headimgurl'] : $wx_info['avatarUrl'],
            ];
            $test->users()->update($user_id, $info);
        }

        return $openid;
    }


    /**
     * @return \multitype
     * @throws \think\Exception
     * 添加备注
     */
    public function sendMsg()
    {
        $seller_memo = request()->post("seller_memo", "");
        $order_id    = request()->post("order_id", "");
        if (empty($order_id)) {
            $this->error('缺少必需参数');
        }
        $data['seller_memo'] = $seller_memo;
        $res                 = \think\Db::name('ns_order')->where(['order_id' => $order_id])->update($data);
        return AjaxReturn($res);
    }


    /**
     * @return int|\multitype
     * 验证手机号
     */
    public function checkPhoneCode()
    {
        if (request()->isAjax()) {
            $sms_captcha      = request()->post('sms_captcha', '');
            $mobile           = request()->post('mobile', '');
            $ip               = request()->post('ip', '');
            $sms_captcha_code = Session::get('mobileKfCode');

            if (($sms_captcha == $sms_captcha_code && !empty($sms_captcha_code))) {
                $request_info = \think\Db::name('bc_kf_request')->where(['current_user_phone' => $mobile])->find();
                $data         = [
                    'request_ip'         => $ip,
                    'current_user_phone' => $mobile,
                    'last_login_time'    => time()
                ];
                if (!empty($request_info)) {
                    $result = \think\Db::name('bc_kf_request')->where(['id' => $request_info['id']])->update($data);
                } else {
                    $result = \think\Db::name('bc_kf_request')->insert($data);
                }
            } else {
                $result = -10;
            }
            return AjaxReturn($result);
        }
    }

    private function registerIpLimit($ip, $phone)
    {
        $request_info = \think\Db::name('bc_kf_request')->where(['current_user_phone' => $phone])->find();

        #首次登录 短信验证
        if (empty($request_info)) {
            return 2;
        }

        #ip变更 短信验证
        if ($request_info['request_ip'] != $ip) {
            return 3;
        }

        #一周时间 短信验证
        if (time() - $request_info['last_login_time'] > 86400 * 7) {
            return 4;
        }

        return 1;
    }


    public function sendMobileCode()
    {
        $params['mobile']  = request()->post('mobile', '');
        $params['shop_id'] = 0;
        $result            = runhook('Notify', 'bindMobile', $params);
        Session::set('mobileKfCode', $result['param']);
        Session::set('sendMobile', $params['mobile']);

        if (empty($result)) {
            return $result = [
                'code' => -1,
                'message' => "发送失败"
            ];
        } else {
            if ($result["code"] != 0) {
                return $result = [
                    'code' => $result["code"],
                    'message' => $result["message"]
                ];
            } else
                if ($result["code"] == 0) {
                    return $result = [
                        'code' => 0,
                        'message' => "发送成功"
                    ];
                }
        }
    }


}
