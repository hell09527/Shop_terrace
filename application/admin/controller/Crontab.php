<?php

namespace app\admin\controller;

use data\model\BcStoreActivitySlaverModel;
use data\model\UserModel;
use data\service\Order;
use data\service\Pospal\Pospal;
use think\Controller;
use think\Db;

class Crontab extends Controller
{
//    public function checkWxPushTemplate($step = 1)
//    {
////        $this->sendTemplate_warnPay('');
//        $target_unix = time() - 15 * 60;
//        $start       = date('Y-m-d H:i:s', $target_unix);
//        $end         = date('Y-m-d H:i:s', $target_unix + 60 * $step + 1);
//        // 将支付的记录删除
//        $where     = [
//            'warn_type' => 2,
//            'created' => [
//                'between', [$start, $end]
//            ],
//            'is_send' => 0,
//        ];
//        $upd_ids   = []; # 等待修改状态
//        $templates = \think\Db::name('ns_template_push')->where($where)->select();
//        foreach ($templates as $k => $item) {
//            $upd_ids[] = $item['out_trade_no'];
//            # 检查是否已付款
//            $pay_status = Db::name('ns_order_payment')->where(['out_trade_no' => $item['out_trade_no']])->find();
//            if ($pay_status['pay_status'] == 1) {
//                continue;
//            }
//            $this->sendTemplate_warnPay($item['out_trade_no'], $item);
//        }
//        # 消失
//        if ($upd_ids) {
//            \think\Db::name('ns_template_push')->where([
//                'out_trade_no' => [
//                    'in', $upd_ids
//                ],
//                'warn_type' => 2,
//                'is_send' => 0,
//            ])->update(['is_send' => 1]);
//        }
//    }

    public function checkWxPushTemplate()
    {
        $min_time = date('Y-m-d H:i:s',time()-15*60);
        $where     = [
            'warn_type' => 2,
            'created' => ['ELT',$min_time],
            'is_send' => 0,
        ];
        $templates = Db::name('ns_template_push')->where($where)->select();

        $upd_ids   = [];
        foreach ($templates as $k => $item) {
            $upd_ids[] = $item['out_trade_no'];
            # 检查是否已付款
            $order = Db::name('ns_order')->where(['out_trade_no' => $item['out_trade_no']])->find();
            if($order['order_status'] == 0){
                $this->sendTemplate_warnPay($item['out_trade_no'], $item);
            }
        }

        # 修改发送状态
        if (!empty($upd_ids)) {
            Db::name('ns_template_push')->where([
                'out_trade_no' => ['in', $upd_ids],
                'warn_type' => 2,
                'is_send' => 0,
            ])->update(['is_send' => 1]);
        }
    }

    # 订单未支付通知
    public function sendTemplate_warnPay($out_trade_no, $template)
    {
//        $template_id = 'eEGvWjnJ3j9HcLgr0PPUGarbo-aawdaKRI08piODMUE'; # dev
//        $template_id = 'HWbHr5eXw23_pV6qsAJF3zqbM-EVMtdkLyK47cCmchs'; # prod

        $template_id = getWxTemplateId('no_pay');

        $order = \think\Db::name('ns_order')->where(['out_trade_no' => $out_trade_no])->find();
        # 如果订单已关闭
        if (!$order) return;
        $conf = json_decode(\think\Db::name('sys_config')->where([
            'key' => 'SHOPAPPLET'
        ])->find()['value'], true);

        $appid  = $conf['appid'];
        $secret = $conf['appsecret'];

        $access_token = getAccessToken($appid, $secret);
        $select_url   = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;

        $openid = $template['open_id'];
        $fid    = $template['form_id'];

        $p1 = $order['order_no'];
        $p2 = date('Y-m-d H:i:s', $order['create_time']);
        $p3 = $order['pay_money'];
        $p4 = '如有疑问请联系客服...';

        $page = 'pages/order/myorderlist/myorderlist?status=1';
//        $page = 'pages/pay/getpayvalue/getpayvalue?out_trade_no=' . $out_trade_no;

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
          "value": "$p2"
      },
      "keyword3": {
          "value": "$p3",
          "color": "#173177"
      },
      "keyword4": {
          "value": "$p4"
      }
  },
  "color":"#ccc"
}
EOL;

        curl_post($select_url, $param);
