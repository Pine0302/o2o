<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Ems;
use app\common\library\Sms;
use app\common\repository\UserRepository;
use fast\Random;
use think\Request;
use think\Validate;
use think\Db;
use think\Cache;
use think\cache\driver\Redis;
use think\Session;
use fast\Http;
use fast\Wx;
use app\common\library\Order;
use fast\Ocr;

/**
 * 会员接口
 */
class User extends Api
{

    protected $noNeedLogin = ['blList','login', 'mobilelogin', 'register', 'resetpwd', 'changeemail', 'changemobile', 'third','userInfo','updateUserInfo','complain','shareQrPic','teamUserList','teamPrizeList','withdraw','withdrawList','checkUserResume','pic2UserInfo','bindPic2UserInfo','getAccessToken','getPic','test1111','resumeFill','webInfo','testuser','workDetailSharePic','getUserFormId','trainDetailSharePic'];
    protected $noNeedRight = '*';

    /**
     * @var UserRepository;
     */
    private $userRepository;

    public function __construct(Request $request = null,UserRepository $userRepository)
    {
        parent::__construct($request);
        $this->userRepository = $userRepository;
    }



    public function _initialize()
    {
        parent::_initialize();
    }


    //骑手认证
    public function validRider(){
        $data = $this->request->post();
        $openid = $this->analysisUserJwtToken();
        $user_info = $this->getGUserInfo($openid);
        if(!empty($user['mobile'])){
            $this->error('用户尚未认证手机号',null);
        }
        $valid_info = $this->userRepository->validRiderCompany($user_info['mobile']);
        $response =[];
        if(!empty($valid_info)){
            $response = [
                'data'=>$valid_info,
            ];
            $this->success('success', $response);
        }else{
            $this->error('暂无数据');
        }

    }

    //验证手机号
    public function validMobile(){
        $data = $this->request->post();
        $auth_code = $data['auth_code'] ?? '';
        $code = $data['code'] ?? '';
        $mobile = $data['mobile'] ?? '';
        try{
            $user_info = $this->getGUserInfo($sess_key);
            $openid = $user_info['openid'];
            $user_id = $user_info['user_id'];
            $profile_key = $user_info['openid']."_profile";
            $sess_code = $this->redis->get($profile_key);
            if($sess_code==$code){
                $arr_user_update = [
                    'mobile'=>$mobile,
                    'mobile_validated'=>1,
                    'weixin_mobile'=>$mobile,
                ];
                Db::name('users')->where('user_id','=',$user_id)->update($arr_user_update);
                $this->success('success', null);
            }else{
                $this->error('验证码有误',null);
            }
        }catch (Exception $e) {
            $this->error('网络繁忙,请稍后再试');
        }
    }

    /*
 * 搜集用户的formId
 */
    public function getUserFormId(){
        $this->wlog("点击了");
        $data = $this->request->post();
        $this->wlog($data);
        $sess_key = $data['sess_key'] ?? '';
        $forum = $data['forum'] ?? '';
        //   $forum = 'ff31fff480744ad380b87f3402aec468,d51564c52c942cf11e54907d678cfed4,9714fb2c858169c7843ba04a367fd2ef,5109ce68859c1127e254ecd432a942d5,64a0e5e1eb91dbeab34086d604efb150,ed70a6df6a523599d96a9d2c29f8d1d2,629fe9182ef6b9f00012b44f7af3e023,2024905471bb20a7084109b7e33636be,b30273e9e34d0a0d38333c93fd0c4cf8,23615b394e4b225fc86a35ce89b150b5,7610825903b1eb3c20f48d204f9f4140,5036488753bb592b7be294e178cb468f,8c734a9e72c93a1fe6d07071390390bd,7b1e1019772caa4b4e1e153ede0fda7f,b5c5b6527128383fa99e9c56a21ef860,bcd5a6384102f565d91ad2066432b66f,efe58d728ac1fc3978daa2c186bdca80,13d68734cf163451e05855f8d050b36f,a1ecf66dcaee685f6b25b3f644d6dbd4,3b11cfecd6a06b1e0b6e427ed6b79b8d';
        //error_log(var_export($data,1),3,"/data/wwwroot/mini3.pinecc.cn/tt.txt");exit;

        if((!empty($sess_key))&&(!empty($forum))){
            try{
                $arr = [  'openid', 'session_key' ];
                $sess_info = $this->redis->hmget($sess_key,$arr);
                $openid_re = $sess_info['openid'];
                $user_info = Db::name('users')
                    ->where('openid',$openid_re)
                    ->field('user_id as id,openid')
                    ->find();
                $uid = $user_info['id'];
                $formIds = explode(',',$forum);
                //$result = $result = $this->redis->rm('milkteaFormIdCollection_'.$uid);
                $this->wlog($uid);
                // error_log(var_export($formIds,1),3,$_SERVER['DOCUMENT_ROOT']."/tt.txt");
                $key_date = date("Y-m-d");
                $result = $this->redis->saddArr('milkteaFormIdCollection_'.$key_date."_".$uid,$formIds);
                $this->redis->expire('milkteaFormIdCollection_'.$key_date."_".$uid,60*60*24*6);
                // $data = $this->redis->smembers('recruitFormIdCollection_'.$uid);
                if(!empty($result)){
                    $this->success('success');
                }else{
                    $this->error('插入redis出错');
                }
            }catch (Exception $e) {
                $this->error('网络繁忙,请稍后再试');
            }
        }else{
            $this->error('缺少参数',null,2);
        }

    }

    //提交用户信息
    public function fillInfo(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'] ?? '';
        $nickname = $data['nick_name'] ?? '';
        $mobile = $data['mobile'] ?? '';
        $sex = $data['gender'] ?? '';
        $birthday = $data['$birthday'] ? strtotime($data['$birthday']) : '';
        $head_pic = $data['avatar'] ?? '';
        //if((!empty($is_rec))&&(!empty($areano))&&(!enpty($job_type))&&(!empty($sort_way))){
            try{
                $user_info = $this->getGUserInfo($sess_key);
                $user_id = $user_info['user_id'];
                $arr_user = [
                    'nickname' => $nickname,
                    'birthday' => $birthday,
                    'sex' => $sex,
                    'mobile' => $mobile,
                    'head_pic' => $head_pic,
                    'weixin_mobile'=>$mobile,
                    'valid_mobile'=>1,
                ];
                Db::name('users')->where('user_id','=',$user_id)->update($arr_user);
                $data = [
                    'data'=>null,
                ];
                $this->success('success', $data);
            }catch (Exception $e) {
                $this->error('网络繁忙,请稍后再试');
            }
    }






    //邀请列表
    public function userInviteList(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'] ?? '';
        try{
            $user_info = $this->getGUserInfo($sess_key);
            $user_id = $user_info['user_id'];
            $invite_arr = Db::name('users')->where('rec_user_id','=',$user_id)->select();
            $coupon_num = Db::name('users')
                ->where('rec_user_id','=',$user_id)
                ->where('rec_send','=',1)
                ->count();
            $invite_list = [];
            if(count($invite_arr)>0){
                foreach($invite_arr as $ki=>$vi){
                    $reg_time = date("Y-m-d H:i",$vi['reg_time']);
                    $invite_list[] = [
                        'reg_time'=>$reg_time,
                        'head_pic'=>$vi['head_pic'],
                        'nickname'=>$vi['nickname']
                    ];
                    $num = $coupon_num;

                }
            }
            $data = [
                'data'=>[
                    'invite_list'=>$invite_list,
                    'num'=>$num,
                ],
            ];
            $this->success('success', $data);
        }catch (Exception $e) {
            $this->error('网络繁忙,请稍后再试');
        }
    }



