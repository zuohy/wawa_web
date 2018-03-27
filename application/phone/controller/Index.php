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
use service\DataService;
use service\NodeService;
use service\ToolsService;
use think\Db;
use think\View;

/**
 * 手机入口
 * Class Index
 * @package app\admin\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/02/15 10:41
 */
class Index extends BasicBaby
{

    /**
     * 指定当前数据表
     * @var string
     */
    public $table = 'TRoomInfo';

    /**
     * 手机框架布局
     * @return View
     */
    public function index()
    {

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
     * 手机首页
     * @return View
     */
    public function main()
    {
        $this->title = '首页';
        $db = Db::name($this->table)->where(['is_deleted' => '0']);

        return parent::_list($db);

    }


    /**
     * 列表数据处理
     * @param type $list
     */
    protected function _data_filter(&$list)
    {

        foreach ($list as &$vo) {
            //转换为中文字符
            if($vo['status'] == 0){
                $vo['status_c'] = '空闲';
            }else{
                $vo['status_c'] = '游戏中';
            }
            if($vo['tag'] == 0){
                $vo['tag_c'] = '普通模式';
            }else{
                $vo['tag_c'] = '英雄模式';
            }
        }

    }

    /**
     * 主机登录
     * @return View
     */
    public function mainLogin()
    {

        $this->success('登录成功，正在进入系统...');
    }



}


