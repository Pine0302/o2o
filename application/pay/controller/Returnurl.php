<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/1 0001
 * Time: 下午 1:43
 */

namespace app\pay\controller;
use app\pay\controller\Payhandle;
use think\addons\Controller;


class Returnurl extends Controller
{
    public function RetrunUrl()
    {
        $data = $_REQUEST;
        $arr = [
            'out_trade_no'=>$data['out_trade_no'],
            'total_amount'=>$data['total_amount'],
        ];
        $payHandle = new Payhandle(config('Alipay'));
        $result = $payHandle->checkAfterPayB2P($arr);
        if($result==true){
            $this->success("支付成功,即将跳转到详情列表页",'https://'.$_SERVER['HTTP_HOST'].'/admin/re/compaccountdetail?ref=addtabs',3);
        }else{
            $this->error("支付失败,请稍后刷新或联系后台管理员,即将跳转到详情列表页",'https://'.$_SERVER['HTTP_HOST'].'/admin/re/compaccountdetail?ref=addtabs',3);
        }

    }

}