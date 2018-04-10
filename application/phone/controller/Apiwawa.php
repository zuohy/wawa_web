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
use think\Log;
use service\DataService;
use GatewayClient\Gateway;
/**
 * 手机接口
 * Class Index
 * @package app\phone\controller
 * @author Zuohy
 * @date 2018/04/15 10:41
 */
class Apiwawa extends BasicBaby
{

    public  $retMsg = array('code' => '0', 'type' => '', 'msg' => 'ok', 'data' => '');
    /**
     * 手机接口入口
     * @return result
     */
    public function index()
    {
        $this->_initRetMsg();

        if (!$this->request->isPost()) {
            $this->retMsg['code'] = '-1';
            $this->retMsg['msg'] = 'no post msg';
            $retMsg = json_encode($this->retMsg);
            Log::info("index: http msg retMsg= " . $retMsg);
            return $retMsg;
        }
        $postArr = $this->request->post();
        $jPack = isset($postArr['json']) ? $postArr['json'] : '';
        $cmdType = isset($jPack['type']) ? $jPack['type'] : '';

        Log::info("index: http rev msg type= " . $cmdType);


        // 处理接口消息
        switch($cmdType) {
            case 'share_login':
                //分享者登录
                $retStatus = $this->_buildShareRel( $jPack['user_id'], $jPack['code_father']);
                if(0 == $retStatus){
                    $this->freeUserCoin($jPack['user_id'], WAWA_COIN_TYPE_SHARE);

                    $db_user = Db::name('TUserConfig');
                    $userInfo = $db_user->where('code', $jPack['code_father'])->find();
                    if($userInfo && ($userInfo['code'] == $jPack['code_father'] ) ){
                        $this->freeUserCoin($userInfo['user_id'], WAWA_COIN_TYPE_SHARE);
                    }

                }
                $this->retMsg['code'] = $retStatus;
                //$this->retMsg['type'] = $cmdType;
                $this->retMsg['data'] = $jPack['code_father'];
                break;
            case 'room_servers':
                //获取房间 服务器信息  视频服务器 聊天服务器 设备服务器
                $retData = array(
                    'wa_video_url' => sysconf('wa_video_url'),
                    'wa_video_port' => sysconf('wa_video_port'),
                    'wa_control_url' => sysconf('wa_control_url'),
                    'wa_control_port' => sysconf('wa_control_port'),
                    'wa_chat_url' => sysconf('wa_chat_url'),
                    'wa_chat_port' => sysconf('wa_chat_port')
                );
                $this->retMsg['data'] = $retData;
                break;
            case 'chat_bind':
                Gateway::$registerAddress = '127.0.0.1:1236';
                $userId = session('user_id');
                $roomId = '11';

                // client_id与uid绑定
                Gateway::bindUid($jPack['client_id'], $userId);
                // 加入某个群组（可调用多次加入多个群组）
                Gateway::joinGroup($jPack['client_id'], $roomId);

                // 向任意uid的网站页面发送数据
                $chatArr = array(
                    'type' => 'chat_msg',
                    'content' => 'bind ok'
                );
                $chatData = json_encode($chatArr);
                Gateway::sendToUid($userId, $chatData);
                break;
            case 'chat_msg':
                $chatArr = array(
                    'type' => 'chat_msg',
                    'content' => $jPack['content']
                );
                // 向任意群组的网站页面发送数据
                $chatData = json_encode($chatArr);
                Gateway::sendToGroup('11', $chatData);

                break;

            default:
                $this->retMsg['code'] = '-2';
                $this->retMsg['msg'] = 'no support msg';
                break;
        }

        $this->retMsg['type'] = $cmdType;
        $retMsg = json_encode($this->retMsg);
        Log::info("index: http msg retMsg= " . $retMsg);
        return $retMsg;

    }

    /**
     * 初始化返回消息结构
     * @return array
     */
    private function _initRetMsg()
    {
        $retMsg['code'] = '0';
        $retMsg['type'] = '';
        $retMsg['msg'] = 'ok';
        $retMsg['data'] = '';
        return $this->retMsg;
    }

