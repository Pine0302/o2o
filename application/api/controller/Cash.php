<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\entity\OrderE;
use app\common\library\wx\WXBizDataCrypt;
use app\common\repository\OrderRepository;
use app\common\repository\StoreRepository;
use app\common\repository\UserRepository;
use app\common\util\OssUtils;
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
class Cash extends Api
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
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var OrderRepository;
     */
    private $orderRepository;

    /**
     * @var StoreRepository
     */
    private $storeRepository;

    public function __construct(Request $request = null,UserRepository $userRepository,OrderRepository $orderRepository,StoreRepository $storeRepository)
    {
        parent::__construct($request);
        $this->userRepository = $userRepository;
        $this->orderRepository = $orderRepository;
        $this->storeRepository = $storeRepository;
    }


    //充值列表
    public function chargeList(){
        $charge_list = config('webset.chage_money');
        $data = [
            'charge_list'=>$charge_list,
        ];
        $this->success('success',$data);
    }

    //充值
    public function addCash(){
        $data = $this->request->post();
       // $this->wlog($data);
        $OrderHandleObj = new OrderHandle();
        $now = time();
        $openid = $this->analysisUserJwtToken();
        $charge_id = $data['charge_id'];

        $user_info = $this->getTUserInfo($openid);

        $order_sn = $OrderHandleObj->createOrder("cre");
        $charge_list = config('webset.chage_money');

        $charge_list_filter = array_filter($charge_list,function($item) use ($charge_id){
            if($item['id']==$charge_id){
                return true;
            }else{
                return false;
            }
        });
        $charge_detail = $charge_list_filter[1];
        exit;
     //生成订单
        $order_insert = [
            'order_sn'=>$order_sn,
            'user_id'=>$user_info['user_id'],
            'type'=>$type,
            'mobile'=>$mobile,
            'order_status'=>0,
            'pay_status'=>0,
            'consignee'=>$consignee,
            'pay_type'=>$pay_type,
            'pay_name'=>$pay_name,
            'goods_price'=>$goods_price,
            'package_fee'=>$package_fee,
            'total_amount'=>$total_price,
            'order_amount'=>$total_price,
            'package_fee'=>$package_fee,
            'add_time'=>$now,
            'user_note'=>$tips,
            'app_time'=>$app_time,
            'way'=>$way,
            'store_id'=>$store_id,
            'is_comment'=>0,
            'shipping_status'=>0,
        ];

        $arr_isnert_goods_order = [];
        $result = 0;
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
                    /*'spec_key'=>$vog['spec'],
                    'spec_key_name'=>$vog['spec_item'],*/
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
       // $this->wlog($result);
        if($result>0){
            $data = [
                'order_id'=>$order_id,
            ];
            $this->success('success',$data);
        }
    }













}