    //个人资料
    public function userInfo(){
        //  $data = $this->request->post();
        $data = $this->request->request();
        $sess_key = $data['sess_key'] ?? '';
        //   $data1 = file_get_contents("php://input");
        if(!empty($sess_key)){
            try {
                $user_info = $this->getGUserInfo($sess_key);
                $data = [
                    'data'=>$user_info,
                ];
                $this->success('success', $data);
            } catch (Exception $e) {
                $this->error('网络繁忙,请稍后再试');
            }
        }else{
            $this->error('缺少必要的参数',null,2);
        }
    }






    public function updateUserInfo()
    {
        $data = $this->request->post();

        $sess_key = $data['sess_key'] ?? '';
        $mobile = $data['mobile'] ?? '';
        $id = $data['id'] ?? '';
        $name = $data['name'] ?? '';
        $gender = $data['gender'] ?? 0;
        $birthday = $data['birthday'] ?? '';
        $sms_code = $data['sms_code'] ?? '';
        if ((!empty($sess_key)) && (!empty($mobile)) && (!empty($name))  && (!empty($birthday)) && (!empty($sms_code)) && (!empty($id)) ) {
            try {

                //验证验证码
                $user_info = Db::table('user')
                    ->where('id', $id)
                    ->field('id,username,mobile,gender,birthday,available_balance,openid_re')
                    ->find();
                $openid_re = $user_info['openid_re'];
                //发短信
                //生成随机6位数
                $code = rand(100000, 999999);
                $param = array(
                    'code' => $code,
                );
                $profile_key = $openid_re . "_profile";
                //  $code = Session::get($profile_key);
                $code = $this->redis->get($profile_key);
                //  error_log("缓存里的code".json_encode($code),3,"/data/wwwroot/mini3.pinecc.cn/runtime/test.txt");
                //   error_log("用户的code".json_encode($sms_code),3,"/data/wwwroot/mini3.pinecc.cn/runtime/test.txt");
                if($code==$sms_code){
                    //修改user表和resume表里面的信息
                    $update_user = [
                        'username'=>$name,
                        'mobile'=>$mobile,
                        'gender'=>$gender,
                        'birthday'=>$birthday,
                    ];
                    $update_re_resume = [
                        'name'=>$name,
                        'mobile'=>$mobile,
                        'sex'=>$gender,
                        'age'=>$birthday,
                    ];
                    $result_user =  Db::table('user')
                        ->where('id',$id)
                        ->update($update_user);
                    $check_resume = Db::table('re_resume')
                        ->where('user_id',$id)
                        ->find();
                    if(!empty($check_resume)){
                        $result_resume =  Db::table('re_resume')
                            ->where('user_id',$id)
                            ->update($update_re_resume);
                    }else{
                        $create_re_resume = [
                            'name'=>$name,
                            'user_id'=>$id,
                            'mobile'=>$mobile,
                            'sex'=>$gender,
                            'age'=>$birthday,
                            'create_at'=>date('Y-m-d H:i:s',time()),
                            'update_at'=>date('Y-m-d H:i:s',time()),
                        ];
                        $result_resume =  Db::table('re_resume')->insert($create_re_resume);
                    }

                    $this->success('修改成功');
                }else{
                    $this->error('短信验证码错误', null, 3);
                }

            } catch (Exception $e) {
                $this->error('网络繁忙,请稍后再试1');
            }
        } else {
            $this->error('缺少必要的参数', null, 2);
        }
    }

    //投诉建议
    public function complain()
    {
        $data = $this->request->post();
        $sess_key = $data['sess_key'] ?? '';
        $mobile = $data['mobile'] ?? '';
        $city = $data['city'] ?? '';
        $company_name = $data['company_name'] ?? '';
        $content = $data['content'] ?? '';
        $other = $data['other'] ?? '';
        if ((!empty($sess_key)) && (!empty($mobile)) && (!empty($city)) && (!empty($company_name)) && (!empty($content)) && (!empty($other)) ) {
            try {
                //获取用户信息
                $arr = [  'openid', 'session_key' ];
                $sess_info = $this->redis->hmget($sess_key,$arr);
                $openid_re = $sess_info['openid'];
                $user_info = Db::table('user')
                    ->where('openid_re',$openid_re)
                    ->field('id,username,mobile,gender,birthday,available_balance')
                    ->find();
                $arr_add = [
                    'user_id'=>$user_info['id'],
                    'mobile' => $mobile,
                    'city' => $city,
                    'company_name' => $company_name,
                    'content' => $content,
                    'other' => $other,
                    'create_at'=>date('Y-m-d H:i:s',time())
                ];
                $add_result = Db::table('user_complain')->insert($arr_add);
                if(!empty($add_result)){
                    $this->success('success');
                }else{
                    $this->error('网络繁忙,请稍后再试');
                }
            } catch (Exception $e) {
                $this->error('网络繁忙,请稍后再试');
            }
        } else {
            $this->error('缺少必要的参数', null, 2);
        }
    }

    public function getAccessToken(){
        $data = $this->request->request();
        $sess_key = $data['sess_key'] ?? '';
        if(!empty($sess_key)) {
            //获取access_token
            $wx_info = config('Wxpay');
            $arr = ['app_id' => $wx_info['APPID'], 'app_secret' => $wx_info['APPSECRET']];
            $wx = new Wx($arr);
            $access_token = $wx->getAccessToken();
            $data1['access_token'] = $access_token;
            $this->success('success', $data1);
        }else{
            $this->error('缺少必要的参数',null,2);
        }

    }


    //生成岗位详情页分享
    public function workDetailSharePic(){
        $data = $this->request->post();
        //   var_dump($data);exit;
        $id = isset($data['id']) ? $data['id'] : '';
        $sess_key = isset($data['sess_key']) ? $data['sess_key'] : '';
        //addUser2Team
        if((!empty($sess_key))&&(!empty($id))){
            $arr = [  'openid', 'session_key' ];
            $sess_info = $this->redis->hmget($sess_key,$arr);
            $openid_re = $sess_info['openid'];
            $user_info = Db::table('user')
                ->where('openid_re',$openid_re)
                ->field('id')
                ->find();
            $uid = $user_info['id'];

            if(!empty($uid)){

                $post_img = "work_".$id."_person_".$uid.".png";
                //查看是否有二维码缩略图 ,没有的话去生成
                if(!file_exists($_SERVER['DOCUMENT_ROOT']."/sharepic_work/".$post_img)){
                    //$start = microtime();

                    $this->createQrcodeApi($uid,$id);

                    /*$end = microtime();
                    $this->wlog($start);
                    $this->wlog($end);*/
                    //    sleep(0.5);
                }
                $work_detail =
                    Db::table('re_job')
                        ->alias('j')
                        ->join('re_company c','j.re_company_id = c.id')
                        ->field('j.name,j.max_salary,j.id,j.mini_salary,j.keyword,j.reward,j.reward_up,c.name as company_name,c.instruction as company_instruction,c.address as company_address')
                        ->where('j.id',$id)
                        ->find();
                $keyword_arr = explode("/",$work_detail['keyword']);
                $keyword_str="";
                if(count($keyword_arr)){
                    foreach($keyword_arr as $kk=>$vk){
                        $keyword_str = $vk." | ".$keyword_str;
                    }
                    $keyword_str =  substr($keyword_str,0,strlen($keyword_str)-3);
                }else{
                    $keyword_str = "";
                }
                $salary_info = intval($work_detail['mini_salary']) ."-".intval($work_detail['max_salary']);
                $arr_data = [
                    'salary' => $salary_info,
                    'id' => $work_detail['id'],
                    'keyword' => $keyword_str,
                    'name' =>  $work_detail['name'],
                    'company_name' => $work_detail['company_name'],
                    'reward' => $work_detail['reward'],
                    'reward_up' => $work_detail['reward_up'],
                ];
                $commonObj = new Common();
                $result = $commonObj->createApic($uid,'ori_new.png',$arr_data);
                $data = [
                    'data'=>"https://".$_SERVER['HTTP_HOST']."/shareimg/".$result,
                ];
                $this->success('success', $data);
            }else{
                $this->error('系统繁忙');
            }
        }else{
            $this->error('缺少参数',null,2);
        }
    }



