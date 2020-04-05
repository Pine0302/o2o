<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Sms as Smslib;
use app\common\model\User;
use think\addons\Controller;
use think\Session;
use think\Db;
use think\cache\driver\Redis;
use alisms\library\Alisms;


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





    public function sendProfileSms(){
        $data = $this->request->post();
        $openid = $this->analysisUserJwtToken();
        $mobile = $data['mobile'];
        $template = "SMS_187215748";
        $sign = '骑士汇';
        if(!empty($mobile)){
            try {
                //发短信
                //生成随机6位数
                $code = rand(100000,999999);
                $param = array(
                    'code'=>$code,
                );
                $profile_key = $openid."_profile";
                $result_set = $this->redis->set($profile_key,$code);$code = $this->redis->get($profile_key);

                $alisms = new Alisms();
                $ret = $alisms->mobile($mobile)
                    ->template($template)
                    ->sign($sign)
                    ->param($param)
                    ->send();
                if ($ret)
                {
                    $this->success("发送成功",$param);
                }
                else
                {
                    $this->error("发送失败！失败原因：" . $alisms->getError());
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
