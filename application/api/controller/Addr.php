<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\wx\WXBizDataCrypt;
use fast\Http;
use think\cache\driver\Redis;
use think\Db;
use think\Session;
use think\Cache;

/**
 * 地区相关接口
 */
class Addr extends Api
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

    /**
     * 无需登录的接口
     *
     */
    public function test1()
    {
        var_dump(123);exit;
     //   $this->success('返回成功', ['action' => 'test1']);
    }

    /**
     * 需要登录的接口
     *
     */
    public function test2()
    {
        $this->success('返回成功', ['action' => 'test2']);
    }

    /**
     * 需要登录且需要验证有相应组的权限
     *
     */
    public function test3()
    {
        $this->success('返回成功', ['action' => 'test3']);
    }




    //筛选岗位的城市
    public function  disttrictList(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'] ?? '';
        $latitude = $data['latitude'];
        $longitude = $data['longitude'];
        if(!empty($sess_key)) {
            $this->validSessKey($sess_key);

            $arr = [  'openid', 'session_key' ];
            $sess_info = $this->redis->hmget($sess_key,$arr);
            $openid_re = $sess_info['openid'];
            $user_info = Db::table('user')
                ->where('openid_re',$openid_re)
                ->field('id,username,mobile,gender,birthday,available_balance')
                ->find();



            try {
                //通过经纬度获取所在城市
                $lbs_qq_key = config('Lbs.QQ_KEY');
                $location = $latitude.",".$longitude;
                $url = "http://apis.map.qq.com/ws/geocoder/v1/?location={$location}&key={$lbs_qq_key}&get_poi=1";

                $http = new Http();
                $result_user_position = $http->get($url);
                $result_user_position_arr = json_decode($result_user_position,true);
                //  $this->wlog($result_user_position_arr['result']['ad_info']);

                $location_areano = substr($result_user_position_arr['result']['ad_info']['city_code'],3);
                $location_area_name = $result_user_position_arr['result']['ad_info']['city'];
                $location_district_name = $result_user_position_arr['result']['ad_info']['district'];


                $arr = [
                    'prov_name' => $result_user_position_arr['result']['ad_info']['province'],
                    'city_name' => $result_user_position_arr['result']['ad_info']['city'],
                    'district_name' => $result_user_position_arr['result']['ad_info']['district'],
                    'district_code' => $result_user_position_arr['result']['ad_info']['adcode'],
                    'city_code' => $location_areano,
                ];
                Db::table('user')->where('id','=',$user_info['id'])->update($arr);

                $location_city = [
                    'areano' => $location_areano,
                    'area_name' => $location_area_name,
                    'district_name' => $location_district_name,
                    'district_no' =>  $result_user_position_arr['result']['ad_info']['adcode'],
                ];
                $city_codes = Db::table('re_job')
                    ->distinct(true)
                    ->field('city_code')
                    ->select();
                $prov_codes = Db::table('re_job')
                    ->distinct(true)
                    ->field('prov_code')
                    ->select();
                $district_codes = Db::table('re_job')
                    ->distinct(true)
                    ->field('district_code')
                    ->select();

                $city_codes_str = '';
                foreach($city_codes as $kc=>$vc){
                    $city_codes_str = $city_codes_str.$vc['city_code'].",";
                }
                $city_codes_str = substr($city_codes_str,0,strlen($city_codes_str)-1);


                $prov_codes_str = '';
                foreach($prov_codes as $kc=>$vc){
                    $prov_codes_str = $prov_codes_str.$vc['prov_code'].",";
                }
                $prov_codes_str = substr($prov_codes_str,0,strlen($prov_codes_str)-1);

                $district_codes_str = '';
                foreach($district_codes as $kc=>$vc){
                    $district_codes_str = $district_codes_str.$vc['district_code'].",";
                }
                $district_codes_str = substr($district_codes_str,0,strlen($district_codes_str)-1);


                $provs_list = Db::table('areas')
                    ->where('areano','in',$prov_codes_str)
                    ->field('areaname as area_name,areano')
                    ->select();

                $citys_list = Db::table('areas')
                    ->where('areano','in',$city_codes_str)
                    ->field('areaname as area_name,areano')
                    ->select();

                $districts_list = Db::table('areas')
                    ->where('areano','in',$district_codes_str)
                    ->field('areaname as area_name,areano')
                    ->select();

                $bizobj = [
                    'location_city'=>$location_city,
                    'recruit_pros'=>array_values($provs_list),
                    'recruit_citys'=>array_values($citys_list),
                    'recruit_district'=>array_values($districts_list),
                ];
                $data = [
                    'data'=>$bizobj,
                ];
                $this->success('success', $data);

            } catch (Exception $e) {
                $this->error('网络繁忙,请稍后再试');
            }
        }else{
            $this->error('缺少必要的参数',null,2);
        }
    }

    //筛选岗位的城市
    public function  cityList(){
        $data = $this->request->post();
        try {
            $city_codes = Db::table('re_job')
                ->distinct(true)
                ->field('city_code')
                ->select();
            $city_codes_str = '';
            foreach($city_codes as $kc=>$vc){
                $city_codes_str = $city_codes_str.$vc['city_code'].",";
            }
            $city_codes_str = substr($city_codes_str,0,strlen($city_codes_str)-1);

            $citys_list = Db::table('areas')
                ->where('areano','in',$city_codes_str)
                ->field('areaname as area_name,areano')
                ->select();
            $arr = [
                'area_name'=>'不限城市',
                'areano'=>'999999',
            ];
            $citys_list[] = $arr;
            $bizobj = [
                'recruit_citys'=>array_values($citys_list),
            ];
            $data = [
                'data'=>$bizobj,
            ];
            $this->success('success', $data);

        } catch (Exception $e) {
            $this->error('网络繁忙,请稍后再试');
        }
    }


    //默认省市区三级展示
    public function defaultDistrict(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'] ?? '';

        $latitude = $data['latitude'] ?? '30.752';
        $longitude = $data['longitude'] ?? '120.908';
        $prov_code = $data['prov_code'] ?? '';
        $city_code = $data['city_code'] ?? '';
        $district_code = $data['district_code'] ?? '';
        $this->wlog("defaultDistrict");
        $this->wlog($data);
        if(!empty($sess_key)) {
            $arr = [  'openid', 'session_key' ];
            $sess_info = $this->redis->hmget($sess_key,$arr);
            $openid_re = $sess_info['openid'];
            $user_info = Db::table('user')
                ->where('openid_re',$openid_re)
                ->field('id,username,mobile,gender,birthday,available_balance')
                ->find();



            if(empty($prov_code)&&empty($city_code)&&empty($district_code)){
                try {
                    //通过经纬度获取所在城市
                    $lbs_qq_key = config('Lbs.QQ_KEY');
                    $location = $latitude.",".$longitude;
                    $url = "http://apis.map.qq.com/ws/geocoder/v1/?location={$location}&key={$lbs_qq_key}&get_poi=1";

                    $http = new Http();
                    $result_user_position = $http->get($url);
                    $result_user_position_arr = json_decode($result_user_position,true);
                    //  $this->wlog($result_user_position_arr['result']['ad_info']);

                    $location_areano = substr($result_user_position_arr['result']['ad_info']['city_code'],3);
                    $location_area_name = $result_user_position_arr['result']['ad_info']['city'];
                    $location_district_name = $result_user_position_arr['result']['ad_info']['district'];

                    $prov_code = substr($location_areano,0,2)."0000";
                    $arr = [
                        'prov_name' => $result_user_position_arr['result']['ad_info']['province'],
                        'city_name' => $result_user_position_arr['result']['ad_info']['city'],
                        'district_name' => $result_user_position_arr['result']['ad_info']['district'],

                        'district_code' => $result_user_position_arr['result']['ad_info']['adcode'],
                        'city_code' => $location_areano,
                        'prov_code'=>$prov_code,
                    ];
                    Db::table('user')->where('id','=',$user_info['id'])->update($arr);
                    $default_area = [
                        'prov'=>[
                            'code'=>$prov_code,
                            'name'=>$result_user_position_arr['result']['ad_info']['province'],
                        ],
                        'city'=>[
                            'code'=>$location_areano,
                            'name'=>$result_user_position_arr['result']['ad_info']['city'],
                        ],
                        'district'=>[
                            'code'=>$result_user_position_arr['result']['ad_info']['adcode'],
                            'name'=>$result_user_position_arr['result']['ad_info']['district'],
                        ],
                    ];


                    $prov_codes = Db::table('re_job')
                        ->distinct(true)
                        ->field('prov_code')
                        ->select();
                    $prov_codes_str = '';
                    foreach($prov_codes as $kc=>$vc){
                        $prov_codes_str = $prov_codes_str.$vc['prov_code'].",";
                    }
                    $prov_codes_str = substr($prov_codes_str,0,strlen($prov_codes_str)-1);
                    $provs_list = Db::table('areas')
                        ->where('areano','in',$prov_codes_str)
                        ->field('areaname as name,areano as code')
                        ->select();

                    $code_list = Db::table('re_job')
                        ->distinct('district_code')
                        ->field('prov_code,city_code,district_code,case city_code when 330400 then 1 else 2 end as city_rank ')
                        ->order('city_rank asc')
                        ->select();
                    $c_codes = [] ;
                    $d_codes = [] ;
                    foreach($code_list as $kc=>$vc){
                        if($vc['prov_code']==$default_area['prov']['code']){
                            $c_codes[]  = $vc['city_code'];
                        }
                        if($vc['city_code']==$default_area['city']['code']){
                            $d_codes[]  = $vc['district_code'];
                        }
                    }

                    if(empty($c_codes)||(empty($d_codes))){
                        $default_area['prov']['code'] = 330000;
                        $default_area['city']['code'] = 330400;
                        foreach($code_list as $kc=>$vc) {
                            if ($vc['prov_code'] == $default_area['prov']['code']) {
                                $c_codes[] = $vc['city_code'];
                            }
                            if ($vc['city_code'] == $default_area['city']['code']) {
                                $d_codes[] = $vc['district_code'];
                            }
                        }
                    }



                    $c_codes = array_unique($c_codes);
                    $d_codes = array_unique($d_codes);

                    $city_codes_str='';
                    foreach($c_codes as $kc=>$vc){
                        $city_codes_str = $city_codes_str.$vc.",";
                    }
                    $city_codes_str = substr($city_codes_str,0,strlen($city_codes_str)-1);

                    $citys_list = Db::table('areas')
                        ->where('areano','in',$city_codes_str)
                        ->field('areaname as name,areano as code,case areano when 330400 then 1 else 2 end as city_rank ')
                        ->order('city_rank asc')
                        ->select();

                    $district_codes_str='';
                    foreach($d_codes as $kc=>$vc){
                        $district_codes_str = $district_codes_str.$vc.",";
                    }
                    $district_codes_str = substr($district_codes_str,0,strlen($district_codes_str)-1);
                    $districts_list = Db::table('areas')
                        ->where('areano','in',$district_codes_str)
                        ->field('areaname as name,areano as code')
                        ->select();
                    foreach($provs_list as $kp=>$vp){
                        if($vp['code']==$default_area['prov']['code']){
                            $provs_list[$kp]['flag'] = 1;
                        }else{
                            $provs_list[$kp]['flag'] = 0;
                        }
                    }
                    foreach($citys_list as $kp=>$vp){
                        if($vp['code']==$default_area['city']['code']){
                            $citys_list[$kp]['flag'] = 1;
                        }else{
                            $citys_list[$kp]['flag'] = 0;
                        }
                    }
                    foreach($districts_list as $kp=>$vp){
                        if($vp['code']==$default_area['district']['code']){
                            $districts_list[$kp]['flag'] = 1;
                        }else{
                            $districts_list[$kp]['flag'] = 0;
                        }
                    }
                    $arr = [
                        'position'=> $default_area,
                        'prov_list'=>$provs_list,
                        'city_list'=>$citys_list,
                        'district_list'=>$districts_list,
                    ];

                    $this->wlog($arr);
                    $data = [
                        'data'=>$arr,
                    ];
                    $this->success('success', $data);

                } catch (Exception $e) {
                    $this->error('网络繁忙,请稍后再试');
                }



            }else{
                if(!empty($district_code)){  //如果地区不为空

                    //通过经纬度获取所在城市
                    $lbs_qq_key = config('Lbs.QQ_KEY');
                    $location = $latitude.",".$longitude;
                    $url = "http://apis.map.qq.com/ws/geocoder/v1/?location={$location}&key={$lbs_qq_key}&get_poi=1";

                    $http = new Http();
                    $result_user_position = $http->get($url);
                    $result_user_position_arr = json_decode($result_user_position,true);
                    //  $this->wlog($result_user_position_arr['result']['ad_info']);

                    $location_areano = substr($result_user_position_arr['result']['ad_info']['city_code'],3);
                    $location_area_name = $result_user_position_arr['result']['ad_info']['city'];
                    $location_district_name = $result_user_position_arr['result']['ad_info']['district'];

                    $prov_code = substr($location_areano,0,2)."0000";
                    $arr = [
                        'prov_name' => $result_user_position_arr['result']['ad_info']['province'],
                        'city_name' => $result_user_position_arr['result']['ad_info']['city'],
                        'district_name' => $result_user_position_arr['result']['ad_info']['district'],

                        'district_code' => $result_user_position_arr['result']['ad_info']['adcode'],
                        'city_code' => $location_areano,
                        'prov_code'=>$prov_code,
                    ];
                    Db::table('user')->where('id','=',$user_info['id'])->update($arr);
                    $default_area = [
                        'prov'=>[
                            'code'=>$prov_code,
                            'name'=>$result_user_position_arr['result']['ad_info']['province'],
                        ],
                        'city'=>[
                            'code'=>$location_areano,
                            'name'=>$result_user_position_arr['result']['ad_info']['city'],
                        ],
                        'district'=>[
                            'code'=>$result_user_position_arr['result']['ad_info']['adcode'],
                            'name'=>$result_user_position_arr['result']['ad_info']['district'],
                        ],
                    ];



                    $prov_code = substr($district_code,0,2)."0000";
                    $city_code = substr($district_code,0,4)."00";
                    $prov_codes = Db::table('re_job')
                        ->distinct(true)
                        ->field('prov_code')
                        ->select();
                    $prov_codes_str = '';
                    foreach($prov_codes as $kc=>$vc){
                        $prov_codes_str = $prov_codes_str.$vc['prov_code'].",";
                    }
                    $prov_codes_str = substr($prov_codes_str,0,strlen($prov_codes_str)-1);
                    $provs_list = Db::table('areas')
                        ->where('areano','in',$prov_codes_str)
                        ->field('areaname as name,areano as code')
                        ->select();

                    $code_list = Db::table('re_job')
                        ->distinct('district_code')
                        ->field('prov_code,city_code,district_code')
                        ->select();
                    $c_codes = [] ;
                    $d_codes = [] ;
                    foreach($code_list as $kc=>$vc){
                        if($vc['prov_code']==$prov_code){
                            $c_codes[]  = $vc['city_code'];
                        }
                        if($vc['city_code']==$city_code){
                            $d_codes[]  = $vc['district_code'];
                        }
                    }

                    $c_codes = array_unique($c_codes);
                    $d_codes = array_unique($d_codes);

                    $city_codes_str='';
                    foreach($c_codes as $kc=>$vc){
                        $city_codes_str = $city_codes_str.$vc.",";
                    }
                    $city_codes_str = substr($city_codes_str,0,strlen($city_codes_str)-1);

                    $citys_list = Db::table('areas')
                        ->where('areano','in',$city_codes_str)
                        ->field('areaname as name,areano as code,case areano when 330400 then 1 else 2 end as city_rank ')
                        ->order('city_rank asc')
                        ->select();

                    $district_codes_str='';
                    foreach($d_codes as $kc=>$vc){
                        $district_codes_str = $district_codes_str.$vc.",";
                    }
                    $district_codes_str = substr($district_codes_str,0,strlen($district_codes_str)-1);
                    $districts_list = Db::table('areas')
                        ->where('areano','in',$district_codes_str)
                        ->field('areaname as name,areano as code')
                        ->select();
                    foreach($provs_list as $kp=>$vp){
                        if($vp['code']==$prov_code){
                            $provs_list[$kp]['flag'] = 1;
                        }else{
                            $provs_list[$kp]['flag'] = 0;
                        }
                    }
                    foreach($citys_list as $kp=>$vp){
                        if($vp['code']==$city_code){
                            $citys_list[$kp]['flag'] = 1;
                        }else{
                            $citys_list[$kp]['flag'] = 0;
                        }
                    }
                    foreach($districts_list as $kp=>$vp){
                        if($vp['code']==$district_code){
                            $districts_list[$kp]['flag'] = 1;
                        }else{
                            $districts_list[$kp]['flag'] = 0;
                        }
                    }
                    $arr = [
                        'position'=> $default_area,
                        'prov_list'=>$provs_list,
                        'city_list'=>$citys_list,
                        'district_list'=>$districts_list,
                    ];
                    $data = [
                        'data'=>$arr,
                    ];
                    $this->success('success', $data);
                }elseif(!empty($city_code)){    //如果城市不为空


                    //通过经纬度获取所在城市
                    $lbs_qq_key = config('Lbs.QQ_KEY');
                    $location = $latitude.",".$longitude;
                    $url = "http://apis.map.qq.com/ws/geocoder/v1/?location={$location}&key={$lbs_qq_key}&get_poi=1";

                    $http = new Http();
                    $result_user_position = $http->get($url);
                    $result_user_position_arr = json_decode($result_user_position,true);
                    //  $this->wlog($result_user_position_arr['result']['ad_info']);

                    $location_areano = substr($result_user_position_arr['result']['ad_info']['city_code'],3);
                    $location_area_name = $result_user_position_arr['result']['ad_info']['city'];
                    $location_district_name = $result_user_position_arr['result']['ad_info']['district'];

                    $prov_code = substr($location_areano,0,2)."0000";
                    $arr = [
                        'prov_name' => $result_user_position_arr['result']['ad_info']['province'],
                        'city_name' => $result_user_position_arr['result']['ad_info']['city'],
                        'district_name' => $result_user_position_arr['result']['ad_info']['district'],

                        'district_code' => $result_user_position_arr['result']['ad_info']['adcode'],
                        'city_code' => $location_areano,
                        'prov_code'=>$prov_code,
                    ];
                    Db::table('user')->where('id','=',$user_info['id'])->update($arr);
                    $default_area = [
                        'prov'=>[
                            'code'=>$prov_code,
                            'name'=>$result_user_position_arr['result']['ad_info']['province'],
                        ],
                        'city'=>[
                            'code'=>$location_areano,
                            'name'=>$result_user_position_arr['result']['ad_info']['city'],
                        ],
                        'district'=>[
                            'code'=>$result_user_position_arr['result']['ad_info']['adcode'],
                            'name'=>$result_user_position_arr['result']['ad_info']['district'],
                        ],
                    ];









                    $prov_code = substr($city_code,0,2)."0000";
                    $prov_codes = Db::table('re_job')
                        ->distinct(true)
                        ->field('prov_code')
                        ->select();
                    $prov_codes_str = '';
                    foreach($prov_codes as $kc=>$vc){
                        $prov_codes_str = $prov_codes_str.$vc['prov_code'].",";
                    }
                    $prov_codes_str = substr($prov_codes_str,0,strlen($prov_codes_str)-1);
                    $provs_list = Db::table('areas')
                        ->where('areano','in',$prov_codes_str)
                        ->field('areaname as name,areano as code')
                        ->select();

                    $code_list = Db::table('re_job')
                        ->distinct('district_code')
                        ->field('prov_code,city_code,district_code')
                        ->select();
                    $c_codes = [] ;
                    $d_codes = [] ;
                    foreach($code_list as $kc=>$vc){
                        if($vc['prov_code']==$prov_code){
                            $c_codes[]  = $vc['city_code'];
                        }
                        if($vc['city_code']==$city_code){
                            $d_codes[]  = $vc['district_code'];
                        }
                    }

                    $c_codes = array_unique($c_codes);
                    $d_codes = array_unique($d_codes);

                    $city_codes_str='';
                    foreach($c_codes as $kc=>$vc){
                        $city_codes_str = $city_codes_str.$vc.",";
                    }
                    $city_codes_str = substr($city_codes_str,0,strlen($city_codes_str)-1);

                    $citys_list = Db::table('areas')
                        ->where('areano','in',$city_codes_str)
                        ->field('areaname as name,areano as code,case areano when 330400 then 1 else 2 end as city_rank ')
                        ->order('city_rank asc')
                        ->select();

                    $district_codes_str='';
                    foreach($d_codes as $kc=>$vc){
                        $district_codes_str = $district_codes_str.$vc.",";
                    }
                    $district_codes_str = substr($district_codes_str,0,strlen($district_codes_str)-1);
                    $districts_list = Db::table('areas')
                        ->where('areano','in',$district_codes_str)
                        ->field('areaname as name,areano as code')
                        ->select();
                    foreach($provs_list as $kp=>$vp){
                        if($vp['code']==$prov_code){
                            $provs_list[$kp]['flag'] = 1;
                        }else{
                            $provs_list[$kp]['flag'] = 0;
                        }
                    }
                    foreach($citys_list as $kp=>$vp){
                        if($vp['code']==$city_code){
                            $citys_list[$kp]['flag'] = 1;
                        }else{
                            $citys_list[$kp]['flag'] = 0;
                        }
                    }
                    foreach($districts_list as $kp=>$vp){
                        $districts_list[$kp]['flag'] = 0;
                    }
                    $arr = [
                        'position'=> $default_area,
                        'prov_list'=>$provs_list,
                        'city_list'=>$citys_list,
                        'district_list'=>$districts_list,
                    ];
                    $data = [
                        'data'=>$arr,
                    ];
                    $this->success('success', $data);

                }elseif (!empty($prov_code)){     //如果省份不为空

                    //通过经纬度获取所在城市
                    $lbs_qq_key = config('Lbs.QQ_KEY');
                    $location = $latitude.",".$longitude;
                    $url = "http://apis.map.qq.com/ws/geocoder/v1/?location={$location}&key={$lbs_qq_key}&get_poi=1";

                    $http = new Http();
                    $result_user_position = $http->get($url);
                    $result_user_position_arr = json_decode($result_user_position,true);
                    //  $this->wlog($result_user_position_arr['result']['ad_info']);

                    $location_areano = substr($result_user_position_arr['result']['ad_info']['city_code'],3);
                    $location_area_name = $result_user_position_arr['result']['ad_info']['city'];
                    $location_district_name = $result_user_position_arr['result']['ad_info']['district'];

                    $prov_code = substr($location_areano,0,2)."0000";
                    $arr = [
                        'prov_name' => $result_user_position_arr['result']['ad_info']['province'],
                        'city_name' => $result_user_position_arr['result']['ad_info']['city'],
                        'district_name' => $result_user_position_arr['result']['ad_info']['district'],

                        'district_code' => $result_user_position_arr['result']['ad_info']['adcode'],
                        'city_code' => $location_areano,
                        'prov_code'=>$prov_code,
                    ];
                    Db::table('user')->where('id','=',$user_info['id'])->update($arr);
                    $default_area = [
                        'prov'=>[
                            'code'=>$prov_code,
                            'name'=>$result_user_position_arr['result']['ad_info']['province'],
                        ],
                        'city'=>[
                            'code'=>$location_areano,
                            'name'=>$result_user_position_arr['result']['ad_info']['city'],
                        ],
                        'district'=>[
                            'code'=>$result_user_position_arr['result']['ad_info']['adcode'],
                            'name'=>$result_user_position_arr['result']['ad_info']['district'],
                        ],
                    ];



                    switch($prov_code){
                        case "330000":
                            $city_code = "330400";
                            break;
                        case "610000":
                            $city_code = "611000";
                            break;
                        case "340000":
                            $city_code = "340100";
                            break;
                    }
                    $prov_codes = Db::table('re_job')
                        ->distinct(true)
                        ->field('prov_code')
                        ->select();
                    $prov_codes_str = '';
                    foreach($prov_codes as $kc=>$vc){
                        $prov_codes_str = $prov_codes_str.$vc['prov_code'].",";
                    }
                    $prov_codes_str = substr($prov_codes_str,0,strlen($prov_codes_str)-1);
                    $provs_list = Db::table('areas')
                        ->where('areano','in',$prov_codes_str)
                        ->field('areaname as name,areano as code')
                        ->select();

                    $code_list = Db::table('re_job')
                        ->distinct('district_code')
                        ->field('prov_code,city_code,district_code')
                        ->select();
                    $c_codes = [] ;
                    $d_codes = [] ;
                    foreach($code_list as $kc=>$vc){
                        if($vc['prov_code']==$prov_code){
                            $c_codes[]  = $vc['city_code'];
                        }
                        if($vc['city_code']==$city_code){
                            $d_codes[]  = $vc['district_code'];
                        }
                    }

                    $c_codes = array_unique($c_codes);
                    $d_codes = array_unique($d_codes);

                    $city_codes_str='';
                    foreach($c_codes as $kc=>$vc){
                        $city_codes_str = $city_codes_str.$vc.",";
                    }
                    $city_codes_str = substr($city_codes_str,0,strlen($city_codes_str)-1);

                    $citys_list = Db::table('areas')
                        ->where('areano','in',$city_codes_str)
                        ->field('areaname as name,areano as code,case areano when 330400 then 1 else 2 end as city_rank ')
                        ->order('city_rank asc')
                        ->select();

                    $district_codes_str='';
                    foreach($d_codes as $kc=>$vc){
                        $district_codes_str = $district_codes_str.$vc.",";
                    }
                    $district_codes_str = substr($district_codes_str,0,strlen($district_codes_str)-1);
                    $districts_list = Db::table('areas')
                        ->where('areano','in',$district_codes_str)
                        ->field('areaname as name,areano as code')
                        ->select();
                    foreach($provs_list as $kp=>$vp){
                        if($vp['code']==$prov_code){
                            $provs_list[$kp]['flag'] = 1;
                        }else{
                            $provs_list[$kp]['flag'] = 0;
                        }
                    }
                    foreach($citys_list as $kp=>$vp){
                        /*if($vp['code']==$city_code){
                            $citys_list[$kp]['flag'] = 1;
                        }else{
                            $citys_list[$kp]['flag'] = 0;
                        }*/
                        $citys_list[$kp]['flag'] = 0;
                    }
                    foreach($districts_list as $kp=>$vp){
                        $districts_list[$kp]['flag'] = 0;
                    }
                    $arr = [
                        'position'=> $default_area,
                        'prov_list'=>$provs_list,
                        'city_list'=>$citys_list,
                        'district_list'=>$districts_list,
                    ];
                    $data = [
                        'data'=>$arr,
                    ];
                    $this->success('success', $data);
                }
            }
        }else{
            $this->error('缺少必要的参数',null,2);
        }
    }





    //选择省份的
    public function prov(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'] ?? '';
        $default_prov_code = $data['prov_code'];
        switch($default_prov_code){
            case "330000":
                $default_city_code = "330400";
                break;
            case "610000":
                $default_city_code = "611000";
                break;
            case "340000":
                $default_city_code = "340100";
                break;
        }

        $code_list = Db::table('re_job')
            ->distinct('district_code')
            ->field('prov_code,city_code,district_code')
            ->select();
        $c_codes = [] ;
        $d_codes = [] ;
        foreach($code_list as $kc=>$vc){
            if($vc['prov_code']==$default_prov_code){
                $c_codes[]  = $vc['city_code'];
            }
            if($vc['city_code']==$default_city_code){
                $d_codes[]  = $vc['district_code'];
            }
        }

        $c_codes = array_unique($c_codes);
        $d_codes = array_unique($d_codes);

        $city_codes_str='';
        foreach($c_codes as $kc=>$vc){
            $city_codes_str = $city_codes_str.$vc.",";
        }
        $city_codes_str = substr($city_codes_str,0,strlen($city_codes_str)-1);

        $citys_list = Db::table('areas')
            ->where('areano','in',$city_codes_str)
            ->field('areaname as name,areano as code,case areano when 330400 then 1 else 2 end as city_rank ')
            ->order('city_rank asc')
            ->select();

        $district_codes_str='';
        foreach($d_codes as $kc=>$vc){
            $district_codes_str = $district_codes_str.$vc.",";
        }
        $district_codes_str = substr($district_codes_str,0,strlen($district_codes_str)-1);
        $districts_list = Db::table('areas')
            ->where('areano','in',$district_codes_str)
            ->field('areaname as name,areano as code')
            ->select();
        $arr = [
            //   'choose'=> $default_area,
            //   'prov_list'=>$provs_list,
            'city_list'=>$citys_list,
            'district_list'=>$districts_list,
        ];
        $data = [
            'data'=>$arr,
        ];
        $this->success('success', $data);
    }


    //选择城市
    public function city(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'] ?? '';
        $default_city_code = $data['city_code'];

        $code_list = Db::table('re_job')
            ->distinct('district_code')
            ->field('prov_code,city_code,district_code')
            ->select();
        $d_codes = [] ;
        foreach($code_list as $kc=>$vc){
            if($vc['city_code']==$default_city_code){
                $d_codes[]  = $vc['district_code'];
            }
        }

        $d_codes = array_unique($d_codes);

        $district_codes_str='';
        foreach($d_codes as $kc=>$vc){
            $district_codes_str = $district_codes_str.$vc.",";
        }
        $district_codes_str = substr($district_codes_str,0,strlen($district_codes_str)-1);
        $districts_list = Db::table('areas')
            ->where('areano','in',$district_codes_str)
            ->field('areaname as name,areano as code')
            ->select();
        $arr = [
            //   'choose'=> $default_area,
            //   'prov_list'=>$provs_list,
            // 'city_list'=>$citys_list,
            'district_list'=>$districts_list,
        ];
        $data = [
            'data'=>$arr,
        ];
        $this->success('success', $data);
    }



}
