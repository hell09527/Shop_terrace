<?php

namespace data\service;
use data\model\BcDistributorWxcodeModel;

class DistributorWxcode extends BaseService
{

    function __construct()
    {
        parent::__construct();
    }

    //极选师小程序码列表
    public function getWxcodeList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $wxcode_model = new BcDistributorWxcodeModel();
        // 查询主表
        $wxcode_list = $wxcode_model->pageQuery($page_index, $page_size, $condition, $order, '*');
        return $wxcode_list;
    }

    //极选师小程序码创建
    public function wxcodeAdd($uid, $name, $code_pic)
    {
        $data = array(
            'uid' => $uid,
            'name' => $name,
            'code_pic' => $code_pic,
            'create_time' => time()
        );
        $wxcode_model = new BcDistributorWxcodeModel();
        $wxcode_model->save($data);
        return $wxcode_model->id;
    }

    //极选师小程序码创建
    public function wxcodeDelete($uid, $id)
    {
        $wxcode_model = new BcDistributorWxcodeModel();
        $condition = array(
            'id' => $id,
            'uid' => $uid
        );
        $wxcode_return = $wxcode_model->destroy($condition);
        if ($wxcode_return > 0) {
            return 1;
        } else {
            return - 1;
        }
    }
}