//        \think\Db::name('ns_template_push')->where(['id'=>$template['id']])->delete();
    }

    # 创建批次..
    public function createBatchQrCode()
    {
        $arr  = $this->storeArr();
        $conf = json_decode(\think\Db::name('sys_config')->where([
            'key' => 'SHOPAPPLET'
        ])->find()['value'], true);

        $appid        = $conf['appid'];
        $secret       = $conf['appsecret'];
        $access_token = getAccessToken($appid, $secret);
        $url          = 'https://api.weixin.qq.com/cgi-bin/wxaapp/createwxaqrcode?access_token=' . $access_token;

        foreach ($arr as $v) {
            $infos = \think\Db::name('ns_goods')->where([
                'material_code' => ['like', "%{$v}%"],
            ])->select();

            if (!$infos) {
                echo '未上架: ';
                echo $v, '<br>';
                continue;
            }

            $info = $infos[0];
            if (count($infos) > 1) foreach ($infos as $_info) {
                if ($_info['goods_type'] == 1) $info = $_info;
            }

            $param = [
                'path' => "pages/goods/goodsdetail/goodsdetail?goods_id=" . $info['goods_id'] . '&' . 'store_id=1',
                'width' => 150,
            ];

            $ret      = $this->http_post_data($url, json_encode($param));
            $fileName = $info['goods_name'] . '-' . $info['material_code'] . 'jpg';
            file_put_contents('/var/www/data/niushop/qr8-1/' . $fileName, $ret);
        }
    }

    public function createQrCode($id)
    {
        $arr  = $this->storeArr();
        $conf = json_decode(\think\Db::name('sys_config')->where([
            'key' => 'SHOPAPPLET'
        ])->find()['value'], true);

        $appid        = $conf['appid'];
        $secret       = $conf['appsecret'];
        $access_token = getAccessToken($appid, $secret);

        $url = 'https://api.weixin.qq.com/cgi-bin/wxaapp/createwxaqrcode?access_token=' . $access_token;
//        $param = [
//            'path' => "pages/goods/goodsdetail/goodsdetail?goods_id=".$id,
//            'width' => 150,
//        ];
//
//        $ret = $this->http_post_data($url,json_encode($param));

        foreach ($arr as $v) {
            $info  = \think\Db::name('ns_goods')->where(['material_code' => $v])->find();
            $param = [
                'path' => "pages/goods/goodsdetail/goodsdetail?goods_id=" . $info['goods_id'] . '&' . 'store_id=1',
                'width' => 150,
            ];

            $ret      = $this->http_post_data($url, json_encode($param));
            $fileName = $info['material_code'] . 'jpg';
            file_put_contents('/var/www/data/niushop/qr/' . $fileName, $ret);
        }
    }

    public function http_post_data($url, $data_string)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($data_string))
        );
        ob_start();
        curl_exec($ch);
        $return_content = ob_get_contents();
        //echo $return_content."<br>";
        ob_end_clean();
        $return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return $return_content;
    }

    #会员到期推送
    public function checkVipPushTemplate($step = 1)
    {
        $target_unix = time() - 15 * 60;
        $start       = date('Y-m-d H:i:s', $target_unix);
        $end         = date('Y-m-d H:i:s', $target_unix + 60 * $step + 1);
        // 将支付的记录删除
        $where     = [
            'warn_type' => 3,
            'is_send' => 1,
        ];
        $upd_ids   = []; # 等待修改状态
        $templates = \think\Db::name('ns_template_push')->where($where)->select();
        foreach ($templates as $k => $item) {
            $upd_ids[] = $item['out_trade_no'];
            # 检查是否已付款
            $pay_status = Db::name('ns_order_payment')->where(['out_trade_no' => $item['out_trade_no']])->find();
            $order      = \think\Db::name('ns_order')->where(['out_trade_no' => $pay_status['out_trade_no']])->find();
            if ($pay_status['pay_status'] == 1) {
                continue;
            }
            $this->sendTemplate_vipOver($order['buyer_id'], $item);
        }
        # 消失
        if ($upd_ids) {
            \think\Db::name('ns_template_push')->where([
                'out_trade_no' => [
                    'in', $upd_ids
                ],
                'warn_type' => 3,
                'is_send' => 0,
            ])->update(['is_send' => 1]);
        }
    }


    # 会员到期通知
    public function sendTemplate_vipOver($uid, $template)
    {
//        $template_id = 'YXYpEoEm5vMaVWk9vMvvCdZ4WtbhudSf-Px51s9GrOw'; # dev
//        $template_id = 'IQ2oCHXX94kVIuuPyORZ9k449Gvlb0SjHLetorDionw'; # prod
        $template_id = getWxTemplateId('vip_over');
        $vip_info    = \think\Db::name('ns_member')->where(['uid' => $uid, 'is_vip' => 1])->find();
        # 该用户不是会员
        if (!$vip_info) return;
        $conf = json_decode(\think\Db::name('sys_config')->where([
            'key' => 'SHOPAPPLET'
        ])->find()['value'], true);

        $appid  = $conf['appid'];
        $secret = $conf['appsecret'];

        $access_token = getAccessToken($appid, $secret);
        $select_url   = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;

        $openid = $template['open_id'];
        $fid    = $template['form_id'];

        $end_time = $vip_info['vip_buy_time'] + 86400 * 365;
        $p1       = date("Y-m", time());
        $p2       = 'BC尊享会员';
        $p3       = ceil(($end_time - time()) / 86400);
        $p4       = date("Y-m-d", $end_time);
        $p5       = '您的会员即将到期，即可续费享受更多特权，点击查看特权详情。';

        $page = '/pages/payMembers/memberZone/memberZone';

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
//        \think\Db::name('ns_template_push')->where(['id'=>$template['id']])->delete();
    }

    #优惠券到期推送
    public function checkCouponPushTemplate($step = 3)
    {
        $target_unix = time();
        $start       = date('Y-m-d H:i:s', $target_unix);
        $end         = date('Y-m-d H:i:s', $target_unix + 64800 * $step);
        // 获取该时间区间数据
        $where     = [
            'warn_type' => 7,
            'is_send' => 0,
        ];
        $upd_ids   = []; # 等待修改状态
        $templates = \think\Db::name('ns_template_push')->where($where)->select();
        foreach ($templates as $k => $item) {
            $upd_ids[] = $item['coupon_id'];
            # 检查是否已过期
            $coupon_status = Db::name('ns_coupon')->where(
                ['coupon_id' => $item['coupon_id'],
                    'end_time' => [
                        'between', [$start, $end]
                    ]])->find();
            if (!$coupon_status) ;
            return;
            if ($coupon_status['state'] == 2 || $coupon_status['state'] == 3) {
                continue;
            }
            $this->sendTemplate_couponOver($item['coupon_id'], $item);
        }
        # 消失
        if ($upd_ids) {
            \think\Db::name('ns_template_push')->where([
                'out_trade_no' => [
                    'in', $upd_ids
                ],
                'warn_type' => 7,
                'is_send' => 0,
            ])->update(['is_send' => 1]);
        }
    }


    # 优惠券到期通知
    public function sendTemplate_couponOver($coupon_id, $template)
    {
        $template_id = getWxTemplateId('coupon_over');
        $coupon_info = \think\Db::name('ns_coupon')->where(['coupon_id' => $coupon_id])->find();
        # 该优惠券不存在
        if (!$coupon_info) return;
        $conf = json_decode(\think\Db::name('sys_config')->where([
            'key' => 'SHOPAPPLET'
        ])->find()['value'], true);

        $appid  = $conf['appid'];
        $secret = $conf['appsecret'];

        $access_token = getAccessToken($appid, $secret);
        $select_url   = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;

        $openid = $template['open_id'];
        $fid    = $template['form_id'];

        $end_time    = date('Y-m-d', $coupon_info['end_time']);
        $coupon_name = \think\Db::name('ns_coupon_type')->where(['coupon_type_id' => $coupon_info['coupon_type_id']])->find();
        $p1          = $coupon_name['coupon_name'];
        $p2          = '卡券还有三天到期,请及时使用!';
        $p3          = $end_time;

        $page = '/pages/index/index';   #主页

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
      }
  },
  "color":"#ccc"
}
EOL;

        curl_post($select_url, $param);
