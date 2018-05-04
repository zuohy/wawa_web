<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2016~2018 贵州华宇信息科技有限公司 [  ]
// +----------------------------------------------------------------------
// | 官方网站:
// +----------------------------------------------------------------------
// | 开源协议 ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | github开源项目：
// +----------------------------------------------------------------------

namespace service;

use think\Log;
use think\Db;
use service\ErrorCode;
/**
 * 分享 活动管理服务
 * Class ActivityService
 * @package service
 * @author zuohy <zhywork@163.com>
 * @date 2018/03/10 15:32
 */
class ActivityService
{
    public static $actInfo = array(
        'act_code' => '',
        'act_pic_show' => '',
        'act_pic_1' => '',
        'act_pic_2' => '',
        'act_pic_3' => '',
        'act_form_1' => '',
        'act_form_2' => '',
        'act_name' => '',
        'describe' => '',
        'act_price' => '',
        'act_end' => '',
        'icons_type' => '',
    );

    public static $shareNumInfo = array(
        'product_code' => '',
        'user_id' => '',
        'share_num' => '',
        'share_valid' => '',
        'update_at' => '',
    );
    public static $memberInfo = array(    //当前成员信息
        'room_id' => '',
        'user_id' => '',
        'name' => '',
        'pic' => '',
        'user_status' => '',
        'v_user_type' => '',
        'v_client_type' => '',
        'c_client_id' => '',
    );
    public static $memberList = array();


    /**
     * 获取活动信息
     * @param string $actCode 产品编码
     * @return bool|string
     */
    public static function getActInfo($actCode)
    {
        $db_act = Db::name('TActConfig');

        $actArr = $db_act->where('act_code', $actCode)
            ->find();
        if($actArr && ($actArr['act_code'] == $actCode ) ){

            foreach($actArr as $key => $value){
                if( isset(self::$actInfo[$key]) ){
                    self::$actInfo[$key] = $value;
                }
            }
        }

        return self::$actInfo;
    }

    /**
     * 获取分享 数量信息
     * @param string $productCode 产品编码
     * @param string $userId 用户ID
     * @return bool|string
     */
    public static function getShareNum($productCode, $userId)
    {
        $db_num = Db::name('TUserShareNum');

        $numArr = $db_num->where('product_code', $productCode)
                         ->where('user_id', $userId)
                         ->find();
        if($numArr && ($numArr['product_code'] == $productCode ) ){

            foreach($numArr as $key => $value){
                if( isset(self::$shareNumInfo[$key]) ){
                    self::$shareNumInfo[$key] = $value;
                }
            }
        }

        return self::$shareNumInfo;
    }


    /**
     * 保存分享记录表
     * @param array $productCode 产品编码 默认为A-00001 应用分享
     * @param array $userAccept 当前被分享者
     * @param array $userFather 分享者
     * @param int $isShare 是否有效分享
     *
     * @return array
     */
   private function _saveShareHis($productCode=ErrorCode::BABY_HEADER_SEQ_APP, $userAccept, $userFather, $isShare)
   {
        //保存分享记录表
        $db_share_his = Db::name('TUserShareHis');

        $i_code = isset($userAccept['code']) ? $userAccept['code'] : '';
        $i_name = isset($userAccept['name']) ? $userAccept['name'] : '';
        $i_pic = isset($userAccept['pic']) ? $userAccept['pic'] : '';
        $i_gender = isset($userAccept['gender']) ? $userAccept['gender'] : '';

        $i_code_father = isset($userFather['code']) ? $userFather['code'] : '';
        $i_name_father = isset($userFather['name']) ? $userFather['name'] : '';
        $i_pic_father = isset($userFather['pic']) ? $userFather['pic'] : '';
        $i_gender_father = isset($userFather['gender']) ? $userFather['gender'] : '';

       //异常判断
       if($i_code == '' || $i_code_father == ''){
           Log::info("_saveShareHis: code is empty i_code= " . $i_code . ' i_code_father' . $i_code_father);
           return;
       }
       //查找分享记录 是否已经存在
       $curShareHis = $db_share_his->where('product_code', $productCode)
           ->where('code', $i_code)
           ->where('code_father', $i_code_father)
           ->find();   //分享信息
       if($curShareHis && $curShareHis['code'] == $i_code){
           //存在分享信息，更新状态 增加分享计数
           $inNum = $curShareHis['s_num'] + 1;
           $data_share = array(
               'id' =>  $curShareHis['id'],
               's_num'=> $inNum
           );

           $curStatus = $curShareHis['s_status'];
           if($curStatus != ErrorCode::BABY_SHARE_SUCCESS){
               //已经为有效分享，只增加分享计数
               $shareStatus = array(
                   's_status'=> $isShare
               );
               $data_share = array_merge($data_share, $shareStatus);
           }

       }else{
           $data_share = array(
               'product_code'=> $productCode,
               'code'=> $i_code,
               'name'=> $i_name,
               'pic'=> $i_pic,
               'gender'=> $i_gender,

               'code_father'=> $i_code_father,
               'name_father'=> $i_name_father,
               'pic_father'=> $i_pic_father,
               'gender_father'=> $i_gender_father,
               's_status'=> $isShare,
               's_num'=> 1);     //新建分享记录 计数为 1 次
       }


        $result = DataService::save($db_share_his, $data_share);

       if($result){
           Log::info("_saveShareHis: end ok isShare= " . $isShare );
           $result = ErrorCode::CODE_OK;
       }else{
           Log::error("_saveShareHis: end failed isShare= " . $isShare );
           $result = ErrorCode::E_NOT_SUPPORT;
       }

       return $result;
   }





}


