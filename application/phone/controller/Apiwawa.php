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
use service\ActivityService;
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
                $proCode = isset($jPack['product_code']) ? $jPack['product_code'] : ErrorCode::BABY_HEADER_SEQ_APP;
                if($proCode == ''){
                    $proCode = ErrorCode::BABY_HEADER_SEQ_APP;
                }
                $codeFather = isset($jPack['code_father']) ? $jPack['code_father'] : '';
                $userId = isset($jPack['user_id']) ? $jPack['user_id'] : '';

                $retStatus = $this->_buildShareRecord( $proCode, $userId, $codeFather);
                if(ErrorCode::CODE_OK == $retStatus){
                    //用户分享app 应用 立刻返 娃娃币
                    $this->freeUserCoin($proCode, $jPack['user_id'], ErrorCode::BABY_COIN_TYPE_SHARE, '徒弟收益');


                    $faUserInfo = $this->getUserInfoByCode($codeFather);
                    if($faUserInfo && ($faUserInfo['code'] == $codeFather ) ){
                        $this->freeUserCoin($proCode, $faUserInfo['user_id'], ErrorCode::BABY_COIN_TYPE_SHARE, '收徒收益', ErrorCode::BABY_INCOME_BACK_TRUE);
                    }

                }
                $this->retMsg['code'] = $retStatus;
                $this->retMsg['data'] = $codeFather;
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
                $tmpMembers = RoomService::$memberList;  //top3 的房间用户
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
                    'gift_info' => $tmpGift,
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
                    'pic' => $userPic,
                    'member_count' => $tmpRoomInfo['member_count']
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
                    'member_count' => RoomService::$curMemberCount,
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
                    Log::error("index: dev_user_auth error retMsg= " . $this->retMsg['msg'] . " user_id=" . $userId);
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
                $retStatus = $this->employUserCoin($userId, $needCoin, ErrorCode::BABY_EMPLOY_REASON_1, '投币游戏', ErrorCode::BABY_EMPLOY_TYPE_FREE, $orderId);
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
                    'is_catch' => $isCatch
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
///////////////start 支付接口////////////////////////
            case 'payment':
                //支付 生成订单
                //获取openid
                $openId = session('open_id');
                $userId = session('user_id');
                $unionId = session('union_id');

                Log::info("payment: openId= " . $openId);
                Log::info("payment: userId= " . $userId);
                Log::info("payment: unionId= " . $unionId);

                //获取产品编码
                $productCode = isset($jPack['product_code']) ? $jPack['product_code'] : ErrorCode::BABY_HEADER_SEQ_APP;
                //获取pay value
                $iconsType = isset($jPack['icons_type']) ? $jPack['icons_type'] :0;   //0 为无效的优惠
                $userPay = isset($jPack['pay_value']) ? $jPack['pay_value'] : -1;   //用户自定义 支付金额

                $lastPay = -1;     //支付金额
                $iconsArr = $this->getPayValue($iconsType);
                if( 0 == $iconsType){
                    $lastPay = $userPay;
                }else{
                    $lastPay = isset($iconsArr['pay_value']) ? $iconsArr['pay_value'] : -1;
                }

                //检查是否为首充
                $tmpUserInfo = $this->getUserInfo($userId);
                $isCharge = isset($tmpUserInfo['is_recharge']) ? $tmpUserInfo['is_recharge'] : ErrorCode::BABY_USER_NO_RECHARGE;
                if($iconsType == ErrorCode::BABY_COIN_TYPE_REG_1
                && ($isCharge == ErrorCode::BABY_USER_OK_RECHARGE) ){
                    //用户已经充值过，选择为首次充值优惠 不能充值
                    $this->retMsg['code'] = ErrorCode::E_USER_NOT_FIRST_RECHARGE;
                    $this->retMsg['msg'] = ErrorCode::$ERR_MSG_C[ErrorCode::E_USER_NOT_FIRST_RECHARGE];
                    Log::error("index: payment error retMsg= " . $this->retMsg['msg']);
                }

                //数据库中充值单位为元， 支付接口单位为 分， 所以这里需要转换金额 1元= 100分
                $payValue = $this->coverPayValue(ErrorCode::BABY_COVER_TYPE_PAY, $lastPay);
                $optionsArr = $this->miniPay($openId, $payValue);
                //$payOptions = json_encode($optionsArr);

                //生成用户订单信息
                Log::info("payment: options code= " . $optionsArr['code']);
                if( $optionsArr['code'] == ErrorCode::CODE_OK ){
                    $this->saveReceipt($userId, $optionsArr['prepayId'], $lastPay, $iconsType, $optionsArr['order_no'], $productCode);
                }else{
                    //用户创建订单失败
                    $this->retMsg['code'] = $optionsArr['code'];
                    $this->retMsg['msg'] = ErrorCode::$ERR_MSG_C[$optionsArr['code']];
                    Log::error("index: payment error retMsg= " . $this->retMsg['msg']);
                }
                //订单 返回支付订单信息
                $this->retMsg['data'] = $optionsArr;
                break;
            case 'pay_result':
                //支付结果， 处理支付后逻辑
                $orderNo = isset($jPack['order_no']) ? $jPack['order_no'] : '';
                $status = isset($jPack['status']) ? $jPack['status'] : '';
                Log::info("paymentResult: start orderNo=" . $orderNo . " status=" . $status);

                $result = $this->completeOrderPay($orderNo, $status);
                if( $result == ErrorCode::BABY_PAY_SUCCESS){
                    $this->retMsg['code'] = ErrorCode::CODE_OK;
                    $this->retMsg['msg'] = ErrorCode::$ERR_MSG[ErrorCode::CODE_OK];

                    //支付成功， 后续逻辑处理
                    $this->handleUserCoin($orderNo);

                }else{
                    $this->retMsg['code'] = ErrorCode::CODE_NOT_SUPPORT;
                    $this->retMsg['msg'] = ErrorCode::$ERR_MSG[ErrorCode::CODE_NOT_SUPPORT];
                }

                Log::info("paymentResult: end orderNo=" . $orderNo . " result=" . $result);

                break;
            case 'apply_cash':
                $this->retMsg['code'] = ErrorCode::CODE_NOT_SUPPORT;
                $this->retMsg['msg'] = ErrorCode::$ERR_MSG_C[ErrorCode::CODE_NOT_SUPPORT];
                break;
