<?php

/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Date: 2015-09-14
 */


namespace app\admin\logic;
use think\Db;
use think\Model;

class CouponLogic extends Model
{

    /**
     * 生成优惠券code码 order_sn
     * @return string
     */
    public function get_coupon_sn($user_id)
    {

        $coupon_sn = null;
        $coupon_sn =   uniqid($user_id).'_coupon_'.$user_id.'_'.rand(1000,9999); // 订单编号
        return  $coupon_sn;
        // 保证不会有重复订单号存在  //批量生成优惠券不适合做sql判断
/*        while(true){

            $coupon_sn =   uniqid($user_id,true).'_coupon_'.$user_id.'_'.rand(1000,9999); // 订单编号
            $coupon_sn_count = M('coupon_list')->where("code_sn = ".$coupon_sn)->count();
            if($coupon_sn_count == 0)
                break;
        }

        return $coupon_sn;*/
    }


}