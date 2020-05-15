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

use app\common\entity\MemberCashLogE;
use app\common\entity\MerchCashLogE;
use app\common\entity\RiderCompany;
use app\common\entity\RiderCompanyCharge;
use app\common\entity\SellerSub;
use app\common\library\OrderHandle;
use think\Db;
use think\Model;
use app\common\entity\User;
use app\common\entity\RiderCompanyBind;


class UserRepository
{

    public function getUserByOpenid($openid){

    }

    public function getUserByMobile($mobile){
        $userDb = Db::name(User::SHORT_TABLE_NAME);
        $userDb->where('mobile','=',$mobile);
        $result = $userDb->find();
        return $result;
    }

    public function getUserById($id){
        $userDb = Db::name(User::SHORT_TABLE_NAME);
        $userDb->where('user_id','=',$id);
        $result = $userDb->find();
        return $result;
    }
    public function updateUserByFilter($data,$map){
        $userDb = Db::name(User::SHORT_TABLE_NAME);
        $userDb->where($map)->update($data);
        $userDb->removeOption();
    }

    public function validMobile($openid,$mobile){
        $userDb = Db::name(User::SHORT_TABLE_NAME);
        $userDb->where('mobile','=',$mobile);
        $userDb->where('openid','neq',$openid);
        $result = $userDb->find();
        $userDb->removeOption();
        return $result;
    }

    //检测骑手和公司的认证
    public function validRiderCompany($mobile){
        $riderCompanyBindDb = Db::name(RiderCompanyBind::SHORT_TABLE_NAME);
        $riderCompanyBindDb->alias('rcb');
        $riderCompanyBindDb->field('rcb.mobile,rc.name as company_name,rcb.total_money,rcb.remain_money,rcb.company_id');
        $riderCompanyBindDb->join(RiderCompany::TABLE_NAME." rc ",'rc.id = rcb.company_id');
        $riderCompanyBindDb->where("rcb.mobile",'=',$mobile);
        $riderCompanyBindDb->where("rcb.status","=",1);
        $result = $riderCompanyBindDb->find();

        $riderCompanyBindDb->removeOption();
        return $result;
    }

    /**
     * * 给用户扣钱
     * @param $amount
     * @param $user_id
     * @throws \think\Exception
     */
    public function deductMoney($amount,$user_id){
        $userDb = Db::name(User::SHORT_TABLE_NAME);
        $map = ['user_id'=>$user_id];
        $userDb->where($map)->setDec('user_money',$amount);
    }

    /**
     * 给用户发钱
     * @param $amount
     * @param $user_id
     * @throws \think\Exception
     */
    public function incUserMoney($amount, $user_id){
        $userDb = Db::name(User::SHORT_TABLE_NAME);
        $map = ['user_id'=>$user_id];
        $userDb->where($map)->setInc('user_money',$amount);
    }


