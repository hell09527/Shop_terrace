<?php
/**
 * Created by PhpStorm.
 * User: xiantao
 * Date: 2018/8/17
 * Time: 下午4:45
 */

namespace data\model;

class BcShareModel extends BaseModel {
    protected $table = 'bc_share';
    protected $rule = [
        'share_no'  =>  '',
    ];
    protected $msg = [
        'share_no'  =>  '',
    ];
}