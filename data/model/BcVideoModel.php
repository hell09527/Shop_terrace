<?php

namespace data\model;

class BcVideoModel extends BaseModel {
    protected $table = 'bc_video';
    protected $rule = [
        'video_id'  =>  '',
    ];
    protected $msg = [
        'video_id'  =>  '',
    ];
}