    /**
     * * 给商家发钱
     * @param $order_info
     * @param $store_sub_info
     * @return int|string
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function raiseMerchMoney($order_info,$store_sub_info){
        $per = $store_sub_info['withdraw_percent']/100;
        $orderhandleObj = new OrderHandle();
        $real_money = $orderhandleObj ->calcShowCash($per*$order_info['order_amount']);
        $sellerSubDb = Db::name(SellerSub::SHORT_TABLE_NAME);
        $map = ['store_id'=>$order_info['store_id']];
        return $sellerSubDb->where($map)
            ->inc('frozen_money',$real_money)
            ->inc('total_real_money',$real_money)
            ->inc('total_money',$order_info['order_amount'])
            ->update();
    }

    /**
     * * 商家给用户退款
     * @param $order_info
     * @param $store_sub_info
     * @return int|string
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function retreatMerchMoney($order_info){
        $store_sub_info = Db::name('store_sub')->where('store_id','=',$order_info['store_id'])->find();
        $per = $store_sub_info['withdraw_percent']/100;
        $orderHandelObj = new OrderHandle();
        $real_money = $orderHandelObj->calcShowCash($per*$order_info['order_amount']);
        $sellerSubDb = Db::name(SellerSub::SHORT_TABLE_NAME);
        $map = ['store_id'=>$order_info['store_id']];
        return $sellerSubDb->where($map)
            ->dec('frozen_money',$real_money)
            ->dec('total_real_money',$real_money)
            ->dec('total_money',$order_info['order_amount'])->update();
    }

    /**
     * 增加商家退款记录
     * @param $user_info
     * @param $cash
     * @return int|string
     */
    public function addMerchReateatLog($order_info,$store_info){
        $real_amount = $order_info['order_amount'] * $store_info['withdraw_percent']/100;
        $orderHandleObj = new OrderHandle();
        $cash_real = $orderHandleObj->calcShowCash($real_amount);
        $order_no= $order_info['order_sn'];
        $arr = [
            'store_id'=>$order_info['store_id'],
            'type'=>MerchCashLogE::TYPE['merch_retreat'],
            'way'=>MerchCashLogE::WAY['out'],
            'tip'=>MerchCashLogE::TIP['merch_retreat'],
            'cash'=>$cash_real,
            'ori_cash'=>$order_info['order_amount'],
            'order_no'=>$order_no,
            'status'=>MerchCashLogE::STATUS['merch_retreat'],
            'update_time'=>time(),
            'create_time'=>$order_info['pay_time'],
        ];
        return Db::name(MerchCashLogE::SHORT_TABLE_NAME)->insert($arr);
    }


    public function raiseUserMoney($order_info,$user_info){
        $amount = $order_info['total_num'];
        $userDb = Db::name(User::SHORT_TABLE_NAME);
        return $userDb->where('user_id','=',$user_info['user_id'])->setInc('user_money',$amount);
    }
    
    /**
     * * 商家提现的资金变动
     * @param $user_info
     * @param $cash
     * @return int|string
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function withMerchMoney($user_info,$cash){
        $sellerSubDb = Db::name(SellerSub::SHORT_TABLE_NAME);
        $sellerSubDb->removeOption();
        return $sellerSubDb->where(['store_id'=>$user_info['store_id']])
            ->inc('withdrawing_money',$cash)
            ->dec('merch_money',$cash)
            ->dec('total_real_money',$cash)
            ->update();
    }

    /**
     * * 通过用户获取商家金额
     * @param $user_info
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getMerchMoneyByUser($user_info){
        $sellerSubDb = Db::name(SellerSub::SHORT_TABLE_NAME);
        $map = ['store_id'=>$user_info['store_id']];
        return $sellerSubDb->where($map)->find();


    }

    public function addMerchWithdrawLog($user_info,$cash){
        $orderHandleObj = new OrderHandle();
        $order_no= $orderHandleObj->createOrder("wit");
        $arr = [
            'store_id'=>$user_info['store_id'],
            'type'=>MerchCashLogE::TYPE['merch_withdraw'],
            'way'=>MerchCashLogE::WAY['out'],
            'tip'=>MerchCashLogE::TIP['merch_withdraw'],
            'cash'=>$cash,
            'order_no'=>$order_no,
            'status'=>MerchCashLogE::STATUS['withdraw'],
            'update_time'=>time(),
            'create_time'=>time(),
        ];
        return Db::name(MerchCashLogE::SHORT_TABLE_NAME)->insert($arr);
    }


    /**
     * 获取商家信息
     * @param $store_id
     * @return array
     */
    public function getSellerinfo($store_id){
        $sellerSubDb = Db::name(SellerSub::SHORT_TABLE_NAME);
        $map = ['store_id'=>$store_id];
        return $sellerSubDb->where($map)->find();
    }

