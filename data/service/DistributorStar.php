<?php

namespace data\service;
use data\model\BcDistributorStarModel;

class DistributorStar extends BaseService
{

    function __construct()
    {
        parent::__construct();
    }

    //星级列表
    public function getStarList($page_index, $page_size, $condition, $order, $field)
    {
        $starMOdel = new BcDistributorStarModel();
        $result = $starMOdel->pageQuery($page_index, $page_size, $condition, $order, $field);
        return $result;
    }

    //添加星级
    public function addStar($star_type,$star_grade,$star_name,$star_standard,$star_reward,$star_description)
    {
        $starMOdel = new BcDistributorStarModel();
        $data = [
            'star_type' => $star_type,
            'star_grade' => $star_grade,
            'star_name' => $star_name,
            'star_standard' => $star_standard,
            'star_reward' => $star_reward/100,
            'star_description' => $star_description
        ];
        $starMOdel->save($data);
        return $starMOdel->star_id;
    }

    //单个星级
    public function getStarDetail($star_id)
    {
        $starMOdel = new BcDistributorStarModel();
        $star_detail = $starMOdel->get($star_id);
        return $star_detail;
    }

    //修改星级
    public function updateStar($star_id, $star_type, $star_grade, $star_name, $star_reward, $star_standard, $star_description)
    {
        $starMOdel = new BcDistributorStarModel();
        $data = [
            'star_type' => $star_type,
            'star_grade' => $star_grade,
            'star_name' => $star_name,
            'star_standard' => $star_standard,
            'star_reward' => $star_reward/100,
            'star_description' => $star_description
        ];
        $retval = $starMOdel->save($data, [
            'star_id' => $star_id
        ]);
        return $retval;
    }
}