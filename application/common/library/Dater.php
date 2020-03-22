<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/21 0021
 * Time: 上午 10:44
 */
namespace app\common\library;



 class Dater
{
     /**
      * 构造函数
      * @param $sessionKey string 用户在小程序登录后获取的会话密钥
      * @param $appid string 小程序的appid
      */
     public function __construct()
     {

     }


     //生成社交时间戳
     //1小时以内 显示xx分钟前
     //24个小时以内,显示xx小时前
     //其余 日期+时间 06-28
    public function socialDateDisplay($str){
        $now = time();
        $time_diff = $now - $str;
        if($time_diff<60*60){
            $min = ceil($time_diff/60);
            $result = $min."分钟前";
        }elseif(($time_diff>60*60)&&($time_diff<24*60*60)){
            $hour = ceil($time_diff/(60*60));
            $result = $hour."小时前";
        }elseif(($time_diff>24*60*60)&&($time_diff<30*24*60*60)){
            $day = ceil($time_diff/(60*60*24));
            $result = $day.'天前';
        }else{
            $result = date('m-d',$str);
        }
        return $result;
    }

}
