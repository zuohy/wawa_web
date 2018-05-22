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
/*
define('BABY_ROOM_MEMBER_STATUS_OUT', 1);   //未进入房间
define('BABY_ROOM_MEMBER_STATUS_IN', 2);   //进入房间
define('BABY_ROOM_MEMBER_STATUS_RUN', 3);   //正在游戏

define('BABY_ROOM_STATUS_BUILD', 0);   //修建中
define('BABY_ROOM_STATUS_ON', 1);   //空闲
define('BABY_ROOM_STATUS_BUSY', 2);   //忙碌 游戏
define('BABY_ROOM_STATUS_OFF', 3);   //下线 维护

define('BABY_ROOM_MODEL_COM', 0);   //普通模式
define('BABY_ROOM_MODEL_HERO', 1);   //英雄模式

define('BABY_EMPLOY_TYPE_FREE', 0);   //娃娃币
define('BABY_EMPLOY_TYPE_COIN', 1);   //金币
*/
class ErrorCode
{
	public static $OK = 0;
	public static $IllegalAesKey = -41001;
	public static $IllegalIv = -41002;
	public static $IllegalBuffer = -41003;
	public static $DecodeBase64Error = -41004;


    //常量
    //用户相关
    const BABY_USER_NO_RECHARGE = 0;  //不是为充值用户
    const BABY_USER_OK_RECHARGE = 1;  //是为充值用户

    //收益 常量
    const BABY_INCOME_NEW = 0;    //生成收益记录
    const BABY_INCOME_DONE = 1;    //处理收益完成
    const BABY_INCOME_FAILED = 2;    //处理收益异常
    const BABY_INCOME_BACK_CNY = 1;    //收益直接返现金
    const BABY_INCOME_BACK_COIN = 0;    //收益返金币
    const BABY_INCOME_BACK_TRUE = 9;    //是为充值收益金币

    //支付常量
    const BABY_PAY_SUCCESS = 1;      //支付成功  微信接口
    const BABY_PAY_FAILED = 0;      //支付失败   微信接口

    const BABY_SHARE_SUCCESS = 0;       //分享成功  有效分享
    const BABY_SHARE_FAILED = 1;        //分享失败 用户已经被分享了 app 应用
    const BABY_SHARE_NOT_PAY = 2;        //分享但未付款 活动 产品
    const BABY_SHARE_NO_ACCEPT = 3;        //分享失败 没有被分享者信息
    const BABY_SHARE_NO_INVITATION = 9;        //分享失败 没有邀请者信息


    //随机数 头标签
    const BABY_HEADER_SEQ_APP = 'A-00001';    //app 应用默认值 字母开头
    const BABY_HEADER_SEQ_A = 'A';    //字母开口的随机数 app应用
    const BABY_HEADER_SEQ_S = 'S';    //字母开口的随机数 商品
    const BABY_HEADER_SEQ_H = 'H';    //字母开口的随机数 活动
    const BABY_HEADER_SEQ_IN = 'I';    //收益随机数
    const BABY_HEADER_SEQ_COST = 'C';    //消费随机数

    const BABY_COVER_TYPE_COIN = 1;    //转换为金币(角)
    const BABY_COVER_TYPE_PAY = 2;    //转换为分 微信支付单位
    const BABY_COVER_TYPE_CNY = 3;    //金币 转换为元
    const BABY_COVER_TYPE_FEE = 4;    //金币 转换为分

    const BABY_COIN_TYPE_REG_1 = 1;    //充值类型1  首次充值 固定
    const BABY_COIN_TYPE_REG_2 = 2;    //充值类型2
    const BABY_COIN_TYPE_SHARE = 20;  //分享类型
    //const BABY_COIN_TYPE_CNY = 100;  //分享类型 直接存入金币 暂时未用

    //房间 常量
    const BABY_ROOM_MEMBER_STATUS_OUT = 1;   //未进入房间
    const BABY_ROOM_MEMBER_STATUS_IN = 2;   //进入房间
    const BABY_ROOM_MEMBER_STATUS_RUN = 3;   //正在游戏

    const BABY_ROOM_STATUS_BUILD = 0;   //修建中
    const BABY_ROOM_STATUS_ON = 1;   //空闲
    const BABY_ROOM_STATUS_BUSY = 2;   //忙碌 游戏
    const BABY_ROOM_STATUS_OFF = 3;   //下线 维护

    const BABY_ROOM_MODEL_COM = 0;   //普通模式
    const BABY_ROOM_MODEL_HERO = 1;   //英雄模式

    //消费 常量
    const BABY_EMPLOY_TYPE_FREE = 0;   //娃娃币
    const BABY_EMPLOY_TYPE_COIN = 1;   //金币

    const BABY_EMPLOY_REASON_1 = 1;   //投币游戏
    const BABY_EMPLOY_REASON_2 = 2;   //支援游戏
    const BABY_EMPLOY_REASON_3 = 3;   //购买商品

