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
 * Author: IT宇宙人
 * Date: 2015-09-09
 */
namespace app\common\repository;

use app\common\entity\CashOrderE;
use app\common\entity\GoodsE;
use app\common\entity\MemberCashLogE;
use app\common\entity\MerchCashLogE;
use app\common\entity\OrderE;
use app\common\entity\OrderGoodsE;
use app\common\entity\RiderCompany;
use think\Db;
use think\Model;
use app\common\entity\User;
use app\common\entity\RiderCompanyBind;


class OrderRepository
{

    public function getOrderBySn($order_sn){
        $orderDb = Db::name(OrderE::SHORT_TABLE_NAME);
        $orderDb->where('order_sn','=',$order_sn);
        $result = $orderDb->find();
        $orderDb->removeOption();
        return $result;
    }

    /**
     * 支付商城订单
     * @param $order_info
     * @param $pay_type
     * @return int|string
     */
    public function setOrderPaid($order_info,$pay_type,$transaction_id=''){
        $now = time();
        $arr_order_update = [
            'order_status'=>OrderE::ORDER_STATUS['TAKE'],
            'pay_status'=>OrderE::PAY_STATUS['YES'],
            'transaction_id'=>$transaction_id,
            'pay_time'=>$now,
            'pay_type'=>$pay_type,
        ];
        return Db::name(OrderE::SHORT_TABLE_NAME)->where('order_sn','=',$order_info['order_sn'])->update($arr_order_update);
    }


    public function setCashOrderPaid($order_info,$pay_type,$transaction_id=''){
        $now = time();
        $arr_order_update = [
            'order_status'=>CashOrderE::ORDER_STATUS['PAID'],
            'status'=>CashOrderE::STATUS['paid'],
            'transaction_id'=>$transaction_id,
            'pay_time'=>$now,
            'pay_type'=>$pay_type,
        ];
        return Db::name(CashOrderE::SHORT_TABLE_NAME)->where('order_sn','=',$order_info['order_sn'])->update($arr_order_update);
    }

    /**
     * 增加用户支付记录
     * @param $order_info
     * @param $user_info
     * @return int|string
     */
    public function addMemberCashLog($order_info,$user_info){
        $arr = [
            'user_id'=>$user_info['user_id'],
            'type'=>MemberCashLogE::TYPE['member_consume'],
            'way'=>MemberCashLogE::WAY['out'],
            'tip'=>MemberCashLogE::TIP['member_consume'],
            'cash'=>$order_info['order_amount'],
            'order_no'=>$order_info['order_sn'],
            'status'=>MemberCashLogE::STATUS['done'],
            'update_time'=>time(),
        ];
        return Db::name(MemberCashLogE::SHORT_TABLE_NAME)->insert($arr);
    }

    /**
     * 增加用户充值记录
     * @param $order_info
     * @param $user_info
     * @return int|string
     */
    public function addMemberChargeCashLog($order_info,$user_info){
        $arr = [
            'user_id'=>$user_info['user_id'],
            'type'=>MemberCashLogE::TYPE['member_charge'],
            'way'=>MemberCashLogE::WAY['in'],
            'tip'=>MemberCashLogE::TIP['member_charge'],
            'cash'=>$order_info['total_num'],
            'order_no'=>$order_info['order_sn'],
            'status'=>MemberCashLogE::STATUS['done'],
            'update_time'=>time(),
        ];
        return Db::name(MemberCashLogE::SHORT_TABLE_NAME)->insert($arr);
    }

    /**
     * 扣减商品库存
     * @param $order_info
     */
    public function deductOrderGoodsStock($order_info){
        $order_goods_info = Db::name(OrderGoodsE::SHORT_TABLE_NAME)->where('order_id','=',$order_info['id'])->select();
        if(count($order_goods_info)>0){
            array_map(function($order_good){
                Db::name(GoodsE::SHORT_TABLE_NAME)->where('id','=',$order_good['goods_id'])->setDec('store_count',$order_good['goods_num']);
            },$order_goods_info);
        }
    }

    /**
     * 给商家增加收钱记录
     * @param $order_info
     * @param $store_sub_info
     */
    public function addMerchCashLog($order_info,$store_sub_info){
        $per = $store_sub_info['withdraw_percent']/100;
        $arr = [
            'store_id'=>$store_sub_info['store_id'],
            'type'=>MerchCashLogE::TYPE['merch_order'],
            'way'=>MerchCashLogE::WAY['in'],
            'tip'=>MerchCashLogE::TIP['merch_order'],
            'cash'=>$per * $order_info['order_amount'],
            'order_no'=>$order_info['order_sn'],
            'status'=>MerchCashLogE::STATUS['frozening'],
            'update_time'=>time(),
        ];
        return Db::name(MerchCashLogE::SHORT_TABLE_NAME)->insert($arr);
    }

    public function changeOrderStatus($order_info,$order_status){
        $arr_order_update = [
            'order_status'=>$order_status,
        ];
        switch($order_status){
            case OrderE::ORDER_STATUS['DONE_BACK']:
                $arr_order_update['cancel_time'] = time();
                break;
        }
        Db::name(OrderE::SHORT_TABLE_NAME)->where('order_sn','=',$order_info['order_sn'])->update($arr_order_update);
    }

    public function addCashOrder($data){
        return  Db::name('cash_order')->insertGetId($data);
    }

    public function getCashOrderBySn($order_sn){
        $orderDb = Db::name(CashOrderE::SHORT_TABLE_NAME);
        $orderDb->where('order_sn','=',$order_sn);
        $result = $orderDb->find();
        $orderDb->removeOption();
        return $result;
    }

    public function getMemberCashLog($user_id){
        $memberCashLogDb = Db::name(MemberCashLogE::SHORT_TABLE_NAME);
        $result = $memberCashLogDb->where('user_id','=',$user_id)
            ->where('method','=',MemberCashLogE::METHOD['cash'])
            ->where('status','=',MemberCashLogE::STATUS['done'])
            ->select();
        return $result;
    }

}
