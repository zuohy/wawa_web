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
        //$payOptions = $this->miniPay(1);

        //$payOptions = json_encode($payOptions);

        //获取openid
        $openId = session('open_id');
        $userId = session('user_id');
        $unionId = session('union_id');
        Log::info("recharge: openId= " . $openId);
        Log::info("recharge: userId= " . $userId);
        Log::info("recharge: unionId= " . $unionId);
        $optionsArr = $this->miniPay($openId, 1);

        //$arr = object_to_array($optionsObj);
        //$payOptions = json_encode($arr);
        //$payOptions = json_encode($payOptions, JSON_FORCE_OBJECT);
        $payOptions = json_encode($optionsArr);

        Log::info("recharge: payOptions= " .$payOptions);
        //$payOptions = '{"appId":"wx543f399af45d82ba","timeStamp":"1522488580","nonceStr":"yts0bf55hcmywxm5p9m7gh7ho0lr0w1k","package":"prepay_id=wx201803311610441ca39aa76f0719651834","signType":"MD5","paySign":"149402D8908D4DECCA3ACAA0D46D05FD","timestamp":"1522488580","order_no":"2850982462"}';
        return $payOptions;
    }

}
