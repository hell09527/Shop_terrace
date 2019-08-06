<?php
namespace data\model;

use data\model\BaseModel as BaseModel;

class NsBoxModel extends BaseModel {

    protected $table = 'ns_box';
    protected $rule = [
        'box_id'  =>  '',
    ];
    protected $msg = [
        'box_id'  =>  '',
    ];

}