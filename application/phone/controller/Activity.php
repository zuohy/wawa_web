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
use service\ErrorCode;
use think\Db;
use think\View;
use think\Log;
use service\ActivityService;
/**
 * 活动 统计等
 * Class Index
 * @package app\admin\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/02/15 10:41
 */

class Activity extends BasicBaby
{


    /**
     * 指定当前数据表
     * @var string
     */
    public $table = 'TActConfig';

    /**
     * 活动列表
     * @return View
     */
    public function index()
    {

        $userId = session('user_id');

        $this->title = '活动' . $userId;
        $db = Db::name($this->table)->where(['is_deleted' => '0']);

        return parent::_list($db);
    }

    /**
     * 活动详情
     * @return View
     */
    public function page()
    {
        $proCode = $this->request->get('product_code');
        $userId = session('user_id');

        //分享 收徒数量
        $actNum = ActivityService::getShareNum($proCode, $userId);
        $shareNum = isset($actNum['share_num']) ? $actNum['share_num'] : '';
        $shareValid = isset($actNum['share_valid']) ? $actNum['share_valid'] : '';
        $this->assign('share_num', $shareNum);
        $this->assign('share_valid', $shareValid);

        //分享 排名 top3

        return view('', ['title' => '活动']);
    }

    /**
     * 列表数据处理
     * @param type $list
     */
    protected function _data_filter(&$list)
    {

        foreach ($list as &$vo) {
            //转换为中文字符

        }

    }


}
