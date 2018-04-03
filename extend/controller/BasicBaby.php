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

namespace controller;

use service\DataService;
use think\Controller;
use think\Db;
use think\db\Query;
use service\PayService;
use think\Log;


/**
 * 娃娃业务基础控制器
 * Class BasicBaby
 * @package controller
 */
class BasicBaby extends Controller
{

    /**
     * 页面标题
     * @var string
     */
    public $title;

    /**
     * 默认操作数据表
     * @var string
     */
    public $table;


    /**
     * 表单默认操作
     * @param Query $dbQuery 数据库查询对象
     * @param string $tplFile 显示模板名字
     * @param string $pkField 更新主键规则
     * @param array $where 查询规则
     * @param array $extendData 扩展数据
     * @return array|string
     */
    protected function _form($dbQuery = null, $tplFile = '', $pkField = '', $where = [], $extendData = [])
    {
        $db = is_null($dbQuery) ? Db::name($this->table) : (is_string($dbQuery) ? Db::name($dbQuery) : $dbQuery);
        $pk = empty($pkField) ? ($db->getPk() ? $db->getPk() : 'id') : $pkField;
        $pkValue = $this->request->request($pk, isset($where[$pk]) ? $where[$pk] : (isset($extendData[$pk]) ? $extendData[$pk] : null));
        // 非POST请求, 获取数据并显示表单页面
        if (!$this->request->isPost()) {
            $vo = ($pkValue !== null) ? array_merge((array)$db->where($pk, $pkValue)->where($where)->find(), $extendData) : $extendData;
            if (false !== $this->_callback('_form_filter', $vo)) {
                empty($this->title) || $this->assign('title', $this->title);
                return $this->fetch($tplFile, ['vo' => $vo]);
            }
            return $vo;
        }
        // POST请求, 数据自动存库
        $data = array_merge($this->request->post(), $extendData);
        if (false !== $this->_callback('_form_filter', $data)) {
            $result = DataService::save($db, $data, $pk, $where);
            if (false !== $this->_callback('_form_result', $result)) {
                if ($result !== false) {
                    $this->success('恭喜, 数据保存成功!', '');
                }
                $this->error('数据保存失败, 请稍候再试!');
            }
        }
    }

    /**
     * 列表集成处理方法
     * @param Query $dbQuery 数据库查询对象
     * @param bool $isPage 是启用分页
     * @param bool $isDisplay 是否直接输出显示
     * @param bool $total 总记录数
     * @param array $result
     * @return array|string
     */
    protected function _list($dbQuery = null, $isPage = true, $isDisplay = true, $total = false, $result = [])
    {
        $db = is_null($dbQuery) ? Db::name($this->table) : (is_string($dbQuery) ? Db::name($dbQuery) : $dbQuery);
        // 列表排序默认处理
        if ($this->request->isPost() && $this->request->post('action') === 'resort') {
            $data = $this->request->post();
            unset($data['action']);
            foreach ($data as $key => &$value) {
                if (false === $db->where('id', intval(ltrim($key, '_')))->setField('sort', $value)) {
                    $this->error('列表排序失败, 请稍候再试');
                }
            }
            $this->success('列表排序成功, 正在刷新列表', '');
        }
        // 列表数据查询与显示
        if (null === $db->getOptions('order')) {
            $fields = $db->getTableFields($db->getTable());
            in_array('sort', $fields) && $db->order('sort asc');
        }
        if ($isPage) {
            $rows = intval($this->request->get('rows', cookie('rows')));
            cookie('rows', $rows >= 10 ? $rows : 20);
            $page = $db->paginate($rows, $total, ['query' => $this->request->get('', '', 'urlencode')]);
            list($pattern, $replacement) = [['|href="(.*?)"|', '|pagination|'], ['data-open="$1"', 'pagination pull-right']];
            list($result['list'], $result['page']) = [$page->all(), preg_replace($pattern, $replacement, $page->render())];
        } else {
            $result['list'] = $db->select();
        }
        if (false !== $this->_callback('_data_filter', $result['list']) && $isDisplay) {
            !empty($this->title) && $this->assign('title', $this->title);
            return $this->fetch('', $result);
        }
        return $result;
    }

