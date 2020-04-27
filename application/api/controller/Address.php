<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\wx\WXBizDataCrypt;
use fast\AreaInclude;
use fast\Http;
use Symfony\Component\Yaml\Tests\A;
use think\cache\driver\Redis;
use think\Db;
use think\Session;
use think\Cache;
use app\api\controller\Common;
use app\api\library\NoticeHandle;
use fast\Algor;
use fast\Arg;
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



    //获取用户定位
    public function getUserPosition(){

        $data = $this->request->post();
        $openid = $this->analysisUserJwtToken();
        $user_info = $this->getGUserInfo($openid);
        $latitude = $data['lat'];
        $longitude = $data['lng'];

        $lbs_qq_key = config('Lbs.QQ_KEY');
        $location = $latitude.",".$longitude;

        $url = "http://apis.map.qq.com/ws/geocoder/v1/?location={$location}&key={$lbs_qq_key}&get_poi=1&page_size=5&page_index=1&policy=1";

        $http = new Http();
        $result_user_position = $http->get($url);
        $result_user_position_arr = json_decode($result_user_position,true);

        $my_village = $this->getMyVillageFromPositionInfo($result_user_position_arr['result']);
        $location_areano = substr($result_user_position_arr['result']['ad_info']['city_code'],3);
        $location_area_name = $result_user_position_arr['result']['ad_info']['city'];
        $location_district_name = $result_user_position_arr['result']['ad_info']['district'];
        $relative_pois = $result_user_position_arr['result']['pois'];
        $relative_pois = array_map(function($pos){
            $arr = [
                'location' => $pos['location'],
                'title' => $pos['title'],
                'address' => $pos['address'],
            ];
            return $arr;
        },$relative_pois);
        $data = [
            'my_village'=>$my_village,
            'location_areano'=>$location_areano,
            'location_area_name'=>$location_area_name,
            'location_district_name'=>$location_district_name,
            'relative_pois'=>$relative_pois,
        ];
        $response = [
            'data'=>$data
        ];
        $this->success('success',$response);

        //验证密码

    }

    public function getMyVillageFromPositionInfo($result_user_position_arr){
        $position = $result_user_position_arr['formatted_addresses']['recommend'];
        if(!empty($result_user_position_arr['address_reference']['landmark_l2']['title'])){
            $position = $result_user_position_arr['address_reference']['landmark_l2']['title'];
        }
        if($result_user_position_arr['poi_count']>0){
            $position = $result_user_position_arr['pois'][0]['title'];
        }
        return $position;
    }


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



    //获取用户定位
    public function searchPosition(){

        $data = $this->request->post();
        $openid = $this->analysisUserJwtToken();
        $user_info = $this->getGUserInfo($openid);
        $latitude = $data['lat'];
        $longitude = $data['lng'];
        $search_name = $data['search_name'];

        $lbs_qq_key = config('Lbs.QQ_KEY');
     //   $location = $latitude.",".$longitude;
        $address = urlencode('杭州市'.$search_name);
        $url = "https://apis.map.qq.com/ws/geocoder/v1/?address={$address}&key={$lbs_qq_key}&region=杭州";
    //    print_r($url);exit;

        $http = new Http();
        $result_user_position = $http->get($url);
        $result_user_position_arr = json_decode($result_user_position,true);
        $location = [];
        $title = [];
        if(!empty($result_user_position_arr)){
            $location = $result_user_position_arr['result']['location'];
            $title = $result_user_position_arr['result']['title'];
        }
        $data = [
            'location'=>$location,
            'title'=>$title,
        ];
        $response = [
            'data'=>$data
        ];
        $this->success('success',$response);

        //验证密码

    }


    //获取用户定位
    public function searchGDPosition(){

        $data = $this->request->post();
        $openid = $this->analysisUserJwtToken();
        $user_info = $this->getGUserInfo($openid);
        $latitude = $data['latitude'];
        $longitude = $data['longitude'];
        $search_name = $data['search_name'];

        $lbs_gd_key = config('Lbs.GD_KEY');
        //   $location = $latitude.",".$longitude;
        $address = urlencode($search_name);
        $url = "https://restapi.amap.com/v3/place/text?key={$lbs_gd_key}&keywords={$address}&types=&city=杭州&children=1&offset=10&page=1&extensions=base";
         //print_r($url);exit;

        $http = new Http();
        $result_user_position = $http->get($url);
        $result_user_position_arr = json_decode($result_user_position,true);
        //print_r($result_user_position_arr['pois']);exit;
        $positions = [];
        $areaIncludeObj = new AreaInclude();
        if(!empty($result_user_position_arr['pois'])){
            $positions = array_map(function($poi) use ($latitude,$longitude,$areaIncludeObj){
                $poi_position = explode(",",$poi['location']);
                $distance = $areaIncludeObj->distance($latitude,$longitude,$poi_position[1],$poi_position[0]);
                $distance = number_format($distance/1000,2);
               return  [
                    'latitude'=>$poi_position[1],
                    'longitude'=>$poi_position[0],
                    'distance' => $distance,
                    'name' => $poi['name'],
                ];
            },$result_user_position_arr['pois']);
            $argObj = new Arg();
            $positions = $argObj::arraySort($positions,'distance','asc');
        }

        $data = [
            'position'=>$positions,
        ];
        $response = [
            'data'=>$data
        ];
        $this->success('success',$response);

        //验证密码

    }




}
