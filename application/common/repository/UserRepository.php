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

use think\Db;
use think\Model;
use app\common\entity\User;


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


}
