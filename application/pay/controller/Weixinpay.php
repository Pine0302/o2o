<?php
namespace app\pay\controller;


use app\common\entity\CashOrderE;
use app\common\entity\MemberCashLogE;
use app\common\entity\MerchCashLogE;
use app\common\entity\OrderE;
use think\Request;
use weixinpay\Weixinpay as WeixinpayClass;
use think\Controller;
use think\Model;
use think\Db;
use app\common\library\CommonFunc;
use app\common\library\OrderHandle;
use app\api\library\NoticeHandle;

use app\common\repository\OrderRepository;
use app\common\repository\StoreRepository;
use app\common\repository\UserRepository;

class Weixinpay extends Controller
{

    public function index(){
        echo 12322;exit;
    }


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
    /**
    * notify_url接收页面
    */
    public function notify()
    {
        error_log(var_export(123123,1),3,"/opt/app-root/src/public/log/test.txt");
        // 获取xml
        $xml=file_get_contents('php://input', 'r');
        //转成php数组 禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $arr = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $arr = json_decode(json_encode($arr),true);
        error_log(var_export($arr,1),3,"/opt/app-root/src/public/log/test.txt");
        error_log("back_success",3,"/opt/app-root/src/public/log/test.txt");

        if(($arr['result_code']=='SUCCESS')&&($arr['return_code']=='SUCCESS')){
            $this->afterpay($arr);
          //  WeixinpayClass::notify();
        }

    }

    /**
     * 公众号支付 必须以get形式传递 out_trade_no 参数
     */
    public function pay(WeixinpayClass $wxpay)
    {
        // 获取jssdk需要用到的数据
        $data = $wxpay->getParameters();
        // 将数据分配到前台页面
        return $this->fetch('', [
           'data'=>json_encode($data)
        ]);
    }

    /**
     * 微信 公众号jssdk支付 生成订单号后去调取支付
     */
    public function wexinpay_js()
    {
        // 此处根据实际业务情况生成订单 然后拿着订单去支付
        // 用时间戳虚拟一个订单号  （请根据实际业务更改）
        $out_trade_no = time();
        // 组合url
        $url = url('pay/weixinpay/pay',['out_trade_no'=>$out_trade_no]);
        // 前往支付
        $this->redirect($url);
    }

    /**
     * 微信二维码支付
     * body(产品描述)、total_fee(订单金额)、out_trade_no(订单号)、product_id(产品id)
     */
    public function qr_pay()
    {
        //todo 调用beforepay 做验证
        $req_info = base64_decode($_REQUEST['req']);
        $req_arr = (\GuzzleHttp\json_decode($req_info,true));
        $order = [
            'body'=>'商家向平台充值订单',
            'total_fee'=>$req_arr['cash'] * 100,
            'out_trade_no'=>strval($req_arr['order_id']),
            'product_id'=>$req_arr['id']
        ];
        $img = weixinpay($order);
        $imgurl = "https://".$_SERVER['HTTP_HOST'].$img;
        $order_id = $req_arr['order_id'];
        echo <<<EOT
            <html>
            <head>
            <meta http-equiv="Content-Type" content="text/html; charset=gb2312" />
            <title>Untitled Document</title>
            <style>
            .main{
                text-align: center; /*让div内部文字居中*/
                background-color: #fff;
                border-radius: 20px;
                width: 300px;
                height: 350px;
                margin: auto;
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
            }
            </style>
            </head>
            <body>
            
                <div class="main">
                    <h1>请扫下方二维码支付</h1>
                    <img src={$imgurl}></img>
                </div>
                
            </body>
            
            </html>

<input type="hidden"  id="order_id"  value="{$order_id}">
<script src= "https://code.jquery.com/jquery-latest.js" ></script>
<script type="text/javascript">
function pay_status(){
   var order_id = $("#order_id").val();
   $.ajax({  
    url:'https://' + window.location.host+ '/pay/weixinpay/checkPay',
    dataType:'json', 
    type:'post',  
    data:{'order_id':order_id}, 
    success:function(data){  
      if(data == '3' ){
        window.clearInterval(int); //销毁定时器
        var html="<h1>支付已完成,正在跳转到账户管理页面!</h1>";
        $('.main').html(html);
        setTimeout(function(){
          //跳转到结果页面，并传递状态
          //location.reload();
          window.location.href="https://"+window.location.host+"/admin/re/compaccountdetail";
        },3000)
         
      }else if(data =='2'){
         var html="<h1>正在支付中...</h1>";
        $('.main').html(html);
    //    window.clearInterval(int); //销毁定时器
     /*   setTimeout(function(){
          //跳转到结果页面，并传递状态
           location.reload();
          window.location.href="http://" rel="external nofollow" rel="external nofollow" +window.location.host+"/home/cart/pay_result?pay_status=fail";
        },1000)*/
      }else{
       /* var html="<h1>支付已提交,请刷新页面重试...</h1>";
         $('.main').html(html);
         window.clearInterval(int);*/
      }
    }, 
    error:function(){  
      alert("error");
       
    },  
 
 });
}
//启动定时器
var int=self.setInterval(function(){pay_status()},2000);
</script>

EOT;

    }

    public function before(){

    }

    public function checkPay(){
        $copmp_detail_model = model('ReCompaccountdetail');
       // $order_id = 'C2P_5B03876B727E8';

        $info = $_REQUEST;
        $order_id = $info['order_id'];
        $order_info = $copmp_detail_model->get(['order_id' => $order_id]);
        echo json_encode($order_info->status);
    }

