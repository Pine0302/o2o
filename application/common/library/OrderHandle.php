<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/21 0021
 * Time: 上午 10:44
 */
namespace app\common\library;

use app\common\entity\OrderE;
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
        return strtoupper($prefix."_".uniqid());
    }

    /**
     * error_code 0 检测通过   1:有问题
     * @param $user_info
     * @param $order_info
     * @param $pay_type
     * @return array
     */
    public function  checkOrder($user_info,$order_info,$pay_type){
        $error_code = 0 ;
        $msg = '';
        if($order_info['pay_status']==1) {
            $error_code = 1;  //已支付
            $msg = "已支付";
            return [
                'error_code'=>$error_code,
                'msg'=>$msg,
            ];
        }
        if($order_info['order_amount']=="0.00") {
            $error_code = 1;  //已支付
            $msg = "订单有误";
            return [
                'error_code'=>$error_code,
                'msg'=>$msg,
            ];
        }
        if($pay_type==OrderE::PAY_TYPE['MONEY']){
            if($user_info['user_money']<$order_info['order_amount']){
                $error_code = 1;  //已支付
                $msg = "余额不足";
                return [
                    'error_code'=>$error_code,
                    'msg'=>$msg,
                ];
            }
        }
        return [
            'error_code'=>$error_code,
            'msg'=>$msg,
        ];
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
