<?php

namespace data\model;

class BcDistributorAccountRecordsModel extends BaseModel
{
    protected $table = 'bc_distributor_account_records';
    protected $rule = [
        'id' => '',
    ];
    protected $msg = [
        'id' => '',
    ];

    //分润数量统计
    public function numberCount($condition)
    {
        $numCount = $this->where($condition)->count();
        return $numCount;
    }

    //分润金额统计
    public function moneySum($condition)
    {
        $moneySum = $this->where($condition)->Sum('money');
        return $moneySum;
    }

    //
    public function distributorSeparationRecordsList($condition, $field ='*')
    {
        $list = $this->field($field)->where($condition)->select();
        return $list;
    }
}