    //微信生成分享二维码对内接口
    public function createQrcodeApi($uid,$work_id){
        $local_file_path = $_SERVER['DOCUMENT_ROOT']."/sharepic_work/work_".$work_id."_person_".$uid.".png";

        if(file_exists($local_file_path)){
            $arr_res['pic_url'] = "https://".$_SERVER['HTTP_HOST']."/sharepic_work/work_".$work_id."_person_".$uid.".png";
        }else{
            //获取access_token
            $wx_info = config('Wxpay');
            $arr = ['app_id'=>$wx_info['APPID'],'app_secret'=>$wx_info['APPSECRET']];
            $wx = new Wx($arr);
            //生成小程序二维码
            $page = "pages/personal/login";
            $page = "";
            //   error_log(var_export($user_info['id'],1),3,$_SERVER['DOCUMENT_ROOT']."/test.txt");
            $return_file_path = $wx->get_work_qrcode_unlimit($uid,$page,$work_id);

            $arr_res['pic_url'] = "https://".$_SERVER['HTTP_HOST']."/sharepic_work/".$return_file_path;
        }
        return $arr_res['pic_url'];
    }

    public function checkIsBindUser($user_info){
        $bind_info = Db::table('re_binduser')->where('user_id','=',$user_info['id'])->find();

        if(!empty($bind_info['re_company_id'])){
            //生成更可以跳转到企业页面的个人推荐二维码
            $local_file_path = $_SERVER['DOCUMENT_ROOT']."/sharepic/comp_".$bind_info['re_company_id']."_user_".$user_info['id'].".png";
       //     var_dump($local_file_path);
            if(file_exists($local_file_path)){
                $arr_res['pic_url'] = "http://".$_SERVER['HTTP_HOST']."/sharepic/comp_".$bind_info['re_company_id']."_user_".$user_info['id'].".png";
            }else{
                //获取access_token
                $wx_info = config('Wxpay');
                $arr = ['app_id'=>$wx_info['APPID'],'app_secret'=>$wx_info['APPSECRET']];
                $wx = new Wx($arr);
                //生成小程序二维码
                $page = "pages/personal/login";
                $page = "";
                //   error_log(var_export($user_info['id'],1),3,$_SERVER['DOCUMENT_ROOT']."/test.txt");
                $return_file_path = $wx->get_comp2user_qrcode_unlimit($bind_info['re_company_id'],$user_info['id'],$page);

                $arr_res['pic_url'] = "https://".$_SERVER['HTTP_HOST']."/sharepic/".$return_file_path;
            }
            if(!empty($arr_res['pic_url'])){
                $data = [
                    'data'=>$arr_res,
                ];
                //     error_log(var_export($data,1),3,"/data/wwwroot/mini3.pinecc.cn/tt.txt");
                $this->success('success', $data);
            }else{
                $this->error('网络繁忙,请稍后再试');
            }
        }
    }




//生成小程序二维码
    public function shareQrPic(){
        $data = $this->request->request();
        $sess_key = $data['sess_key'];

        if(!empty($sess_key)){
            try {
                $arr = [  'openid', 'session_key' ];
                $sess_info = $this->redis->hmget($sess_key,$arr);
                $openid_re = $sess_info['openid'];
                $user_info = Db::table('user')
                    ->where('openid_re',$openid_re)
                    ->field('id,username,mobile,gender,birthday,available_balance')
                    ->find();
                //如果该人是驻场,则走驻场流程
                $this->checkIsBindUser($user_info);

                $local_file_path = $_SERVER['DOCUMENT_ROOT']."/sharepic/person_".$user_info['id'].".png";
                if(file_exists($local_file_path)){
                    $arr_res['pic_url'] = "http://".$_SERVER['HTTP_HOST']."/sharepic/person_".$user_info['id'].".png";
                }else{
                    //获取access_token
                    $wx_info = config('Wxpay');
                    $arr = ['app_id'=>$wx_info['APPID'],'app_secret'=>$wx_info['APPSECRET']];
                    $wx = new Wx($arr);
                    //生成小程序二维码
                    $page = "pages/personal/login";
                    $page = "";
                    //   error_log(var_export($user_info['id'],1),3,$_SERVER['DOCUMENT_ROOT']."/test.txt");
                    $return_file_path = $wx->get_qrcode_unlimit($user_info['id'],$page);

                    $arr_res['pic_url'] = "http://".$_SERVER['HTTP_HOST']."/sharepic/".$return_file_path;
                }

                //     $this->wlog($arr_res['pic_url'],"tt.txt");
                if(!empty($arr_res['pic_url'])){
                    $data = [
                        'data'=>$arr_res,
                    ];
                    //     error_log(var_export($data,1),3,"/data/wwwroot/mini3.pinecc.cn/tt.txt");
                    $this->success('success', $data);
                }else{
                    $this->error('网络繁忙,请稍后再试');
                }
            } catch (Exception $e) {
                $this->error('网络繁忙,请稍后再试');
            }
        }else{
            $this->error('缺少必要的参数',null,2);
        }
    }


    public  function  getPic(){
        $data = $this->request->request();
        //   error_log(json_encode($data),3,"/data/wwwroot/mini3.pinecc.cn/runtime/test.txt");
        //  error_log(json_encode($_FILES),3,"/data/wwwroot/mini3.pinecc.cn/runtime/test.txt");
    }


















    public function base2pic($image){

        $imageName = "25220_".date("His",time())."_".rand(1111,9999).'.png';
        if (strstr($image,",")){
            $image = explode(',',$image);
            $image = $image[1];
        }

        //   $path = "/data/wwwroot/mini3.pinecc.cn/public/sharepic/".date("Ymd",time());
        $path = "/data/wwwroot/mini3.pinecc.cn/public/sharepic";
        if (!is_dir($path)){ //判断目录是否存在 不存在就创建
            mkdir($path,0777,true);
        }
        $imageSrc=  $path."/". $imageName;  //图片名字
        //  $r = file_put_contents(ROOT_PATH ."public/".$imageSrc, base64_decode($image));//返回的是字节数
        $r = file_put_contents($imageSrc, base64_decode($image));//返回的是字节数
        if (!$r) {
            return json(['data'=>null,"code"=>1,"msg"=>"图片生成失败"]);
        }else{
            return json(['data'=>1,"code"=>0,"msg"=>"图片生成成功"]);
        }

    }






