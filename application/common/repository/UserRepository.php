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

use app\common\entity\RiderCompany;
use app\common\entity\SellerSub;
use think\Db;
use think\Model;
use app\common\entity\User;
use app\common\entity\RiderCompanyBind;


class UserRepository
{

    public function getUserByOpenid($openid){

    }

    public function getUserByMobile($mobile){

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
        $riderCompanyBindDb->field('rcb.mobile,rc.name as company_name');
        $riderCompanyBindDb->join(RiderCompany::TABLE_NAME." rc ",'rc.id = rcb.company_id');
        $riderCompanyBindDb->where("rcb.mobile",'=',$mobile);
        $riderCompanyBindDb->where("rcb.status","=",1);
        $result = $riderCompanyBindDb->find();
        $riderCompanyBindDb->removeOption();
        return $result;
    }

    /**
     * 给用户扣钱
     * @param $amount
     * @param $user_id
     */
    public function deductMoney($amount,$user_id){
        $userDb = Db::name(User::SHORT_TABLE_NAME);
        $map = ['user_id'=>$user_id];
        $userDb->where($map)->setDec('user_money',$amount);
    }


    /**
     * 给商家发钱
     * @param $order_info
     */
    public function raiseMerchMoney($order_info,$store_sub_info){
        $per = $store_sub_info['withdraw_percent']/100;
        $sellerSubDb = Db::name(SellerSub::SHORT_TABLE_NAME);
        $map = ['store_id'=>$order_info['store_id']];
        return $sellerSubDb->where($map)->inc('frozen_money',$per*$order_info['order_amount'])->inc('total_money',$order_info['order_amount'])->update();
    }




}
