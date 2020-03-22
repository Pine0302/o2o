<?php

namespace app\api\library;

use think\Db;
use think\exception\Handle;
use fast\Wx;
use think\cache\driver\Redis;

/**
 * 消息通知
 */
class NoticeHandle
{

    public function __construct()
    {
        $this->redis = new Redis();
    }

    //生成关于申请入职相关的模版消息
    public function sendModel($order_id,$way){
        $order_info = Db::name('order')->where('order_id','=',$order_id)->find();
        $order_goods = Db::name('order_goods')->where('order_id','=',$order_id)->select();
        $goods_name = '';
        $user_info =  Db::name('users')->where('user_id','=',$order_info['user_id'])->find();
        $store_info = Db::name('store_sub')->where('store_id','=',$order_info['store_id'])->find();
        $store_address = $store_info['store_address']."-".$store_info['store_name'];
        foreach($order_goods as $ko=>$vo){
       //     $goods_name .= $vo['goods_name']."x".$vo['goods_num'].",";
            $goods_name .= $vo['goods_name'].",";
        }
        $goods_name = rtrim($goods_name,",");
       // $order_arr
        $template_config = config('templates');
        $emphasis_keyword = '';
        switch ($way){
            case 1:                                     //下单成功通知
                if($order_info['type']==1){   //自取单
                    $template_id = $template_config['ORDER_FETCH'];
                    $data = [
                        'keyword1'=>['value'=>$order_info['order_amount']],
                        'keyword2'=>['value'=>$goods_name],
                        'keyword3'=>['value'=>$user_info['nickname']],
                        'keyword4'=>['value'=>$user_info['order_num']],
                        'keyword5'=>['value'=>$store_address],
                        'keyword6'=>['value'=>'请耐心等待商家接单～'],
                    ];
                }else{                        //配送单
                    $template_id = $template_config['ORDER_SEND'];
                    $data = [
                        'keyword1'=>['value'=>$order_info['order_amount']],
                        'keyword2'=>['value'=>$goods_name],
                        'keyword3'=>['value'=>$order_info['order_num']],
                        'keyword4'=>['value'=>$store_info['store_name']],
                        'keyword5'=>['value'=>'请耐心等待商家接单～'],
                    ];
                }
                break;
            case 2:                                     //制作完成
                $template_id = $template_config['COOK_DONE'];
                $data = [
                    'keyword1'=>['value'=>$order_info['order_num']],
                    'keyword2'=>['value'=>$store_info['store_name']],
                    'keyword3'=>['value'=>$store_info['store_address']],
                    'keyword4'=>['value'=>"完成"],
                    'keyword5'=>['value'=>'咖啡将在5分钟后丢弃，请及时提取'],
                ];
                break;
            case 3:                                     //取餐提醒
                $template_id = $template_config['FETCH'];
                $data = [
                    'keyword1'=>['value'=>$order_info['order_num']],
                    'keyword2'=>['value'=>$store_info['store_name']],
                    'keyword3'=>['value'=>$store_info['store_address']],
                    'keyword4'=>['value'=>$goods_name],
                    'keyword5'=>['value'=>'您的餐品已完成，请尽快前来门店取单，以免影响口感'],
                ];
                $emphasis_keyword = 'keyword1.DATA';
                break;
            case 4:                                     //订单配送通知
                //获取配送员
                $template_id = $template_config['SEND'];
                $ship_info = Db::name('shipping_status')->where('order_id','=',$order_id)->find();
                $address = $order_info['address'].$order_info['address_num'];
                $data = [
                    'keyword1'=>['value'=>$order_info['order_amount']],
                    'keyword2'=>['value'=>$goods_name],
                    'keyword3'=>['value'=>$ship_info['operator_name']],
                    'keyword4'=>['value'=>$ship_info['operator_phone']],
                    'keyword5'=>['value'=>$address],
                    'keyword6'=>['value'=>'外卖小哥正火速赶往您的位置,接收时如遇撒漏,请您联系门店电话,我们将及时为您处理'],
                ];
                break;
            case 5:                                     //取餐提醒
                $template_id = $template_config['EVALUATE'];
                $data = [
                    'keyword1'=>['value'=>$store_info['store_name']],
                    'keyword2'=>['value'=>'已交货'],
                    'keyword3'=>['value'=>$goods_name],
                    'keyword4'=>['value'=>$order_info['order_sn']],
                    'keyword5'=>['value'=>date("Y-m-d H:i:s",$order_info['add_time'])],
                    'keyword6'=>['value'=>'点击反馈您的下单体验, 将帮助我们更好提升服务'],
                ];
                break;
        }
        error_log(var_export($data,1),3,"/data/wwwroot/www.itafe.cn/tt.txt");
        $this->sendModelMsg($user_info,$data,$emphasis_keyword,$template_id,'');
    }


