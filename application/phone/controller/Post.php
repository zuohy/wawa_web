<?php
/**
 * Created by PhpStorm.
 * User: tguo
 * Date: 2018/4/23
 * Time: 8:59
 */

namespace app\phone\controller;

use controller\BasicBaby;
use service\ErrorCode;
use think\Db;
use think\View;
use service\DataService;
use service\RoomService;
/**
 * 个人中心业务
 * Class Personal
 * @package app\phone\controller
 */
class Post extends BasicBaby
{

    /**
     * 个人中心
     * @return View
     */
    public function index()
    {

        return view('', ['title' => '个人中心']);
    }


    /**
     * 抓取成功记录
     * @return View
     */
    public function getresult()
    {
        $status = isset($_GET['status']) ? $_GET['status'] : 0; //0 全部 1 寄存中 2 待邮寄 3 已发货 4 已换币
        $addressId = isset($_GET['address_id']) ? $_GET['address_id'] : 0;
        $userId = session('user_id');
        $this->title = '我的娃娃';

        $field = ["user_id" => $userId, "result" => ErrorCode::BABY_CATCH_SUCCESS];
        if($status != 0)
        {
            $field['status'] = $status;
        }

        $db = Db::name('TRoomGameResult');
        $db->where($field)->order('create_at desc');

        //获取地址信息
        if($addressId != 0){
            //用户选择了地址信息
            $addressInfo = $this->getAddressInfo($addressId);
        }else{
            $addressInfo = $this->getAddressByUser($userId);
        }

        $addressId = isset($addressInfo['id']) ? $addressInfo['id'] : '';
        $name = isset($addressInfo['name']) ? $addressInfo['name'] : '';
        $phone = isset($addressInfo['phone']) ? $addressInfo['phone'] : '';
        $address = isset($addressInfo['address']) ? $addressInfo['address'] : '';

        $this->assign('address_id', $addressId);
        $this->assign('name', $name);
        $this->assign('phone', $phone);
        $this->assign('address', $address);

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
                $vo['gift_price'] = isset($giftInfo['gift_price']) ? $giftInfo['gift_price'] : '';
            }else{
                $vo['gift_pic_show'] =  '';
                $vo['gift_name'] = '';
                $vo['gift_price'] = '';
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


    /**
     * 收货地址列表 管理收货地址
     * @return View
     */
    public function address()
    {
        $userId = session('user_id');
        $status = isset($_GET['status']) ? $_GET['status'] : 1; //0 全部 1 寄存中 2 待邮寄 3 已发货 4 已换币
        $isChoose = isset($_GET['is_choose']) ? $_GET['is_choose'] : 0;  //来自寄存申请 选择地址信息
        $addressId = isset($_GET['address_id']) ? $_GET['address_id'] : 0;  //来自寄存申请 选择地址信息
        $this->title = '地址管理';

        $this->assign('status', $status);
        $this->assign('is_choose', $isChoose);
        $this->assign('address_id', $addressId);

        $field = ["user_id" => $userId, "is_deleted" => '0'];
        $db = Db::name('TUserPostalAddress');
        $db->where($field)->order('create_at desc');
        return parent::_list($db);
    }

    /**
     * 编辑收货地址页面
     * @return View
     */
    public function edit()
    {
        $address_id = isset($_GET['id']) ? $_GET['id'] : 0;
        $userId = session('user_id');

        $addressInfo = $this->getAddressInfo($address_id);
        if($addressInfo == ''){
            $addressInfo['name'] = '';
            $addressInfo['phone'] = '';
            $addressInfo['address'] = '';
        }
        return view('', ['address_id' => $address_id, 'address_info' => $addressInfo, 'title' => '收货地址' ]);
    }

    /**
     * 保存收货地址数据
     * @return View
     */
    public function save()
    {
        $userId = session('user_id');
        $add_id = isset($_POST['address_id']) ? $_POST['address_id'] : '';
        $add_name = isset($_POST['name']) ? $_POST['name'] : '';
        $add_phone = isset($_POST['phone']) ? $_POST['phone'] : '';
        $add_address = isset($_POST['address']) ? $_POST['address'] : '';
        $add_is_default = isset($_POST['is_default']) ? $_POST['is_default'] : '';

        $recordResult = $this->updateAddressInfo($add_id, $userId, $add_name, $add_phone, $add_address, $add_is_default);
        if($recordResult == ErrorCode::CODE_OK)
        {
            $this->success("提交收货地址成功！");
        }else{
            $this->error("提交失败，请稍后再试");
        }
    }

    /**
     * 保存收货地址数据
     * @return View
     */
    public function delete()
    {
        $userId = session('user_id');
        $add_id = isset($_POST['address_id']) ? $_POST['address_id'] : '';

        $recordResult = $this->deleteAddressInfo($add_id, $userId);
        if($recordResult == ErrorCode::CODE_OK)
        {
            $this->success("删除地址成功！");
        }else{
            $this->error("请求失败，请稍后再试");
        }
    }


    /**
     * 提交申请邮寄
     */
    public function applyPost()
    {
        $userId = session('user_id');

        $this->title = '申请邮寄';

        $order_id = isset($_POST['order_id']) ? $_POST['order_id'] : '';
        $address_id = isset($_POST['address_id']) ? $_POST['address_id'] : '';

        //异常检查
        if($order_id == ''){
            $this->error("提交失败，请稍后再试");
        }
        if($address_id == ''){
            $this->error("提交失败，请填写地址！");
        }
        //大于两个娃娃才能申请邮寄
        $inCount = $this->getResultList($userId, ErrorCode::BABY_CATCH_SUCCESS, ErrorCode::BABY_POST_IN, 1);
        if( $inCount < ErrorCode::BABY_POST_MIN_NUM ){
            $this->error("提交失败，至少两个娃娃才能邮寄！");
        }
        //存入结果表
        $db_record = Db::name('TUserApplyRecord');
        $recordData = [
            "address_id" => $address_id,
            "order_id" => $order_id,
            "user_id" => $userId,
            "req_type" => ErrorCode::BABY_POST_WAIT,
            "status" => ErrorCode::BABY_APPLY_HANDLE,
            "remark" => "邮寄",
            "create_at" => date('Y-m-d H:i:s',time())
        ];
        $recordResult = DataService::save($db_record, $recordData);

        //更新抓取结果状态
        $this->updateResultStatus($userId, $order_id, ErrorCode::BABY_POST_WAIT);

        if($recordResult)
        {
            $this->success("提交收货地址成功，请等待后台发货！");
        }else{
            $this->error("提交失败，请稍后再试");
        }

    }


    /**
     * 兑换金币
     */
    public function exchangeCoin()
    {
        $order_id = isset($_POST['order_id']) ? $_POST['order_id'] : 0;

        $userId = session('user_id');

        // 调用接口，兑换成相应的娃娃币
        $result = $this->exchangeUserCoin($userId, $order_id);
        if($result != ErrorCode::CODE_OK)
        {
            $this->error("兑换失败");
            return;
        }
        //更新游戏结果表为已换币
        //更新抓取结果状态
        $retStatus = $this->updateResultStatus($userId, $order_id, ErrorCode::BABY_POST_DONE);
        if($retStatus != ErrorCode::CODE_OK){
            $this->error("兑换失败！");
            return;
        }else{
            $this->success("兑换成功！");
            return;
        }

    }


}