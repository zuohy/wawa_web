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
use GatewayClient\Gateway;
/**
 * 房间管理服务
 * Class RoomService
 * @package service
 * @author zuohy <zhywork@163.com>
 * @date 2018/03/10 15:32
 */
class RoomService
{
    public static $roomInfo = array(
        'room_id' => '',
        'topic' => '',
        'status' => '',
        'tag' => '',
        'price' => '',
        'room_pic' => '',
        'gift_id' => '',
        'room_audio' => '',
        'game_audio' => '',
        'move_audio' => '',
        'catch_audio' => '',
        'success_audio' => '',
        'failed_audio' => '',
        'member_count' => '0',   //房间成员人数
        'create_at' => '',
    );
    public static $memberInfo = array(    //当前成员信息
        'room_id' => '',
        'dev_room_id' => '',
        'user_id' => '',
        'name' => '',
        'pic' => '',
        'user_status' => '',
        'v_user_type' => '',
        'v_client_type' => '',
        'c_client_id' => '',
    );
    public static $curMemberCount = 0;   //当前房间人数
    public static $memberList = array();
    public static $giftInfo = array(
        'gift_id' => '',
        'gift_pic_show' => '',
        'gift_pic_1' => '',
        'gift_pic_2' => '',
        'gift_pic_3' => '',
        'gift_pic_4' => '',
        'gift_pic_5' => '',
        'gift_name' => '',
        'describe' => '',
        'gift_price' => '',
    );

    /**
     * 获取房间信息
     * @param string $roomId 房间ID
     * @param string $userId 当前用户ID
     * @param string $clientId 当前用户长连接 workman 的客户ID
     * @return bool|string
     */
    public static function runRoom($roomId, $userId, $clientId, $devRoomId){

        $ret = self::joinRoom($roomId, $userId, $clientId, $devRoomId);
        $tmpRoom = self::getRoomInfo($roomId);
        if( !empty($tmpRoom) ){
            self::getGiftInfo($tmpRoom['gift_id']);
        }

        return $ret;
    }


    /**
     * 获取房间信息
     * @param string $roomId 房间ID
     * @return bool|string
     */
    public static function getRoomInfo($roomId)
    {
        $db_room = Db::name('TRoomInfo');

        $roomArr = $db_room->where('room_id', $roomId)->find();
        if($roomArr && ($roomArr['room_id'] == $roomId ) ){

            foreach($roomArr as $key => $value){
                if( isset(self::$roomInfo[$key]) ){
                    self::$roomInfo[$key] = $value;
                }
            }
        }

        return self::$roomInfo;
    }

    /**
     * 更新房间状态 房间成员状态
     * @param string $roomId 房间ID
     * @param arrary $roomInfo
     * @return int
     */
    public static function updateRoomInfo($roomId, $roomInfo){
        Log::info("updateRoomInfo: start room_id= " . $roomId);

        $db_room = Db::name('TRoomInfo');
        $data_room = array();
        $result = ErrorCode::E_ROOM_UPDATE_FAIL;

        $roomArr = $db_room->where('room_id', $roomId)->find();
        if($roomArr && ($roomArr['room_id'] == $roomId ) ){
            $data_room['id'] = $roomArr['id'];

            foreach($roomArr as $key => $value){
                if( isset($roomInfo[$key]) ){
                    $data_room[$key] = $roomInfo[$key];
                }
            }
            $jsonData = json_encode($data_room);
            Log::info("updateRoomInfo: update roomInfo= " . $jsonData );
            $result = DataService::save($db_room, $data_room);

        }

        if($result){
            Log::info("updateRoomInfo: end ok room_id= " . $roomId );
            $result = ErrorCode::CODE_OK;
        }else{
            Log::error("updateRoomInfo: end failed room_id= " . $roomId );
            $result = ErrorCode::E_ROOM_UPDATE_FAIL;
        }

        return $result;

    }

