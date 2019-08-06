<?php
/**
 * Activity
 */
namespace app\api\controller;

use data\model\BcStoreActivityMasterModel;
use data\model\BcStoreActivitySlaverModel;
use data\model\BcStoreAppointmentRecordModel;
use data\service\Article;
use data\model\NsGoodsModel as NsGoodsModel;
use data\service\Events;
use data\service\Goods;
use think\Db;

class Activity extends BaseController
{
    public function activityList()
    {
        $topicService = new Article();
        $list         = $topicService->getMasterTopic();
        return $this->outMessage('活动列表数据', $list);
    }

    public function activityInfo()
    {
        #dev
        $master_id = request()->post('master_id', '0');
        if ($master_id) {
            $topicService = new Article();
            $info         = \think\Db::name('nc_cms_master_topic')->where(['id' => $master_id])->find();
            #新增点击量
            $this->activityClick($master_id);
            $this->userClick($master_id);
            $list['icon_link']  = $info['icon_link'];
            $list['detail_pic'] = $info['detail_pic'];
            $list['title']      = $info['title'];
            $list['data']       = $topicService->getSlaverTopic($master_id);
            $goods              = new NsGoodsModel();
            foreach ($list['data'] as $key => $v) {
                if (is_numeric($v['pic_link'])) {
                    $list['data'][$key]['pic_link'] = intval($v['pic_link']);
                    $goods_id                       = intval($v['pic_link']);
                } elseif (empty(trim($v['pic_link'])) || trim($v['pic_link']) != "会员专区" || trim($v['pic_link']) != "礼品专区" || trim($v['pic_link']) != "主页") {
                    $goods_id = substr(strstr(trim($v['pic_link']), '='), 1);
                    if (strpos($goods_id, '&') !== false) {
                        $goods_id = explode("&", $goods_id);
                        $goods_id = $goods_id[0];
                    }
                } else {
                    $goods_id = 0;
                }
                if (isset($v['pid'])) {
                    $info                             = \think\Db::name('ns_goods')->where(['goods_id' => $v['pid']])->find();
                    $list['data'][$key]['goods_info'] = $info;
                    $pic_info                         = \think\Db::name('sys_album_picture')->where(['pic_id' => $info['picture']])->find();
                    $group                            = \think\Db::name('ns_goods_group')->where(['group_id' => $info['group_id_array']])->select();
                    foreach ($group as $v) {
                        $list['data'][$key]['goods_info']['group_name'] .= $v['group_name'];
                    }
                    $list['data'][$key]['goods_info']['goods_pic'] = $pic_info['pic_cover'];
                }
                if ($goods_id) {
                    $info                                = $goods->getInfo(["goods_id" => $goods_id]);
                    $list['data'][$key]['goods_id']      = $goods_id;
                    $list['data'][$key]['goods_name']    = $info["goods_name"];
                    $list['data'][$key]['source_type']   = $info["source_type"];
                    $list['data'][$key]['material_code'] = $info["material_code"];
                }
            }
            return $this->outMessage('活动详情 列表数据', $list);
        } else {
            return $this->outMessage('参数异常', "", "-1", "无法获取活动详情");
        }

//        #prod
//        $master_id = request()->post('master_id', '0');
//        if($master_id){
//            $topicService = new Article();
//            $list = $topicService->getSlaverTopic($master_id);
//            return $this->outMessage('活动详情 列表数据',$list);
//        }else{
//            return $this->outMessage('参数异常', "","-1","无法获取活动详情");
//        }
    }

    public function runUpLevel()
    {
        $a = new \data\service\Order\Order();
        return $a->upLevel();
    }

    ########     热门话题    #######

    public function hotTopic()
    {
        $limit = request()->post('limit', '0');
        if ($limit == 1) {
            $list['data'] = \think\Db::name('nc_cms_master_topic')->where(['is_show' => 1])->order("sort asc")->limit(1)->find();
            $res          = \think\Db::name('nc_cms_slaver_topic')->where(['master_id' => $list['data']['id']])->select();
            $ids          = [];
            foreach ($res as $v) {
                if (is_numeric($v['content']) && $v['content'] !== '') array_push($ids, $v['content']);
            }
            $ids   = implode(',', $ids);
            $goods = new Goods();

            $goods_list       = $goods->getGoodsQueryLimit([
                'ng.goods_id' => $ids,
                'ng.state' => 1
            ], "ng.goods_id,ng.goods_name,ng_sap.pic_cover_mid,ng.price", 1000);
            $list['pro_list'] = $goods_list;
        } else if ($limit == 3) {
            $list['data'] = $res = \think\Db::name('nc_cms_master_topic')->where(['is_show' => 1])->order("sort asc")->limit(1, 3)->select();
        } else {
            return $this->outMessage('参数异常', "", "-1", "无法获取热门活动数据");
        }
        return $this->outMessage('热门活动数据', $list);
    }

    /**
     * @param $master_id
     * @throws \think\Exception
     * 点击量
     */
    public function activityClick($master_id)
    {
        if (empty($master_id)) return;
        $info  = \think\Db::name('nc_cms_master_topic')->where(['id' => $master_id])->find();
        $click = $info['click'] + 1;
        \think\Db::name('nc_cms_master_topic')->where([
            'id' => $master_id,
        ])->update(['click' => $click]);
    }

