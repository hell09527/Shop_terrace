<?php
/**
 * Created by PhpStorm.
 * User: xiantao
 * Date: 2018/7/30
 * Time: 下午2:54
 */

namespace data\model;

class BcDistributorViewModel extends BaseModel {
    protected $table = 'bc_distributor';
    /**
     * 获取列表返回数据格式
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param unknown $order
     * @return unknown
     */
    public function getViewList($page_index, $page_size, $condition, $order){

        $queryList = $this->getViewQuery($page_index, $page_size, $condition, $order);
        $queryCount = $this->getViewCount($condition);
        $list = $this->setReturnList($queryList, $queryCount, $page_size);
        return $list;
    }
    /**
     * 获取列表
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param unknown $order
     * @return \data\model\multitype:number
     */
    public function getViewQuery($page_index, $page_size, $condition, $order)
    {
        //设置查询视图
        $viewObj = $this->alias('bd')
            ->join('ns_member nm','nm.uid= bd.uid','left')
            ->join('sys_user su','bd.uid = su.uid','left')
            ->field('nm.uid, bd.real_name, nm.distributor_type, nm.source_distribution,nm.inviter, nm.source_branch, su.nick_name, su.user_tel, su.wx_info, su.user_headimg, bd.balance, bd.bonus, bd.status, bd.create_time, bd.activation_code');
        $list = $this->viewPageQuery($viewObj, $page_index, $page_size, $condition, $order);
        return $list;
    }
    /**
     * 获取列表数量
     * @param unknown $condition
     * @return \data\model\unknown
     */
    public function getViewCount($condition)
    {
        $viewObj = $this->alias('bd')
            ->join('ns_member nm','nm.uid = bd.uid','left')
            ->join('sys_user su','bd.uid= su.uid','left')
            ->field('bd.uid');
        $count = $this->viewCount($viewObj,$condition);
        return $count;
    }
}