    public function testAfterPay()
    {
        $arr =  ["appid"=>"wxd98c6a52dab64ef7","bank_type"=>"CFT",
            "cash_fee"=>"1","fee_type"=>"CNY","is_subscribe"=>"Y",
            "mch_id"=>"1486122612","nonce_str"=>"CmES",
            "openid"=>"oUTUPwt3kDmbu8f0EJZROuqPX5Zc",
            "out_trade_no"=>"O2O_5E94702A6C712",
            "result_code"=>"SUCCESS","return_code"=>"SUCCESS",
            "sign"=>"2B36C19BD4C1196F1DE939968D7588A0","time_end"=>"20180522162705",
            "total_fee"=>"1","trade_type"=>"NATIVE","transaction_id"=>"4200000145201805223882998629"];
        $this->afterpay($arr);
    }


    public function afterpay($arr){
            $order_id = $arr['out_trade_no'];
            $order_id_arr = explode('_',$order_id);
            switch($order_id_arr['0']){

                case 'TEA':
                    $this->afterpaytea($order_id,$arr);
                    break;
                case 'B2P':
                    $this->afterpayb2p($order_id,$arr);
                    break;
                case 'TRAIN':
                    $this->afterPayTrain($order_id,$arr);
                    break;
                case 'O2O':
                    $this->afterPayO2O($order_id,$arr);
                    break;
                case 'CRE':
                    $this->afterPayCRE($order_id,$arr);
                    break;
                default:
                    $this->afterpaytea($order_id,$arr);
                    break;
            }

    }

    public function afterpayO2O($order_id,$arr){

        $order_info = $this->orderRepository->getOrderBySn($order_id);
        $user_info = $this->userRepository->getUserById($order_info['user_id']);
        $store_sub_info = $this->storeRepository->getStoreSubByStoreId($order_info['store_id']);

        if($order_info['pay_status']==OrderE::PAY_STATUS['NO']){
            Db::startTrans();
            try{

                //把订单设定为已支付
                $this->orderRepository->setOrderPaid($order_info,OrderE::PAY_TYPE['WEIXIN'],$arr['transaction_id']);

                //增加用户支付记录
                $this->orderRepository->addMemberCashLog($order_info,$user_info,MemberCashLogE::METHOD['wechat']);
                //扣减商品库存

                $this->orderRepository->deductOrderGoodsStock($order_info);
                //给商家加钱
                $this->userRepository->raiseMerchMoney($order_info,$store_sub_info);

                //给商家增加收入记录
                $result = $this->orderRepository->addMerchCashLog($order_info,$store_sub_info);
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                $this->error('系统繁忙,请稍候再试');
            }
        }else{
            $result = 1;
        }

        //todo 给用户发消息
        //todo 给商家发消息
        if(!empty($result)){
            WeixinpayClass::notify();
        }

    }

    public function afterpayCRE($order_id,$arr){

        $order_info = $this->orderRepository->getCashOrderBySn($order_id);
        $user_info = $this->userRepository->getUserById($order_info['user_id']);
        if($order_info['status']==CashOrderE::STATUS['unpaid']){
        //    Db::startTrans();
          //  try{
                //给用户增加余额
                $result1 = $this->userRepository->raiseUserMoney($order_info,$user_info);
          //  error_log("afterpayCRE--1-".json_encode($result1),3,"/opt/app-root/src/public/log/test.txt");
                //给用户增加余额充值记录
                $result2 = $this->orderRepository->addMemberChargeCashLog($order_info,$user_info);
         //   error_log("afterpayCRE--2-".json_encode($result2),3,"/opt/app-root/src/public/log/test.txt");
                //修改订单状态
                $result3 = $this->orderRepository->setCashOrderPaid($order_info,CashOrderE::PAY_TYPE['wechat'],$arr['transaction_id']);
       //     error_log("afterpayCRE--3-".json_encode($result3),3,"/opt/app-root/src/public/log/test.txt");
            //}catch (\Exception $e) {
                // 回滚事务
              //  Db::rollback();
               // $this->error('系统繁忙,请稍候再试');
           // }
        }else{
            $result = 1;
        }

        //todo 给用户发消息

        if(!empty($result)){
            WeixinpayClass::notify();
        }

    }

    public function afterpaytea($order_id,$arr){
        error_log("进入afterpaytea---".json_encode($order_id),3,"/opt/app-root/src/public/log/test.txt");
        error_log("进入afterpaytea---".json_encode($arr),3,"/opt/app-root/src/public/log/test.txt");
        $now = time();
        //检测是否已支付
        $check_pay_status = Db::name("order")->where('order_sn','=',$order_id)->find();
        if($check_pay_status['order_status']==0){   //未支付
            $arr_order_update = [
                'order_status'=>1,
                'pay_status'=>1,
                'transaction_id'=>$arr['transaction_id'],
                'pay_time'=>$now,
            ];
            $result = Db::name("order")->where('order_sn','=',$order_id)->update($arr_order_update);

            $order_info = Db::name("order")->where('order_sn','=',$order_id)->find();
            if(!empty($order_info['coupon_id'])){
                $arr_coupon_update = [
                    'status'=>1,
                    'use_time'=>$now,
                ];
                Db::name('coupon_list')->where('id','=',$order_info['coupon_id'])->update($arr_coupon_update);
            }
            //检测用户是否是新用户第一单,是的话给老用户送券(多张)
            $user_info = Db::name('users')->where('user_id','=',$check_pay_status['user_id'])->find();
            if(!empty($user_info['rec_user_id'])&&($user_info['rec_send']!=1)){
                $orderHandlerObj = new OrderHandle();
                $coupon_info = Db::name('plat_coupon')->where('is_rec','=',1)->select();
                error_log(var_export($coupon_info,1),3,"/data/wwwroot/www.itafe.cn/tt.txt");
                if(!empty($coupon_info)){
                    foreach($coupon_info as $kc=>$vc){
                        error_log(var_export($vc,1),3,"/data/wwwroot/www.itafe.cn/tt.txt");
                        $result_send_coupon = $orderHandlerObj->sendCoupon($user_info['rec_user_id'],$vc,1);
                    }
                    if(!empty($result_send_coupon)){
                        Db::name('users')->where('user_id','=',$check_pay_status['user_id'])->update(['rec_send'=>1]);
                    }
                }

            }
            $noticeObj = new NoticeHandle();
            $noticeObj->sendModel($check_pay_status['order_id'],1);
            if(!empty($result)){
                WeixinpayClass::notify();
            }
        }
    }



