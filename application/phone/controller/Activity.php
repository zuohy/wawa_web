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
        $db = Db::name($this->table)->where(['is_deleted' => '0'])
            ->whereBetween('icons_type', [ErrorCode::BABY_COIN_TYPE_SHARE+1, "1000"]);;

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
        $this->assign('code', $tmpCode);
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
        $actAudio = isset($actInfo['act_audio']) ? $actInfo['act_audio'] : '';
        $actPicShow = isset($actInfo['act_pic_show']) ? $actInfo['act_pic_show'] : '';
        $iconsType = isset($actInfo['icons_type']) ? $actInfo['icons_type'] : '';
        $actPrice = isset($actInfo['act_price']) ? $actInfo['act_price'] : '';
        $payPrice = $this->coverPayValue(ErrorCode::BABY_COVER_TYPE_CNY, $actPrice);

        //提供商图片 提供商信息
        $vendorPic1 = isset($actInfo['act_form_1']) ? $actInfo['act_form_1'] : '';
        $vendorPic2 = isset($actInfo['act_form_2']) ? $actInfo['act_form_2'] : '';
        $vendorUserId = isset($actInfo['user_id']) ? $actInfo['user_id'] : '';
        $tmpVendorUser = $this->getUserInfo($vendorUserId);
        $vendorPhone = isset($tmpVendorUser['phone']) ? $tmpVendorUser['phone'] : '';

        $this->assign('vendor_phone', $vendorPhone);
        $this->assign('vendor_pic1', $vendorPic1);
        $this->assign('vendor_pic2', $vendorPic2);
        //活动音乐
        $this->assign('act_audio', $actAudio);

        $this->assign('act_pic_show', $actPicShow);
        $this->assign('pay_price', $payPrice);
        $this->assign('icons_type', $iconsType);

        //规则说明数组
        $desArr = ActivityService::createDesArr($actInfo['describe']);
        $this->assign('des_arr', $desArr);

        //获取当前用户的排名，与上一排名分享差值
        $sharePosInfo = ActivityService::getUserSharePos($proCode, $tmpCode);
        $this->assign('share_pos', $sharePosInfo);

        //分享 排名 top3
        $db_num = Db::name('TUserShareNum');
        $dbArr = $db_num->where('product_code', $proCode)->order('share_valid desc, update_at desc')->limit(3);



        $this->title = '活动';
        return parent::_list($dbArr);
    }

    /**
     * 列表数据处理
     * @param type $list
     */
    protected function _data_filter(&$list)
    {

        foreach ($list as $key => &$vo) {

            //转换 去掉 时间
            $endTime = isset($vo['act_end']) ? $vo['act_end'] : '';
            $sStatus = isset($vo['s_status']) ? $vo['s_status'] : ''; // 分享状态

            if($endTime != ''){
                $endDate = explode(' ', $endTime);
                $vo['act_end_date'] = $endDate[0];
            }
            if($sStatus == ErrorCode::BABY_SHARE_SUCCESS){
                $vo['s_status_c'] = '成功';
            }elseif($sStatus > ErrorCode::BABY_SHARE_SUCCESS){
                $vo['s_status_c'] = '未成功';
            }


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


    /**
     * 分享徒弟列表
     * @return View
     */
    public function disciple()
    {
        $userId = session('user_id');
        $proCode = $this->request->post('product_code');
        $userCode = $this->request->post('code');

        $tudiList = ActivityService::getShareHisList($proCode, $userCode, '', 0);  //获取成功分享和失败分享所有记录

        //$logData = json_encode($tudiList);
       // Log::info("disciple: get disciple= " . $logData );

        $this->assign('title', '徒弟列表');
        $this->success("成功！", null, $tudiList);

    }

}
