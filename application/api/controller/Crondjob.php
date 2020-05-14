<?php
namespace app\api\controller;

use app\common\controller\Api;
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
use app\api\controller\Common;
use app\api\library\NoticeHandle;
use app\api\controller\Weixinpay;


class Crondjob extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    //  protected $noNeedLogin = ['test1","login'];
    protected $noNeedLogin = ['*'];
    // 无需鉴权的接口,*表示全部
    //  /protected $noNeedRight = ['test2'];
    protected $noNeedRight = ['*'];

    public function test(){
        var_dump(123);
    }

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

    /*
    * 每个订单，如果超过了 支付时间和预约时间超过 24小时，就把它 支付掉
     * 设置为完成
    * */
    public function handleOrder()
    {
        $now = time();
        $deadline = $now - 24*60*60;
        $order_list = Db::name('order')
            ->where('pay_time','<',$deadline)
            ->where('app_time','<',$deadline)
            ->where('pay_status','=',OrderE::PAY_STATUS['YES'])
            ->where('order_status','=',OrderE::ORDER_STATUS['TAKE'])
            ->select();
        $order_list = array_map(function($order){
            $order['pay_time'] = date("Y-m-d H:i",$order['pay_time']);
            return $order;
        },$order_list);
        print_r($order_list);exit;
    }

    //把7天前的订单金额转入我的可用余额
    public function batchHandleOrders(){
        error_log(var_export(['test'=>111],1),3,"/opt/app-root/src/test.txt");
        $now = time();
        $serven_days_ago = $now - 1*24*60*60;
        $list = Db::name('merch_cash_log')
            ->where('type','=',1)
            ->where('status','=',0)
            ->where('update_time',"<",$serven_days_ago)
            ->select();
        array_map(function($log){
            $this->updateHandleOrder($log);
        },$list);
        print_r($list);exit;
    }

    public function updateHandleOrder($merch_cash_log){
        //把商户记录里面的冻结金额转换成可用金额
        $this->userRepository->updateMerchMoneyToAvailable($merch_cash_log);
    }




    //发送消息
    public function sendRecruitMsg($recommend_info){

        $user_info = Db::table('user')->where('id','=',$recommend_info['low_user_id'])->find();

        $up_user_info = Db::table('user')->where('id','=',$recommend_info['up_user_id'])->find();

        $job_info = Db::table('re_job')->where('id','=',$recommend_info['re_job_id'])->find();

        $company_info = Db::table('re_company')->where('id','=',$job_info['re_company_id'])->find();

        $days = round((strtotime($recommend_info['timeline'])-strtotime($recommend_info['create_at']))/(60*60*24));

        //给驻场发送消息
        //查找驻场人员
        $noticeHandleObj = new NoticeHandle();
        // $bind_list = $this->getBindUsersByAdminCompanyId($job_info['admin_id'],$job_info['re_company_id']);
        $bind_list = $this->getBindUsersByAdminId($job_info['admin_id']);

        if(count($bind_list)>0){
            foreach($bind_list as $kb=>$vb){
                //添加驻场人员消息记录
                $type = 7;
                $content = "您的公司成员".$user_info['nickname']."已入职".$company_info['name']."公司".$job_info['name']."岗位".$days."天，请及时发放奖励。";
             //   $content = $user_info['nickname']."投递您公司".$job_info['name']."岗位（驻场人员收到）";
                $is_read = 2;
                $noticeHandleObj->createNotice($type,$vb['user_id'],$content,$is_read);
            }
        }
        //给职员发送消息
        if($recommend_info['lower_cash']>0){
            $type = 6;
            $content ="有入职奖：您已入职".$company_info['name']."公司".$job_info['name']."岗位".$days."天，您的入职奖励".$recommend_info['lower_cash']."元即将发放。";
            $is_read = 2;
            $noticeHandleObj->createNotice($type,$user_info['id'],$content,$is_read);
        }
        //给推荐者发送消息
        if($recommend_info['up_cash']>0){
            $type = 6;
            $content ="您的团队成员".$user_info['nickname']."已入职".$company_info['name']."公司".$job_info['name']."岗位".$days."天，您的奖励即将发放。";
            $is_read = 2;
            $noticeHandleObj->createNotice($type,$up_user_info['id'],$content,$is_read);
        }

    }

    public function flushDBs(){
        Db::table('cart')->query('truncate tp_article_bak');
        Db::table('cart')->query('truncate tp_goods');
        Db::table('cart')->query('truncate tp_goods_activity');
        Db::table('cart')->query('truncate tp_goods_attr');
        Db::table('cart')->query('truncate tp_goods_attribute');
        Db::table('cart')->query('truncate tp_goods_category');
        Db::table('cart')->query('truncate tp_goods_images');
        Db::table('cart')->query('truncate tp_goods_plus');
        Db::table('cart')->query('truncate tp_goods_type');
        Db::table('cart')->query('truncate tp_goods_visit');
        Db::table('cart')->query('truncate tp_goods_images');
        Db::table('cart')->query('truncate tp_member_cash_log');
        Db::table('cart')->query('truncate tp_merch_cash_log');
        Db::table('cart')->query('truncate tp_order');
        Db::table('cart')->query('truncate tp_order_action');
        Db::table('cart')->query('truncate tp_order_goods');
        Db::table('cart')->query('truncate tp_order_num');
        Db::table('cart')->query('truncate tp_plus_attr');
        Db::table('cart')->query('truncate tp_recharge');
        Db::table('cart')->query('truncate tp_rider_company');
        Db::table('cart')->query('truncate tp_rider_company_bind');
        Db::table('cart')->query('truncate tp_rider_company_charge');
        Db::table('cart')->query('truncate tp_seller_sub');
        Db::table('cart')->query('truncate tp_store_sub');
        Db::table('cart')->query('truncate tp_store_sub_extend');
        Db::table('cart')->query('truncate tp_users');
        Db::table('cart')->query('truncate tp_withdrawals');
        Db::table('cart')->query('truncate tp_spec_goods_price');
        Db::table('cart')->query('truncate tp_spec_image');
        Db::table('cart')->query('truncate tp_spec_item');

    }

}
