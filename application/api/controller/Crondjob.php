<?php
namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\wx\WXBizDataCrypt;
use fast\Http;
use think\cache\driver\Redis;
use think\Db;
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

    /*
    * 定时处理活动状态
    * */
    public function handleTrain()
    {
       // error_log(var_export(123,1),3,"/data/wwwroot/mini3.pinecc.cn/tt.txt");
        $now_date = date("Y-m-d H:i:s");
        $train_list = Db::table('re_training')
            ->where('status','neq',4)
            ->select();
        foreach($train_list as $kr=>$vr){
            $status = '-1';
            $pre_status = $vr['status'];
            $max_person = $vr['max_person'];
            $person_count = $vr['person_count'];
            $is_full = (($max_person-$person_count)>1) ? 2:1;   //是否报名人满 1:满 2:未满
           /* echo "活动id: ".$vr['id']." -- 活动状态: ".$pre_status." -- 活动是否报名人满: ".$is_full;
            echo "<br>";*/
                if($pre_status==2){        //报名中
                    if($vr['sign_time']>$now_date){   //活动报名时间还没结束
                        if($is_full==1){   //人满
                            if($vr['status']!=1){
                                $status  = 1;           //todo 活动报名结束
                            }
                        }
                    }else{        //活动报名已结束
                        if($is_full==2){  //人不满
                            if($vr['status']!=3){
                                $status = 3;           //todo 活动取消
                            }
                        }
                    }
                }elseif($pre_status==1){   //活动报名人满结束报名
                    if($vr['train_time']<$now_date){  //已过开始时间
                        if($vr['status']!=5){
                            $status = 5 ;           //todo 活动开始
                        }
                    }
                }elseif($pre_status==5){   //活动开始
                    if($vr['train_end_time']<$now_date){
                        if($vr['status']!=4){
                            $status = 4 ;           //todo 活动结束
                        }
                    }
                }
                //var_dump($status);
                if($status!="-1"){
                    $this->handleTrainDetail($status,$vr);
                }


        }
    }

    //处理活动状态变更
    public function handleTrainDetail($status,$train_info){
        if(($status==1)||($status==5)){   //活动开始或者报名结束
            Db::table('re_training')->where('id','=',$train_info['id'])->update(['status'=>$status]);
            if($status==5){
                Db::table('user_training')->where('re_training_id','=',$train_info['id'])->update(['status'=>2]);  //用户报名活动表状态改为已参加
            }

        }
        if($status==3){     //活动取消
            if($train_info['fee']>0){  //有支付,退款
                $weixinPayObj = new Weixinpay();
                $weixinPayObj->trainRefund($train_info['id']);
                Db::table('re_training')->where('id','=',$train_info['id'])->update(['status'=>$status]);
                Db::table('user_training')->where('re_training_id','=',$train_info['id'])->update(['status'=>4]);  //用户报名活动表状态改为已取消
            }else{
                Db::table('re_training')->where('id','=',$train_info['id'])->update(['status'=>$status]);
                Db::table('user_training')->where('re_training_id','=',$train_info['id'])->update(['status'=>4]);  //用户报名活动表状态改为已取消
            }
        }

        if($status==4){  //活动结束
            Db::table('re_training')->where('id','=',$train_info['id'])->update(['status'=>$status]);
            Db::table('user_training')->where('re_training_id','=',$train_info['id'])->update(['status'=>3]);  //用户报名活动表状态改为已结束

        }
    }

    //培训活动的 自动发佣金 --每天两点十分执行
    public function trainCommisionDistributeAuto(){
        $rec_list  = Db::table('re_rectraindetail')->where('status','=',2)->select();
        $now_date = date("Y-m-d H:i:s",time());
        foreach($rec_list as $kr=>$vr){
            if(($now_date>$vr['deadline'])&&($vr['status']==2)){
                $this->trainCommisionDistributeAutoDetail($vr);
            }
        }
    }

    //自动发送培训活动佣金详细
    public function trainCommisionDistributeAutoDetail($rec_train_info) {
      //  $rec_train_info = Db::table('re_rectraindetail')->where('id','=',117)->find();

        $train_order_detail = Db::table('re_trainorder')->where('id','=',$rec_train_info['re_trainorder_id'])->find();
        $company_info = Db::table('re_company')->where('admin_id','=',$train_order_detail['admin_id'])->find();

        $p_company_info = Db::table('re_company')->where('admin_id','=',1)->find();
        $now_date = date("Y-m-d H:i:s",time());
        if($rec_train_info['up_cash']>0){
            $up_user_info = Db::table('user')->field('id,available_balance,rec_cash')->where('id','=',$rec_train_info['up_user_id'])->find();
        }
     /*   var_dump($company_info['train_frozen']);
        var_dump($train_order_detail['total']);*/
        if(!($company_info['train_frozen']<$train_order_detail['total'])){
            // 启动事务
            Db::startTrans();
            try{
                //修改分佣状态--已完成
                Db::table('re_rectraindetail')->where('id','=',$rec_train_info['id'])->update(['status'=>1,'update_at'=>$now_date]);
           //     var_dump(1);
                //修改订单状态 --已完成
                Db::table('re_trainorder')->where('id','=',$train_order_detail['id'])->update(['status'=>3]);
             //   var_dump(2);
                //代理商解冻获得的活动费用-支出的佣金
                $new_company_account = $company_info['account']+$train_order_detail['total']-$rec_train_info['total_cash'];
                $arr_company_update = [
                    'train_frozen' => ($company_info['train_frozen']-$train_order_detail['total']),
                    'account'=>$new_company_account,
                ];
                // var_dump($arr_company_update);
                Db::table('re_company')->where('id','=',$company_info['id'])->update($arr_company_update);
               // var_dump(3);
                //代理商解冻获得的活动费用的cash_log
                $arr_cashlog_company_profit_insert = [
                    're_company_id'=>$company_info['id'],
                    'apply_company_id'=>$company_info['id'],
                    'way'=>1,
                    'tip'=>"代理商活动入款",
                    'cash'=>$train_order_detail['total'],
                    'order_no'=>$train_order_detail['code'],
                    'type'=>12,
                    'status'=>1,
                    'apply_user_id'=>$train_order_detail['user_id'],
                    're_training_id'=>$train_order_detail['re_training_id'],
                    're_trainorder_id'=>$train_order_detail['id'],
                    'admin_id'=>$train_order_detail['admin_id'],
                    'update_at'=>$now_date,
                ];
                //var_dump($arr_cashlog_company_profit_insert);
                Db::table('cash_log')->insert($arr_cashlog_company_profit_insert);
                //var_dump(4);
                //代理商支付活动佣金的cash_log
                $arr_cashlog_company_commision_insert = [
                    're_company_id'=>$company_info['id'],
                    'apply_company_id'=>$company_info['id'],
                    'way'=>2,
                    'tip'=>"代理商支付活动推荐佣金",
                    'cash'=>$rec_train_info['total_cash'],
                    'order_no'=>$train_order_detail['code'],
                    'rec_id'=>$rec_train_info['id'],
                    'type'=>15,
                    'status'=>1,
                    'apply_user_id'=>$train_order_detail['user_id'],
                    're_training_id'=>$train_order_detail['re_training_id'],
                    're_trainorder_id'=>$train_order_detail['id'],
                    'admin_id'=>$train_order_detail['admin_id'],
                    'update_at'=>$now_date,
                ];
                //var_dump($arr_cashlog_company_commision_insert);
                Db::table('cash_log')->insert($arr_cashlog_company_commision_insert);
                //var_dump(5);
                //上级获取佣金+cash_log  + todo 活动奖励到账通知notice
                if($rec_train_info['up_cash']>0){
                    $arr_user_up_update = [
                        'available_balance'=>($up_user_info['available_balance']+$rec_train_info['up_cash']),
                        'rec_cash'=>($up_user_info['rec_cash']+$rec_train_info['up_cash']),
                    ];
                    //  var_dump($arr_user_up_update);
                    Db::table('user')->where('id','=',$up_user_info['id'])->update($arr_user_up_update);
                  //  var_dump(6);
                    //---cash_log  上级获取佣金
                    $arr_cashlog_up_user_insert = [
                        're_company_id'=>$company_info['id'],
                        'apply_company_id'=>$company_info['id'],
                        'way'=>1,
                        'tip'=>"会员推荐活动奖励",
                        'cash'=>$rec_train_info['up_cash'],
                        'order_no'=>$train_order_detail['code'],
                        'type'=>13,
                        'status'=>1,
                        'user_id'=>$up_user_info['id'],
                        'apply_user_id'=>$train_order_detail['user_id'],
                        're_training_id'=>$train_order_detail['re_training_id'],
                        're_trainorder_id'=>$train_order_detail['id'],
                        'admin_id'=>$train_order_detail['admin_id'],
                        'update_at'=>$now_date,
                    ];
                    //var_dump($arr_cashlog_up_user_insert);
                    Db::table('cash_log')->insert($arr_cashlog_up_user_insert);
                }
                //var_dump(7);

                if($rec_train_info['p_cash']>0){
                    $arr_p_company_update = [
                        'account'=>($p_company_info['account']+$rec_train_info['p_cash']),
                    ];
                    //var_dump($arr_p_company_update);
                    Db::table('re_company')->where('id','=',$p_company_info['id'])->update($arr_p_company_update);
                  //  var_dump(8);
                    //---cash_log  平台获取佣金

                    $arr_cashlog_p_company_insert = [
                        're_company_id'=>$company_info['id'],    //代理商有权限查看平台分佣的情况
                        'apply_company_id'=>$p_company_info['id'],
                        'way'=>1,
                        'tip'=>"平台推荐分红--活动",
                        'cash'=>$rec_train_info['p_cash'],
                        'order_no'=>$train_order_detail['code'],
                        'type'=>4,
                        'status'=>1,
                        'user_id'=>$train_order_detail['user_id'],
                        'rec_id'=>$rec_train_info['id'],
                        'apply_user_id'=>$train_order_detail['user_id'],
                        're_training_id'=>$train_order_detail['re_training_id'],
                        're_trainorder_id'=>$train_order_detail['id'],
                        'admin_id'=>$train_order_detail['admin_id'],
                        'update_at'=>$now_date,
                    ];

                    //var_dump($arr_cashlog_p_company_insert);
                  $result =  Db::table('cash_log')->insert($arr_cashlog_p_company_insert);
                    //var_dump(9);
                }
                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
            }

            //todo 操作成功或者失败记录log
            if($result){
                return 1;
            }else{
                return 0;
            }
        }else{
            return 0;
        }
    }



    /*
   * 招聘推荐的自动发奖
   * */
    public function recruitDistributeReward()
    {
        $date = date("Y-m-d H:i:s");
        $recommend_list = Db::table('re_recommenddetail')
            ->where('status','=',2)
            ->where('deadline','neq','')
            ->select();
        foreach($recommend_list as $kr=>$vr){
            if(($vr['deadline']>$date)&&($vr['status']==2)){
                $this->pass($vr['id']);
            }
        }
    }


    /**
     * 确认通过
     */
    public function pass($ids = NULL)
    {
        $platform_id = 1; //平台id
        //  $row = $this->recommendDetailModel->get($ids);
        $row = Db::table("re_recommenddetail")->where('id','=',$ids)->find();

        $re_recommenddetail_id = $row['id'];
        $re_recommenddetail_info = Db::table('re_recommenddetail')->where('id',$re_recommenddetail_id)->find();
        $re_job_id  =  $re_recommenddetail_info['re_job_id'];
        $job_info = Db::table('re_job')->where('id','=',$re_job_id)->find();
        $job_admin_id = $job_info['admin_id'];

        $re_company_id = $row['re_company_id'];
        $total = $row['total_cash'];

        $comp_info = Db::table('re_company')->where('admin_id','=',$job_admin_id)->find();
        //发奖励之前先从发布该岗位的公司账户里扣除对应金额
        $result_down_money =  $this->downMoney($job_admin_id,$total);



        if ($result_down_money!=2) {


            $params_commend_detail_update = [
                'status' => 1,
                'update_at' => date('Y-m-d H:i:s')
            ];
            $params_company_account_update = $result_down_money;

            //todo  给用户增加金额,给用户增加记录,给推荐人添加金额,给推荐人添加记录,给平台(公司)添加金额/记录,给代理商(公司)添加金额/记录
            //     $re_recommenddetail_info = Db::table('re_recommenddetail')->where('id',$re_recommenddetail_id)->find();
            //公司资金变动记录
            $params_company_cash_log_update = [
                're_company_id' => $comp_info['id'],
                'apply_company_id' => $job_info['re_company_id'],
                'apply_user_id' => $re_recommenddetail_info['low_user_id'],
                'admin_id' => $re_recommenddetail_info['admin_id'],
                'way' => 2,
                'tip' => "入职员工奖励金支付",
                'rec_id' => $re_recommenddetail_info['id'],
                'type' => 6,
                'status' => 1,
                'cash' => $total,
                'update_at' => date("Y-m-d H:i:s",time())
            ];

            //1.给入职用户发奖
            $low_user_info = Db::table('user')->field('id,total_balance,available_balance,rec_cash')->where('id',$re_recommenddetail_info['low_user_id'])->find();
            $arr_update_low_user = [
                'total_balance' => $low_user_info['total_balance'] + $re_recommenddetail_info['lower_cash'],
                'available_balance' => $low_user_info['available_balance'] + $re_recommenddetail_info['lower_cash'],
                'rec_cash' => $low_user_info['rec_cash'] + $re_recommenddetail_info['lower_cash'],
            ];
            //a.给入职用户添加奖金记录
            $arr_update_low_user_cash_log = [
                'user_id' => $re_recommenddetail_info['low_user_id'],
                'apply_company_id' => $job_info['re_company_id'],
                'apply_user_id' => $re_recommenddetail_info['low_user_id'],
                'admin_id' => $re_recommenddetail_info['admin_id'],
                'way' => 1,
                'tip' => "入职奖励",
                'rec_id' => $re_recommenddetail_info['id'],
                'type' => 1,
                'status' => 1,
                'cash' => $re_recommenddetail_info['lower_cash'],
                'update_at' => date("Y-m-d H:i:s",time())
            ];

            //2.给上级人发奖()
            if(!empty($re_recommenddetail_info['up_user_id'])){
                $up_user_info = Db::table('user')->field('id,total_balance,available_balance,rec_cash')->where('id',$re_recommenddetail_info['up_user_id'])->find();
                $arr_update_up_user = [
                    'total_balance' => $up_user_info['total_balance'] + $re_recommenddetail_info['up_cash'],
                    'available_balance' => $up_user_info['available_balance'] + $re_recommenddetail_info['up_cash'],
                    'rec_cash' => $up_user_info['rec_cash'] + $re_recommenddetail_info['up_cash'],
                ];
                //a.给上级用户添加奖金记录
                $arr_update_up_user_cash_log = [
                    'user_id' => $re_recommenddetail_info['up_user_id'],
                    'apply_company_id' => $job_info['re_company_id'],
                    'apply_user_id' => $re_recommenddetail_info['low_user_id'],
                    'admin_id' => $re_recommenddetail_info['admin_id'],
                    'way' => 1,
                    'tip' => "会员推荐奖励",
                    'rec_id' => $re_recommenddetail_info['id'],
                    'type' => 2,
                    'status' => 1,
                    'cash' => $re_recommenddetail_info['up_cash'],
                    'update_at' => date("Y-m-d H:i:s",time())
                ];

            }

            //3.给平台(公司)添加金额
            $p_company_info = Db::table('re_company')->field('id,account')->where('id',$platform_id)->find();
            $arr_update_up_p = [
                'account' => $p_company_info['account'] + $re_recommenddetail_info['p_cash'],
            ];
            $arr_update_p_company_cash_log = [
                're_company_id' => $p_company_info['id'],
                'apply_company_id' => $job_info['re_company_id'],
                'apply_user_id' => $re_recommenddetail_info['low_user_id'],
                'admin_id' => $re_recommenddetail_info['admin_id'],
                'way' => 1,
                'tip' => "入职平台分红",
                'rec_id' => $re_recommenddetail_info['id'],
                'type' => 4,
                'status' => 1,
                'cash' => $re_recommenddetail_info['p_cash'],
                'update_at' => date("Y-m-d H:i:s",time())
            ];

            // 启动事务
            Db::startTrans();
            try {
                Db::table('re_company')->where('id', $re_company_id)->update($params_company_account_update);
                Db::table('re_recommenddetail')->where('id', $re_recommenddetail_id)->update($params_commend_detail_update);
                Db::table('cash_log')->insert($params_company_cash_log_update);  //"支付公司";
                //"入职用户";
                if($re_recommenddetail_info['lower_cash']>0){
                    Db::table('user')->where('id', $low_user_info['id'])->update($arr_update_low_user);
                    Db::table('cash_log')->insert($arr_update_low_user_cash_log);
                }

                //"上级用户";
                if($re_recommenddetail_info['up_cash']>0){
                    if(!empty($re_recommenddetail_info['up_user_id'])){
                        Db::table('user')->where('id', $up_user_info['id'])->update($arr_update_up_user);
                        Db::table('cash_log')->insert($arr_update_up_user_cash_log);
                    }
                }


                //平台
                if($re_recommenddetail_info['p_cash']>0){
                    Db::table('re_company')->where('id', $p_company_info['id'])->update($arr_update_up_p);
                    Db::table('cash_log')->insert($arr_update_p_company_cash_log);
                }


                /*  if($re_recommenddetail_info['agent_cash']!=0) {
                      //代理商
                      Db::table('re_company')->where('id', $a_company_info['id'])->update($arr_update_up_a);
                      Db::table('cash_log')->insert($arr_update_a_company_cash_log);
                  }*/
                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                //$this->error("网络繁忙,请稍后再试");exit;
            }
          //  $this->success();
        } else {
          //  $this->error("余额不足,请先充值");
        }
    }


    public function downMoney($admin_id,$total){
        $company_info = Db::table('re_company')->where('admin_id','=',$admin_id)->find();
        $ori_frozen = $company_info['frozen'];
        $ori_account = $company_info['account'];
        $ori_total = $ori_frozen + $ori_account;
        if ($ori_frozen > $total){
            $arr['frozen'] = $ori_frozen - $total;
            $result =  Db::table('re_company')->where('admin_id','=',$admin_id)->update($arr);
            return $arr;
        }elseif($ori_total>$total){
            $arr['frozen'] = 0;
            $arr['account'] = $ori_total - $total;
            $result =  Db::table('re_company')->where('admin_id','=',$admin_id)->update($arr);
            return $arr;
        }else{
            return 2;
        }

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

}