    /**
     * 更新房间在线人数
     * @param string $roomId 房间ID
     * @param int $type  进入房间或退出房间
     * @return int
     */
    public static function updateRoomNum($roomId, $type){
        Log::info("updateRoomNum: start room_id= " . $roomId . " status=" . $type);
        $result = ErrorCode::CODE_OK;

        if($type == ErrorCode::BABY_ROOM_MEMBER_STATUS_OUT
        || $type == ErrorCode::BABY_ROOM_MEMBER_STATUS_IN){
            //update status
        }else{
            Log::info("updateRoomNum: not need handle room_id= " . $roomId  . " status=" . $type);
            return $result;
        }
        //更新房间成员人数
        $db_member = Db::name('TRoomMemberInfo');
        $memberOnline = $db_member->where('room_id', $roomId)
            ->where('user_status', ErrorCode::BABY_ROOM_MEMBER_STATUS_IN)
            ->count();

        //获取随机数 作为房间人数的基数
        if( ($memberOnline <= 1) && ($type == ErrorCode::BABY_ROOM_MEMBER_STATUS_IN) ){
            //由于是先更新成员状态，再更新在线人数，所以最小有一个围观用户
            //房间人数为0 时，生成基础人数
            $baseNum = rand(50, 160);
        }else{
            $baseNum = 0;
        }
        $roomInfo = self::getRoomInfo($roomId);
        self::$curMemberCount =  isset($roomInfo['member_count']) ? $roomInfo['member_count'] : 0;

        Log::info("updateRoomNum: baseNum= " . $baseNum . " curMemberCount=" . self::$curMemberCount);

        if($type == ErrorCode::BABY_ROOM_MEMBER_STATUS_OUT){
            //退出房间
            self::$curMemberCount = self::$curMemberCount  - 1; //增加成员计数
            $roomData['member_count'] = self::$curMemberCount;

        }elseif($type == ErrorCode::BABY_ROOM_MEMBER_STATUS_IN){
            //加入房间
            self::$curMemberCount = $baseNum + self::$curMemberCount  + 1; //减少成员计数
            $roomData['member_count'] = self::$curMemberCount;

        }

        //获取成员人数
        if( ($memberOnline <= ErrorCode::CODE_OK) && ($type == ErrorCode::BABY_ROOM_MEMBER_STATUS_OUT) ){
            //全部退出房间
            self::$curMemberCount = ErrorCode::CODE_OK; //清空成员计数
            $roomData['member_count'] = self::$curMemberCount;
        }

        Log::info("updateRoomNum: member_count= " . $roomData['member_count'] . " memberOnline=" . $memberOnline);
        $result = self::updateRoomInfo($roomId, $roomData);

        if($result == ErrorCode::CODE_OK){
            Log::info("updateRoomNum: end ok room_id= " . $roomId );
            $result = ErrorCode::CODE_OK;
        }else{
            Log::error("updateRoomNum: end failed room_id= " . $roomId );
            $result = ErrorCode::E_ROOM_UPDATE_FAIL;
        }
        return $result;
    }

    /**
     * 获取房间成员信息
     * @param string $roomId 房间ID
     * @return bool|string
     */
    public static function getMemberInfo($roomId, $userId){
        $db_member = Db::name('TRoomMemberInfo');

        $memberArr = $db_member->where('room_id', $roomId)->where('user_id', $userId)->find();
        if($memberArr && ($memberArr['user_id'] == $userId ) ){

            foreach($memberArr as $key => $value){
                if( isset(self::$memberInfo[$key]) ){
                    self::$memberInfo[$key] = $value;
                }
            }
        }

        return self::$memberInfo;
    }

