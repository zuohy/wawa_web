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
/**
 * 房间信息
 * Class Index
 * @package app\admin\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/02/15 10:41
 */

class Room extends BasicBaby
{

    /**
     * 房间
     * @return View
     */
    public function index()
    {

        $roomId = $this->request->get('room_id');

        //获取房间信息
        $tmpRoom = RoomService::getRoomInfo($roomId);

        //获取房间成员信息
        //$tmpUser = RoomService::getTopMemberList($roomId, '');
        //获取用户信息
        $userId = session('user_id');
        $tmpUser = $this->getUserInfo($userId);

        $price = isset($tmpRoom['price']) ? $tmpRoom['price'] : '';
        $coin = isset($tmpUser['coin']) ? $tmpUser['coin'] : '';
        $free_coin = isset($tmpUser['free_coin']) ? $tmpUser['free_coin'] : '';

        //保存房间信息到session
        session('room_id', $roomId);

        //获取设备控制服务器信息
        $controlAddress = sysconf('wa_control_url');
        $controlPort = sysconf('wa_control_port');
        $controlUrl = 'http://' . $controlAddress . ':' . $controlPort;

        return view('', ['title' => '房间', 'control_url' =>$controlUrl,
                     'price' =>$price, 'coin' => $coin, 'free_coin' => $free_coin, 'room_id' => $roomId, 'user_id' => $userId,]);
    }

    /**
     * 房间
     * @return View
     */
    public function room_frame()
    {

        return view('', ['title' => '房间']);
    }



}
