<?php
/**
 * @author : niuteam
 * @date : 2018.3.17.17
 * @version : v1.0.0.0
 */
namespace app\admin\controller;

use data\model\BcStoreModel;
use data\service\Article;
use data\service\Store as StoreService;

/**
 * 商品控制器
 */
class Store extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    //门店组列表
    public function storeGroupList()
    {
        $store = new StoreService();
        if (request()->isAjax()) {
            $page_index = request()->post("page_index", 1);
            $page_size  = request()->post('page_size', PAGESIZE);
            $list       = $store->getStoreGroupList($page_index, $page_size);
            return $list;
        }

        return view($this->style . 'Store/storeGroupList');
    }

    //添加门店组
    public function addStoreGroup()
    {
        $store            = new StoreService();
        $store_group_code = request()->post('store_group_code', '');
        $store_group_name = request()->post('store_group_name', '');
        $retval           = $store->addStoreGroup($store_group_code, $store_group_name);
        return AjaxReturn($retval);
    }

    /**
     * 查询单个门店组
     */
    public function getStoreGroupDetail()
    {
        $store          = new StoreService();
        $store_group_id = request()->post("store_group_id", 0);
        $info           = $store->getStoreGroupDetail($store_group_id);
        return $info;
    }

    /**
     * 修改门店组
     */
    public function updateStoreGroup()
    {
        if (request()->isAjax()) {
            $store            = new StoreService();
            $store_group_id   = request()->post('group_id', '');
            $store_group_code = request()->post('group_code', '');
            $store_group_name = request()->post('group_name', '');
            $res              = $store->updateStoreGroup($store_group_id, $store_group_code, $store_group_name);
            return AjaxReturn($res);
        }
    }

    //门店列表
    public function storeList()
    {
        $store = new StoreService();
        if (request()->isAjax()) {
            $page_index = request()->post("page_index", 1);
            $page_size  = request()->post('page_size', PAGESIZE);
            $list       = $store->getStoreList($page_index, $page_size);
            return $list;
        }
        return view($this->style . 'Store/storeList');
    }

    //添加门店
    public function addStore()
    {
        $store = new StoreService();
        if (request()->isAjax()) {
            $store_group_id = request()->post('store_group_id', '');
            $store_type     = request()->post('store_type', '');
            $store_code     = request()->post('store_code', '');
            $store_name     = request()->post('store_name', '');
            $province_id    = request()->post('province_id', '');
            $city_id        = request()->post('city_id', '');
            $district_id    = request()->post('district_id', '');
            $address        = request()->post('address', '');
            $postalcode     = request()->post('postalcode', '');
            $linkman        = request()->post('linkman', '');
            $phone_number   = request()->post('phone_number', '');
            $seat_number    = request()->post('seat_number', '');
            $retval         = $store->addStore($store_group_id, $store_type, $store_code, $store_name, $province_id, $city_id, $district_id, $address, $postalcode, $linkman, $phone_number, $seat_number);
            return AjaxReturn($retval);
        } else {
            $store_group_list = $store->getStoreGroups([], 'store_group_id, store_group_name', 'store_group_id');
            $this->assign("store_group_list", $store_group_list);
            return view($this->style . 'Store/addStore');
        }
    }

    //修改门店
    public function updateStore()
    {
        $store_id = request()->get('store_id', '');
        $store    = new StoreService();
        if (request()->isAjax()) {
            $id             = request()->post('store_id');
            $store_group_id = request()->post('store_group_id', '');
            $store_type     = request()->post('store_type', '');
            $store_code     = request()->post('store_code', '');
            $store_name     = request()->post('store_name', '');
            $province_id    = request()->post('province_id', '');
            $city_id        = request()->post('city_id', '');
            $district_id    = request()->post('district_id', '');
            $address        = request()->post('address', '');
            $postalcode     = request()->post('postalcode', '');
            $linkman        = request()->post('linkman', '');
            $phone_number   = request()->post('phone_number', '');
            $seat_number    = request()->post('seat_number', '');
            $retval         = $store->updateStore($id, $store_group_id, $store_type, $store_code, $store_name, $province_id, $city_id, $district_id, $address, $postalcode, $linkman, $phone_number, $seat_number);
            return AjaxReturn($retval);
        }
        $store_group_list = $store->getStoreGroups([], 'store_group_id, store_group_name', 'store_group_id');
        $this->assign("store_group_list", $store_group_list);

        $store_detail = $store->getStoreDetail($store_id);
        $this->assign('store_detail', $store_detail);
        $this->assign('store_id', $store_id);
        return view($this->style . "Store/updateStore");
    }

    //生成微信二维码
    public function getWxCode()
    {
        $store_id = request()->post('store_id', 0);
//        $info = \think\Db::name('bc_store')->where(['store_id'=>$store_id])->find();
        $url            = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . 'wxd145d8a6e951dd1b' . "&secret=" . '9e22a3ac6f4c0ccae03a2356e710d68f';
        $res            = $this->send_post($url, '');
        $AccessToken    = json_decode($res, true);
        $AccessToken    = $AccessToken['access_token'];
        $url            = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=" . $AccessToken;
        $post_data      =
            array(
                'scene' => $store_id,
                'page' => 'pages/index/index',
                'width' => 430,
            );
        $post_data      = json_encode($post_data);
        $data           = $this->send_post($url, $post_data);
        $result['url']  = $this->data_uri($data, 'image/png');
        $result['code'] = 1;
        return $result;
    }

    //生成相关页面门店小程序码
    public function getStoreWxCode()
    {
        $store_id        = request()->get('store_id', 0);
        $url             = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . 'wxd145d8a6e951dd1b' . "&secret=" . '9e22a3ac6f4c0ccae03a2356e710d68f';
        $res             = $this->send_post($url, '');
        $AccessToken     = json_decode($res, true);
        $AccessToken     = $AccessToken['access_token'];
        $url             = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=" . $AccessToken;

        //首页门店码
        $post_data_index =
            array(
                'scene' => '0&' . $store_id,
                'page' => 'pages/index/index',
                'width' => 430,
            );
        $post_data_index = json_encode($post_data_index);
        $data_index      = $this->send_post($url, $post_data_index);
        $code_index      = $this->data_uri($data_index, 'image/png');
        $this->assign('code_index', $code_index);

        //手机绑定页门店码
        $post_data_mobile =
            array(
                'scene' => '0&' . $store_id,
                'page' => 'pages/member/updatemobile/updatemobile',
                'width' => 430,
            );
        $post_data_mobile = json_encode($post_data_mobile);
        $data_mobile      = $this->send_post($url, $post_data_mobile);
        $data_mobile      = $this->data_uri($data_mobile, 'image/png');
        $this->assign('data_mobile', $data_mobile);

        return view($this->style . "Store/storeWxCode");
    }

    /**
     * 消息推送http
     * @param $url
     * @param $post_data
     * @return bool|string
     */
    protected function send_post($url, $post_data)
    {
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type:application/json',
                //header 需要设置为 JSON
                'content' => $post_data,
                'timeout' => 60
                //超时时间
            )
        );
        $context = stream_context_create($options);
        $result  = file_get_contents($url, false, $context);
        return $result;
    }

    /**
     * @param $contents
     * @param $mime
     * @return string
     * 二进制转图片image/png
     */
    public function data_uri($contents, $mime)
    {
        $base64 = base64_encode($contents);
        return ('data:' . $mime . ';base64,' . $base64);
    }


    /**
     * @return \think\response\View
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * 门店excel导入
     */
    public function storeImportExcel()
    {
        if (request()->isAjax()) {
//            include 'data/extend/phpexcel_classes/PHPExcel.php';

//            $PHPReader = new \PHPExcel();

            //获取表单上传文件
            $file = request()->post();
            var_dump($file);
            //导入PHPExcel类库，因为PHPExcel没有用命名空间，只能inport导入
            //创建PHPExcel对象，注意，不能少了\
            //如果excel文件后缀名为.xls，导入这个类
            if ($file == 'xls') {
                Vendor('PHPExcel.PHPExcel.Reader.Excel5');
                $PHPReader = new \PHPExcel_Reader_Excel5();
            } else if ($file == 'xlsx') {
                Vendor('PHPExcel.PHPExcel.Reader.Excel2007');
                $PHPReader = new \PHPExcel_Reader_Excel2007();
            }

            //载入文件
            $PHPExcel = $PHPReader->load($file);
            exit;
            //获取表中的第一个工作表，如果要获取第二个，把0改为1，依次类推
            $currentSheet = $PHPExcel->getSheet(0);
            //获取总列数
            $allColumn = $currentSheet->getHighestColumn();
            //获取总行数
            $allRow = $currentSheet->getHighestRow();
            //循环获取表中的数据，$currentRow表示当前行，从哪行开始读取数据，索引值从0开始
            for ($currentRow = 1; $currentRow <= $allRow; $currentRow++) {
                //从哪列开始，A表示第一列
                for ($currentColumn = 'A'; $currentColumn <= $allColumn; $currentColumn++) {
                    //数据坐标
                    $address = $currentColumn . $currentRow;
                    //读取到的数据，保存到数组$data中
                    $cell = $currentSheet->getCell($address)->getValue();

                    if ($cell instanceof PHPExcel_RichText) {
                        $cell = $cell->__toString();
                    }
                    $data[$currentRow - 1][$currentColumn] = $cell;
                    //  print_r($cell);
                }

            }
            var_dump($data);

        }

        return view($this->style . 'Store/storeImportExcel');
    }

    # 门店活动列表
    public function storeActivityList(){
        if (request()->isAjax()) {
            $article     = new Article();
            $store       = new BcStoreModel();
            $page_index  = request()->post('page_index', 1);
            $page_size   = request()->post('page_size', PAGESIZE);
            $search_text = request()->post("search_text", "");
            $retval      = $article->getStoreActivityList($page_index, $page_size, [
                'activity_name' => [
                    "like",
                    "%" . $search_text . "%"
                ]
            ], 'create_time desc');

            foreach ( $retval['data'] as $v ){
                $v['store_name'] = $store->getInfo(['store_id' => $v['store_id']])['store_name'];
            }
            return $retval;
        } else {
            return view($this->style . 'Store/storeActivityList');
        }
    }

    # 门店活动添加
    public function addStoreActivity(){
        if (request()->isAjax()) {
            $data = request()->post();
            $ret['store_id'] = $data['store_id'];
            $ret['activity_name'] = $data['activity_name'];
            $ret['start_time'] = $data['start_time'];
            $ret['end_time'] = $data['end_time'];
            $ret['province_id'] = $data['province_id'];
            $ret['city_id']   = $data['city_id'];
            $ret['district_id'] = $data['district_id'];
            $ret['address']   = $data['address'];
            $ret['cover_pic'] = $data['cover_pic'];
            $ret['extension_pic'] = $data['extension_pic'];
            $ret['is_show'] = $data['is_show'];
            $ret['status'] = 0;

            $article = new Article();
            $id = $article->addStoreMasterTopic($ret);

            $res = $data['items'];
            if ($res) {
                foreach ($res as $v) {
                    unset($v['id']);
                    $v['master_id'] = $id;
                    $article->addStoreSlaverTopic($v);
                }
            }
            return AjaxReturn($id);
        } else {
            $store = new BcStoreModel();
            $store_list = $store->getQuery('1=1','*','store_id desc');
            $this->assign("store_list", $store_list);
            return view($this->style . 'Store/addStoreActivity');
        }
    }

    # 门店活动修改
    public function updateStoreActivity()
    {
        $article = new Article();
        if (request()->isAjax()) {
            $data             = request()->post();
            $ret['id']        = $data['id'];
            $ret['pic']       = $data['pic'];
            $ret['icon_link'] = $data['icon_link'];
            $ret['title']     = $data['title'];
            $ret['sort']      = $data['master_sort'];
            $ret['is_show']   = $data['is_show'];
            $ret['status']    = $data['status'];
            $res              = $data['items'];
            $topic            = $article->updateMasterActivity($ret);
            $ids              = $article->getSlaverActivityIds($ret['id']);
            if ($res) {
                $arr3 = array_merge(array_diff(array_column($res, 'id'), array_column($ids, 'id')), array_diff(array_column($ids, 'id'), array_column($res, 'id')));
            } else {
                $arr3 = array_column($ids, 'id');
            }
            foreach ($arr3 as $v) {
                $article->deleteStoreActivity($v);
            }
            if ($res) {
                foreach ($res as $v) {
                    if ($v['sid']) {
                        $v['master_id'] = $ret['id'];
                        $article->addStoreSlaverTopic($v);
                    } else {
                        $article->updateSlaverActivity($v);
                    }
                }
            }
            return AjaxReturn($topic);
        } else {
            $id            = request()->get('id', '');
            $info          = $article->getMasterActivityDetail($id);
            $info['items'] = $article->getSlaverActivityDetail($info['id']);
            $this->assign('info', $info);
            return view($this->style . 'store/updateStoreActivity');
        }
    }

    # 门店活动删除
    public function deleteStoreActivity()
    {
        if (request()->isAjax()) {
            $topic_id = request()->post('id', '');
            $article  = new Article();
            $res      = $article->deleteStoreActivity($topic_id);
            return AjaxReturn($res);
        }
    }
}