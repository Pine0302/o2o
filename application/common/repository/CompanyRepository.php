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

use app\common\entity\MerchCashLogE;
use app\common\entity\RiderCompany;
use app\common\entity\SellerSub;
use app\common\library\OrderHandle;
use think\Db;
use think\Model;
use app\common\entity\User;
use app\common\entity\RiderCompanyBind;


class CompanyRepository
{
    public function companyList(){
        $companyDb = Db::name(RiderCompany::SHORT_TABLE_NAME);
        return $companyDb->select();
    }

    public function addCompany($data){
        $companyDb = Db::name(RiderCompany::SHORT_TABLE_NAME);
        return $companyDb->insertGetId($data);
    }

    public function updateCompany($data,$map){
        $companyDb = Db::name(RiderCompany::SHORT_TABLE_NAME);
        return $companyDb->where($map)->update($data);
    }

    public function getCompanyById($id){
        $companyDb = Db::name(RiderCompany::SHORT_TABLE_NAME);
        return $companyDb->where('id','=',$id)->find();
    }

    public function getRiderCountByCondition($condition){
        $companyRiderDb = Db::name(RiderCompanyBind::SHORT_TABLE_NAME);
        return $companyRiderDb->where($condition)->count();
    }

    public function getCompanyByName($company_name){
        $companyDb = Db::name(RiderCompany::SHORT_TABLE_NAME);
        return $companyDb->where('name','=',$company_name)->find();
    }

    /**
     * 获取绑定的骑手列表
     * @param $condition
     * @param $offset
     * @param $page_size
     * @return int|string
     */
    public function getRiderListByCondition($condition,$offset,$length){
        $companyRiderDb = Db::name(RiderCompanyBind::SHORT_TABLE_NAME);
        $companyRiderDb->alias('rcb');
        if(!empty($condition['rider_id'])){
            $companyRiderDb->where('rcb.rider_id','=',$condition['rider_id']);
        }
        if(!empty($condition['company_id'])){
            $companyRiderDb->where('rcb.company_id','=',$condition['company_id']);
        }
        $companyRiderDb->join(User::TABLE_NAME.' u ','u.user_id = rcb.rider_id','inner');
        $companyRiderDb->join(RiderCompany::TABLE_NAME.' rc ','rc.id = rcb.company_id','inner');
        return $companyRiderDb->field('u.*,rc.name as company_name')->limit($offset,$length)->select();
    }


    /**
     * 通过mobile集合过滤掉不属于某个公司的骑手
     * @param $company_id
     * @param $arr
     */
    public function setRidersNotBelongToCompanyByMobile($company_id,$arr){
        $companyRiderDb = Db::name(RiderCompanyBind::SHORT_TABLE_NAME);
        return $companyRiderDb
            ->where('mobile','not in',$arr)
            ->where('company_id','=',$company_id)
            ->update(['status'=>2]);
    }

    /*
     * @param $company_id
     */
    public function getValidRiderListByCompanyId($company_id){
        $companyRiderDb = Db::name(RiderCompanyBind::SHORT_TABLE_NAME);
        return $companyRiderDb
            ->where('company_id','=',$company_id)
            ->where('status','=',1)
            ->select();
    }

    public function setMobilebelongToCompany($mobile,$company_id,$user_info){
        //在user表找该用户
        $user_id = isset($user_info['user_id']) ? intval($user_info['user_id']) : 0 ;
        print_r($user_id);
        $arr = [
            'rider_id'=>$user_id,
            'mobile'=>$mobile,
            'company_id'=>$company_id,
            'status'=>RiderCompanyBind::STATUS['VALID'],
            'create_time'=>time(),
            'update_time'=>time(),
        ];
        $companyRiderDb = Db::name(RiderCompanyBind::SHORT_TABLE_NAME);
        return $companyRiderDb->insert($arr);
    }

}
