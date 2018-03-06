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

use controller\BasicAdmin;
use service\DataService;
use service\NodeService;
use service\ToolsService;
use think\Db;
use think\View;

/**
 * 手机个人信息
 * Class Index
 * @package app\admin\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/02/15 10:41
 */
class Personal extends BasicAdmin
{

    /**
     * 后台框架布局
     * @return View
     */
    public function index()
    {
        NodeService::applyAuthNode();
        $list = (array) Db::name('SystemMenu')->where(['status' => '1'])->order('sort asc,id asc')->select();
        //$menus = $this->_filterMenuData(ToolsService::arr2tree($list), NodeService::get(), !!session('user'));
        return view('', ['title' => '个人中心']);
    }


}
