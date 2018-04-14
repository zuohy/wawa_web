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

                $retStatus = RoomService::runRoom($roomId, $userId, $jPack['client_id']);
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
                $noteMsg = ErrorCode::buildMsg(ErrorCode::MSG_TYPE_INFO, ErrorCode::I_USER_JOIN_ROOM); //ErrorCode::$INFO_MSG[ErrorCode::I_USER_JOIN_ROOM];
                // 向uid的网站页面发送数据
                $chatArr = array(
                    'type' => 'chat_msg',
                    'content' => $userName . ': ' . $noteMsg,
                    'pic' => $userPic
                );
                $chatData = json_encode($chatArr);
                RoomService::gateWaySendMsg($roomId, '', $chatData);
                break;
            case 'chat_msg':
                $roomId = session('room_id');
                $userId = session('user_id');
                $userInfo = $this->getUserInfo($userId);
                $name = isset($userInfo['name']) ? $userInfo['name'] : '';
                $pic = isset($userInfo['pic']) ? $userInfo['pic'] : '';

                $chatArr = array(
                    'type' => 'chat_msg',
                    'content' => $jPack['content'],
                    'pic' => $pic
                );
                // 向任意群组的网站页面发送数据
                $chatData = json_encode($chatArr);
                RoomService::gateWaySendMsg($roomId, '', $chatData);
                break;
            case 'exit_room':
                $roomId = session('room_id');
                $userId = session('user_id');
                $userInfo = $this->getUserInfo($userId);
                $name = isset($userInfo['name']) ? $userInfo['name'] : '';
                $pic = isset($userInfo['pic']) ? $userInfo['pic'] : '';
                $showMsg = ErrorCode::buildMsg(ErrorCode::MSG_TYPE_INFO, ErrorCode::I_USER_EXIT_ROOM);
                $chatArr = array(
                    'type' => 'chat_msg',
                    'content' => $name . $showMsg,
                    'pic' => $pic
                );
                $chatData = json_encode($chatArr);
                RoomService::gateWaySendMsg($roomId, '', $chatData);
                break;
            case 'dev_user_auth':
                //baby_control 设备服务器 认证用户信息消息
                $userId = isset($jPack['user_id']) ? $jPack['user_id'] : '';
                $roomId = isset($jPack['room_id']) ? $jPack['room_id'] : '';
                $price = isset($jPack['price']) ? $jPack['price'] : 0;
                $retStatus = $this->_dev_authUser($roomId, $userId, $price);
                if($retStatus != ErrorCode::CODE_OK){
                    $this->retMsg['code'] = $retStatus;
                    $this->retMsg['msg'] = ErrorCode::buildMsg(ErrorCode::MSG_TYPE_CLIENT_ERROR, $retStatus);
                    Log::error("index: join room error retMsg= " . $this->retMsg['msg']);

                }

                //通知房间所有用户 不能投币操作

                break;
            case 'dev_notify_coins':
                $userId = isset($jPack['user_id']) ? $jPack['user_id'] : '';
                $roomId = isset($jPack['room_id']) ? $jPack['room_id'] : '';
                $coinsStatus = isset($jPack['status']) ? $jPack['status'] : ErrorCode::E_DEV_COINS_STATUS_ERROR;
                if($coinsStatus != ErrorCode::CODE_OK){
                    //投币失败 通知用户
                    break;
                }

                //投币成功 更新房间成员状态，房间状态，扣除用户金币 娃娃币

                //通知所有用户当前不能投币

                //通知当前用户可以操作游戏


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
    private function _dev_authUser($roomId, $userId, $price){

        //获取房间信息 判断房间是否为游戏状态
        $tmpRoom = RoomService::getRoomInfo($roomId);
        $roomStatus = isset($tmpRoom['status']) ? $tmpRoom['status'] : '';
        $roomPrice = isset($tmpRoom['price']) ? $tmpRoom['price'] : '';
        if(BABY_ROOM_STATUS_ON != $roomStatus){
            //房间状态不正确
            Log::error("_dev_authUser: room_id= " . $roomId . ' status= '. $roomStatus
                . ' error= ' . ErrorCode::$ERR_MSG[ErrorCode::E_ROOM_STATUS_ERROR]);
            return ErrorCode::E_ROOM_STATUS_ERROR;
        }
        if($price != $roomPrice){
            //投币金额不正确
            Log::error("_dev_authUser: room_id= " . $roomId . ' user_id= '. $userId
                . ' error= ' . ErrorCode::$ERR_MSG[ErrorCode::E_USER_INSERT_COIN_ERROR]);
            return ErrorCode::E_USER_INSERT_COIN_ERROR;
        }

        //获取房间成员信息 状态是否为正常状态
        $tmpMember = RoomService::getMemberInfo($roomId, $userId);
        $memberStatus = isset($tmpMember['user_status']) ? $tmpMember['user_status'] : '';
        if(BABY_ROOM_MEMBER_STATUS_IN != $memberStatus){
            //房间成员状态不正确
            Log::error("_dev_authUser: room_id= " . $roomId . ' user_id= '. $userId
                . ' error= ' . ErrorCode::$ERR_MSG[ErrorCode::E_ROOM_USER_STATUS_ERROR]);
            return ErrorCode::E_ROOM_USER_STATUS_ERROR;
        }

        //获取用户金币 娃娃币余额是否充足
        $tmpUser = $this->getUserInfo($userId);
        $userCoin = isset($tmpUser['coin']) ? $tmpUser['coin'] : 0;
        $userFreeCoin = isset($tmpUser['free_coin']) ? $tmpUser['free_coin'] : 0;
        if($roomPrice > $userFreeCoin){
            //用户娃娃币不足
            Log::error("_dev_authUser: user_id= " . $userId
                . ' error= ' . ErrorCode::$ERR_MSG[ErrorCode::E_USER_FREE_COIN_LACK]);
            return ErrorCode::E_USER_FREE_COIN_LACK;
        }elseif($roomPrice > $userCoin){
            //用金币不足
            Log::error("_dev_authUser: user_id= " . $userId
                . ' error= ' . ErrorCode::$ERR_MSG[ErrorCode::E_USER_COIN_LACK]);
            return ErrorCode::E_USER_COIN_LACK;
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


