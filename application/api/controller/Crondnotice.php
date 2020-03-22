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


class Crondnotice extends Api
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
    * 招聘推荐的自动发奖
    * */
    public function recruitSendMsg()
    {
        $date = date("Y-m-d H:i:s");
        $date_begin = date("Y-m-d 00:00:00");
        $recommend_list = Db::table('re_recommenddetail')
            ->where('status','=',2)
            ->where('timeline','neq','')
            ->select();

        foreach($recommend_list as $kr=>$vr){
            //if(($vr['timeline']<$date)&&($vr['status']==2)&($vr['timeline']>$date_begin)){
            if(($vr['timeline']>$date)&&($vr['status']==2)){
                    $this->sendRecruitMsg($vr);
                }
            //}
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
