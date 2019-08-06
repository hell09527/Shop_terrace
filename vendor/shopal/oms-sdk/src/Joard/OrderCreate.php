<?php
/**
 * echo "██╗   ██╗███████╗██╗  ██╗ ██████╗ ██████╗  █████╗ ██╗         ";
 * echo "██║   ██║██╔════╝██║  ██║██╔═══██╗██╔══██╗██╔══██╗██║         ";
 * echo "██║   ██║███████╗███████║██║   ██║██████╔╝███████║██║         ";
 * echo "██║   ██║╚════██║██╔══██║██║   ██║██╔═══╝ ██╔══██║██║         ";
 * echo "╚██████╔╝███████║██║  ██║╚██████╔╝██║     ██║  ██║███████╗    ";
 * echo " ╚═════╝ ╚══════╝╚═╝  ╚═╝ ╚═════╝ ╚═╝     ╚═╝  ╚═╝╚══════╝    ";
 * echo "                                                              ";
 * 日期: 2019/7/7
 * 作者: four-li
 */

namespace OMS\Sdk\Joard;


use OMS\Sdk\ClientInterface;

class OrderCreate implements ClientInterface
{
    /** @var string */
    private $tid;

    /** @var string */
    private $seller_nick;

    /** @var integer */
    private $is_delivery = 0;

    /** @var integer */
    private $tx_type = 1;

    /** @var string */
    private $created;

    /** @var string */
    private $pay_time;

    /** @var null|string */
    private $est_con_time;

    /** @var null|string */
    private $consign_time;

    /** @var float */
    private $payment;

    /** @var float */
    private $post_fee = 0.000;

    /** @var float */
    private $discount_fee = 0.000;

    /** @var float */
    private $order_tax_fee = 0.000;

    /** @var array */
    private $promotion_details = [];

    /** @var string */
    private $buyer_nick;

    /** @var string */
    private $buyer_email;

    /** @var string */
    private $buyer_message;

    /** @var string */
    private $seller_memo;

    /** @var integer */
    private $step_trade_status;

    /** @var float */
    private $step_paid_fee = 0.000;

    /** @var string */
    private $receiver_name;

    /** @var string */
    private $receiver_state;

    /** @var string */
    private $receiver_city;

    /** @var string */
    private $receiver_district;

    /** @var string */
    private $receiver_address;

    /** @var integer */
    private $receiver_mobile;

    /** @var string */
    private $receiver_phone;

    /** @var string */
    private $card_no;

    /** @var string */
    private $card_name;

    /** @var string */
    private $pay_type = 1;

    /** @var string */
    private $pay_flow_no;

    /** @var array */
    private $order_items;

    /**
     * @param string $tid
     * @return OrderCreate
     */
    public function setTid(string $tid): OrderCreate
    {
        $this->tid = $tid;
        return $this;
    }

    /**
     * @param string $seller_nick
     * @return OrderCreate
     */
    public function setSellerNick(string $seller_nick): OrderCreate
    {
        $this->seller_nick = $seller_nick;
        return $this;
    }

    /**
     * @param int $is_delivery
     * @return OrderCreate
     */
    public function setIsDelivery(int $is_delivery): OrderCreate
    {
        $this->is_delivery = $is_delivery;
        return $this;
    }

    /**
     * @param int $tx_type
     * @return OrderCreate
     */
    public function setTxType(int $tx_type): OrderCreate
    {
        $this->tx_type = $tx_type;
        return $this;
    }

    /**
     * @param string $created
     * @return OrderCreate
     */
    public function setCreated(string $created): OrderCreate
    {
        $this->created = $created;
        return $this;
    }

    /**
     * @param string $pay_time
     * @return OrderCreate
     */
    public function setPayTime(string $pay_time): OrderCreate
    {
        $this->pay_time = $pay_time;
        return $this;
    }

    /**
     * @param string $est_con_time
     * @return OrderCreate
     */
    public function setEstConTime(string $est_con_time): OrderCreate
    {
        $this->est_con_time = $est_con_time;
        return $this;
    }

    /**
     * @param string $consign_time
     * @return OrderCreate
     */
    public function setConsignTime(string $consign_time): OrderCreate
    {
        $this->consign_time = $consign_time;
        return $this;
    }

    /**
     * @param float $payment
     * @return OrderCreate
     */
    public function setPayment(float $payment): OrderCreate
    {
        $this->payment = $payment;
        return $this;
    }

    /**
     * @param float $post_fee
     * @return OrderCreate
     */
    public function setPostFee(float $post_fee): OrderCreate
    {
        $this->post_fee = $post_fee;
        return $this;
    }

    /**
     * @param float $discount_fee
     * @return OrderCreate
     */
    public function setDiscountFee(float $discount_fee): OrderCreate
    {
        $this->discount_fee = $discount_fee;
        return $this;
    }

    /**
     * @param float $order_tax_fee
     * @return OrderCreate
     */
    public function setOrderTaxFee(float $order_tax_fee): OrderCreate
    {
        $this->order_tax_fee = $order_tax_fee;
        return $this;
    }

    /**
     * @param array $promotion_details
     * @return OrderCreate
     */
    public function setPromotionDetails(array $promotion_details): OrderCreate
    {
        $this->promotion_details = $promotion_details;
        return $this;
    }

    /**
     * @param string $buyer_nick
     * @return OrderCreate
     */
    public function setBuyerNick(string $buyer_nick): OrderCreate
    {
        $this->buyer_nick = $buyer_nick;
        return $this;
    }

