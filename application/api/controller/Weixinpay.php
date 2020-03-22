<?php
namespace app\api\controller;

use weixinpay\Weixinpay as WeixinpayClass;
use think\Controller;
use think\Model;
use think\Cache;
use think\cache\driver\Redis;
use think\Db;
use think\Session;
use fast\Http;
use fast\Wx;
use app\common\library\Order;
use fast\Date;
use app\common\library\CommonFunc;
use app\api\library\NoticeHandle;
use app\common\library\OrderHandle;



class Weixinpay
{

    public function __construct($name = null)
    {
        $this->redis = new Redis();
    }

    public function index(){
        echo 12322;exit;
    }

    public function  checkTraining($user_info,$train_info){
        $flag = 0;
        //检测该活动是否可以报名
        $available_num = $train_info['max_person']-$train_info['person_count'];
        if($available_num<1){
            $flag = 1;
        }else{      //检测该用户是否已报名
            $user_training_info = Db::table('user_training')
                ->where('user_id','=',$user_info['id'])
                ->where('re_training_id','=',$train_info['id'])
                ->find();
            if(!empty($user_training_info)){
                $flag = 0;
            }
        }
        return $flag;
    }

    public function  checkOrder($order_info){
        if($order_info['pay_status']==1){
            return 2;  //已支付
        }else{
            return 1;
        }

    }


    //小程序支付
   public function  miniPay(){

       $sess_key = $_REQUEST['sess_key'];
       $order_id = $_REQUEST['order_id'];

       $arr = [  'openid', 'session_key' ];
       $sess_info = $this->redis->hmget($sess_key,$arr);
       $openid = $sess_info['openid'];

       if(!empty($sess_info['openid'])){
           $openid = $sess_info['openid'];
       }else{
           $response = [
               "error_code"=> 4,
               "msg"=> "登录超时",
               "time"=> time(),
               "bizobj"=>  null,
           ];
           echo json_encode($response);exit;
       }

       $user_info = Db::name('users')->where('openid','=',$openid)->find();

       $order_info = Db::name('order')->where('order_id','=',$order_id)->find();

       $check_pay = $this->checkOrder($order_info);

        if($check_pay!=1){
            $response = [
                "error_code"=> 2,
                "msg"=> "支付超时",
                "time"=> time(),
                "bizobj"=>  null,
            ];
            echo json_encode($response);exit;
        }else{
            if((number_format($order_info['order_amount'],2))!='0.00'){
                $this->wxTrainPay($user_info,$order_info);
            }else{

                $result_pay0 = $this->pay0succes($order_info);

                if($result_pay0==1){
                    $response = [
                        "error_code"=> 6,
                        "msg"=> "支付成功",
                        "time"=> time(),
                        "bizobj"=>  null,
                    ];
                }else{
                    $response = [
                        "error_code"=> 1,
                        "msg"=> "系统繁忙,请稍候再试",
                        "time"=> time(),
                        "bizobj"=>  null,
                    ];
                }

           //     var_dump($response);exit;
                echo json_encode($response);exit;
            }
        }
    }

    //0元支付成功
    public function pay0succes($order_info){
        $now = time();
        $order_id = $order_info['order_id'];

        //检测是否已支付
        $check_pay_status = Db::name("order")->where('order_id','=',$order_info['order_id'])->find();
        if($check_pay_status['order_status']==0){   //未支付
            $arr_order_update = [
                'order_status'=>1,
                'pay_status'=>1,
                'transaction_id'=>0,
                'pay_time'=>$now,
            ];
            $result = Db::name("order")->where('order_id','=',$order_id)->update($arr_order_update);
            $order_info = Db::name("order")->where('order_id','=',$order_id)->find();
            if(!empty($order_info['coupon_id'])){
                $arr_coupon_update = [
                    'status'=>1,
                    'use_time'=>$now,
                ];
                Db::name('coupon_list')->where('id','=',$order_info['coupon_id'])->update($arr_coupon_update);
            }
            //检测用户是否是新用户第一单,是的话给老用户送券
            $user_info = Db::name('users')->where('user_id','=',$check_pay_status['user_id'])->find();

            if(!empty($user_info['rec_user_id'])&&($user_info['rec_send']!=1)){
                $orderHandlerObj = new OrderHandle();
                $coupon_info = Db::name('plat_coupon')->where('is_rec','=',1)->select();
                error_log(var_export($coupon_info,1),3,"/data/wwwroot/www.itafe.cn/tt.txt");
                foreach($coupon_info as $kc=>$vc){
                    error_log(var_export($vc,1),3,"/data/wwwroot/www.itafe.cn/tt.txt");
                    $result_send_coupon = $orderHandlerObj->sendCoupon($user_info['rec_user_id'],$vc,1);
                }
                if(!empty($result_send_coupon)){
                    Db::name('users')->where('user_id','=',$check_pay_status['user_id'])->update(['rec_send'=>1]);
                }
            }
            if(!empty($result)){
                return 1;
            }else{
                return 0;
            }
        }else{
            return 1;
        }

    }