    public function afterpayb2p($order_id,$arr){
        error_log("进入afterpayb2p---".json_encode($order_id),3,"/opt/app-root/src/public/log/test.txt");
        error_log("进入afterpayb2p---".json_encode($arr),3,"/opt/app-root/src/public/log/test.txt");
        $params_re_compaccountdetail = [
            'pay_time'=>date("Y-m-d H:i:s",time()),
            'status'=>3,
        ];
        $arr_pay_log = [
            'openid'=>$arr['openid'],
            'out_trade_no'=>$arr['out_trade_no'],
            'total_fee'=>$arr['total_fee'],
            'type'=>2,
            'time_end'=>$arr['time_end'],
            'transaction_id'=>$arr['transaction_id'],
            'appid'=>$arr['appid'],
            'create_at'=>date("Y-m-d H:i:s"),
        ];

        $re_compaccountdetail_info = Db::table('re_compaccountdetail')->where('order_id',$order_id)->find();


        $param_cash_log = [
            're_company_id'=>$re_compaccountdetail_info['re_company_id'],
            'way' => 1,
            'tip' => '企业充值-微信',
            'type' =>7,
            'cash' =>$re_compaccountdetail_info['cash'],
            're_compaccountdetail_id' =>$re_compaccountdetail_info['id'],
            'order_no' =>$order_id,
            'status' =>1,
            'update_at' =>date("Y-m-d H:i:s",time()),
            'admin_id'=>$re_compaccountdetail_info['admin_id'],
        ];


        if($re_compaccountdetail_info['status']!=3){
            $old_company_info = Db::table('re_company')->where('id',$re_compaccountdetail_info['re_company_id'])->find();
            $new_account = floatval($old_company_info['account']) + floatval($re_compaccountdetail_info['cash']);
            $param_re_company = ['account'=>$new_account];



            Db::startTrans();
            try {
                //更新商家账单详情表
                Db::name('re_compaccountdetail')
                    ->where('order_id', $order_id)
                    ->data($params_re_compaccountdetail)
                    ->update();
                //更新商信息
                Db::name('re_company')
                    ->where('id', $re_compaccountdetail_info['re_company_id'])
                    ->data($param_re_company)
                    ->update();
                 Db::name('pay_log')->insert($arr_pay_log);
                Db::name('cash_log')->insert($param_cash_log);



                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
            }
        }else{
            WeixinpayClass::notify();
        }

    }

