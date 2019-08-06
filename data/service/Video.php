<?php

namespace data\service;
use data\model\BcVideoModel;


class Video extends BaseService
{

    function __construct()
    {
        parent::__construct();
    }

    public function getVideoDetail()
    {
        $video_model = new BcVideoModel();
        $kol_detail = $video_model->getInfo();
        return $kol_detail;
    }

    public function setVideo($video_id, $video_name, $video_address)
    {
        $video_model = new BcVideoModel();
        $data =[
            'video_name'=>$video_name,
            'video_address'=>$video_address
        ];
        if($video_id){
            $retval = $video_model->save($data, ['video_id' => $video_id]);
        }else{
            $retval = $video_model->save($data);
        }
        return $retval;
    }
}