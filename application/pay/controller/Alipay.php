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
use think\Loader;
use alipay\pay\buildermodel\AlipayTradeQueryContentBuilder;
use alipay\pay\buildermodel\AlipayTradePagePayContentBuilder;
use alipay\pay\service\AlipayTradeService;
use alipay\pay\service\AlipayWapPayTradeService;




class Alipay extends Controller
{


    public $alipay_config;

    public function pay(){
        $this->alipay_config = config('Alipay');
        $config = $this->alipay_config;
        $out_trade_no = trim($_GET['WIDout_trade_no']);
        //订单名称，必填
        $subject = trim($_GET['WIDsubject']);
        //付款金额，必填
        $total_amount = trim($_GET['WIDtotal_amount']);
        //  $total_amount = 0.01;

        //商品描述，可空
        $body = $_GET['WIDbody'] ? $_GET['WIDbody']:'招聘后台订单';
        //构造参数

        $payRequestBuilder = new AlipayTradePagePayContentBuilder();
        $payRequestBuilder->setBody($body);
        $payRequestBuilder->setSubject($subject);
        $payRequestBuilder->setTotalAmount($total_amount);
        $payRequestBuilder->setOutTradeNo($out_trade_no);

        $aop = new AlipayTradeService($config);

        /**
         * pagePay 电脑网站支付请求
         * @param $builder 业务参数，使用buildmodel中的对象生成。
         * @param $return_url 同步跳转地址，公网可以访问
         * @param $notify_url 异步通知地址，公网可以访问
         * @return $response 支付宝返回的信息
         */
        $response = $aop->pagePay($payRequestBuilder,$config['return_url'],$config['notify_url']);
    }




}