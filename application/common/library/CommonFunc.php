<?php

namespace app\common\library;

use app\common\library\Auth;
use think\Config;
use think\Request;
use think\Response;
use think\Cache;
use think\Db;
use fast\Http;
use think\cache\driver\Redis;

/**
 * API控制器基类
 */
class CommonFunc
{
    public function getPlatformRatio($admin_id){
        $ratio = Db::table('re_ratio')->where('uid','=',$admin_id)->find();
        if(empty($ratio)){
            $ratio = Db::table('re_ratio')->where('uid','=',1)->find();
        }
        return $ratio;
    }

    //获取百度token
    public function getBaiduToken(){
        # 填写网页上申请的appkey 如 $apiKey="g8eBUMSokVB1BHGmgxxxxxx"
        $apiKey = "cqjqUbxfGFZRy3r12lctEUZU";
        # 填写网页上申请的APP SECRET 如 $secretKey="94dc99566550d87f8fa8ece112xxxxx"
        $secretKey = "AxfWLfy6O7rkAcNOf7QiCZexWPdNwB2Y";
        $auth_url = "https://openapi.baidu.com/oauth/2.0/token?grant_type=client_credentials&client_id=".$apiKey."&client_secret=".$secretKey;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $auth_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //信任任何证书
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // 检查证书中是否设置域名,0不验证
        curl_setopt($ch, CURLOPT_VERBOSE, DEMO_CURL_VERBOSE);
        $res = curl_exec($ch);
        if(curl_errno($ch))
        {
            print curl_error($ch);
        }
        curl_close($ch);

        $flag = 0;
        $response = json_decode($res, true);

        if (!isset($response['access_token'])){
            //echo "ERROR TO OBTAIN TOKEN\n";
            $flag = 1;
          //  exit(1);
        }
        if (!isset($response['scope'])){
//            /echo "ERROR TO OBTAIN scopes\n";
            //exit(2);
            $flag == 2;
        }

        if (!in_array('audio_tts_post',explode(" ", $response['scope']))){
            //echo "DO NOT have tts permission\n";
            // 请至网页上应用内开通语音合成权限
            //exit(3);
            $flag = 3;
        }

        $token = $response['access_token'];
        if(!empty($token)){
            return $token;
        }else{
            return null;
        }

        /** 公共模块获取token结束 */









    }



}