    //团队列表
    public function  teamUserList(){
        $data = $this->request->post();
        $sess_key = isset($data['sess_key']) ? $data['sess_key'] : '';
        $page = isset($data['page']) ? $data['page'] : 1;
        $page_size = isset($data['page_size']) ? $data['page_size'] : 10;
        $this->wlog($sess_key);
        //if((!empty($is_rec))&&(!empty($areano))&&(!enpty($job_type))&&(!empty($sort_way))){
        if(!empty($sess_key)){
            try{

                $arr = [  'openid', 'session_key' ];
                $sess_info = $this->redis->hmget($sess_key,$arr);
                $openid_re = $sess_info['openid'];
                $user_info = Db::table('user')
                    ->where('openid_re',$openid_re)
                    ->field('id')
                    ->find();
                $this->wlog($user_info);

                $count = Db::table('user_team')
                    ->alias('t')
                    ->join('user u','u.id = t.low_user_id')
                    ->field('t.*,u.nickname,u.avatar')
                    ->where('t.up_user_id',$user_info['id'])
                    ->count();
                $this->wlog($count);

                $data = [];
                if(!empty($count)){
                    $page_info = [
                        'cur_page'=>$page,
                        'page_size'=>$page_size,
                        'total_items'=>$count,
                        'total_pages'=>ceil($count/$page_size)
                    ];

                    $team_list = Db::table('user_team')
                        ->alias('t')
                        ->join('user u','u.id = t.low_user_id')
                        ->field('t.*,u.nickname,u.avatar')
                        ->where('t.up_user_id',$user_info['id'])
                        ->order('id desc')
                        ->page($page,$page_size)
                        ->select();
                    $this->wlog($team_list);
                    if(!empty($team_list)){
                        foreach($team_list as $kw=>$vw){
                            $data1[] = [
                                'id'=>$vw['id'],
                                'nickname'=>$vw['nickname'],
                                'avatar'=>$vw['avatar'],
                                'user_id'=>$vw['low_user_id'],
                                'update_at'=>$vw['update_at'],
                            ];
                        }
                    }else{
                        $page_info = null;
                        $data1 = null;
                    }
                }else{
                    $page_info = null;
                    $data1 = null;
                }
                $data = [
                    'data'=>$data1,
                    'page_info'=>$page_info,
                ];
                $this->success('success', $data);
            }catch (Exception $e) {
                $this->error('网络繁忙,请稍后再试');
            }

        }else{
            $this->error('缺少参数',null,2);
        }
    }



    //我的奖励
    public function teamPrizeList_bak(){
        $data = $this->request->post();
        $sess_key = isset($data['sess_key']) ? $data['sess_key'] : '';
        $page = isset($data['page']) ? $data['page'] : 1;
        $page_size = isset($data['page_size']) ? $data['page_size'] : 10;
        if(!empty($sess_key)){
            try{
                $arr = [  'openid', 'session_key' ];
                $sess_info = $this->redis->hmget($sess_key,$arr);
                $openid_re = $sess_info['openid'];
                $user_info = Db::table('user')
                    ->where('openid_re',$openid_re)
                    ->field('id,username,mobile,gender,birthday,available_balance')
                    ->find();


                $count = Db::table('re_recommenddetail')
                    ->alias('r')
                    ->join('user u','u.id = r.low_user_id')
                    ->join('re_company c','c.id = r.re_company_id')
                    ->field('r.*,u.nickname,u.avatar,c.name')
                    ->where('r.up_user_id',$user_info['id'])
                    ->where('r.status',1)
                    ->count();
                if(!empty($count)){
                    $page_info = [
                        'cur_page'=>$page,
                        'page_size'=>$page_size,
                        'total_items'=>$count,
                        'total_pages'=>ceil($count/$page_size)
                    ];

                    $rec_list =  Db::table('re_recommenddetail')
                        ->alias('r')
                        ->join('user u','u.id = r.low_user_id')
                        ->join('re_company c','c.id = r.re_company_id')
                        ->field('r.*,u.nickname,u.avatar,c.name')
                        ->where('r.up_user_id',$user_info['id'])
                        ->where('r.status',1)
                        ->page($page,$page_size)
                        ->select();

                    if(!empty($rec_list)){
                        $data1 = [];
                        foreach($rec_list as $kr=>$vr){
                            $data1[] = [
                                'id' => $vr['id'],
                                'user_id' => $vr['low_user_id'],
                                'cash' => $vr['up_cash'],
                                'avatar' => $vr['avatar'],
                                'nickname' => $vr['nickname'],
                                're_company_name' => $vr['name'],
                                'create_at' => $vr['update_at'],
                            ];
                        }
                    }else{
                        $data_res = null;
                        $page_info = null;
                    }
                    $data = [
                        'data'=>$data1,
                        'page_info'=>$page_info,
                    ];
                }else{
                    $data = [
                        'data'=>null,
                        'page_info'=>null,
                    ];
                }

                $this->success('success', $data);
            }catch (Exception $e) {
                $this->error('网络繁忙,请稍后再试');
            }

        }else{
            $this->error('缺少参数',null,2);
        }
    }

//我的奖励
    public function teamPrizeList(){
        $data = $this->request->post();
        $sess_key = isset($data['sess_key']) ? $data['sess_key'] : '';
        $page = isset($data['page']) ? $data['page'] : 1;
        $page_size = isset($data['page_size']) ? $data['page_size'] : 10;
        if(!empty($sess_key)){
            try{
                $arr = [  'openid', 'session_key' ];
                $sess_info = $this->redis->hmget($sess_key,$arr);
                $openid_re = $sess_info['openid'];
                $user_info = Db::table('user')
                    ->where('openid_re',$openid_re)
                    ->field('id,username,nickname,mobile,gender,birthday,available_balance,avatar')
                    ->find();
                /*var_dump($user_info['id']);*/
                $count = Db::table('rec')
                    ->where('low_user_id='.$user_info['id'].' AND status=1')
                    ->whereOr('up_user_id='.$user_info['id'].' AND status=1')
                    ->count();

                if(!empty($count)){
                    $page_info = [
                        'cur_page'=>$page,
                        'page_size'=>$page_size,
                        'total_items'=>$count,
                        'total_pages'=>ceil($count/$page_size)
                    ];

                    $cash_list = Db::table('rec')
                        ->alias('r')
                        ->join('re_company c','r.re_company_id = c.id','left')
                        ->field('r.*,c.name as company_name')
                        ->where('r.low_user_id='.$user_info['id'].' AND r.status=1')
                        ->whereOr('r.up_user_id='.$user_info['id'].' AND r.status=1')
                        ->order('r.id desc')
                        ->page($page,$page_size)
                        ->select();
           

                    if(!empty($cash_list)){
                        foreach($cash_list as $kw=>$vw){
                            $update_at = empty($vw['update_at']) ? $vw['create_at'] : $vw['update_at'];
                            $total_days = round((strtotime($vw['timeline'])-strtotime($vw['create_at']))/(60*60*24));
                            $past_days = round((time()-strtotime($vw['create_at']))/(60*60*24));
                            $ratio = $past_days."/".$total_days;
                            if($vw['type']==1){
                                if($user_info['id'] == $vw['low_user_id']){    //入职奖励
                                    $method = "入职奖励";
                                }else{
                                    $method = "入职推荐奖励";
                                }
                            }else{
                                $method = "活动推荐奖励";
                            }
                            if($user_info['id']==$vw['low_user_id']){
                                $avatar = $user_info['avatar'];
                                $nickname = $user_info['nickname'];
                            }
                            if($user_info['id']==$vw['up_user_id']){
                                $low_user_info = Db::table('user')->where('id','=',$vw['low_user_id'])->find();
                                $avatar = $low_user_info['avatar'];
                                $nickname = $low_user_info['nickname'];
                            }
                            $data_res[] = [
                                'id'=>$vw['id'],
                                'method'=>$method,
                                'cash'=>$vw['lower_cash'],
                                'way'=>1,
                                'company_name'=>$vw['company_name'],
                                'update_at'=>$update_at,
                                'ratio'=>$ratio,
                                'avatar'=>$avatar,
                                'nickname'=>$nickname,
                            ];
                        }
                    }else{
                        $data_res = null;
                        $page_info = null;
                    }
                    $data = [
                        'data'=>$data_res,
                        'page_info'=>$page_info,
                    ];
                }else{
                    $data = [
                        'data'=>null,
                        'page_info'=>null,
                    ];
                }
                $this->success('success', $data);
            }catch (Exception $e) {
                $this->error('网络繁忙,请稍后再试');
            }

        }else{
            $this->error('缺少参数',null,2);
        }
    }

