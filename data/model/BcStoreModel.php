<?php
/**
 * Created by PhpStorm.
 * User: xiantao
 * Date: 2018/7/17
 * Time: 下午8:25
 */

namespace data\model;
use data\model\BaseModel as BaseModel;

class BcStoreModel extends BaseModel {
    protected $table = 'bc_store';
    protected $rule = [
        'store_id'  =>  '',
    ];
    protected $msg = [
        'store_id'  =>  '',
    ];
}