    /**
     * 当前对象回调成员方法
     * @param string $method
     * @param array|bool $data
     * @return bool
     */
    protected function _callback($method, &$data)
    {
        foreach ([$method, "_" . $this->request->action() . "{$method}"] as $_method) {
            if (method_exists($this, $_method) && false === $this->$_method($data)) {
                return false;
            }
        }
        return true;
    }


    /**
     * 新用户微信id 名称，头像
     * @param string $unionId
     * @param string $openId
     * @param string $name
     * @param string $pic
     * @return bool
     */
    protected function newUser($unionId, $openId, $name, $pic, $gender, $country, $province, $city)
    {
        //查询当前是否已经存在用户信息
        $db_wx = Db::name('TUserWeixin');
        $db_user = Db::name('TUserConfig');
        $wxUser = '';
        if($unionId){
            $wxUser = $db_wx->where('union_id', $unionId)->find();
            $openId = $unionId;
        }else{
            $wxUser = $db_wx->where('open_id', $openId)->find();
        }

        if($wxUser == ''){
            //保存新用户信息
            $seqNum = DataService::createSequence(10, 'WXUSER');
            $userId = $seqNum;
            $data_user = array('user_id'=> $seqNum, 'name' => $name, 'pic' => $pic,
                                'gender' => $gender, 'country' => $country, 'province' => $province, 'city' => $city);
            $result = DataService::save($db_user, $data_user);

            $data_wx = array('user_id'=> $seqNum, 'union_id' => $unionId, 'open_id' => $openId);
            $result = DataService::save($db_wx, $data_wx);
        }else{
            $userId = $wxUser['user_id'];
        }

        session('openid', $openId);
        session('user_id', $userId);
        return $userId;
    }

    /**
     * 设置open id 在session 中
     * @param string $userId
     * @return bool
     */
    protected function setOpenId($userId)
    {
        //查询openid
        //查询当前是否已经存在用户信息
        $db_wx = Db::name('TUserWeixin');
        $wxUser = '';
        $openId = '';
        $unionId = '';
        if($userId){
            $wxUser = $db_wx->where('user_id', $userId)->find();
            if($wxUser){
                $openId = $wxUser['open_id'];
                $unionId = $wxUser['union_id'];
            }
        }
        session('user_id', $userId);
        session('open_id', $openId);
        session('union_id', $unionId);

    }

    /**
     * 充值金额转换
     * @param int $coverType 1 单位元 转换为 金币数量， 2 单位元 转换为 分
     * @param int $inValue  输入单位 元 数据
     * @return int $outValue
     */
    protected function coverPayValue($coverType, $inValue)
    {
        $outValue = $inValue;
        switch($coverType){
            case WAWA_COVER_TYPE_COIN:
                //元 转换为 金币（角）
                $outValue = $inValue * 10;
                break;
            case WAWA_COVER_TYPE_PAY:
                //元 转换为 分
                $outValue = $inValue * 100;
                break;
            default:
                $outValue = $inValue;
                break;
        }
        return $outValue;

    }

    /**
     * 获得充值数据
     * @param int $userId
     * @param string $orderNo
     * @param string $prepayid
     * @param int $channelId
     * @param int $payValue   充值 金额，单位元
     * @param int $receiptType
     * @return bool
     */
    protected function getPayValue($iconsType)
    {
        $db_icons = Db::name('TUserReceiptFree');

        //查询充值信息
        $iconsInfo = $db_icons->where('icons_type', $iconsType)->find();
        if($iconsInfo && ($iconsInfo != '') ) {
           return $iconsInfo;
        }
        return $iconsInfo = '';
    }

