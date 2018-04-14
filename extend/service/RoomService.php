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
    );

    /**
     * 获取房间信息
     * @param string $roomId 房间ID
     * @param string $userId 当前用户ID
     * @param string $clientId 当前用户长连接 workman 的客户ID
     * @return bool|string
     */
    public static function runRoom($roomId, $userId, $clientId){

        $tmpRoom = self::getRoomInfo($roomId);
        $ret = self::joinRoom($roomId, $userId, $clientId);
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
     * 获取房间信息
     * @param string $roomId 房间ID
     * @param string $userId 当前成员ID
     * @param string $type 命令参数类型
     * @return bool|string
     */
    public static function getTopMemberList($roomId, $userId)
    {
        $db_member = Db::name('TRoomMemberInfo');

        $memberArr = $db_member->where('room_id', $roomId)->where('user_status>'. BABY_ROOM_MEMBER_STATUS_OUT)
            ->order('user_status desc')              //正在游戏的成员排在第一位
            ->order('update_at desc')                //按最晚进入房间排序
            ->select();
        if($memberArr){

            foreach($memberArr as $key => $user){
                if($key >= 5){
                    //只需要最新的5条记录
                    break;
                }
                $memKey = $user['user_id'];
                self::$memberList[$memKey] = $memberArr[$key];

            }
        }

        //获取当前成员信息
        if($userId){
            $memberInfo = $db_member->where('room_id', $roomId)->where('user_status>'. BABY_ROOM_MEMBER_STATUS_OUT)
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
     * @return int
     */
    public static function joinRoom($roomId, $userId, $clientId)
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
            'user_id'=> $userId,
            'name'=> $userInfo['name'],
            'pic'=> $userInfo['pic'],
            'user_status'=> BABY_ROOM_MEMBER_STATUS_IN,
            'c_client_id' => $clientId,
            'update_at'=> date('Y-m-d H:m:s')
        );

        $db_member = Db::name('TRoomMemberInfo');
        $memberArr = $db_member->where('user_id', $userId)->find();
        if($memberArr && ($memberArr['user_id'] == $userId ) ){
            //找到用户信息 查看是否更换房间
            if($roomId != $memberArr['room_id']){
                //更换房间
                if(BABY_ROOM_MEMBER_STATUS_RUN == $memberArr['user_status']){
                    //异常情况 记录log
                    Log::error("joinRoom: user is gaming status= " . BABY_ROOM_MEMBER_STATUS_RUN. ' user_id= ' . $userId . ' room_id= ' . $roomId);
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

    //////////////////////////end gateway function//////////////////////////////////////////


}