///////////////end 支付接口////////////////////////
            case 'user_notify':
                //用户首页通知
                $notifyType = isset($jPack['notify_type']) ? $jPack['notify_type'] : 0;  //默认为抓取结果通知
                $curUserId = session('user_id');
                $notifyList = '';
                Log::info("user_notify:" . " notifyType=" . $notifyType);

                $curDate = date('Y-m-d H:m:s');
                $startDate = date("Y-m-d",strtotime("-1 day"));
                if($notifyType == 2){
                    //获取登录赠送金币
                    $userInfo = $this->getUserInfo($curUserId);
                    $uLoginTime = isset($userInfo['login_at']) ? $userInfo['login_at'] : $curDate;
                    $uMaxFree = isset($userInfo['all_free']) ? $userInfo['all_free'] : '300';
                    if($uMaxFree >= ErrorCode::BABY_MAX_FREE_COIN){
                        //超出免费赠送币 上限
                        $this->retMsg['code'] = ErrorCode::E_NOTIFY_MSG_NULL;
                        break;
                    }
                    $tmpLoginDate = date("Y-m-d",strtotime($uLoginTime));  //转成年月日
                    $loginDate = strtotime($tmpLoginDate);
                    $tmpPreDate = date("Y-m-d",strtotime("-1 day"));
                    $preDate = strtotime($tmpPreDate);
                    if($loginDate <= $preDate){
                        Log::info("user_notify: first login per date". " last_login=" . $uLoginTime);
                        //每天第一次登录 免费送娃娃币
                        $this->freeUserCoin(ErrorCode::BABY_HEADER_SEQ_APP, $curUserId, ErrorCode::BABY_COIN_TYPE_LOGIN, '首登收益');
                        $receiptFreeInfo = $this->getPayValue(ErrorCode::BABY_COIN_TYPE_LOGIN);
                        $freeCoin = isset($receiptFreeInfo['free_value']) ? $receiptFreeInfo['free_value'] : 0;
                        $newFreeCoin = $uMaxFree + $freeCoin;

                        $notifyList[0] = ['name' => $userInfo['name'], 'free_coin' => $freeCoin ];

                        //更新用户表 赠送币
                        $data_user = array('login_at' => $curDate, 'all_free' => $newFreeCoin,);
                        $this->updateUserInfo($curUserId, $data_user);
                    }else{
                        Log::info("user_notify: not first login per date". " last_login=" . $uLoginTime);
                    }

                }elseif($notifyType == 1){
                    //获取当天收益记录
                    $notifyList = ActivityService::getIncomeNotify($startDate, $curDate);
                }else{
                    //获取当天抓取成功记录
                    $notifyList = $this->getResultNotify($startDate, $curDate);
                }


                $maxPos = count($notifyList);
                if(empty($notifyList) || $maxPos <= 0){
                    //少于一条不通知
                    $this->retMsg['code'] = ErrorCode::E_NOTIFY_MSG_NULL;
                    break;
                }

                //随机选中一条
                //$fixPos = rand(0, $maxPos-1);
                //最新的 3条
                $notifyMsg = array();
                foreach($notifyList as $key => $record){
                    if($key >  2){
                        break;
                    }


                //获取通知相关信息
                $nInfo = $notifyList[$key];
                $userId = isset($nInfo['user_id']) ? $nInfo['user_id'] : '';
                $uValue = isset($nInfo['i_value']) ? $nInfo['i_value'] : '';
                $uCoin = isset($nInfo['coin']) ? $nInfo['coin'] : '';
                $uFreeCoin = isset($nInfo['free_coin']) ? $nInfo['free_coin'] : '';
                $uGiftId = isset($nInfo['gift_id']) ? $nInfo['gift_id'] : '';
                $giftInfo = RoomService::getGiftInfo($uGiftId);
                $gName = isset($giftInfo['gift_name']) ? $giftInfo['gift_name'] : '';

                $userInfo = $this->getUserInfo($userId);
                $uName = isset($userInfo['name']) ? $userInfo['name'] : '';



                //生成通知
                $msgHeader = $uName . ' 获得 ';
                $msgBody = '';
                if($gName){
                    $msgBody = '礼品 ' . $gName;
                }elseif($uValue){
                    $msgBody = '返现' . $uValue;
                }else{
                    if($uCoin){
                        $msgBody = '金币 ' . $uCoin;
                    }
                    if($uFreeCoin){
                        $msgBody = $msgBody . ' 娃娃币 ' . $uFreeCoin;
                    }

                }

                    $notifyMsg[] = $msgHeader . $msgBody;
                }//foreach($notifyList as $key => $record){

                $this->retMsg['data'] = $notifyMsg;
                $this->retMsg['msg'] = $msgHeader . $msgBody;
                Log::info("user_notify: end msg=" . $this->retMsg['msg']);
                break;
            case 'auto_exchange':
                //到期自动兑换金币
                $this->_wait_timeout_exchange(ErrorCode::BABY_POST_WAIT_TIMEOUT);


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
     * 测试页面
     * @return View
     */
    public function test()
    {

        return view('', ['title' => '接口测试中心']);

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
     * 建立分享关系 邀请码对应用户
     * @return array
     */
    private function _buildShareRecord($proCode ,$userId, $codeFather)
    {
        $isShare = ErrorCode::BABY_SHARE_FAILED;  //0 分享关联成功 1 用户 已经被分享  2 购买商品 未支付

        //查找当前登录用户
        $userAccept = $this->getUserInfo($userId);         //被分享者信息
        $userInvitation = $this->getUserInfoByCode($codeFather);   //邀请者信息

        //保存分享记录表
        if($proCode != ErrorCode::BABY_HEADER_SEQ_APP){
            $isShare = ErrorCode::BABY_SHARE_NOT_PAY;  //商品分享，未支付

        }else{
            //用户是否被分享
            if($userInvitation && $userInvitation['code'] == $codeFather){
                if($userAccept && $userAccept['user_id'] == $userId){
                    if($userAccept['code_father'] != ''){
                        //当前用户已经被分享了，暂时不更新分享者邀请码
                        $isShare = ErrorCode::BABY_SHARE_FAILED;
                    }else{
                        //更新当前用户的父级邀请码
                        $db_user = Db::name('TUserConfig');
                        $data_user = array('id'=> $userAccept['id'], 'code_father'=> $codeFather);
                        $result = DataService::save($db_user, $data_user);
                        $isShare = ErrorCode::BABY_SHARE_SUCCESS;
                    }

                }else{
                    //记录日志
                    $isShare = ErrorCode::BABY_SHARE_NO_ACCEPT;  //没有被邀请者信息

                }
            }else{
                //记录日志
                $isShare = ErrorCode::BABY_SHARE_NO_INVITATION; //没有找到邀请者信息
            }
        }


        $result = ActivityService::updateShareHis($proCode, $userAccept, $userInvitation, $isShare);

        Log::info("_buildShareRecord: isShare= " . $isShare);
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
            || (ErrorCode::BABY_ROOM_STATUS_OFF == $roomStatus) ){
            //房间状态不正确
            Log::error($logInfo
                . ' error= ' . ErrorCode::$ERR_MSG[ErrorCode::E_ROOM_STATUS_ERROR]);
            return ErrorCode::E_ROOM_STATUS_ERROR;
        }
        if(ErrorCode::BABY_ROOM_STATUS_ON != $devStatus){
            //设备房间状态不正确
            Log::error($logInfo
                . ' error= ' . ErrorCode::$ERR_MSG[ErrorCode::E_DEV_GAME_RUN]);
            return ErrorCode::E_DEV_GAME_RUN;
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
     * 超期自动兑换金币
     * @return int
     */
    private function _wait_timeout_exchange($MaxDate=ErrorCode::BABY_POST_WAIT_TIMEOUT)
    {

        $result = ErrorCode::CODE_OK;
        //查询符合超期兑换条件的 记录
        //抓取成功 result=1， 寄存中 status =1， 超期时间大于7天
        log::info("_wait_timeout_exchange: start MaxDate= " . $MaxDate);
        $db_result = Db::name('TRoomGameResult');
        //默认为一周 7天
        //自定义天数
        $tmpDateStr = "-" . $MaxDate . " day";
        $tmpPreDate = date("Y-m-d 00-00-00",strtotime($tmpDateStr));

        $timeoutRecord = $db_result->where('create_at', '<', $tmpPreDate)
            ->where('result', ErrorCode::BABY_CATCH_SUCCESS)   //抓取成功
            ->where('status', ErrorCode::BABY_POST_IN)    //寄存中
            ->limit(50)
            ->select();

        $allCount = count($timeoutRecord);
        $posCount = 0;
        foreach($timeoutRecord as $key => $record){
            $userId = isset($record['user_id']) ? $record['user_id'] : '';
            $order_id = isset($record['order_id']) ? $record['order_id'] : '';

            //更新游戏结果表为已换币
            //更新抓取结果状态  由于是重复定时执行， 这里先更新状态，然后再充值
            $result = $this->updateResultStatus($userId, $order_id, ErrorCode::BABY_POST_DONE);
            if($result != ErrorCode::CODE_OK){
                //更新系统日志
                log::error("_wait_timeout_exchange: update status failed " . " allCount=" . $allCount ." userId=" . $userId  ." order_id=" . $order_id);
                break;
            }else{
                //更新系统日志

            }

            // 调用接口，兑换成相应的娃娃币
            $result = $this->exchangeUserCoin($userId, $order_id, '超期兑换');
            if($result != ErrorCode::CODE_OK){
                //更新系统日志
                log::error("_wait_timeout_exchange: exchange coin failed " . " allCount=" . $allCount ." userId=" . $userId  ." order_id=" . $order_id);
                break;
            }


            $posCount = $posCount + 1;
        }

        if($result != ErrorCode::CODE_OK)
        {
            log::error("_wait_timeout_exchange: exchange coin failed " . " allCount=" . $allCount ." posCount=" . $posCount);

        }else{
            log::info("_wait_timeout_exchange: exchange done" . " allCount=" . $allCount ." posCount=" . $posCount);
        }

        log::info("_wait_timeout_exchange: end MaxDate= " . $MaxDate);
        return $result;

    }



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