    /**
     * 获取充值类型列表
     * @param int $userId
     * @param string $orderNo
     * @param string $prepayid
     * @param int $channelId
     * @param int $payValue
     * @param int $receiptType
     * @return bool
     */
    protected function getReceiptList()
    {
        $db_list = Db::name('TuserReceiptFree');
        $receiptList = '';
        $result = '';
        //查询订单信息
        $receiptList = $db_list->whereBetween('icons_type', ["0", "20"]);

        return $receiptList;
    }


    /**
     * 生成支付收据
     * @param int $userId
     * @param string $orderNo
     * @param string $prepayid
     * @param int $channelId
     * @param int $payValue  金币数量 1元等于10个金币
     * @param int $receiptType 交易类型 0为生成订单，1为交易成功
     * @param int $iconsType  充值优惠类型
     * @return bool
     */
    protected function saveReceipt($userId, $prepayId, $payValue, $iconsType, $orderNo, $channelId=1, $receiptType=0)
    {
        $db_receipt = Db::name('TUserCommonpayReceipt');
        $receiptInfo = '';
        $result = '';

        //异常处理
        if( empty($orderNo) ){
            Log::error("saveReceipt: empty failed order_no= " . $orderNo);
            return $result;
        }
        if($receiptType == 0){
            //新创建支付收据，以下数据必须有数据
            if( empty($orderNo)
                || empty($userId)
                || empty($prepayId)
                || empty($payValue)
                || empty($iconsType) ){
                Log::error("saveReceipt: empty create failed order_no= " . $orderNo . "userId=" . $userId . "prepayId=" . $prepayId . "payValue=" . $payValue . "iconsType=" . $iconsType);
                return $result;
            }

        }

        //查询订单信息
        $receiptInfo = $db_receipt->where('order_no', $orderNo)->find();
        Log::info("saveReceipt: start order_no=" . $orderNo . " receiptType=" . $receiptType);

        if($receiptInfo && ($receiptInfo['order_no'] == $orderNo ) ){
            //更新订单
            if($receiptType != 0){
                //更新订单 状态
                Log::info("saveReceipt: update receipt order_no=" . $orderNo . " receiptType=" . $receiptType);
                //$pk = empty($orderNo) ? ($db_receipt->getPk() ? $db_receipt->getPk() : 'id') : 'order_no';

                $data_receipt = array('id'=> $receiptInfo['id'], 'receipt_type'=> $receiptType);
                $result = DataService::save($db_receipt, $data_receipt);
            }
        }else{
            //创建订单
            Log::info("saveReceipt: careate receipt order_no= " . $orderNo);
            $data_receipt = array('user_id'=> $userId,
                'user_id'=> $userId,
                'order_no'=> $orderNo,
                'prepayid'=> $prepayId,
                'channel_id'=> $channelId,
                'pay_value'=> $payValue,
                'receipt_type'=> $receiptType,
                'icons_type'=> $iconsType);
            $result = DataService::save($db_receipt, $data_receipt);
        }

        if($result){
            Log::info("saveReceipt: end ok order_no= " . $orderNo);
        }else{
            Log::error("saveReceipt: end failed order_no= " . $orderNo);
        }
        return $result;

    }

