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
        $userId = session('user_id');
        $tmpUserInfo = $this->getUserInfo($userId);
        $userCoin = isset($tmpUserInfo['coin']) ? $tmpUserInfo['coin'] : '';
        $userFreeCoin = isset($tmpUserInfo['free_coin']) ? $tmpUserInfo['free_coin'] : '';
        $this->assign('user_coin', $userCoin/10);   //单位元
        $this->assign('free_coin', $userFreeCoin);

        return view('', ['title' => '个人钱包']);
    }


    /**
     * 支付订单
     * @return 订单参数
     */
    public function recharge()
    {
        $userId = session('user_id');
        $tmpUserInfo = $this->getUserInfo($userId);
        $userCoin = isset($tmpUserInfo['coin']) ? $tmpUserInfo['coin'] : '';
        $userFreeCoin = isset($tmpUserInfo['free_coin']) ? $tmpUserInfo['free_coin'] : '';
        $this->assign('coin', $userCoin);
        $this->assign('free_coin', $userFreeCoin);

        $this->title = '充值';
        $db = Db::name('TUserReceiptFree');
        $minType = ErrorCode::BABY_COIN_TYPE_REG_1;
        $maxType = ErrorCode::BABY_COIN_TYPE_SHARE - 1;
        $db->whereBetween('icons_type', [$minType, $maxType]);
        return parent::_list($db);
    }

    /**
     * 账单记录  包括消费记录和充值记录
     * @return 订单参数
     */
    public function bill()
    {
        $userId = session('user_id');
        $inType = isset($_GET['type']) ? $_GET['type'] : 1;  //1 返回充值账单， 2 返回消费账单, 3 收益账单

        $this->assign('in_type', $inType);
        if($inType == 1){
            //充值账单
            $this->title = '充值账单';
            $db = Db::name('TUserCommonpayReceipt');
            $field = ["user_id" => $userId, "receipt_type" => ErrorCode::BABY_PAY_SUCCESS];
            $db->where($field)->order('create_at desc');
            return parent::_list($db);
        }elseif( $inType == 2 ){
            //消费账单

            $this->title = '消费账单';
            $db = Db::name('TUserBill');
            $db->where('user_id', $userId)->order('create_at desc');
            return parent::_list($db);

        }elseif( $inType == 3 ){
            //收益账单
            $this->title = '收益账单';
            $db = Db::name('TUserIncome');
            $field = ["user_id" => $userId, "i_status" => ErrorCode::BABY_PAY_SUCCESS];

            $db->where($field)->order('create_at desc');
            return parent::_list($db);
        }

        //默认消费账单
        $this->title = '账单';
        $db = Db::name('TUserBill');
        $db->where('user_id', $userId)->order('create_at desc');
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
            $reason = isset($vo['reason']) ? $vo['reason'] : '';
            $iconsType = isset($vo['icons_type']) ? $vo['icons_type'] : '';
            $proCode = isset($vo['product_code']) ? $vo['product_code'] : '';

            if($reason == ErrorCode::BABY_EMPLOY_REASON_1){
                $vo['reason_c'] = '投币游戏';
            }

            //判断充值类型
            if( $iconsType >= ErrorCode::BABY_COIN_TYPE_REG_1  && $iconsType < ErrorCode::BABY_COIN_TYPE_SHARE){
                //充值
                $vo['reason_c'] = '充值';
                $vo['remark'] = '普通充值';
            }elseif( $iconsType == ErrorCode::BABY_COIN_TYPE_SHARE ){
                //分享
                $vo['reason_c'] = '分享';
                $vo['remark'] = '分享充值';
            }elseif( $iconsType > ErrorCode::BABY_COIN_TYPE_SHARE ){
                //购买
                $vo['reason_c'] = '购买';
                //$vo['remark'] = '产品编码' . $proCode;
            }

            //产品编码 加入 remark 字段
            if($proCode && $proCode != ErrorCode::BABY_HEADER_SEQ_APP){
                $vo['remark'] = $vo['remark'] . ' 产品编码 ' . $proCode;
            }
            //字段保护
            if( empty($vo['reason_c']) ){
                $vo['reason_c'] = '';
            }
            if( empty($vo['remark']) ){
                $vo['remark'] = '';
            }

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

        Log::info("recharge: openId= " . $openId);
        Log::info("recharge: userId= " . $userId);
        Log::info("recharge: unionId= " . $unionId);

        //获取pay value
        $iconsType = isset($_POST['icons_type']) ? $_POST['icons_type'] : '';

        $iconsArr = $this->getPayValue($iconsType);
        //数据库中充值单位为元， 支付接口单位为 分， 所以这里需要转换金额 1元= 100分
        $payValue = $this->coverPayValue(ErrorCode::BABY_COVER_TYPE_PAY, $iconsArr['pay_value']);

        $optionsArr = $this->miniPay($openId, $payValue);

        $payOptions = json_encode($optionsArr);

        //生成用户订单信息
        Log::info("recharge: options code= " . $optionsArr['code']);
        if( $optionsArr['code'] == ErrorCode::CODE_OK ){
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

    /**
     * 提现
     * @return 订单参数
     */
    public function outCash()
    {
        $userId = session('user_id');
        $userInfo = $this->getUserInfo($userId);
        $isRecharge = isset($userInfo['is_recharge']) ? $userInfo['is_recharge'] : '';
        $coin = isset($userInfo['coin']) ? $userInfo['coin'] : '';

        return view('', ['title' => '提现', 'coin' => $coin]);
        //$ret = $this->miniRedPackage('ovFkn4x7bI7CI0vcy8XqEer8zQYk', '');
        //return $ret;

    }

}
