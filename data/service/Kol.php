<?php

namespace data\service;
use data\model\BcKolModel;
use data\model\BcKolViewModel;
use data\model\NsOrderModel;


class Kol extends BaseService
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
     * kol列表
     */
    public function getKolUserList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $kol_view = new BcKolViewModel();
        $result = $kol_view->getViewList($page_index, $page_size, $condition, $order);

        $order = new NsOrderModel();
        $time = time()-60*60*24*15;
        foreach($result['data'] as $k => $v){
            $v['wx_info'] = stripslashes(htmlspecialchars_decode($v['wx_info']));
            $condition = ['source_distribution' => $v['id'],'order_status' => 4,'finish_time'=>['ELT',$time]];
            $result['data'][$k]['order_number_count'] = $order->orderNumberCount($condition);  //累计完成订单
            $result['data'][$k]['order_fraction_sum'] = $order->orderFractionSum($condition);  //累计分润金额
        }
        return $result;
    }

    //获取单条kol
    public function getKolInfo($condition, $field)
    {
        $kol_info = new BcKolModel();
        return $kol_info->getInfo($condition, $field);
    }

    public function getKolDetail($kol_id)
    {
        $kol_model = new BcKolModel();
        $kol_detail = $kol_model->get($kol_id);
        return $kol_detail;
    }

    /**
     * kol锁定
     */
    public function kolLock($uid)
    {
        $kol = new BcKolModel();
        $retval = $kol->save([
            'kol_status' => 0
        ], [
            'uid' => $uid
        ]);
        return $retval;
    }

    /**
     * kol解锁
     */
    public function kolUnlock($uid)
    {
        $kol = new BcKolModel();
        $retval = $kol->save([
            'kol_status' => 1
        ], [
            'uid' => $uid
        ]);
        return $retval;
    }


}