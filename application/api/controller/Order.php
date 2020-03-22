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
use fast\OpenicSf;
/**
 * 工作相关接口
 */
class Order extends Api
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


    public function test(){
        $noticeHandel = new NoticeHandle();
        $noticeHandel->sendModel(33,1);
    }

    //我的购物车列表
    public function orderShow(){
        $data = $this->request->post();

        $now = time();
        $sess_key = $data['sess_key'];
        $store_id = $data['store_id'];
        $type = $data['type'];
        $coupon_id = isset($data['coupon_id']) ? $data['coupon_id']:'' ;
        $address_id = isset($data['address_id']) ? $data['address_id']:'' ;
        $user_info = $this->getGUserInfo($sess_key);

        $cart_list = Db::name('cart')
          /*  ->where('user_id','=',$user_info['user_id'])
            ->where('store_id','=',$store_id)
            ->select();*/
            ->alias('c')
            ->join('tp_goods g','g.goods_id = c.goods_id')
            ->where('c.user_id','=',$user_info['user_id'])
            ->where('c.store_id','=',$store_id)
            ->field('c.*,g.cat_id')
            ->select();



        $goodList = [];
        $price = 0;
        $item_num = 0;
        foreach($cart_list as $kc=>$vc){
            $item_price = $vc['key_price'] * $vc['goods_num'] ;
            $price = $price + $item_price;
            $item_num = $item_num + $vc['goods_num'];
            $goodList[] = [
                'cart_id'=>$vc['id'],
                'name'=>$vc['goods_name'],
                'spec'=>$vc['key'],
                'spec_item'=>$vc['key_name'],
                'num'=>$vc['goods_num'],
                'price'=>$vc['key_price'],
                'total_price'=>$item_price,
                'cat_id'=>$vc['cat_id']
            ];
        }


      /*  $coupon_list = Db::name('coupon_list')
            ->alias('l')
            ->join('tp_plat_coupon c','l.cid=c.id','left')
            ->field('l.*,c.name,case when l.condition <= '.$price.' then 1  else 2 end condition_sort')
            ->where('l.uid','=',$user_info['user_id'])
            ->where('l.use_end_time','>',$now)
            ->where('l.use_start_time','<',$now)
            ->order('condition_sort asc,id desc')
            ->select();
        $valid_coupon_count = 0;
        foreach($coupon_list as $kc=>$vc){
            if($vc['condition_sort']==1){
                $valid_coupon_count++;
            }
        }

        $coupon_arr = [];
        foreach($coupon_list as $kc=>$vc){

            $money = $vc['money'];
            if($vc['type']==2){
                $money = intval($money);
            }

            $isvalid = $vc['condition_sort'];
            $reason = ($isvalid==1) ? '' : '不够满减额度';
            $coupon_arr[] = [
                'id'=>$vc['id'],
                'name'=>$vc['name'],
                'money'=>$money,
                'type'=>$vc['type'],
                'condition'=>$vc['condition'],
                'use_end_time'=>date("Y-m-d",$vc['use_end_time']),
                'isvalid'=>$isvalid,
                'reason'=>$reason,
            ];

        }*/


        if(empty($address_id)){
            $address_info = Db::name('user_address')
                ->where('user_id','=',$user_info['user_id'])
                ->where('is_default','=',1)
                ->find();

            $address_response = [
                'address_id'=>$address_info['address_id'],
                'address_num'=>$address_info['address_num'],
                'label'=>$address_info['label'],
                'mobile'=>$address_info['mobile'],
                'name'=>$address_info['consignee'],
                'gender'=>$address_info['gender'],
                'is_default'=>$address_info['is_default'],
                'lat'=>$address_info['latitude'],
                'lng'=>$address_info['longitude'],
            ];
        }else{
            $address_info = Db::name('user_address')
                ->where('address_id','=',$address_id)
                ->find();
            $address_response = [
                'address_id'=>$address_info['address_id'],
                'address_num'=>$address_info['address_num'],
                'label'=>$address_info['label'],
                'mobile'=>$address_info['mobile'],
                'name'=>$address_info['consignee'],
                'gender'=>$address_info['gender'],
                'is_default'=>$address_info['is_default'],
                'lat'=>$address_info['latitude'],
                'lng'=>$address_info['longitude'],
            ];
        }

        if(empty($address_response['mobile'])){
            $address_response['mobile'] = $user_info['weixin_mobile'];
        }


        //获取起送费和是否支达到起送金额
        $init_delivery_info = Db::name('store_arg')->where('id','=',1)->find();
        $delivery_fee_info = Db::name('store_arg')->where('id','=',2)->find();
        $no_fee_info = Db::name('store_arg')->where('id','=',3)->find();

        $init_delivery_fee = $init_delivery_info['value'];
        //获取是否在配送范围  todo 这个暂时不清楚具体逻辑,先默认在配送范围
        $user_point = [
            'lat'=>$address_response['lat'],
            'lng'=>$address_response['lng'],
        ];
        $init_delivery_area = $this->checkSendInfo($user_point,$store_id);
        //查看是否有选择coupon
        if(!empty($coupon_id)){
            $coupon_info =
                Db::name('coupon_list')
                    ->alias('l')
                    ->join('tp_plat_coupon c','c.id=l.cid','left')
                    ->where("l.id",'=',$coupon_id)
                    ->field('l.*,c.is_new,c.is_rec')
                    ->find();

            $coupon_money = $coupon_info['money'];
            $coupon_type = $coupon_info['type'];


            if(intval($coupon_money)==0){
             //   if(($coupon_info['is_new']==1)||($coupon_info['is_rec']==1)){
                    $coupon_dec_money = $cart_list[0]['goods_price'];
                    foreach($cart_list as $kc=>$vc){
                        if($vc['goods_price']<$coupon_dec_money){
                            $coupon_dec_money = $vc['goods_price'];
                        }
                    }
            //    }
            }else{
                if($coupon_type==1){
                    $coupon_dec_money = $coupon_money ;
                }else{
                    //$coupon_dec_money = (100-$coupon_money)/100*$price;
                    $coupon_cat_id_arr = explode(",",$coupon_info['category_ids']);
                    if($coupon_type==2){   //折扣券
                        $coupon_inlude_goods_price = 0;
                        if(!empty($coupon_info['category_ids'])){    //优惠券有规定类别
                            foreach ($goodList as $kg=>$vg){
                                if(in_array($vg['cat_id'],$coupon_cat_id_arr)){
                                    $coupon_inlude_goods_price = $coupon_inlude_goods_price + $vg['total_price'];
                                }
                            }
                        }else{          //优惠券适用所有类别
                            $coupon_inlude_goods_price = $price;
                        }
                        $coupon_dec_money = (100-$coupon_money)/100*$coupon_inlude_goods_price;
                    }elseif($coupon_type==3){ //买一赠一券
                        $coupon_inlude_goods_price = 0;
                        if(!empty($coupon_info['category_ids'])){    //优惠券有规定类别
                            foreach ($goodList as $kg=>$vg){
                                if(in_array($vg['cat_id'],$coupon_cat_id_arr)){
                                    if($coupon_inlude_goods_price==0){
                                        $coupon_inlude_goods_price =  $vg['price'];
                                    }else{
                                        if($vg['price']<$coupon_inlude_goods_price){
                                            $coupon_inlude_goods_price = $vg['price'];
                                        }
                                    }
                                }
                            }
                        }else{          //优惠券适用所有类别
                            foreach ($goodList as $kg=>$vg){
                                if($coupon_inlude_goods_price==0){
                                    $coupon_inlude_goods_price =  $vg['price'];
                                }else{
                                    if($vg['price']<$coupon_inlude_goods_price){
                                        $coupon_inlude_goods_price = $vg['price'];
                                    }
                                }
                            }
                        }
                        $coupon_dec_money = $coupon_inlude_goods_price;
                    }
                }
            }

            $init_delivery_status = (($price-$coupon_dec_money)>$init_delivery_fee) ? 1 : 2;
            $delivery_fee = 0;
            $total_price = $price;
            $coupon_money = $coupon_dec_money;
            $total_money = $price-$coupon_dec_money;
            if($type==2){    //配送
                $delivery_fee = $delivery_fee_info['value'];
                if($total_money<$no_fee_info['value']){
                    $total_money = $total_money + $delivery_fee;
                }else{
                    $delivery_fee = 0;
                }

            }
        }else{
            $init_delivery_status = ($price>$init_delivery_fee) ? 1 : 2;
            $delivery_fee = 0;
            $total_price = $price;
            $coupon_money = 0;
            $total_money = $price;
            if($type==2){    //配送
                $delivery_fee = $delivery_fee_info['value'];

                if($total_money<$no_fee_info['value']){
                    $total_money = $total_money + $delivery_fee;
                }else{
                    $delivery_fee = 0;
                }
            }
        }
      //  $this->wlog($delivery_fee);
        if($total_money<0){
            $total_money = 0;
        }
        $info = [
       //     'valid_coupon'=>$valid_coupon_count,
            'init_delivery_fee'=>$init_delivery_fee,
            'init_delivery_status'=>$init_delivery_status,
            'init_delivery_area'=>$init_delivery_area,
            'delivery_fee'=>$delivery_fee,
            'total_price'=>$total_price,
            'coupon_money'=>$coupon_money,
            'total_money'=>$total_money,
        ];

        $respond_arr = [
            'goodList'=>$goodList,
         //   'coupon_list'=>$coupon_arr,
            'address_info'=>$address_response,
            'info'=>$info,
        ];
        $data = [
            'data'=>$respond_arr,
        ];
        $this->success('success', $data);
    }

    //检测用户位置是否在所在店铺的配送范围
    public function checkSendInfo($user_point,$store_id){
        $areaIncludeObj = new AreaInclude();
        $store_info = Db::name("store_sub")->where('store_id','=',$store_id)->field('lnglat_tx')->find();
        $lnglat_tx = unserialize($store_info['lnglat_tx']);
        $is_include = $areaIncludeObj->is_point_in_polygon($user_point,$lnglat_tx);
        return  $is_include;
    }




    //获取购物车商品及价格
    public function getCartList($store_id,$user_id){
        $cart_list = Db::name('cart')
            ->where('user_id','=',$user_id)
            ->where('store_id','=',$store_id)
            ->select();
        $goodList = [];
        $price = 0;
        $item_num = 0;
        foreach($cart_list as $kc=>$vc){
            $item_price = $vc['key_price'] * $vc['goods_num'] ;
            $price = $price + $item_price;
            $item_num = $item_num + $vc['goods_num'];
            $goodList[] = [
                'cart_id'=>$vc['id'],
                'goods_id'=>$vc['goods_id'],
                'name'=>$vc['goods_name'],
                'spec'=>$vc['key'],
                'spec_item'=>$vc['key_name'],
                'num'=>$vc['goods_num'],
                'price'=>$vc['key_price'],
                'item_total_price'=>$item_price,
            ];
        }
        $arr = [
            'goodList'=>$goodList,
            'price'=>$price,
            'item_num'=>$item_num,
        ];
        return $arr;
    }


    //下单接口
    public function order(){
        $data = $this->request->post();
       // $this->wlog($data);
        $OrderHandleObj = new OrderHandle();
        $now = time();
        $sess_key = $data['sess_key'];
        $store_id = $data['store_id'];
        $type = $data['type'];           //配送方式 1:自取 2:配送
        $coupon_id = isset($data['coupon_id']) ? $data['coupon_id']:'' ;
        $way = $data['way'] ?? 1;         //购买方式 1:立即下单 2:预约下单

        if($data['app_time']==0){  //选择了时间,则认为是预约单
            $way = 1;
        }else{
            $way = 2;
        }

        $app_time = isset($data['app_time']) ? strtotime($data['app_time']):$now ; //预约时间
        $mobile = isset($data['mobile']) ? $data['mobile']:'' ; //手机号
        $tips = isset($data['tips']) ? $data['tips']:'' ; //备注
        $address_id = isset($data['address_id']) ? $data['address_id']:'' ; //用户地址id
        $user_info = $this->getTUserInfo($sess_key);
        $cart_info = $this->getCartList($store_id,$user_info['user_id']);
        if($type==2){
            $result_check = $this->checkAddressId($address_id,$store_id);
            if($result_check==2){
                $this->error('该门店离您的位置已超出区域范围，敬请期待新门店的开通');exit;
            }
        }


        if((!empty($user_info['lat']))&&(!empty($user_info['lng']))){
            $distance = 1;
            $areaIncludeObj = new AreaInclude();
            $store_info = Db::name('store_sub')->where('store_id','=',$store_id)->find();
          //  $distance = $areaIncludeObj->distance($user_info['lng'],$user_info['lat'],$store_info['store_lng_tx'],$store_info['store_lat_tx']);
            $distance = $areaIncludeObj->distance($user_info['lat'],$user_info['lng'],$store_info['store_lat_tx'],$store_info['store_lng_tx']);
         //   $distance = intval($distance);
            $this->wlog("--distance----",'ttt.txt');
            $this->wlog($distance,'ttt.txt');
            $this->wlog($user_info['nickname'],'ttt.txt');
            $this->wlog($user_info['lng'],'ttt.txt');
            $this->wlog($user_info['lat'],'ttt.txt');
            $this->wlog($store_info['store_lng_tx'],'ttt.txt');
            $this->wlog($store_info['store_lat_tx'],'ttt.txt');
            $this->wlog("--distance----",'ttt.txt');
            if($distance>15000){
                $this->error('该门店离您的位置已超出区域范围，敬请期待新门店的开通');exit;
            }
        }


        //如果用户选择的地址超出了配送范围,就不予以配送

        //适配,测试一下使用
       /* if($type==2){
            $address_id = 102;
        }*/



        $arg_info = Db::name('store_arg')->select();
        $delivery_fee = $arg_info[1]['value'];
        $no_fee_condition = $arg_info[2]['value'];
        $coupon_dec = 0;
        if($coupon_id>0){
            $coupon_info = Db::name('coupon_list')
                ->alias('l')
                ->join('tp_plat_coupon c','l.cid=c.id','left')
                ->field('l.*,c.name,c.is_new,c.is_rec')
                ->where('l.id','=',$coupon_id)
                ->find();
            $price = $cart_info['price'];



          //  if($user_info['user_id']==265){

                if(intval($coupon_info['money']==0)){
                //    if(($coupon_info['is_new']==1)||($coupon_info['is_rec']==1)) {
                        $coupon_dec = $cart_info['goodList'][0]['price'];
                        foreach ($cart_info['goodList'] as $kc => $vc) {
                            if ($vc['price'] < $coupon_dec) {
                                $coupon_dec = $vc['price'];
                            }
                        }
                //    }
                }else{
                    $coupon_dec = $this->couponDec($price,$coupon_info);  //优惠券减少的金额
                }

       //     }

          //  $coupon_dec = $this->couponDec($price,$coupon_info);  //优惠券减少的金额
        }

        $without_deliver_fee = $cart_info['price'] - $coupon_dec;
        if($type==2){     //配送单
            if(($without_deliver_fee > $no_fee_condition)||($without_deliver_fee == $no_fee_condition)){
                $total_fee = $without_deliver_fee;
                $delivery_fee = 0;
            }else{
                $total_fee = $without_deliver_fee + $delivery_fee;
            }
        }else{          //自提单
            $total_fee = $without_deliver_fee;
            $delivery_fee = 0;
        }


        $consignee = '';
        $address = '';
        $address_num = '';
        $mobile = '';
        $longitude = '';
        $latitude = '';
        $pay_name = "微信小程序支付";
        $goods_price = $cart_info['price'];
        $shipping_price = $delivery_fee;

        if($type==2){
            $address_info = Db::name('user_address')->where('address_id','=',$address_id)->find();
            $consignee = $address_info['consignee'];
            $address = $address_info['address'];
            $address_num = $address_info['address_num'];
            $mobile = $address_info['mobile'];
            $longitude = $address_info['longitude'];
            $latitude = $address_info['latitude'];
        }


        $order_sn = $OrderHandleObj->createOrder("tea");

        $order_num = $this->getOrderNum();

        if($total_fee<0){
            $total_fee = 0;
        }
     //生成订单
        $order_insert = [
            'order_sn'=>$order_sn,
            'order_num'=>$order_num,
            'user_id'=>$user_info['user_id'],
            'type'=>$type,
            'way'=>$way,
            'mobile'=>$mobile,
            'order_status'=>0,
            'pay_status'=>0,
            'consignee'=>$consignee,
            'address'=>$address,
            'address_num'=>$address_num,
            'longitude'=>$longitude,
            'latitude'=>$latitude,
            'pay_name'=>$pay_name,
            'goods_price'=>$goods_price,
            'shipping_price'=>$shipping_price,
            'coupon_price'=>$coupon_dec,
            'coupon_id'=>$coupon_id,
            'total_amount'=>$goods_price,
            'order_amount'=>$total_fee,
            'add_time'=>$now,
            'user_note'=>$tips,
            'app_time'=>$app_time,
            'store_id'=>$store_id,
            'is_comment'=>0,
            'shipping_status'=>0,
        ];
        $arr_isnert_goods_order = [];
        Db::startTrans();
        try{
            $order_id = Db::name('order')->insertGetId($order_insert);

            //生成订单商品列表
            foreach($cart_info['goodList'] as $kog=>$vog){
                $arr_isnert_goods_order[] = [
                    'order_id'=>$order_id,
                    'goods_id'=>$vog['goods_id'],
                    'goods_name'=>$vog['name'],
                    'goods_num'=>$vog['num'],
                    'goods_price'=>$vog['price'],
                    'key'=>$vog['spec'],
                    'key_name'=>$vog['spec_item'],
                    'spec_key'=>$vog['spec'],
                    'spec_key_name'=>$vog['spec_item'],
                    'prom_type'=>0,
                    'is_send' => 0,
                ];
            }
            Db::name('order_goods')->insertAll($arr_isnert_goods_order);

            //优惠券置位已使用
           /* if(!empty($coupon_id)){
                $arr_coupon_update = [
                    'status'=>1,
                    'use_time'=>$now,
                ];
                Db::name('coupon_list')->where('id','=',$coupon_id)->update($arr_coupon_update);
            }*/
            //删除购物车
            $result = Db::name('cart')
                ->where('user_id','=',$user_info['user_id'])
                ->where('store_id','=',$store_id)
                ->delete();

            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $this->error('系统繁忙,请稍候再试');
        }
        $this->wlog($result);
        if($result>0){

            $data = [
                'order_id'=>$order_id,
            ];
            $this->success('success',$data);
        }
    }

    public function checkAddressId($address_id,$store_id){
        $areaIncludeObj = new AreaInclude();
        $store_info = Db::name('store_sub')->where('store_id','=',$store_id)->find();
        $address_info = Db::name('user_address')->where('address_id','=',$address_id)->find();
        $lnglat_tx = unserialize($store_info['lnglat_tx']);
        $user_point = [
            'lng'=>$address_info['longitude'],
            'lat'=>$address_info['latitude'],
        ];
        $is_include = $areaIncludeObj->is_point_in_polygon($user_point,$lnglat_tx);
        return $is_include;
    }


    // 今日订单(已支付)
    public function todayOrderList(){
        $data = $this->request->post();
        $now = time();
        $today_begin = strtotime(date("Y-m-d 00:00:00",$now));
        $today_end = $today_begin + 24*60*60;
        $sess_key = $data['sess_key'];
        $user_info = $this->getGUserInfo($sess_key);

        $order_list = Db::name('order')
            ->where('user_id','=',$user_info['user_id'])
            ->where('pay_status','=',1)
            ->where('pay_time',['>',$today_begin],['<',$today_end],'and')
            ->order('pay_time desc')
            ->select();

        $arr_response = [];
        foreach($order_list as $ko=>$vo){
            $store_arr = Db::name('store_sub')->where('store_id','=',$vo['store_id'])->find();

            $arr_response[$ko]['store_info'] = [
                'store_name'=>$store_arr['store_name'],
                'store_phone'=>$store_arr['store_phone'],
                'lng'=>$store_arr['store_lng_tx'],
                'lat'=>$store_arr['store_lat_tx'],
                'store_address'=>$store_arr['store_address'],
            ];
            $goods_arr = Db::name("order_goods")->where('order_id','=',$vo['order_id'])->select();
            $goods_info = [];

            foreach($goods_arr as $kg=>$vg){
                $goods_spec = Db::name('goods')->where('goods_id','=',$vg['goods_id'])->field('original_img')->find();
                $item_price = $vg['goods_price'] * $vg['goods_num'];
                $goods_info[] = [
                    'goods_name'=>$vg['goods_name'],
                    'key_name'=>$vg['key_name'],
                    'goods_num'=>$vg['goods_num'],
                    'item_price'=>$item_price,
                    'goods_image'=>"https://".$_SERVER['HTTP_HOST'].$goods_spec['original_img'],
                ];
            }

            $arr_response[$ko]['goods_info'] = $goods_info;

            $order_info = [
                'order_id'=>$vo['order_id'],
                'shipping_price'=>$vo['shipping_price'],
                'coupon_price'=>$vo['coupon_price'],
                'goods_price'=>$vo['goods_price'],
                'order_amount'=>$vo['order_amount'],
                'type'=>$vo['type'],
                'way'=>$vo['way'],
                'order_status'=>$vo['order_status'],
                'order_num'=>$vo['order_num'],
                'order_sn'=>$vo['order_sn'],
                'add_time'=>date("m/d H:i",$vo['add_time']),
                'app_time'=>date("m/d H:i",$vo['app_time']),
                'user_name'=> $vo['consignee'],
                'address'=> $vo['address'],
                'address_num'=> $vo['address_num'],
                'mobile'=> $vo['mobile'],
            ];

            $rider_info =[];
            if($vo['order_status']==4){
                //获取配送员信息

                $openicSfObj = new openicSf();

                $rider_info = $openicSfObj->getRiderPosition($vo);

                $order_info['rider_info'] = [
                    'rider_name'=>$rider_info['rider_name'],
                    'rider_phone'=>$rider_info['rider_phone'],
                ];
            }

            $arr_response[$ko]['order_info'] = $order_info;
        }

        $data = [
            'data'=>$arr_response,
        ];
        $this->wlog($order_info);
        $this->success('success',$data);
    }

    // 历史订单(已支付)
    public function historyOrderList(){
        $data = $this->request->post();
        $now = time();
        $today_begin = strtotime(date("Y-m-d 00:00:00",$now));
        $today_end = $today_begin + 24*60*60;
        $sess_key = $data['sess_key'];
        $page = isset($data['page']) ? $data['page'] : 1;
        $page_size = isset($data['page_size']) ? $data['page_size'] : 10;
        $user_info = $this->getGUserInfo($sess_key);

        $count = Db::name('order')
            ->where('user_id','=',$user_info['user_id'])
            ->where('order_status','=',5)
            ->count();
        $order_list = Db::name('order')
            ->where('user_id','=',$user_info['user_id'])
            ->where('order_status','=',5)
            ->order('pay_time desc')
            ->page($page,$page_size)
            ->select();


        $page_info = [
            'cur_page'=>$page,
            'page_size'=>$page_size,
            'total_items'=>$count,
            'total_pages'=>ceil($count/$page_size)
        ];

        $arr_response = [];
        foreach($order_list as $ko=>$vo){
            $store_arr = Db::name('store_sub')->where('store_id','=',$vo['store_id'])->find();
            $arr_response[$ko]['store_info'] = [
                'store_name'=>$store_arr['store_name'],
                'store_phone'=>$store_arr['store_phone'],
                'lng'=>$store_arr['store_lng_tx'],
                'lat'=>$store_arr['store_lat_tx'],
                'store_address'=>$store_arr['store_address'],
            ];
            $goods_arr = Db::name("order_goods")->where('order_id','=',$vo['order_id'])->select();
            $goods_info = [];
            foreach($goods_arr as $kg=>$vg){

                 $goods_spec = Db::name('goods')->where('goods_id','=',$vg['goods_id'])->field('original_img')->find();
                $item_price = $vg['goods_price'] * $vg['goods_num'];
                $goods_info[] = [
                    'goods_name'=>$vg['goods_name'],
                    'key_name'=>$vg['key_name'],
                    'goods_num'=>$vg['goods_num'],
                    'item_price'=>$item_price,
                    'goods_image'=>"https://".$_SERVER['HTTP_HOST'].$goods_spec['original_img'],
                ];
            }
            $arr_response[$ko]['goods_info'] = $goods_info;
            $is_comment = ($vo['is_comment']==1) ? 1 : 2;
            $order_info = [
                'order_id'=>$vo['order_id'],
                'shipping_price'=>$vo['shipping_price'],
                'coupon_price'=>$vo['coupon_price'],
                'goods_price'=>$vo['goods_price'],
                'order_amount'=>$vo['order_amount'],
                'type'=>$vo['type'],
                'way'=>$vo['way'],
                'order_status'=>$vo['order_status'],
                'order_num'=>$vo['order_num'],
                'order_sn'=>$vo['order_sn'],
                'add_time'=>date("m/d H:i",$vo['add_time']),
                'user_name'=> $vo['consignee'],
                'address'=> $vo['address'],
                'address_num'=> $vo['address_num'],
                'mobile'=> $vo['mobile'],
                'is_comment'=> $is_comment,
            ];
            $arr_response[$ko]['order_info'] = $order_info;
        }
        $data = [
            'data'=>$arr_response,
            'page_info'=>$page_info,
        ];
        $this->success('success',$data);
    }

    //排队列表
    public function orderNum(){
        $data = $this->request->post();
        $now = time();
        $today_begin = strtotime(date("Y-m-d 00:00:00",$now));
        $today_end = $today_begin + 24*60*60;
        $sess_key = $data['sess_key'];
        $order_id = $data['order_id'];

        $order_info = Db::name('order')->where('order_id','=',$order_id)->find();
        $pay_time = $order_info['pay_time'];
        //查看今天的,order_num比我小的,还未送达的 ,type 为1的订单
        $order_list = Db::name('order')
            ->where('order_status','between', ['1', '2'])
            ->where('store_id','=',$order_info['store_id'])
            ->where('order_num', '<', $order_info['order_num'])
            ->where('pay_time',['>',$today_begin],['<',$today_end],'and')
            ->order('order_num asc')
            ->select();
        $order_num_list =[];
        if(count($order_list)>0){
            $num = count($order_list);
            foreach($order_list as $ko=>$vo){
                $order_num_list[] = $vo['order_num'];
            }
        }else{
            $num = 0;
        }

        $data = [
            'order_num'=>$order_info['order_num'],
            'num'=>$num,
            'order_num_list'=>$order_num_list,
        ];
        $this->success("success",$data);
    }


    //评价模版
    public function commmentTipTemp(){
        $data = $this->request->post();
        $order_id = $data['order_id'];
        $sess_key = $data['sess_key'];
        $level = $data['level'];
        $temp_list = Db::name('order_comment_tip')
            ->where('level','=',$level)
            ->order('sort desc')
            ->select();

        $respond_arr = [];
        foreach($temp_list as $kt=>$vt){
            $respond_arr[] = [
                'id'=>$vt['id'],
                'name'=>$vt['name'],
            ];
        }
        $data = [
            'data'=>$respond_arr
        ];
        $this->success("success",$data);
    }

    //评价
    public function eva(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'];
        $order_id = $data['order_id'];
        $tip_ids = isset($data['tip_ids']) ? $data['tip_ids'] : '';
        $comment = $data['comment'];
        $level = $data['level'];
        $user_info = $this->getGUserInfo($sess_key);
        $temp_content = '';
        if(!empty($tip_ids)){
            $template_list =  Db::name('order_comment_tip')
                ->where('id','in',$tip_ids)
                ->select();
            foreach($template_list as $kt=>$vt){
                $temp_content = $temp_content.$vt['name'].",";
            }
            $temp_content = mb_substr($temp_content,0,mb_strlen($temp_content)-1);
        }
        $order_info = Db::name('order')->where('order_id','=',$order_id)->find();
        $arr_isnert = [
            'username'=>$user_info['nickname'],
            'content'=>$comment,
            'add_time'=>time(),
            'is_show'=>0,
            'user_id'=>$user_info['user_id'],
            'order_id'=>$order_id,
            'temp_content'=>$temp_content,
            'temp_ids'=>$tip_ids,
            'store_id'=>$order_info['store_id'],
        ];
        Db::name('comment')->insert($arr_isnert);
        //把订单设为已评论
        Db::name('order')->where('order_id','=',$order_id)->update(['is_comment'=>1]);
        $this->success("success");
    }



    //生成codeNum
  /*  public function getOrderNum(){
        $key = date("Ymd_")."order_num";
        $result = $this->redis->get($key);
        if(empty($result)){
            $this->redis->set($key,81001);
        }else{
            $this->redis->inc($key,1);
        }
        return $this->redis->get($key);
    }*/

    //生成codeNum
    public function getOrderNum(){
        $key = date("Y-m-d");
        $result = Db::name('order_num')->where('day','=',$key)->find();
        if(empty($result)){
            $arr = [
                'day'=>$key,
                'num'=>81001,
            ];
            Db::name('order_num')->insert($arr);
        }else{
            Db::name('order_num')->where('day','=',$key)->setInc('num',1);
        }
        $new_num = Db::name('order_num')->where('day','=',$key)->getField('num');
        return $new_num;
    }

    public function couponDec($price,$coupon_info){
        if($coupon_info['type']==1){
            return $coupon_info['money'];
        }else{
          //  return 0;
            return $price*(100-$coupon_info['money'])/100 ;
        }
    }



    public function test111(){
        $openicSfObj = new openicSf();
        $openicSfObj->sfOrder();
    }


    //生成顺丰订单
    public function createSfOrder(){
        $order_id = 47;
        $order_info = Db::name('order')->where('order_id','=',$order_id)->find();
        $order_goods = Db::name('order_goods')->where('order_id','=',$order_id)->select();
        $store_info = Db::name('store_sub')->where('store_id','=',$order_info['store_id'])->find();

        $openicSfObj = new openicSf();
        $result = $openicSfObj->createSfOrder($order_info,$order_goods,$store_info);
        if(!empty($result)){
            $arr  = [
                'shipping_code'=>$result['sf_order_id'],
                'shipping_push_time'=>$result['push_time'],
                'shipping_sf_price'=>$result['total_price'],
                'shipping_name'=>"顺丰配送",
            ];
            Db::name('order')->where('order_id','=',$order_id)->update($arr);
        }
    }



    //顺丰状态改变
    public function changeSendstatus(){
        $data = $this->request->post();

        $order_sn = $data['shop_order_id'];
        $order_info = Db::name('order')->where('order_sn','=',$order_sn)->find();
        $this->wlog($data);
        $check_ship_insert = Db::name('shipping_status')
            ->where('order_id','=',$order_info['order_id'])
            ->where('order_status','=',$data['order_status'])
            ->find();
        $this->wlog($check_ship_insert);
        if(empty($check_ship_insert)){
            $arr = [
                'order_id'=>$order_info['order_id'],
                'sf_order_id'=>$data['sf_order_id'],
                'url_index'=>$data['url_index'],
                'operator_name'=>$data['operator_name'],
                'operator_phone'=>$data['operator_phone'],
                'rider_lng'=>$data['rider_lng'],
                'rider_lat'=>$data['rider_lat'],
                'push_time'=>$data['push_time'],
                'status_desc'=>$data['status_desc'],
                'order_status'=>$data['order_status'],
            ];
            $this->wlog($arr);
            $result = Db::name('shipping_status')->insert($arr);
            $this->wlog($result);
            if(!empty($result)){
                $result_response = [
                    'error_code'=>0,
                    'error_msg'=>'success',
                ];
            }
            if($data['order_status']==10){   //如果指派了
                $noticeObj = new NoticeHandle();
                $noticeObj->sendModel($order_info['order_id'],4);
            }
        }else{
            $result_response = [
                'error_code'=>0,
                'error_msg'=>'success',
            ];
        }




        $this->wlog($result_response);
        echo json_encode($result_response);
    }


    public function sendComplete(){
        $data = $this->request->post();
        $order_sn = $data['shop_order_id'];
        $order_info = Db::name('order')->where('order_sn','=',$order_sn)->find();
        $this->wlog($data);
        $check_ship_insert = Db::name('shipping_status')
            ->where('order_id','=',$order_info['order_id'])
            ->where('order_status','=',$data['order_status'])
            ->find();
     //   $this->wlog($check_ship_insert);
        if(empty($check_ship_insert)){
            $arr = [
                'order_id'=>$order_info['order_id'],
                'sf_order_id'=>$data['sf_order_id'],
                'url_index'=>$data['url_index'],
                'operator_name'=>$data['operator_name'],
                'operator_phone'=>$data['operator_phone'],
                'rider_lng'=>$data['rider_lng'],
                'rider_lat'=>$data['rider_lat'],
                'push_time'=>$data['push_time'],
                'status_desc'=>$data['status_desc'],
                'order_status'=>$data['order_status'],
            ];
         //   $this->wlog($arr);
            $result = Db::name('shipping_status')->insert($arr);
            $this->wlog($result);
            if(!empty($result)){
                $arr_order_update = [
                    'order_status'=>5,
                    'finish_time'=>time(),
                ];
                //订单设置为已完成
                Db::name('order')
                    ->where('order_id','=',$order_info['order_id'])
                    ->update($arr_order_update);
                $result_response = [
                    'error_code'=>0,
                    'error_msg'=>'success',
                ];
            }
        }else{
            $result_response = [
                'error_code'=>0,
                'error_msg'=>'success',
            ];
        }
        echo  json_encode($result_response);


    }


    public function sendCancel(){

        $data = $this->request->post();
        $order_sn = $data['shop_order_id'];
        $order_info = Db::name('order')->where('order_sn','=',$order_sn)->find();
        $this->wlog($data);
        $check_ship_insert = Db::name('shipping_status')
            ->where('order_id','=',$order_info['order_id'])
            ->where('order_status','=',$data['order_status'])
            ->find();
        $this->wlog($check_ship_insert);
        if(empty($check_ship_insert)){
            $arr = [
                'order_id'=>$order_info['order_id'],
                'sf_order_id'=>$data['sf_order_id'],
                'url_index'=>$data['url_index'],
                'operator_name'=>$data['operator_name'],
                'operator_phone'=>$data['operator_phone'],
                'rider_lng'=>$data['rider_lng'],
                'rider_lat'=>$data['rider_lat'],
                'push_time'=>$data['push_time'],
                'status_desc'=>$data['status_desc'],
                'order_status'=>$data['order_status'],
            ];
            $this->wlog($arr);
            $result = Db::name('shipping_status')->insert($arr);
            $this->wlog($result);
            if(!empty($result)){
                $arr_order_update = [
                    'order_status'=>7,
                    'cancel_time'=>time(),
                ];
                //订单设置为已完成
                Db::name('order')
                    ->where('order_id','=',$order_info['order_id'])
                    ->update($arr_order_update);
                $result_response = [
                    'error_code'=>0,
                    'error_msg'=>'success',
                ];
            }
        }else{
            $result_response = [
                'error_code'=>0,
                'error_msg'=>'success',
            ];
        }
        echo json_encode($result_response);
    }

    //获取配送员轨迹
    public function getRiderH5($order_id){
        $order_info = Db::name('order')->where('order_id','=',$order_id)->find();
        $openicSfObj = new openicSf();
        $result = $openicSfObj->getRiderPosition($order_info);

        if(!empty($result)){
            $url = preg_replace('/http/','https',$result['url']);
            $result_response = [
                'url'=>$url,
            ];
        }else{
            $result_response = null;
        }
        $data = [
            'data'=>$result_response,
        ];
        $this->success("success",$result_response);
    }

    //查看某个店铺是否有新订单
    // 预约自取订单预约前10分钟提示语音三次自动取消
    //   预约配送单40分钟之前提示语音三次自动取消
    public function hasNewOrder1(){

        $data = $this->request->request();
        $now = time();
        $min_time = $now -45;
        $store_id = $data['store_id'];
        $new_order_info = Db::name('order')
            ->where('store_id','=',$store_id)
            ->where('order_status','=',1)
            ->find();

        $has_new_order = empty($new_order_info) ? 2 : 1;
        $has_app_self_order = 2;  //预约自取单
        $has_app_send_order = 2;    //预约配送单

        $app_self_order_num = 0;  //预约自取单
        $app_send_order_num = 0;    //预约配送单

       // $map['order_status'] = array('between','1,2');
        $new_order_list = Db::name('order')
            ->where('store_id','=',$store_id)
            ->order('order_id asc')
            ->select();
        if(!empty($new_order_list)){

            foreach($new_order_list as $kn=>$vn){
                if($vn['way']==2){
                    if($vn['type']==1){   //自取
                        $set_time = $vn['app_time'] - 60*15;
                        if(($set_time>$min_time)&&($set_time<$now)){
                            $has_app_self_order = 1;
                            if($app_self_order_num==0){
                                $app_self_order_num = $vn['order_num'];
                            }
                        }
                    }
                    if($vn['type']==2){
                        $set_time = $vn['app_time'] - 60*45;
                        if(($set_time>$min_time)&&($set_time<$now)){
                            $has_app_send_order = 1;
                            if($app_send_order_num==0){
                                $app_send_order_num = $vn['order_num'];
                            }
                        }
                    }
                }
            }
        }
        $data = [
            'has_new_order'=>$has_new_order,
            'has_app_self_order'=>$has_app_self_order,
            'has_app_send_order'=>$has_app_send_order,
            'app_send_order_num'=>$app_send_order_num,
            'app_self_order_num'=>$app_self_order_num,
        ];


   /*     $data = [
            'has_new_order'=>2,
            'has_app_self_order'=>1,
            'has_app_send_order'=>1,
            'app_send_order_num'=>81001,
            'app_self_order_num'=>81002,
        ];*/

        $this->success("success",$data);
    }


    //获取骑手定位
    public function getRiderPosition(){
        $data = $this->request->post();
        $order_id = $data['order_id'];
        $order_info = Db::name('order')->where('order_id','=',$order_id)->find();
        $openicSfObj = new openicSf();
        $result = $openicSfObj->getRiderPosition($order_info);
        $this->wlog($result);
        $user = [
            'user_lng'=>$order_info['longitude'],
            'user_lat'=>$order_info['latitude'],
        ];
        $data = [
            'rider'=>$result,
            'user'=>$user,
            'expect_time'=>date("H:i",$order_info['sf_expect_time']),
        ];

       /**********test************/
      /*  $result = [
            'rider_name'=>'测试',
            'rider_phone'=>'13285214785',
            'rider_lng'=>'120.2635700',
            'rider_lat'=>'30.1843400',
        ];
        $user = [
            'user_lng'=>$order_info['longitude'],
            'user_lat'=>$order_info['latitude'],
        ];
        $data = [
            'rider'=>$result,
            'user'=>$user,
            'expect_time'=>date("H:i",$order_info['sf_expect_time']),
        ];*/

        $this->success("success",$data);
    }



}
