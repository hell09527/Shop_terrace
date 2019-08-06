<?php
/**
 * Created by PhpStorm.
 * User: xiantao
 * Date: 2018/7/17
 * Time: 下午12:56
 */
namespace data\service;
use data\api\IStore;
use data\model\BcStoreGroupModel;
use data\model\BcStoreModel;

class Store extends BaseService implements IStore
{

    function __construct()
    {
        parent::__construct();
    }

    //添加门店组
    public function addStoreGroup($store_group_code, $store_group_name)
    {
        $store_group = new BcStoreGroupModel();
        $data = [
            'store_group_code' => $store_group_code,
            'store_group_name' => $store_group_name
        ];
        $store_group->save($data);
        return $store_group->store_group_id;
    }

    //门店组列表
    public function getStoreGroupList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        $store_group = new BcStoreGroupModel();
        return $store_group->pageQuery($page_index, $page_size, $condition, $order, $field);
    }

    //门店组
    public function getStoreGroups($condition, $field, $order)
    {
        $store_group = new BcStoreGroupModel();
        return $store_group->getQuery($condition, $field, $order);
    }

    //查询单个门店组
    public function getStoreGroupDetail($store_group_id)
    {
        $store_group_model = new BcStoreGroupModel();
        $store_group_detail = $store_group_model->get($store_group_id);
        return $store_group_detail;
    }

    //修改门店组
    public function updateStoreGroup($store_group_id, $store_group_code, $store_group_name)
    {
        $store = new BcStoreGroupModel();
        $data = [
            'store_group_code' => $store_group_code,
            'store_group_name' => $store_group_name
        ];
        $retval = $store->save($data, [
            'store_group_id' => $store_group_id
        ]);
        return $retval;
    }

    //添加门店
    public function addStore($store_group_id, $store_type, $store_code, $store_name, $province_id, $city_id, $district_id, $address, $postalcode, $linkman, $phone_number, $seat_number)
    {
        $store = new BcStoreModel();
        $data = [
            'store_group_id' => $store_group_id,
            'store_type' => $store_type,
            'store_code' => $store_code,
            'store_name' => $store_name,
            'province_id' => $province_id,
            'city_id' => $city_id,
            'district_id' => $district_id,
            'address' => $address,
            'postalcode' => $postalcode,
            'linkman' => $linkman,
            'phone_number' => $phone_number,
            'seat_number' => $seat_number
        ];
        $store->save($data);
        return $store->store_id;
    }

    //门店列表
    public function getStoreList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        $store = new BcStoreModel();
        $list = $store->pageQuery($page_index, $page_size, $condition, $order, $field);
        if (! empty($list)) {
            $address = new Address();
            $store_group = new BcStoreGroupModel();
            $store_type = [1=>"自营", 2=>"加盟", 3=>"合营", 4=>"代理", 99=>"其他"];
            foreach ($list['data'] as $k => $v) {
                $list['data'][$k]['province_name'] = $address->getProvinceName($v['province_id']);
                $list['data'][$k]['city_name'] = $address->getCityName($v['city_id']);
                $list['data'][$k]['dictrict_name'] = $address->getDistrictName($v['district_id']);
                $list['data'][$k]['store_group_name'] = $store_group->getInfo(['store_group_id'=>$v['store_group_id']],'store_group_name')['store_group_name'];
                $list['data'][$k]['store_type_name'] = $store_type[$v['store_type']];
            }
        }
        return $list;
    }

    public function getStoreDetail($store_id)
    {
        $store_model = new BcStoreModel();
        $store_detail = $store_model->get($store_id);
        return $store_detail;
    }

    //门店
    public function getStore($condition, $field, $order)
    {
        $store = new BcStoreModel();
        return $store->getQuery($condition, $field, $order);
    }

    //修改门店
    public function updateStore($id, $store_group_id, $store_type, $store_code, $store_name, $province_id, $city_id, $district_id, $address, $postalcode, $linkman, $phone_number, $seat_number)
    {
        $store = new BcStoreModel();
        $data = [
            'store_group_id' => $store_group_id,
            'store_type' => $store_type,
            'store_code' => $store_code,
            'store_name' => $store_name,
            'province_id' => $province_id,
            'city_id' => $city_id,
            'district_id' => $district_id,
            'address' => $address,
            'postalcode' => $postalcode,
            'linkman' => $linkman,
            'phone_number' => $phone_number,
            'seat_number' => $seat_number
        ];
        $retval = $store->save($data, [
            'store_id' => $id
        ]);
        return $retval;
    }
}