    /**
     * 更新房间成员信息 房间成员状态
     * @param string $roomId 房间ID
     * @param arrary $roomInfo
     * @return int
     */
    public static function updateMemberInfo($roomId, $userId, $memberInfo){
        Log::info("updateMemberInfo: start room_id= " . $roomId . ' user_id=' . $userId);

        $db_member = Db::name('TRoomMemberInfo');
        $data_member = array();
        $result = ErrorCode::E_ROOM_UPDATE_FAIL;

        $memberArr = $db_member->where('user_id', $userId)->find();
        if($memberArr && ($memberArr['user_id'] == $userId ) ){
            $data_member['id'] = $memberArr['id'];

            foreach($memberArr as $key => $value){
                if( isset($memberInfo[$key]) ){
                    $data_member[$key] = $memberInfo[$key];
                }
            }
            $jsonData = json_encode($data_member);
            Log::info("updateMemberInfo: update memberInfo= " . $jsonData );
            $result = DataService::save($db_member, $data_member);

        }

        if($result){
            Log::info("updateMemberInfo: end ok user_id= " . $userId );
            $result = ErrorCode::CODE_OK;
        }else{
            Log::error("updateMemberInfo: end failed user_id= " . $userId );
            $result = ErrorCode::E_ROOM_UPDATE_FAIL;
        }

        return $result;

    }


    /**
     * 获取房间信息
     * @param string $roomId 房间ID
     * @param string $userId 当前成员ID
     * @param string $type 命令参数类型
     * @return bool|string
     */
    public static function getTopMemberList($roomId, $userId)
    {
        $db_member = Db::name('TRoomMemberInfo');

        $memberArr = $db_member->where('room_id', $roomId)->where('user_status>'. ErrorCode::BABY_ROOM_MEMBER_STATUS_OUT)
            ->order('user_status desc')              //正在游戏的成员排在第一位
            ->order('update_at desc')                //按最晚进入房间排序
            ->select();
        if($memberArr){

            foreach($memberArr as $key => $user){
                if($key >= 4){
                    //只需要最新的3条记录
                    break;
                }
                $memKey = $user['user_id'];
                self::$memberList[$memKey] = $memberArr[$key];

            }
        }

        //获取当前成员信息
        if($userId){
            $memberInfo = $db_member->where('room_id', $roomId)->where('user_status>'. ErrorCode::BABY_ROOM_MEMBER_STATUS_OUT)
                ->where('user_id', $userId)
                ->find();
            $isFound = false;
            foreach(self::$memberList as $key => $user){
                if($userId == $user['user_id']){
                    //当前用户已经在成员列表 不需要处理
                    $isFound = true;
                    break;
                }
            }
            if($isFound != true && !empty($memberInfo) ){
                //增加当前用户进入top 成员列表
                self::$memberList[$userId] = $memberInfo;
            }

        }
        return self::$memberList;
    }

    /**
     * 获取商品信息
     * @param string $roomId 房间ID
     * @return bool|string
     */
    public static function getGiftInfo($giftId)
    {
        $db_gift = Db::name('TGiftConfig');
        $giftArr = $db_gift->where('gift_id', $giftId)->find();
        if($giftArr && ($giftArr['gift_id'] == $giftId ) ){

            foreach($giftArr as $key => $value){
                if( isset(self::$giftInfo[$key]) ){
                    self::$giftInfo[$key] = $value;
                }
            }
        }

        return self::$giftInfo;
    }