    //提现申请
    public function withdraw(){
        $data = $this->request->request();
        $sess_key = $data['sess_key'] ?? '';
        $cash = $data['cash'] ?? '';
        if((!empty($sess_key))&&(!empty($cash))){
            try {
                $arr = [  'openid', 'session_key' ];
                $sess_info = $this->redis->hmget($sess_key,$arr);
                $openid_re = $sess_info['openid'];
                $user_info = Db::table('user')
                    ->where('openid_re',$openid_re)
                    ->field('id,username,mobile,gender,birthday,available_balance,total_balance,frozen_balance')
                    ->find();
                //查看提现金额是否超出可提现余额
                /*var_dump($cash);
                var_dump($user_info['available_balance']);
                var_dump( $user_info['available_balance'] - $cash);*/
                if($cash>$user_info['available_balance']){
                    $this->error('提现金额超过可提现余额',null,3);exit;
                }else{
                    $orderObj = new Order();
                    //查看费率
                 /*   $withdraw_per_info = Db::table('re_ratio')->where('uid','=',1)->field('withdraw_per')->find();
                    if(!empty($withdraw_per)){   //除去费率之后金额
                        $cash = (100-$withdraw_per_info['withdraw_per'])/100 * $cash;
                    }*/
                    //个人信息修改
                    $update_user_info = [
                        'available_balance' => $user_info['available_balance'] - $cash,
                        'frozen_balance' => $user_info['frozen_balance'] + $cash,
                    ];

                    //提现申请单
                    $update_user_withdraw = [
                        'user_id' => $user_info['id'],
                        'cash' => $cash,
                        'status' => 0,
                        'create_at' => date('Y-m-d H:i:s'),
                        'update_at' => date('Y-m-d H:i:s'),
                        'order_id'=>$orderObj->createOrder2('P2U'),
                    ];

                    //提现记录
                    $update_cash_log = [
                        'user_id' => $user_info['id'],
                        'cash' => $cash,
                        'tip'=>'会员提现',
                        'way' => 2,
                        'type' => 3,
                        'status' => 0,
                        'update_at' => date('Y-m-d H:i:s'),
                    ];
                    /*       error_log(var_export($update_user_info,1),3,"/data/wwwroot/mini3.pinecc.cn/runtime/test.txt");
                           error_log(var_export($update_user_withdraw,1),3,"/data/wwwroot/mini3.pinecc.cn/runtime/test.txt");
                           error_log(var_export($update_cash_log,1),3,"/data/wwwroot/mini3.pinecc.cn/runtime/test.txt");*/
                    /*  var_dump($update_user_info);
                      var_dump($update_user_withdraw);
                      var_dump($update_cash_log);exit;*/


                    // 启动事务
                    Db::startTrans();
                    try {
                        $result0 = Db::table('user')->where('id', $user_info['id'])->update($update_user_info);
                        $result1 =  Db::table('user_withdraw')->insertGetId($update_user_withdraw);
                        $update_cash_log['with_id'] = $result1;
                        Db::table('cash_log')->insert($update_cash_log);
                        // 提交事务
                        Db::commit();
                        //   $this->success('success');

                    } catch (\Exception $e) {
                        // 回滚事务
                        Db::rollback();
                        $this->error("网络繁忙,请稍后再试");exit;
                    }
                    $this->success('success');
                }
            } catch (Exception $e) {
                $this->error('网络繁忙,请稍后再试');
            }
        }else{
            $this->error('缺少必要的参数',null,2);
        }
    }




    //提现列表
    public function  withdrawList(){
        $data = $this->request->post();
        $sess_key = isset($data['sess_key']) ? $data['sess_key'] : '';
        $page = isset($data['page']) ? $data['page'] : 1;
        $page_size = isset($data['page_size']) ? $data['page_size'] : 10;

        //if((!empty($is_rec))&&(!empty($areano))&&(!enpty($job_type))&&(!empty($sort_way))){
        if(!empty($sess_key)){
            try{

                $arr = [  'openid', 'session_key' ];
                $sess_info = $this->redis->hmget($sess_key,$arr);
                $openid_re = $sess_info['openid'];
                $user_info = Db::table('user')
                    ->where('openid_re',$openid_re)
                    ->field('id')
                    ->find();


                $count = Db::table('user_withdraw')
                    ->where('user_id',$user_info['id'])
                    ->where('status',1)
                    ->count();


                $data = [];
                if(!empty($count)){
                    $page_info = [
                        'cur_page'=>$page,
                        'page_size'=>$page_size,
                        'total_items'=>$count,
                        'total_pages'=>ceil($count/$page_size)
                    ];

                    $team_list = Db::table('user_withdraw')
                        ->where('user_id',$user_info['id'])
                        ->where('status',1)
                        ->page($page,$page_size)
                        ->select();
                    if(!empty($team_list)){
                        foreach($team_list as $kw=>$vw){
                            $data1[] = [
                                'id' => $vw['id'],
                                'cash' => $vw['cash'],
                                'create_at' => $vw['create_at'],
                            ];
                        }
                    }else{
                        $page_info = null;
                        $data = null;
                    }
                }else{
                    $page_info = null;
                    $data = null;
                }
                $data = [
                    'data'=>$data1,
                    'page_info'=>$page_info,
                ];
                $this->success('success', $data);
            }catch (Exception $e) {
                $this->error('网络繁忙,请稍后再试');
            }

        }else{
            $this->error('缺少参数',null,2);
        }
    }


