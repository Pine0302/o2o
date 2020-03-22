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
class Coupon extends Api
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




    //个人中心页面优惠券列表
    public function userCouponList(){
        $data = $this->request->post();
        $now = time();
        $sess_key = $data['sess_key'];

        $user_info = $this->getGUserInfo($sess_key);

        //先获取所有未过期,未使用的优惠券
        $type = $data['type'] ?? 1;
        if($type==1){
            $map['l.use_end_time'] = ['>',$now];
            $map['l.use_start_time'] = ['<',$now];
            $map['l.status'] = ['=',0];
        }elseif($type==2){
            $map['l.status'] = ['=',1];
        }else{
            $map['l.use_end_time'] = ['<',$now];
            $map['l.status'] = ['neq',1];
        }
        $coupon_list = Db::name('coupon_list')
            ->alias('l')
            ->join('tp_plat_coupon c','l.cid=c.id','left')
            ->where('l.uid','=',$user_info['user_id'])
            ->where($map)
        /*    ->where('l.use_end_time','>',$now)
            ->where('l.use_start_time','<',$now)*/
            ->field('l.*,c.name')
            ->select();
        $respond_arr = [];
        foreach($coupon_list as $kc=>$vc){
            $money = $vc['money'];
            if($vc['type']==1){
                $money = intval($money);
            }
            $respond_arr[] = [
                'id'=>$vc['id'],
                'name'=>$vc['name'],
                'money'=>intval($money),
                'type'=>$vc['type'],
                'condition'=>intval($vc['condition']),
                'use_end_time'=>date("Y-m-d",$vc['use_end_time']),
            ];
        }
        $data = [
            'data'=>$respond_arr,
        ];
        $this->success('success', $data);
    }


    //订单页面优惠券列表
    public function orderCouponList(){
        $data = $this->request->post();
        $now = time();
        $sess_key = $data['sess_key'];
        $store_id = $data['store_id'] ?? "170";
        $user_info = $this->getGUserInfo($sess_key);

        //先获取所有未过期,未使用的优惠券
        $coupon_list = $this->getValidCoupons($user_info['user_id']);


        $userable_coupon_num = 0;  //可使用优惠券
        $userable_coupon = [];
        $unuserable_coupon = [];
        if(count($coupon_list)>0){
            $cart_category_info = $this->getCartCategoryInfo($user_info['user_id'],$store_id);
           //获取商品总数量
            $cat_info = $cart_category_info['cat_info'];
            $num = 0;
            if(!empty($cat_info)){
                foreach($cat_info as $kc=>$vc){
                    $num  += $vc['num'];
                }
            }

         if($num>1){
             foreach($coupon_list as $kc=>$vc){
                 if($vc['type']==2){
                     //todo 后期更新,这里要把27 28
                     if(($vc['cid']!=27)&&($vc['cid']!=28)&&($vc['cid']!=37)){
                         unset($coupon_list[$kc]);
                         $vc['reason'] = "购买商品超过优惠券使用个数";
                         $unuserable_coupon[] = $vc;
                     }
                 }
             }
         }

            foreach($coupon_list as $kc=>$vc){
                if($vc['type']==1){  //满减券
                    if($vc['condition']>$cart_category_info['price']){
                        $vc['reason'] = "订单总额不满优惠券最低消费金额";
                        $unuserable_coupon[] = $vc;
                    }else{
                        $userable_coupon[] = $vc;
                    }
                }elseif ($vc['type']==2){       //折扣券
                    //查看该券支持的category_ids
                    if(!empty($vc['category_ids'])){
                        $category_ids_arr = explode(",",$vc['category_ids']);
                        $status = 0;
                        foreach($cart_category_info['cat_info'] as $kcc=>$vcc){
                            if(in_array($vcc['cat_id'],$category_ids_arr)){
                                $status = 1;
                                break;
                            }
                        }
                    }else{
                        $status = 1;
                    }

                    if($status==1){
                        $userable_coupon[] = $vc;
                    }else{
                        $vc['reason'] = "订单商品中没有属于优惠券的品类";
                        $unuserable_coupon[] = $vc;
                    }

                }elseif ($vc['type']==3){       //买一赠一券
                    $num = 0;
                    if(!empty($vc['category_ids'])){
                        $category_ids_arr = explode(",",$vc['category_ids']);
                        $status = 0;
                        foreach($cart_category_info['cat_info'] as $kcc=>$vcc){
                            if(in_array($vcc['cat_id'],$category_ids_arr)){
                                $num = $num+$vcc['num'];
                            }
                        }
                        if($num>1){
                            $status = 1;
                        }
                    }else{
                        foreach($cart_category_info['cat_info'] as $kcc=>$vcc){
                                $num = $num+$vcc['num'];
                        }
                        if($num>1){
                            $status = 1;
                        }
                    }

                    if($status==1){
                        $userable_coupon[] = $vc;
                    }else{
                        $vc['reason'] = "订单中商品没有符合买一赠一条件";
                        $unuserable_coupon[] = $vc;
                    }
                }
            }
            $arr = [
                'coupon_list'=>$coupon_list,
                'unuserable_coupon'=>$unuserable_coupon,
                'userable_coupon'=>$userable_coupon,
                'userable_coupon_num' =>count($userable_coupon),
            ];
        }else{   //没有可用的优惠券
            $arr = [
                'coupon_list'=>[],
                'unuserable_coupon'=>[],
                'userable_coupon'=>[],
                'userable_coupon_num' =>0,
            ];
        }
        $this->success('success', $arr);
    }





    //获取购物车里商品的类型和数量
    public function getCartCategoryInfo($user_id,$store_id){
        //var_dump($user_info);exit;
        $cart_list = Db::name('cart')
            ->alias('c')
            ->join('tp_goods g','g.goods_id = c.goods_id')
            ->where('c.user_id','=',$user_id)
            ->where('c.store_id','=',$store_id)
            ->field('c.*,g.cat_id')
            ->select();

      //  $goodList = [];
     //   $good_ids_arr = [];
        $cat_ids_arr = [];
        $cat_ids_info = [];
        $price = 0;

        foreach($cart_list as $kc=>$vc){
            $item_price = $vc['key_price'] * $vc['goods_num'] ;
            $price = $price + $item_price;
       /*     if(!in_array($vc['goods_id'],$good_ids_arr)){
                $good_ids_arr[] = $vc['goods_id'];
            }*/
            if(!in_array($vc['cat_id'],$cat_ids_arr)){
                $cat_ids_arr[] = $vc['cat_id'];
                $cat_ids_info[$vc['cat_id']] =
                    [
                        'cat_id'=> $vc['cat_id'],
                        'num'=> $vc['goods_num'],
                    ];
            }else{
                $cat_ids_info[$vc['cat_id']]['num'] =$cat_ids_info[$vc['cat_id']]['num'] + $vc['goods_num'];
            }
     /*       $goodList[] = [
                'goods_id'=>$vc['goods_id'],
                'name'=>$vc['goods_name'],
                'num'=>$vc['goods_num'],
                'price'=>$vc['key_price'],
                'cat_id'=>$vc['cat_id'],
            ];*/
        }
        $arr = [
            'cat_info'=>$cat_ids_info,
            'price'=>$price,
        ];
   //     var_dump($arr);exit;
        return $arr;
    }


    //获取某个用户的使用期限内未使用的优惠券列表
    public function getValidCoupons($user_id){
        $now = time();
        $map['l.use_end_time'] = ['>',$now];
        $map['l.use_start_time'] = ['<',$now];
        $map['l.status'] = ['=',0];
        $coupon_list = Db::name('coupon_list')
            ->alias('l')
            ->join('tp_plat_coupon c','l.cid=c.id','left')
            ->where('l.uid','=',$user_id)
            ->where($map)
            ->field('l.*,c.name')
            ->select();

        $respond_arr = [];
        foreach($coupon_list as $kc=>$vc){
            $money = $vc['money'];
            if($vc['type']==1){
                $money = intval($money);
            }else{
                $money = '';
            }
            $respond_arr[] = [
                'id'=>$vc['id'],
                'cid'=>$vc['cid'],
                'name'=>$vc['name'],
                'money'=>intval($money),
                'type'=>$vc['type'],
                'condition'=>intval($vc['condition']),
                'category_ids'=>$vc['category_ids'],
                'use_end_time'=>date("Y-m-d",$vc['use_end_time']),
            ];
        }
        //var_dump($respond_arr);exit;
        return $respond_arr;

    }

    //优惠券数量
    public function userCouponNum(){
        $data = $this->request->post();
        $now = time();
        $sess_key = $data['sess_key'];
        $user_info = $this->getGUserInfo($sess_key);
        $coupon_num = Db::name('coupon_list')
            ->alias('l')
            ->join('tp_plat_coupon c','l.cid=c.id','left')
            ->where('l.uid','=',$user_info['user_id'])
            ->where('l.status','=',0)
            ->where('l.use_end_time','>',$now)
            ->where('l.use_start_time','<',$now)
            ->field('l.*,c.name')
            ->count();
        $data = [
            'data'=>$coupon_num,
        ];
        $this->success('success', $data);
    }


    //发新人券
    public function newUserCouponList(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'];
        $user_info = $this->getGUserInfo($sess_key);

        $result= [];
        if($user_info['coupon_status']!=1){
            //发券
            if($user_info['user_id']){
                $plat_coupon_info = Db::name('plat_coupon')->where('is_new','=',1)->select();

                foreach($plat_coupon_info as $kp=>$vp){
                   // $result = $this->sendCoupon($user_info['user_id'],$plat_coupon_info[0]);
                    $result = $this->sendCoupon($user_info['user_id'],$vp);
                    if(!empty($result)){
                        Db::name('users')->where('user_id','=',$user_info['user_id'])
                            ->update([
                                'coupon_status'=>1,
                                'is_new'=>2,
                            ]);
                        $response[] = $result;
                    }
                }

            }
        }
        $response = [
            'data'=>$response,
        ];

        $this->success('success', $response);
    }


    //给用户发券(单张)
    public function sendCoupon($user_id,$coupon_info){
        $code = uniqid($user_id).'_coupon_'.$user_id.'_'.rand(1000,9999); //单号

        $insert_coupon_list_arr = [
            'cid'=>$coupon_info['id'],
            'type'=>$coupon_info['type'],
            'uid'=>$user_id,
            'money'=>$coupon_info['money'],
            'condition'=>$coupon_info['condition'],
            'use_start_time'=>$coupon_info['use_start_time'],
            'use_end_time'=>$coupon_info['use_end_time'],
            'category_ids'=>$coupon_info['category_ids'],
            'method'=>1,
            'code'=>$code,
            'send_time'=>time(),
            'status'=>0,
        ];

        $result = Db::name('coupon_list')->insert($insert_coupon_list_arr);
        if(!empty($result)){
            return $insert_coupon_list_arr;
        }
    }

    //优惠券列表
    public function couponInfo(){
        $data = $this->request->post();
        $now = time();
        $sess_key = $data['sess_key'];
        $user_info = $this->getGUserInfo($sess_key);
        $coupon_id = $data['coupon_id'];
        $coupon_info = Db::name('coupon_list')
            ->alias('l')
            ->join('tp_plat_coupon c','l.cid=c.id','left')
            ->where('l.uid','=',$user_info['user_id'])
            ->where('l.id','=',$coupon_id)
            ->field('l.*,c.name,c.secen,c.instruction')
            ->find();

        $respond_arr = [];
        $money = $coupon_info['money'];
        if($coupon_info['type']==1){
            $money = intval($money);
        }

        //todo   后期改回来临时处理 折扣券显示改为改为0元券
        if($coupon_info['type']==2){
            $money = 0;
        }

        $valid_product = "全部商品";
        if($coupon_info['type']!=1){
            if(!empty($coupon_info['category_ids'])){

                $type_info = Db::name('goods_category')->where('id','in',$coupon_info['category_ids'])->select();

                if(!empty($type_info)){
                    $valid_product ='';
                    foreach($type_info as $kt=>$vt){
                        $valid_product = $valid_product.$vt['name'].",";
                    }

                    $valid_product = rtrim($valid_product,",");

                //    var_dump($valid_product);exit;
                }
            }
        }
        $respond_arr = [
            'id'=>$coupon_info['id'],
            'name'=>$coupon_info['name'],
            'money'=>intval($money),
            'type'=>$coupon_info['type'],
            'condition'=>intval($coupon_info['condition']),
            'use_end_time'=>date("Y-m-d",$coupon_info['use_end_time']),
            'secen'=>explode("/",$coupon_info['secen']),
            'instruction'=>explode("/",$coupon_info['instruction']),
            'store_info'=>"全部门店",
            'valid_product'=>$valid_product,
        ];
        $data = [
            'data'=>$respond_arr,
        ];
        $this->success('success', $data);
    }

}