    const BABY_CATCH_SUCCESS = 1;   //抓取成功
    const BABY_CATCH_FAIL = 0;   //抓取失败

    //邮寄常量
    const BABY_POST_ALL = 0;   //所有状态
    const BABY_POST_IN = 1;   //寄存中
    const BABY_POST_WAIT = 2;   //待邮寄
    const BABY_POST_TO = 3;   //已发货
    const BABY_POST_DONE = 4;   //已兑换
    const BABY_POST_MIN_NUM = 2;   //最少邮寄娃娃数量

    const BABY_APPLY_HANDLE = 0;   //用户申请待处理
    const BABY_APPLY_DONE = 1;   //用户申请处理完成
    //
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
    const E_USER_COIN_LACK = -2003;    //金币币 不足
    const E_USER_FREE_COIN_LACK = -2004;    //娃娃币 不足
    const E_USER_INSERT_COIN_ERROR = -2005;  //投币金额不正确
    const E_USER_EMPLOY_COIN_ERROR = -2006;  //用户消费金额错误
    const E_USER_COUNT_COIN_ERROR = -2007;  //用户消费金额计算错误
    const E_USER_INCOME_MAX = -2008;  //用户返现超过最大值 10元
    const E_USER_INCOME_FAIL = -2009;  //用户增加 币 失败
    const E_USER_NOT_FIRST_RECHARGE = -2010;  //用户不是首次充值

    const E_ROOM_STATUS_ERROR = -2101;       //房间状态不正确
    const E_ROOM_USER_STATUS_ERROR = -2102;  //房间成员状态不正确
    const E_ROOM_UPDATE_FAIL = -2103;         //房间更新失败
    const E_ROOM_STATUS_UPDATE_ERROR = -2104;  //房间状态更新错误

    const E_DEV_COINS_STATUS_ERROR = -2202;  //设备 投币错误
    const E_DEV_GAME_TIME_OUT = -2203;  //设备 操作超时

    //通用错误
    const E_NOT_SUPPORT = -3001;  //通用错误 不支持的操作
    const E_PAY_ALREADY_SUCCESS = -3002;      //已经支付成功
    const E_PAY_PICKER_FAILED = -3003;      //创建微信支付订单失败
    const E_NOTIFY_MSG_NULL = -3004;       //没有需要通知的消息

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
        self::E_USER_EMPLOY_COIN_ERROR => 'user employ coin error',
        self::E_USER_COUNT_COIN_ERROR => 'user employ coin count error',
        self::E_USER_NOT_FIRST_RECHARGE => 'user is not first recharge',

        self::E_ROOM_STATUS_ERROR => 'room status error',
        self::E_ROOM_USER_STATUS_ERROR => 'room member status error',
        self::E_ROOM_UPDATE_FAIL => 'room information update failed',
        self::E_ROOM_STATUS_UPDATE_ERROR => 'room status update error',

        self::E_DEV_COINS_STATUS_ERROR => 'device insert coins failed',
        self::E_DEV_GAME_TIME_OUT => 'device game time out',

        self::E_NOT_SUPPORT => 'not support this step',
        self::E_PAY_ALREADY_SUCCESS => 'pay already done',
        self::E_PAY_PICKER_FAILED => 'create picker failed',
        self::E_NOTIFY_MSG_NULL => 'no notify message',
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
        self::E_USER_EMPLOY_COIN_ERROR => '消费金额错误',
        self::E_USER_COUNT_COIN_ERROR => '消费金额计算错误',
        self::E_USER_NOT_FIRST_RECHARGE => '您不是首充用户，请选择其它充值',

        self::E_ROOM_STATUS_ERROR => '房间状态错误',
        self::E_ROOM_USER_STATUS_ERROR => '用户状态错误',
        self::E_ROOM_STATUS_UPDATE_ERROR => '房间状态更新错误',

        self::E_DEV_COINS_STATUS_ERROR => '投币失败',
        self::E_DEV_GAME_TIME_OUT => '抓取超时失败',

        self::E_NOT_SUPPORT => '未知操作',
        self::E_PAY_ALREADY_SUCCESS => '支付已经完成',
        self::E_PAY_PICKER_FAILED => '创建预支付订单失败',
    );  //error code msg

    //通知消息
    const I_USER_JOIN_ROOM = 1001;
    const I_USER_EXIT_ROOM = 1002;
    const I_USER_INSERT_COINS_FROZEN = 1003;
    const I_USER_CATCH_FAIL = 1004;
    const I_USER_CATCH_SUCCESS = 1005;
    public static $INFO_MSG = array(
        self::I_USER_JOIN_ROOM => '进入房间',
        self::I_USER_EXIT_ROOM => '退出房间',
        self::I_USER_INSERT_COINS_FROZEN => '投币冻结',
        self::I_USER_CATCH_FAIL => '很遗憾抓取失败',
        self::I_USER_CATCH_SUCCESS => '恭喜抓取成功',
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