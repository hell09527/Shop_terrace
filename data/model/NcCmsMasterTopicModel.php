<?php

namespace data\model;

use data\model\BaseModel as BaseModel;

/**
 * 主题表（主）
 * @author Administrator
 *
 */
class NcCmsMasterTopicModel extends BaseModel{
    protected $table = 'nc_cms_master_topic';
    protected $rule = [
        'topic_id'  =>  '',
        'content'  =>  'no_html_parse',
    ];
    protected $msg = [
        'topic_id'  =>  '',
        'content'  =>  '',
    ];
}