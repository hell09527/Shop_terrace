<?php
/**
 * Pospal
 */
namespace app\api\controller;

use data\model\BcPospalGoodsAddRecordModel;
use data\model\BcPospalStockInfoModel;
use data\model\BcPospalUserRecordModel;
use data\service\Member\MemberAccount;
use security\Base;

class Pospal extends BaseController
{

    private $member_account;
    private $user_record;
    private $stock_info;
    private $pospal;
    private $goods_record_member;

    public function __construct()
    {
        parent::__construct();

        $this->member_account      = new MemberAccount();
        $this->user_record         = new BcPospalUserRecordModel();
        $this->stock_info          = new BcPospalStockInfoModel();
        $this->goods_record_member = new BcPospalGoodsAddRecordModel();
        $this->pospal              = new \data\service\Pospal\Pospal();

    }

    # pospal 接收线下单据
    public function pospalPush(){
        $cmd  = request()->post('cmd','');
        $body = request()->post('body','');

        #$cmd  = 'stockFlow.confirmStockFlowIn';
        #$body = '{"stockFlowId":"666"}';


        if(empty($cmd) || empty($body)) return;

        $body_data = json_decode($body, JSON_UNESCAPED_UNICODE);

        switch ($cmd) {
            case "ticket.new":
                #新单据
                $this->pushSaleToOms($body_data,$cmd,$body);         # 推送订单
                $this->setUserPoint($body_data);          # 设置用户积分
                $this->setLog($cmd,$body);                # 添加日志
                break;
            case "ticket.reverse":
                #退款单
                $this->pushRefundToOms($body_data);     # 推送退款单
                $this->cutUserPoint($body_data);        # 扣除积分
                $this->setLog($cmd,$body);              # 添加日志
                break;
            case "stockFlow.createStockFlowIn":
                # 创建订货单 todo....
                break;
            case "stockFlow.editStockFlowIn":
                # 修改订货单 todo...
                $this->editStockFlowIn($cmd,$body);
                break;
            case "stockFlow.confirmStockFlowIn":
                # 已完成订货单
                $this->confirmStockFlowIn($cmd,$body);
                break;
            case "stockFlow.refuseStockFlowIn":
                # 已拒绝订货单
                $this->refuseStockFlowIn($cmd,$body);
                break;
            case "customer.new":
                break;

            case "customer.recharge":

                break;
            case "couponCode.use":


                break;
            case "stockTaking.new":

                $this->stockTaking($cmd,$body);

                break;
            case "product.edit":
                # todo ... 暂时只记录商品更新日志
                $this->setLog($cmd,$body);
                break;

            default:

                break;
        }


    }



    # 添加积分
    private function setUserPoint($body_data){
        # 只接收会员订单
        if(!empty($body_data['customerUid']) && $body_data['customerUid'] !== 0 ){
            $condition['customer_uid'] = $body_data['customerUid'];
            $condition['status']       = 'success';
            $uid                       = $this->user_record->getInfo($condition, 'uid')['uid'];
            $text                      = '门店消费获取积分';
            $shop_id                   = 0;
            $number                    = $body_data['customerPointGaintLogs'][0]['gaintPoint'];
            $this->member_account->addMemberAccountData($shop_id,1,$uid,1,$number,20,'',$text);
        }
    }

    # 扣除积分
    private function cutUserPoint($body_data){
        # 只接收会员订单
        if(!empty($body_data['customerUid']) && $body_data['customerUid'] !== 0 ){
            $condition['customer_uid'] = $body_data['customerUid'];
            $condition['status']       = 'success';
            $uid                       = $this->user_record->getInfo($condition, 'uid')['uid'];
            $text                      = '门店退款扣除积分';
            $shop_id                   = 0;
            $number                    = $body_data['customerPointGaintLogs'][0]['gaintPoint'];
            $this->member_account->addMemberAccountData($shop_id,1,$uid,2,$number,21,'',$text);
        }
    }


