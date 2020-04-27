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

}
