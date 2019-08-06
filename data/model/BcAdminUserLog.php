<?php
namespace data\model;

use data\model\BaseModel as BaseModel;
/**
 * 商品表
 * @author Administrator
 *
 */
class BcAdminUserLog extends BaseModel {

    protected $table = 'bc_admin_user_log';

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
        $viewObj = $this->alias('baul')
            ->join('sys_user_admin sua','sua.uid= baul.uid','left')
            ->field('sua.admin_name, baul.id, baul.uid, baul.name, baul.operate_id, baul.is_success, baul.status, baul.model_url ,baul.create_time ,baul.operate_ip');
        $list = $this->viewPageQuery($viewObj, $page_index, $page_size, $condition, $order);
        return $list;
    }

    /**
     * 设置关联查询返回数据格式
     *
     * @param unknown $list
     *            查询数据列表
     * @param unknown $count
     *            查询数据数量
     * @param unknown $page_size
     *            每页显示条数
     * @return multitype:unknown number
     */
    public function setReturnList($list, $count, $page_size)
    {
        if($page_size == 0)
        {
            $page_count = 1;
        }else{
            if ($count % $page_size == 0) {
                $page_count = $count / $page_size;
            } else {
                $page_count = (int) ($count / $page_size) + 1;
            }
        }
        return array(
            'data' => $list,
            'total_count' => $count,
            'page_count' => $page_count
        );
    }

    /**
     * 获取列表数量
     * @param unknown $condition
     * @return \data\model\unknown
     */
    public function getViewCount($condition)
    {
        $viewObj = $this->alias('baul')
            ->join('sys_user_admin sua','sua.uid= baul.uid','left')
            ->field('baul.uid');
        $count = $this->viewCount($viewObj,$condition);
        return $count;
    }
}