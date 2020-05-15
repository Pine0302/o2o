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

use app\api\controller\Merch;
use app\common\entity\CashOrderE;
use app\common\entity\GoodsE;
use app\common\entity\MemberCashLogE;
use app\common\entity\MerchCashLogE;
use app\common\entity\OrderE;
use app\common\entity\OrderGoodsE;
use app\common\entity\RiderCompany;
use app\common\library\OrderHandle;
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
    public function setOrderPaid($order_info,$pay_type,$transaction_id='',$order_num=''){
        $now = time();
        $arr_order_update = [
            'order_status'=>OrderE::ORDER_STATUS['TAKE'],
            'pay_status'=>OrderE::PAY_STATUS['YES'],
            'transaction_id'=>$transaction_id,
            'pay_time'=>$now,
            'order_num'=>$order_num,
            'pay_type'=>$pay_type,
        ];
        return Db::name(OrderE::SHORT_TABLE_NAME)->where('order_sn','=',$order_info['order_sn'])->update($arr_order_update);
    }


    public function setCashOrderPaid($order_info,$pay_type,$transaction_id=''){
        $now = time();
        $arr_order_update = [
            //'order_status'=>CashOrderE::ORDER_STATUS['PAID'],
            'status'=>CashOrderE::STATUS['paid'],
            'transaction_id'=>$transaction_id,
            'pay_time'=>$now,
            'pay_type'=>$pay_type,
        ];
        error_log("afterpayCRE--arr_order_update-".json_encode($arr_order_update),3,"/opt/app-root/src/public/log/test.txt");
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
        $orderHandleObj = new OrderHandle();
        $cash = $orderHandleObj->calcShowCash($order_info['order_amount']*$per);

        $arr = [
            'store_id'=>$store_sub_info['store_id'],
            'type'=>MerchCashLogE::TYPE['merch_order'],
            'way'=>MerchCashLogE::WAY['in'],
            'tip'=>MerchCashLogE::TIP['merch_order'],
            'cash'=>$cash,
            'ori_cash'=>$order_info['order_amount'],
            'order_no'=>$order_info['order_sn'],
            'status'=>MerchCashLogE::STATUS['frozening'],
            'update_time'=>time(),
            'create_time'=>time(),
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
            case OrderE::ORDER_STATUS['TAKE']:
                $arr_order_update['cook_time'] = time();
                break;
            case OrderE::ORDER_STATUS['UNDONE_BACK']:
                $arr_order_update['finish_time'] = time();
                break;
            case OrderE::ORDER_STATUS['DONE']:
                $arr_order_update['finish_time'] = time();
                break;
        }
        Db::name(OrderE::SHORT_TABLE_NAME)->where('order_sn','=',$order_info['order_sn'])->update($arr_order_update);
    }

    /**
     * 商家同意退单之后给骑手退钱
     * @param $order_detail
     */
    public function retreatUserMoney($order_detail){
        $user_id = $order_detail['user_id'];
        $cash = $order_detail['order_amount'];
        Db::startTrans();
        try {
            //给用户加钱
            $userRepository = new UserRepository();
            $userRepository->incUserMoney($cash,$user_id);
            //给用户增加加钱记录
            $arr = [
                'user_id'=>$user_id,
                'type'=>MemberCashLogE::TYPE['order_retreat'],
                'way'=>MemberCashLogE::WAY['in'],
                'tip'=>MemberCashLogE::TIP['order_retreat'],
                'cash'=>$cash,
                'order_no'=>$order_detail['order_sn'],
                'status'=>MemberCashLogE::STATUS['done'],
                'update_time'=>time(),
                'method'=>MemberCashLogE::METHOD['cash']
            ];
            $result = Db::name(MemberCashLogE::SHORT_TABLE_NAME)->insert($arr);
            //减少商家的销售额
            //$result = Db::name('seller_sub')->where('store_id','=',$order_detail['store_id'])->setDec('total_money',$cash);
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
        }
        return $result;
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
          //  ->where('method','=',MemberCashLogE::METHOD['cash'])
            ->where('status','=',MemberCashLogE::STATUS['done'])
            ->order(['id'=>'desc'])
            ->select();
        return $result;
    }

    public function getTotalPaidMoneyByTime($store_id,$start_time,$end_time){
        $orderDb = Db::name(OrderE::SHORT_TABLE_NAME);
        $result = $orderDb
            ->where('store_id','=',$store_id)
            ->where('pay_time',['>',$start_time],['<',$end_time],'and')
            ->where('order_status','neq',OrderE::ORDER_STATUS['DONE_BACK'])
            ->sum('order_amount');
        return $result;
    }

    public function getTotalOrderByTime($store_id,$start_time,$end_time){
        return Db::name(OrderE::SHORT_TABLE_NAME)
            ->where('store_id','=',$store_id)
            ->where('pay_time',['>',$start_time],['<',$end_time],'and')
            ->count('*');
    }

    public function getMerchCashLogByTime($store_id,$start_time,$end_time){
        $merchCashLogDb = Db::name(MerchCashLogE::SHORT_TABLE_NAME);
        $result = $merchCashLogDb
            ->where('store_id','=',$store_id)
            ->where('type','neq',MerchCashLogE::TYPE['merch_withdraw'])
            ->where('update_time',['>',$start_time],['<',$end_time],'and')
            ->order(['id'=>'desc'])
            ->select();
        return $result;
    }

    public function getMerchOrderListFilter($store_id,$start_time,$end_time,$status,$search_data){
        $orderDb = Db::name(OrderE::SHORT_TABLE_NAME);
        $orderDb->where('store_id','=',$store_id);
        $orderDb->where('pay_time',['>',$start_time],['<',$end_time],'and');
        if($status!="-1"){
            $orderDb->where('order_status','=',$status);
        }
        if(!empty($search_data)){
            $orderDb->where(function($query) use ($search_data){
                $query->where('mobile','=',$search_data)->whereOr('order_sn','=',$search_data)->whereOr('consignee','=',$search_data);
            });
        }
        $orderDb->order(['order_id'=>'desc']);
        $result = $orderDb->select();
        //print_r($orderDb->getLastSql());exit;
        return $result;
    }

    public function getMerchWithdrawCashLogByTime($store_id,$start_time,$end_time){
        $merchCashLogDb = Db::name(MerchCashLogE::SHORT_TABLE_NAME);
        $result = $merchCashLogDb
            ->where('store_id','=',$store_id)
            ->where('type','=',MerchCashLogE::TYPE['merch_withdraw'])
            ->where('update_time',['>',$start_time],['<',$end_time],'and')
            ->order(['id'=>'desc'])
            ->select();
        return $result;
    }



}
