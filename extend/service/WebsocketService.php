<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2017 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://think.ctolog.com
// +----------------------------------------------------------------------
// | 开源协议 ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | github开源项目：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

namespace service;

//use vakata\websocket\Client;
use service\HttpService;
use think\Log;
use WebSocket\Client;
/**
 * websocket 请求服务
 * Class WebsocketService
 * @package service
 * @author zuohy <zhywork@163.com>
 * @date 2018/03/10 15:32
 */
class WebsocketService
{
    private static $_host = '';
    private static $_port = '';
    private static $_path = '';
    private static $_origin = false;
    private static $_Socket = null;
    private static $_connected = false;
    private static $_client = null;

    private static $_cmdParams = array();
    private static $_cmdLayout = array(
    'id' => '',
    'method'  =>'',
    'params'  =>''
    );

    private static $cmdRet = array(
        'id' => '',
        'code'  =>'',
        'msg'  =>''
    );
/*
    public function __construct() { }

    public function __destruct()
    {
        $this->disconnect();
    }
*/
    private static function initCmd(){
        self::$_cmdLayout['id'] = '';
        self::$_cmdLayout['method'] = '';
        self::$_cmdLayout['params'] = '';

        //cmd params
        self::$_cmdParams = array();

        //ret msg
        self::$cmdRet['id'] = '';
        self::$cmdRet['code'] = '';
        self::$cmdRet['msg'] = '';
    }

    private static function buildCmd($cmd_method, $cmd_param=''){
        self::initCmd();


        switch($cmd_method){
            case 'insert_coins':
                self::$_cmdParams['out_trade_no'] = $cmd_param;

                self::$_cmdLayout['id'] = '123456';
                self::$_cmdLayout['method'] = 'insert_coins';
                self::$_cmdLayout['params'] = self::$_cmdParams;
                break;
            case 'control':
                self::$_cmdParams['operation'] = $cmd_param;

                self::$_cmdLayout['id'] = '123457';
                self::$_cmdLayout['method'] = 'control';
                self::$_cmdLayout['params'] = self::$_cmdParams;
                break;

            default:
                break;
        }

        $jsonCmd = json_encode(self::$_cmdLayout);
        return $jsonCmd;
    }

    /**
     * websocket 建立链接 并投币消息，
     * @param obj $client websocket 链接对象
     * @param string $data 命令参数
     * @param string $type 命令参数类型
     * @return bool|string
     */
    public static function sendCoinsData($client, $data, $type = 'json')
    {
        self::$_client = $client;
        $retMsg = '';

        if( !is_string($data)) {
            Log::error("Not a string data was given.", E_USER_WARNING);
            return $retMsg;
        }
        if (strlen($data) == 0)
        {
            Log::error("Not a string data content", E_USER_WARNING);
            return $retMsg;
        }


        $wsCmd = self::buildCmd('insert_coins', '16025821436281');

        //{"id": "123456","method": "insert_coins","params": {"out_trade_no": "16025821436283"}}
        self::$_client->send($wsCmd);
        Log::info("device send cmd: ", $wsCmd);

        $retMsg = self::$_client->receive();
        Log::info("device receive cmd: ", $retMsg);

        return $retMsg;
    }

    /**
     * sendControlData  控制设备命令，
     * @param obj $client websocket 链接对象
     * @param string $data 命令参数
     * @param string $type 命令参数类型
     * @return bool|string
     */
    public static function sendControlData($client, $data, $type = 'json')
    {
        self::$_client = $client;
        self::$_connected = $client->isConnected();
        $retMsg = '';

        if( !is_string($data)) {
            Log::error("Not a string data was given.", E_USER_WARNING);
            return $retMsg;
        }
        if (strlen($data) == 0)
        {
            return $retMsg;
        }

        $wsCmd = self::buildCmd('control', $data);

        //{"id": "123457","method": "control","params": {"operation": "u"}}
        self::$_client->send($wsCmd);
        Log::info("device send cmd: ", $wsCmd);

        $retMsg = self::$_client->receive();
        Log::info("device receive cmd: ", $retMsg);
        return $retMsg;
    }

    /**
     * getMsgData  获取 设备服务器websocket 推送的命令，
     * @param obj $client websocket 链接对象
     * @param string $data 命令参数
     * @param string $type 命令参数类型
     * @return bool|string
     */
    public static function getMsgData()
    {
        $retMsg = '';

        self::$_client->setTimeout(1);
        $retMsg = self::$_client->receive();
        Log::info("device receive cmd: ", $retMsg);
        return $retMsg;
    }

    /**
     * getWsUrl  获取 设备服务器websocket url 地址 60秒有效
     * @param string $host url 地址
     * @param string $port 命令参数
     * @param string $path 命令参数类型
     * @return bool|string
     */
    public static function getWsUrl($host, $port, $path, $origin = false)
    {

        self::$_port = $port;
        self::$_path = $path;
        self::$_origin = $origin;

        $jsonRet = HttpService::get("", [], 30, []);
        $stRet = json_decode($jsonRet);

        if($stRet->errcode != 0){

            return self::$_connected = false;
        }
        $stData = $stRet->data;
        $wsUrl = $stData->ws_url;

        self::$_host = $wsUrl;
        //"ws://ws1.open.wowgotcha.com:9090/play/7996d3e8ad7483d8d6c6d3475cd49265549a6430"
        self::$_client = new Client(self::$_host);
        $retMsg = self::$_client->receive();

        self::$_connected = true;
        return self::$_client;
    }

    public static function checkConnection()
    {
        self::$_connected = false;

        // send ping:
        self::$_connected = self::$_client->isConnected();

        return self::$_connected;
    }


    public static function disconnect()
    {
        self::$_connected = false;
        //close web socket
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


}