    public function base64EncodeImage ($image_file) {
        $base64_image = '';
        $image_info = getimagesize($image_file);
        $image_data = fread(fopen($image_file, 'r'), filesize($image_file));
        $base64_image = 'data:' . $image_info['mime'] . ';base64,' . chunk_split(base64_encode($image_data));
        $base64_image_split = chunk_split(base64_encode($image_data));
        return $base64_image_split;
    }


    public function  test1111(){


        $wx_info = config('Wxpay');
        $arr = ['app_id'=>$wx_info['APPID'],'app_secret'=>$wx_info['APPSECRET']];
        $wx = new Wx($arr);
        $access_token = $wx->getAccessToken();
        // $wx->get_qrcode1($access_token,"pages/index/index",'430',true,'{"r":"0","g":"0","b":"0"}',false);
        $wx->get_qrcode_unlimit();


    }

    //src 图片完整路径
    //$direction 1顺时针90   2 逆时针90
    public function imgturn($src,$direction=1)
    {
        $ext = pathinfo($src)['extension'];
        switch ($ext) {
            case 'gif':
                $img = imagecreatefromgif($src);
                break;
            case 'jpg':
            case 'jpeg':
                $img = imagecreatefromjpeg($src);
                break;
            case 'png':
                $img = imagecreatefrompng($src);
                break;
            default:
                die('图片格式错误!');
                break;
        }
        $width = imagesx($img);
        $height = imagesy($img);
        $img2 = imagecreatetruecolor($height,$width);

        //顺时针旋转90度
        if($direction==1)
        {
            for ($x = 0; $x < $width; $x++) {
                for($y=0;$y<$height;$y++) {
                    imagecopy($img2, $img, $height-1-$y,$x, $x, $y, 1, 1);
                }
            }

        }else if($direction==2) {
            //逆时针旋转90度
            for ($x = 0; $x < $height; $x++) {
                for($y=0;$y<$width;$y++) {
                    imagecopy($img2, $img, $x, $y, $width-1-$y, $x, 1, 1);
                }
            }
        }

        switch ($ext) {
            case 'jpg':
            case "jpeg":
                imagejpeg($img2, $src, 100);
                break;

            case "gif":
                imagegif($img2, $src, 100);
                break;

            case "png":
                imagepng($img2, $src, 100);
                break;

            default:
                die('图片格式错误!');
                break;
        }
        imagedestroy($img);
        imagedestroy($img2);
    }


    //图片解析(用户后台人员上传)
    public function bindPic2UserInfo(){
        $data = $this->request->post();
     //   $this->wlog($_FILES);
        //   error_log(var_export($data,1),3,"/data/wwwroot/mini3.pinecc.cn/tt.txt");
        $file['img'] = $_FILES['pic_info'];

        $res = $this->upload1($file);

        $pic_url = "https://".$_SERVER['HTTP_HOST']."/upload/".$res;

        //   error_log(var_export($res,1),3,"/data/wwwroot/mini3.pinecc.cn/tt.txt");
        $pic_info = $this->base64EncodeImage($_SERVER['DOCUMENT_ROOT'].'/upload/'.$res);

        $sess_key = isset($data['sess_key']) ? $data['sess_key'] : '12212';

        if((!empty($sess_key))&&(!empty($pic_info))){
            try{

                /* $arr = [  'openid', 'session_key' ];
                 $sess_info = $this->redis->hmget($sess_key,$arr);
                 $openid_re = $sess_info['openid'];
                 $user_info = Db::table('user')
                     ->where('openid_re',$openid_re)
                     ->field('id,username,mobile,gender,birthday,available_balance')
                     ->find();
                 //error_log(var_export($result,1),3,"/data/wwwroot/mini3.pinecc.cn/runtime/test.txt");
                 $uid = $user_info['id'];*/


                $result = $this->getIdInfo2($pic_info);

                if (!empty($result)){
               /*     $result['name'] = urldecode($result['name']);
                    $result['user_address'] = urldecode($result['user_address']);
                    $result['nationality'] = urldecode($result['nationality']);*/

                    $result['pic_url'] =$pic_url;
                    //存入数据库,然后取出来
                    /*
                                       */

                    $data = [
                        'data'=>$result,
                    ];
                    $this->success('success',$data);
                }else{
                    $this->error('error1');
                }
            }catch (Exception $e) {
                $this->error('error2');
            }

        }else{
            $this->error('error3',null,2);
        }
    }

    //图片解析
    public function pic2UserInfo(){
        $data = $this->request->post();
        //   error_log(var_export($data,1),3,"/data/wwwroot/mini3.pinecc.cn/tt.txt");
        $file['img'] = $_FILES['pic_info'];
        $res = $this->upload1($file);
        $pic_url = "https://".$_SERVER['HTTP_HOST']."/upload/".$res;

        //   error_log(var_export($res,1),3,"/data/wwwroot/mini3.pinecc.cn/tt.txt");
        $pic_info = $this->base64EncodeImage($_SERVER['DOCUMENT_ROOT'].'/upload/'.$res);

        $sess_key = isset($data['sess_key']) ? $data['sess_key'] : '12212';

        if((!empty($sess_key))&&(!empty($pic_info))){
            try{

                /* $arr = [  'openid', 'session_key' ];
                 $sess_info = $this->redis->hmget($sess_key,$arr);
                 $openid_re = $sess_info['openid'];
                 $user_info = Db::table('user')
                     ->where('openid_re',$openid_re)
                     ->field('id,username,mobile,gender,birthday,available_balance')
                     ->find();
                 //error_log(var_export($result,1),3,"/data/wwwroot/mini3.pinecc.cn/runtime/test.txt");
                 $uid = $user_info['id'];*/



                $result = $this->getIdInfo2($pic_info);

                if (!empty($result)){
                    $result['pic_url'] =$pic_url;
                    //存入数据库,然后取出来
                    /*
                                       */

                    $data = [
                        'data'=>$result,
                    ];
                    $this->success('success',$data);
                }else{
                    $this->error('error1');
                }
            }catch (Exception $e) {
                $this->error('error2');
            }

        }else{
            $this->error('error3',null,2);
        }
    }
    public function upload1($data){
        if ((($data["img"]["type"] == "image/gif")
                || ($data["img"]["type"] == "image/jpeg")
                || ($data["img"]["type"] == "image/jpg")
                || ($data["img"]["type"] == "image/png")
                || ($data["img"]["type"] == "image/pjpeg"))
            && ($data["img"]["size"] < 10000000))
        {
            if ($data["img"]["error"] > 0)
            {
                return 3;//上传有误
            }
            else
            {
                $file_name = time().rand(10000,99999);
                $arr = explode('.',$data["img"]["name"]);
                $len_arr = count($arr);
                $type_name = $arr[$len_arr-1];
                move_uploaded_file($data["img"]["tmp_name"],$_SERVER['DOCUMENT_ROOT']."/upload/" . $file_name.".".$type_name);
                return  $file_name.".".$type_name ;
            }
        }
        else
        {
            return 2;
        }

    }




