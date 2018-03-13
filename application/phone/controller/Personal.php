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
 * 手机个人信息
 * Class Index
 * @package app\admin\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/02/15 10:41
 */
class Personal extends BasicBaby
{

    /**
     * 后台框架布局
     * @return View
     */
    public function index()
    {

        return view('', ['title' => '个人中心']);
    }


}
