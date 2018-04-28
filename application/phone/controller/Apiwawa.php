<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2017 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://think.ctolog.com
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | github开源项目：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

namespace app\phone\controller;

use controller\BasicBaby;
use think\Db;
use think\Log;
use service\DataService;
use service\ErrorCode;
use service\RoomService;
use service\DeviceService;
//use GatewayClient\Gateway;
/**
 * 手机接口
 * Class Index
 * @package app\phone\controller
 * @author Zuohy
 * @date 2018/04/15 10:41
 */
class Apiwawa extends BasicBaby
{

    public  $retMsg = array('code' => '0', 'type' => '', 'msg' => 'ok', 'data' => '');
    /**
     * 手机接口入口
     * @return result
     */
    public function index()
    {
        $this->_initRetMsg();

        if (!$this->request->isPost()) {
            $this->retMsg['code'] = ErrorCode::CODE_NOT_POST;
            $this->retMsg['msg'] = ErrorCode::$ERR_MSG[ErrorCode::CODE_NOT_POST]; //'no post msg';
            $retMsg = json_encode($this->retMsg);
            Log::info("index: http msg retMsg= " . $retMsg);
            return $retMsg;
        }
        $postArr = $this->request->post();
        $jPack = isset($postArr['json']) ? $postArr['json'] : '';
        $cmdType = isset($jPack['type']) ? $jPack['type'] : '';

        Log::info("index: http rev msg type= " . $cmdType);
        $logRevMsg = json_encode($jPack);
        Log::info("index: http rev msg= " . $logRevMsg);


        // 处理接口消息
        switch($cmdType) {
            case 'share_login':
                //分享者登录
                $retStatus = $this->_buildShareRel( $jPack['user_id'], $jPack['code_father']);
                if(ErrorCode::CODE_OK == $retStatus){
                    $this->freeUserCoin($jPack['user_id'], WAWA_COIN_TYPE_SHARE);

                    $db_user = Db::name('TUserConfig');
                    $userInfo = $db_user->where('code', $jPack['code_father'])->find();
                    if($userInfo && ($userInfo['code'] == $jPack['code_father'] ) ){
                        $this->freeUserCoin($userInfo['user_id'], WAWA_COIN_TYPE_SHARE);
                    }

                }
                $this->retMsg['code'] = $retStatus;
                //$this->retMsg['type'] = $cmdType;
                $this->retMsg['data'] = $jPack['code_father'];
                break;
            case 'room_servers':
                //获取房间 服务器信息  视频服务器 聊天服务器 设备服务器
                $retData = array(
                    'wa_video_url' => sysconf('wa_video_url'),
                    'wa_video_port' => sysconf('wa_video_port'),
                    'wa_control_url' => sysconf('wa_control_url'),
                    'wa_control_port' => sysconf('wa_control_port'),
                    'wa_chat_url' => sysconf('wa_chat_url'),
                    'wa_chat_port' => sysconf('wa_chat_port')
                );
                $this->retMsg['data'] = $retData;

                break;
            case 'chat_bind':
                //Gateway::$registerAddress = '127.0.0.1:1236';
                $userId = session('user_id');
                $roomId = session('room_id');
                $devRoomId = session('dev_room_id');

                $retStatus = RoomService::runRoom($roomId, $userId, $jPack['client_id'], $devRoomId);
                $tmpMembers = RoomService::$memberList;
                $tmpRoomInfo = RoomService::$roomInfo;
                $tmpGift = RoomService::$giftInfo;
                if($retStatus != ErrorCode::CODE_OK){
                    $this->retMsg['code'] = $retStatus;
                    $this->retMsg['msg'] = ErrorCode::$ERR_MSG[$retStatus];
                    Log::error("index: join room error retMsg= " . $this->retMsg['msg']);
                    break;
                }

                $retData = array(
                    'room_info' => $tmpRoomInfo,
                    'member_list' => $tmpMembers,
                    'gift_show' => $tmpGift['gift_pic_show'],
                    'cur_member' =>  $tmpMembers[$userId]
                );
                $this->retMsg['data'] = $retData;

                //绑定gateway 长连接
                RoomService::gateWayBind($roomId, $userId, $jPack['client_id']);

                $userName = $tmpMembers[$userId]['name'];
                $userPic = $tmpMembers[$userId]['pic'];
                $showMsg = ErrorCode::buildMsg(ErrorCode::MSG_TYPE_INFO, ErrorCode::I_USER_JOIN_ROOM); //ErrorCode::$INFO_MSG[ErrorCode::I_USER_JOIN_ROOM];
                // 向uid的网站页面发送数据
                $paraArr = array(
                    'notify_type' => 'join_room',
                    'move_member' =>  $tmpMembers[$userId],   //进入房间的用户信息 通知房间所有人
                    'name' => $userName,
                    'pic' => $userPic
                );
                $chatData = RoomService::gateWayBuildMsg('chat_msg', $showMsg, $paraArr);
                RoomService::gateWaySendMsg($roomId, '', $chatData);
                break;
            case 'chat_msg':
                $roomId = session('room_id');
                $userId = session('user_id');
                $userInfo = $this->getUserInfo($userId);
                $name = isset($userInfo['name']) ? $userInfo['name'] : '';
                $pic = isset($userInfo['pic']) ? $userInfo['pic'] : '';
                $showMsg = isset($jPack['content']) ? $jPack['content'] : '';

                $paraArr = array(
                    'name' => $name,
                    'pic' => $pic
                );
                // 向任意群组的网站页面发送数据
                $chatData = RoomService::gateWayBuildMsg('chat_msg', $showMsg, $paraArr);
                RoomService::gateWaySendMsg($roomId, '', $chatData);
                break;
            case 'exit_room':
                $roomId = session('room_id');
                $userId = session('user_id');
                $userInfo = $this->getUserInfo($userId);
                $name = isset($userInfo['name']) ? $userInfo['name'] : '';
                $pic = isset($userInfo['pic']) ? $userInfo['pic'] : '';

                //更新房间成员状态
                RoomService::updateMemberStatus($roomId, $userId, ErrorCode::BABY_ROOM_MEMBER_STATUS_OUT);

                $showMsg = ErrorCode::buildMsg(ErrorCode::MSG_TYPE_INFO, ErrorCode::I_USER_EXIT_ROOM);
                $paraArr = array(
                    'notify_type' => 'exit_room',
                    'name' => $name,
                    'pic' => $pic,
                    'move_member' =>  $userInfo,   //退出房间的用户信息 通知房间所有人
                );
                $chatData = RoomService::gateWayBuildMsg('chat_msg', $showMsg, $paraArr);
                RoomService::gateWaySendMsg($roomId, '', $chatData);
                break;
            case 'dev_user_auth':
                //baby_control 设备服务器 认证用户信息消息
                $userId = isset($jPack['user_id']) ? $jPack['user_id'] : '';
                $roomId = isset($jPack['room_id']) ? $jPack['room_id'] : '';
                $price = isset($jPack['price']) ? $jPack['price'] : 0;
                $devRoomId = isset($jPack['dev_room_id']) ? $jPack['dev_room_id'] : '';
                $retStatus = $this->_dev_authUser($roomId, $userId, $price, $devRoomId);
                if($retStatus != ErrorCode::CODE_OK){
                    $this->retMsg['code'] = $retStatus;
                    $this->retMsg['msg'] = ErrorCode::buildMsg(ErrorCode::MSG_TYPE_CLIENT_ERROR, $retStatus);
                    Log::error("index: dev_user_auth error retMsg= " . $this->retMsg['msg']);
                    //通知当前用户 投币失败
                    $showMsg = $this->retMsg['msg']; //ErrorCode::buildMsg(ErrorCode::MSG_TYPE_INFO, ErrorCode::I_USER_INSERT_COINS_FROZEN);
                    $paraArr = array(
                        'code' => $retStatus,
                        'notify_type' => 'dev_user_auth',
                    );
                    $chatData = RoomService::gateWayBuildMsg('notify_msg', $showMsg, $paraArr);
                    RoomService::gateWaySendMsg('', $userId, $chatData);
                    break;
                }
                //返回设备控制服务器 设备控制url
                $tmpDeviceInfo = DeviceService::getDeviceInfo($devRoomId);
                $retData = array(
                    'dev_con_url' => $tmpDeviceInfo['dev_con_url'],
                );
                $this->retMsg['data'] = $retData;

                //通知房间所有用户 不能投币操作
                $showMsg = ErrorCode::buildMsg(ErrorCode::MSG_TYPE_INFO, ErrorCode::I_USER_INSERT_COINS_FROZEN);
                $paraArr = array(
                    'notify_type' => 'insert_coins_frozen',
                );
                $chatData = RoomService::gateWayBuildMsg('notify_msg', $showMsg, $paraArr);
                RoomService::gateWaySendMsg($roomId, '', $chatData);
                break;
            case 'dev_notify_coins':
                //设备投币是否成功，并通知房间用户，页面更新控件显示
                $userId = isset($jPack['user_id']) ? $jPack['user_id'] : '';
                $roomId = isset($jPack['room_id']) ? $jPack['room_id'] : '';
                $price = isset($jPack['price']) ? $jPack['price'] : 0;
                $devRoomId = isset($jPack['dev_room_id']) ? $jPack['dev_room_id'] : '';
                $coinsStatus = isset($jPack['status']) ? $jPack['status'] : ErrorCode::E_DEV_COINS_STATUS_ERROR;

                if($coinsStatus != ErrorCode::CODE_OK){
                    //投币失败 通知用户
                    $paraArr = array(
                        'notify_type' => 'dev_notify_coins',
                        'code' => $coinsStatus
                    );
                    $showMsg = ErrorCode::buildMsg(ErrorCode::MSG_TYPE_CLIENT_ERROR, ErrorCode::E_DEV_COINS_STATUS_ERROR);
                    $chatData = RoomService::gateWayBuildMsg('notify_msg', $showMsg, $paraArr);
                    RoomService::gateWaySendMsg('', $userId, $chatData);

                    $this->retMsg['code'] = $coinsStatus;
                    $this->retMsg['msg'] = ErrorCode::$ERR_MSG[$coinsStatus];
                    Log::error("index: dev_notify_coins error retMsg= " . $this->retMsg['msg']);
                    break;
                }

                //投币成功 更新设备状态
                $retStatus = DeviceService::updateDevStatus($devRoomId, ErrorCode::BABY_ROOM_STATUS_BUSY);
                if($retStatus != ErrorCode::CODE_OK){
                    $this->retMsg['code'] = $retStatus;
                    $this->retMsg['msg'] = ErrorCode::$ERR_MSG[$retStatus];
                    Log::error("index: dev_notify_coins error retMsg= " . $this->retMsg['msg']);
                    break;
                }
                //投币成功 更新房间成员状态，房间状态
                $retStatus = RoomService::updateGameStatus($roomId, $userId, ErrorCode::BABY_ROOM_STATUS_BUSY);
                if($retStatus != ErrorCode::CODE_OK){
                    $this->retMsg['code'] = $retStatus;
                    $this->retMsg['msg'] = ErrorCode::$ERR_MSG[$retStatus];
                    Log::error("index: dev_notify_coins error retMsg= " . $this->retMsg['msg']);
                    break;
                }

                $tmpRoomInfo = RoomService::getRoomInfo($roomId);
                $needCoin = isset($tmpRoomInfo['price']) ? $tmpRoomInfo['price'] : 0;
                if($needCoin != $price){
                    $this->retMsg['code'] = ErrorCode::E_USER_INSERT_COIN_ERROR;
                    $this->retMsg['msg'] = ErrorCode::$ERR_MSG[ErrorCode::E_USER_INSERT_COIN_ERROR];
                    Log::error("index: dev_notify_coins error retMsg= " . $this->retMsg['msg'] . ' needCoin=' . $needCoin . ' price=' .$price);
                    break;
                }
                //扣除用户金币 娃娃币
                $orderId = '';
                $retStatus = $this->employUserCoin($userId, $needCoin, ErrorCode::BABY_EMPLOY_REASON_1, '普通消费', ErrorCode::BABY_EMPLOY_TYPE_FREE, $orderId);
                if($retStatus != ErrorCode::CODE_OK){
                    $this->retMsg['code'] = $retStatus;
                    $this->retMsg['msg'] = ErrorCode::$ERR_MSG[$retStatus];
                    Log::error("index: dev_notify_coins error retMsg= " . $this->retMsg['msg']);
                    break;
                }

                //消费订单ID 返回设备服务器
                $retData = array(
                    'order_id' => $orderId,
                );
                $this->retMsg['data'] = $retData;

                //通知所有用户当前不能投币
                $tmpMemberInfo = $this->getUserInfo($userId);
                $name = isset($tmpMemberInfo['name']) ? $tmpMemberInfo['name'] : '';
                $pic = isset($tmpMemberInfo['pic']) ? $tmpMemberInfo['pic'] : '';
                $coin = isset($tmpMemberInfo['coin']) ? $tmpMemberInfo['coin'] : '';
                $free_coin = isset($tmpMemberInfo['free_coin']) ? $tmpMemberInfo['free_coin'] : '';
                $showMsg = ErrorCode::buildMsg(ErrorCode::MSG_TYPE_INFO, ErrorCode::I_USER_INSERT_COINS_FROZEN);
                $paraArr = array(
                    'notify_type' => 'insert_coins_frozen',
                    'name' => $name,
                    'pic' => $pic,
                );
                $chatData = RoomService::gateWayBuildMsg('notify_msg', $showMsg, $paraArr);
                RoomService::gateWaySendMsg($roomId, '', $chatData);

                //通知当前用户可以操作游戏
                $paraArr = array(
                    'notify_type' => 'dev_notify_coins',
                    'name' => $name,
                    'pic' => $pic,
                    'coin' => $coin,
                    'free_coin' => $free_coin,
                );
                $showMsg = ErrorCode::buildMsg(ErrorCode::MSG_TYPE_CLIENT_ERROR, ErrorCode::CODE_OK);
                $chatData = RoomService::gateWayBuildMsg('notify_msg', $showMsg, $paraArr);
                RoomService::gateWaySendMsg('', $userId, $chatData);

                break;
            case 'dev_notify_result':
                //收到设备服务器抓取结果
                $userId = isset($jPack['user_id']) ? $jPack['user_id'] : '';
                $roomId = isset($jPack['room_id']) ? $jPack['room_id'] : '';
                $orderId = isset($jPack['order_id']) ? $jPack['order_id'] : '';
                $devRoomId = isset($jPack['dev_room_id']) ? $jPack['dev_room_id'] : '';
                $isCatch = isset($jPack['is_catch']) ? $jPack['is_catch'] : ErrorCode::BABY_CATCH_FAIL;

                //更新抓取结果记录
                $retStatus = RoomService::updateGameResult($roomId, $userId, $isCatch, $orderId);
                if($retStatus != ErrorCode::CODE_OK){
                    $this->retMsg['code'] = $retStatus;
                    $this->retMsg['msg'] = ErrorCode::$ERR_MSG[$retStatus];
                    Log::error("index: dev_notify_result error retMsg= " . $this->retMsg['msg']);
                }

                //投币成功 更新设备状态
                $retStatus = DeviceService::updateDevStatus($devRoomId, ErrorCode::BABY_ROOM_STATUS_ON);
                if($retStatus != ErrorCode::CODE_OK){
                    $this->retMsg['code'] = $retStatus;
                    $this->retMsg['msg'] = ErrorCode::$ERR_MSG[$retStatus];
                    Log::error("index: dev_notify_coins error retMsg= " . $this->retMsg['msg']);
                    break;
                }

                $retStatus = RoomService::updateGameStatus($roomId, $userId, ErrorCode::BABY_ROOM_STATUS_ON);
                if($retStatus != ErrorCode::CODE_OK){
                    $this->retMsg['code'] = $retStatus;
                    $this->retMsg['msg'] = ErrorCode::$ERR_MSG[$retStatus];
                    Log::error("index: dev_notify_result error retMsg= " . $this->retMsg['msg']);
                }

                //通知用户抓取结果
                if( ErrorCode::BABY_CATCH_SUCCESS == $isCatch){
                    $showMsg = ErrorCode::buildMsg(ErrorCode::MSG_TYPE_INFO, ErrorCode::I_USER_CATCH_SUCCESS);
                }else{
                    $showMsg = ErrorCode::buildMsg(ErrorCode::MSG_TYPE_INFO, ErrorCode::I_USER_CATCH_FAIL);
                }

                $paraArr = array(
                    'notify_type' => 'dev_notify_result',
                );
                $chatData = RoomService::gateWayBuildMsg('notify_msg', $showMsg, $paraArr);
                RoomService::gateWaySendMsg('', $userId, $chatData);

                //通知房间所有用户 抓取结果
                $tmpMemberInfo = $this->getUserInfo($userId);
                $name = isset($tmpMemberInfo['name']) ? $tmpMemberInfo['name'] : '';
                $pic = isset($tmpMemberInfo['pic']) ? $tmpMemberInfo['pic'] : '';
                $paraArr = array(
                    'notify_type' => 'dev_notify_result',
                    'name' => $name,
                    'pic' => $pic,
                );
                $chatData = RoomService::gateWayBuildMsg('chat_msg', $showMsg, $paraArr);
                RoomService::gateWaySendMsg($roomId, '', $chatData);

                break;
            default:
                $this->retMsg['code'] = ErrorCode::CODE_NOT_SUPPORT;
                $this->retMsg['msg'] = ErrorCode::$ERR_MSG[ErrorCode::CODE_NOT_SUPPORT];
                break;
        }

        $this->retMsg['type'] = $cmdType;
        $retMsg = json_encode($this->retMsg);
        Log::info("index: return http msg retMsg= " . $retMsg);
        return $retMsg;

    }