    /**
     * @param $master_id
     * @throws \think\Exception
     * 专题页用户访问逻辑
     */
    public function userClick($master_id)
    {
        $record_info = \think\Db::name('bc_click_record')->where(['uid' => $this->uid, 'type' => 1, 'click_id' => $master_id])->order('click_time desc')->limit(1)->find();
        $user_info   = \think\Db::name('sys_user')->where(['uid' => $this->uid])->find();
        $master_info = \think\Db::name('nc_cms_master_topic')->where(['id' => $master_id])->find();
        if (empty($user_info)) return;
        $record_res = [
            'uid' => $this->uid,
            'last_login_ip' => $user_info['last_login_ip'],
            'click_id' => $master_id,
            'type' => 1,
            'click_time' => time(),
        ];
        if (empty($record_info)) {
            \think\Db::name('bc_click_record')->insert($record_res);
            $user_click_num = $master_info['user_click'] + 1;
            \think\Db::name('nc_cms_master_topic')->where([
                'id' => $master_id,
            ])->update(['user_click' => $user_click_num]);
        } else {
            $now_date  = date('Y-m-d');
            $last_date = date('Y-m-d', $record_info['click_time']);
            if ($now_date !== $last_date) {
                $user_click_num = $master_info['user_click'] + 1;
                \think\Db::name('nc_cms_master_topic')->where([
                    'id' => $master_id,
                ])->update(['user_click' => $user_click_num]);
                \think\Db::name('bc_click_record')->insert($record_res);
            }
        }
    }

    # 门店卡券展示
//    public function storeCardShow(){
//        $title  = '门店卡券展示';
//        $status = request()->post('status', '1');
//
//        $store_appointment = new BcStoreAppointmentRecordModel();
//        $store_slaver      = new BcStoreActivitySlaverModel();
//
//        $condition = [
//            'uid'    => $this->uid,
//            'status' => $status
//        ];
//
//        $appointment_lists = $store_appointment->getQuery($condition,'*','appointment_time asc');
//
//        foreach ( $appointment_lists as $v ){
//            $slaver_info     = $store_slaver->getInfo(['id' => $v['appointment_id']]);
//            $v['coupon_pic'] = $slaver_info['coupon_pic'];
//        }
//
//        return $this->outMessage($title, $appointment_lists);
//
//    }

    # 门店卡券展示
    public function storeCardShow(){
        $title  = '门店卡券展示';

        if (empty($this->uid)) {
            return $this->outMessage($title, "", '-50', "无法获取会员登录信息");
        }
        $status = request()->post('status', 1);
        $condition['uid'] = $this->uid;
        $condition['status'] = $status;

        $store_appointment = new BcStoreAppointmentRecordModel();
        $appointment_lists = $store_appointment->getQuery($condition,'*','appointment_time asc');

        foreach ($appointment_lists as $k => $v ){
            $store_slaver      = new BcStoreActivitySlaverModel();
            $slaver_info     = $store_slaver->getInfo(['id' => $v['appointment_id']],'coupon_pic');
            $appointment_lists[$k]['coupon_pic'] = $slaver_info['coupon_pic'];
        }
        return $this->outMessage($title, $appointment_lists);
    }


    # 门店活动列表
    public function storeActivityList(){
        $title = '门店活动列表';
        $topicService = new Article();
        $list = $topicService->storeActivityList();
        return $this->outMessage($title, $list);

    }

    # 门店活动详情
    public function storeActivityDetail(){
        $title = '门店活动详情';
        $master_id = request()->post('master_id', '');
        if( empty($master_id) || empty($this->uid) ) return $this->outMessage($title, "", "-1", "参数异常");
        $topicService = new Article();
        $data = $topicService->storeActivityDetail($master_id,$this->uid);
        return $this->outMessage($title, $data);
    }

    # 门店活动预约
//    public function storeActivityAppointment(){
//        $title          = '预约活动';
//        $appointment_id = request()->post('master_id', '');
//        $uid            = $this->uid;
//        if( empty($appointment_id) || empty($uid) ) return $this->outMessage($title, "", "-1", "参数异常");
//
//        $article         = new Article();
//        $appointment_res = $article->storeActivityAppointment($appointment_id,$uid);
//        if( $appointment_res > 0 ){
//            return $this->outMessage($title,'', $appointment_res,'预约成功');
//        }else if( $appointment_res == '-2' ){
//            return $this->outMessage($title, "", $appointment_res, "已预约 勿重复预约");
//        }else if( $appointment_res == '-3' ){
//            return $this->outMessage($title, "", $appointment_res, "暂无预约名额");
//        }else{
//            return $this->outMessage($title, "", $appointment_res, "预约失败");
//        }
//    }

    # 门店活动预约
    public function storeActivityAppointment(){
        $title = '门店活动预约';
        $master_id = request()->post('master_id', '');
        $name = request()->post('name', '');
        $tel = request()->post('tel', '');
        $remarks = request()->post('remarks', '');
        if( empty($master_id) || empty($this->uid) ) return $this->outMessage($title, "", "-1", "参数异常");

        $article = new Article();
        $appointment_res = $article->storeActivityAppointment($master_id,$this->uid,$name,$tel,$remarks);
        if( $appointment_res > 0 ){
            return $this->outMessage($title, $appointment_res);
        }else if( $appointment_res == '-2' ){
            return $this->outMessage($title, '', '-1', "已预约 请勿重复预约");
        }else{
            return $this->outMessage($title, $appointment_res, '-50', "error");
        }
    }

    # 获取预约活动模版
    public function getStoreActivityAppointmentModel(){

        Db::name('ns_template_push')->insert([
            'open_id'      => request()->post('openid',''),
            'form_id'      => request()->post('formid',''),
            'warn_type'    => 100,
            'out_trade_no' => request()->post('appointment_id',''),
            'is_send'      => 0,
            'created'      => date('Y-m-d H:i:s', time())
        ]);
        return json(['code' => 0, 'msg' => 'success']);
    }
}