    public function updateUserMoneyByMobile($mobile,$money,$company_id){
        $checkRirder = $this->getUserByMobile($mobile);
        if(!empty($checkRirder)){
            $userDb = Db::name(User::SHORT_TABLE_NAME);
            $userDb->where('user_id','=',$checkRirder['user_id'])->setInc('user_money',$money);

            $arr = [
                'mobile'=>$mobile,
                'company_id'=>$company_id,
                'status'=>RiderCompanyCharge::STATUS['done'],
                'create_time'=>time(),
                'update_time'=>time(),
                'money'=>$money,
                'rider_id'=>$checkRirder['user_id'],
            ];
            Db::name('rider_company_charge')->insert($arr);
            Db::name('rider_company_bind')->where('mobile','=',$mobile)->inc('total_money',$money)->update();
            //增加加钱记录
            $arr = [
                'user_id'=>$checkRirder['user_id'],
                'type'=>MemberCashLogE::TYPE['company_charge_member'],
                'way'=>MemberCashLogE::WAY['in'],
                'tip'=>MemberCashLogE::TIP['company_charge_member'],
                'cash'=>$money,
                'order_no'=>'CHA_'.$checkRirder['user_id'].time(),
                'status'=>MemberCashLogE::STATUS['done'],
                'update_time'=>time(),
            ];
            return Db::name(MemberCashLogE::SHORT_TABLE_NAME)->insert($arr);



        }else{
            $arr = [
                'mobile'=>$mobile,
                'company_id'=>$company_id,
                'status'=>RiderCompanyCharge::STATUS['undone'],
                'create_time'=>time(),
                'update_time'=>time(),
                'money'=>$money,
            ];
            Db::name('rider_company_charge')->insert($arr);
            Db::name('rider_company_bind')->where('mobile','=',$mobile)->inc('total_money',$money)->inc('remain_money',$money)->update();
        }
    }


     public function getRiderChargeList($mobile,$company_id){
         $list = Db::name('rider_company_charge')
            ->where('mobile','=',$mobile)
            ->where('company_id','=',$company_id)
            ->where('status','=',2)
            ->select();
        return $list;
     }

    public function chargeRiderWhileValid($charge,$user_id){
        //给用户加钱
        $this->incUserMoney($charge['money'],$user_id);
        //给用户增加收益记录
        $arr = [
            'user_id'=>$user_id,
            'type'=>MemberCashLogE::TYPE['company_charge_member'],
            'way'=>MemberCashLogE::WAY['in'],
            'tip'=>MemberCashLogE::TIP['company_charge_member'],
            'cash'=>$charge['money'],
            'order_no'=>'CHA_'.$user_id.time(),
            'status'=>MemberCashLogE::STATUS['done'],
            'update_time'=>time(),
        ];
         Db::name(MemberCashLogE::SHORT_TABLE_NAME)->insert($arr);
        //减少骑手-商家绑定表中的余额
        $result1 = Db::name('rider_company_bind')
            ->where('company_id','=',$charge['company_id'])
            ->where('mobile','=',$charge['mobile'])
            ->dec('remain_money',$charge['money'])
            ->update();

        //将rider_company_charge表状态设为已支付
        $result2 = Db::name('rider_company_charge')
            ->where('id','=',$charge['id'])
            ->update(['status'=>RiderCompanyCharge::STATUS['done'],'update_time'=>time(),'rider_id'=>$user_id]);
    }


    /**
     * @param $merch_cash_log
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function updateMerchMoneyToAvailable($merch_cash_log){
        $sellerSubDb = Db::name(SellerSub::SHORT_TABLE_NAME);
        $map = ['store_id'=>$merch_cash_log['store_id']];
        $sellerSubDb->where($map)
            ->dec('frozen_money',$merch_cash_log['cash'])
            ->inc('merch_money',$merch_cash_log['cash'])
            ->update();

        $arr = ['status'=>MerchCashLogE::STATUS['done'],'update_time'=>time()];
        $map = ['id'=>$merch_cash_log['id']];
        Db::name('merch_cash_log')->where($map)->update($arr);

    }

}