    /**
     * 加入房间 更新房间成员信息
     * @param string $roomId 房间ID
     * @param string $userId 房间ID
     * @param string $clientId 当前用户长连接 workman 的客户ID
     * @param string $devRoomId 房间设备ID
     * @return int
     */
    public static function joinRoom($roomId, $userId, $clientId, $devRoomId)
    {
        //获取用户信息
        $db_user = Db::name('TUserConfig');
        $userInfo = $db_user->where('user_id', $userId)->find();
        if($userInfo && ($userInfo['user_id'] == $userId ) ){

        }else{
            Log::error("joinRoom: user not found room_id= " . $roomId . ' user_id= ' . $userId);
            return ErrorCode::E_USER_NOT_FOUND;
        }

        $data_member = array('room_id'=> $roomId,
            'dev_room_id'=> $devRoomId,
            'user_id'=> $userId,
            'name'=> $userInfo['name'],
            'pic'=> $userInfo['pic'],
            'user_status'=> ErrorCode::BABY_ROOM_MEMBER_STATUS_IN,
            'c_client_id' => $clientId,
            'update_at'=> date('Y-m-d H:m:s')
        );

        $db_member = Db::name('TRoomMemberInfo');
        $memberArr = $db_member->where('user_id', $userId)->find();
        if($memberArr && ($memberArr['user_id'] == $userId ) ){
            //找到用户信息 查看是否更换房间
            if($roomId != $memberArr['room_id']){
                //更换房间
                if(ErrorCode::BABY_ROOM_MEMBER_STATUS_RUN == $memberArr['user_status']){
                    //异常情况 记录log
                    Log::error("joinRoom: user is gaming status= " . ErrorCode::BABY_ROOM_MEMBER_STATUS_RUN. ' user_id= ' . $userId . ' room_id= ' . $roomId);
                }
                Log::info("joinRoom: user change room from room_id= " . $memberArr['room_id'] . ' to room_id= ' . $roomId);
            }
            //更新成员信息
            $data_member['id'] = $memberArr['id'];
            unset($data_member['user_id']);

        }else{
            //第一次加入房间
        }

        //更新成员表
        $result = DataService::save($db_member, $data_member);

        //更新top成员列表
        self::getTopMemberList($roomId, $userId);

        //更新房间成员人数
        self::updateRoomNum($roomId, ErrorCode::BABY_ROOM_MEMBER_STATUS_IN);
/*        self::$curMemberCount = $db_member->where('room_id', $roomId)->count();
        //获取随机数 作为房间人数的基数
        if(self::$curMemberCount == ErrorCode::CODE_OK){
            //房间人数为0 时，生成基础人数
            $baseNum = rand(50, 160);
        }else{
            $baseNum = 0;
        }
        self::$curMemberCount = self::$curMemberCount  + 1 + $baseNum; //增加成员计数
        $roomData['member_count'] = self::$curMemberCount;
        self::updateRoomInfo($roomId, $roomData);
*/
        if($result){
            Log::info("joinRoom: end ok room_id= " . $roomId . ' user_id= ' . $userId);
            $result = ErrorCode::CODE_OK;
        }else{
            Log::error("joinRoom: end failed room_id= " . $roomId . ' user_id= ' . $userId);
            $result = ErrorCode::E_USER_JOIN_ROOM_FAIL;
        }

        return $result;
    }

    /**
     * 获取设备信息
     * @param string $roomId 房间ID
     * @return bool|string
     */
    public static function getDeviceInfo($roomId)
    {
        $db_device = Db::name('TRoomInfo');

        return self::$roomInfo;
    }

    /**
     * 更新 房间成员状态
     * @param string $roomId 房间ID
     * @param string $userId 房间成员ID
     * @param int $status 房间成员状态
     * @return int
     */
    public static function updateMemberStatus($roomId, $userId, $memberStatus){
        Log::info("updateMemberStatus: start room_id= " . $roomId . ' user_id= ' . $userId . ' memberStatus=' . $memberStatus);

        if( ErrorCode::BABY_ROOM_MEMBER_STATUS_IN == $memberStatus ){
            $memberInfo['user_status'] = ErrorCode::BABY_ROOM_MEMBER_STATUS_IN;
        }elseif ( ErrorCode::BABY_ROOM_MEMBER_STATUS_RUN == $memberStatus ){
            $memberInfo['user_status'] = ErrorCode::BABY_ROOM_MEMBER_STATUS_RUN;
        }elseif ( ErrorCode::BABY_ROOM_MEMBER_STATUS_OUT == $memberStatus ){
            $memberInfo['user_status'] = ErrorCode::BABY_ROOM_MEMBER_STATUS_OUT;
        }else{
            Log::error("updateMemberStatus: end failed not support status= " . $memberStatus . ' room_id' . $roomId . ' user_id= ' . $userId);
            $result = ErrorCode::E_ROOM_USER_STATUS_ERROR;
            return $result;
        }

        //更新房间成员状态
        $result = self::updateMemberInfo($roomId, $userId, $memberInfo);
        if($result != ErrorCode::CODE_OK){
            Log::error("updateMemberStatus: end failed room_id= " . $roomId . ' user_id= ' . $userId);
            $result = ErrorCode::E_ROOM_USER_STATUS_ERROR;
            return $result;
        }

        //更新房间在线人数
        self::updateRoomNum($roomId, $memberStatus);

        if($result == ErrorCode::CODE_OK){
            Log::info("updateMemberStatus: end ok room_id= " . $roomId . ' user_id= ' . $userId);
            $result = ErrorCode::CODE_OK;
        }else{
            Log::error("updateMemberStatus: end failed room_id= " . $roomId . ' user_id= ' . $userId);
            $result = ErrorCode::E_ROOM_USER_STATUS_ERROR;
        }

        return $result;

    }

