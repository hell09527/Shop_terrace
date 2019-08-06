<?php
/**
 * Created by PhpStorm.
 * User: xiantao
 * Date: 2018/7/30
 * Time: 下午2:54
 */

namespace data\model;

use data\model\BaseModel as BaseModel;

class BcKolViewModel extends BaseModel {
    protected $table = 'bc_kol';
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
        $viewObj = $this->alias('bk')
            ->join('ns_member nm','nm.uid= bk.uid','left')
            ->join('sys_user su','bk.uid = su.uid','left')
            ->field('nm.uid,  nm.is_kol, su.nick_name, su.user_tel, su.user_email, su.wx_info, bk.id, bk.kol_status, bk.kol_code');
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
        $viewObj = $this->alias('bk')
            ->join('ns_member nm','nm.uid = bk.uid','left')
            ->join('sys_user su','bk.uid= su.uid','left')
            ->field('bk.uid');
        $count = $this->viewCount($viewObj,$condition);
        return $count;
    }
}