    ///////////////////////////////////////////////////////////////////
    /**
     * 小程序支付请求 生成统一订单
     * @param string $unionId
     * @param string $openId
     * @param string $name
     * @param string $pic
     * @return bool
     */
    protected function miniPay($openId, $total_fee)
    {

        //查询当前是否已经存在订单
        $order_no = session('pay-mini-order-no');
        Log::info("miniPay start: order_no= " . $order_no);
        if (empty($order_no)) {
            $order_no = DataService::createSequence(10, 'wechat-pay-mini');
            session('pay-mini-order-no', $order_no);
        }
        if (PayService::isPay($order_no)) {
            //清除已经支付完成的订单号缓存
            Log::info("miniPay wechat pay already ok: order_no= " . $order_no);
            $this->completeMiniPay($order_no, WAWA_PAY_SUCCESS);
            return ['code' => 2, 'msg' => "此订单已完成支付", 'order_no' => $order_no];
        }

        $pay = load_wx_mini('pay');
        $options = PayService::createWechatPayJsPicker($pay, $openId, $order_no, $total_fee, 'JSAPI支付测试2');
        if ($options === false) {
            $options = ['code' => 3, 'msg' => "创建支付失败，{$pay->errMsg}[$pay->errCode]"];
        }else{
            //获取prepay id  "package":"prepay_id=wx201803311610441ca39aa76f0719651834",
            $packageArr = explode('=', $options['package']);
            $prepayId = $packageArr[1];

            $options['prepayId'] = $prepayId;
            $options['code'] = 0;
            $options['msg'] = "创建支付成功";

        }
        $options['order_no'] = $order_no;

        Log::info("miniPay end: options= " . json_encode($options));
        return $options;
        //return json($options);

    }

    /**
     * 小程序 支付完成 清除缓存订单
     * @param string $orderNo
     * @param int $status
     * @return bool
     */
    protected function completeMiniPay($orderNo, $status){
        $order_no = session('pay-mini-order-no');   //由于微信小程序 支付请求接口，重新建立session 所以预支付订单页面的 session数据获取不到
        $retBool = false;
        Log::info("completeMiniPay: start orderNo=" . $orderNo . " status=" . $status . " pay-mini-order-no=" . $order_no);

        if( $orderNo ){
            if($status == 1){
                //支付成功
                Log::info("completeMiniPay: saveReceipt orderNo=" . $orderNo . " status=" . $status);
                //更新充值表状态 t_user_commonpay_receipt

                $retBool = $this->saveReceipt('', '', '',  '', $orderNo, '', $status);  //$orderNo 为第5个参数，注意
                if( empty($retBool) ){
                    Log::error("completeMiniPay: update pay failed orderNo=" . $orderNo . " status=" . $status);
                    return $retBool;
                }
                Log::info("completeMiniPay: update pay complete orderNo=" . $orderNo);

                //充值用户金币数量 t_user_config
                $retBool = $this->rechargeUserCoin($orderNo);
                Log::info("completeMiniPay: update user config coin complete retBool=" . $retBool);

                // 重置缓存订单
                session('pay-mini-order-no', null);
            }

        }elseif ($order_no){
            //处理请求的$orderNo 异常为空的情况，查找缓存中$order_no的状态
            //查询微信订单表 wechat_pay_prepayid
            if (PayService::isPay($order_no)) {
                //微信订单支付成功， 查询用户充值订单表 t_user_commonpay_receipt
                $db_receipt = Db::name('TUserCommonpayReceipt');
                $receiptInfo = $db_receipt->where('order_no', $orderNo)->where('receipt_type', WAWA_PAY_SUCCESS)->find();
                if($receiptInfo && ($receiptInfo['order_no'] == $orderNo ) ){
                    Log::info("completeMiniPay: user receipt complete order_no=" . $order_no);
                    $retBool = true;
                }else{
                    Log::error("completeMiniPay: user receipt not complete order_no=" . $order_no);
                }

            }else{
                //微信订单未支付成功
                Log::error("completeMiniPay: wechat pay receipt complete order_no=" . $order_no);
                return $retBool;
            }

        }
        Log::info("completeMiniPay: end orderNo=" . $orderNo . " status=" . $status);

        return $retBool;

    }


