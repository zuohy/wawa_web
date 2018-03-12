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
use service\WebsocketService;
/**
 * 手机入口
 * Class Index
 * @package app\admin\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/02/15 10:41
 */
class Index extends BasicAdmin
{

    /**
     * 后台框架布局
     * @return View
     */
    public function index()
    {
        NodeService::applyAuthNode();
        $list = (array) Db::name('SystemMenu')->where(['status' => '1'])->order('sort asc,id asc')->select();
        $menus = $this->_filterMenuData(ToolsService::arr2tree($list), NodeService::get(), !!session('user'));
        return view('', ['title' => '客户端']);
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
        foreach ($menus as $key => &$menu) {
            !empty($menu['sub']) && $menu['sub'] = $this->_filterMenuData($menu['sub'], $nodes, $isLogin);
            if (!empty($menu['sub'])) {
                $menu['url'] = '#';
            } elseif (preg_match('/^https?\:/i', $menu['url'])) {
                continue;
            } elseif ($menu['url'] !== '#') {
                $node = join('/', array_slice(explode('/', preg_replace('/[\W]/', '/', $menu['url'])), 0, 3));
                $menu['url'] = url($menu['url']);
                if (isset($nodes[$node]) && $nodes[$node]['is_login'] && empty($isLogin)) {
                    unset($menus[$key]);
                } elseif (isset($nodes[$node]) && $nodes[$node]['is_auth'] && $isLogin && !auth($node)) {
                    unset($menus[$key]);
                }
            } else {
                unset($menus[$key]);
            }
        }
        return $menus;
    }

    /**
     * 主机信息显示
     * @return View
     */
    public function main()
    {
        $_version = Db::query('select version() as ver');
/*
        WebsocketService::getWsUrl('1', '2', '3', 'true');
        WebsocketService::sendCoinsData('16025821436281');

        WebsocketService::sendControlData('r');
        sleep(1);
        WebsocketService::sendControlData('rr');
        sleep(5);
        WebsocketService::sendControlData('g');
        */
        return view('', ['mysql_ver' => array_pop($_version)['ver'], 'title' => '首页']);
    }


    /**
     * 主机信息显示
     * @return View
     */
    public function mainLogin()
    {
        $_version = Db::query('select version() as ver');
        $this->success('登录成功，正在进入系统...');
    }

    /**
     * 主机信息显示
     * @return View
     */
    public function personal()
    {
        $_version = Db::query('select version() as ver');
        return view('', ['mysql_ver' => array_pop($_version)['ver'], 'title' => '个人中心']);
    }



}


