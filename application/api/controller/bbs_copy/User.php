<?php

namespace app\api\controller\bbs;

use app\common\controller\Api;
use app\common\library\Ems;
use app\common\library\Sms;
use fast\Random;
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

    protected $noNeedLogin = ['blList','login', 'mobilelogin', 'register', 'resetpwd', 'changeemail', 'changemobile', 'third','userInfo','updateUserInfo','complain','shareQrPic','teamUserList','teamPrizeList','withdraw','withdrawList','checkUserResume','pic2UserInfo','getAccessToken','getPic','test1111','resumeFill','webInfo','testuser'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }




    //个人资料
    public function userInfo(){
     //  $data = $this->request->post();
        $data = $this->request->request();
        $sess_key = $data['sess_key'] ?? '';
     //   $data1 = file_get_contents("php://input");
        if(!empty($sess_key)){
            try {
                $arr = [  'openid', 'session_key' ];
                $sess_info = $this->redis->hmget($sess_key,$arr);
                $openid_re = $sess_info['openid'];
                $user_list = Db::table('user')
                    ->where('openid_re',$openid_re)
                    ->field('id,username,mobile,gender,birthday,available_balance')
                    ->find();

                $resume = Db::table('re_resume')
                    ->where('user_id',$user_list['id'])
                    ->find();
                if(empty($resume)||(empty($resume['name']))||(empty($resume['age']))||(empty($resume['id_num']))){
                    $resume_fill = 0;
                }else{
                    $resume_fill = 1;
                }



                $arr = [
                    'id'=>$user_list['id'],
                    'username'=>$user_list['username'],
                    'mobile'=>$user_list['mobile'],
                    'gender'=>$user_list['gender'],
                    'birthday'=>$user_list['birthday'],
                    'amount'=>$user_list['available_balance'],
                    'resume_fill'=>$resume_fill,
                ];
                $data = [
                    'data'=>$arr,
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
       // error_log(var_export($data,1),3,"/data/wwwroot/mini3.pinecc.cn/tt.txt");
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


    public function base64EncodeImage ($image_file) {
        $base64_image = '';
        $image_info = getimagesize($image_file);
        $image_data = fread(fopen($image_file, 'r'), filesize($image_file));
        $base64_image = 'data:' . $image_info['mime'] . ';base64,' . chunk_split(base64_encode($image_data));
        $base64_image_split = chunk_split(base64_encode($image_data));
        return $base64_image_split;
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




    public function testuser(){
        var_dump(123);
    }

}
