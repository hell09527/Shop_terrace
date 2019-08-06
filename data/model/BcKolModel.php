<?php

namespace data\model;
use data\model\BaseModel as BaseModel;

class BcKolModel extends BaseModel {
    protected $table = 'bc_kol';
    protected $rule = [
        'store_id'  =>  '',
    ];
    protected $msg = [
        'store_id'  =>  '',
    ];
}