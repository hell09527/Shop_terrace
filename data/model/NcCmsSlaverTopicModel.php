<?php

namespace data\model;

use data\model\BaseModel as BaseModel;

/**
 * 主题表（从）
 * @author Administrator
 *
 */
class NcCmsSlaverTopicModel extends BaseModel{
    protected $table = 'nc_cms_slaver_topic';
    protected $rule = [
        'topic_id'  =>  '',
        'content'  =>  'no_html_parse',
    ];
    protected $msg = [
        'topic_id'  =>  '',
        'content'  =>  '',
    ];
}