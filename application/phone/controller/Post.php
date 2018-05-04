<?php
/**
 * Created by PhpStorm.
 * User: tguo
 * Date: 2018/4/23
 * Time: 8:59
 */

namespace app\phone\controller;

use controller\BasicBaby;
use think\Db;
use think\View;
use service\DataService;

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
     * 抓取记录
     * @return View
     */
    public function getresult()
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
        $db->where($field);
        return parent::_list($db);

    }

    /**
     * 收货地址页面
     * @return View
     */
    public function apply()
    {
        $order_id = isset($_GET['id']) ? $_GET['id'] : 0;
        $userId = session('user_id');
        //$userId = 3665019677;

        return view('', ['order_id' => $order_id, 'title' => '收货地址' ]);
    }


    /**
     * 提交申请邮寄
     */
    public function applyPost()
    {
        $userId = session('user_id');
        //$userId = 3665019677;
        $data = $_POST;
        $this->title = '申请邮寄';

        $user_name = $_POST['user_name'];
        $address = $_POST['address'];
        $phone = $_POST['phone'];
        $order_id = $_POST['order_id'];

        //查询是否是新地址
        $db_address = Db::name('TUserPostalAddress');
        $resultInfo = $db_address->where('user_id',$userId)->where('address',$address)->find();

        //不存在，新增地址
        if(!$resultInfo)
        {
            $addressData = [
                "user_id" => $userId,
                "address" => $address,
                "phone" => $phone,
                "create_at" => date('Y-m-d H:i:s',time())
            ];
            $result = DataService::save($db_address, $addressData, 'id', []);
            if($result)
            {
                $resultInfo = $db_address->where('user_id',$userId)->where('address',$address)->find();
            }
        }

        $address_id = $resultInfo['id'];

        //存入结果表
        $db_record = Db::name('TUserApplyRecord');
        $recordData = [
            "address_id" => $address_id,
            "order_id" => $order_id,
            "user_id" => $userId,
            "req_type" => 12,
            "status" => 0,
            "remark" => "",
            "create_at" => date('Y-m-d H:i:s',time())
        ];
        $recordResult = DataService::save($db_record, $recordData, 'id', []);

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
        $order_id = isset($_GET['id']) ? $_GET['id'] : 0;

        //todo 调用接口，兑换成相应的娃娃币

        //更新游戏结果表为已换币
        $db_result = Db::name('TRoomGameResult');
        $resultData = [
            "order_id" => $order_id,
            "status" => 4
        ];
        $result =  DataService::update($db_result, $resultData);

        if($result)
        {
            $this->success("兑换成功！");
        }else{
            $this->error("兑换失败");
        }
    }


}