    /**
     * 初始化返回消息结构
     * @return array
     */
    private function _initRetMsg()
    {
        $retMsg['code'] = ErrorCode::CODE_OK;
        $retMsg['type'] = '';
        $retMsg['msg'] = ErrorCode::$ERR_MSG[ErrorCode::CODE_OK];
        $retMsg['data'] = '';
        return $this->retMsg;
    }

    /**
     * 保存分享记录表
     * @param array $userAccept 当前被分享者
     * @param array $userFather 分享者
     * @param int $isStatus 是否已经分享
     *
     * @return array
     */
/*    private function _saveShareHis($userAccept, $userFather, $isStatus)
    {
        //保存分享记录表
        $db_share_his = Db::name('TUserShareHis');

        $i_code = isset($userAccept['code']) ? $userAccept['code'] : '';
        $i_name = isset($userAccept['name']) ? $userAccept['name'] : '';
        $i_pic = isset($userAccept['pic']) ? $userAccept['pic'] : '';
        $i_gender = isset($userAccept['gender']) ? $userAccept['gender'] : '';
        $i_code_father = isset($codeFather) ? $codeFather : '';
        $i_name_father = isset($userInvitation['name']) ? $userInvitation['name'] : '';
        $i_pic_father = isset($userInvitation['pic']) ? $userInvitation['pic'] : '';
        $i_gender_father = isset($userInvitation['gender']) ? $userInvitation['gender'] : '';

        $data_share = array(
            'code'=> $i_code,
            'name'=> $i_name,
            'pic'=> $i_pic,
            'gender'=> $i_gender,

            'code_father'=> $i_code_father,
            'name_father'=> $i_name_father,
            'pic_father'=> $i_pic_father,
            'gender_father'=> $i_gender_father,
            's_status'=> $isShare);
        $result = DataService::save($db_share_his, $data_share);
    }
*/
    /**
     * 建立分享关系 邀请码对应用户
     * @return array
     */
    private function _buildShareRel($userId, $codeFather)
    {
        $isShare = 0;  //0 分享关联成功 1 失败 已经被分享  2 更换
        //保存分享记录表
        $db_share_his = Db::name('TUserShareHis');

        //查找当前登录用户
        $db_user = Db::name('TUserConfig');
        $userAccept = $db_user->where('user_id', $userId)->find();   //被分享者信息
        $userInvitation = $db_user->where('code', $codeFather)->find();   //邀请者信息

        if($userInvitation && $userInvitation['code'] == $codeFather){
            if($userAccept && $userAccept['user_id'] == $userId){
                if($userAccept['code_father'] != ''){
                    //当前用户已经被分享了，暂时不更新分享者邀请码
                    $isShare = 1;
                }else{
                    //更新当前用户的父级邀请码
                    $data_user = array('id'=> $userAccept['id'], 'code_father'=> $codeFather);
                    $result = DataService::save($db_user, $data_user);
                    $isShare = 0;
                }

            }else{
                //记录日志
                $isShare = 3;  //没有被邀请者信息

            }
        }else{
            //记录日志
            $isShare = 9; //没有找到邀请者信息
        }

        //保存分享记录表
        $i_code = isset($userAccept['code']) ? $userAccept['code'] : '';
        $i_name = isset($userAccept['name']) ? $userAccept['name'] : '';
        $i_pic = isset($userAccept['pic']) ? $userAccept['pic'] : '';
        $i_gender = isset($userAccept['gender']) ? $userAccept['gender'] : '';
        $i_code_father = isset($codeFather) ? $codeFather : '';
        $i_name_father = isset($userInvitation['name']) ? $userInvitation['name'] : '';
        $i_pic_father = isset($userInvitation['pic']) ? $userInvitation['pic'] : '';
        $i_gender_father = isset($userInvitation['gender']) ? $userInvitation['gender'] : '';

        $data_share = array(
            'code'=> $i_code,
            'name'=> $i_name,
            'pic'=> $i_pic,
            'gender'=> $i_gender,

            'code_father'=> $i_code_father,
            'name_father'=> $i_name_father,
            'pic_father'=> $i_pic_father,
            'gender_father'=> $i_gender_father,
            's_status'=> $isShare);
        $result = DataService::save($db_share_his, $data_share);
        Log::info("_buildShareRel: isShare= " . $isShare);
        return $isShare;

    }


