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
 * 房间设备管理服务
 * Class DeviceService
 * @package service
 * @author zuohy <zhywork@163.com>
 * @date 2018/03/10 15:32
 */
class DeviceService
{
    public static $deviceInfo = array(
        'dev_room_id' => '',
        'dev_topic' => '',   //未用
        'dev_status' => '',
        'dev_tag' => '',
        'dev_pic' => '',

        'dev_ser_url' => '',
        'dev_ser_port' => '',
        'dev_con_url' => '',
        'dev_video_url' => '',
        'dev_video_port' => '',
        'dev_chat_url' => '',
        'dev_chat_port' => '',
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
     * 获取房间设备信息
     * @param string $devRoomId 房间设备ID
     * @return bool|string
     */
    public static function getDeviceInfo($devRoomId)
    {
        $db_dev = Db::name('TDevRoomInfo');

        $devArr = $db_dev->where('dev_room_id', $devRoomId)->find();
        if($devArr && ($devArr['dev_room_id'] == $devRoomId ) ){

            foreach($devArr as $key => $value){
                if( isset(self::$deviceInfo[$key]) ){
                    self::$deviceInfo[$key] = $value;
                }
            }
        }

        return self::$deviceInfo;
    }

    /**
     * 更新房间设备状态
     * @param string $devRoomId 房间ID
     * @param arrary $devInfo
     * @return int
     */
    public static function updateDevInfo($devRoomId, $devInfo){
        Log::info("updateDevInfo: start room_id= " . $devRoomId);

        $db_dev = Db::name('TDevRoomInfo');
        $data_dev = array();
        $result = ErrorCode::E_ROOM_UPDATE_FAIL;

        $devArr = $db_dev->where('dev_room_id', $devRoomId)->find();
        if($devArr && ($devArr['dev_room_id'] == $devRoomId ) ){
            $data_dev['id'] = $devArr['id'];

            foreach($devArr as $key => $value){
                if( isset($devInfo[$key]) ){
                    $data_dev[$key] = $devInfo[$key];
                }
            }
            $jsonData = json_encode($data_dev);
            Log::info("updateDevInfo: update device Info= " . $jsonData );
            $result = DataService::save($db_dev, $data_dev);

        }

        if($result){
            Log::info("updateDevInfo: end ok room_id= " . $devRoomId );
            $result = ErrorCode::CODE_OK;
        }else{
            Log::error("updateDevInfo: end failed room_id= " . $devRoomId );
            $result = ErrorCode::E_ROOM_UPDATE_FAIL;
        }

        return $result;

    }

    /**
     * 更新房间设备状态
     * @param string $devRoomId 房间ID
     * @param int $status
     * @return int
     */
    public static function updateDevStatus($devRoomId, $devStatus){
        Log::info("updateDevStatus: start dev_room_id= " . $devRoomId . ' dev_status=' . $devStatus);

        if( ErrorCode::BABY_ROOM_STATUS_ON == $devStatus ){
            $devInfo['dev_status'] = ErrorCode::BABY_ROOM_STATUS_ON;

        }elseif ( ErrorCode::BABY_ROOM_STATUS_BUSY == $devStatus ){
            $devInfo['dev_status'] = ErrorCode::BABY_ROOM_STATUS_BUSY;

        }else{
            Log::error("updateDevStatus: end failed not support dev_status= " . $devStatus . ' dev_room_id' . $devRoomId);
            $result = ErrorCode::E_ROOM_STATUS_UPDATE_ERROR;
            return $result;
        }

        //更新房间状态 成员状态
        $result = self::updateDevInfo($devRoomId, $devInfo);
        if($result != ErrorCode::CODE_OK){
            Log::error("updateDevStatus: end failed room_id= " . $devRoomId. ' dev_status=' . $devStatus);
            $result = ErrorCode::E_ROOM_STATUS_UPDATE_ERROR;
            return $result;
        }

        return $result;
    }

}