    // 培训/活动的微信支付
    public function balanceTrainPay($user_info,$train_info){

        $checkTraining =  $this->checkTraining($user_info,$train_info);
        if($checkTraining==0){
            $now = time();
            //查看用户余额是否足够支付
            if(!($train_info['fee'] > $user_info['available_balance'] )){
                $orderObj = new Order();
                $code = $orderObj->createOrder('train');
                //公司信息
                $company_info = Db::table('re_company')->where('admin_id','=',$train_info['admin_id'])->find();
                //上级用户信息
                $up_user_info = Db::table('user_team')->where('low_user_id','=',$user_info['id'])->find();

                Db::startTrans();
                try{

                    $train_order_arr = [
                        'openid_re'=>$user_info['openid_re'],
                        'user_id'=>$user_info['id'],
                        're_training_id'=>$train_info['id'],
                        'cash'=>0,//线上支付数量
                        'materia'=>2,    //支付方式 1.微信 2.余额 3.混合
                        'create_at'=>date("Y-m-d H:i:s",$now),
                        'pay_time'=>date("Y-m-d H:i:s",$now),
                        'status'=>2,
                        'code'=>$code,
                        'user_cash'=>$train_info['fee'],  //余额支付金额
                        'total'=>$train_info['fee'],       //总金额
                        'admin_id'=>$train_info['admin_id'],
                    ];
                    //0.添加re_trainorder记录
                    $result_insert_tarin_order_id = Db::table('re_trainorder')->insertGetId($train_order_arr);

                    // 1. 扣除用户余额
                    $update_user_sql = "update user set available_balance = available_balance - ".$train_info['fee']." where id = ".$user_info['id'];
                    Db::execute($update_user_sql);

                    //扣除用户余额-cash_log 记录
                    $cash_log_user_train_dec = [
                        'user_id'=>$user_info['id'],
                        're_company_id'=>$company_info['id'],
                        'apply_company_id'=>$company_info['id'],
                        'apply_user_id'=>$user_info['id'],
                        'way'=>2,
                        'tip'=>'会员活动支付',
                        'user_id'=>$user_info['id'],
                        'rec_id'=>'',   //
                        'cash'=>$train_info['fee'],
                        'order_no'=>$code,
                        're_trainorder_id'=>$result_insert_tarin_order_id,
                        'type'=>11,
                        'status'=>1,
                        're_training_id'=>$train_info['id'],
                        'admin_id'=>$train_info['admin_id'],
                        'update_at'=>date("Y-m-d H:i:s",$now),
                    ];
                    Db::table('cash_log')->insert($cash_log_user_train_dec);

                    // 2.增加代理商公司余额
                    $update_company_sql = "update re_company set account = account + ".$train_info['fee']." where id = ".$company_info['id'];

                    $result1 = Db::execute($update_company_sql);
                    //增加代理商公司余额-cash_log 记录
                    $cash_log_company_train_asc = [
                        'user_id'=>$user_info['id'],
                        're_company_id'=>$company_info['id'],
                        'apply_company_id'=>$company_info['id'],
                        'apply_user_id'=>$user_info['id'],
                        'way'=>1,
                        'tip'=>'公司活动入款',
                        'rec_id'=>'',                       //
                        'order_no'=>$code,
                        're_trainorder_id'=>$result_insert_tarin_order_id,
                        'type'=>12,
                        'status'=>1,
                        'cash'=>$train_info['fee'],
                        're_training_id'=>$train_info['id'],
                        'admin_id'=>$train_info['admin_id'],
                        'update_at'=>date("Y-m-d H:i:s",$now),
                    ];
                    Db::table('cash_log')->insert($cash_log_company_train_asc);

                    // 3.增加活动推荐记录
                    if($train_info['reward_up']>0){
                        $commonFuncObj = new commonFunc();
                        $ratio = $commonFuncObj->getPlatformRatio($company_info['admin_id']);
                        $up_cash = 0;
                        $p_cash = 0;
                        $flag_rec = 0;//是否有推荐奖励记录  0:无 1:有

                        if(!empty($up_user_info['up_user_id'])){
                            $up_cash = $train_info['reward_up'];
                        }
                        if($ratio['reward_type']==1){
                            $p_cash = $ratio['p_cash'];
                        }else{
                            $p_cash = $train_info['reward_up'] * $ratio['p_per']/100;
                        }
                        $total_cash = $up_cash + $p_cash;

                        if($total_cash>0){
                            //3.增加活动推荐记录
                            $flag_rec = 1;
                            $insert_rectraindetail_arr = [
                                're_company_id'=>$company_info['id'],
                                'low_user_id'=>$user_info['id'],
                                'up_user_id'=>$up_user_info['up_user_id'],
                                'up_cash'=>$up_cash,
                                'p_cash'=>$p_cash,
                                'reward_type'=>$ratio['reward_type'],  //平台获取佣金方式
                                'status'=>2, //发送状态/未发送
                                'total_cash'=>$total_cash,
                                'admin_id'=>$company_info['admin_id'],
                                'create_at'=>date("Y-m-d H:i:s",$now),
                                'update_at'=>date("Y-m-d H:i:s",$now),
                                're_training_id'=>$train_info['id'],
                                're_trainorder_id'=>$result_insert_tarin_order_id,
                                'up_company_id'=>$company_info['id'],
                                'timeline'=>$train_info['train_end_time'],
                                'deadline'=> date("Y-m-d H:i:s",strtotime($train_info['train_end_time'])+ 60*60*24*7),
                            ];
                            $result_insert_rectraindetai_id = Db::table('re_rectraindetail')->insertGetId($insert_rectraindetail_arr);
                            //给代理商冻结推荐佣金
                            $update_company_forzen_rec_cash_sql = "update re_company set account = account - ".$total_cash.", frozen = frozen + ".$total_cash." where id = ".$company_info['id'];
                            Db::execute($update_company_forzen_rec_cash_sql);

                            //代理商冻结佣金记录
                            $cash_log_company_train_frozen = [
                                'user_id'=>$user_info['id'],
                                're_company_id'=>$company_info['id'],
                                'apply_company_id'=>$company_info['id'],
                                'apply_user_id'=>$user_info['id'],
                                'way'=>2,
                                'tip'=>'代理商冻结活动推荐佣金',
                                'rec_id'=>'',                       //
                                'order_no'=>$code,
                                're_trainorder_id'=>$result_insert_tarin_order_id,
                                'type'=>15,
                                'status'=>1,
                                'cash'=>$total_cash,
                                're_training_id'=>$train_info['id'],
                                'admin_id'=>$train_info['admin_id'],
                                'update_at'=>date("Y-m-d H:i:s",$now),
                            ];
                            Db::table('cash_log')->insert($cash_log_company_train_frozen);

                        }
                    }

                    // 4.添加user_trainin 记录
                    $user_train_arr = [
                        'user_id'=>$user_info['id'],
                        're_training_id'=>$train_info['id'],
                        're_trainorder_id'=>$result_insert_tarin_order_id,
                        'status'=>1,   //已报名
                        'create_at'=>date("Y-m-d H:i:s",$now),
                        'update_at'=>date("Y-m-d H:i:s",$now),
                    ];
                    $result_insert_user_training = Db::table('user_training')->insert($user_train_arr);

                    //5.修改re_training活动的报名人数+更新活动状态
                    if($train_info['max_person']-$train_info['person_count']<2){
                        $update_training_arr = [
                            'status' => 1,
                            'person_count' =>$train_info['person_count']+1
                        ];
                    }else{
                        $update_training_arr = [
                            'person_count' =>$train_info['person_count']+1
                        ];
                    }
                    $result_update_train = Db::table('re_training')->where('id','=',$train_info['id'])->update($update_training_arr);


                    // 提交事务
                    Db::commit();
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                }

                if(!empty($result_update_train)){
                    //发送消息和模版消息

                    /*$response = [
                        "error_code"=> 0,
                        "msg"=> "success",
                        "time"=> time(),
                        "bizobj"=>  null,
                    ];
                    echo json_encode($response);exit;*/
                }else{
                    $response = [
                        "error_code"=> 2,
                        "msg"=> "系统繁忙,请稍候再试",
                        "time"=> time(),
                        "bizobj"=>  null,
                    ];
                    echo json_encode($response);exit;
                }
            }else{
                $response = [
                    "error_code"=> 2,
                    "msg"=> "余额不足,请稍候重试",
                    "time"=> time(),
                    "bizobj"=>  null,
                ];
                echo json_encode($response);exit;
            }
        }else{
            if($checkTraining==1){
                $response = [
                    "error_code"=> 2,
                    "msg"=> "该项目人员已满",
                    "time"=> time(),
                    "bizobj"=>  null,
                ];
                echo json_encode($response);exit;

            }elseif($checkTraining==2){
                $response = [
                    "error_code"=> 2,
                    "msg"=> "您已报名该项目!",
                    "time"=> time(),
                    "bizobj"=>  null,
                ];
                echo json_encode($response);exit;

            }
        }
    }