//        \think\Db::name('ns_template_push')->where(['id'=>$template['id']])->delete();
    }


    #到货通知查询
    public function checkPushProTemplate()
    {
        #dev:       wWjdNBZogSaecJ9iWoalkq3zncZMBpUNPm_eMuORi0g
        #prod:      9gfgdxbbMOtlj4-7ZqDzKosLLHa8_H7Pp0xJxAaLzoc
        // 查询售空的商品
        $where       = [
            'stock' => 0,
        ];
        $goods_lists = \think\Db::name('ns_goods')->where($where)->select();
        if ($goods_lists) {
            #保存数据
            foreach ($goods_lists as $k => $item) {
                if (\think\Db::name('ns_goods_template_record')->where(['goods_id' => $item['goods_id']])->find()) continue;
                Db::name('ns_goods_template_record')->insert([
                    'goods_id' => $item['goods_id'],
                    'stock' => 0,
                    'created' => date('Y-m-d H:i:s', time())
                ]);
            }
        }
        $record_goods_lists = \think\Db::name('ns_goods_template_record')->where(['stock' => 0])->select();
        if (!$record_goods_lists) return;
        foreach ($record_goods_lists as $v) {
            $goods_info = \think\Db::name('ns_goods')->where(['goods_id' => $v['goods_id']])->find();
            # 如果商品已经不存在
            if (!$goods_info) {
                \think\Db::name('ns_goods_template_record')->where(['id' => $v['id']])->delete();
                continue;
            } else if ($goods_info['stock'] > 0) {
                \think\Db::name('ns_goods_template_record')->where(['id' => $v['id']])->delete();
                $this->sendTemplate_UpPro($goods_info);
            } else {
                continue;
            }
        }
    }

    # 到货通知
    public function sendTemplate_UpPro($goods_info)
    {
        $template_id    = getWxTemplateId('up_pro');
        $conf           = json_decode(\think\Db::name('sys_config')->where([
            'key' => 'SHOPAPPLET'
        ])->find()['value'], true);
        $appid          = $conf['appid'];
        $secret         = $conf['appsecret'];
        $access_token   = getAccessToken($appid, $secret);
        $select_url     = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;
        $template_lists = \think\Db::name('ns_template_push')->where(['warn_type' => 11, 'out_trade_no' => $goods_info['goods_id']])->select();
        if (!$template_lists) return;
        foreach ($template_lists as $vo) {
            $openid = $vo['open_id'];
            $fid    = $vo['form_id'];
            $p1     = $goods_info['goods_name'];
            $p2     = $goods_info['price'] . '元';
            $p3     = '您预约的' . $goods_info['goods_name'] . '已到货';
            $page   = 'pages/goods/goodsdetail/goodsdetail?goods_id=' . $goods_info['goods_id'] . '&goods_name=' . $goods_info['goods_name'];
            $param  = <<<EOL
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
          "value": "$p2"
      },
      "keyword3": {
          "value": "$p3",
          "color": "#173177"
      }
  },
  "color":"#ccc"
}
EOL;
            curl_post($select_url, $param);
            \think\Db::name('ns_template_push')->where(['form_id' => $fid, 'open_id' => $openid])->delete();
        }
    }

    public function storeArr()
    {
        $arr = [
            'FCAL007',
            'FCAL005',
            'FCAL006',
            'FAHU041',
            'FCNB016',
            'SCNB002',
            'SCNB007',
            'FCNB012',
            'FCNB008',
            'SCNB006',
            'FETL032',
            'FETL018',
            'FETL001',
            'FETL020',
            'FETL003',
            'FETL021'
        ];
        return $arr;
    }

    #用户注册同步RunSa
    public function memberSyncRunSa()
    {
        $runSa    = Order\RunSa::instance();
        $userList = \think\Db::name('sys_user')->select();
        foreach ($userList as $v) {
            if (!$v['user_tel']) continue;
            $findMemberInfo = [
                'phone' => $v['user_tel'],
            ];
            if (json_decode($runSa->findMember($findMemberInfo)[1], true)['msg'] !== '顾客不存在') continue;
            if (\think\Db::name('bc_runsa_member_record')->where(['phone' => $v['user_tel']])->find()) continue;
            $info = \think\Db::name('ns_member')->where(['uid' => $v['uid']])->find();
            if ($info['source_distribution']) {
                $refer_info = \think\Db::name('sys_user')->where(['uid' => $info['source_distribution']])->find();
            }
            $memberInfo = [
                'phone'      => $v['user_tel'],
                'cstName'    => $v['nick_name'],
                'realName'   => empty($info['real_name']) ? $v['nick_name'] : $info['real_name'],
                'cstSrc'     => 'SIT',
                'srcVal'     => 'BC0001',
                'sex'        => $v['sex'],
                'email'      => $v['user_email'],
                'referPhone' => empty($refer_info['user_tel']) ? '' : $refer_info['user_tel'],
                'regTime'    => $v['reg_time'],
            ];
            $res        = $runSa->addMember($memberInfo);
            if (json_decode($res[1], true)['code'] != '20000' || json_decode($res[1], true)['content']['status'] != '2') continue;
            #记录
            $record_res = [
                'phone' => $v['user_tel'],
                'uid' => $v['uid'],
                'cstId' => json_decode($res[1], true)['content']['cstId'],
                'created_time' => time(),
            ];
            \think\Db::name('bc_runsa_member_record')->insert($record_res);
        }
    }


    # 预约活动提醒计时
    public function checkStoreActivityAppointmentTemplate()
    {
        $target_unix = time();
        $start       = date('Y-m-d H:i:s', $target_unix);
        $end         = date('Y-m-d H:i:s', $target_unix + 64800 * 1);

        # 获取该时间区间数据
        $where     = [
            'warn_type' => 100,
            'is_send'   => 0,
        ];

        $upd_ids   = []; # 等待修改状态

        $templates = \think\Db::name('ns_template_push')->where($where)->select();

        foreach ($templates as $k => $item) {
            $upd_ids[] = $item['out_trade_no'];
            $this->sendTemplate_StoreActivityAppointment($item['out_trade_no'], $item);
        }

        # 更新状态
        if ($upd_ids) {
            \think\Db::name('ns_template_push')->where([
                'out_trade_no' => [
                    'in', $upd_ids
                ],
                'warn_type' => 100,
                'is_send'   => 0,
            ])->update(['is_send' => 1]);
        }
    }


    # 预约活动模版推送
    public function sendTemplate_StoreActivityAppointment($appointment_id,$template)
    {
        $template_id  = getWxTemplateId('store_appointment');

        $slaver_store = new BcStoreActivitySlaverModel();

        $slaver_store_info = $slaver_store->getInfo(['id' => $appointment_id]);

        # 该预约活动不存在
        if ( !$slaver_store_info ) return;

        $conf = json_decode(\think\Db::name('sys_config')->where([
            'key' => 'SHOPAPPLET'
        ])->find()['value'], true);

        $appid  = $conf['appid'];
        $secret = $conf['appsecret'];

        $access_token = getAccessToken($appid, $secret);
        $select_url   = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;

        $openid = $template['open_id'];
        $fid    = $template['form_id'];

//        $end_time    = date('Y-m-d', $coupon_info['end_time']);
//        $coupon_name = \think\Db::name('ns_coupon_type')->where(['coupon_type_id' => $coupon_info['coupon_type_id']])->find();
//        $p1          = $coupon_name['coupon_name'];
//        $p2          = '卡券还有三天到期,请及时使用!';
//        $p3          = $end_time;

        $page = '/pages/index/index';   #主页

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
      }
  },
  "color":"#ccc"
}
EOL;

        curl_post($select_url, $param);
    }



