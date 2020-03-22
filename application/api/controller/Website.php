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
use app\common\library\OrderHandle;
use fast\Algor;
/**
 * 工作相关接口
 */
class Website extends Api
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



    public function __construct()
    {
        parent::__construct();


    }



    // 今日订单(已支付)
    public function banner(){
        $data = $this->request->post();

        $sess_key = $data['sess_key'];
     //   $user_info = $this->getGUserInfo($sess_key);
        $banner = Db::name('ad')
            ->where('enabled','=',1)
            ->order('orderby desc')
            ->select();

        $response = [];
        foreach($banner as $ko=>$vo){
            $url = "https://".$_SERVER['HTTP_HOST'].$vo['ad_code'];
            $id = $vo['ad_id'];
            $order_info[] = [
                'id'=>$id,
                'url'=>$url,
            ];
        }
        $data = [
            'data'=>$order_info,
        ];
        $this->success('success',$data);
    }

    //ado
    public function ado(){
        $data = $this->request->post();

      //  $sess_key = $data['sess_key'];
        //   $user_info = $this->getGUserInfo($sess_key);
        $banner = Db::name('ado')
            ->where('enabled','=',1)
            ->find();
        $response = [];
        $response = [
            'url'=>$url = "https://".$_SERVER['HTTP_HOST'].$banner['ad_code'],
            'id'=>$banner['ad_id'],
        ];
        $data = [
            'data'=>$response,
        ];
        $this->success('success',$data);
    }


    //ado
    public function adenter(){
        $data = $this->request->post();

        //  $sess_key = $data['sess_key'];
        //   $user_info = $this->getGUserInfo($sess_key);
        $banner = Db::name('adenter')
            ->where('enabled','=',1)
            ->find();
        $response = [];
        $response = [
            'url'=>$url = "https://".$_SERVER['HTTP_HOST'].$banner['ad_code'],
            'id'=>$banner['ad_id'],
        ];
        $data = [
            'data'=>$response,
        ];
        $this->success('success',$data);
    }





}
