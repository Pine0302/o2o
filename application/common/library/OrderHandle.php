<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/21 0021
 * Time: 上午 10:44
 */
namespace app\common\library;

use think\Db;

class OrderHandle
{
    /**
     * 查看
     * prefix  b2p:公司支付到平台
     */
    public function createOrderBak($prefix="re")
    {
        return strtoupper($prefix."_".uniqid());
    }

    public function createOrder($prefix="re")
    {
        return strtoupper(uniqid());
    }



    //给用户发券(单张)
    public function sendCoupon($user_id,$coupon_info,$is_rec=0){
        $code = uniqid($user_id).'_coupon_'.$user_id.'_'.rand(1000,9999); //单号

        $insert_coupon_list_arr = [
            'cid'=>$coupon_info['id'],
            'type'=>$coupon_info['type'],
            'uid'=>$user_id,
            'money'=>$coupon_info['money'],
            'condition'=>$coupon_info['condition'],
            'use_start_time'=>$coupon_info['use_start_time'],
            'use_end_time'=>$coupon_info['use_end_time'],
            'method'=>1,
            'code'=>$code,
            'send_time'=>time(),
            'status'=>0,
        ];
      //  error_log(json_encode($is_rec),3,"/data/wwwroot/www.itafe.cn/public/log/tt.txt");
        if($is_rec==1){
          //  error_log(json_encode($user_id),3,"/data/wwwroot/www.itafe.cn/public/log/tt.txt");
          //  $result1 = Db::name('users')->where('id','=',$user_id)->setInc('rec_num');

        //    error_log(json_encode($result1),3,"/data/wwwroot/www.itafe.cn/public/log/tt.txt");
        }
        $result = Db::name('coupon_list')->insert($insert_coupon_list_arr);
        if(!empty($result)){
            return $insert_coupon_list_arr;
        }


    }


}