    /////////////////////////start baby_coontrol 设备服务器消息处理//////////////////////////////////////////////////
    /**
     * 设备服务器认证用户信息
     * @return int
     */
    private function _dev_authUser($roomId, $userId, $price, $devRoomId){

        //获取房间信息 判断房间是否为游戏状态
        $tmpRoom = RoomService::getRoomInfo($roomId);
        $tmpDevice = DeviceService::getDeviceInfo($devRoomId);

        $devStatus = isset($tmpDevice['dev_status']) ? $tmpDevice['dev_status'] : '';
        $roomStatus = isset($tmpRoom['status']) ? $tmpRoom['status'] : '';
        $roomPrice = isset($tmpRoom['price']) ? $tmpRoom['price'] : '';
        $logInfo = "_dev_authUser: room_id= " . $roomId . ' status= '. $roomStatus . ' dev_room_id= '. $devRoomId . ' dev_status= '. $devStatus;
        if( (ErrorCode::BABY_ROOM_STATUS_BUILD == $roomStatus)
            || (ErrorCode::BABY_ROOM_STATUS_OFF == $roomStatus)
            || (ErrorCode::BABY_ROOM_STATUS_ON != $devStatus) ){
            //房间状态不正确
            Log::error($logInfo
                . ' error= ' . ErrorCode::$ERR_MSG[ErrorCode::E_ROOM_STATUS_ERROR]);
            return ErrorCode::E_ROOM_STATUS_ERROR;
        }
        if($price != $roomPrice){
            //投币金额不正确
            Log::error($logInfo
                . ' error= ' . ErrorCode::$ERR_MSG[ErrorCode::E_USER_INSERT_COIN_ERROR]);
            return ErrorCode::E_USER_INSERT_COIN_ERROR;
        }

        //获取房间成员信息 状态是否为正常状态
        $tmpMember = RoomService::getMemberInfo($roomId, $userId);
        $memberStatus = isset($tmpMember['user_status']) ? $tmpMember['user_status'] : '';
        if(ErrorCode::BABY_ROOM_MEMBER_STATUS_IN != $memberStatus){
            //房间成员状态不正确
            Log::error("_dev_authUser: room_id= " . $roomId . ' user_id= '. $userId . ' user_status= '. $memberStatus
                . ' error= ' . ErrorCode::$ERR_MSG[ErrorCode::E_ROOM_USER_STATUS_ERROR]);
            return ErrorCode::E_ROOM_USER_STATUS_ERROR;
        }

        //获取用户金币 娃娃币余额是否充足
        $tmpUser = $this->getUserInfo($userId);
        $userCoin = isset($tmpUser['coin']) ? $tmpUser['coin'] : 0;
        $userFreeCoin = isset($tmpUser['free_coin']) ? $tmpUser['free_coin'] : 0;
        $userAllCoins = $userCoin + $userFreeCoin;  //总金额

        if($userAllCoins < $roomPrice){
            Log::error("_dev_authUser: all coin not more roomPrice=" . $roomPrice . ' all coin=' . $userAllCoins
                . ' error= ' . ErrorCode::$ERR_MSG[ErrorCode::E_USER_FREE_COIN_LACK]);
            return ErrorCode::E_USER_FREE_COIN_LACK;
        }

        return ErrorCode::CODE_OK;
    }
    ///////////////////////////////////////////////////////////////////////////



    /**
     * 后台主菜单权限过滤
     * @param array $menus 当前菜单列表
     * @param array $nodes 系统权限节点数据
     * @param bool $isLogin 是否已经登录
     * @return array
     */
    private function _filterMenuData($menus, $nodes, $isLogin)
    {

        return $menus;
    }




    /**
     * 列表数据处理
     * @param type $list
     */
    protected function _data_filter(&$list)
    {


    }



}