    /**
     * 更新房间状态 房间成员状态
     * @param string $roomId 房间ID
     * @param string $userId 房间ID
     * @param int $status 房间状态
     * @return int
     */
    public static function updateGameStatus($roomId, $userId, $roomStatus){
        Log::info("updateGameStatus: start room_id= " . $roomId . ' user_id= ' . $userId . ' roomStatus=' . $roomStatus);

        if( ErrorCode::BABY_ROOM_STATUS_ON == $roomStatus ){
            $roomInfo['status'] = ErrorCode::BABY_ROOM_STATUS_ON;
            $memberInfo['user_status'] = ErrorCode::BABY_ROOM_MEMBER_STATUS_IN;
        }elseif ( ErrorCode::BABY_ROOM_STATUS_BUSY == $roomStatus ){
            $roomInfo['status'] = ErrorCode::BABY_ROOM_STATUS_BUSY;
            $memberInfo['user_status'] = ErrorCode::BABY_ROOM_MEMBER_STATUS_RUN;
        }else{
            Log::error("updateGameStatus: end failed not support status= " . $roomStatus . ' room_id' . $roomId . ' user_id= ' . $userId);
            $result = ErrorCode::E_ROOM_STATUS_UPDATE_ERROR;
            return $result;
        }

        //更新房间状态 成员状态
        $result = self::updateRoomInfo($roomId, $roomInfo);
        if($result != ErrorCode::CODE_OK){
            Log::error("updateGameStatus: end failed room_id= " . $roomId . ' user_id= ' . $userId);
            $result = ErrorCode::E_ROOM_STATUS_UPDATE_ERROR;
            return $result;
        }

        $result = self::updateMemberStatus($roomId, $userId, $memberInfo['user_status']);
        //$result = self::updateMemberInfo($roomId, $userId, $memberInfo);
        if($result != ErrorCode::CODE_OK){
            Log::error("updateGameStatus: end failed room_id= " . $roomId . ' user_id= ' . $userId);
            $result = ErrorCode::E_ROOM_STATUS_UPDATE_ERROR;
            return $result;
        }

        if($result == ErrorCode::CODE_OK){
            Log::info("updateGameStatus: end ok room_id= " . $roomId . ' user_id= ' . $userId);
            $result = ErrorCode::CODE_OK;
        }else{
            Log::error("updateGameStatus: end failed room_id= " . $roomId . ' user_id= ' . $userId);
            $result = ErrorCode::E_ROOM_STATUS_UPDATE_ERROR;
        }

        return $result;
    }