//$updataMemberInfo = [
//'cstId'    => 100000000001,
//'cstName'  => '撒浪嘿呦',
//'realName' => '陈传烽',
//'cstType'  => 0,
////    'regTime' =>
//];
////$client->updateMember ($updataMemberInfo);
    #runsa会员更新   弃用 ！！！
    public function memberUpdateRunSa()
    {
        $runSa         = Order\RunSa::instance();
        $memberInfo    = [
            'phone'    => '18895305362',
            'cstName'  => '撒浪嘿呦11',        #sys_user    nick_name
            'realName' => '陈传烽',          #ns_member   member_name
            'cstSrc'   => 'SIT',
            'srcVal'   => 'BC0001',
            'sex'      => 1,                #sys_user    sex
            'email'    => mt_rand (10000, 99999) . '@zz.com',         #sys_user    user_email
            'regTime'  => date('Y-m-d H:i:s','1540120156')
        ];

        var_dump($runSa->addMember ($memberInfo));
    }

    /**
     * 同步pospal线下更改的用户信息到小程序
     * 可能修改的信息  会员姓名  手机号  生日  邮箱  qq  地址
     * 每天更新一次  所有用户信息 ...
     */
    public function crontabPospalUser(){
        if( $_SERVER['HTTP_HOST'] !== 'www.bonnieclyde.cn' ) return;
        $pospal         = new Pospal();
        #查询接口调用
        $queryDaily = $pospal->queryDailyAccess();
        $user       = new UserModel();
        $res        = $pospal->getUserInfoPages();

        $i    = 0;
        $j    = 0;
        if($res){
            foreach($res as $v){
                $uid = $pospal->getUidByCustomerUid($v['customerUid']);
                if(!$uid) continue;
                $userInfo = [
                    'user_name'    => empty($v['name']) ? '' : $v['name'],
                    'nick_name'    => empty($v['name']) ? '' : $v['name'],
                    'user_tel'     => empty($v['phone']) ? '' : $v['phone'],
                    'user_email'   => empty($v['email']) ? '' : $v['email'],
                    'user_qq'      => empty($v['qq']) ? '' : $v['qq'],
                    'location'     => empty($v['address']) ? '' : $v['address'],
                ];
                $res = $user->save($userInfo,['uid' => $uid]);
                $j ++;
                if($res > 0){
                    $i ++;
                }
            }

            $data = [
                'success_num'   => $i,
                'count_num'     => $j,
                'api_use_count' => $queryDaily['data'][0]['haveAcessTimes'],
                'api_count'     => $queryDaily['data'][0]['limitTimes'],
                'created'       => time(),
            ];

            \think\Db::name('bc_pospal_user_sync_record')->insert($data);
        }
    }


    public function addUser(){
        $pospal      = new Pospal();
        $user        = new UserModel();

        $condition["user_tel"] = [
            [
                "neq",
                ''
            ]
        ];


        $member_info = $user->getQuery($condition, 'uid,user_tel,sex,real_name,nick_name', '');


        foreach($member_info as $v){
            if(empty($v['user_tel'])) continue;
            $number      = str_pad($v['uid'], 6, "0", STR_PAD_LEFT);
            $userInfo = [
                'categoryName' => '会员卡',
                'number'       => 'BC'.$number,
                'name'         => $v['nick_name'],
                'phone'        => $v['user_tel'],
            ];

            if(!$pospal->getRecordInfo($v['user_tel'])) $pospal->createLoginUser($userInfo,$v['uid'],$v['user_tel']);
        }

    }


    public function test(){
        $pospal      = new Pospal();

        $res = $pospal->queryDailyAccess();
        echo '<pre>';
        var_dump($res);
    }

}