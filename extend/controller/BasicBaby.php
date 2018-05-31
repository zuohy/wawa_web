<?php

namespace controller;

use service\DataService;
use service\ErrorCode;
use service\RoomService;
use think\Controller;
use think\Db;
use think\db\Query;
use service\PayService;
use think\Log;
use service\ActivityService;

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
            //暂时不支持$unionId
            $wxUser = $db_wx->where('union_id', $unionId)->find();
            $openId = $unionId;
            Log::error("newUser: open_id= " . $openId . ' not support wx unionid');
            $retMsgArr = array('code' => ErrorCode::E_USER_NOT_FOUND, 'type' => '', 'msg' => 'error', 'data' => '');
            $retMsg = json_encode($retMsgArr);
            return $retMsg;
        }else{
            $wxUser = $db_wx->where('open_id', $openId)->find();
        }

        $curDate = date('Y-m-d H:m:s');
        if($wxUser && ($wxUser['open_id'] == $openId )){
            $userId = $wxUser['user_id'];

        }else{
            //保存新用户信息
            $seqNum = DataService::createSequence(10, 'WXUSER');
            $userId = $seqNum;
            $seqCode = DataService::createSequence(11, 'WXUSER-CODE');
            $code = $seqCode;

            //异常用户检查
            $tmpUserInfo = $db_user->where('user_id', $userId)->find();
            if($tmpUserInfo && $tmpUserInfo['user_id'] == $userId){
                //用户随机ID 已经存在
                Log::error("newUser: user_id= " . $userId . ' already exist');
                $retMsgArr = array('code' => ErrorCode::E_USER_AlREADY_EXIST, 'type' => '', 'msg' => 'error', 'data' => '');
                $retMsg = json_encode($retMsgArr);
                return $retMsg;
            }

            $preDate = date("Y-m-d",strtotime("-1 day"));  //新用户 登录时间为前一天，送首次登录币
            $data_user = array('user_id'=> $seqNum, 'name' => $name, 'pic' => $pic,
                                'gender' => $gender, 'country' => $country, 'province' => $province, 'city' => $city, 'code'=> $code,
                                'login_num' => 1,'login_at' => $preDate, 'update_at' => $curDate,);
            $result = DataService::save($db_user, $data_user);

            $data_wx = array('user_id'=> $seqNum, 'union_id' => $unionId, 'open_id' => $openId);
            $result = DataService::save($db_wx, $data_wx);
        }

        $userInfo = $db_user->where('user_id', $userId)->find();
        if($userInfo && $userInfo['user_id'] == $userId){
            //更新登录时间 次数
            $loginNum = $userInfo['login_num'] + 1;
            $data_user = array('id'=> $userInfo['id'],
                'login_num' => $loginNum,'update_at' => $curDate,);
            $result = DataService::save($db_user, $data_user);

            $retData = array('user_id' => $userInfo['user_id'], 'code' => $userInfo['code'], );
            $retMsgArr = array('code' => ErrorCode::CODE_OK, 'type' => '', 'msg' => 'ok', 'data' => $retData);
        }else{
            $retMsgArr = array('code' => ErrorCode::E_USER_NOT_FOUND, 'type' => '', 'msg' => 'error', 'data' => '');
        }

        $retMsg = json_encode($retMsgArr);
        session('openid', $openId);
        session('user_id', $userId);
        return $retMsg;
    }

    /**
     * 设置open id 在session 中
     * @param string $userId
     * @return bool
     */
    protected function getOpenId($userId)
    {
        //查询openid
        //查询当前是否已经存在用户信息
        $db_wx = Db::name('TUserWeixin');

        $wxUser = $db_wx->where('user_id', $userId)->find();
        $openId = isset($wxUser['open_id']) ? $wxUser['open_id'] : '';
        $unionId = isset($wxUser['union_id']) ? $wxUser['union_id'] : '';

        return $openId;
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
                session('open_id', $openId);
                session('union_id', $unionId);
            }
            session('user_id', $userId);
        }


    }
    ////////////////////////////////////start 用户管理 相关函数////////////////////////////////////
    /**
     * 获取用户信息 id 名称，头像
     * @param string $userId
     * @return array
     */
    protected function getUserInfo($userId){
        //获取用户信息
        $db_user = Db::name('TUserConfig');
        $userInfo = $db_user->where('user_id', $userId)->find();
        if($userInfo && ($userInfo['user_id'] == $userId ) ){
            return $userInfo;
        }

        return '';

    }
    /**
     * 获取用户信息 by code
     * @param string $unionId
     * @param string $openId
     * @param string $name
     * @param string $pic
     * @return array
     */
    protected function getUserInfoByCode($code){
        //获取用户信息
        $db_user = Db::name('TUserConfig');
        $userInfo = $db_user->where('code', $code)->find();
        if($userInfo && ($userInfo['code'] == $code ) ){
            return $userInfo;
        }

        return '';

    }


    /**
     * 更新用户信息 更新用户金额
     * @param string $userId 用户ID
     * @param arrary $userInfo
     * @return int
     */
    public static function updateUserInfo($userId, $userInfo){
        Log::info("updateUserInfo: start user_id= " . $userId);

        $db_user = Db::name('TUserConfig');
        $data_user = array();

        $userArr = $db_user->where('user_id', $userId)->find();
        if($userArr && ($userArr['user_id'] == $userId ) ){
            $data_user['id'] = $userArr['id'];

            foreach($userArr as $key => $value){
                if( isset($userInfo[$key]) ){
                    $data_user[$key] = $userInfo[$key];
                }
            }
            $jsonData = json_encode($data_user);
            Log::info("updateUserInfo: update userInfo= " . $jsonData );
            $result = DataService::save($db_user, $data_user);

        }

        if($result){
            Log::info("updateUserInfo: end ok user_id= " . $userId );
            $result = ErrorCode::CODE_OK;
        }else{
            Log::error("updateUserInfo: end failed user_id= " . $userId );
            $result = ErrorCode::E_ROOM_UPDATE_FAIL;
        }

        return $result;

    }

    ////////////////////////////////////end 用户管理 相关函数////////////////////////////////////


    ////////////////////////////////////start 支付 金币 相关函数////////////////////////////////////
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
            case ErrorCode::BABY_COVER_TYPE_COIN:
                //元 转换为 金币（角）
                $outValue = $inValue * 10;
                break;
            case ErrorCode::BABY_COVER_TYPE_PAY:
                //元 转换为 分
                $outValue = $inValue * 100;
                break;
            case ErrorCode::BABY_COVER_TYPE_CNY:
                //金币 转换为 元
                $outValue = $inValue / 10;
                break;
            case ErrorCode::BABY_COVER_TYPE_FEE:
                //金币 转换为 分
                $outValue = $inValue * 10;
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

        //查询订单信息
        $receiptList = $db_list->whereBetween('icons_type', ["0", "20"]);

        return $receiptList;
    }

    /**
     * 获取充值 购买订单信息
     * @param int $userId
     * @param string $orderNo
     * @return Array
     */
    protected function getReceiptInfo($orderNo)
    {
        $db_receipt = Db::name('TUserCommonpayReceipt');
        $receiptInfo = '';

        Log::info("getReceiptInfo: order_no=" . $orderNo);
        $receiptInfo = $db_receipt->where('order_no', $orderNo)->find();
        if($receiptInfo && ($receiptInfo['order_no'] == $orderNo) ) {
            return $receiptInfo;
        }
        return '';
    }

    /**
     * 生成支付收据
     * @param int $userId
     * @param string $prepayid
     * @param int $payValue  金币数量 1元等于10个金币
     * @param int $iconsType  充值优惠类型
     * @param string $orderNo
     * @param int $proCode  产品编码
     * @param int $channelId
     * @param int $receiptType 交易类型 0为生成订单，1为交易成功
     * @return bool
     */
    protected function saveReceipt($userId, $prepayId, $payValue, $iconsType, $orderNo, $proCode=ErrorCode::BABY_HEADER_SEQ_APP, $channelId=1, $receiptType=0)
    {
        $db_receipt = Db::name('TUserCommonpayReceipt');
        $receiptInfo = '';
        $result = '';
        Log::info("saveReceipt: start order_no=" . $orderNo . " receiptType=" . $receiptType);

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
                'icons_type'=> $iconsType,
                'product_code'=> $proCode);
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
     * 生成缓存唯一序号 (失败返回 NULL )
     * @param int $length 序号长度
     * @return string
     */
    protected function createTmpSeq($length = 10)
    {
        $times = 0;
        while ($times++ < 10) {
            list($i, $sequence) = [0, ''];
            while ($i++ < $length) {
                $sequence .= ($i <= 1 ? rand(1, 9) : rand(0, 9));
            }

        }
        return $sequence;
    }

    /**
     * 生成 字母开头的 缓存唯一序号 (失败返回 NULL )
     * @param int $length 序号长度
     * @return string
     */
    protected function createHeaderSeq($topType, $length = 10)
    {
        $topSequence = null;
        $seq = $this->createTmpSeq($length);
        $topSequence = $topType  . $seq;

        return $topSequence;
    }

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

        //异常判断 检查订单是否 为当前未支付订单
        $curReceipt = $this->getReceiptInfo($order_no);
        $PayValue = isset($curReceipt['pay_value']) ? $curReceipt['pay_value'] : 0;  //单位元
        $cruPayValue = $this->coverPayValue(ErrorCode::BABY_COVER_TYPE_PAY, $PayValue);  //元转换为 分
        if($cruPayValue != $total_fee){
            //不是同一个订单 重新生效新的订单
            $order_no = DataService::createSequence(10, 'wechat-pay-mini');
            session('pay-mini-order-no', $order_no);
        }

        if (PayService::isPay($order_no)) {
            //清除已经支付完成的订单号缓存
            Log::info("miniPay wechat pay already ok: order_no= " . $order_no);
            $retBool = $this->saveReceipt('', '', '',  '', $order_no, '', '', ErrorCode::BABY_PAY_SUCCESS);  //$orderNo 为第5个参数，注意
            // 重置缓存订单
            session('pay-mini-order-no', null);

            //return ['code' => ErrorCode::E_PAY_ALREADY_SUCCESS, 'msg' => "此订单已完成支付", 'order_no' => $order_no];
            //支付完成重新生成新的订单号 不返回错误
            $order_no = DataService::createSequence(10, 'wechat-pay-mini');
            session('pay-mini-order-no', $order_no);

        }

        $pay = load_wx_mini('pay');
        $options = PayService::createWechatPayJsPicker($pay, $openId, $order_no, $total_fee, 'JSAPI支付测试2');
        if ($options === false) {
            $options = ['code' =>  ErrorCode::E_PAY_PICKER_FAILED, 'msg' => "创建支付失败，{$pay->errMsg}[$pay->errCode]"];
        }else{
            //获取prepay id  "package":"prepay_id=wx201803311610441ca39aa76f0719651834",
            $packageArr = explode('=', $options['package']);
            $prepayId = $packageArr[1];

            $options['prepayId'] = $prepayId;
            $options['code'] = ErrorCode::CODE_OK;
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

        // 重置缓存订单
        session('pay-mini-order-no', null);     //只要完成支付，不管成功失败都清除缓存

        $retBool = false;
        Log::info("completeMiniPay: start orderNo=" . $orderNo . " status=" . $status . " pay-mini-order-no=" . $order_no);

        if( $orderNo ){
            if($status == ErrorCode::BABY_PAY_SUCCESS){
                //支付成功
                Log::info("completeMiniPay: saveReceipt orderNo=" . $orderNo . " status=" . $status);
                //更新充值表状态 t_user_commonpay_receipt

                $retBool = $this->saveReceipt('', '', '',  '', $orderNo, '', '', $status);  //$orderNo 为第5个参数，注意
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
                $receiptInfo = $db_receipt->where('order_no', $orderNo)->where('receipt_type', ErrorCode::BABY_PAY_SUCCESS)->find();
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
     * 小程序 支付完成 清除缓存订单 更新订单表
     * @param string $orderNo
     * @param int $status
     * @return bool
     */
    protected function completeOrderPay($orderNo, $status){
        $order_no = '';//session('pay-mini-order-no');   //由于微信小程序 支付请求接口，重新建立session 所以预支付订单页面的 session数据获取不到
        $result = ErrorCode::BABY_PAY_FAILED;

        Log::info("completeOrderPay: start orderNo=" . $orderNo . " status=" . $status . " pay-mini-order-no=" . $order_no);

        if( $orderNo ){
            if($status == ErrorCode::BABY_PAY_SUCCESS){
                //支付成功
                Log::info("completeOrderPay: saveReceipt orderNo=" . $orderNo . " status=" . $status);
                //更新充值表状态 t_user_commonpay_receipt

                $retBool = $this->saveReceipt('', '', '',  '', $orderNo, '', '', $status);  //$orderNo 为第5个参数，注意
                if( empty($retBool) ){
                    Log::error("completeOrderPay: update pay failed orderNo=" . $orderNo . " status=" . $status);
                    return $result;
                }
                $result = ErrorCode::BABY_PAY_SUCCESS;
                Log::info("completeOrderPay: update pay complete orderNo=" . $orderNo);

                // 重置缓存订单
                //session('pay-mini-order-no', null);
            }

        }elseif ($order_no){
            //处理请求的$orderNo 异常为空的情况，查找缓存中$order_no的状态
            //查询微信订单表 wechat_pay_prepayid
            if (PayService::isPay($order_no)) {
                //微信订单支付成功， 查询用户充值订单表 t_user_commonpay_receipt
                $db_receipt = Db::name('TUserCommonpayReceipt');
                $receiptInfo = $db_receipt->where('order_no', $orderNo)->where('receipt_type', ErrorCode::BABY_PAY_SUCCESS)->find();
                if($receiptInfo && ($receiptInfo['order_no'] == $orderNo ) ){
                    Log::info("completeOrderPay: user receipt complete order_no=" . $order_no);
                    $result = ErrorCode::E_PAY_ALREADY_SUCCESS;
                }else{
                    Log::error("completeOrderPay: user receipt not complete order_no=" . $order_no);
                }

            }else{
                //微信订单未支付成功
                Log::error("completeOrderPay: wechat pay receipt complete order_no=" . $order_no);
                return $result;
            }

        }
        Log::info("completeOrderPay: end orderNo=" . $orderNo . " status=" . $status);

        return $result;

    }

    /**
     * 支付成功后， 处理逻辑
     * @param string $unionId
     * @param string $unionId
     * @return bool
     */
    protected function handleUserCoin($orderNo){

        $result = ErrorCode::CODE_OK;

        //获取内部订单
        Log::info("handleUserCoin: start orderNo=" . $orderNo);

        //查询订单信息
        $db_receipt = Db::name('TUserCommonpayReceipt');
        $receiptInfo = $db_receipt->where('order_no', $orderNo)->find();
        $iconsType = isset($receiptInfo['icons_type']) ? $receiptInfo['icons_type'] : '';

        Log::info("handleUserCoin: query receipt success iconsType=" . $iconsType);

        if( $iconsType >= ErrorCode::BABY_COIN_TYPE_REG_1
        && $iconsType < ErrorCode::BABY_COIN_TYPE_SHARE){
            //充值用户金币数量 t_user_config
            $retBool = $this->rechargeUserCoin($orderNo);
            Log::info("handleUserCoin: update user config coin complete retBool=" . $retBool);
            if($retBool != true){
                $result = ErrorCode::E_NOT_SUPPORT;  //通用错误
            }

        }elseif($iconsType >= ErrorCode::BABY_COIN_TYPE_SHARE){
            //分享购买返现
            $result = $this->backUserCoin(ErrorCode::BABY_INCOME_BACK_CNY, $orderNo);

        }

        return $result;

    }

    /**
     * 购买返现
     * @param string $isCNY   1 直接反现金， 0 反金币
     * @param string $orderNo   //购买订单ID
     * @return bool
     */
    protected function backUserCoin($isCNY, $orderNo){

        Log::info("backUserCoin: start orderNo=" . $orderNo . ' isCNY=' . $isCNY);
        //获取订单信息
        $receiptInfo = $this->getReceiptInfo($orderNo);
        $userId = isset($receiptInfo['user_id']) ? $receiptInfo['user_id'] : '';
        $proCode = isset($receiptInfo['product_code']) ? $receiptInfo['product_code'] : '';
        $iconsType = isset($receiptInfo['icons_type']) ? $receiptInfo['icons_type'] : '';

        Log::info("backUserCoin: receipt info user_id=" . $userId . ' product_code=' . $proCode . ' icons_type=' . $iconsType);

        //获取用户信息
        $tmpUserInfo = $this->getUserInfo($userId);
        $tmpCode = isset($tmpUserInfo['code']) ? $tmpUserInfo['code'] : '';



        //获取分享者 收益者信息
        $isShare = ErrorCode::BABY_SHARE_NOT_PAY;
        $tmpShareHis = ActivityService::getShareHisInfo($proCode, $tmpCode, $isShare, 0);  //不查询isShare
        if( empty($tmpShareHis) ){
            //没有分享信息 返回成功
            Log::info("backUserCoin: not found share history product_code=" . $proCode
                . ' user_id=' . $userId . ' code(被邀请码)=' . $tmpCode);
            $result = ErrorCode::CODE_OK;  //这里不做错误处理，只是不产生收益
            return $result;
        }
        $tmpFatherCode = isset($tmpShareHis['code_father']) ? $tmpShareHis['code_father'] : '';
        $tmpShareStatus = isset($tmpShareHis['s_status']) ? $tmpShareHis['s_status'] : '';
        if('' == $tmpFatherCode){
            //没有收益者信息
            Log::error("backUserCoin: father code not found product_code=" . $proCode
                . ' user_id=' . $userId . ' code(被邀请码)=' . $tmpCode);
            $result = ErrorCode::E_NOT_SUPPORT;  //这里做错误处理，只是不产生收益，有可能是已经处理过收益逻辑的用户
            return $result;

        }elseif(ErrorCode::BABY_SHARE_SUCCESS == $tmpShareStatus){
            //已经处理过收益者信息
            Log::info("backUserCoin: share status already done product_code=" . $proCode
                . ' user_id=' . $userId . ' code(被邀请码)=' . $tmpCode . ' tmpFatherCode=' . $tmpFatherCode);
            $result = ErrorCode::CODE_OK;  //这里不做错误处理，只是不产生收益，有可能是已经处理过收益逻辑的用户
            return $result;
        }

        $inUserInfo = $this->getUserInfoByCode($tmpFatherCode);
        $inUserId = isset($inUserInfo['user_id']) ? $inUserInfo['user_id'] : '';


        //获取优惠记录
        $receiptFreeInfo = $this->getPayValue($iconsType);
        $tmpCoinValue = isset($receiptFreeInfo['b_coin_value']) ? $receiptFreeInfo['b_coin_value'] : '';
        $tmpFreeValue = isset($receiptFreeInfo['b_free_value']) ? $receiptFreeInfo['b_free_value'] : '';
        $InValue = 0;   //返现金
        $InCoin = 0;    //返金币
        if($isCNY == ErrorCode::BABY_INCOME_BACK_CNY){
            $InValue = $this->coverPayValue(ErrorCode::BABY_COVER_TYPE_CNY, $tmpCoinValue);  //转换为单位元
            $payValue = $this->coverPayValue(ErrorCode::BABY_COVER_TYPE_PAY, $InValue);  //转换为单位分
        }else{
            $InCoin = $tmpCoinValue;
        }

        //异常检测
        //$payValue 如果为 异常， 微信转账会报错误， 不用检查。
        if( empty($inUserId)
            || empty($tmpCoinValue) ){
            Log::error("backUserCoin: income data user_id=" . $inUserId . ' coin_value(金币)=' . $tmpCoinValue . ' free_value(娃娃币)=' . $tmpFreeValue);
            $result = ErrorCode::E_NOT_SUPPORT;  //通用错误
            return $result;
        }

        //更新分享记录表 为有效分享
        ActivityService::updateShareHis($proCode, $tmpUserInfo, $inUserInfo, ErrorCode::BABY_SHARE_SUCCESS);

        Log::info("backUserCoin: income data user_id=" . $inUserId . ' coin_value(金币)=' . $tmpCoinValue . ' free_value(娃娃币)=' . $tmpFreeValue);
        Log::info("backUserCoin: income data user_id=" . $inUserId . ' InValue(元)=' . $InValue . ' payValue(分)=' . $payValue);

        //保存收益记录表
        $orderNum = $this->createHeaderSeq(ErrorCode::BABY_HEADER_SEQ_IN, 12);
        $result = ActivityService::addUserIncome($inUserId, $proCode, $orderNum, $orderNo, $InValue, $InCoin, $tmpFreeValue, ErrorCode::BABY_EMPLOY_REASON_3, '活动收益');
        if($result != ErrorCode::CODE_OK){
            Log::error("backUserCoin: addUserIncome failed user_id=" . $inUserId . ' InValue(元)=' . $InValue . ' payValue(分)=' . $payValue);
            return $result;
        }

        //检查$payValue  值上限 10元
        $maxFee = $this->coverPayValue(ErrorCode::BABY_COVER_TYPE_PAY, 10);
        if( $payValue > $maxFee ){
            Log::error("backUserCoin: income data too max user_id=" . $inUserId . ' InValue(元)=' . $InValue . ' payValue(分)=' . $payValue);
            $result = ErrorCode::E_USER_INCOME_MAX;
            return $result;
        }

        //存入对应的收益
        if($isCNY == ErrorCode::BABY_INCOME_BACK_CNY){
            $result = $this->miniRedPackage($inUserId, $orderNum, $payValue, '活动收益');
        }else{
            $this->freeUserCoin($proCode, $inUserId, $iconsType, Error::BABY_INCOME_BACK_TRUE);
        }

        if(ErrorCode::CODE_OK == $result){
            Log::info("backUserCoin: end ok order_no= " . $orderNo . ' order_num=' . $orderNum  . ' isCNY=' . $isCNY);
            //更新收益记录表
            $result = ActivityService::updateIncomeStatus($orderNum, ErrorCode::BABY_INCOME_DONE);

        }else{
            Log::error("backUserCoin: end failed order_no= " . $orderNo . ' order_num=' . $orderNum  . ' isCNY=' . $isCNY);
        }

        return $result;

    }

    /**
     * 充值用户金币数量
     * @param string $orderNo  订单编码
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
            $lastCoinValue = $this->coverPayValue(ErrorCode::BABY_COVER_TYPE_COIN, $lastPayValue);

            Log::info("rechargeUserCoin: cover receipt value success lastPayValue=" . $lastPayValue . " lastCoinValue=" . $lastCoinValue . " lastFreeValue=" . $lastFreeValue);

            //保存用户信息表 充值金币 和 娃娃币
            //$userId = session('user_id');  //小程序支付新建session 获取不到session 数据
            $userId = $receiptInfo['user_id'];

            $db_user = Db::name('TUserConfig');
            $userInfo = $db_user->where('user_id', $userId)->find();
            if($userInfo && ($userInfo['user_id'] == $userId ) ){

                //$saveCoin = $userInfo['coin'] + $lastCoinValue;   //充值全部 为娃娃币 金币只有在产生收益时候增加
                $saveFree = $userInfo['free_coin'] + $lastFreeValue + $lastCoinValue;
                //$data_userCoin = array('id'=> $userInfo['id'], 'coin'=> $userInfo['coin'], 'free_coin'=> $saveFree);
                $data_userCoin = array('id'=> $userInfo['id'], 'free_coin'=> $saveFree,  'is_recharge'=> ErrorCode::BABY_USER_OK_RECHARGE);
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

    /**
     * 兑换礼物 商品金币数量
     * @param string $userId  用户ID
     * @param string $orderId  消费订单编码
     * @return int
     */
    protected function exchangeUserCoin($userId, $orderId){
        $retStatus = ErrorCode::CODE_OK;
        $lastCoinValue = 0;   //最终充值的金币数量 单位金币  (1金币= 1娃娃币)
        $lastFreeValue = 0;   //最终充值的娃娃币数量 单位娃娃币

        Log::info("exchangeUserCoin: start order_id=" . $orderId . ' user_id=' . $userId);

        //获取抓取结果记录
        $resultInfo = $this->getResultInfo($orderId);
        $inUserId =  isset($resultInfo['user_id']) ? $resultInfo['user_id'] : '';
        $resultStatus = isset($resultInfo['result']) ? $resultInfo['result'] : '';
        $handleStatus = isset($resultInfo['status']) ? $resultInfo['status'] : '';
        $giftId = isset($resultInfo['gift_id']) ? $resultInfo['gift_id'] : '';
        //异常判断
        if( $inUserId != $userId                 //兑换user id 不一致
        || $resultStatus != ErrorCode::BABY_CATCH_SUCCESS    //结果异常
        || $handleStatus >= ErrorCode::BABY_POST_TO ){         //处理状态 为已经发货 或 已换币

            $retStatus = ErrorCode::E_NOT_SUPPORT;
            return $retStatus;

        }

        //获取礼物价格
        $giftInfo = RoomService::getGiftInfo($giftId);
        $inPrice = isset($giftInfo['gift_price']) ? $giftInfo['gift_price'] : 0;

        //更新用户金币
        $retStatus = $this->addUserCoin($giftId, $userId, $inPrice, 0);


        if($retStatus == ErrorCode::CODE_OK){
            //增加收益记录 并处理完成状态
            Log::info("exchangeUserCoin: add income coin=" . $inPrice );
            $orderNum = self::createHeaderSeq(ErrorCode::BABY_HEADER_SEQ_IN, 12);
            ActivityService::addUserIncome($userId, $giftId, $orderNum, $orderId, 0, $inPrice, 0, ErrorCode::BABY_EMPLOY_REASON_1, '兑换礼物', ErrorCode::BABY_INCOME_DONE);
            Log::info("exchangeUserCoin: end ok order_id= " . $orderId . "userId=" . $userId);
        }else{
            Log::error("exchangeUserCoin: end failed order_id= " . $orderId .  "userId=" . $userId);
        }
        return $retStatus;
    }

    /**
     * 增加用户金币 娃娃币
     * @param string $proCode   产品编码
     * @param string $userId   用户ID
     * @param string $freeType  优惠类型
     * @return bool
     */
    protected function addUserCoin($proCode, $userId, $lastCoinValue, $lastFreeValue){

        Log::info("addUserCoin:  user_id =" . $userId . ' product_code=' . $proCode
            . ' lastCoinValue=' . $lastCoinValue . ' lastFreeValue=' . $lastFreeValue);

        $retStatus = ErrorCode::CODE_OK;
        $retBool = false;

        $db_user = Db::name('TUserConfig');
        $userInfo = $db_user->where('user_id', $userId)->find();

        if($userInfo && ($userInfo['user_id'] == $userId ) ){

            $data_userCoin = array('id'=> $userInfo['id']);
            if($lastFreeValue > 0){
                $saveFree = $userInfo['free_coin'] + $lastFreeValue;
                $arrFree = array('free_coin'=> $saveFree);
                $data_userCoin = array_merge($data_userCoin, $arrFree);
            }
            if($lastCoinValue > 0){
                $saveCoin = $userInfo['coin'] + $lastCoinValue;
                $arrCoin = array('coin'=> $saveCoin);
                $data_userCoin = array_merge($data_userCoin, $arrCoin);
            }

            $log_userCoin = json_encode($data_userCoin);
            Log::info("addUserCoin: update user coins= " . $log_userCoin);

            $retBool = DataService::save($db_user, $data_userCoin);
        }

        if($retBool){
            $retStatus = ErrorCode::CODE_OK;
            Log::info("addUserCoin: end ok userId= " . $userId . ' product_code=' . $proCode);
        }else{
            $retStatus = ErrorCode::E_USER_INCOME_FAIL;
            Log::error("addUserCoin: end failed userId= " . $userId . ' product_code=' . $proCode);
        }

        return $retStatus;

    }

    /**
     * 优惠 奖励用户金币 娃娃币
     * @param string $proCode   产品编码
     * @param string $userId   用户ID
     * @param string $freeType  优惠类型
     * @param string $isBack  收益充值标志
     * @return bool
     */
    protected function freeUserCoin($proCode, $userId, $freeType, $isBack=0){
        $retStatus = ErrorCode::CODE_OK;
        //获取充值优惠
        $receiptFreeInfo = $this->getPayValue($freeType);
        if($isBack == ErrorCode::BABY_INCOME_BACK_TRUE){
            //为收益充值
            $lastFreeValue = isset($receiptFreeInfo['b_free_value']) ? $receiptFreeInfo['b_free_value'] : 0;
            $lastCoinValue = isset($receiptFreeInfo['b_coin_value']) ? $receiptFreeInfo['b_coin_value'] : 0;
        }else{
            $lastFreeValue = isset($receiptFreeInfo['free_value']) ? $receiptFreeInfo['free_value'] : 0;
            $lastCoinValue = isset($receiptFreeInfo['coin_value']) ? $receiptFreeInfo['coin_value'] : 0;
        }


        Log::info("freeUserCoin:  lastFreeValue= " . $lastFreeValue . ' lastCoinValue=' . $lastCoinValue);

        //更新用户金币
        $retStatus = $this->addUserCoin($proCode, $userId, $lastCoinValue, $lastFreeValue);
        if($retStatus == ErrorCode::CODE_OK){
            Log::info("freeUserCoin: end ok userId= " . $userId . ' product_code=' . $proCode . ' freeType=' . $freeType);
            //保存收益记录表
            $orderNum = self::createHeaderSeq(ErrorCode::BABY_HEADER_SEQ_IN, 12);
            $result = ActivityService::addUserIncome($userId, $proCode, $orderNum, '', 0, $lastCoinValue, $lastFreeValue, ErrorCode::BABY_EMPLOY_REASON_1, '分享收益', $iStatus=ErrorCode::BABY_INCOME_DONE);

        }else{
            Log::error("freeUserCoin: end failed userId= " . $userId . ' product_code=' . $proCode . ' freeType=' . $freeType);
        }

        return $retStatus;
/*        $db_user = Db::name('TUserConfig');
        $userInfo = $db_user->where('user_id', $userId)->find();

        if($userInfo && ($userInfo['user_id'] == $userId ) ){

            $data_userCoin = array('id'=> $userInfo['id']);
            if($lastFreeValue > 0){
                $saveFree = $userInfo['free_coin'] + $lastFreeValue;
                $arrFree = array('free_coin'=> $saveFree);
                $data_userCoin = array_merge($data_userCoin, $arrFree);
            }
            if($lastCoinValue > 0){
                $saveCoin = $userInfo['coin'] + $lastCoinValue;
                $arrCoin = array('coin'=> $saveCoin);
                $data_userCoin = array_merge($data_userCoin, $arrCoin);
            }

            $log_userCoin = json_encode($data_userCoin);
            Log::info("freeUserCoin: update user coins= " . $log_userCoin);

            $retBool = DataService::save($db_user, $data_userCoin);
        }

        if($retBool){
            Log::info("freeUserCoin: end ok userId= " . $userId . ' product_code=' . $proCode . ' freeType=' . $freeType);
            //保存收益记录表
            $orderNum = self::createHeaderSeq(ErrorCode::BABY_HEADER_SEQ_IN, 12);
            $result = ActivityService::addUserIncome($userId, $proCode, $orderNum, '', 0, $lastCoinValue, $lastFreeValue, ErrorCode::BABY_EMPLOY_REASON_1, '分享收益', $iStatus=ErrorCode::BABY_INCOME_DONE);

        }else{
            Log::error("freeUserCoin: end failed userId= " . $userId . ' product_code=' . $proCode . ' freeType=' . $freeType);
        }*/

    }

    /**
     * 消费用户金币 娃娃币
     * @param string $userId   用户ID
     * @param int $num   金币数量
     * @param int $coinType  金币类型  默认消耗娃娃币
     * @param string $orderId   消费订单ID 返回参数
     * @return int
     */
    protected function employUserCoin($userId, $num, $reason='', $remark='', $coinType=ErrorCode::BABY_EMPLOY_TYPE_FREE, &$orderId){
        Log::info("employUserCoin: start user_id=" . $userId . ' num=' . $num);

        //金币数量检查
        if( !is_numeric($num) ){
            Log::error("employUserCoin: coin num is not int num=" . $num);
            return ErrorCode::E_USER_EMPLOY_COIN_ERROR;
        }
        if( $num <= 0 ){
            Log::error("employUserCoin: coin num too low num=" . $num);
            return ErrorCode::E_USER_EMPLOY_COIN_ERROR;
        }

        //获取用户信息
        $userInfo = self::getUserInfo($userId);
        if( empty($userInfo) ){
            Log::error("employUserCoin: user info not found user_id=" . $userId);
            return ErrorCode::E_USER_NOT_FOUND;
        }

        Log::info("employUserCoin: user_id= " . $userId
            . ' coin= ' . $userInfo['coin'] . ' free_coin= ' . $userInfo['free_coin']);

        $userCoin = isset($userInfo['coin']) ? $userInfo['coin'] : 0;
        $userFreeCoin = isset($userInfo['free_coin']) ? $userInfo['free_coin'] : 0;

        $userAllCoins = $userCoin + $userFreeCoin;  //总金额
        $tmpUseCoin = 0;   //临时消耗金额 用于娃娃币不足的情况
        $lastCoin = $userCoin;                //扣费后的金币  初始值为当前用户数量
        $lastFreeCoin = $userFreeCoin;        //扣费后的娃娃币  初始值为当前用户数量

        if($userAllCoins < $num){
            Log::error("employUserCoin: all coin not more num=" . $num . ' all coin=' . $userAllCoins);
            return ErrorCode::E_USER_EMPLOY_COIN_ERROR;
        }

        if(ErrorCode::BABY_EMPLOY_TYPE_FREE == $coinType){
            //消费娃娃币
            if($userFreeCoin < $num){
                //娃娃币不足
                $lastFreeCoin = 0;
                $tmpUseCoin = $num - $userFreeCoin;

                Log::info("employUserCoin: tmpUseCoin= " . $tmpUseCoin
                    . ' info= ' . ErrorCode::$ERR_MSG[ErrorCode::E_USER_FREE_COIN_LACK]);

                if($userCoin < $tmpUseCoin){
                    //一般不会进入此分支 金币不足的情况
                    Log::error("employUserCoin: ???!!!why pull this error!!!??? tmpUseCoin= " . $tmpUseCoin . ' userCoin=' . $userCoin
                        . ' error= ' . ErrorCode::$ERR_MSG[ErrorCode::E_USER_COIN_LACK]);
                    return ErrorCode::E_USER_COIN_LACK;
                }

                $lastCoin = $userCoin - $tmpUseCoin;    //扣除 娃娃币剩下的金币

            }else{
                //娃娃币充足 直接扣费
                $lastFreeCoin = $userFreeCoin - $num;
            }


        }elseif( ErrorCode::BABY_EMPLOY_TYPE_COIN == $coinType ){
            //只消费金币
            if($userCoin < $num){
                Log::error("employUserCoin: num= " . $num . ' userCoin=' . $userCoin
                    . ' error= ' . ErrorCode::$ERR_MSG[ErrorCode::E_USER_COIN_LACK]);
                return ErrorCode::E_USER_COIN_LACK;
            }

            $lastCoin = $userCoin - $num;

        }else{
            //暂时不支持的消费模式
            Log::error("employUserCoin: num= " . $num . ' userCoin=' . $userCoin
                . ' error= ' . ErrorCode::$ERR_MSG[ErrorCode::E_NOT_SUPPORT]);
            return ErrorCode::E_NOT_SUPPORT;
        }

        //检查扣费是否正确
        $downNum = ($userCoin + $userFreeCoin) - $lastCoin - $lastFreeCoin;
        Log::info("employUserCoin: num= " . $num . ' downNum=' . $downNum
            . ' userCoin=' . $userCoin  . ' userFreeCoin=' . $userFreeCoin
            . ' lastCoin=' . $lastCoin  . ' lastFreeCoin=' . $lastFreeCoin);

        if($downNum != $num){
            //扣费金额计算错误
            Log::error("employUserCoin: num= " . $num . ' downNum=' . $downNum
                . ' userCoin=' . $userCoin  . ' userFreeCoin=' . $userFreeCoin
                . ' lastCoin=' . $lastCoin  . ' lastFreeCoin=' . $lastFreeCoin
                . ' error= ' . ErrorCode::$ERR_MSG[ErrorCode::E_USER_COUNT_COIN_ERROR]);
            return ErrorCode::E_USER_COUNT_COIN_ERROR;
        }

        $data_user = array();
        $billCoin = 0;       //消费订单记录 金币数量
        $billFreeCoin = 0;   //消费订单记录 娃娃币数量
        //扣费 保存数组
        if( $userFreeCoin > $lastFreeCoin ){
            //更新娃娃币
            $data_user['free_coin'] = $lastFreeCoin;
            $billFreeCoin = $userFreeCoin - $lastFreeCoin;
        }
        if( $userCoin > $lastCoin ){
            //更新金币
            $data_user['coin'] = $lastCoin;
            $billCoin = $userCoin - $lastCoin;
        }

        if( count($data_user) <= 0){
            Log::error("employUserCoin: data_user error! num= " . $num . ' downNum=' . $downNum
                . ' userCoin=' . $userCoin  . ' userFreeCoin=' . $userFreeCoin
                . ' lastCoin=' . $lastCoin  . ' lastFreeCoin=' . $lastFreeCoin
                . ' error= ' . ErrorCode::$ERR_MSG[ErrorCode::E_USER_COUNT_COIN_ERROR]);
            return ErrorCode::E_USER_COUNT_COIN_ERROR;
        }

        //扣费 更新数据库
        $result = self::updateUserInfo($userId, $data_user);
        if(ErrorCode::CODE_OK != $result){
            Log::error("employUserCoin: update user coin error num=" . $num . ' all coin=' . $userAllCoins);
            return $result;
        }

        //保存消费记录
        //$orderId = self::createTmpSeq(12);
        $orderId = self::createHeaderSeq(ErrorCode::BABY_HEADER_SEQ_COST, 12);
        self::updateUserBill($userId, $orderId, $billCoin, $billFreeCoin, $reason, $remark);

        if(ErrorCode::CODE_OK == $result){
            Log::info("employUserCoin: end ok user_id= " . $userId );
            $result = ErrorCode::CODE_OK;
        }else{
            Log::error("employUserCoin: end failed user_id= " . $userId );
            $result = ErrorCode::E_USER_EMPLOY_COIN_ERROR;
        }

        return $result;
    }

    /**
     * 更新消费订单表
     * @param string $userId   用户ID
     * @param int $coinType  金币类型  默认消耗娃娃币
     * @param int $num   金币数量
     * @return bool
     */
    protected function updateUserBill($userId, $orderId, $coin, $freeCoin, $reason, $remark){
        Log::info("updateUserBill: start user_id= " . $userId);

        $db_bill = Db::name('TUserBill');
        $data_bill = array(
            'user_id' => $userId,
            'order_id' => $orderId,
            'coin' => $coin,
            'free_coin' => $freeCoin,
            'reason' => $reason,
            'remark' => $remark,
        );

        $jsonData = json_encode($data_bill);
        Log::info("updateUserBill: update user bill= " . $jsonData );
        $result = DataService::save($db_bill, $data_bill);



        if($result){
            Log::info("updateUserBill: end ok user_id= " . $userId );
            $result = ErrorCode::CODE_OK;
        }else{
            Log::error("updateUserBill: end failed user_id= " . $userId );
            $result = ErrorCode::E_ROOM_UPDATE_FAIL;
        }
        return $result;
    }

    ////////////////////////////////////end 支付 金币 相关函数 ////////////////////////////////////

    ////////////////////////////////////start 现金红包 相关函数 ////////////////////////////////////
    /**
     * 小程序发送现金红包 目前使用企业打款方式
     * @param string $userId
     * @param string $openId
     * @param string $total_fee  单位分
     * @param string $reMark
     * @return bool
     */
    protected function miniRedPackage($userId, $orderNum, $total_fee, $reMark)
    {
        //获取openid
        $openId = $this->getOpenId($userId);

        Log::info("miniRedPackage start: order_num= " . $orderNum . ' openid=' . $openId);

        $pay = load_wx_mini('pay');
        //$result = PayService::createRedPackage($pay, $openId, $orderId);
        $result = PayService::createTransfer($pay, $openId, $orderNum, $total_fee, $reMark);

        if($result){
            Log::info("miniRedPackage: end ok order_no= " . $orderNum . ' openid=' . $openId);
            $result = ErrorCode::CODE_OK;
        }else{
            Log::error("miniRedPackage: end failed order_no= " . $orderNum . ' openid=' . $openId);
            $result = ErrorCode::E_USER_NOT_FOUND;
        }

        return $result;

    }
   ////////////////////////////////////end 现金红包 相关函数 ////////////////////////////////////

   ////////////////////////////////////start 抓取结果 相关函数 ////////////////////////////////////
    /**
     * 获取通知 抓取成功记录列表
     * @param string $startDate 起始时间
     * @param string $endDate   结束时间
     * @param string $status   抓取结果 1为成功 0 为失败
     * @return array
     */
    protected function getResultNotify($startDate, $endDate, $status=ErrorCode::BABY_CATCH_SUCCESS){
        //获取用户信息
        $db_result = Db::name('TRoomGameResult');

        $resultList = $db_result->where('result', $status)
            ->whereBetween('create_at', [$startDate, $endDate])->order('create_at desc')
            ->select();

        return $resultList;

    }

    /**
     * 获取抓取记录信息
     * @param string $userId
     * @param string $orderId   抓取消费订单
     * @return array
     */
    protected function getResultInfo($orderId){
        $db_result = Db::name('TRoomGameResult');

        $field = ["order_id" => $orderId];
        $resultInfo = $db_result->where($field)->find();
        if($resultInfo && ($resultInfo['order_id'] == $orderId ) ){
            return $resultInfo;
        }else{
            $resultInfo = '';
        }

        return $resultInfo;

    }

    /**
     * 获取抓取记录信息列表
     * @param string $userId
     * @param string $result   抓取结果 1为成功 0 为失败
     * @param string $status   结果处理状态
     * @param string $isCount   是否只获取记录条数 0为无效 1 为有效
     * @return array
     */
    protected function getResultList($userId, $result, $status, $isCount=0){
        //获取用户信息
        $db_result = Db::name('TRoomGameResult');

        $field = ["user_id" => $userId];
        if($status != ErrorCode::BABY_POST_ALL)
        {
            $field['status'] = $status;
        }
        if($result == ErrorCode::BABY_CATCH_SUCCESS)
        {
            //获取抓取成功记录
            $field['result'] = $result;
        }

        if(0 == $isCount){
            $resultList = $db_result->where($field)->select();
        }else{
            $resultList = $db_result->where($field)->count();
        }

        /*foreach($resultList as $key => $record){
            //获取每条结果记录的礼物信息

        }*/

        return $resultList;

    }

    /**
     * 更新抓取记录信息状态
     * @param string $userId
     * @param string $orderId   抓取结果 订单ID
     * @param string $status   结果处理状态
     * @return array
     */
    protected function updateResultStatus($userId, $orderId, $status){
        $data_result = array();
        Log::info("updateResultStatus: start order_id= " . $orderId . ' status=' . $status . ' user_id=' . $userId);

        $db_result = Db::name('TRoomGameResult');

        $field = ["order_id" => $orderId];
        $resultInfo = $db_result->where($field)->find();
        if($resultInfo && ($resultInfo['user_id'] == $userId ) ){
            //更新
            $data_result['id'] = $resultInfo['id'];
            $data_result['status'] = $status;
            //异常检查
            if( $status < $resultInfo['status']){
                //更新状态值 小于 当前结果记录状态值， 记录错误日志
                Log::error("updateResultStatus: !!! status issue !!! order_id= " . $orderId . 'cur status=' . $resultInfo['status'] . ' new status=' . $status );
            }

        }else{
            Log::error("updateResultStatus: not found order_id= " . $orderId . ' status=' . $status . ' user_id=' . $userId );
            $result = ErrorCode::E_NOT_SUPPORT;
            return $result;
        }

        $retBool = DataService::save($db_result, $data_result);
        if($retBool){
            Log::info("updateResultStatus: end ok order_id= " . $orderId . ' status=' . $status . ' user_id=' . $userId );
            $result = ErrorCode::CODE_OK;
        }else{
            Log::error("updateResultStatus: end failed order_id= " . $orderId . ' status=' . $status . ' user_id=' . $userId );
            $result = ErrorCode::E_NOT_SUPPORT;
        }
        return $result;
    }

    /**
     * 获取地址信息
     * @param string $addrId
     * @return array
     */
    protected function getAddressInfo($addrId){
        //获取用户信息
        $db_result = Db::name('TUserPostalAddress');

        $field = ["id" => $addrId, "is_deleted" => '0'];
        $addrInfo = $db_result->where($field)->find();
        if($addrInfo && ($addrInfo['id'] == $addrId ) ){
            return $addrInfo;
        }
        return '';
    }

    /**
     * 获取用户所有 地址信息
     * @param string $userId
     * @param string $isDefault  是否获取默认地址
     * @param string $isAll  是否获取该用户所有的地址  1 为获取所有的
     * @return array
     */
    protected function getAddressByUser($userId, $isDefault=0){
        //获取地址信息
        $db_address = Db::name('TUserPostalAddress');

        //获取默认地址信息

        $field = ["user_id" => $userId, "is_default" => 1, "is_deleted" => '0'];
        $addrInfo = $db_address->where($field)->find();
        if( empty($addrInfo) && ($isDefault != 0) ){

            return '';   //未找到默认地址信息

        }elseif( empty($addrInfo) ){
            //没有默认信息 获取用户最新的地址信息
            $field = ["user_id" => $userId, "is_deleted" => '0'];
            $addrInfo = $db_address->where($field)->order('create_at desc')->find();

        }

        if( empty($addrInfo) ){
            return '';   //没有找到任何地址信息
        }

        return $addrInfo;
    }


    /**
     * 更新地址信息
     * @param string $addrId
     * @param string $name
     * @param string $phone
     * @param string $address
     * @param string $isDefault
     * @return array
     */
    protected function updateAddressInfo($addrId, $userId, $name, $phone, $address, $isDefault){
        //检查更新数据
        $data_addr = array();
        Log::info("updateAddressInfo: start address_id= " . $addrId );

        if($userId != ''){
            $data_addr['user_id'] = $userId;
        }
        if($name != ''){
            $data_addr['name'] = $name;
        }
        if($phone != ''){
            $data_addr['phone'] = $phone;
        }
        if($address != ''){
            $data_addr['address'] = $address;
        }
        if($isDefault != ''){
            $data_addr['is_default'] = $isDefault;
        }

        $db_address = Db::name('TUserPostalAddress');

        $field = ["id" => $addrId];
        $addrInfo = $db_address->where($field)->find();
        if($addrInfo && ($addrInfo['id'] == $addrId ) ){
            //更新
            $data_addr['id'] = $addrId;

        }
        $data_addr['update_at'] = date('Y-m-d H:i:s',time());
        $retBool = DataService::save($db_address, $data_addr);
        if($retBool){
            Log::info("updateAddressInfo: end ok address_id= " . $addrId );
            $result = ErrorCode::CODE_OK;
        }else{
            Log::error("updateAddressInfo: end failed address_id= " . $addrId );
            $result = ErrorCode::E_NOT_SUPPORT;
        }
        return $result;
    }

    /**
     * 删除地址信息
     * @param string $addrId
     * @param string $userId
     * @return array
     */
    protected function deleteAddressInfo($addrId, $userId){

        Log::info("deleteAddressInfo: start address_id= " . $addrId );


        $db_address = Db::name('TUserPostalAddress');
        //检查更新数据
        $field = ["id" => $addrId];
        $addrInfo = $db_address->where($field)->find();
        if($addrInfo && ($addrInfo['id'] == $addrId ) ){
            //更新
            if($userId != $addrInfo['user_id']){
                Log::error("deleteAddressInfo: user failed address_id= " . $addrId . 'user_id=' . $userId);
                $result = ErrorCode::E_NOT_SUPPORT;
                return;
            }

        }



        $data_addr = ["id" => $addrId, "is_deleted" => 1, "update_at" => date('Y-m-d H:i:s',time()) ];
        //$retBool = $db_address->where($field)->delete();
        $retBool = DataService::save($db_address, $data_addr);

        if($retBool){
            Log::info("deleteAddressInfo: end ok address_id= " . $addrId );
            $result = ErrorCode::CODE_OK;
        }else{
            Log::error("deleteAddressInfo: end failed address_id= " . $addrId );
            $result = ErrorCode::E_NOT_SUPPORT;
        }
        return $result;
    }
   ////////////////////////////////////end 抓取结果 相关函数 ////////////////////////////////////

}
