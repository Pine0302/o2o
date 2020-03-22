<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\wx\WXBizDataCrypt;
use fast\AreaInclude;
use fast\Http;
use think\cache\driver\Redis;
use think\Db;
use think\Session;
use think\Cache;
use app\api\controller\Common;
use app\api\library\NoticeHandle;
use fast\Algor;
/**
 * 工作相关接口
 */
class Address extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    //  protected $noNeedLogin = ['test1","login'];
    protected $noNeedLogin = ['*'];
    // 无需鉴权的接口,*表示全部
//    /protected $noNeedRight = ['test2'];
    protected $noNeedRight = ['*'];




    //修改/更新地址
    public function addEditUserAddress(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'];
        $address_id = isset($data['address_id']) ? $data['address_id']:'' ;
        $is_default = isset($data['is_default']) ? $data['is_default']:2 ;
        $name= $data['name'];
        $mobile = $data['mobile'];
        $lat = $data['lat'];
        $lng = $data['lng'];
        $address = $data['address'];
        $address_num = $data['address_num'];
        $label = $data['label'];
        $gender = $data['gender'];
        $prov_name = isset($data['prov_name']) ? $data['prov_name']:'' ;
        $city_name = isset($data['city_name']) ? $data['city_name']:'' ;
        $area_name = isset($data['area_name']) ? $data['area_name']:'' ;

        $user_info = $this->getGUserInfo($sess_key);
        $arr_add = [
            'user_id'=>$user_info['user_id'],
            'consignee'=>$name,
            'prov_name'=>$prov_name,
            'city_name'=>$city_name,
            'area_name'=>$area_name,
            'address'=>$address,
            'address_num'=>$address_num,
            'mobile'=>$mobile,
            'is_default'=>$is_default,
            'longitude'=>$lng,
            'latitude'=>$lat,
            'label'=>$label,
            'gender'=>$gender,
        ];
        if($is_default==1){
            Db::name('user_address')->where('user_id','=',$user_info['user_id'])->update(['is_default'=>2]);
        }
        if(!empty($address_id)){    //修改地址
            Db::name('user_address')->where('address_id','=',$address_id)->update($arr_add);
        }else{                      //新增地址
            $address_id = Db::name('user_address')->insertGetId($arr_add);
        }
        $this->success('success');
    }

    //删除地址
    public function delAddress(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'];
        $user_info = $this->getGUserInfo($sess_key);
        $address_id = $data['address_id'];
        Db::name('user_address')->where('address_id','=',$address_id)->delete();
        $this->success('success');
    }


    //用户地址列表
    public function addressList(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'];
        $user_info = $this->getGUserInfo($sess_key);
        $address_list = Db::name('user_address')->where('user_id','=',$user_info['user_id'])->select();
        $response_arr = [];
        foreach($address_list as $ka=>$va){
            $response_arr[] = [
                'address_id'=>$va['address_id'],
                'address_num'=>$va['address_num'],
                'label'=>$va['label'],
                'address'=>$va['address'],
                'mobile'=>$va['mobile'],
                'name'=>$va['consignee'],
                'gender'=>$va['gender'],
                'is_default'=>$va['is_default'],
                'lat'=>$va['latitude'],
                'lng'=>$va['longitude'],
            ];
        }
        $data = [
            'data'=>$response_arr,
        ];
        $this->success('success', $data);

    }

    //用户地址列表
    public function defaultAddress(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'];
        $user_info = $this->getGUserInfo($sess_key);
        $address_list = Db::name('user_address')
            ->where('user_id','=',$user_info['user_id'])
            ->where('is_default','=',1)
            ->find();
        $response_arr = [];
        if(!empty($address_list)){
            $va = $address_list;
            $response_arr = [
                'address_id'=>$va['address_id'],
                'address'=>$va['address'],
                'address_num'=>$va['address_num'],
                'label'=>$va['label'],
                'mobile'=>$va['mobile'],
                'name'=>$va['consignee'],
                'gender'=>$va['gender'],
                'is_default'=>$va['is_default'],
                'lat'=>$va['latitude'],
                'lng'=>$va['longitude'],
            ];
        }else{
            $response_arr = null;
        }
        $data = [
            'data'=>$response_arr,
        ];
        $this->success('success', $data);

    }


    //用户地址列表
    public function mobileList(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'];
        $user_info = $this->getGUserInfo($sess_key);
        $address_list = Db::name('user_address')->where('user_id','=',$user_info['user_id'])->select();
        $response_arr = [];
        if(!empty($address_list)){
            foreach($address_list as $ka=>$va){
                $response_arr[] =   $va['mobile'];

            }
        }
        $data = [
            'data'=>$response_arr,
        ];
        $this->success('success', $data);

    }






}