    /**
     * @param string $buyer_email
     * @return OrderCreate
     */
    public function setBuyerEmail(string $buyer_email): OrderCreate
    {
        $this->buyer_email = $buyer_email;
        return $this;
    }

    /**
     * @param string $buyer_message
     * @return OrderCreate
     */
    public function setBuyerMessage(string $buyer_message): OrderCreate
    {
        $this->buyer_message = $buyer_message;
        return $this;
    }

    /**
     * @param string $seller_memo
     * @return OrderCreate
     */
    public function setSellerMemo(string $seller_memo): OrderCreate
    {
        $this->seller_memo = $seller_memo;
        return $this;
    }

    /**
     * @param int $step_trade_status
     * @return OrderCreate
     */
    public function setStepTradeStatus(int $step_trade_status): OrderCreate
    {
        $this->step_trade_status = $step_trade_status;
        return $this;
    }

    /**
     * @param float $step_paid_fee
     * @return OrderCreate
     */
    public function setStepPaidFee(float $step_paid_fee): OrderCreate
    {
        $this->step_paid_fee = $step_paid_fee;
        return $this;
    }

    /**
     * @param string $receiver_name
     * @return OrderCreate
     */
    public function setReceiverName(string $receiver_name): OrderCreate
    {
        $this->receiver_name = $receiver_name;
        return $this;
    }

    /**
     * @param string $receiver_state
     * @return OrderCreate
     */
    public function setReceiverState(string $receiver_state): OrderCreate
    {
        $this->receiver_state = $receiver_state;
        return $this;
    }

    /**
     * @param string $receiver_city
     * @return OrderCreate
     */
    public function setReceiverCity(string $receiver_city): OrderCreate
    {
        $this->receiver_city = $receiver_city;
        return $this;
    }

    /**
     * @param string $receiver_district
     * @return OrderCreate
     */
    public function setReceiverDistrict(string $receiver_district): OrderCreate
    {
        $this->receiver_district = $receiver_district;
        return $this;
    }

    /**
     * @param string $receiver_address
     * @return OrderCreate
     */
    public function setReceiverAddress(string $receiver_address): OrderCreate
    {
        $this->receiver_address = $receiver_address;
        return $this;
    }

    /**
     * @param int $receiver_mobile
     * @return OrderCreate
     */
    public function setReceiverMobile(int $receiver_mobile): OrderCreate
    {
        $this->receiver_mobile = $receiver_mobile;
        return $this;
    }

    /**
     * @param string $receiver_phone
     * @return OrderCreate
     */
    public function setReceiverPhone(string $receiver_phone): OrderCreate
    {
        $this->receiver_phone = $receiver_phone;
        return $this;
    }

    /**
     * @param string $card_no
     * @return OrderCreate
     */
    public function setCardNo(string $card_no): OrderCreate
    {
        $this->card_no = $card_no;
        return $this;
    }

    /**
     * @param string $card_name
     * @return OrderCreate
     */
    public function setCardName(string $card_name): OrderCreate
    {
        $this->card_name = $card_name;
        return $this;
    }

    /**
     * @param string $pay_type
     * @return OrderCreate
     */
    public function setPayType(string $pay_type): OrderCreate
    {
        $this->pay_type = $pay_type;
        return $this;
    }

    /**
     * @param string $pay_flow_no
     * @return OrderCreate
     */
    public function setPayFlowNo(string $pay_flow_no): OrderCreate
    {
        $this->pay_flow_no = $pay_flow_no;
        return $this;
    }

    /**
     * @param array $order_items
     * @return OrderCreate
     */
    public function setOrderItems(array $order_items): OrderCreate
    {
        $this->order_items = $order_items;
        return $this;
    }

    public function getApiParameters()
    {
        return [
            'seller_nick'       => $this->seller_nick,
            'tid'               => $this->tid,
            'is_delivery'       => $this->is_delivery,
            'tx_type'           => $this->tx_type,
            'created'           => $this->created,
            'pay_time'          => $this->pay_time,
            'est_con_time'      => $this->est_con_time,
            'consign_time'      => $this->consign_time,
            'payment'           => $this->payment,
            'post_fee'          => $this->post_fee,
            'discount_fee'      => $this->discount_fee,
            'order_tax_fee'     => $this->order_tax_fee,
            'promotion_details' => $this->promotion_details,
            'buyer_nick'        => $this->buyer_nick,
            'buyer_message'     => $this->buyer_message,
            'seller_memo'       => $this->seller_memo,
            'step_trade_status' => $this->step_trade_status,
            'step_paid_fee'     => $this->step_paid_fee,
            'receiver_name'     => $this->receiver_name,
            'receiver_state'    => $this->receiver_state,
            'receiver_city'     => $this->receiver_city,
            'receiver_district' => $this->receiver_district,
            'receiver_address'  => $this->receiver_address,
            'receiver_mobile'   => $this->receiver_mobile,
            'receiver_phone'    => $this->receiver_phone,
            'card_no'           => $this->card_no,
            'card_name'         => $this->card_name,
            'pay_type'          => $this->pay_type,
            'pay_flow_no'       => $this->pay_flow_no,
            'order_items'       => $this->order_items
        ];
    }

    public function getMethod(): string
    {
        return 'post';
    }

    public function getRoute(): string
    {
        return 'api-rest/joard/order.create';
    }

    public function check()
    {
        // TODO: Implement check() method.
        if (!$this->tid) throw (new \Exception('tid错误'));
        if (!$this->seller_nick) throw (new \Exception('seller_nick错误'));
        if (!$this->order_items) throw (new \Exception('items错误'));

        return true;
    }
}