    /**
     * 更新抓取结果
     * @param string $roomId 房间ID
     * @param string $userId 房间ID
     * @param int $status 房间状态
     * @return int
     */
    public static function updateGameResult($roomId, $userId, $isCatch, $orderId){
        Log::info("updateGameResult: start room_id= " . $roomId
            . 'user_id=' . $userId . ' order_id=' . $orderId  . ' is_catch=' . $isCatch);

        $tmpRoom = self::getRoomInfo($roomId);
        $giftId = isset($tmpRoom['gift_id']) ? $tmpRoom['gift_id'] : '';
        $tmpMember = self::getMemberInfo($roomId, $userId);
        $memberName = isset($tmpMember['name']) ? $tmpMember['name'] : '';
        $memberPic = isset($tmpMember['pic']) ? $tmpMember['pic'] : '';
        $tmpTag = isset($tmpRoom['tag']) ? $tmpRoom['tag'] : 0;

        //房间标签 tag 不等于0 活动 不计入兑换
        $isStatus = ErrorCode::BABY_POST_IN;
        if($tmpTag > 0){
            $isStatus = ErrorCode::BABY_POST_OVER;
        }else{
            $isStatus = ErrorCode::BABY_POST_IN;
        }

        $db_result = Db::name('TRoomGameResult');
        $data_result = array(
            'order_id' => $orderId,
            'room_id' => $roomId,
            'user_id' => $userId,
            'name' => $memberName,
            'pic' => $memberPic,
            'gift_id' => $giftId,
            'result' => $isCatch,
            'status' => $isStatus,  //默认为寄存中

        );


        $result = DataService::save($db_result, $data_result);
        if($result){
            Log::info("updateGameResult: end ok room_id= " . $roomId
                . ' user_id=' . $userId . ' order_id=' . $orderId  . ' is_catch=' . $isCatch);
            $result = ErrorCode::CODE_OK;
        }else{
            Log::error("updateGameResult: end failed room_id= " . $roomId
                . ' user_id=' . $userId . ' order_id=' . $orderId  . ' is_catch=' . $isCatch);
            $result = ErrorCode::E_ROOM_UPDATE_FAIL;
        }
        return $result;
    }

    private function _generateRandomString($length = 10, $addSpaces = true, $addNumbers = true)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!"??$%&/()=[]{}';
        $useChars = array();
        // select some random chars:
        for($i = 0; $i < $length; $i++)
        {
            $useChars[] = $characters[mt_rand(0, strlen($characters)-1)];
        }
        // add spaces and numbers:
        if($addSpaces === true)
        {
            array_push($useChars, ' ', ' ', ' ', ' ', ' ', ' ');
        }
        if($addNumbers === true)
        {
            array_push($useChars, rand(0,9), rand(0,9), rand(0,9));
        }
        shuffle($useChars);
        $randomString = trim(implode('', $useChars));
        $randomString = substr($randomString, 0, $length);
        return $randomString;
    }


    //////////////////////////start gateway function//////////////////////////////////////////
    /**
     * bind user id
     * @param string $roomId 房间ID
     * @param string $userId 当前用户ID
     * @param string $clientId 当前用户长连接 workman 的客户ID
     * @return bool|string
     */
    public static function gateWayBind($roomId, $userId, $clientId){

        //绑定gateway
        Gateway::$registerAddress = '127.0.0.1:1236';
        // client_id与uid绑定
        Gateway::bindUid($clientId, $userId);
        // 加入某个群组（可调用多次加入多个群组）
        Gateway::joinGroup($clientId, $roomId);

    }

    /**
     * bind user id
     * @param string $roomId 房间ID
     * @param string $userId 当前用户ID
     * @param string $data 发送消息内容
     * @return bool|string
     */
    public static function gateWaySendMsg($roomId='', $userId='', $data){

        if($userId){
            Gateway::sendToUid($userId, $data);
        }else{
            Gateway::sendToGroup($roomId, $data);
        }


    }

    /**
     * gateWayBuildMsg   构造gateway 消息
     * @param string $type 消息类型
     * @param string $content 消息内容
     * @param array $para 消息参数
     * @return bool|string
     */
    public static function gateWayBuildMsg($type, $content, $para=array()){

        //保证参数中一定有 code
        $para['code'] = isset($para['code']) ? $para['code'] : ErrorCode::CODE_OK;

        $chatArr = array(
            'type' => $type,
            'content' => $content,
            'para' => $para
        );
        // 向网站页面发送数据
        $chatData = json_encode($chatArr);
        Log::debug("gateWayBuildMsg: chatData= " . $chatData );
        return $chatData;
    }

    //////////////////////////end gateway function//////////////////////////////////////////


}