    public function getIdInfo2($file_path){

        // $file_path1 = $this->base64EncodeImage($file_path);
        //    $file_path1 = $this->bandle($file_path);

        $id2s_set = config('ocr');
        $arr=array(
            'app_code' => $id2s_set['app_code'],
            'app_key' => $id2s_set['app_key'],
            'app_secret' => $id2s_set['app_secret'],
            'url' => $id2s_set['url'],
            'type' => 1
        );

        $ocr = new Ocr($arr);
        $data = $ocr->analyzeId($file_path);

        $sex = ($data['sex']=="男") ? 1 : 2 ;


        if(!empty($data)){
            $param = [
                'name'=>urlencode($data['name']),
                'birth'=>$data['date_of_birth'],
                'user_address'=>urlencode($data['address']),
                'id_num'=>$data['card_no'],
                'sex'=>$sex,
                'nationality'=>urlencode($data['nation'])
            ];

        }else{
            //todo
            $param=[];
        }
        return $param;
    }




    //提交用户信息
    public function resumeFill(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'] ?? '';
        $birth = $data['birth'] ?? '';
        $id_num = $data['id_num'] ?? '';
        $name = $data['name'] ?? '';
        $nationality = $data['nationality'] ?? '';
        $sex = $data['sex'] ?? '';
        $user_address = $data['user_address'] ?? '';


        //if((!empty($is_rec))&&(!empty($areano))&&(!enpty($job_type))&&(!empty($sort_way))){
        if((!empty($sess_key))&&(!empty($birth))&&(!empty($id_num))&&(!empty($name))&&(!empty($nationality))&&(!empty($user_address))&&(!empty($sex))){
            try{

                $arr = [  'openid', 'session_key' ];
                $sess_info = $this->redis->hmget($sess_key,$arr);
                $openid_re = $sess_info['openid'];
                $user_info = Db::table('user')
                    ->where('openid_re',$openid_re)
                    ->field('id')
                    ->find();
                $uid = $user_info['id'];



                $arr_resume =[
                    'name'=>$name,
                    'sex'=>$sex,
                    'age'=>$birth,
                    'user_address'=>$user_address,
                    'nationality'=>$nationality,
                    'id_num'=>$id_num,
                ];

                $arr_user = [
                    'username' => $name,
                    'birthday' => $birth,
                    'gender' => $sex,
                ];
                Db::table('user')->where('id',$uid)->update($arr_user);
                $check_user_resume = Db::table('re_resume')->where('user_id',$uid)->find();
                if(!empty($check_user_resume)){
                    Db::table('re_resume')->where('user_id',$uid)->update($arr_resume);
                }else{
                    $arr_resume['user_id'] = $uid;
                    Db::table('re_resume')->insert($arr_resume);
                    $check_user_resume = Db::table('re_resume')->where('user_id',$uid)->find();
                }



                $data = [];

                $data = [
                    'data'=>null,

                ];
                $this->success('success', $data);
            }catch (Exception $e) {
                $this->error('网络繁忙,请稍后再试');
            }

        }else{
            $this->error('缺少参数',null,2);
        }
    }




    //小程序图标接口
    public function webInfo(){
        $data = $this->request->post();
        try{
            $data = [];
            $data = [
                'p_icon' => 'https://'.$_SERVER['HTTP_HOST']."/webinfo/lj.png",
                'p_name' => '链匠招聘',
            ];
            $data = [
                'data'=>$data,
            ];
            $this->success('success', $data);
        }catch (Exception $e) {
            $this->error('网络繁忙,请稍后再试');
        }
    }


    //生成活动详情页分享
    public function trainDetailSharePic(){
        $data = $this->request->post();
        //   var_dump($data);exit;
        $id = isset($data['id']) ? $data['id'] : '';
        $sess_key = isset($data['sess_key']) ? $data['sess_key'] : '';

        if((!empty($sess_key))&&(!empty($id))){
            $arr = [  'openid', 'session_key' ];
            $sess_info = $this->redis->hmget($sess_key,$arr);
            $openid_re = $sess_info['openid'];
            $user_info = Db::table('user')
                ->where('openid_re',$openid_re)
                ->field('id')
                ->find();

            $uid = $user_info['id'];

            if(!empty($uid)){

                $post_img = "train_".$id."_person_".$uid.".png";

                //查看是否有二维码缩略图 ,没有的话去生成
                if(!file_exists($_SERVER['DOCUMENT_ROOT']."/sharepic_train/".$post_img)){
                    //$start = microtime();

                    $result = $this->createQrcodeTrainApi($uid,$id);
                    //var_dump($result);exit;

                    /*$end = microtime();
                    $this->wlog($start);
                    $this->wlog($end);*/
                    //    sleep(0.5);
                }

                $train_detail =
                    Db::table('re_training')
                        ->alias('j')
                       /* ->join('re_company c','j.re_company_id = c.id')*/
                        ->field('j.id,j.name,j.train_time,j.fee,j.reward_up,j.max_person')
                        ->where('j.id',$id)
                        ->find();


                if(mb_strlen($train_detail['name'])>10){
                    $train_detail['name'] = mb_substr($train_detail['name'],0,9)."...";
                }
                $arr_data = [
                    'name' =>  $train_detail['name'],
                    'person' => $train_detail['max_person']."人",
                    'train_time' => date("Y.m.d",strtotime($train_detail['train_time'])),
                    'reward_up' => $train_detail['reward_up'],
                    'fee' => $train_detail['fee']."元",
                    'id' => $train_detail['id'],
                ];

                $commonObj = new Common();
                $result = $commonObj->createTrainPic($uid,'ori_new.png',$arr_data);
                $data = [
                    'data'=>"https://".$_SERVER['HTTP_HOST']."/shareimg/".$result,
                ];
                $this->success('success', $data);
            }else{
                $this->error('系统繁忙');
            }
        }else{
            $this->error('缺少参数',null,2);
        }
    }


    //微信生成分享二维码活动接口
    public function createQrcodeTrainApi($uid,$train_id){
        $local_file_path = $_SERVER['DOCUMENT_ROOT']."/sharepic_train/train_".$train_id."_person_".$uid.".png";

        if(file_exists($local_file_path)){
            $arr_res['pic_url'] = "https://".$_SERVER['HTTP_HOST']."/sharepic_train/train_".$train_id."_person_".$uid.".png";
        }else{
            //获取access_token
            $wx_info = config('Wxpay');
            $arr = ['app_id'=>$wx_info['APPID'],'app_secret'=>$wx_info['APPSECRET']];
            $wx = new Wx($arr);
            //生成小程序二维码
            $page = "pages/personal/login";
            $page = "";
            //   error_log(var_export($user_info['id'],1),3,$_SERVER['DOCUMENT_ROOT']."/test.txt");
            $return_file_path = $wx->get_work_qrcode_unlimit_train($uid,$page,$train_id);

            $arr_res['pic_url'] = "http://".$_SERVER['HTTP_HOST']."/sharepic_train/".$return_file_path;
        }
        return $arr_res['pic_url'];
    }

























































































































































    /**
     * 会员中心
     */
    public function index()
    {
        $this->success('', ['welcome' => $this->auth->nickname]);
    }


