<?php
/**
 * Pay.php
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

use data\service\UnifyPay;
use data\service\Member;
use data\service\Order;
use data\service\Config;

/**
 * 支付控制器
 *
 * @author Administrator
 *
 */
class Pay extends BaseController
{

    /**
     * 获取支付相关信息
     */
    public function getPayValue()
    {
        $title        = "获取支付信息";
        $out_trade_no = request()->post('out_trade_no', '');
        if (empty($out_trade_no)) {
            return $this->outMessage($title, "", -50, "缺少必填参数out_trade_no");
        }
        $pay       = new UnifyPay();
        $member    = new Member();
        $pay_value = $pay->getPayInfo($out_trade_no);

        if ($pay_value['pay_status'] == 1) {
            // 订单已经支付
            return $this->outMessage($title, '', -50, '订单已经支付或者订单价格为0.00，无需再次支付!');
        }
        if ($pay_value['type'] == 1) {
            // 订单
            $order_status = $this->getOrderStatusByOutTradeNo($out_trade_no);
            // 订单关闭状态下是不能继续支付的
            if ($order_status == 5) {
                return $this->outMessage($title, '', -50, '订单已关闭');
            }
        }
        $member_info = $member->getMemberDetail();
        $data        = array(
            'pay_value' => $pay_value,
            'nick_name' => $member_info['member_name']
        );
        return $this->outMessage($title, $data);
    }


    /**
     * 根据外部交易号查询订单状态，订单关闭状态下是不能继续支付的
     * 创建时间：2017年10月13日 14:35:59 王永杰
     *
     * @param unknown $out_trade_no
     * @return number
     */
    public function getOrderStatusByOutTradeNo($out_trade_no)
    {
        $order        = new Order();
        $order_status = $order->getOrderStatusByOutTradeNo($out_trade_no);
        if (!empty($order_status)) {
            return $order_status['order_status'];
        }
        return 0;
    }

    /**
     * 小程序支付
     */
    public function appletWechatPay()
    {
        $title        = "订单支付!";
        $out_trade_no = request()->post('out_trade_no', '');
        $openid       = request()->post('openid', '');
        if (empty($out_trade_no)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数out_trade_no");
        }
        $red_url = str_replace("/index.php", "", __URL__);
        $red_url = str_replace("/api.php", "", __URL__);
        $red_url = str_replace("index.php", "", $red_url);
        $red_url = $red_url . "/weixinpay.php";
        $pay     = new UnifyPay();
        $config  = new Config();

        $res          = $pay->wchatPay($out_trade_no, 'APPLET', $red_url, $openid);
        $wchat_config = $config->getWpayConfig($this->instance_id);

        if ($res["result_code"] == "SUCCESS" && $res["return_code"] == "SUCCESS") {
            $appid            = $res["appid"];
            $nonceStr         = $res["nonce_str"];
            $package          = $res["prepay_id"];
            $signType         = "MD5";
            $key              = $wchat_config['value']['mch_key'];
            $timeStamp        = time();
            $sign_string      = "appId=$appid&nonceStr=$nonceStr&package=prepay_id=$package&signType=$signType&timeStamp=$timeStamp&key=$key";
            $paySign          = strtoupper(md5($sign_string));
            $res["timestamp"] = $timeStamp;
            $res["PaySign"]   = $paySign;
        }
        return $this->outMessage($title, $res);
    }

    /**
     * 根据流水号查询订单编号，
     * 创建时间：2017年10月9日 18:36:54
     *
     * @param unknown $out_trade_no
     * @return string
     */
    public function getOrderNoByOutTradeNo()
    {
        $title        = '查询订单号';
        $out_trade_no = request()->post('out_trade_no', '');
        if (empty($out_trade_no)) {
            return $this->outMessage($title, "", '-50', "缺少必填参数out_trade_no");
        }
        $order     = new Order();
        $pay       = new UnifyPay();
        $pay_value = $pay->getPayInfo($out_trade_no);
        $order_no  = "";
        if ($pay_value['type'] == 1) {
            // 订单
            $list = $order->getOrderNoByOutTradeNo($out_trade_no);
            if (!empty($list)) {
                foreach ($list as $v) {
                    $order_no .= $v['order_no'];
                }
            }
        } elseif ($pay_value['type'] == 4) {
            // 余额充值不进行处理
        }
        return $this->outMessage($title, array('order_no' => $order_no));
    }


