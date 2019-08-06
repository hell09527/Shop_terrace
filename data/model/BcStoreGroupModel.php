<?php
/**
 * Created by PhpStorm.
 * User: xiantao
 * Date: 2018/7/17
 * Time: 下午1:11
 */

namespace data\model;
use data\model\BaseModel as BaseModel;

class BcStoreGroupModel extends BaseModel {
    protected $table = 'bc_store_group';
    protected $rule = [
        'store_group_id'  =>  '',
    ];
    protected $msg = [
        'store_group_id'  =>  '',
    ];
}