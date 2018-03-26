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
     * 房间列表
     * @return View
     */
    public function index()
    {

        $controlUrl = 'http://120.77.61.179:2100/';
        return view('', ['title' => '房间', 'control_url' =>$controlUrl]);
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