    //生成模版消息接口
    public function sendModelMsg($user_info,$data,$emphasis_keyword,$template_id,$page=''){
        $arr = [
            'app_id'=>config("wxpay.APPID"),
            'app_secret'=>config("wxpay.APPSECRET"),
        ];
        $wx = new Wx($arr);
        $openid = $user_info['openid'];

        $date_key = date("Y-m-d");
        $key = "milkteaFormIdCollection";
        $form_id = $this->getFormId($date_key,$key,$user_info['user_id']);

      //  $form_id = $this->redis->spop('milkteaFormIdCollection_'.$user_info['user_id']);


        if(!empty($form_id)){
            $page = empty($page) ? "pages/index/orderList/orderList" : $page;
            $emphasis_keyword = empty($emphasis_keyword) ?? '';
        /*    error_log(var_export($form_id,1),3,$_SERVER['DOCUMENT_ROOT'].'/tt.txt');
            error_log(var_export($openid,1),3,$_SERVER['DOCUMENT_ROOT'].'/tt.txt');
            error_log(var_export($data,1),3,$_SERVER['DOCUMENT_ROOT'].'/tt.txt');
            error_log(var_export($template_id,1),3,$_SERVER['DOCUMENT_ROOT'].'/tt.txt');*/
            $wx->sendTemplateMessage($openid,$template_id,$page,$form_id,$data,$emphasis_keyword);
        }else{

        }
    }


    //从redis 缓存中获取form_id
    public function getFormId($date_key,$key,$user_id){
        $form_id = 0;
        $i=7;
        //从date_key 向前刷六天,开始找form_id,知道找到当天或者找到为止
        $form_id = 0;
        while(($form_id==0)&&($i!=0)){
            $i--;
            $pre_date_strtime = strtotime($date_key) - 24*60*60*$i;
            $pre_date = date("Y-m-d",$pre_date_strtime);
            $form_id = $this->redis->spop($key."_".$date_key."_".$user_id);
        }
        return $form_id;


    }

    //生成模版消息接口
    public function sendModelMsg1($user_info,$data,$emphasis_keyword,$template_type,$page=''){
        $arr = [
            'app_id'=>config("wxpay.APPID"),
            'app_secret'=>config("wxpay.APPSECRET"),
        ];
        $wx = new Wx($arr);
        $openid = $user_info['openid_re'];
        $form_id = $this->redis->spop('recruitFormIdCollection_'.$user_info['id']);

        // $form_id = 'c9872d85269ba51214f7a853d2af0add';
        //  error_log(var_export($form_id),3,$_SERVER['DOCUMENT_ROOT'].'/tt.txt');
        if(!empty($form_id)){
            $openid =$user_info['openid_re'];
            $template_id =config("wxTemplate.".$template_type);
            $page = empty($page) ? "pages/index/orderList/orderList" : $page;
            $emphasis_keyword = empty($emphasis_keyword) ?? '';
            $wx->sendTemplateMessage($openid,$template_id,$page,$form_id,$data,$emphasis_keyword);
        }else{

        }

    }
}
