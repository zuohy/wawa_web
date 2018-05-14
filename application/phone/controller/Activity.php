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

        $tmpUserInfo = $this->getUserInfo($userId);
        $tmpCode = isset($tmpUserInfo['code']) ? $tmpUserInfo['code'] : '';
        $tmpPhone = isset($tmpUserInfo['phone']) ? $tmpUserInfo['phone'] : '';
        $this->assign('phone', $tmpPhone);
        $this->assign('product_code', $proCode);

        //分享 收徒数量
        $actNum = ActivityService::getShareNum($proCode, $tmpCode);
        $shareNum = isset($actNum['share_num']) ? $actNum['share_num'] : '';
        $shareValid = isset($actNum['share_valid']) ? $actNum['share_valid'] : '';
        $this->assign('share_num', $shareNum);
        $this->assign('share_valid', $shareValid);

        //活动信息
        $actInfo = ActivityService::getActInfo($proCode);
        //活动价格
        $iconsType = isset($actInfo['icons_type']) ? $actInfo['icons_type'] : '';
        $actPrice = isset($actInfo['act_price']) ? $actInfo['act_price'] : '';
        $payPrice = $this->coverPayValue(ErrorCode::BABY_COVER_TYPE_CNY, $actPrice);

        $this->assign('pay_price', $payPrice);
        $this->assign('icons_type', $iconsType);

        //规则说明数组
        $desArr = ActivityService::createDesArr($actInfo['describe']);
        $this->assign('des_arr', $desArr);
        //分享 排名 top3
        $db_num = Db::name('TUserShareNum');
        $dbArr = $db_num->where('product_code', $proCode)->order('share_valid desc');

        $this->title = '活动';
        return parent::_list($dbArr);
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


    /**
     * 填写手机号
     * @return View
     */
    public function phone()
    {
        $userId = session('user_id');

        $userInfo = $this->getUserInfo($userId);
        $name = isset($userInfo['name']) ? $userInfo['name'] : '';
        $phone = isset($userInfo['phone']) ? $userInfo['phone'] : '';

        return view('', ['name' => $name, 'phone' => $phone, 'title' => '活动电话' ]);
    }

    /**
     * 保存手机号
     * @return View
     */
    public function save()
    {
        $phone = $this->request->post('phone');
        $userId = session('user_id');
        $inUserInfo = array(
            'phone' => $phone
        );
        $userResult = $this->updateUserInfo($userId, $inUserInfo);


       if($userResult == ErrorCode::CODE_OK)
       {
           $this->success("保存成功！");
       }else{
           $this->error("保存失败，请稍后再试");
       }
    }

}
