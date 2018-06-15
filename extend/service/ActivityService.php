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
        'act_audio' => '',
        'act_name' => '',
        'describe' => '',
        'act_price' => '',
        'act_end' => '',
        'icons_type' => '',
    );

    public static $shareNumInfo = array(
        'product_code' => '',
        'code' => '',
        'name' => '',
        'pic' => '',
        'code' => '',
        'gender' => '',
        'share_valid' => '',
        'update_at' => '',
        'create_at' => '',
    );


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

            return $actArr;
        }

        return '';
    }

    /**
     * 拆分活动描述信息
     * @param string $actCode 产品编码
     * @return bool|string
     */
    public static function createDesArr($describe)
    {
        $desArr = explode('\n', $describe);

        return $desArr;
    }

    /**
     * 获取分享 数量信息
     * @param string $productCode 产品编码
     * @param string $userId 用户ID
     * @return bool|string
     */
    public static function getShareNum($productCode, $code)
    {
        $db_num = Db::name('TUserShareNum');

        $numArr = $db_num->where('product_code', $productCode)
                         ->where('code', $code)
                         ->find();
        if($numArr && ($numArr['product_code'] == $productCode ) ){

            foreach($numArr as $key => $value){
                if( isset(self::$shareNumInfo[$key]) ){
                    self::$shareNumInfo[$key] = $value;
                }
            }
            return $numArr;
        }

        return '';
    }

    /**
     * 获取指定用户分享人数 排名
     * @param string $productCode 产品编码
     * @param string $userId 用户ID
     * @return bool|string
     */
    public static function getUserSharePos($productCode, $code)
    {
        //分享 排名 top3
        $db_num = Db::name('TUserShareNum');
        $shareArr = $db_num->where('product_code', $productCode)
            ->order('share_valid desc, update_at desc')->select();

        $name = '';
        $pos = 0;     //指定用户排名位置
        $curNum = 0;  //指定用户 有效分享人数
        $preNum = 0;  //上一位排名 有效分享人数
        $czNum = 0;  //与上一位排名差

        $preUserInfo = '';  //上一位用户
        foreach($shareArr as $key => $user){
            if($user['code'] == $code){
                //找到指定用户
                $pos = $key+1;
                $name = $user['name'];
                $curNum = $user['share_valid'];
                if($preUserInfo != ''){
                    $preNum = $preUserInfo['share_valid'];
                    $czNum = $preNum - $curNum;   //与上一位排名差
                }
                break;
            }
            //保存为上一位用户
            $preUserInfo = $user;

        }

        if($czNum <= 0){
            $czNum = 0;
        }
        $retInfo = array(
            'name' => $name,
            'pos' => $pos,
            'curNum' => $curNum,
            'czNum' => $czNum,
        );

        return $retInfo;

    }

    /**
     * 获取分享 数量信息
     * @param string $productCode 产品编码
     * @param string $userId 用户ID
     * @param string $isShare 是否为有效分享
     * @param string $name 用户名
     * @param string $pic 用户头像
     * @param string $gender 性别
     * @return bool|string
     */
    public static function addShareNum($productCode, $code, $name, $pic, $gender, $isShare)
    {
        Log::info("addShareNum: start product_code= " . $productCode . ' code=' . $code . ' name=' . $name . ' isShare=' . $isShare);
        //获取更新 有效分享
        $num = 0;      //分享次数
        $validNum = 0; //有效分享次数

        if($isShare == ErrorCode::BABY_SHARE_SUCCESS){
            //有效分享， 都加1次
            $num = 1;
            $validNum = 1;
        }else{
            $num = 1;
        }

        $curNumInfo = self::getShareNum($productCode, $code);
        $curId = isset($curNumInfo['id']) ? $curNumInfo['id'] : '';
        $curCode = isset($curNumInfo['code']) ? $curNumInfo['code'] : '';
        $curShareNum = isset($curNumInfo['share_num']) ? $curNumInfo['share_num'] : 0;
        $curShareValid = isset($curNumInfo['share_valid']) ? $curNumInfo['share_valid'] : 0;

        $logData = json_encode($curNumInfo);
        Log::info("addShareNum: get data " . ' save data=' . $logData );

        if($curCode == $code){
            //分享次数统计，记录已经存在 更新信息

            $data_num = array(
                'id'=> $curId,
                //'product_code'=> $productCode,
                //'code'=> $code,
                'name'=> $name,
                'pic'=> $pic,
                'gender'=> $gender,
                'share_num'=> $curShareNum + $num,
                'share_valid'=> $curShareValid + $validNum,

                'update_at'=> date('Y-m-d H:m:s')    //记录更新时间
            );

        }else{
            $data_num = array(
                'product_code'=> $productCode,
                'code'=> $code,
                'name'=> $name,
                'pic'=> $pic,
                'gender'=> $gender,
                'share_num'=> $num,
                'share_valid'=> $validNum,

                'update_at'=> date('Y-m-d H:m:s')    //记录更新时间
            );
        }
        $logData = json_encode($data_num);
        Log::info("addShareNum: update product_code= " . $productCode . ' code=' . $code . ' save data=' . $logData );

        $db_num = Db::name('TUserShareNum');
        $retBool = DataService::save($db_num, $data_num);   //返回bool 量

        if($retBool){
            Log::info("addShareNum: end ok isShare= " . $isShare . ' product_code=' . $productCode . ' code=' .  $code);
            $result = ErrorCode::CODE_OK;
        }else{
            Log::error("addShareNum: end failed isShare= " . $isShare . ' product_code=' . $productCode . ' code=' .  $code);
            $result = ErrorCode::E_NOT_SUPPORT;
        }

        return $result;

    }

    /**
     * 保存分享记录表 更新分享记录状态
     * @param array $productCode 产品编码 默认为A-00001 应用分享
     * @param array $userAccept 当前被分享者
     * @param array $userFather 分享者
     * @param int $isShare 是否有效分享
     *
     * @return array
     */
   public static function updateShareHis($productCode=ErrorCode::BABY_HEADER_SEQ_APP, $userAccept, $userFather, $isShare)
   {
       Log::info("updateShareHis: start product_code= " . $productCode);
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
           Log::info("updateShareHis: code is empty i_code= " . $i_code . ' i_code_father' . $i_code_father);
           $result = ErrorCode::E_NOT_SUPPORT;
           return $result;
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
               's_num'=> $inNum,
               'update_at'=> date('Y-m-d H:m:s')    //记录更新时间 很重要，用于确定最新的分享者
           );

           $curStatus = $curShareHis['s_status'];
           if($curStatus != ErrorCode::BABY_SHARE_SUCCESS){
               //已经为有效分享，更新为有效分享
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
               's_num'=> 1,       //新建分享记录 计数为 1 次
               'update_at'=> date('Y-m-d H:m:s')    //记录更新时间
           );

       }

       $logData = json_encode($data_share);
       Log::info("updateShareHis: update product_code= " . $productCode .  ' save data=' . $logData );

        $result = DataService::save($db_share_his, $data_share);   //返回bool 量

       if($result){
           Log::info("updateShareHis: end ok isShare= " . $isShare );
           $result = ErrorCode::CODE_OK;

           //增加分享统计 信息
           self::addShareNum($productCode, $i_code_father, $i_name_father, $i_pic_father, $i_gender_father, $isShare);
       }else{
           Log::error("updateShareHis: end failed isShare= " . $isShare );
           $result = ErrorCode::E_NOT_SUPPORT;
       }

       return $result;
   }


    /**
     * 获取分享者记录表信息 根据update_at 字段获取最新的分享者记录
     * @param array $productCode 产品编码 默认为A-00001 应用分享
     * @param array $userCode 当前被分享者code
     * @param int $isShare 是否有效分享
     * @param int $check 是否需要加入isShare 查询 0 默认不限制 1 加入isShare
     * @return array
     */
    public static function getShareHisInfo($productCode=ErrorCode::BABY_HEADER_SEQ_APP, $userCode, $isShare, $check=0){


        $db_share_his = Db::name('TUserShareHis');
        $db_where = $db_share_his->where('product_code', $productCode)
            ->where('code', $userCode)
            ->order('update_at desc');   //获取最新的 分享者记录


        if($check){
            $db_where->where('s_status', $isShare);
        }
        $listShareHis = $db_where->select();   //分享信息  这里有可能有多个分享者记录
        $curShareHis = isset($listShareHis[0]) ? $listShareHis[0] : '';

        if($curShareHis && $curShareHis['code'] == $userCode){
            return $curShareHis;
        }else{
            $curShareHis = '';
        }
        return $curShareHis;
    }

    /**
     * 获取分享者 收徒的列表记录
     * @param array $productCode 产品编码 默认为A-00001 应用分享
     * @param array $userCode 当前分享者code
     * @param int $isShare 是否有效分享
     * @param int $check 是否需要加入isShare 查询 0 默认不限制 1 加入isShare
     * @return array
     */
    public static function getShareHisList($productCode=ErrorCode::BABY_HEADER_SEQ_APP, $userCode, $isShare, $check=0){
        $db_share_his = Db::name('TUserShareHis');
        $db_where = $db_share_his->where('product_code', $productCode)
            ->where('code_father', $userCode)
            ->order('update_at desc');   //获取最新的 分享者记录


        if($check){
            $db_where->where('s_status', $isShare);
        }
        $listShareHis = $db_where->select();   //分享信息  这里有可能有多个分享者记录

        return $listShareHis;
    }
    /**
     * 添加收益记录
     * @param string $userId   用户ID
     * @param int $coinType  金币类型  默认消耗娃娃币
     * @param int $num   金币数量
     * @return bool
     */
    public static function addUserIncome($userId, $proCode, $orderNum, $orderNo, $inValue, $coin, $freeCoin, $reason, $remark, $iStatus=ErrorCode::BABY_INCOME_NEW){
        Log::info("addUserIncome: start user_id= " . $userId);

        $tmpValue = 0;   //收益金额  单位元
        $tmpCoin = 0;    //收益金币  单位金币
        if( $inValue != 0 ){
            // 保证 现金和 金币互斥
            $tmpValue = $inValue;
        }else{
            $tmpCoin = $coin;
        }
        $db_income = Db::name('TUserIncome');
        $data_income = array(
            'user_id' => $userId,
            'order_num' => $orderNum,
            'order_no' => $orderNo,
            'product_code' => $proCode,
            'i_value' => $tmpValue,  //单位元
            'coin' => $tmpCoin,
            'free_coin' => $freeCoin,
            'reason' => $reason,
            'remark' => $remark,
            'i_status' => $iStatus,  //默认为 0 生成收益
            'update_at'=> date('Y-m-d H:m:s')    //记录更新时间
        );

        $jsonData = json_encode($data_income);
        Log::info("addUserIncome: update user income= " . $jsonData );
        $retBool = DataService::save($db_income, $data_income);

        if($retBool){
            Log::info("addUserIncome: end ok user_id= " . $userId );
            $result = ErrorCode::CODE_OK;
        }else{
            Log::error("addUserIncome: end failed user_id= " . $userId );
            $result = ErrorCode::E_NOT_SUPPORT;
        }
        return $result;
    }

    /**
     * 更新收益记录 状态
     * @param string $orderNum   收益编码
     * @param int $iStatus  收益状态
     * @return bool
     */
    public static function updateIncomeStatus($orderNum, $iStatus=ErrorCode::BABY_INCOME_NEW)
    {
        Log::info("updateIncomeStatus: start order_num= " . $orderNum);
        $retBool = false;

        $db_income = Db::name('TUserIncome');
        $incomeInfo = $db_income->where('order_num', $orderNum)->find();
        if($incomeInfo && $incomeInfo['order_num'] == $orderNum){
            //更新状态
            $data_income = array(
                'id' =>  $incomeInfo['id'],
                'i_status'=> $iStatus,
                'update_at'=> date('Y-m-d H:m:s')    //记录更新时间
            );
            $retBool = DataService::save($db_income, $data_income);
        }



        if($retBool){
            Log::info("updateIncomeStatus: end ok i_status= " . $iStatus );
            $result = ErrorCode::CODE_OK;
        }else{
            Log::error("updateIncomeStatus: end failed i_status= " . $iStatus );
            $result = ErrorCode::E_NOT_SUPPORT;
        }

        return $result;

    }

    /**
     * 获取通知的收益记录
     * @param string $curDate   收益时间
     * @param int $iStatus  收益状态
     * @return bool
     */
    public static function getIncomeNotify($startDate, $endDate, $iStatus=ErrorCode::BABY_INCOME_DONE){
        $db_income = Db::name('TUserIncome');
        $incomeList = $db_income->where('i_status', $iStatus)
            ->whereBetween('create_at', [$startDate, $endDate])
            ->select();

        return $incomeList;
    }

    /**
     * 获取房间抓取成功的top10 暂时直接查询的方式
     * @return int
     */
    public static function getTopSucCatch($roomId, $userId)
    {
        $result = ErrorCode::CODE_OK;

        log::info("getTopSucCatch: start roomId= " . $roomId . " userId=" . $userId);
        //获取房间信息
        $roomInfo = RoomService::getRoomInfo($roomId);
        $tmpCreateDate = isset($roomInfo['create_at']) ? $roomInfo['create_at'] : '';

        $db_result = Db::name('TRoomGameResult');


        $db_result->where('create_at', '>', $tmpCreateDate)
            ->where('room_id', $roomId)                           //活动房间
            ->where('result', ErrorCode::BABY_CATCH_SUCCESS)   //抓取成功
            ->where('status', ErrorCode::BABY_POST_OVER)    //不计入兑换的记录
            ->group('user_id')
            ->limit(10);
        $fieldVal = ['COUNT( "user_id") AS tp_count', 'room_id', 'user_id', 'name'];
        $db_result->field($fieldVal)->order('tp_count DESC');

        $topCatchList = $db_result->select();
        $topCount = count($topCatchList);
        log::info("getTopSucCatch: end roomId= " . $roomId . " userId=" . $userId . " topCount=" . $topCount);
        if( $topCount > 0){
            return $topCatchList;
        }else{
            return [];
        }



    }
    /**
     * 获取当前用户房间抓取排名
     * $catchList 为getTopSucCatch 返回的结果数组
     * @return int
     */
    public static function getCatchPosNum($userId, $catchList)
    {
        log::info("getCatchPosNum: start userId= " . $userId);
        $pos = 0;     //指定用户排名位置
        $curNum = 0;  //指定用户 有抓取次数
        $preNum = 0;  //上一位排名 有抓取次数
        $czNum = 0;  //与上一位排名差
        $name = '';
        $preUserInfo = '';  //上一位用户

        //检查是否存在当前用户记录
        foreach($catchList as $key => $user){

            if($userId == $user['user_id']){
                //找到当前用户
                $pos = $key+1;
                $name = $user['name'];
                $curNum = $user['tp_count'];
                if($preUserInfo != ''){
                    $preNum = $preUserInfo['tp_count'];
                    $czNum = $preNum - $curNum;   //与上一位排名差
                }
            }

            //保存为上一位用户
            $preUserInfo = $user;
        }

        $curUserInfo = array(
            'name' => $name,
            'pos' => $pos,
            'curNum' => $curNum,
            'czNum' => $czNum,
        );

        log::info("getCatchPosNum: end userId= " . $userId . " curUserInfo=" . $curUserInfo['name']);
        return $curUserInfo;

    }


}