    # 推送订单
    private function pushSaleToOms($body_data,$cmd,$body){
        if($body_data['sn']) {
            $res = $this->pospal->queryTicketBySn($body_data['sn']);
            if( $res['data']['ticketType'] == 'SELL' ){
                require(VENDOR_PATH . 'sdk/Client.php');
                $option        = ['token' => 'BC'];  # token 必传固定参数'BC'
                $pospal_client = \pospal\pushCreated::_instance($option);
                $pospal_client->push($body_data['sn']);
            }else if( $res['data']['ticketType'] == 'SELL_RETURN' ){
                # 查询原始单
                $res = $this->pospal->querySellTicketByRefunTicketSn($body_data['sn']);
                $this->pushRefundToOms($res['data']);     # 推送退款单
                $this->cutUserPoint($body_data);          # 扣除积分
                $this->setLog($cmd,$body);                # 添加日志

            }else{
                return;
            }
        }
    }

    # 推送退款单
    private function pushRefundToOms($body_data){
        if($body_data['sn']){
            require(VENDOR_PATH . 'sdk/Client.php');
            $option       = ['token' => 'BC'];  # token 必传固定参数'BC'
            $pospal_client = \pospal\pushRefund::_instance($option);
            $pospal_client->push($body_data['sn']);
        }
    }


    # 添加记录
    private function setLog($cmd,$body){
        $customerUid     = json_decode($body, JSON_UNESCAPED_UNICODE)['customerUid'];
        $data['cmd']     = $cmd;
        $data['body']    = $body;
        $data['type']    = empty($customerUid) || $customerUid == 0 ? 2 : 1;    # 1 会员  2 非会员
        $data['created'] = time();

        \think\Db::name('bc_pospal_ticket_push')->insert($data);
    }


    # 接受入库单
    private function confirmStockFlowIn($cmd,$body){
        $_res = $this->limitRepeatPush($cmd,$body);
        if( !$_res ){
            require(VENDOR_PATH . 'sdk/Client.php');
            $stockFlowId   = json_decode($body, JSON_UNESCAPED_UNICODE)['stockFlowId'];
            $option        = ['token' => 'BC'];          # token 必传固定参数'BC'
            $pospal_client = \pospal\confirmStockFlowIn::_instance($option);
            $res           = $pospal_client->push($stockFlowId);

            if($res == '"ok"'){
                $this->setLog($cmd,$body);                        # 添加日志
                $body_data                  = json_decode($body, JSON_UNESCAPED_UNICODE);
                $data['return_code']        = 1;
                $data['update_time']        = time();
                $data['confirm']            = $res;
                $condition['stock_flow_id'] = $body_data['stockFlowId'];
                $this->stock_info->save($data,$condition);
            }
        }
    }

    # 拒绝入库单
    private function refuseStockFlowIn($cmd,$body){
        $_res = $this->limitRepeatPush($cmd,$body);
        if( !$_res ) {
            require(VENDOR_PATH . 'sdk/Client.php');
            $stockFlowId   = json_decode($body, JSON_UNESCAPED_UNICODE)['stockFlowId'];
            $option        = ['token' => 'BC'];  # token 必传固定参数'BC'
            $pospal_client = \pospal\refuseStockFlowIn::_instance($option);
            $res           = $pospal_client->push($stockFlowId);

            if($res == '"ok"'){
                $this->setLog($cmd,$body);                        # 添加日志
                $body_data                  = json_decode($body, JSON_UNESCAPED_UNICODE);
                $data['return_code']        = 2;
                $data['update_time']        = time();
                $data['confirm']            = $res;
                $condition['stock_flow_id'] = $body_data['stockFlowId'];
                $this->stock_info->save($data, $condition);
            }
        }
    }

    # 修改入库单
    private function editStockFlowIn($cmd,$body){
        $_res = $this->limitRepeatPush($cmd,$body);
        if( !$_res ) {
            $body_data                  = json_decode($body, JSON_UNESCAPED_UNICODE);
            $data['return_code']        = 3;
            $data['update_time']        = time();
            $condition['stock_flow_id'] = $body_data['stockFlowId'];
            $this->stock_info->save($data, $condition);
            $this->setLog($cmd,$body);                        # 添加日志
        }
    }

    # 盘点
    private function stockTaking($cmd,$body){
        return '';

        # todo ......

    }





    # 防止重复推送
    private function limitRepeatPush($cmd,$body){
        $condition['cmd']  = $cmd;
        $condition['body'] = $body;
        $res               = \think\Db::name('bc_pospal_ticket_push')->where($condition)->find();
        return $res;
    }


    # 测试
    public function test(){
        $sn = '201906191437017450004';
        $res = $this->pospal->queryDailyAccess();

        echo '<pre>';
        var_dump($res);exit;
    }




}