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
use service\RoomService;
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
     * 个人中心
     * @return View
     */
    public function index()
    {
        $userId = session('user_id');
        $inCount = $this->getResultList($userId, ErrorCode::BABY_CATCH_SUCCESS, ErrorCode::BABY_POST_IN, 1);
        $waitCount = $this->getResultList($userId, ErrorCode::BABY_CATCH_SUCCESS, ErrorCode::BABY_POST_WAIT, 1);

        $userInfo = $this->getUserInfo($userId);
        $userName = isset($userInfo['name']) ? $userInfo['name'] : '';
        $userFreeCoin = isset($userInfo['free_coin']) ? $userInfo['free_coin'] : '';
        $userPic = isset($userInfo['pic']) ? $userInfo['pic'] : '';
        $userId = isset($userInfo['user_id']) ? $userInfo['user_id'] : '';
        $minUserId = substr($userId,4); //截取后6位user ID

        return view('', ['title' => '个人中心', 'in_count' => $inCount, 'wait_count' => $waitCount,
            'min_user_id' => $minUserId, 'name' => $userName, 'free_coin' => $userFreeCoin,'pic' => $userPic]);

    }


    /**
     * 抓取记录
     * @return View
     */
    public function gresult()
    {

        $status = isset($_GET['status']) ? $_GET['status'] : 0; //0 全部 1 寄存中 2 待邮寄 3 已发货 4 已换币
        $userId = session('user_id');
        $this->title = '抓取记录';

        //$userId = 3665019677;
        $field = ["user_id" => $userId];
        if($status != 0)
        {
            $field['status'] = $status;
        }

        $db = Db::name('TRoomGameResult');
        $db->where($field)->order('create_at desc');
        return parent::_list($db);

    }

    /**
     * 列表数据处理
     * @param type $list
     */
    protected function _data_filter(&$list)
    {

        foreach ($list as &$vo) {

            $giftId = isset($vo['gift_id']) ? $vo['gift_id'] : '';
            $isCatch = isset($vo['result']) ? $vo['result'] : 0;   //是否抓取成功
            $status = isset($vo['status']) ? $vo['status'] : 1;   //抓取结果状态

            //获取礼物信息
            if( $giftId != '' ){
                $giftInfo = RoomService::getGiftInfo($vo['gift_id']);
                $vo['gift_pic_show'] = isset($giftInfo['gift_pic_show']) ? $giftInfo['gift_pic_show'] : '';
                $vo['gift_name'] = isset($giftInfo['gift_name']) ? $giftInfo['gift_name'] : '';
            }else{
                $vo['gift_pic_show'] =  '';
                $vo['gift_name'] = '';
            }
            if($isCatch == ErrorCode::BABY_CATCH_SUCCESS){
                $vo['result_c'] = '抓取成功';
            }else{
                $vo['result_c'] = '抓取失败';
            }

            if($status == ErrorCode::BABY_POST_IN){
                $vo['status_c'] = '寄存中';
            }elseif($status == ErrorCode::BABY_POST_WAIT){
                $vo['status_c'] = '待邮寄';
            }elseif($status == ErrorCode::BABY_POST_TO){
                $vo['status_c'] = '已发货';
            }elseif($status == ErrorCode::BABY_POST_DONE){
                $vo['status_c'] = '已兑换';
            }

        }

    }

}
