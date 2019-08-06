<?php

namespace app\api\controller;

use data\extend\email\PHPMailer;
use data\model\NsGoodsViewModel;
use data\model\NsMemberModel;
use data\service\Distributor as DistributorService;
use data\service\Member;
use data\service\Order;
use data\service\Order as OrderService;
use Qiniu\Auth;
use Qiniu\Cdn\CdnManager;
use Qiniu\Http\Request;
use Qiniu\Storage\UploadManager;
use think\Db;
use data\service\DistributorWxcode;
use data\service\GoodsBrand;
use data\model\BcDistributorModel;

/**
 * cms内容管理系统
 */
class Distributor extends BaseController
{
    //极选师推广码(首页)
    public function getWxCode()
    {
        $title = "极选师推广码";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取登录信息");
        }
        #$url         = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . 'wxba4f30503c1ea656' . "&secret=" . '369964cf3f3da0a50cd7a9d27ca5577a'; #测试
        $url         = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . 'wxd145d8a6e951dd1b' . "&secret=" . '9e22a3ac6f4c0ccae03a2356e710d68f'; #正式
        $res         = $this->send_post($url, '');
        $AccessToken = json_decode($res, true);
        $AccessToken = $AccessToken['access_token'];
        $url         = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=" . $AccessToken;
        $post_data   =
            array(
                'scene' => $this->uid . '&0',
                'page' => 'pages/index/index',
                'width' => 430,
            );
        $post_data   = json_encode($post_data);
        $data        = $this->send_post($url, $post_data);
        $result      = $this->data_uri($data, 'image/png');
        return $this->outMessage($title, $result);
    }

    //极选师的单品小程序码
    public function getDistributorGoodsWxCode()
    {
        $title = "极选师的单品小程序码";
        $distributor_type = request()->post('distributor_type', '');
        if($distributor_type > 0){
            if (empty($this->uid)) {
                return $this->outMessage($title, "", '-50', "无法获取登录信息");
            }else{
                $uid = $this->uid;
            }
        }else{
            $uid = 0;
        }
        $goods_id = request()->post('goods_id', '');
        #$url         = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . 'wxba4f30503c1ea656' . "&secret=" . '369964cf3f3da0a50cd7a9d27ca5577a'; #测试
        $url         = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . 'wxd145d8a6e951dd1b' . "&secret=" . '9e22a3ac6f4c0ccae03a2356e710d68f'; #正式
        $res         = send_post($url, '');
        $AccessToken = json_decode($res, true);
        $AccessToken = $AccessToken['access_token'];
        $url         = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=" . $AccessToken;
        $post_data   =
            [
                'scene' => $goods_id . '&' . $uid . '&0',
                'page' => 'pages/goods/goodsdetail/goodsdetail',
                'width' => 100,
            ];
        $post_data   = json_encode($post_data);
        $data        = send_post($url, $post_data);
        $WXcode      = data_uri($data, 'image/png');
        return $this->outMessage($title, $WXcode);
    }

    //极选师的品牌及首页列表
    public function getDistributorBrandList()
    {
        $title       = "极选师的品牌及首页列表";
        $goods_brand = new GoodsBrand();
        $result      = $goods_brand->getGoodsBrandList(1, 0, ['brand_recommend' => 1], 'sort asc', 'brand_id,brand_name,brand_ads');
        $list        = $result['data'];
        $list[]      = ['brand_id' => '-1', 'brand_name' => '首页', 'brand_ads' => 'https://static.bonnieclyde.cn/upload/common/1522648278.png'];
        return $this->outMessage($title, $list);
    }

    //极选师的品牌及首页小程序码
    public function getDistributorBrandWxCode()
    {
        $title = "极选师的品牌及首页小程序码";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取登录信息");
        }
        $brand_id = request()->post('brand_id', 0);
        #$url         = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . 'wxba4f30503c1ea656' . "&secret=" . '369964cf3f3da0a50cd7a9d27ca5577a'; #测试
        $url         = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . 'wxd145d8a6e951dd1b' . "&secret=" . '9e22a3ac6f4c0ccae03a2356e710d68f'; #正式
        $res         = send_post($url, '');
        $AccessToken = json_decode($res, true);
        $AccessToken = $AccessToken['access_token'];
        $url         = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=" . $AccessToken;
        if ($brand_id == '-1') {
            $post_data =
                array(
                    'scene' => $this->uid . '&0',
                    'page' => 'pages/index/index',
                    'width' => 100,
                );
        } else {
            $post_data =
                array(
                    'scene' => $brand_id . '&' . $this->uid . '&0',
                    'page' => 'pages/goods/brandlist/brandlist',
                    'width' => 100,
                );
        }
        $post_data = json_encode($post_data);
        $data      = send_post($url, $post_data);
        $WXcode    = data_uri($data, 'image/png');
        return $this->outMessage($title, $WXcode);
    }

    //极选师账户 明细 配置
    public function getDistributorAccountDetail()
    {
        $title = "极选师账户与明细";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取登录信息");
        }
        $condition['uid'] = $this->uid;
        $start_date       = request()->post('start_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('start_date'));
        $end_date         = request()->post('end_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('end_date')) + 86400;
        if ($start_date != 0 && $end_date != 0) {
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
        } elseif ($start_date != 0 && $end_date == 0) {
            $condition["settlement_time"] = [
                [
                    ">",
                    $start_date
                ]
            ];
        } elseif ($start_date == 0 && $end_date != 0) {
            $condition["settlement_time"] = [
                [
                    "<",
                    $end_date
                ]
            ];
        }
        $condition['from_type'] = array(
            "in",
            "1,2,3"
        ); // 订单类型
        $distributor            = new DistributorService();
        $balanceInfo            = $distributor->getDistributorAccountDetail($condition);
        return $this->outMessage($title, $balanceInfo);
    }

    //极选师申请提现
    public function toWithdraw()
    {
        $title = "极选师申请提现";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $withdraw_no  = time() . rand(111, 999);
        $account_type = request()->post('account_type', '');
        $cash         = request()->post('cash', '');
        $distributor  = new DistributorService();
        $retval       = $distributor->addMemberBalanceWithdraw($this->instance_id, $withdraw_no, $this->uid, $account_type, $cash);
//        $this->email1();
        return $this->outMessage($title, $retval['data'], $retval['code'], $retval['message']);
    }

    //创建极选师提现通知模板
    public function createWithdrawTemplate()
    {
        Db::name('ns_template_push')->insert(
            [
                'open_id'      => request()->post('open_id'),
                'form_id'      => request()->post('form_id'),
                'out_trade_no' => request()->post('data_id'),
                'created'      => date('Y-m-d H:i:s', time()),
                'warn_type'    => 10,
                'is_send'      => 0
            ]
        );
        return json(['code' => 0, 'msg' => 'success']);
    }

    public function checkContraband($content)
    {
        $badword = require('badword.src.php');
        $m       = 0;
        for ($i = 0; $i < count($badword); $i++) {    //根据数组元素数量执行for循环
            //应用substr_count检测文章的标题和内容中是否包含敏感词      111
            if (substr_count($content, $badword [$i]) > 0) {
                $m++;
            }
        }
        return $m;
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
     * @return \think\response\Json
     * 极选师申请
     * 改版    03-28  全民极选师。。。
     */
    public function applyDistributor()
    {
        $title     = "极选师申请";
        $uid       = $this->uid;
        $post_data = request()->post();
        $time      = time();
        if (empty($uid))  return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        if (empty($post_data))  return $this->outMessage($title, "", '-100', "参数异常");

        $member      = new NsMemberModel();
        $member_info = $member->getInfo(['uid' => $uid]);   # 获取用户详情

        if($member_info['distributor_type'] != 0) return $this->outMessage($title, "", '-50', "已经是极选师");

        $apply_info       = \think\Db::name('bc_distributor_info')->where(['uid' => $uid])->find();
        $distributor_info = \think\Db::name('bc_distributor')->where(['uid' => $uid])->find();

        if ($apply_info && $distributor_info['is_check'] == 0) return $this->outMessage($title, "", '-200', "正在审核中 请耐心等候");
        if (strstr($post_data['birthday'], '-') == false) $post_data['birthday'] = substr($post_data['birthday'], 0, 4) . '-' . substr($post_data['birthday'], 4, 2) . '-' . substr($post_data['birthday'], 6, 2);

        $distributor_info = [
            'uid'             => $uid,
            'province'        => $post_data['province'],
            'name'            => $post_data['name'],
            'city'            => $post_data['city'],
            'tel'             => $post_data['tel'],
            'recommend_user'  => $post_data['recommend_user'],      # 推荐人 recommend_user
            'is_recommend'    => 1,                                 # 推荐人 1
            'created_time'    => $time
        ];
        #不通过  再次申请
        if (!empty($apply_info) && $distributor_info['is_check'] == 2) {
            $distributor_data = [
                'real_name'    => $post_data['name'],
                'balance'      => 0,
                'bonus'        => 0,
                'origin'       => 1,
                'is_check'     => 0,
                'status'       => 1,
                'update_time' => $time
            ];

            $res = \think\Db::name('bc_distributor_info')->insert($distributor_info);
            #update
            if ($res) {
                $condition['uid'] = $uid;
                $condition['id']  = array(
                    'neq',
                    $res
                );
                $_data['status']  = 0;
                \think\Db::name('bc_distributor_info')->where($condition)->update($_data);
                \think\Db::name('bc_distributor')->where(['uid' => $this->uid])->update($distributor_data);
                $this->email();
                return $this->outMessage($title, "", "1", '提交成功');
            } else {
                return $this->outMessage($title, "", "-1", '提交失败');
            }
        } else {
            $distributor_data = [
                'uid'          => $uid,
                'real_name'    => $post_data['name'],
                'balance'      => 0,
                'bonus'        => 0,
                'origin'       => 1,
                'is_check'     => 0,
                'status'       => 1,
                'create_time' => $time
            ];

            $res = \think\Db::name('bc_distributor_info')->insert($distributor_info);
            #add
            if ($res) {
                $distributorModel = new BcDistributorModel();
                $distributorModel->save($distributor_data);
                $id = $distributorModel->id;

                $distributorService = new DistributorService();
                $distributorModel->save(['activation_code'=>$distributorService->getActivationCode($id)],['id'=>$id]);
                $this->email();
                return $this->outMessage($title, "", "1", '提交成功');
            } else {
                return $this->outMessage($title, "", "-1", '提交失败');
            }
        }
    }

    //激活码申请极选师
    public function codeApplyDistributor()
    {
        $title     = "激活码极选师申请";
        if (empty($this->uid))  return $this->outMessage($title, "", '-50', "无法获取登录信息");

        $recommend_user = request()->post('recommend_user', '');
        $activation_code = request()->post('activation_code', '');

        $recommendInfo = \think\Db::name('bc_distributor')->where(['uid' => $recommend_user])->find();
        if(empty($recommendInfo))
            return $this->outMessage($title, "", '-50', "无法获取推荐人信息");

        if($recommendInfo['activation_code'] != $activation_code)
            return $this->outMessage($title, "", '-50', "请您填写正确的激活码");

        //判断验证
        $member      = new NsMemberModel();
        $member_info = $member->getInfo(['uid' => $this->uid]);   # 获取用户详情
        if($member_info['distributor_type'] != 0) return $this->outMessage($title, "", '-50', "已经是极选师或高级分销");

        $retval = $member->save(['distributor_type' => 4, 'source_distribution' => $recommend_user], ['uid' => $this->uid]);

        $distributorService = new DistributorService();
        if($retval > 0){
            $distributor = new BcDistributorModel();
            $info        = $distributor->getInfo(['uid' => $this->uid]);
            if (!empty($info)) { #有历史数据 update
                $data = [
                    'origin'       => 2,
                    'is_check'     => 1,
                    'status'       => 1,
                    'update_time' => time()
                ];
                $distributor->save($data, ['uid' => $this->uid]);
            } else { #没有历史数据 add
                $id = $distributor->where('1=1')->order('id desc')->find()['id'];
                $data = [
                    'uid'          => $this->uid,
                    'origin'       => 2,
                    'is_check'     => 1,
                    'status'       => 1,
                    'create_time' => time(),
                    'activation_code' => $distributorService->getActivationCode($id+1)
                ];
                $distributorModel = new BcDistributorModel();
                $distributorModel->save($data);
            }

            $open_id  = request()->post('open_id');
            $form_id  = request()->post('form_id');
            Db::name('ns_template_push')->insert([
                'open_id'   => $open_id,
                'form_id'   => $form_id,
                'warn_type' => 30,
                'is_send'   => 0,
                'uid'       => $this->uid,
                'created'   => date('Y-m-d H:i:s', time())
            ]);
            $distributorService->distributorTemplateSend($open_id,$form_id);
        }
        return $this->outMessage($title, "", "1", '提交成功');
    }

    /**
     * @return \think\response\Json
     * 获取推荐人姓名
     * 新版本弃用    03-28
     */
    public function applyUserName()
    {
        $uid   = request()->post('uid', 0);
        $title = "获取推荐人姓名";
        if (empty($uid)) return $this->outMessage($title, "", '-50', "请求参数为空");
        $real_name = \think\Db::name('ns_member')->where(['uid' => $uid])->find()['real_name'];
        return $this->outMessage($title, $real_name, "1", '提交成功');
    }


    /**
     * @return \think\response\Json
     * 获取申请入口小程序码
     */
    public function getApplyCode()
    {
        $title = "获取申请入口小程序码";
        if (empty($this->uid)) return $this->outMessage($title, "", '-50', "无法获取登录信息");

//        $url         = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . 'wxba4f30503c1ea656' . "&secret=" . '369964cf3f3da0a50cd7a9d27ca5577a'; #测试
        $url         = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . 'wxd145d8a6e951dd1b' . "&secret=" . '9e22a3ac6f4c0ccae03a2356e710d68f'; #正式
        $res         = $this->send_post($url, '');
        $AccessToken = json_decode($res, true);
        $AccessToken = $AccessToken['access_token'];
        $url         = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=" . $AccessToken;

//        $_info       = \think\Db::name('bc_distributor')->where(['uid' => $this->uid])->find();

//        if($_info['is_check'] == 1){
//            #申请成功页面
//            $post_data   =
//                array(
//                    'scene' => $this->uid,
//                    'page'  => 'pages/member/kolSuccess/kolSuccess',
//                    'width' => 430,
//                );
//        }else{
        #申请表单页面
        $post_data =
            array(
                'scene' => '0'.'&'.$this->uid,                                #推荐人&邀请人
                'page'  => 'pages/member/kolApply/kolApply',
                'width' => 430,
            );
//        }

        $post_data = json_encode($post_data);
        $data      = $this->send_post($url, $post_data);
        $result    = $this->data_uri($data, 'image/png');

        return $this->outMessage($title, $result);
    }

    /**
     * @return \think\response\Json
     * 极选师验证
     */
    public function checkApply()
    {
        $title            = "极选师验证";
        $uid              = $this->uid;
        $apply_info       = \think\Db::name('bc_distributor_info')->where(['uid' => $uid])->find();
        $distributor_info = \think\Db::name('bc_distributor')->where(['uid' => $uid])->find();
        $member_info      = \think\Db::name('ns_member')->where(['uid' => $uid])->find();

        if ($member_info['distributor_type'] != 0) {
            return $this->outMessage($title, "", '2', "你已经是极选师");
        } elseif (!empty($apply_info) && $distributor_info['is_check'] == 0) {
            return $this->outMessage($title, "", '3', "资料正在审核中 请耐心等待");
        } else {
            return $this->outMessage($title, "", '1', "未申请");
        }
    }


    /**
     * @return \think\response\Json
     * 创建kol申请提醒模板
     */
    public function sendKolTemplateCreated()
    {
        $openid = request()->post('openid', '0');
        $formid = request()->post('formid', '0');

        $info = \think\Db::name('ns_template_push')->where(['uid' => $this->uid, 'is_send' => 0, 'warn_type' => 30])->find();

        if ($info) return $this->outMessage('', "", '-50', "已存在");

        Db::name('ns_template_push')->insert([
            'open_id'   => $openid,
            'form_id'   => $formid,
            'warn_type' => 30,
            'is_send'   => 0,
            'uid'       => $this->uid,
            'created'   => date('Y-m-d H:i:s', time())
        ]);

        return json(['code' => 0, 'msg' => 'success']);
    }


    /**
     * @param
     * @author dai <zhiwei.dai@ushopal.com>
     * @return mixed
     * 极选师申请
     */
    public function email()
    {
//        $toEmail1 = 'bc_kol_manger@ushopal.com';
//        $name1    = 'BonnieClyde极选师-管理组';

        $toEmail2 = 'xiaoshi.he@ushopal.com';
        $name2    = '何晓诗';

        $toEmail3 = 'zhiwei.dai@ushopal.com';
        $name3    = '戴志伟';

        $subject = 'BC极选师申请提示';
        $content = '你好，有新的极选师申请，请及时处理！（后台链接）
                    <br><br><a href="https://www.bonnieclyde.cn/index.php?s=/dira/Distributor/kolApplyList ">点我进入后台</a>';

//        $this->send_mail($toEmail1, $name1, $subject, $content);
        $this->send_mail($toEmail2, $name2, $subject, $content);
        $this->send_mail($toEmail3, $name3, $subject, $content);
    }

    /**
     * @param
     * @author dai <zhiwei.dai@ushopal.com>
     * @return mixed
     * 极选师提现申请
     */
    public function email1()
    {
        $toEmail1 = 'cherry@ushopal.com';
        $name1    = 'cherry';


        $subject = '极选师提现申请通知';
        $content = '你好，有新的极选师提现申请，请及时处理！（后台链接）
                    <br><br><a href="https://www.bonnieclyde.cn/index.php?s=/dira/Distributor/kolWithdrawList.html">点我进入后台</a>';

        $this->send_mail($toEmail1, $name1, $subject, $content);
    }


    /**
     * 系统邮件发送函数
     * @param string $toMail 接收邮件者邮箱
     * @param string $name 接收邮件者名称
     * @param string $subject 邮件主题
     * @param string $body 邮件内容
     * @param string $attachment 附件列表
     * @return boolean
     * @author bc <noreply@ushopal.com>
     */
    function send_mail($toMail, $name, $subject = '', $body = '', $attachment = null)
    {
        $mail          = new PHPMailer();
        $mail->CharSet = 'UTF-8';
        $mail->IsSMTP();
        $mail->SMTPDebug  = 0;
        $mail->SMTPAuth   = true;
        $mail->SMTPSecure = 'ssl';
        $mail->Host       = "smtp.exmail.qq.com";
        $mail->Port       = 465;
        $mail->Username   = "noreply@ushopal.com";
        $mail->Password   = "ShopalTech123";
        $mail->SetFrom('noreply@ushopal.com', '技术部门');
        $replyEmail = '';
        $replyName  = '';
        $mail->AddReplyTo($replyEmail, $replyName);
        $mail->Subject = $subject;
        $mail->MsgHTML($body);
        $mail->AddAddress($toMail, $name);
        # 添加附件
        if (is_array($attachment)) {
            foreach ($attachment as $file) {
                is_file($file) && $mail->AddAttachment($file);
            }
        }
        return $mail->Send() ? true : $mail->ErrorInfo;
    }

    //极选师小程序码列表
    public function wxcodeList()
    {
        $title = "极选师小程序码列表";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取登录信息");
        }
        $page_index       = request()->post("page", 1);
        $condition['uid'] = $this->uid;
        $wxcode_service   = new DistributorWxcode();
        $wxcode_list      = $wxcode_service->getWxcodeList($page_index, PAGESIZE, $condition, 'create_time desc');
        return $this->outMessage($title, $wxcode_list);
    }

    //极选师小程序码创建
    public function wxcodeAdd()
    {
        $title = "极选师小程序码创建";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取登录信息");
        }
        $name           = request()->post('name', 0);
        $code_pic       = request()->post('code_pic', '');
        $wxcode_service = new DistributorWxcode();
        $id             = $wxcode_service->wxcodeAdd($this->uid, $name, $code_pic);
        return $this->outMessage($title, $id);
    }

    //极选师小程序码删除
    public function wxcodeDelete()
    {
        $title = "极选师小程序码删除";
        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取登录信息");
        }
        $id             = request()->post('id', '');
        $wxcode_service = new DistributorWxcode();
        $retval         = $wxcode_service->wxcodeDelete($this->uid, $id);
        return $this->outMessage($title, $retval);
    }



    /**
     * @param $type
     * @param $file_path
     * @param $uid
     * @return bool|int|string
     * @throws \Exception
     * 上传私密空间图片
     */
    public function upPrivateIMG($type , $file_path ,$uid){
        #type    1:银行卡 暂时弃用   2:身份证正 3:身份证反

        $accessKey = '_xBTRsUTy2VR_qH5JNjspfBakRzTIv7YLsV3Fjup';
        $secretKey = '09F9mdbtGnCN1oTCXExVjpb2N79Qp5rgFye37CmE';

        $auth = new Auth($accessKey, $secretKey);

        // 要转码的文件所在的空间
        $bucket = 'bcids';

        //自定义上传回复的凭证 返回的数据
        $returnBody = '{"key":"$(key)","hash":"$(etag)","fsize":$(fsize),"bucket":"$(bucket)","name":"$(fname)"}';
        $policy = array(
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

        if($type == 2){
            $key = $uid.time().'_身份证正面.jpg';
        }else{
            $key = $uid.time().'_身份证反面.jpg';
        }

        $uploadMgr = new UploadManager();   // 调用 UploadManager 的 putFile 方法进行文件的上传。

        list($ret, $err) = $uploadMgr->putFile($token, $key, $filePath);

        if ($err !== null) {
            return false;
        } else {
            $data = [
                'uid'        => $uid,
                'fname'      => $key,
                'key'        => $ret['key'],
                'type'       => $type,
                'url'        => $this->getIMG($key),
                'createTime' => time()
            ];

            \think\Db::name('bc_private_img')->insert($data);
        }
    }


    /**
     * @param $file_name
     * @return string
     * 获取图片url
     */
    public function getIMG($file_name){
        $accessKey = '_xBTRsUTy2VR_qH5JNjspfBakRzTIv7YLsV3Fjup';
        $secretKey = '09F9mdbtGnCN1oTCXExVjpb2N79Qp5rgFye37CmE';
        $auth      = new Auth($accessKey, $secretKey);
        $baseUrl   = 'http://private.bonnieclyde.cn/'.$file_name;
        $signedUrl = $auth->privateDownloadUrl($baseUrl);
        return $signedUrl;
    }


    /**
     * @param $id_face_pros
     * @param $id_face_cons
     * @param $uid
     * 新增私密图片
     */
    public function cronTabUpImgDay($id_face_pros ,$id_face_cons,$uid){
        $img1      = file_get_contents($id_face_pros);
        $img2      = file_get_contents($id_face_cons);
//        $img3      = file_get_contents($bank_card_pic);
        $address   = 'application/images/' . $uid;
        $fileName1 = $address . '_身份证正面.jpg';
        $fileName2 = $address . '_身份证反面.jpg';
//        $fileName3 = $address . '_银行卡照片.jpg';
        file_put_contents($fileName1, $img1);
        file_put_contents($fileName2, $img2);
//        file_put_contents($fileName3, $img3);
//        $this->upPrivateIMG(1 , $fileName3 ,$uid);
        $this->upPrivateIMG(2 , $fileName1 ,$uid);
        $this->upPrivateIMG(3 , $fileName2 ,$uid);
    }


    /**
     * @return \think\response\Json
     * 上传身份证  实名认证
     */
    public function upUserIDCard(){
        $real_name    = request()->post('real_name', '');
        $id_face_pros = request()->post('id_face_pros', '');
        $id_face_cons = request()->post('id_face_cons', '');
        $idCard       = request()->post('idCard', '');
        $issue        = request()->post('issue', '');
        $start_date   = request()->post('start_date', '');
        $end_date     = request()->post('end_date', '');
        $birthday     = request()->post('birthday', '');
        $nation       = request()->post('nation', '');
        $address      = request()->post('address', '');
        $sex          = request()->post('sex', '');
        $request_id   = request()->post('request_id', '');


        $title        = '获取身份证信息';

        $data = [
            'id_face_pros' => $id_face_pros,
            'id_face_cons' => $id_face_cons,
            'real_name'    => $real_name,
            'idCard'       => $idCard,
            'issue'        => $issue,
            'start_date'   => $start_date,
            'end_date'     => $end_date,
            'birthday'     => $birthday,
            'nation'       => $nation,
            'address'      => $address,
            'sex'          => $sex,
            'request_id'   => $request_id,
            'uid'          => $this->uid,
            'created_time' => time()
        ];

        $data1 = [
            'id_face_pros' => $id_face_pros,
            'id_face_cons' => $id_face_cons,
            'real_name'    => $real_name,
            'idCard'       => $idCard,
            'issue'        => $issue,
            'start_date'   => $start_date,
            'end_date'     => $end_date,
            'birthday'     => $birthday,
            'nation'       => $nation,
            'address'      => $address,
            'sex'          => $sex,
            'request_id'   => $request_id,
            'update_time'  => time()
        ];

        if(!empty(\think\Db::name('bc_authentication_info')->where(['uid' => $this->uid])->find())){
            $res = \think\Db::name('bc_authentication_info')->where(['uid' => $this->uid])->update($data1);
        }else{
            $res = \think\Db::name('bc_authentication_info')->insert($data);
        }

        if($res > 0){
            $_res['real_name'] = $real_name;
            \think\Db::name('sys_user')->where(['uid' => $this->uid])->update($_res);
            \think\Db::name('ns_member')->where(['uid' => $this->uid])->update($_res);
            \think\Db::name('bc_distributor')->where(['uid' => $this->uid])->update($_res);
            return $this->outMessage($title, $res, "1", '提交成功');
        }else{
            return $this->outMessage($title, $res, "0", '操作失败');
        }

    }

    # 同步数据  47cols
    public function syncUserId(){
        $list = \think\Db::name('bc_distributor_info')->select();
        $arr  = [];
        foreach($list as $v){
            if(!empty($v['id_face_pros'])){
                # 接口识别


                $data = [
                    'id_face_pros' => $v['$id_face_pros'],
                    'id_face_cons' => $v['$id_face_cons'],
                    'real_name'    => $v['name'],
                    'idCard'       => '',
                    'issue'        => '',
                    'start_date'   => '',
                    'end_date'     => '',
                    'birthday'     => $v['birthday'],
                    'nation'       => $v['nation'],
                    'address'      => $v['province'].$v['city'].$v['district'].$v['address'],
                    'sex'          => $v['sex'] == '0' ? '女' : '男',
                    'request_id'   => '',
                    'uid'          => $v['uid'],
                    'created_time' => $v['created_time']
                ];
                $info = \think\Db::name('bc_authentication_info')->insert($data);

                if($info > 0){
                    array_push($arr,$v['uid']);
                }
            }
        }
        var_dump($arr);
    }


    public function kolProducts(){
        $title      = 'kol分润商品';
        $fraction   = request()->post('key', '');
        $page_index = request()->post("page_index", 1);
        $page_size  = request()->post("page_size", PAGESIZE);

        $member      = new NsMemberModel();
        $member_info = $member->getInfo(['uid' => $this->uid],'source_distribution,distributor_type');
        $_a = ($member_info['source_distribution'] > 0 && $member_info['distributor_type'] == 4) ? '1' : '2';

        if($_a == 1){
            $head_list = [
                '0' => ['key' => '0' , 'value' => '24%以上', 'is_check' => true],
                '1' => ['key' => '1' , 'value' => '24%～16%', 'is_check' => false],
                '2' => ['key' => '2' , 'value' => '16%～8%', 'is_check' => false],
                '3' => ['key' => '3' , 'value' => '8%以下', 'is_check' => false],
            ];
        }else{
            $head_list = [
                '0' => ['key' => '0' , 'value' => '30%以上', 'is_check' => true],
                '1' => ['key' => '1' , 'value' => '30%～20%', 'is_check' => false],
                '2' => ['key' => '2' , 'value' => '20%～10%', 'is_check' => false],
                '3' => ['key' => '3' , 'value' => '10%以下', 'is_check' => false],
            ];
        }


        $condition['ng.state']          = 1;                     # 上架
        $condition['ng.is_vip']         = 0;                     # 非会员商品
        $condition['ng.is_vip']         = 0;                     # 非会员商品
        $condition['ng.goods_type']     = array('in', '1,2');    # 实物和礼品
        $condition['ngc.sort']          = array('neq', '0');     # 实物和礼品
        $condition['ng.fraction']       = array('egt', '0.3');   # 分润比例
        $condition['ngc.is_visible']    = 1;                     # 不现实隐藏分类商品



        if($fraction) {
            switch ($fraction) {
            case '0':
                $condition['ng.fraction'] = $_a == 1 ? array('egt', '0.24') : array('egt', '0.3');
                break;
            case '1':
                $condition['ng.fraction'] = $_a == 1 ? array(array('egt','0.16'),array('lt','0.24')) : array(array('egt','0.2'),array('lt','0.3'));
                break;
            case '2':
                $condition['ng.fraction'] = $_a == 1 ? array(array('egt','0.08'),array('lt','0.16')) : array(array('egt',0.1),array('lt',0.2));
                break;
            case '3':
                $condition['ng.fraction'] = $_a == 1 ?  array('lt', '0.08') : array('lt', '0.1');
                break;
            default:
                break;
            }
        }

        $goods = new NsGoodsViewModel();

        $product_list         = $goods->getGoodsViewList($page_index, $page_size, $condition, 'ng.sales desc');

        if($_a == 1){
            foreach($product_list['data'] as $key=>$v){
                $product_list['data'][$key]['fraction'] = $product_list['data'][$key]['fraction'] * 0.8;
            }
        }

        $data['head_list']    = $head_list;
        $data['product_list'] = $product_list['data'];

        return $this->outMessage($title, $data);

    }

    # 返回身份信息
    public function outIdCardInfo(){

        $title             = '返回身份信息';
        $info              = \think\Db::name('bc_authentication_info')->where(['uid' => $this->uid])->find();
        $data['idCard']    = empty($info['idCard']) ? '' : $info['idCard'];
        $data['real_name'] = empty($info['real_name']) ? '' : $info['real_name'];
        return $this->outMessage($title, $data);
    }

    public function test(){
        return [];
        $res = \think\Db::name('bc_distributor_info')->select();

        foreach($res as $v){
            if(empty($v['id_face_pros'])) continue;
            $_res['id_face_pros'] = $v['id_face_pros'] ;
            $_res['id_face_cons'] = $v['id_face_cons'] ;
            \think\Db::name('bc_authentication_info')->where(['uid' => $v['uid']])->update($_res);
        }
    }

}