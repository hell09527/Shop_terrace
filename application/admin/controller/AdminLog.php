<?php
namespace app\admin\controller;

use data\model\BcAdminUserLog;
use data\service\Promotion as PromotionService;
use data\service\Member;

/**
 * 营销控制器
 *
 * @author Administrator
 *
 */
class AdminLog extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }


    public function logIndex()
    {
        if (request()->isAjax()) {
            $page_index          = request()->post('page_index', 1);
            $page_size           = request()->post('page_size', PAGESIZE);
            $search_text         = request()->post('search_text', '');
            $condition     = array(
                'baul.name|sua.admin_name|baul.operate_ip' => array(
                    'like',
                    '%' . $search_text . '%'
                ),
            );

            $user_log = new BcAdminUserLog();
            $data       = $user_log->getViewList($page_index,$page_size,$condition,'create_time desc');
            return $data;
        } else {
            return view($this->style . "AdminLog/logIndex");
        }
    }

    public function statusUp(){
        $id             = request()->post('id');
        $data['status'] = 0;
        $res = \think\Db::name('bc_admin_user_log')->where(['id' => $id])->update($data);
        return AjaxReturn($res);
    }


}