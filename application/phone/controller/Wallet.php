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
use service\WebsocketService;
/**
 * 手机钱包信息
 * Class Index
 * @package app\admin\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/02/15 10:41
 */

class Wallet extends BasicBaby
{

   public  $webClient = null;
    /**
     * 个人钱包列表
     * @return View
     */
    public function index()
    {

        return view('', ['title' => '个人钱包']);
    }

    /**
     * 测试设备控制
     * @return View
     */
    public function dev_control_move()
    {

        $this->webClient = session('extClient');
        WebsocketService::sendControlData($this->webClient, 'r');
        sleep(1);
        WebsocketService::sendControlData($this->webClient, 'rr');

        return ;
    }

    /**
     * 测试设备控制
     * @return View
     */
    public function dev_control_fetch()
    {

        WebsocketService::sendControlData($this->webClient, 'g');
        $retMsg = WebsocketService::getMsgData();
        $retMsg = WebsocketService::getMsgData();
        return ;
    }

    /**
     * 测试设备控制
     * @return View
     */
    public function dev_start()
    {

        $this->webClient = WebsocketService::getWsUrl('1', '2', '3', 'true');
        WebsocketService::sendCoinsData($this->webClient, '16025821436281');
        session('extClient', $this->webClient);
        $this->success('投币成功！');
    }

}
