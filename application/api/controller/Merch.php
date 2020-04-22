<?php

namespace app\api\controller;



use app\common\controller\Api;
use app\common\entity\MerchCashLogE;
use app\common\entity\OrderE;
use app\common\library\wx\WXBizDataCrypt;
use app\common\repository\OrderRepository;
use app\common\repository\StoreRepository;
use app\common\repository\UserRepository;
use fast\Http;
use think\cache\driver\Redis;
use think\Db;
use think\Request;
use think\Session;
use think\Cache;
use app\common\util\OssUtils;
/**
 * 工作相关接口
 */
class Merch extends Api
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
     * @var UserRepository;
     */
    private $userRepository;

    /**
     * @var OrderRepository
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

    /**
     * 商家授权
     */
    public function auth()
    {

        $data = $this->request->post();
        $code = $data['code'];

        $mini_config_url = config('mini.url');
        $appid = config('Wxpay.APPID');
        $app_secret = config('Wxpay.APPSECRET');
        $login_url = $mini_config_url['wx_login']."?appid={$appid}&secret={$app_secret}&js_code={$code}&grant_type=authorization_code";

  //      $this->wlog($login_url);
        $result_json = Http::get($login_url);
        $result = json_decode($result_json,true);
        if(IS_TEST){
            $result = [
                'openid'=>'oUQcI0bzIh2RXXaD5eN11QNnd9uo2',
                'session_key'=>'123123',
            ];
        }

        if(!empty($result['openid'])&&(!empty($result['session_key']))){
            $user_data = [
                'openid'=>$result['openid'],
                'session_key'=>$result['session_key'],
            ];
            $this->cacheUser($user_data);
            $auth_code = $this->signUserJwtToken($user_data);     //获取token
            $user_info = $this->registerUser($result['openid']);            //插入用户openid
            $data = ['auth_code'=>$auth_code,'merch_login'=>$user_info['merch_login'],'store_id'=>$user_info['store_id']];
            $bizobj = ['data'=>$data];
            $this->success('成功', $bizobj);
        }else{
            $this->error('没有获取到数据');
        }

    }

    //注册用户
    public function registerUser($openid){
        $check_user = Db::name('users')->where('openid','=',$openid)->find();
        if(empty($check_user)){
            $data = [
                'openid'=>$openid,
                'merch_login'=>$check_user['merch_login'],
                'store_id'=>$check_user['store_id'],
            ];
            Db::name('users')->insert($data);
            $check_user = $data;
        }
        return $check_user;
    }


    /**
     * 商家登录
     */
    public function login(){
        $data = $this->request->post();
        $openid = $this->analysisUserJwtToken();
        $user_info = $this->getGUserInfo($openid);

        $seller_name = I('post.username');
        $password = I('post.password');
        $seller = M('seller_sub')->where(array('seller_name' => $seller_name))->find();
        if ($seller) {
            $user_where = array(
                'store_id' => $seller['store_id'],
                'password' => encrypt($password),
            );
            $user = M('store_sub')->where($user_where)->find();

            if ($user) {
                M('seller_sub')->where(array('seller_id' => $seller['seller_id']))->save(array('last_login_time' => time()));
                //绑定商家用户id
                $this->userRepository->updateUserByFilter(['store_id'=>$seller['store_id'],'merch_login'=>1],['user_id'=>$user_info['user_id']]);
                $user = [
                    'store_id'=>$seller['store_id'],
                    'merch_login'=>1,
                ];
                $this->success('登录成功',$user);

            } else {
                $this->error('账号密码不正确或检查账号是否有效成功');
            }
        } else {
            $this->error('账号不存在');
        }
    }

    //规定时间的商家营业统计
    public function staticsByTime(){
        $data = $this->request->post();
        $openid = $this->analysisUserJwtToken();
        $user_info = $this->getGUserInfo($openid);
        $store_id = $user_info['store_id'];
        $now = time();

        $start_time = isset($data['start_time']) ? strtotime($data['start_time']) : 0;
        $end_time = isset($data['end_time']) ? strtotime($data['end_time']) : $now;
        $is_today = isset($data['is_today']) ? $data['$is_today'] : 0;
        if($is_today){
            $start_time = strtotime(date("Y-m-d"))-1;
            $end_time = $now+1;
        }
        $total_money = $this->orderRepository->getTotalPaidMoneyByTime($store_id,$start_time,$end_time);
        $total_num = $this->orderRepository->getTotalOrderByTime($store_id,$start_time,$end_time);
        $data = [
            'total_money'=>$total_money,
            'total_num'=>$total_num,
        ];
        $this->success('success',$data);
    }

    //商家的总收入,可提现金额,待确认金额
    //todo 还没做
    public function staticsSummary(){
        $data = $this->request->post();
        $openid = $this->analysisUserJwtToken();
        $user_info = $this->getGUserInfo($openid);
        $store_id = $user_info['store_id'];
        $now = time();
        $seller_info = $this->userRepository->getSellerinfo($store_id);
        $data = [
            'total_money'=>$seller_info['total_money'],
            'available_money'=>$seller_info['merch_money'],
            'frozen_money'=>$seller_info['frozen_money'],
        ];
        $this->success('success',$data);
    }


    //规定时间的商家收支列表
    public function cashListByTime(){
        $data = $this->request->post();
        $openid = $this->analysisUserJwtToken();
        $user_info = $this->getGUserInfo($openid);
        $store_id = $user_info['store_id'];

        $now = time();

        $start_time = isset($data['start_time']) ? strtotime($data['start_time']) : 0;
        $end_time = isset($data['end_time']) ? strtotime($data['end_time']) : $now;
        $is_today = isset($data['is_today']) ? $data['$is_today'] : 0;
        if($is_today){
            $start_time = strtotime(date("Y-m-d"))-1;
            $end_time = $now+1;
        }
        $cash_list = $this->orderRepository->getMerchCashLogByTime($store_id,$start_time,$end_time);
        $cash_list = array_map(function($cash){
            $fuhao = ($cash['way']==1) ? "+" : "-";
            $cash_res = [
                'status_ch'=>MerchCashLogE::STATUS_CH[$cash['status']],
                'tip'=>$cash['tip'],
                'time'=>date("Y-m-d H:i",$cash['update_time']),
                'order_no'=>$cash['order_no'],
                'cash' => $fuhao.$cash['cash'],
            ];
            return $cash_res;
        },$cash_list);
        $data = [
            'cahs_list'=>$cash_list,
        ];
        $this->success('success',$data);
    }

    //规定时间的商家收支列表
    public function orderListByTime(){
        $data = $this->request->post();
        $openid = $this->analysisUserJwtToken();
        $user_info = $this->getGUserInfo($openid);
        $store_id = $user_info['store_id'];
        $status = isset($data['status']) ? $data['status']:'-1';
        $search_data = isset($data['search_data']) ? $data['search_data'] : '';
        $now = time();
        $start_time = isset($data['start_time']) ? strtotime($data['start_time']) : '';
        $end_time = isset($data['end_time']) ? strtotime($data['end_time']) : '';
        $is_today = isset($data['is_today']) ? $data['$is_today'] : 0;
        if($is_today){
            $start_time = strtotime(date("Y-m-d"))-1;
            $end_time = $now+1;
        }

        $order_list = $this->orderRepository->getMerchOrderListFilter($store_id,$start_time,$end_time,$status,$search_data);
        $now_date = date("Y-m-d");
        $order_list = array_map(function($order) use($now_date){
            if(!$order['app_time']){
                $app_time = "立刻到店";
            }else{
                $app_date = date("Y-m-d",$order['app_time']);
                $check_is_tomorrow = ($now_date==$app_date) ? '' : "(明天)";
                $app_time = date("Y-m-d ".$check_is_tomorrow." H:i",$order['app_time']);
            }
            $fuhao = ($order['way']==1) ? "+" : "-";
            $order_res = [
                'order_id'=>$order['order_id'],
                'consignee'=>$order['consignee'],
                'total_price'=>$order['order_amount'],
                'package_fee'=>$order['package_fee'],
                'way'=>$order['way'],
                'order_status'=>$order['order_status'],
                'order_status_tip'=>OrderE::ORDER_STATUS_TIP[$order['order_status']],
                'order_num'=>$order['order_num'],
                'order_sn'=>$order['order_sn'],
                'add_time'=>date("Y-m-d H:i",$order['add_time']),
                'app_time'=>$app_time,
                'user_name'=> $order['consignee'],
                'mobile'=> $order['mobile'],
                'tips' => $order['tips'],
            ];
            return $order_res;
        },$order_list);
        $data = [
            'order_list'=>$order_list,
        ];
        $this->success('success',$data);
    }

    // 改变订单状态
    public function changeOrderStatus(){
        $data = $this->request->post();
        $now = time();
        $openid = $this->analysisUserJwtToken();
        $user_info = $this->getGUserInfo($openid);
        $order_id = $data['order_id'];
        $status = $data['status'];
        //todo  判断该商家是否有修改权限
        $order_detail = Db::name('order')
            ->where('user_id','=',$user_info['user_id'])
            ->where('pay_status','=',1)
            ->where('order_id','=',$order_id)
            ->find();

        switch ($status){
            case OrderE::ORDER_STATUS['DONE_BACK']:  //同意取消
                $this->orderRepository->changeOrderStatus($order_detail,$status);
                $order_status = OrderE::ORDER_STATUS['DONE_BACK'];
                break;
            case OrderE::ORDER_STATUS['UNDONE_BACK']:  //拒绝取消订单
                $this->orderRepository->changeOrderStatus($order_detail,$status);
                $order_status = OrderE::ORDER_STATUS['UNDONE_BACK'];
                break;
            case OrderE::ORDER_STATUS['TAKE']:  //商家接单
                $this->orderRepository->changeOrderStatus($order_detail,$status);
                $order_status = OrderE::ORDER_STATUS['TAKE'];
                break;
            case OrderE::ORDER_STATUS['DONE']:  //订单已完成
                $this->orderRepository->changeOrderStatus($order_detail,$status);
                $order_status = OrderE::ORDER_STATUS['DONE'];
                break;
        }

        $arr_response = [];
        $data = [
            'status'=>$order_status,
            'status_tip'=>OrderE::ORDER_STATUS_TIP[$order_status],
        ];
        $this->success('success',$data);
    }

    /**
     * 改变门店状态
     */
    public function changeStoreState()
    {
        $data = $this->request->post();
        $now = time();
        $openid = $this->analysisUserJwtToken();
        $store_id = $data['store_id'];
        $user_info = $this->getGUserInfo($openid);
        $store_info = Db::name('store_sub')
            ->where('store_id','=',$store_id)
            ->find();
        $time_state = $this->getStoreStateByTime($store_info);

        $state = $data['status'];
        if($time_state==0){
            if($state==1){
                $this->error('现在是打烊时间，修改失败');
            }
        }
        $this->storeRepository->changeStoreState($store_id,$state);
        $this->success('修改完成');
    }

    //获取状态
    public function getStoreStateByTime($store_info){
        $now = time();
        $store_begin_time = strtotime($store_info['store_time']);
        $store_end_time = strtotime($store_info['store_end_time']);
        if(($now>$store_begin_time)&&($now<$store_end_time)){
            return 1;
        }else{
            return 0;
        }
    }

    /**
     * 改变门店状态
     */
    public function changePassword()
    {
        $data = $this->request->post();
        $old_passwd = $data['old_passwd'];
        $new_passwd = $data['new_passwd'];

        $store_id = $data['store_id'];

        $seller = M('seller_sub')->where('store_id' ,'=', $store_id)->find();

        if ($seller) {
            $user_where = array(
                'store_id' => $seller['store_id'],
                'password' => encrypt($old_passwd),
            );
            $user = M('store_sub')->where($user_where)->find();
            if ($user) {
                $new_pass = encrypt($new_passwd);
                M('store_sub')->where('store_id','=',$store_id)->update(['password'=>$new_pass]);
                $this->success('修改成功');

            } else {
                $this->error('原密码不正确');
            }
        } else {
            $this->error('店铺不存在');
        }

    }

    //店铺详情
    public function storeInfo(){
        $data = $this->request->post();
        $openid = $this->analysisUserJwtToken();
        $user_info = $this->getTUserInfo($openid);
        $store_id =  $data['store_id'];
        $OssUtilsObj = new OssUtils();
        $store_info = Db::name('store_sub')
            ->where('is_shenhe','=',1)
            ->where('store_id','=',$store_id)
            ->find();
        $image_zhizhao = explode(",",$store_info['image2']);
        $image_zhizhao =array_map(function($image){
            return "https://".$_SERVER['HTTP_HOST'].$image;
        },$image_zhizhao);
        $pic = $OssUtilsObj->getImage($store_info['image'],$store_info['image_oss']);
        $arr = [
            'store_id'=>$store_info['store_id'],
            'name'=>$store_info['store_name'],
            'pic'=>$pic,
            'notice'=>$store_info['notice'],
            'store_description'=>$store_info['store_description'],
            'meituan_grade'=>$store_info['meituan_grade'],
            'month_sale'=>$store_info['month_sale'],
            'meituan_grade'=>$store_info['meituan_grade'],
            'image_zhizhao'=>$image_zhizhao,
            'type_name'=>$store_info['type_name'],
            'store_state'=>$this->getStoreState($store_info),
            'mobile'=>$store_info['store_phone'],
            'address'=>$store_info['store_address'],
            'lng'=>$store_info['store_lng_tx'],
            'lat'=>$store_info['store_lat_tx'],
            'store_time'=>$store_info['store_time'],
            'store_end_time'=>$store_info['store_end_time'],
            'store_status'=>$store_info['store_state'],
        ];
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
        if(($now>$store_begin_time)&&($now<$store_end_time)&&($store_info['state']==1)){
            return 1;
        }else{
            return 0;
        }
    }

    //商家提现
    public function withdraw(){
        $data = $this->request->post();
        $openid = $this->analysisUserJwtToken();
        $user_info = $this->getTUserInfo($openid);
        $store_id =  $data['store_id'];
        $cash = $data['cash'];
        $merch_info = $this->userRepository->getMerchMoneyByUser($user_info);

        if(empty($store_id)){
            $this->error('系统繁忙，请稍后再试1');
        }
        if($user_info['store_id']!=$store_id){
            $this->error('系统繁忙，请稍后再试2');
        }
        if($cash>$merch_info['merch_money']){
            $this->error('可提现金额不足');
        }
        //给商家减钱
        $this->userRepository->withMerchMoney($user_info,$cash);
        //给商家增加提现记录
        $this->userRepository->addMerchWithdrawLog($user_info,$cash);
        $this->success("申请成功");
    }

    /**
     * sh商家扫码
     */
    public function scanOrder(){
        $data = $this->request->post();
        $openid = $this->analysisUserJwtToken();
        $user_info = $this->getTUserInfo($openid);
        $order_sn =  $data['order_sn'];


        $order_detail = Db::name('order')
            ->where('order_sn','=',$order_sn)
            ->find();
        if($order_detail['pay_status']!=1){
            $this->success('该订单尚未支付');
        }
        $arr_response = [];
        $OssUtilsObj = new OssUtils();

        $store_arr = Db::name('store_sub')->where('store_id','=',$order_detail['store_id'])->find();
        $pic = $OssUtilsObj->getImage($store_arr['image'],$store_arr['image_oss']);

        $arr_response['store_info'] = [
            'store_id'=>$store_arr['store_id'],
            'store_name'=>$store_arr['store_name'],
            'pic'=>$pic,
            'store_phone'=>$store_arr['store_phone'],
            'lng'=>$store_arr['store_lng_tx'],
            'lat'=>$store_arr['store_lat_tx'],
            'store_address'=>$store_arr['store_address'],
        ];
        $goods_arr = Db::name("order_goods")->where('order_id','=',$order_detail['order_id'])->select();
        $goods_info = [];
        $total_goods_num= 0;
        foreach($goods_arr as $kg=>$vg){
            $total_goods_num += $vg['goods_num'];
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

        $arr_response['goods_info'] = $goods_info;
        $now_date = date("Y-m-d");
        if(!$order_detail['app_time']){
            $app_time = "立刻到店";
        }else{
            $app_date = date("Y-m-d",$order_detail['app_time']);
            $check_is_tomorrow = ($now_date==$app_date) ? '' : "(明天)";
            $app_time = date("Y-m-d ".$check_is_tomorrow." H:i",$order_detail['app_time']);
        }

        if($total_goods_num==1){
            $description = $goods_arr[0]['goods_name']." 一件商品";
        }else{
            $description = $goods_arr[0]['goods_name']." 等".$total_goods_num."件商品";
        }

        $order_info = [
            'order_id'=>$order_detail['order_id'],
            'total_price'=>$order_detail['order_amount'],
            'package_fee'=>$order_detail['package_fee'],
            'way'=>$order_detail['way'],
            'order_status'=>$order_detail['order_status'],
            'order_status_tip'=>OrderE::ORDER_STATUS_TIP[$order_detail['order_status']],
            'order_num'=>$order_detail['order_num'],
            'order_sn'=>$order_detail['order_sn'],
            'add_time'=>date("Y-m-d H:i",$order_detail['add_time']),
            'app_time'=>$app_time,
            'user_name'=> $order_detail['consignee'],
            'mobile'=> $order_detail['mobile'],
            'tips' => $order_detail['tips'],
            'total_goods_num'=>$total_goods_num,

        ];

        $arr_response['order_info'] = $order_info;
        $data = [
            'data'=>$arr_response,
        ];
        //$this->wlog($order_info);
        $this->success('success',$data);

    }




}