    #微信提现
    public function pullMoney()
    {
        # todo test
        $desc  = "极选师分润提现";
        $money = 1;

//        $openid;
        $appid  = "wxd145d8a6e951dd1b";                         //商户账号appid
        $secret = "9992738883d7f95146c7459146f81cf8";           //api密码
        $mch_id = "1414337802";                                 //商户号
        $mch_no = "";

        $openid = "123456789";//授权用户openid

        $arr                     = array();
        $arr['mch_appid']        = $appid;
        $arr['mchid']            = $mch_id;
        $arr['nonce_str']        = md5(time());          //随机字符串，不长于32位


        $arr['partner_trade_no'] = '1298016501' . date("Ymd") . rand(10000, 90000) . rand(10000, 90000);//商户订单号
        $arr['openid']           = $openid;
        $arr['check_name']       = 'NO_CHECK';           //是否验证用户真实姓名，这里不验证
        $arr['amount']           = $money;               //付款金额，单位为分

        p($arr['partner_trade_no']);exit;

        $arr['desc']             = $desc;               //描述信息
        $arr['spbill_create_ip'] = '121.40.195.141';    //获取服务器的ip

        //封装的关于签名的算法
//        $notify                           = new Notify_pub();


        $notify->weixin_app_config        = array();
        $notify->weixin_app_config['KEY'] = $mch_no;

        $arr['sign'] = $this->getSign($arr);//签名

        $var         = $this->arrayToXml($arr);
        $xml         = $this->curl_post_ssl('https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers', $var, 30, array(), 1);
        $rdata       = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $return_code = (string)$rdata->return_code;
        $result_code = (string)$rdata->result_code;
        $return_code = trim(strtoupper($return_code));
        $result_code = trim(strtoupper($result_code));

        if ($return_code == 'SUCCESS' && $result_code == 'SUCCESS') {
            $isrr = array(
                'con'   =>'ok',
                'error' => 0,
            );
        } else {
            $returnmsg = (string)$rdata->return_msg;
            $isrr = array(
                'error'  => 1,
                'errmsg' => $returnmsg,
            );

        }
        return json_encode($isrr);

    }


    function curl_post_ssl($url, $vars, $second = 30, $aHeader = array())
    {
        $isdir = "/cert/";//证书位置

        $ch = curl_init();//初始化curl

        curl_setopt($ch, CURLOPT_TIMEOUT, $second); //设置执行最长秒数
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_URL, $url);        //抓取指定网页
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);// 终止从服务端进行验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);//
        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');//证书类型
        curl_setopt($ch, CURLOPT_SSLCERT, $isdir . 'apiclient_cert.pem');//证书位置
        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');//CURLOPT_SSLKEY中规定的私钥的加密类型
        curl_setopt($ch, CURLOPT_SSLKEY, $isdir . 'apiclient_key.pem');//证书位置
        curl_setopt($ch, CURLOPT_CAINFO, 'PEM');
        curl_setopt($ch, CURLOPT_CAINFO, $isdir . 'rootca.pem');
        if (count($aHeader) >= 1) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);//设置头部
        }
        curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);//全部数据使用HTTP协议中的"POST"操作来发送

        $data = curl_exec($ch);//执行回话
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            echo "call faild, errorCode:$error\n";
            curl_close($ch);
            return false;
        }
    }

    function arrayToXml($data){
        $str='<xml>';
        foreach($data as $k=>$v) {
            $str.='<'.$k.'>'.$v.'</'.$k.'>';
        }
        $str.='</xml>';
        return $str;
    }

    function xmltoarray($xml) {
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $val       = json_decode(json_encode($xmlstring), true);
        return $val;
    }


    function getSign($data,$secrect){
        //将要发送的数据整理为$data
        ksort($data);//排序
        //使用URL键值对的格式（即key1=value1&key2=value2…）拼接成字符串
        $str='';
        foreach($data as $k=>$v) {
            $str.=$k.'='.$v.'&';
        }
        //拼接API密钥
        $str.='key='.$secrect;
        $data['sign']=md5($str);//加密
        return $data;
    }
}