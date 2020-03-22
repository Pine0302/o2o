<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Sms as Smslib;
use app\common\model\User;
use think\addons\Controller;
use think\Session;
use think\Db;
use think\cache\driver\Redis;
use addons\alisms\library\Alisms;
use aliyunSms\sendSms;
use fast\Http;

/*require_once dirname(__DIR__) . "../../../extend/aliyunsms/demo/sendSms.php";*/

/**
 * 手机短信接口
 */
class Sms extends Api
{

    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }

    public function test(){
     /*   $sendSmsObj = new sendSms();
        $sendSmsObj->sendSmstest();*/

    }



    public function sendProfileSms(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'] ?? '';
        $mobile = $data['mobile'];
        $template = "SMS_152514269";
        $sign = '蓝火柴ITAFE';

        if((!empty($template))&&(!empty($mobile))){
            try {
                $user_info = $this->getGUserInfo($sess_key);


                //发短信
                //生成随机6位数
                $code = rand(100000,999999);
                $param = array(
                    'code'=>$code,
                );
             //   error_log(var_export($param,1),3,"/data/wwwroot/mini3.pinecc.cn/tt.txt");
                $profile_key = $user_info['openid']."_profile";
                $result_set = $this->redis->set($profile_key,$code);

                $code = $this->redis->get($profile_key);
                $sendSmsObj = new sendSms();

                $result = $sendSmsObj->sendSmstest($code,$mobile,$template,$sign);
                if($result->Code=="OK"){
                  //  $this->wlog($result->Code,'smslog.txt');
                    $this->success("发送成功");
                }else
                {
                   // $this->error('网络繁忙,请稍后再试');
                 //   $this->wlog($result->Message,'smslog.txt');
                    $this->error("发送失败！失败原因：" . $result->Message);
                }
            } catch (Exception $e) {
                $this->error('网络繁忙,请稍后再试');
            }
        }else{
            $this->error('缺少必要的参数',null,2);
        }


















    }

























    /**
     * 发送验证码
     *
     * @param string    $mobile     手机号
     * @param string    $event      事件名称
     */
    public function send()
    {
        $mobile = $this->request->request("mobile");
        $event = $this->request->request("event");
        $event = $event ? $event : 'register';

        $last = Smslib::get($mobile, $event);
        if ($last && time() - $last['createtime'] < 60)
        {
            $this->error(__('发送频繁'));
        }
        if ($event)
        {
            $userinfo = User::getByMobile($mobile);
            if ($event == 'register' && $userinfo)
            {
                //已被注册
                $this->error(__('已被注册'));
            }
            else if (in_array($event, ['changemobile']) && $userinfo)
            {
                //被占用
                $this->error(__('已被占用'));
            }
            else if (in_array($event, ['changepwd', 'resetpwd']) && !$userinfo)
            {
                //未注册
                $this->error(__('未注册'));
            }
        }
        $ret = Smslib::send($mobile, NULL, $event);
        if ($ret)
        {
            $this->success(__('发送成功'));
        }
        else
        {
            $this->error(__('发送失败'));
        }
    }

    /**
     * 检测验证码
     *
     * @param string    $mobile     手机号
     * @param string    $event      事件名称
     * @param string    $captcha    验证码
     */
    public function check()
    {
        $mobile = $this->request->request("mobile");
        $event = $this->request->request("event");
        $event = $event ? $event : 'register';
        $captcha = $this->request->request("captcha");

        if ($event)
        {
            $userinfo = User::getByMobile($mobile);
            if ($event == 'register' && $userinfo)
            {
                //已被注册
                $this->error(__('已被注册'));
            }
            else if (in_array($event, ['changemobile']) && $userinfo)
            {
                //被占用
                $this->error(__('已被占用'));
            }
            else if (in_array($event, ['changepwd', 'resetpwd']) && !$userinfo)
            {
                //未注册
                $this->error(__('未注册'));
            }
        }
        $ret = Smslib::check($mobile, $captcha, $event);
        if ($ret)
        {
            $this->success(__('成功'));
        }
        else
        {
            $this->error(__('验证码不正确'));
        }
    }


}
