<?php

/**
 * error code 说明.
 * <ul>

 *    <li>-41001: encodingAesKey 非法</li>
 *    <li>-41003: aes 解密失败</li>
 *    <li>-41004: 解密后得到的buffer非法</li>
 *    <li>-41005: base64加密失败</li>
 *    <li>-41016: base64解密失败</li>
 * </ul>
 */

namespace service;
class ErrorCode
{
	public static $OK = 0;
	public static $IllegalAesKey = -41001;
	public static $IllegalIv = -41002;
	public static $IllegalBuffer = -41003;
	public static $DecodeBase64Error = -41004;


    //消息文案类型
    const MSG_TYPE_ERROR = 1;               //英文错误消息，用于服务器打印日志
    const MSG_TYPE_CLIENT_ERROR = 2;       //中文错误消息，用于返回客户端显示
    const MSG_TYPE_INFO = 3;                //通知客户端消息

    //Api wawa code  -1000 到 -2000
    const CODE_OK = 0;
    const CODE_NOT_POST = -1001;
    const CODE_NOT_SUPPORT = -1002;

    //web code  -2000 到 -3000
    const E_USER_NOT_FOUND = -2001;
    const E_USER_JOIN_ROOM_FAIL = -2002;
    const E_USER_COIN_LACK = -2003;    //娃娃币 不足
    const E_USER_FREE_COIN_LACK = -2004;    //娃娃币 不足
    const E_USER_INSERT_COIN_ERROR = -2005;  //投币金额不正确

    const E_ROOM_STATUS_ERROR = -2101;       //房间状态不正确
    const E_ROOM_USER_STATUS_ERROR = -2102;  //房间成员状态不正确

    const E_DEV_COINS_STATUS_ERROR = -2202;  //设备 投币错误
    const E_DEV_GAME_TIME_OUT = -2203;  //设备 操作超时


    public static $ERR_MSG = array(
        self::CODE_OK => 'ok',
        self::CODE_NOT_POST => 'no post msg',
        self::CODE_NOT_SUPPORT => 'no support msg',

        //web code  msg
        self::E_USER_NOT_FOUND => 'user not found',
        self::E_USER_JOIN_ROOM_FAIL => 'user join room failed',
        self::E_USER_COIN_LACK => 'user coin lack',
        self::E_USER_FREE_COIN_LACK => 'user free coin lack',
        self::E_USER_INSERT_COIN_ERROR => 'user insert coin error',

        self::E_ROOM_STATUS_ERROR => 'room status error',
        self::E_ROOM_USER_STATUS_ERROR => 'room member status error',

        self::E_DEV_COINS_STATUS_ERROR => 'device insert coins failed',
        self::E_DEV_GAME_TIME_OUT => 'device game time out',
    );  //error code msg

    //返回客户端的错误文案
    public static $ERR_MSG_C = array(
        self::CODE_OK => '成功',
        self::CODE_NOT_POST => 'no post msg',
        self::CODE_NOT_SUPPORT => 'no support msg',

        //web code  msg
        self::E_USER_NOT_FOUND => '请先登录',
        self::E_USER_JOIN_ROOM_FAIL => '加入房间失败',
        self::E_USER_COIN_LACK => '金币不足 请充值',
        self::E_USER_FREE_COIN_LACK => '娃娃币不足',
        self::E_USER_INSERT_COIN_ERROR => '投币金额错误',

        self::E_ROOM_STATUS_ERROR => '房间状态错误',
        self::E_ROOM_USER_STATUS_ERROR => '用户状态错误',

        self::E_DEV_COINS_STATUS_ERROR => '投币失败',
        self::E_DEV_GAME_TIME_OUT => '抓取超时失败',
    );  //error code msg

    //通知消息
    const I_USER_JOIN_ROOM = 1001;
    const I_USER_EXIT_ROOM = 1002;
    public static $INFO_MSG = array(
        self::I_USER_JOIN_ROOM => '进入房间',
        self::I_USER_EXIT_ROOM => '退出房间',


    );  //error code msg

    /**
     * 获取房间信息
     * @param int $msgType
     * @param int $msgCode
     * @return string
     */
    public static function buildMsg($msgType, $msgCode){

        $noMsg = '未知消息';
        $retMsg = '';
        switch($msgType){
            case self::MSG_TYPE_ERROR:

                $retMsg = isset(self::$ERR_MSG[$msgCode]) ? self::$ERR_MSG[$msgCode] : $noMsg;
                break;
            case self::MSG_TYPE_CLIENT_ERROR:
                $retMsg = isset(self::$ERR_MSG_C[$msgCode]) ? self::$ERR_MSG_C[$msgCode] : $noMsg;
                break;
            case self::MSG_TYPE_INFO:
                $retMsg = isset(self::$INFO_MSG[$msgCode]) ? self::$INFO_MSG[$msgCode] : $noMsg;
                break;
            default:
                break;
        }

        return $retMsg;

    }

}

?>