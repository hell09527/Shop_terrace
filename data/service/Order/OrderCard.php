<?php
namespace data\service\Order;

use data\service\BaseService;
use data\model\NsOrderCardModel as NsOrderCardModel;

class OrderCard extends BaseService
{
    private $orderCard;

    function __construct()
    {
        parent::__construct();
        $this->orderCard = new NsOrderCardModel();
    }

    /**
     * 使用心意卷
     */
    public function useCard($order_id, $card_id, $card_token, $card_money)
    {
        $data = array(
            'order_id' => $order_id,
            'card_id' => $card_id,
            'card_token' => $card_token,
            'card_money' => $card_money
        );
        $retval = $this->orderCard->save($data);
        return $retval;
    }

    /**
     * 通过card_token查询一条数据
     */
    public function getCard($where)
    {
        $data = $this->orderCard->get($where);
        return $data;
    }

    /**
     * 删除一条数据
     */
    public function deteleCard($where)
    {
        $res = $this->orderCard->destroy($where);
        return $res;
    }

    /**
     * 修改一条数据
     */
    public function updateCard($data, $where)
    {
        $res = $this->orderCard->save($data,$where);
        return $res;
    }
}