    /**
     * 保存分享记录表
     * @param array $userAccept 当前被分享者
     * @param array $userFather 分享者
     * @param int $isStatus 是否已经分享
     *
     * @return array
     */
/*    private function _saveShareHis($userAccept, $userFather, $isStatus)
    {
        //保存分享记录表
        $db_share_his = Db::name('TUserShareHis');

        $i_code = isset($userAccept['code']) ? $userAccept['code'] : '';
        $i_name = isset($userAccept['name']) ? $userAccept['name'] : '';
        $i_pic = isset($userAccept['pic']) ? $userAccept['pic'] : '';
        $i_gender = isset($userAccept['gender']) ? $userAccept['gender'] : '';
        $i_code_father = isset($codeFather) ? $codeFather : '';
        $i_name_father = isset($userInvitation['name']) ? $userInvitation['name'] : '';
        $i_pic_father = isset($userInvitation['pic']) ? $userInvitation['pic'] : '';
        $i_gender_father = isset($userInvitation['gender']) ? $userInvitation['gender'] : '';

        $data_share = array(
            'code'=> $i_code,
            'name'=> $i_name,
            'pic'=> $i_pic,
            'gender'=> $i_gender,

            'code_father'=> $i_code_father,
            'name_father'=> $i_name_father,
            'pic_father'=> $i_pic_father,
            'gender_father'=> $i_gender_father,
            's_status'=> $isShare);
        $result = DataService::save($db_share_his, $data_share);
    }
*/
    /**
     * 建立分享关系 邀请码对应用户
     * @return array
     */
    private function _buildShareRel($userId, $codeFather)
    {
        $isShare = 0;  //0 分享关联成功 1 失败 已经被分享  2 更换
        //保存分享记录表
        $db_share_his = Db::name('TUserShareHis');

        //查找当前登录用户
        $db_user = Db::name('TUserConfig');
        $userAccept = $db_user->where('user_id', $userId)->find();   //被分享者信息
        $userInvitation = $db_user->where('code', $codeFather)->find();   //邀请者信息

        if($userInvitation && $userInvitation['code'] == $codeFather){
            if($userAccept && $userAccept['user_id'] == $userId){
                if($userAccept['code_father'] != ''){
                    //当前用户已经被分享了，暂时不更新分享者邀请码
                    $isShare = 1;
                }else{
                    //更新当前用户的父级邀请码
                    $data_user = array('id'=> $userAccept['id'], 'code_father'=> $codeFather);
                    $result = DataService::save($db_user, $data_user);
                    $isShare = 0;
                }

            }else{
                //记录日志
                $isShare = 3;  //没有被邀请者信息

            }
        }else{
            //记录日志
            $isShare = 9; //没有找到邀请者信息
        }

        //保存分享记录表
        $i_code = isset($userAccept['code']) ? $userAccept['code'] : '';
        $i_name = isset($userAccept['name']) ? $userAccept['name'] : '';
        $i_pic = isset($userAccept['pic']) ? $userAccept['pic'] : '';
        $i_gender = isset($userAccept['gender']) ? $userAccept['gender'] : '';
        $i_code_father = isset($codeFather) ? $codeFather : '';
        $i_name_father = isset($userInvitation['name']) ? $userInvitation['name'] : '';
        $i_pic_father = isset($userInvitation['pic']) ? $userInvitation['pic'] : '';
        $i_gender_father = isset($userInvitation['gender']) ? $userInvitation['gender'] : '';

        $data_share = array(
            'code'=> $i_code,
            'name'=> $i_name,
            'pic'=> $i_pic,
            'gender'=> $i_gender,

            'code_father'=> $i_code_father,
            'name_father'=> $i_name_father,
            'pic_father'=> $i_pic_father,
            'gender_father'=> $i_gender_father,
            's_status'=> $isShare);
        $result = DataService::save($db_share_his, $data_share);
        Log::info("_buildShareRel: isShare= " . $isShare);
        return $isShare;

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

        return $menus;
    }




    /**
     * 列表数据处理
     * @param type $list
     */
    protected function _data_filter(&$list)
    {


    }



}


