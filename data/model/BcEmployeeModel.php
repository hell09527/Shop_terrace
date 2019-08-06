<?php

namespace data\model;

class BcEmployeeModel extends BaseModel {
    protected $table = 'bc_employee';

    /**
     * 获取列表返回数据格式
     */
    public function getViewList($page_index, $page_size, $condition, $order){
    
        $queryList = $this->getViewQuery($page_index, $page_size, $condition, $order);
        $queryCount = $this->getViewCount($condition);
        $list = $this->setReturnList($queryList, $queryCount, $page_size);
        return $list;
    }

    /**
     * 获取列表
     */
    public function getViewQuery($page_index, $page_size, $condition, $order)
    {
        $viewObj = $this->field('*');
        $list = $this->viewPageQuery($viewObj, $page_index, $page_size, $condition, $order);
        return $list;
    }

    /**
     * 获取列表数量
     */
    public function getViewCount($condition)
    {
        $viewObj = $this->field('id');
        $count = $this->viewCount($viewObj,$condition);
        return $count;
    }
}
