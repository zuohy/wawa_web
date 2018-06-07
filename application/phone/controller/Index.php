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
use service\HttpService;
use service\RoomService;
use service\WxBizDataCrypt;
use service\ErrorCode;
use think\Db;
use think\session\driver\Memcache;
use think\View;
use think\Log;
/**
 * 手机入口
 * Class Index
 * @package app\admin\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/02/15 10:41
 */
class Index extends BasicBaby
{

    /**
     * 指定当前数据表
     * @var string
     */
    public $table = 'TRoomInfo';

    /**
     * 手机框架布局
     * @return View
     */
    public function index()
    {

        //获取session_key
        $code = isset($_GET['code']) ? $_GET['code'] : '';
        $sessionId = isset($_GET['sessionId']) ? $_GET['sessionId'] : '';
        $encryptedData = isset($_GET['encryptedData']) ? $_GET['encryptedData'] : '';
        $iv = isset($_GET['iv']) ? $_GET['iv'] : '';

        $dataObj = null;

        if($code != ''){
            //登录消息
            //获取session_key open_id
            $appId = '?appid=' . sysconf('wechat_mini_appid'); //'wx543f399af45d82ba';
            $secret = '&secret=' . sysconf('wechat_mini_appsecret'); //'7b38dd5915b836b96eb41540d27972b9';
            $jsCode = '&js_code=' . $code;
            $type = '&grant_type=authorization_code';

            //https://api.weixin.qq.com/sns/jscode2session?appid=APPID&secret=SECRET&js_code=JSCODE&grant_type=authorization_code
            $reqUrl = 'https://api.weixin.qq.com/sns/jscode2session' .$appId . $secret . $jsCode . $type ;
            $info = HttpService::get($reqUrl);

            //写入缓存
            $cacSeq = $this->_createTmpSeq();
            $options = array('expire' => '60');
            cache($cacSeq, $info, $options);

            return $cacSeq;
        }
        if($sessionId != ''){
            //用户信息
            $list = cache($sessionId);
            //$sessionKey = $list['session_key'];

            if($list == false){
                $list = '';
                return $list;
            }

            //解析union id openid
            $appId = sysconf('wechat_mini_appid'); //'wx543f399af45d82ba';
            $listObj = json_decode($list);
            $sessionKey = $listObj->session_key;
            Log::info("index: sessionKey= " . $sessionKey);

            $pc = new WXBizDataCrypt($appId, $sessionKey);
            $errCode = $pc->decryptData($encryptedData, $iv, $data );

            if($errCode != 0){
                return $errCode;
            }
            Log::info("index: user info= " . $data);
            $dataObj = json_decode($data);
        }


        //获取微信用户信息
        if($dataObj->openId){
            $unionId = isset($dataObj->unionId) ? $dataObj->unionId : '';
            $retMsg = $this->newUser($unionId, $dataObj->openId, $dataObj->nickName, $dataObj->avatarUrl,
                $dataObj->gender, $dataObj->country, $dataObj->province, $dataObj->city);

        }

        return $retMsg;


    }

    /**
     * 生成缓存唯一序号 (失败返回 NULL )
     * @param int $length 序号长度
     * @return string
     */
    private function _createTmpSeq($length = 10)
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
     * 后台主菜单权限过滤
     * @param array $menus 当前菜单列表
     * @param array $nodes 系统权限节点数据
     * @param bool $isLogin 是否已经登录
     * @return array
     */
    private function _filterMenuData($menus, $nodes, $isLogin)
    {
        foreach ($menus as $key => &$menu) {
            !empty($menu['sub']) && $menu['sub'] = $this->_filterMenuData($menu['sub'], $nodes, $isLogin);
            if (!empty($menu['sub'])) {
                $menu['url'] = '#';
            } elseif (preg_match('/^https?\:/i', $menu['url'])) {
                continue;
            } elseif ($menu['url'] !== '#') {
                $node = join('/', array_slice(explode('/', preg_replace('/[\W]/', '/', $menu['url'])), 0, 3));
                $menu['url'] = url($menu['url']);
                if (isset($nodes[$node]) && $nodes[$node]['is_login'] && empty($isLogin)) {
                    unset($menus[$key]);
                } elseif (isset($nodes[$node]) && $nodes[$node]['is_auth'] && $isLogin && !auth($node)) {
                    unset($menus[$key]);
                }
            } else {
                unset($menus[$key]);
            }
        }
        return $menus;
    }

    /**
     * 手机首页
     * @return View
     */
    public function main()
    {
        $userId = isset($_GET['userId']) ? $_GET['userId'] : '';
        Log::info("main: userId= " . $userId);

        $this->setOpenId($userId);

        $this->title = '首页' . $userId;
        $this->assign('user_id', $userId);

        //获取轮播图信息
        $db_banner = Db::name('TSysBanner');

        $field = ['is_deleted' => '0'];
        $bannerList = $db_banner->where($field)->order('create_at desc')->select();
        $this->assign('banner_list', $bannerList);


        $db = Db::name($this->table)->where(['is_deleted' => '0']);

        return parent::_list($db);

    }

    /**
     * 手机用户房间设备列表
     * @return View
     */
    public function device()
    {
        $uRoomId = $this->request->get('room_id');
        $uGiftId = $this->request->get('gift_id');
        $uPrice = $this->request->get('price');
        //$uRoomId = isset($uRoomId) ? $uRoomId : '';
        Log::info("device: uRoomId= " . $uRoomId);

        //保存用户房间ID
        session('room_id', $uRoomId);

        //房间 礼物信息
        $giftInfo = RoomService::getGiftInfo($uGiftId);
        $giftName = isset($giftInfo['gift_name']) ? $giftInfo['gift_name'] : '';
        $this->assign('gift_name', $giftName);
        $this->assign('price', $uPrice);

        //拉取设备列表
        $this->title = '设备列表' . $uRoomId;

        $db = Db::name('TDevRoomInfo')->where(['is_deleted' => '0']);

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
            $status = isset($vo['status']) ? $vo['status'] : '';
            $dev_status = isset($vo['dev_status']) ? $vo['dev_status'] : '';
            $tag = isset($vo['tag']) ? $vo['tag'] : '';
            $dev_tag = isset($vo['dev_tag']) ? $vo['dev_tag'] : '';
            if( $dev_status ){
                $status = $dev_status;
            }
            if( $dev_tag ){
                $tag = $dev_tag;
            }

            if( $status == ErrorCode::BABY_ROOM_STATUS_BUILD ){
                $vo['status_c'] = '修建中';
            }elseif( $status == ErrorCode::BABY_ROOM_STATUS_ON ){
                $vo['status_c'] = '空闲';
            }elseif( $status == ErrorCode::BABY_ROOM_STATUS_BUSY ){
                $vo['status_c'] = '正在游戏';
            }elseif( $status == ErrorCode::BABY_ROOM_STATUS_OFF ){
                $vo['status_c'] = '维护';
            }

            if( $tag == ErrorCode::BABY_ROOM_MODEL_COM ){
                $vo['tag_c'] = '普通模式';
            }else{
                $vo['tag_c'] = '英雄模式';
            }
        }

    }

    /**
     * 主机登录
     * @return View
     */
    public function mainLogin()
    {

        $this->success('登录成功，正在进入系统...');
    }

    /**
     * 游戏规则说明
     * @return View
     */
    public function rule()
    {


        return view('', ['title' => '游戏说明']);
    }

}


