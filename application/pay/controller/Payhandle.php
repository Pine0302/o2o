<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/1 0001
 * Time: 下午 1:39
 */

namespace app\pay\controller;

use weixinpay\Weixinpay as WeixinpayClass;
use think\Controller;
use think\Model;
use think\Db;

class Payhandle extends Controller
{
    public $alipay_config;
    public function __construct($alipay_config) {
        parent::__construct();
        $this->alipay_config= $alipay_config;
        /*$this->load->model('User_model', 'member');
        $this->load->model('House_model', 'house');
        $this->load->model('Horder_model', 'horder');
        $this->load->model('Base_model', 'base');
        $this->load->model('Pay_model', 'pay');
        $this->load->library('MyCurl','mycurl');
        $this->uid = $_SESSION['uid'];
        if(empty($this->uid)){
            header('Location: http://'.$_SERVER['HTTP_HOST'].'/www/user/login');
        }*/
    }


    //支付前的验证处理
    public function beforePay(){
      /*  $data = $_REQUEST;
        $param['trade_no'] = base64_decode($data['oid']);
        $param['total_amount'] = base64_decode($data['total']);
        $param['pay_mode'] = $data['mode'];

        //验证订单
        if($param['pay_mode']==1){
            $sql = "select * from horder where order_num = '".$param['trade_no']."' and total = ".$param['total_amount'];
            $result_query = $this->db->query($sql);
            $result_arr = $result_query->result_array();
            if(!empty($result_arr['0'])){
                $param['trade_no'] = $result_arr['0']['order_num'];
                $param['subject'] = '预定房间订单';
                $param['total_amount'] = $result_arr['0']['total'];
                $param['body'] = "普通房源订单";
                header('Location: http://'.$_SERVER['HTTP_HOST'].'/www/pay/pay?WIDout_trade_no='.$param['trade_no']."&WIDsubject=".$param['subject']."&WIDtotal_amount=". $param['total_amount']."&WIDbody=".$param['body']);
            }else{
                //todo  验证订单失败的处理
            }
        }*/

    }

    //商家向平台充值处理
    public function afterPayB2P($arr){

        $order_id = $arr['out_trade_no'];
        $params_re_compaccountdetail = [
            'pay_time'=>date("Y-m-d H:i:s",time()),
            'status'=>3,
        ];

        $re_compaccountdetail_info = Db::table('re_compaccountdetail')->where('order_id',$order_id)->find();
        $arr_pay_log = [
            'out_trade_no'=>$arr['out_trade_no'],
            'total_fee'=>$arr['total_amount'],
            'type'=>1,
            'appid'=>$arr['app_id'],
            'gmt_payment'=>$arr['gmt_payment'],
            'body'=>$arr['body'],
            'create_at'=>date("Y-m-d H:i:s",time()),
            're_company_id'=>$re_compaccountdetail_info['re_company_id'],

        ];
      //  error_log(var_export($re_compaccountdetail_info,1),3,'/data/wwwroot/recruit.czucw.com/runtime/test.txt');

        if($re_compaccountdetail_info['status']!=3){
            $old_company_info = Db::table('re_company')->where('id',$re_compaccountdetail_info['re_company_id'])->find();

           // error_log(var_export($old_company_info,1),3,'/data/wwwroot/recruit.czucw.com/runtime/test.txt');

            $new_account = floatval($old_company_info['account']) + floatval($re_compaccountdetail_info['cash']);
            $param_re_company = ['account'=>$new_account];
            $param_cash_log = [
                're_company_id'=>$re_compaccountdetail_info['re_company_id'],
                'way' => 1,
                'tip' => '企业充值-支付宝',
                'type' =>7,
                'cash' =>$re_compaccountdetail_info['cash'],
                're_compaccountdetail_id' =>$re_compaccountdetail_info['id'],
                'order_no' =>$order_id,
                'status' =>1,
                'update_at' =>date("Y-m-d H:i:s",time()),
                'admin_id'=>$re_compaccountdetail_info['admin_id'],
            ];
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
                //添加cash_log表
                Db::name('cash_log')->insert($param_cash_log);
                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
            }
        }
    }

    //支付后的处理
    public function afterPay(){
    }

    public function checkAfterPayB2P($arr){
        $order_id = $arr['out_trade_no'];
        $order_info = Db::table('re_compaccountdetail')->where('order_id',$order_id)->find();
        if(!empty($order_info)&&($order_info['status']==3)){
            return true;
        }else{
            return false;
        }
    }







}