    // 培训/活动的微信支付
    public function wxTrainPay($user_info,$order_info){
        $now = time();
        $code = $order_info['order_sn'];

        $weixinPay = new WeixinpayClass();
        $order = [
             'total_fee'=>$order_info['order_amount'] * 100,
            //'total_fee'=>1,
            'out_trade_no'=>$code,
            'product_id'=>$user_info['user_id'],
            'openId'=>$user_info['openid'],

        ];
        $uni_return = $weixinPay->unifiedMiniOrder($order);

    }







    /*  //小程序支付
      public function  miniPay(){

          $weixinPay = new WeixinpayClass();
          $level = $_REQUEST['level_id'];
       //   $fee_info = Db::table('bbs_fee')->where('id','=',$level)->find();
          if(!empty($fee_info['total'])){
              $order = [
                  // 'total_fee'=>$fee_info['total'] * 100,
                  'total_fee'=> 1,
                  'out_trade_no'=>"info_".time(),
                  'product_id'=>$level
              ];
              error_log('---miniPay---'.json_encode(var_export($order,1)),3,'/data/wwwroot/mini4.pinecc.cn/public/log/test.txt');
              $uni_return = $weixinPay->unifiedMiniOrder($order);
          }
      }*/



    /**
    * notify_url接收页面
    */
    public function notify()
    {
        // 获取xml
        error_log(var_export(11111111111,1),3,'/data/wwwroot/mini3.pinecc.cn/public/log/test.txt');
        var_dump(123123);
        /*$xml=file_get_contents('php://input', 'r');

        //转成php数组 禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $arr = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $arr = json_decode(json_encode($arr),true);*/
        //进入回调

       /* if(($arr['result_code']=='SUCCESS')&&($arr['return_code']=='SUCCESS')){
            if (1) {
                $str='<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
            }else{
                $str='<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
            }
            echo $str;
            return $arr;

          $openid = $arr['openid'];
          $total_fee = $arr['total_fee'];
          $productId = $arr['productId'];

          $fee_info = Db::table('bbs_fee')->where('total','=',$total_fee/100)->find();
            error_log(json_encode(8888888888),3,"/data/wwwroot/mini4.pinecc.cn/public/test.txt");
            error_log(var_export($fee_info,1),3,"/data/wwwroot/mini4.pinecc.cn/public/test.txt");
          $user_info = Db::table('bbs_member')->where('open_id','=',$openid)->find();

          if (!empty($fee_info)){
              $insert = [
                  'out_trade_no'=>$arr['out_trade_no'],
                  'user_id' => $user_info['id'],
                  'open_id'=>$openid,
                  'total_fee' => $arr['total_fee'],
                  'type' => 2,
                  'create_at' =>date("Y-m-d H:i:s",time()),
                  'status' => 1,
                  'description'=>"charge",
              ];
              error_log(json_encode(333333333333),3,"/data/wwwroot/mini4.pinecc.cn/public/test.txt");

              $check_bbs_paylog = Db::table('bbs_paylog')
                                ->where('out_trade_no','=',$arr['out_trade_no'])
                                ->find();

              error_log(json_encode(11111111111111),3,"/data/wwwroot/mini4.pinecc.cn/public/test.txt");
              if(!$check_bbs_paylog){
                  error_log(json_encode(444444444444444),3,"/data/wwwroot/mini4.pinecc.cn/public/test.txt");
                  error_log(var_export($insert,1),3,"/data/wwwroot/mini4.pinecc.cn/public/test.txt");
                  $pay_log_id =  Db::table('bbs_paylog')->insertGetId($insert);
              }else{
                  $pay_log_id = $check_bbs_paylog['id'];
              }
              error_log(json_encode(22222222222),3,"/data/wwwroot/mini4.pinecc.cn/public/test.txt");
              error_log(var_export($pay_log_id,1),3,"/data/wwwroot/mini4.pinecc.cn/public/test.txt");
              //c查看是否有添加记录,没有的话添加,有的话跳过
                $check_point_change_log = Db::table('bbs_member_point')->where('bbs_paylog_id','=',$pay_log_id)->find();
              if(empty($check_point_change_log)){        //给用户添加积分
                  $point = $fee_info['point'];
                  $result_update_user_point = Db::table('bbs_member')->where('id', $user_info['id'])->setInc('point',$point);
                  if(!empty($result_update_user_point)){
                      //给用户添加积分记录
                      $arr_point_log = [
                          'bbs_member_id'=>$user_info['id'],
                          'point'=>$point,
                          'point_item'=>"charge",
                          'remark'=>"充值获得积分",
                          'isvalid'=>1,
                          'add_time'=>time(),
                          'create_time'=>date("Y-m-d H:i:s",time()),
                          'bbs_paylog_id'=>$pay_log_id,
                          'way'=>1
                      ];
                      Db::table('bbs_member_point')->insert($arr_point_log);

                  }
              }else{
                  $result_update_user_point = 1;
              }
              error_log(json_encode("result"),3,"/data/wwwroot/mini4.pinecc.cn/public/test.txt");
              error_log(var_export($result_update_user_point,1),3,"/data/wwwroot/mini4.pinecc.cn/public/test.txt");
              error_log(json_encode("result"),3,"/data/wwwroot/mini4.pinecc.cn/public/test.txt");

              if($result_update_user_point){
                  echo  "success";
              }


          }
        }*/

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
var int=self.setInterval(function(){pay_status()},1000);
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

        $arr =  ["appid"=>"wxd98c6a52dab64ef7","bank_type"=>"CFT","cash_fee"=>"1","fee_type"=>"CNY","is_subscribe"=>"Y","mch_id"=>"1486122612","nonce_str"=>"CmES","openid"=>"oUTUPwt3kDmbu8f0EJZROuqPX5Zc","out_trade_no"=>"B2P_5B03D42E339FC","result_code"=>"SUCCESS","return_code"=>"SUCCESS","sign"=>"2B36C19BD4C1196F1DE939968D7588A0","time_end"=>"20180522162705","total_fee"=>"1","trade_type"=>"NATIVE","transaction_id"=>"4200000145201805223882998629"];

        $this->afterpay($arr);
    }