    /**
     * 充值用户金币数量
     * @param string $unionId
     * @param string $unionId
     * @return bool
     */
    protected function rechargeUserCoin($orderNo){
        $retBool = false;
        $lastPayValue = 0;   //最终充值金额 单位元
        $lastCoinValue = 0;   //最终充值的金币数量 单位金币  (1金币= 1娃娃币)
        $lastFreeValue = 0;   //最终充值的娃娃币数量 单位娃娃币

        Log::info("rechargeUserCoin: start orderNo=" . $orderNo);

        //查询订单信息
        $db_receipt = Db::name('TUserCommonpayReceipt');
        $receiptInfo = $db_receipt->where('order_no', $orderNo)->find();
        if($receiptInfo && ($receiptInfo['order_no'] == $orderNo ) ){

            $iconsType = $receiptInfo['icons_type'];
            Log::info("rechargeUserCoin: query receipt success iconsType=" . $iconsType);

            //获取充值优惠
            $receiptFreeInfo = $this->getPayValue($iconsType);

            if($receiptFreeInfo && ($receiptFreeInfo['pay_value'] == $receiptInfo['pay_value'] ) ){
                $lastPayValue = $receiptInfo['pay_value'];
                $lastFreeValue = $receiptFreeInfo['free_value'];

                Log::info("rechargeUserCoin: query receipt free success lastPayValue=" . $lastPayValue . "lastFreeValue=" . $lastFreeValue);

            }else{
                //充值金额异常处理
                if( $receiptFreeInfo['pay_value'] != $receiptInfo['pay_value'] ){
                    Log::error("rechargeUserCoin: not match receipt value=" . $receiptFreeInfo['pay_value'] . "receipt actual value==" . $receiptInfo['pay_value']);
                    //选择充值金额小的数据
                    if($receiptFreeInfo['pay_value'] < $receiptInfo['pay_value']){
                        $lastPayValue = $receiptFreeInfo['pay_value'];
                    }else{
                        $lastPayValue = $receiptInfo['pay_value'];
                    }
                    $lastFreeValue = $receiptFreeInfo['free_value'];

                }else{
                    //没有找到充值优惠 使用充值订单金额
                    Log::info("rechargeUserCoin: no receipt free lastPayValue=" .$receiptInfo['pay_value']);
                    $lastPayValue = $receiptInfo['pay_value'];
                    $lastFreeValue = 0;
                }

            } //if($receiptFreeInfo && ($receiptFreeInfo['pay_value'] == $receiptInfo['pay_value'] ) )

            //转换充值金额为 充值金币  1元 = 10 金币
            $lastCoinValue = $this->coverPayValue(WAWA_COVER_TYPE_COIN, $lastPayValue);

            Log::info("rechargeUserCoin: cover receipt value success lastPayValue=" . $lastPayValue . " lastCoinValue=" . $lastCoinValue . " lastFreeValue=" . $lastFreeValue);

            //保存用户信息表 充值金币 和 娃娃币
            //$userId = session('user_id');  //小程序支付新建session 获取不到session 数据
            $userId = $receiptInfo['user_id'];

            $db_user = Db::name('TUserConfig');
            $userInfo = $db_user->where('user_id', $userId)->find();
            if($userInfo && ($userInfo['user_id'] == $userId ) ){

                $saveCoin = $userInfo['coin'] + $lastCoinValue;
                $saveFree = $userInfo['free_coin'] + $lastFreeValue;
                //$pk = empty($userId) ? ($db_receipt->getPk() ? $db_receipt->getPk() : 'id') : 'user_id';
                $data_userCoin = array('id'=> $userInfo['id'], 'coin'=> $saveCoin, 'free_coin'=> $saveFree);
                $retBool = DataService::save($db_user, $data_userCoin);
            }

        } //if($receiptInfo && ($receiptInfo['order_no'] == $orderNo ) )

        if($retBool){
            Log::info("rechargeUserCoin: end ok order_no= " . $orderNo . "userId=" . $userId);
        }else{
            Log::error("rechargeUserCoin: end failed order_no= " . $orderNo .  "userId=" . $userId);
        }
        return $retBool;
    }

}
