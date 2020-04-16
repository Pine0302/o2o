<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\entity\CashOrderE;
use app\common\entity\MemberCashLogE;
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
use app\api\controller\Weixinpay;
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
       // print_r($user_info);exit;
        $order_sn = $OrderHandleObj->createOrder("cre");
        $charge_list = config('webset.chage_money');

        $charge_list_filter = array_filter($charge_list,function($item) use ($charge_id){
            if($item['id']==$charge_id){
                return true;
            }else{
                return false;
            }
        });
        $charge_detail = array_values($charge_list_filter)[0];
     //生成订单
        $order_insert = [
            'order_sn'=>$order_sn,
            'user_id'=>$user_info['user_id'],
            'method'=>CashOrderE::METHOD['mini_charge'],
            'pay_type'=>CashOrderE::PAY_TYPE['wechat'],
            'status'=>CashOrderE::STATUS['unpaid'],
            'total_num'=>$charge_detail['total_num'],
            'pay_num'=>$charge_detail['pay_num'],
            'bonus_num'=>$charge_detail['bonus_num'],
            'create_time'=>$now,
            'update_time'=>$now,
        ];
        $order_id = $this->orderRepository->addCashOrder($order_insert);
        if($order_id>0){
            $WeixinpayObj = new Weixinpay();
            $order_info = [
                'order_sn'=>$order_insert['order_sn'],
                'order_amount'=>$order_insert['pay_num'],
            ];
            $para = $WeixinpayObj->wxTrainPay($user_info,$order_info);
            $data = [
                'order_id'=>$order_id,
            ];
            $this->success('success',$data);
        }
    }

    //账户记录
    public function cashList(){
        $data = $this->request->post();
        $now = time();
        $openid = $this->analysisUserJwtToken();
        $user_info = $this->getTUserInfo($openid);
        $member_cash_log = $this->orderRepository->getMemberCashLog($user_info['user_id']);
        $response_info = [];
        $user_data = [
            'user_money'=>$user_info['user_money'],
        ];
        $cash_log = [];
        if(!empty($member_cash_log)){
            $cash_log = array_map(function($log){
                $fuhao = ($log['way']==MemberCashLogE::WAY['in']) ? '+':"-";
                $cash_log = [
                    'tip'=>$log['tip'],
                    'cash' => $fuhao.$log['cash'],
                    'time' => date("Y-m-d H:i",$log['update_time'])
                ];
                return $cash_log;
            },$member_cash_log);
        }
        $arr_response = [
            'user_data'=>$user_data,
            'cash_log'=>$cash_log,
        ];
        $this->success('success',$arr_response);

    }













}