    public function afterpay($arr){
            $order_id = $arr['out_trade_no'];
            $order_id_arr = explode('_',$order_id);
            switch($order_id_arr['0']){
                case 'B2P':
                    $this->afterpayb2p($order_id,$arr);
                    break;
            }
    }

    public function afterpayb2p($order_id,$arr){
      //  error_log("进入afterpayb2p---".json_encode($order_id),3,'/data/wwwroot/mini3.pinecc.cn/public/log/test.txt');
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

    //活动报名通知
    public function createNotice($user_info,$train_info,$up_user_info){
        $noticeHandleObj = new noticeHandle();
        $type = 12;
        $content = "您已成功报名".$train_info['name']."活动";
        $is_read = 2;
        $noticeHandleObj->createNotice($type,$user_info['id'],$content,$is_read);
        if(!empty($up_user_info)){
            $up_user_id = $up_user_info['id'];
            $type = 11;
            $content = "您的团队成员".$user_info['nickname']."，报名".$train_info['name']."活动";
            $is_read = 2;
            $noticeHandleObj->createNotice($type,$up_user_id,$content,$is_read);
        }
    }

    //活动退款
    public function trainRefund($re_train_id=16){
        //$re_train_id = 16;  //测试数据

        $order_list = Db::table('re_trainorder')
            ->where('re_training_id','=',$re_train_id)
            ->select();
        foreach($order_list as $ko=>$vo){
            if($vo['status']==2){     //已支付 退款,修改状态
            //    var_dump($vo['materia']);
                switch ($vo['materia']){
                    case 1:
                        $this->trainRefundWx($vo);    //微信支付退款
                        break;
                    case 2:
                        $this->trainRefundBalance($vo);  //余额支付退款
                        break;
                    case 3:
                        $this->trainRefundMix($vo);      //混合支付退款
                        break;
                }
            }elseif($vo['status']==1){  //未支付  修改订单状态

            }else{

            }
        }

     /*   $weixinPay = new WeixinpayClass();
        $weixinPay->refund('4200000193201811029236397490', 1, 1, "TRAINREFUND_5BDC2E962C53A", "TRAIN_5BDC2E962C53A");*/

    }

    //微信支付退款
    public function trainRefundWx($order_info){

        $weixinPay = new WeixinpayClass();
        $cash = $order_info['cash']*100;
        /****************测试cash = 1*********************/
        $cash = 1;
        /****************测试cash = 1*********************/
        $code = $order_info['code'];
        $refund_code = "REFUND_".$code;
        $result = $weixinPay->refund($order_info['transaction_id'], $cash, $cash, $refund_code, $code);
     //   var_dump($result);
       /* $result = [
            'result_code'=>"SUCCESS",
            'return_code'=>"SUCCESS",
        ];*/

        if(($result['result_code'] =="SUCCESS")&&($result['return_code'] =="SUCCESS")){  //退款成功

            $company_info = Db::table('re_company')->where('admin_id','=',$order_info['admin_id'])->find();
            $check_has_rectraindetail = Db::table('re_rectraindetail')->where('re_trainorder_id','=',$order_info['id'])->find();
            //var_dump($company_info);
            //var_dump($check_has_rectraindetail);
            /*
             *1.把退款订单存入数据库
             *2.商家解除相对应的冻结金额
             *3.订单记录设置为已取消,有退款,添加退款订单号
             *4.用户参加培训记录设为已取消
             *
             *5.$check_has_rectraindetail 如果有 推荐记录设为已取消 reason:报名人数不满自动取消
             *
             */
            //把退款订单存入数据库
            $arr_re_refundlog = [
               'appid'=>$result['appid'],
               'mch_id'=>$result['mch_id'],
               'transaction_id'=>$result['transaction_id'],
               'out_trade_no'=>$result['out_trade_no'],
               'out_refund_no'=>$result['out_refund_no'],
               'refund_id'=>$result['refund_id'],
               'refund_fee'=>$result['refund_fee']/100,
               'type'=>1,
               'create_at'=>date("Y-m-d H:i:s",time()),
            ];
           // var_dump($arr_re_refundlog);
            Db::table('re_refundlog')->insert($arr_re_refundlog);
            //商家解除相对应的冻结金额
            $arr_update_company = [
                'train_frozen'=>($company_info['train_frozen']-$order_info['total']),
            ];
           // var_dump($arr_update_company);
            Db::table('re_company')->where('id','=',$company_info['id'])->update($arr_update_company);

            //订单记录设置为已取消,有退款,添加退款订单号
            $arr_re_trainorder_update = [
                'status'=>4,
                'is_refund'=>1,
                'refund_code'=>$result['out_refund_no'],
                'refund_time'=>date("Y-m-d H:i:s",time()),
            ];
         //   var_dump($arr_re_trainorder_update);
            Db::table('re_trainorder')->where('id','=',$order_info['id'])->update($arr_re_trainorder_update);

            //用户参加培训记录设为已取消
            $arr_user_training_update = [
                'status'=>4,
                'update_at'=>date("Y-m-d H:i:s",time()),
            ];
         //   var_dump($arr_user_training_update);
            Db::table('user_training')->where('re_trainorder_id','=',$order_info['id'])->update($arr_user_training_update);
            //推荐记录设为已取消 reason:报名人数不满自动取消
            if($check_has_rectraindetail){
                $arr_re_rectraindetail_update = [
                    'status'=>3,
                    'reason'=>'报名人数不满取消活动',
                ];
             //   var_dump($arr_re_rectraindetail_update);
                Db::table('re_rectraindetail')->where('re_trainorder_id','=',$order_info['id'])->update($arr_user_training_update);
            }
           // exit;
        }
    }



    //余额支付退款
    public function trainRefundBalance($order_info){


        $cash = $order_info['user_cash'];
        $code = $order_info['code'];
        $refund_code = "REFUND_".$code;


        $company_info = Db::table('re_company')->where('admin_id','=',$order_info['admin_id'])->find();
        $user_info = Db::table('user')->where('id','=',$order_info['user_id'])->find();
        $check_has_rectraindetail = Db::table('re_rectraindetail')->where('re_trainorder_id','=',$order_info['id'])->find();
        //var_dump($company_info);
        //var_dump($check_has_rectraindetail);
        /*0.给用户添加余额及cash-log
         *1.把退款订单存入数据库
         *2.商家解除相对应的冻结金额
         *3.订单记录设置为已取消,有退款,添加退款订单号
         *4.用户参加培训记录设为已取消
         *
         *5.$check_has_rectraindetail 如果有 推荐记录设为已取消 reason:报名人数不满自动取消
         *
         */


        //给用户添加余额
        $arr_user_update = [
            'available_balance'=>($user_info['available_balance']+$order_info['total']),
        ];
           Db::table('user')->where('id','=',$user_info['id'])->update($arr_user_update);
       // var_dump($user_info);
      //  var_dump($arr_user_update);
        //给用户添加余额的cash-log
        $arr_cash_log_insert = [
            'user_id'=>$user_info['id'],
            're_company_id'=>$company_info['id'],
            'apply_company_id'=>$company_info['id'],
            'way'=>1,
            'tip'=>'活动取消用户退款(余额支付)',
            'cash'=>$order_info['total'],
            'order_no'=>$refund_code,
            'type'=>18,
            'status'=>1,
            'apply_user_id'=>$user_info['id'],
            're_training_id'=>$order_info['re_training_id'],
            're_trainorder_id'=>$order_info['id'],
            'admin_id'=>$order_info['admin_id'],
            'update_at'=>date("Y-m-d H:i:s",time()),
        ];
       // var_dump($arr_cash_log_insert);
        Db::table('cash_log')->insert($arr_cash_log_insert);
        //把退款订单存入数据库
        $arr_re_refundlog = [
            'out_trade_no'=>$order_info['code'],
            'out_refund_no'=>$refund_code,
            'balance_fee'=>$cash,
            'type'=>2,
            'create_at'=>date("Y-m-d H:i:s",time()),
        ];
        //var_dump($arr_re_refundlog);
           Db::table('re_refundlog')->insert($arr_re_refundlog);
        //商家解除相对应的冻结金额
        $arr_update_company = [
            'train_frozen'=>($company_info['train_frozen']-$order_info['total']),
        ];
        //var_dump($arr_update_company);
           Db::table('re_company')->where('id','=',$company_info['id'])->update($arr_update_company);

        //订单记录设置为已取消,有退款,添加退款订单号
        $arr_re_trainorder_update = [
            'status'=>4,
            'is_refund'=>1,
            'refund_code'=>$refund_code,
            'refund_time'=>date("Y-m-d H:i:s",time()),
        ];
       // var_dump($arr_re_trainorder_update);
        Db::table('re_trainorder')->where('id','=',$order_info['id'])->update($arr_re_trainorder_update);

        //用户参加培训记录设为已取消
        $arr_user_training_update = [
            'status'=>4,
            'update_at'=>date("Y-m-d H:i:s",time()),
        ];
        //var_dump($arr_user_training_update);
        Db::table('user_training')->where('re_trainorder_id','=',$order_info['id'])->update($arr_user_training_update);
        //推荐记录设为已取消 reason:报名人数不满自动取消
        if($check_has_rectraindetail){
            $arr_re_rectraindetail_update = [
                'status'=>3,
                'reason'=>'报名人数不满取消活动',
            ];
           // var_dump($arr_re_rectraindetail_update);
            Db::table('re_rectraindetail')->where('re_trainorder_id','=',$order_info['id'])->update($arr_user_training_update);
        }
  //      exit;

    }



    //混合支付退款
    public function trainRefundMix($order_info){

        $weixinPay = new WeixinpayClass();
        $cash = $order_info['cash']*100;
        /****************测试cash = 1*********************/
        $cash = 1;
        /****************测试cash = 1*********************/
        $code = $order_info['code'];
        $refund_code = "REFUND_".$code;
        $result = $weixinPay->refund($order_info['transaction_id'], $cash, $cash, $refund_code, $code);
        var_dump($result);
        if(($result['result_code'] =="SUCCESS")&&($result['return_code'] =="SUCCESS")){  //退款成功

            $company_info = Db::table('re_company')->where('admin_id','=',$order_info['admin_id'])->find();
            $user_info = Db::table('user')->where('id','=',$order_info['user_id'])->find();
            $check_has_rectraindetail = Db::table('re_rectraindetail')->where('re_trainorder_id','=',$order_info['id'])->find();
            //var_dump($company_info);
            //var_dump($check_has_rectraindetail);
            /*0.给用户添加余额及cash-log
           *1.把退款订单存入数据库
           *2.商家解除相对应的冻结金额
           *3.订单记录设置为已取消,有退款,添加退款订单号
           *4.用户参加培训记录设为已取消
           *
           *5.$check_has_rectraindetail 如果有 推荐记录设为已取消 reason:报名人数不满自动取消
           *
           */

            //给用户添加余额
            $arr_user_update = [
                'available_balance'=>($user_info['available_balance']+$order_info['user_cash']),
            ];
            Db::table('user')->where('id','=',$user_info['id'])->update($arr_user_update);

            //var_dump($arr_user_update);
            //给用户添加余额的cash-log
            $arr_cash_log_insert = [
                'user_id'=>$user_info['id'],
                're_company_id'=>$company_info['id'],
                'apply_company_id'=>$company_info['id'],
                'way'=>1,
                'tip'=>'活动取消用户退款(混合支付)',
                'cash'=>$order_info['user_cash'],
                'order_no'=>$refund_code,
                'type'=>18,
                'status'=>1,
                'apply_user_id'=>$user_info['id'],
                're_training_id'=>$order_info['re_training_id'],
                're_trainorder_id'=>$order_info['id'],
                'admin_id'=>$order_info['admin_id'],
                'update_at'=>date("Y-m-d H:i:s",time()),
            ];
          //  var_dump($arr_cash_log_insert);
            Db::table('cash_log')->insert($arr_cash_log_insert);

            //把退款订单存入数据库
            $arr_re_refundlog = [
                'appid'=>$result['appid'],
                'mch_id'=>$result['mch_id'],
                'transaction_id'=>$result['transaction_id'],
                'out_trade_no'=>$result['out_trade_no'],
                'out_refund_no'=>$result['out_refund_no'],
                'refund_id'=>$result['refund_id'],
                'refund_fee'=>$result['refund_fee']/100,
                'balance_fee'=>$order_info['user_cash'],
                'type'=>3,
                'create_at'=>date("Y-m-d H:i:s",time()),
            ];
          //  var_dump($arr_re_refundlog);
               Db::table('re_refundlog')->insert($arr_re_refundlog);
            //商家解除相对应的冻结金额
            $arr_update_company = [
                'train_frozen'=>($company_info['train_frozen']-$order_info['total']),
            ];
           // var_dump($arr_update_company);
               Db::table('re_company')->where('id','=',$company_info['id'])->update($arr_update_company);

            //订单记录设置为已取消,有退款,添加退款订单号
            $arr_re_trainorder_update = [
                'status'=>4,
                'is_refund'=>1,
                'refund_code'=>$result['out_refund_no'],
                'refund_time'=>date("Y-m-d H:i:s",time()),
            ];
           // var_dump($arr_re_trainorder_update);
            Db::table('re_trainorder')->where('id','=',$order_info['id'])->update($arr_re_trainorder_update);

            //用户参加培训记录设为已取消
            $arr_user_training_update = [
                'status'=>4,
                'update_at'=>date("Y-m-d H:i:s",time()),
            ];
           // var_dump($arr_user_training_update);
            Db::table('user_training')->where('re_trainorder_id','=',$order_info['id'])->update($arr_user_training_update);
            //推荐记录设为已取消 reason:报名人数不满自动取消
            if($check_has_rectraindetail){
                $arr_re_rectraindetail_update = [
                    'status'=>3,
                    'reason'=>'报名人数不满取消活动',
                ];
               // var_dump($arr_re_rectraindetail_update);
                Db::table('re_rectraindetail')->where('re_trainorder_id','=',$order_info['id'])->update($arr_user_training_update);
            }
       //     exit;
        }
    }




    //活动退款
    public function trainRefund_bak(){

        $re_train_id = 15;
        $weixinPay = new WeixinpayClass();
    //    $weixinPay->refund($transaction_id, $total_fee, $refund_fee, $out_refund_no, $out_trade_no);
        $result = $weixinPay->refund('4200000203201811039714774023', 1, 1, "REFUND_TRAIN_5BDD47033F069", "TRAIN_5BDD47033F069");
        var_dump($result);exit;
      /*  $now = time();
        $orderObj = new Order();
        $code = $orderObj->createOrder('train');
        $weixinPay = new WeixinpayClass();
        $order = [
            // 'total_fee'=>$train_info['fee'] * 100,
            'total_fee'=>1,
            'out_trade_no'=>$code,
            'product_id'=>$train_info['id'],
            'openId'=>$user_info['openid_re'],

        ];
        $train_order_arr = [
            'openid_re'=>$user_info['openid_re'],
            'user_id'=>$user_info['id'],
            're_training_id'=>$train_info['id'],
            'cash'=>$train_info['fee'],//线上支付数量
            'materia'=>1,    //支付方式
            'create_at'=>date("Y-m-d H:i:s",$now),    //订单生成时间
            'status'=>1,
            'code'=>$code,
            'user_cash'=>0,
            'total'=>$train_info['fee'],
            'admin_id'=>$train_info['admin_id'],
        ];
        $result_insert_tarin_order = Db::table('re_trainorder')->insert($train_order_arr);
        if(!empty($result_insert_tarin_order)){
            $uni_return = $weixinPay->unifiedMiniOrder($order);
        }else{
            $response = [
                "error_code"=> 2,
                "msg"=> "系统繁忙,请稍候再试",
                "time"=> time(),
                "bizobj"=>  null,
            ];
            echo json_encode($response);exit;
        }*/
    }


    //活动退款
    public function trainRefundQuery(){

        //对于每一个活动 ,进行退款  cash_log 记录 ,修改订单状态,re_trainorder user_training re_training

        //如果该活动是
        $re_trainorder_id = 134;
        $weixinPay = new WeixinpayClass();
        //    $weixinPay->refund($transaction_id, $total_fee, $refund_fee, $out_refund_no, $out_trade_no);
        $weixinPay->refundquery('4200000203201811027724870609', "TRAIN_5BDC0D2F97579");
        /*  $now = time();
          $orderObj = new Order();
          $code = $orderObj->createOrder('train');
          $weixinPay = new WeixinpayClass();
          $order = [
              // 'total_fee'=>$train_info['fee'] * 100,
              'total_fee'=>1,
              'out_trade_no'=>$code,
              'product_id'=>$train_info['id'],
              'openId'=>$user_info['openid_re'],

          ];
          $train_order_arr = [
              'openid_re'=>$user_info['openid_re'],
              'user_id'=>$user_info['id'],
              're_training_id'=>$train_info['id'],
              'cash'=>$train_info['fee'],//线上支付数量
              'materia'=>1,    //支付方式
              'create_at'=>date("Y-m-d H:i:s",$now),    //订单生成时间
              'status'=>1,
              'code'=>$code,
              'user_cash'=>0,
              'total'=>$train_info['fee'],
              'admin_id'=>$train_info['admin_id'],
          ];
          $result_insert_tarin_order = Db::table('re_trainorder')->insert($train_order_arr);
          if(!empty($result_insert_tarin_order)){
              $uni_return = $weixinPay->unifiedMiniOrder($order);
          }else{
              $response = [
                  "error_code"=> 2,
                  "msg"=> "系统繁忙,请稍候再试",
                  "time"=> time(),
                  "bizobj"=>  null,
              ];
              echo json_encode($response);exit;
          }*/
    }


}
