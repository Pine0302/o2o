<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\wx\WXBizDataCrypt;
use fast\AreaInclude;
use fast\Http;
use think\cache\driver\Redis;
use think\Db;
use think\Request;
use think\Session;
use think\Cache;
use app\api\controller\Common;
use app\api\library\NoticeHandle;
/**
 * 工作相关接口
 */
class Store extends Api
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


    public function test1()
    {
        $this->success('返回成功', ['action' => 'test1']);
    }

    //店铺列表
    public function storeList(){
        $data = $this->request->post();
        $openid = $this->analysisUserJwtToken();
        $user_info = $this->getGUserInfo($openid);
        $user_point = [
            'lng'=>$data['lng'],
            'lat'=>$data['lat'],
        ];
        $type = $data['type'];
        $title = $data['title'];

        $storeDb = Db::name('store_sub');
        if(!empty($data['title'])){
            $storeDb->where('store_name','like',"'%".$data['title']."%'");
        }
        $storeDb->where('is_shenhe','=',1);
        switch($type){
            case "price":
                $storeDb->order(['average_consume'=>'asc']);
                break;
            case "sale":
                $storeDb->order(['month_sale'=>'desc']);
                break;
        }
        $store_list = $storeDb->select();
        $store_list = $this->getStoreWithDistance($store_list,$user_point);
        if($type=="distance"){
            $distances = array_column($store_list,'distance');
            array_multisort($distances,SORT_ASC,$store_list);
        }
        $arr = $store_list;
        $data = [
            'data'=>$arr,
        ];
        $this->success('success', $data);
    }


    public function getStoreWithDistance($store_list,$user_point){
        $arr = [];
        if(count($store_list)>0){
            $areaIncludeObj = new AreaInclude();
            foreach($store_list as $kd=>$vd){
                $lnglat_tx = unserialize($vd['lnglat_tx']);
                $distance = $areaIncludeObj->distance($user_point['lat'],$user_point['lng'],$vd['store_lat_tx'],$vd['store_lng_tx']);
                $distance = number_format($distance/1000,2);
                $arr[] = [
                    'store_id'=>$vd['store_id'],
                    'name'=>$vd['store_name'],
                    'store_state'=>$this->getStoreState($vd),
                    'mobile'=>$vd['store_phone'],
                    'address'=>$vd['store_address'],
                    'lng'=>$vd['store_lng_tx'],
                    'lat'=>$vd['store_lat_tx'],
                    'store_time'=>$vd['store_time'],
                    'store_time2'=>$vd['store_time2'],
                    'store_end_time'=>$vd['store_end_time'],
                    'store_end_time2'=>$vd['store_end_time2'],
                    'distance'=>$distance,
                    'month_sale'=>$vd['month_sale'],
                    'average_consume'=>$vd['average_consume'],
                    'meituan_grade'=>$vd['meituan_grade'],
                    'notice'=>$vd['notice'],
                    'logo' => $this->getImage($vd['image'],$vd['image_oss']),
                ];
            }
        }
        return $arr;
    }


    //选择店铺列表
    public function setDefaultStore(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'];
        $store_id = $data['store_id'];
        $user_info = $this->getGUserInfo($sess_key);
        $update = [
            'default_store_id'=>$store_id,
        ];
        Db::name('users')->where('user_id','=',$user_info['user_id'])->update($update);
        $this->success('success');
    }

    //店铺列表
    public function defaultStore(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'];
        $user_info = $this->getTUserInfo($sess_key);

        $user_point = [
            'lng'=>$data['lng'],
            'lat'=>$data['lat'],
        ];
        if($user_info['user_id']==265){
            $this->wlog("---------",'ttt.txt');
            $this->wlog($user_info['user_id'],'ttt.txt');
            $this->wlog($user_point,'ttt.txt');
            $this->wlog("defaultStore",'ttt.txt');
            $this->wlog("---------",'ttt.txt');
        }
        Db::name('users')->where('user_id','=',$user_info['user_id'])->update($user_point);
        if(!empty($user_info['default_store_id'])){
            $store_list = Db::name('store_sub')
                ->where('is_shenhe','=',1)
                ->where('store_id','=',$user_info['default_store_id'])
                ->select();
        }else{
            $store_list = Db::name('store_sub')
                ->where('is_shenhe','=',1)
                ->select();
        }

        $store_list = Db::name('store_sub')
            ->where('is_shenhe','=',1)
            ->select();

        $arr = [];
        if(count($store_list)>0){
            $areaIncludeObj = new AreaInclude();
            $default_distance = 10000000;
            $default_store_id = 0;
            $arr = [];
            foreach($store_list as $kd=>$vd){

                $lnglat_tx = unserialize($vd['lnglat_tx']);

                $is_include = $areaIncludeObj->is_point_in_polygon($user_point,$lnglat_tx);

                $this->wlog($is_include);
             //  if($is_include==1){
                $distance = $areaIncludeObj->distance($user_point['lat'],$user_point['lng'],$vd['store_lat_tx'],$vd['store_lng_tx']);
                if($user_info['user_id']==646){
                    $this->wlog('-------------','ttt.txt');
                    $this->wlog($vd['store_name'],'ttt.txt');
                    //   $this->wlog($lnglat_tx,'ttt.txt');
                    $this->wlog($distance,'ttt.txt');
                    $this->wlog('-------------','ttt.txt');
                }
                    if(intval($distance)<$default_distance){
                        $default_distance = $distance;
                        $default_store_id = $vd['store_id'];

                        $arr[$vd['store_id']] = [
                            'store_id'=>$vd['store_id'],
                            'name'=>$vd['store_name'],
                            'store_state'=>$this->getStoreState($vd),
                            'mobile'=>$vd['store_phone'],
                            'address'=>$vd['store_address'],
                            'lng'=>$vd['store_lng_tx'],
                            'lat'=>$vd['store_lat_tx'],
                            'store_time'=>$vd['store_time'],
                            'store_end_time'=>$vd['store_end_time'],
                            'distance'=>number_format($distance/1000,2),
                            'store_status'=>$vd['store_state'],
                        ];
                    }
              //  }
            }

            $default_store = $arr[$default_store_id];
        }else{
            $default_store = null;
        }

        if($user_info['user_id']==646){
            $this->wlog('-------------','ttt.txt');
            $this->wlog($default_store,'ttt.txt');
            $this->wlog('-------------','ttt.txt');
        }

        $data = [
            'data'=>$default_store,
        ];
        $this->success('success', $data);
    }


    //店铺详情
    public function storeInfo(){
        $data = $this->request->post();
        $openid = $this->analysisUserJwtToken();
        $user_info = $this->getTUserInfo($openid);
        $store_id =  $data['store_id'];
        $user_point = [
            'lng'=>$data['longitude'],
            'lat'=>$data['latitude'],
        ];
        Db::name('users')->where('user_id','=',$user_info['user_id'])->update($user_point);
        $store_info = Db::name('store_sub')
            ->where('is_shenhe','=',1)
            ->where('store_id','=',$store_id)
            ->find();
        $areaIncludeObj = new AreaInclude();
        $distance = $areaIncludeObj->distance($user_point['lat'],$user_point['lng'],$store_info['store_lat_tx'],$store_info['store_lng_tx']);
        $arr = [
            'store_id'=>$store_info['store_id'],
            'name'=>$store_info['store_name'],
            'notice'=>$store_info['notice'],
            'store_description'=>$store_info['store_description'],
            'meituan_grade'=>$store_info['meituan_grade'],
            'month_sale'=>$store_info['month_sale'],
            'meituan_grade'=>$store_info['meituan_grade'],
            'type_name'=>$store_info['type_name'],
            'store_state'=>$this->getStoreState($store_info),
            'mobile'=>$store_info['store_phone'],
            'address'=>$store_info['store_address'],
            'lng'=>$store_info['store_lng_tx'],
            'lat'=>$store_info['store_lat_tx'],
            'store_time'=>$store_info['store_time'],
            'store_end_time'=>$store_info['store_end_time'],
            'distance'=>number_format($distance/1000,2),
            'store_status'=>$store_info['store_state'],
        ];
        $data = [
            'data'=>$arr,
        ];
        $this->success('success', $data);
    }


    //城市列表
    public function cityList(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'];
        $user_info = $this->getGUserInfo($sess_key);
        $city_ids = '';
        $store_sub_list = Db::name('store_sub')
            ->field('distinct city_id')
            ->where('is_shenhe','=',1)
            ->select();
        foreach($store_sub_list as $ks=>$vs){
            $city_ids = $city_ids.$vs['city_id'].",";
        }
        $city_ids = substr($city_ids,0,strlen($city_ids)-1);
        $city_list = Db::name('region')->where('id','in',$city_ids)->field('id,name')->select();
        $data = [
            'data'=>$city_list,
        ];
        $this->success('success', $data);
    }


    //城市的门店列表
    public function cityStoreList(){

        $data = $this->request->post();
        $this->wlog($data);
        $sess_key = $data['sess_key'];
        $user_info = $this->getGUserInfo($sess_key);
        $data = $this->request->post();
        $city_id = $data['city_id'];
        /*$user_point = [
            'lng'=>$user_info['user_info']['lng'],
            'lat'=>$user_info['user_info']['lat'],
        ];*/
        $user_point = [
            'lat'=>$data['lat'],
            'lng'=>$data['log'],
        ];

        /*  $user_point = [
              'lng' => '120.112574',
              'lat' => '29.309797',
          ];*/

        $store_list = Db::name('store_sub')
            ->where('city_id','=',$city_id)
            ->where('is_shenhe','=',1)
            ->select();
        $arr = [];

        if(count($store_list)>0){
            $areaIncludeObj = new AreaInclude();
            foreach($store_list as $kd=>$vd){
                $lnglat_tx = unserialize($vd['lnglat_tx']);
                $is_include = $areaIncludeObj->is_point_in_polygon($user_point,$lnglat_tx);
                $is_include = 1;
                if($is_include==1){
                    //获取两点之间的距离

                    /*$this->wlog("-------------");
                    $this->wlog($data['log']);
                    $this->wlog($data['lat']);
                    $this->wlog($vd['store_lng_tx']);
                    $this->wlog($vd['store_lat_tx']);*/

                  //  $distance = $areaIncludeObj->getDistance($data['log'],$data['lat'],$vd['store_lng_tx'],$vd['store_lat_tx'],1);
                    $distance = $areaIncludeObj->distance($data['lat'],$data['log'],$vd['store_lat_tx'],$vd['store_lng_tx'],2);
                    /*  $this->wlog($distance);
                      $this->wlog("----------");*/

                    $distance = number_format($distance/1000,2);
                    //$distance = 1000;
                    $arr[] = [
                        'store_id'=>$vd['store_id'],
                        'name'=>$vd['store_name'],
                        'store_state'=>$this->getStoreState($vd),
                        'mobile'=>$vd['store_phone'],
                        'address'=>$vd['store_address'],
                        'lng'=>$vd['store_lng_tx'],
                        'lat'=>$vd['store_lat_tx'],
                        'store_time'=>$vd['store_time'],
                        'store_end_time'=>$vd['store_end_time'],
                        'distance'=>$distance,
                        'store_status'=>$vd['store_state'],
                    ];
                }
            }
        }
        $data = [
            'data'=>$arr,
        ];
        $this->success('success', $data);
    }


    //店铺列表
    public function storeSearchList(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'];
        $search_name = $data['search_name'];
        $user_info = $this->getGUserInfo($sess_key);

        /*$user_point = [
            'lng'=>$data['lng'],
            'lat'=>$data['lat'],
        ];*/

        /*   if($user_info['user_id']==62){
               $this->wlog($user_point);
           }*/
        /*  $user_point = [
              'lng'=>'120.060781',
              'lat'=>'29.301272',
          ];*/
        $map['s.store_name']  = ['like','%'.$search_name.'%'];
        $store_list = Db::name('store_sub s')
            ->join('tp_region r','r.id = s.city_id')
            ->field('s.*,r.name as city_name')
            ->where($map)
            ->select();

        $arr = [];

        if(count($store_list)>0){
            $areaIncludeObj = new AreaInclude();

            foreach($store_list as $kd=>$vd){

                $lnglat_tx = unserialize($vd['lnglat_tx']);
                //       $is_include = $areaIncludeObj->is_point_in_polygon($user_point,$lnglat_tx);
                //   if($is_include==1){
                //获取两点之间的距离
                $distance = $areaIncludeObj->distance($data['lng'],$data['lat'],$vd['store_lng_tx'],$vd['store_lat_tx']);
                $distance = number_format($distance,2);
                $arr[] = [
                    'store_id'=>$vd['store_id'],
                    'name'=>$vd['store_name'],
                    'store_state'=>$this->getStoreState($vd),
                    'mobile'=>$vd['store_phone'],
                    'address'=>$vd['store_address'],
                    'lng'=>$vd['store_lng_tx'],
                    'lat'=>$vd['store_lat_tx'],
                    'store_time'=>$vd['store_time'],
                    'store_end_time'=>$vd['store_end_time'],
                    'distance'=>$distance,
                    'city_id'=>$vd['city_id'],
                    'city_name'=>$vd['city_name'],
                    'store_status'=>$vd['store_state'],
                ];
                //   }
            }
        }
        $data = [
            'data'=>$arr,
        ];
        $this->success('success', $data);
    }

    //获取状态
    public function getStoreState($store_info){
        $now = time();
        $store_begin_time = strtotime($store_info['store_time']);
        $store_end_time = strtotime($store_info['store_end_time']);
        if(($now>$store_begin_time)&&($now<$store_end_time)){
            return 1;
        }else{
            return 0;
        }
    }




}
