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
use think\Log;
/**
 * 手机钱包信息
 * Class Index
 * @package app\admin\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/02/15 10:41
 */

class Wallet extends BasicBaby
{

    /**
     * 个人钱包列表
     * @return View
     */
    public function index()
    {
        $openId = session('open_id');
        return view('', ['title' => '个人钱包' . $openId]);
    }


    /**
     * 支付订单
     * @return 订单参数
     */
    public function recharge()
    {

        //$this->getReceiptList();
        $this->title = '充值';
        $db = Db::name('TUserReceiptFree');
        $db->whereBetween('icons_type', ["0", "19"]);
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

        }

    }



    /**
     * 充值
     * @return 订单参数
     */
    public function payment()
    {
        //获取openid
        $openId = session('open_id');
        $userId = session('user_id');
        $unionId = session('union_id');
        $payValue = 1; //1分
        $coinValue = 0; //0 个金币

        Log::info("recharge: openId= " . $openId);
        Log::info("recharge: userId= " . $userId);
        Log::info("recharge: unionId= " . $unionId);

        //获取pay value
        $iconsType = isset($_POST['icons_type']) ? $_POST['icons_type'] : '';

        $iconsArr = $this->getPayValue($iconsType);
        //数据库中充值单位为元， 支付接口单位为 分， 所以这里需要转换金额 1元= 100分
        $payValue = $this->coverPayValue(WAWA_COVER_TYPE_PAY, $iconsArr['pay_value']);
        //$payValue = $iconsArr['pay_value'];  //测试 数据
        $optionsArr = $this->miniPay($openId, $payValue);

        $payOptions = json_encode($optionsArr);

        //生成用户订单信息
        Log::info("recharge: options code= " . $optionsArr['code']);
        if( $optionsArr['code'] == 0 ){
            $this->saveReceipt($userId, $optionsArr['prepayId'], $iconsArr['pay_value'], $iconsArr['icons_type'], $optionsArr['order_no']);
        }

        //Log::info("recharge: payOptions= " .$payOptions);
        //$payOptions = '{"appId":"wx543f399af45d82ba","timeStamp":"1522488580","nonceStr":"yts0bf55hcmywxm5p9m7gh7ho0lr0w1k",
        //"package":"prepay_id=wx201803311610441ca39aa76f0719651834","signType":"MD5","paySign":"149402D8908D4DECCA3ACAA0D46D05FD",
        //"timestamp":"1522488580","order_no":"2850982462"}';
        return $payOptions;
    }


    /**
     * 充值
     * @return 订单参数
     */
    public function paymentResult()
    {
        $orderNo = isset($_GET['order_no']) ? $_GET['order_no'] : '';
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        Log::info("paymentResult: start orderNo=" . $orderNo . " status=" . $status);

        $retBool = $this->completeMiniPay($orderNo, $status);

        Log::info("paymentResult: end orderNo=" . $orderNo . " retBool=" . $retBool);

        return $status;
    }
}