    /**
     * 会员登录
     *
     * @param string $account 账号
     * @param string $password 密码
     */
    public function login()
    {
        $account = $this->request->request('account');
        $password = $this->request->request('password');
        if (!$account || !$password)
        {
            $this->error(__('Invalid parameters'));
        }
        $ret = $this->auth->login($account, $password);
        if ($ret)
        {
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Logged in successful'), $data);
        }
        else
        {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 手机验证码登录
     *
     * @param string $mobile 手机号
     * @param string $captcha 验证码
     */
    public function mobilelogin()
    {
        $mobile = $this->request->request('mobile');
        $captcha = $this->request->request('captcha');
        if (!$mobile || !$captcha)
        {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$"))
        {
            $this->error(__('Mobile is incorrect'));
        }
        if (!Sms::check($mobile, $captcha, 'mobilelogin'))
        {
            $this->error(__('Captcha is incorrect'));
        }
        $user = \app\common\model\User::getByMobile($mobile);
        if ($user)
        {
            //如果已经有账号则直接登录
            $ret = $this->auth->direct($user->id);
        }
        else
        {
            $ret = $this->auth->register($mobile, Random::alnum(), '', $mobile, []);
        }
        if ($ret)
        {
            Sms::flush($mobile, 'mobilelogin');
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Logged in successful'), $data);
        }
        else
        {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 注册会员
     *
     * @param string $username 用户名
     * @param string $password 密码
     * @param string $email 邮箱
     * @param string $mobile 手机号
     */
    public function register()
    {
        $username = $this->request->request('username');
        $password = $this->request->request('password');
        $email = $this->request->request('email');
        $mobile = $this->request->request('mobile');
        if (!$username || !$password)
        {
            $this->error(__('Invalid parameters'));
        }
        if ($email && !Validate::is($email, "email"))
        {
            $this->error(__('Email is incorrect'));
        }
        if ($mobile && !Validate::regex($mobile, "^1\d{10}$"))
        {
            $this->error(__('Mobile is incorrect'));
        }
        $ret = $this->auth->register($username, $password, $email, $mobile, []);
        if ($ret)
        {
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Sign up successful'), $data);
        }
        else
        {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 注销登录
     */
    public function logout()
    {
        $this->auth->logout();
        $this->success(__('Logout successful'));
    }

    /**
     * 修改会员个人信息
     *
     * @param string $avatar 头像地址
     * @param string $username 用户名
     * @param string $nickname 昵称
     * @param string $bio 个人简介
     */
    public function profile()
    {
        $user = $this->auth->getUser();
        $username = $this->request->request('username');
        $nickname = $this->request->request('nickname');
        $bio = $this->request->request('bio');
        $avatar = $this->request->request('avatar');
        $exists = \app\common\model\User::where('username', $username)->where('id', '<>', $this->auth->id)->find();
        if ($exists)
        {
            $this->error(__('Username already exists'));
        }
        $user->username = $username;
        $user->nickname = $nickname;
        $user->bio = $bio;
        $user->avatar = $avatar;
        $user->save();
        $this->success();
    }

    /**
     * 修改邮箱
     *
     * @param string $email 邮箱
     * @param string $captcha 验证码
     */
    public function changeemail()
    {
        $user = $this->auth->getUser();
        $email = $this->request->post('email');
        $captcha = $this->request->request('captcha');
        if (!$email || !$captcha)
        {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::is($email, "email"))
        {
            $this->error(__('Email is incorrect'));
        }
        if (\app\common\model\User::where('email', $email)->where('id', '<>', $user->id)->find())
        {
            $this->error(__('Email already exists'));
        }
        $result = Ems::check($email, $captcha, 'changeemail');
        if (!$result)
        {
            $this->error(__('Captcha is incorrect'));
        }
        $verification = $user->verification;
        $verification->email = 1;
        $user->verification = $verification;
        $user->email = $email;
        $user->save();

        Ems::flush($email, 'changeemail');
        $this->success();
    }

    /**
     * 修改手机号
     *
     * @param string $email 手机号
     * @param string $captcha 验证码
     */
    public function changemobile()
    {
        $user = $this->auth->getUser();
        $mobile = $this->request->request('mobile');
        $captcha = $this->request->request('captcha');
        if (!$mobile || !$captcha)
        {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$"))
        {
            $this->error(__('Mobile is incorrect'));
        }
        if (\app\common\model\User::where('mobile', $mobile)->where('id', '<>', $user->id)->find())
        {
            $this->error(__('Mobile already exists'));
        }
        $result = Sms::check($mobile, $captcha, 'changemobile');
        if (!$result)
        {
            $this->error(__('Captcha is incorrect'));
        }
        $verification = $user->verification;
        $verification->mobile = 1;
        $user->verification = $verification;
        $user->mobile = $mobile;
        $user->save();

        Sms::flush($mobile, 'changemobile');
        $this->success();
    }

    /**
     * 第三方登录
     *
     * @param string $platform 平台名称
     * @param string $code Code码
     */
    public function third()
    {
        $url = url('user/index');
        $platform = $this->request->request("platform");
        $code = $this->request->request("code");
        $config = get_addon_config('third');
        if (!$config || !isset($config[$platform]))
        {
            $this->error(__('Invalid parameters'));
        }
        $app = new \addons\third\library\Application($config);
        //通过code换access_token和绑定会员
        $result = $app->{$platform}->getUserInfo(['code' => $code]);
        if ($result)
        {
            $loginret = \addons\third\library\Service::connect($platform, $result);
            if ($loginret)
            {
                $data = [
                    'userinfo'  => $this->auth->getUserinfo(),
                    'thirdinfo' => $result
                ];
                $this->success(__('Logged in successful'), $data);
            }
        }
        $this->error(__('Operation failed'), $url);
    }

    /**
     * 重置密码
     *
     * @param string $mobile 手机号
     * @param string $newpassword 新密码
     * @param string $captcha 验证码
     */
    public function resetpwd()
    {
        $type = $this->request->request("type");
        $mobile = $this->request->request("mobile");
        $email = $this->request->request("email");
        $newpassword = $this->request->request("newpassword");
        $captcha = $this->request->request("captcha");
        if (!$newpassword || !$captcha)
        {
            $this->error(__('Invalid parameters'));
        }
        if ($type == 'mobile')
        {
            if (!Validate::regex($mobile, "^1\d{10}$"))
            {
                $this->error(__('Mobile is incorrect'));
            }
            $user = \app\common\model\User::getByMobile($mobile);
            if (!$user)
            {
                $this->error(__('User not found'));
            }
            $ret = Sms::check($mobile, $captcha, 'resetpwd');
            if (!$ret)
            {
                $this->error(__('Captcha is incorrect'));
            }
            Sms::flush($mobile, 'resetpwd');
        }
        else
        {
            if (!Validate::is($email, "email"))
            {
                $this->error(__('Email is incorrect'));
            }
            $user = \app\common\model\User::getByEmail($email);
            if (!$user)
            {
                $this->error(__('User not found'));
            }
            $ret = Ems::check($email, $captcha, 'resetpwd');
            if (!$ret)
            {
                $this->error(__('Captcha is incorrect'));
            }
            Ems::flush($email, 'resetpwd');
        }
        //模拟一次登录
        $this->auth->direct($user->id);
        $ret = $this->auth->changepwd($newpassword, '', true);
        if ($ret)
        {
            $this->success(__('Reset password successful'));
        }
        else
        {
            $this->error($this->auth->getError());
        }
    }


    public function users(){
        echo 'herer';exit;
    }

    public function testuser(){
        $arr = [
            'mobile'=>'',
            'id_num'=>''
        ];
        Db::table('re_resume')
            ->where('user_id', 10)
            ->update($arr);
        Db::table('re_resume')
            ->where('user_id', 47)
            ->update($arr);
    }

}
