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
            $this->resetMiniPay();
            return ['code' => 2, 'order_no' => $order_no];
        }

        $pay = load_wx_mini('pay');
        $options = PayService::createWechatPayJsPicker($pay, $openId, $order_no, $total_fee, 'JSAPI支付测试2');
        if ($options === false) {
            $options = ['code' => 3, 'msg' => "创建支付失败，{$pay->errMsg}[$pay->errCode]"];
        }
        $options['order_no'] = $order_no;
        Log::info("miniPay end: options= " . json_encode($options));
        return $options;
        //return json($options);

    }
    /**
     * 小程序 支付完成 清除缓存订单
     * @return bool
     */
    protected function resetMiniPay(){
        session('pay-mini-order-no', null);
    }

}
