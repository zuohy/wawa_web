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
use think\View;
use service\RoomService;
use service\DeviceService;
/**
 * 房间设备信息
 * 说明： 房间和设备 一一对应 为真实操作设备的房间。t_dev_room_info
 * 用户房间为虚拟房间 和商品一一对应。t_room_info
 * Class Index
 * @package app\admin\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/02/15 10:41
 */

class Room extends BasicBaby
{

    /**
     * 房间信息
     * @return View
     */
    public function index()
    {

        $devRoomId = $this->request->get('dev_room_id');

        //保存房间设备ID信息到session
        session('dev_room_id', $devRoomId);

        //获取用户房间ID
        $uRoomId = session('room_id');
        //获取房间信息
        $tmpRoom = RoomService::getRoomInfo($uRoomId);

        //获取用户信息
        $userId = session('user_id');
        $tmpUser = $this->getUserInfo($userId);

        $memberCount = isset($tmpRoom['member_count']) ? $tmpRoom['member_count'] : '';
        $price = isset($tmpRoom['price']) ? $tmpRoom['price'] : '';
        $coin = isset($tmpUser['coin']) ? $tmpUser['coin'] : '';
        $free_coin = isset($tmpUser['free_coin']) ? $tmpUser['free_coin'] : '';

        //获取房间设备信息
        $tmpDevice = DeviceService::getDeviceInfo($devRoomId);
        $controlAddress = isset($tmpDevice['dev_ser_url']) ? $tmpDevice['dev_ser_url'] : '';
        $controlPort = isset($tmpDevice['dev_ser_port']) ? $tmpDevice['dev_ser_port'] : '';
        $devInfo = json_encode($tmpDevice);

        //$controlAddress = sysconf('wa_control_url');
        //$controlPort = sysconf('wa_control_port');
        $controlUrl = 'http://' . $controlAddress . ':' . $controlPort;

        return view('', ['title' => '房间', 'control_url' =>$controlUrl,
            'member_count' =>$memberCount, 'price' =>$price, 'coin' => $coin, 'free_coin' => $free_coin,  'user_id' => $userId,
                     'room_id' => $uRoomId, 'dev_room_id' => $devRoomId, 'dev_info' => $devInfo]);
    }

    /**
     * 房间
     * @return View
     */
    public function room_frame()
    {

        return view('', ['title' => '房间']);
    }

    public function edit()
    {
        $userId = session('user_id');
        return $this->_form('TUserConfig', 'form', 'user_id', [], ['user_id'=> $userId]);
    }

}