    public function afterPayTrain($order_id,$arr){
       error_log("进入afterpaytrain---".json_encode($order_id),3,"/opt/app-root/src/public/log/test.txt");
        $order_info = Db::table('re_trainorder')->where('code','=',$order_id)->find();
        $train_info = Db::table('re_training')->where('id','=',$order_info['re_training_id'])->find();
       //error_log(var_export($train_info,1),3,"/opt/app-root/src/public/log/test.txt");
        $user_info = Db::table('user')->where('id','=',$order_info['user_id'])->find();
       //error_log(var_export($user_info,1),3,"/opt/app-root/src/public/log/test.txt");
        //验证支付
     //   error_log(var_export($arr,1),3,"/opt/app-root/src/public/log/test.txt");
        $result_check_pay = $this->checkOrderBeforeHandle($order_info,$arr);
        error_log("验证支付---".json_encode($result_check_pay),3,"/opt/app-root/src/public/log/test.txt");
        if($result_check_pay===0){        //正常支付
            error_log("train_info---".json_encode($train_info),3,"/opt/app-root/src/public/log/test.txt");
            $now = time();
            $code = $order_id;
            //公司信息
            $company_info = Db::table('re_company')->where('admin_id','=',$train_info['admin_id'])->find();
            error_log("公司信息---".json_encode($company_info),3,"/opt/app-root/src/public/log/test.txt");
            //上级用户信息
            $up_user_info = Db::table('user_team')->where('low_user_id','=',$user_info['id'])->find();
            error_log("上级用户信息---".json_encode($up_user_info),3,"/opt/app-root/src/public/log/test.txt");
     //       error_log(var_export($order_info['materia'],1),3,"/opt/app-root/src/public/log/test.txt");
            if($order_info['materia']==1){  //微信支付
                Db::startTrans();
                try{

                    $train_order_arr = [
                        'pay_time'=>date("Y-m-d H:i:s",$now),
                        'status'=>2,
                        'transaction_id'=>$arr['transaction_id'],
                    ];
                    error_log("添加re_trainorder记录---".json_encode($train_order_arr),3,"/opt/app-root/src/public/log/test.txt");
                    //0.添加re_trainorder记录
                    Db::table('re_trainorder')->where('code','=',$order_id)->update($train_order_arr);
                    $result_insert_tarin_order_id = $order_info['id'];
                    error_log("添加re_trainorder记录---".json_encode($train_order_arr),3,"/opt/app-root/src/public/log/test.txt");
           //         error_log(var_export($order_id,1),3,"/opt/app-root/src/public/log/test.txt");
                    // 1. 扣除用户余额
                    /*     $update_user_sql = "update user set available_balance = available_balance - ".$train_info['fee']." where id = ".$user_info['id'];
                         Db::execute($update_user_sql);*/

                    //扣除用户余额-cash_log 记录
                    /*     $cash_log_user_train_dec = [
                             'user_id'=>$user_info['id'],
                             're_company_id'=>$company_info['id'],
                             'apply_company_id'=>$company_info['id'],
                             'apply_user_id'=>$user_info['id'],
                             'way'=>2,
                             'tip'=>'会员活动支付-微信',
                             'user_id'=>$user_info['id'],
                             'rec_id'=>'',   //
                             'cash'=>$train_info['fee'],
                             'order_no'=>$code,
                             're_trainorder_id'=>$result_insert_tarin_order_id,
                             'type'=>11,
                             'status'=>1,
                             're_training_id'=>$train_info['id'],
                             'admin_id'=>$train_info['admin_id'],
                             'update_at'=>date("Y-m-d H:i:s",$now),
                         ];
                         Db::table('cash_log')->insert($cash_log_user_train_dec);*/

                    // 2.增加代理商公司余额
                    //$update_company_sql = "update re_company set account = account + ".$train_info['fee']." where id = ".$company_info['id'];
                    $update_company_sql = "update re_company set train_frozen = train_frozen + ".$train_info['fee']." where id = ".$company_info['id'];
                    error_log("增加代理商公司余额---".json_encode($update_company_sql),3,"/opt/app-root/src/public/log/test.txt");
                    $result1 = Db::execute($update_company_sql);
                    //增加代理商公司余额-cash_log 记录
                 /*   $cash_log_company_train_asc = [
                        'user_id'=>$user_info['id'],
                        're_company_id'=>$company_info['id'],
                        'apply_company_id'=>$company_info['id'],
                        'apply_user_id'=>$user_info['id'],
                        'way'=>1,
                        'tip'=>'公司活动入款',
                        'rec_id'=>'',                       //
                        'order_no'=>$code,
                        're_trainorder_id'=>$result_insert_tarin_order_id,
                        'type'=>12,
                        'status'=>1,
                        'cash'=>$train_info['fee'],
                        're_training_id'=>$train_info['id'],
                        'admin_id'=>$train_info['admin_id'],
                        'update_at'=>date("Y-m-d H:i:s",$now),
                    ];
                    Db::table('cash_log')->insert($cash_log_company_train_asc);*/

                    // 3.增加活动推荐记录
                    if($train_info['reward_up']>0){
                        error_log("上级佣金---".json_encode($train_info['reward_up']),3,"/opt/app-root/src/public/log/test.txt");
                        $commonFuncObj = new commonFunc();
                        $ratio = $commonFuncObj->getPlatformRatio($company_info['admin_id']);
                        error_log("ratio---".json_encode($ratio),3,"/opt/app-root/src/public/log/test.txt");
                        $up_cash = 0;
                        $p_cash = 0;
                        $flag_rec = 0;//是否有推荐奖励记录  0:无 1:有

                        if(!empty($up_user_info['up_user_id'])){
                            $up_cash = $train_info['reward_up'];
                        }
                        if($ratio['reward_type']==1){
                            $p_cash = $ratio['p_cash'];
                        }else{
                            $p_cash = $train_info['reward_up'] * $ratio['p_per']/100;
                        }
                        $total_cash = $up_cash + $p_cash;
                        error_log("total_cash---".json_encode($total_cash),3,"/opt/app-root/src/public/log/test.txt");
                        if($total_cash>0){
                            //3.增加活动推荐记录
                            $flag_rec = 1;
                            $insert_rectraindetail_arr = [
                                're_company_id'=>$company_info['id'],
                                'low_user_id'=>$user_info['id'],
                                'up_user_id'=>$up_user_info['up_user_id'],
                                'up_cash'=>$up_cash,
                                'p_cash'=>$p_cash,
                                'reward_type'=>$ratio['reward_type'],  //平台获取佣金方式
                                'status'=>2, //发送状态/未发送
                                'total_cash'=>$total_cash,
                                'admin_id'=>$company_info['admin_id'],
                                'create_at'=>date("Y-m-d H:i:s",$now),
                                'update_at'=>date("Y-m-d H:i:s",$now),
                                're_training_id'=>$train_info['id'],
                                're_trainorder_id'=>$result_insert_tarin_order_id,
                                'up_company_id'=>$company_info['id'],
                                'timeline'=>$train_info['train_end_time'],
                                'deadline'=> date("Y-m-d H:i:s",strtotime($train_info['train_end_time'])+ 60*60*24*7),
                            ];

                            $result_insert_rectraindetai_id = Db::table('re_rectraindetail')->insertGetId($insert_rectraindetail_arr);
                            //给代理商冻结推荐佣金
                         /*   $update_company_forzen_rec_cash_sql = "update re_company set account = account - ".$total_cash.", frozen = frozen + ".$total_cash." where id = ".$company_info['id'];
                            Db::execute($update_company_forzen_rec_cash_sql);

                            //代理商冻结佣金记录
                            $cash_log_company_train_frozen = [
                                'user_id'=>$user_info['id'],
                                're_company_id'=>$company_info['id'],
                                'apply_company_id'=>$company_info['id'],
                                'apply_user_id'=>$user_info['id'],
                                'way'=>2,
                                'tip'=>'代理商冻结活动推荐佣金',
                                'rec_id'=>'',                       //
                                'order_no'=>$code,
                                're_trainorder_id'=>$result_insert_tarin_order_id,
                                'type'=>15,
                                'status'=>1,
                                'cash'=>$total_cash,
                                're_training_id'=>$train_info['id'],
                                'admin_id'=>$train_info['admin_id'],
                                'update_at'=>date("Y-m-d H:i:s",$now),
                            ];
                            Db::table('cash_log')->insert($cash_log_company_train_frozen);*/

                        }
                    }

                    // 4.添加user_trainin 记录
                    $user_train_arr = [
                        'user_id'=>$user_info['id'],
                        're_training_id'=>$train_info['id'],
                        're_trainorder_id'=>$result_insert_tarin_order_id,
                        'status'=>1,   //已报名
                        'create_at'=>date("Y-m-d H:i:s",$now),
                        'update_at'=>date("Y-m-d H:i:s",$now),
                    ];
                    $result_insert_user_training = Db::table('user_training')->insert($user_train_arr);

                    //5.修改re_training活动的报名人数+更新活动状态
                    if($train_info['max_person']-$train_info['person_count']<2){
                        $update_training_arr = [
                            'status' => 1,
                            'person_count' =>$train_info['person_count']+1
                        ];
                    }else{
                        $update_training_arr = [
                            'person_count' =>$train_info['person_count']+1
                        ];
                    }
                    $result_update_train = Db::table('re_training')->where('id','=',$train_info['id'])->update($update_training_arr);
                    // 提交事务
                    Db::commit();
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                }
                if(!$result_update_train){
                    $error = [
                        'msg'=>"支付错误",
                        'detail'=>"支付事务有误,已支付",
                        'order'=>$order_info,
                    ];
                    $path = $_SERVER['DOCUMENT_ROOT'];
                    error_log(var_export($error,1),$path.'/log/order/pay_error.txt');
                }
            }

            if($order_info['materia']==3){  //混合支付
                Db::startTrans();
                try{
                    $train_order_arr = [
                        'pay_time'=>date("Y-m-d H:i:s",$now),
                        'status'=>2,
                        'transaction_id'=>$arr['transaction_id'],
                    ];
                    //0.添加re_trainorder记录
                    Db::table('re_trainorder')->where('code','=',$order_id)->update($train_order_arr);
                    $result_insert_tarin_order_id = $order_info['id'];
                    error_log(var_export($train_order_arr,1),3,"/opt/app-root/src/public/log/test.txt");
                    error_log(var_export($order_id,1),3,"/opt/app-root/src/public/log/test.txt");
                    // 1. 扣除用户余额
                         $update_user_sql = "update user set available_balance = available_balance - ".$order_info['user_cash']." where id = ".$user_info['id'];
                         Db::execute($update_user_sql);
                    error_log(var_export($update_user_sql,1),3,"/opt/app-root/src/public/log/test.txt");
                    //扣除用户余额-cash_log 记录
                         $cash_log_user_train_dec = [
                             'user_id'=>$user_info['id'],
                             're_company_id'=>$company_info['id'],
                             'apply_company_id'=>$company_info['id'],
                             'apply_user_id'=>$user_info['id'],
                             'way'=>2,
                             'tip'=>'会员活动支付-余额(混合支付)',
                             'user_id'=>$user_info['id'],
                             'rec_id'=>'',   //
                             'cash'=>$order_info['user_cash'],
                             'order_no'=>$code,
                             're_trainorder_id'=>$result_insert_tarin_order_id,
                             'type'=>11,
                             'status'=>1,
                             're_training_id'=>$train_info['id'],
                             'admin_id'=>$train_info['admin_id'],
                             'update_at'=>date("Y-m-d H:i:s",$now),
                         ];
                         Db::table('cash_log')->insert($cash_log_user_train_dec);
                    error_log(var_export($cash_log_user_train_dec,1),3,"/opt/app-root/src/public/log/test.txt");
                    // 2.增加代理商公司余额
                    //$update_company_sql = "update re_company set account = account + ".$order_info['total']." where id = ".$company_info['id'];
                    $update_company_sql = "update re_company set train_frozen = train_frozen + ".$order_info['total']." where id = ".$company_info['id'];

                    $result1 = Db::execute($update_company_sql);
                    //增加代理商公司余额-cash_log 记录
                  /*  $cash_log_company_train_asc = [
                        'user_id'=>$user_info['id'],
                        're_company_id'=>$company_info['id'],
                        'apply_company_id'=>$company_info['id'],
                        'apply_user_id'=>$user_info['id'],
                        'way'=>1,
                        'tip'=>'公司活动入款',
                        'rec_id'=>'',                       //
                        'order_no'=>$code,
                        're_trainorder_id'=>$result_insert_tarin_order_id,
                        'type'=>12,
                        'status'=>1,
                        'cash'=>$order_info['total'],
                        're_training_id'=>$train_info['id'],
                        'admin_id'=>$train_info['admin_id'],
                        'update_at'=>date("Y-m-d H:i:s",$now),
                    ];
                    Db::table('cash_log')->insert($cash_log_company_train_asc);*/

                    // 3.增加活动推荐记录
                    if($train_info['reward_up']>0){
                        $commonFuncObj = new commonFunc();
                        $ratio = $commonFuncObj->getPlatformRatio($company_info['admin_id']);
                        $up_cash = 0;
                        $p_cash = 0;
                        $flag_rec = 0;//是否有推荐奖励记录  0:无 1:有

                        if(!empty($up_user_info['up_user_id'])){
                            $up_cash = $train_info['reward_up'];
                        }
                        if($ratio['reward_type']==1){
                            $p_cash = $ratio['p_cash'];
                        }else{
                            $p_cash = $train_info['reward_up'] * $ratio['p_per']/100;
                        }
                        $total_cash = $up_cash + $p_cash;

                        if($total_cash>0){
                            //3.增加活动推荐记录
                            $flag_rec = 1;
                            $insert_rectraindetail_arr = [
                                're_company_id'=>$company_info['id'],
                                'low_user_id'=>$user_info['id'],
                                'up_user_id'=>$up_user_info['up_user_id'],
                                'up_cash'=>$up_cash,
                                'p_cash'=>$p_cash,
                                'reward_type'=>$ratio['reward_type'],  //平台获取佣金方式
                                'status'=>2, //发送状态/未发送
                                'total_cash'=>$total_cash,
                                'admin_id'=>$company_info['admin_id'],
                                'create_at'=>date("Y-m-d H:i:s",$now),
                                'update_at'=>date("Y-m-d H:i:s",$now),
                                're_training_id'=>$train_info['id'],
                                're_trainorder_id'=>$result_insert_tarin_order_id,
                                'up_company_id'=>$company_info['id'],
                                'timeline'=>$train_info['train_end_time'],
                                'deadline'=> date("Y-m-d H:i:s",strtotime($train_info['train_end_time'])+ 60*60*24*7),
                            ];
                            $result_insert_rectraindetai_id = Db::table('re_rectraindetail')->insertGetId($insert_rectraindetail_arr);
                            //给代理商冻结推荐佣金
                            /*$update_company_forzen_rec_cash_sql = "update re_company set account = account - ".$total_cash.", frozen = frozen + ".$total_cash." where id = ".$company_info['id'];
                            Db::execute($update_company_forzen_rec_cash_sql);

                            //代理商冻结佣金记录
                            $cash_log_company_train_frozen = [
                                'user_id'=>$user_info['id'],
                                're_company_id'=>$company_info['id'],
                                'apply_company_id'=>$company_info['id'],
                                'apply_user_id'=>$user_info['id'],
                                'way'=>2,
                                'tip'=>'代理商冻结活动推荐佣金',
                                'rec_id'=>'',                       //
                                'order_no'=>$code,
                                're_trainorder_id'=>$result_insert_tarin_order_id,
                                'type'=>15,
                                'status'=>1,
                                'cash'=>$total_cash,
                                're_training_id'=>$train_info['id'],
                                'admin_id'=>$train_info['admin_id'],
                                'update_at'=>date("Y-m-d H:i:s",$now),
                            ];
                            Db::table('cash_log')->insert($cash_log_company_train_frozen);*/

                        }
                    }

                    // 4.添加user_trainin 记录
                    $user_train_arr = [
                        'user_id'=>$user_info['id'],
                        're_training_id'=>$train_info['id'],
                        're_trainorder_id'=>$result_insert_tarin_order_id,
                        'status'=>1,   //已报名
                        'create_at'=>date("Y-m-d H:i:s",$now),
                        'update_at'=>date("Y-m-d H:i:s",$now),
                    ];
                    $result_insert_user_training = Db::table('user_training')->insert($user_train_arr);

                    //5.修改re_training活动的报名人数+更新活动状态
                    if($train_info['max_person']-$train_info['person_count']<2){
                        $update_training_arr = [
                            'status' => 1,
                            'person_count' =>$train_info['person_count']+1
                        ];
                    }else{
                        $update_training_arr = [
                            'person_count' =>$train_info['person_count']+1
                        ];
                    }
                    $result_update_train = Db::table('re_training')->where('id','=',$train_info['id'])->update($update_training_arr);
                    // 提交事务
                    Db::commit();
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                }
                if(!$result_update_train){
                    $error = [
                        'msg'=>"支付错误",
                        'detail'=>"支付事务有误,已支付",
                        'order'=>$order_info,
                    ];
                    $path = $_SERVER['DOCUMENT_ROOT'];
                    error_log(var_export($error,1),$path.'/log/order/pay_error.txt');
                }else{
                    //发送消息

                }
            }
        }elseif($result_check_pay==1){   //已支付 不再支付

        }else{                           //支付金额有误   写入log日志
            $paid = $arr['cash_fee']/100;
            $error = [
                'msg'=>"支付错误",
                'detail'=>"支付过程中所付金额---".$paid."---元和需付金额---".$order_info['cash']."--元不等",
                'order'=>$order_info,
            ];
            $path = $_SERVER['DOCUMENT_ROOT'];
            error_log(var_export($error,1),$path.'/log/order/pay_error.txt');
        }




        WeixinpayClass::notify();





   /*     if($re_compaccountdetail_info['status']!=3){
            $old_company_info = Db::table('re_company')->where('id',$re_compaccountdetail_info['re_company_id'])->find();
            $new_account = floatval($old_company_info['account']) + floatval($re_compaccountdetail_info['cash']);
            $param_re_company = ['account'=>$new_account];



            Db::startTrans();
            try {
                //更新商家账单详情表
                Db::name('re_compaccountdetail')
                    ->where('order_id', $order_id)
                    ->data($params_re_compaccountdetail)
                    ->update();
                //更新商信息
                Db::name('re_company')
                    ->where('id', $re_compaccountdetail_info['re_company_id'])
                    ->data($param_re_company)
                    ->update();
                Db::name('pay_log')->insert($arr_pay_log);
                Db::name('cash_log')->insert($param_cash_log);



                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
            }
        }else{
            WeixinpayClass::notify();
        }*/

    }

    /*处理支付之前的验证
        return
            正常流程 0
            已支付 1
            金额有误 2
     * */
    public function checkOrderBeforeHandle($order_info,$arr){
        $flag = 0;
      //  error_log(var_export($order_info,1),3,"/opt/app-root/src/public/log/test.txt");
    //    error_log(var_export($arr,1),3,"/opt/app-root/src/public/log/test.txt");
        //error_log(var_export($order_info['status'],1),3,"/opt/app-root/src/public/log/test.txt");
        //判断该用户是否已经支付
        if($order_info['status']!=1){
            $flag = 1;
        }else{
            //判断金额是否准确
            $cash_fee_pay = $arr['cash_fee']/100;
          //  error_log(var_export($cash_fee_pay,1),3,"/opt/app-root/src/public/log/test.txt");
            $train_fee = $order_info['cash'];
            //error_log(var_export($train_fee,1),3,"/opt/app-root/src/public/log/test.txt");
            if($cash_fee_pay != $train_fee){
                $flag = 2;
             //   $flag = 0;  /***********************************测试暂定************************************************/
            }
        }
        error_log(var_export($flag,1),3,'/data/wwwroot/'.$_SERVER['HTTP_HOST'].'/public/log/testispay.txt');
        return $flag;
    }




}
