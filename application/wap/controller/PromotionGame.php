<?php
/**
 * Member.php
 * Niushop商城系统 - 团队十年电商经验汇集巨献!
 * =========================================================
 * Copy right 2015-2025 山西牛酷信息科技有限公司, 保留所有权利。
 * ----------------------------------------------
 * 官方网址: http://www.niushop.com.cn
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用。
 * 任何企业和个人不允许对程序代码以任何形式任何目的再发布。
 * =========================================================
 * @author : niuteam
 * @date : 2015.1.17
 * @version : v1.0.0.0
 */
namespace app\wap\controller;

use data\service\Promotion;
use data\service\Member;
/** 
 * 营销活动 小游戏
 *
 * @author Administrator
 *        
 */
class PromotionGame extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 检测用户
     */
    private function checkLogin()
    {
        $uid = $this->uid;
        if (empty($uid)) {
            $redirect = __URL(__URL__ . "/wap/login");
            $this->redirect($redirect); // 用户未登录
        }
        $is_member = $this->user->getSessionUserIsMember();
        if (empty($is_member)) {
            $redirect = __URL(__URL__ . "/wap/login");
            $this->redirect($redirect); // 用户未登录
        }
    }
    
    /**
     * 刮刮乐
     */
    public function scratchMusic(){
        $promotion = new Promotion();
        $game_id = request()->get("gid", ""); 
        
        if(empty($this->uid)){
            $_SESSION['login_pre_url'] = __URL(\think\Config::get('view_replace_str.APP_MAIN') . "/PromotionGame/scratchMusic?gid=".$game_id);
            $this->redirect("Login/index");
        }
        
        //活动信息
        $gameDetail = $promotion -> getPromotionGameDetail($game_id);
        //获取用户账户信息
        $member = new Member();
        $member_info = $member->getMemberDetail($this->instance_id);
        $this->assign("member_info", $member_info);
        
        
        if(empty($gameDetail["game_id"])){
            $this->error("未找到该活动信息！", "member/index");
        }
        
        if($gameDetail["start_time"] > time()){
            $this->error("该活动尚未开始！", "member/index");
        }
        if($gameDetail["end_time"] < time()){
            $this->error("该活动尚未开始！", "member/index");
        }
        
        if($gameDetail["member_level"] != 0){
            if($member_info["member_level"] != $gameDetail["member_level"]){
                $error_message = "对不起,该活动只有".$gameDetail["level_name"]."才可以参与！";
                $this->error($error_message, "member/index");
            }
        }
        
        $this->assign("gameDetail", $gameDetail);
        
        //该活动最新抽奖记录表
        $condition = [
            "game_id" => $game_id,
            "shop_id" => $this->instance_id,
            "is_winning" => 1
        ];
        $winningRecordsList = $promotion -> getPromotionGameWinningRecordsList(1, 15, $condition, "add_time desc", "*");
        $this->assign('WinningRecordsList', $winningRecordsList['data']);
        
        return view($this->style."PromotionGame/scratchMusic");
    }
    
    /**
     * 随机获取奖项
     */
    public function getRandAward(){
        if(request()->isAjax()){
            $promotion = new Promotion();
            $game_id = request()->post("game_id", 0);
            $res = $promotion -> getRandAward($game_id);
            //添加中奖记录
            $result = $promotion -> addPromotionGamesWinningRecords($this->uid, $this->instance_id, $game_id, $res["winning_info"]["rule_id"]);
            if($result["code"] == 0){
                return $res = array(
                    "is_winning" => 0,
                    "no_winning_instruction" => $res["no_winning_instruction"]
                );
            }else if($result["code"] == -1){
                return $res = array(
                    "is_winning" => -1,
                    "message" => $result["message"]
                );
            }
            return $res;
        }
    }
}