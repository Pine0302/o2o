<?php

namespace app\api\controller;



use app\common\controller\Api;
use app\common\library\wx\WXBizDataCrypt;
use app\common\repository\UserRepository;
use fast\Http;
use think\cache\driver\Redis;
use think\Db;
use think\Request;
use think\Session;
use think\Cache;
/**
 * 工作相关接口
 */
class Login extends Api
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
     * @var UserRepository;
     */
    private $userRepository;

    public function __construct(Request $request = null,UserRepository $userRepository)
    {
        parent::__construct($request);
        $this->userRepository = $userRepository;
    }

    public function login()
    {

        $data = $this->request->post();
        $code = $data['code'];

        $mini_config_url = config('mini.url');
        $appid = config('Wxpay.APPID');
        $app_secret = config('Wxpay.APPSECRET');
        $login_url = $mini_config_url['wx_login']."?appid={$appid}&secret={$app_secret}&js_code={$code}&grant_type=authorization_code";

        $this->wlog($login_url);
        $result_json = Http::get($login_url);
        $result = json_decode($result_json,true);
        if(IS_TEST){
            $result = [
                'openid'=>'oUQcI0bzIh2RXXaD5eN11QNnd9uo2',
                'session_key'=>'123123',
            ];
        }

        if(!empty($result['openid'])&&(!empty($result['session_key']))){
            $user_data = [
                'openid'=>$result['openid'],
                'session_key'=>$result['session_key'],
            ];
            $this->cacheUser($user_data);
            $auth_code = $this->signUserJwtToken($user_data);     //获取token
            $user_info = $this->registerUser($result['openid']);            //插入用户openid
            $bind_mobile = empty($user_info['mobile']) ? 2 : 1;
            $is_login = empty($user_info['is_login']) ? 2 : $user_info['is_login'];
            $data = ['auth_code'=>$auth_code,'bind_mobile'=>$bind_mobile];
            $bizobj = ['data'=>$data];
            $this->success('成功', $bizobj);
        }else{
            $this->error('没有获取到数据');
        }

    }

    //验证手机号
    public function getUserMobile(){
        $data = $this->request->post();
        $openid = $this->analysisUserJwtToken();
        $sessionKey = $this->redis->hget($openid,'session_key');

        $appid = config('wxpay.APPID');
        $encryptedData = $data['encryptedData'];
        $iv = $data['iv'];
        $pc = new WXBizDataCrypt($appid, $sessionKey);
        $mobile_info_json = $pc->decryptData($encryptedData, $iv, $data );
        $result = json_decode($mobile_info_json,true);

        if(IS_TEST){
            $result = [
                'purePhoneNumber'=>'19906721236',
            ];
        }

        if(!empty($result['purePhoneNumber'])){
            $user_info = $this->getGUserInfo($openid);
            if(!empty($user_info['user_id'])){
                Db::name('users')->where('user_id','=',$user_info['user_id'])->update(['weixin_mobile'=>$result['purePhoneNumber']]);
            }
        }else{
            $this->error('lostkey',null,10);exit;
        }
        $response = [
            'data'=>$result['purePhoneNumber']
        ];
        $this->success('success',$response);
    }


    //手机号登录
    public function mobileLogin(){
        $data = $this->request->post();
        $openid = $this->analysisUserJwtToken();
        $user_info = $this->getGUserInfo($openid);
        $mobile = $data['mobile'];
        $sms_code = $data['sms_code'];
        //验证密码
        $profile_key = $openid . "_profile";
        $code = $this->redis->get($profile_key);
        if($code==$sms_code){
            //给用户登录身份
            $this->userRepository->updateUserByFilter(['mobile'=>$mobile,'is_login'=>1],['openid'=>$openid]);
            $this->success('登录成功');
        }else{
            $this->success('短信验证码错误');
        }
    }


    //注册用户
    public function registerUser($openid){
        $check_user = Db::name('users')->where('openid','=',$openid)->find();
        if(empty($check_user)){
            $data = [
                'openid'=>$openid,
            ];
            Db::name('users')->insert($data);
            $check_user = $data;
        }
        return $check_user;
    }


    //给用户发券(单张)
    public function sendCoupon($user_id,$coupon_info){
        $code = uniqid($user_id).'_coupon_'.$user_id.'_'.rand(1000,9999); //单号

        $insert_coupon_list_arr = [
            'cid'=>$coupon_info['id'],
            'type'=>$coupon_info['type'],
            'uid'=>$user_id,
            'money'=>$coupon_info['money'],
            'condition'=>$coupon_info['condition'],
            'use_start_time'=>$coupon_info['use_start_time'],
            'use_end_time'=>$coupon_info['use_end_time'],
            'method'=>1,
            'code'=>$code,
            'send_time'=>time(),
            'status'=>0,
        ];

        $result = Db::name('coupon_list')->insert($insert_coupon_list_arr);
        if(!empty($result)){
            return $insert_coupon_list_arr;
        }


    }




    public function filterEmoji($str)
    {
        $str = preg_replace_callback( '/./u',
            function (array $match) {
                return strlen($match[0]) >= 4 ? '' : $match[0];
            },
            $str);
        return $str;
    }


    public function getUserInfo(){
        $this->wlog('getUserInfo');
        $data = $this->request->post();
        $sess_key = $data['sess_key'];
        $sessionKey = $this->redis->hget($sess_key,'session_key');
        $appid = config('wxpay.APPID');
        $encryptedData = $data['encryptedData'];
        $iv = $data['iv'];

        $pc = new WXBizDataCrypt($appid, $sessionKey);
        $user_info_json = $pc->decryptData($encryptedData, $iv, $data );

        $user_info_arr = json_decode($user_info_json,true);
        //$unionId = $user_info_arr['unionId'];
        $openId = $user_info_arr['openId'];

        if(!empty($openId)){
            //   $this->redis->hset($sess_key,'unionid',$unionId);
            $arr_user = [
                'openid'=>$user_info_arr['openId'],
                //  'unionid'=>$user_info_arr['unionId'],
                'nickname'=>$this->filterEmoji($user_info_arr['nickName']),
                'sex'=>$user_info_arr['gender'],
                'head_pic'=>$user_info_arr['avatarUrl'],

                'last_ip'=>$this->request->ip(),
                'last_login'=>time(),
                'reg_time'=>time(),
            ];

            //存数据库
            $checkUser = Db::name('users')->where('openid',$openId)->find();
            $this->wlog($arr_user);
            //  error_log(var_export($checkUser,1),3,$_SERVER['DOCUMENT_ROOT'].'/tt.txt');
            //     error_log(var_export($checkUser,1),3,$_SERVER['DOCUMENT_ROOT'].'/test.txt');
            if(empty($checkUser)){
                try {
                    Db::name('users')->insert($arr_user);
                }catch (Exception $e) {
                    $this->wlog($e);
                }
                $checkUser = Db::name('users')->where('openid',$openId)->find();
            }else{
                if(empty($checkUser['head_pic'])){
                    Db::name('users')->where('openid',$openId)->update($arr_user);
                }
            }

            /*   $resume = Db::table('re_resume')
                   ->where('user_id',$checkUser['id'])
                   ->find();
               if(empty($resume)||(empty($resume['name']))||(empty($resume['age']))||(empty($resume['id_num']))){
                   $resume_fill = 0;
               }else{
                   $resume_fill = 1;
               }

               $binduser = Db::table('re_binduser')->where('user_id','=',$checkUser['id'])->where('open','=',1)->find();
               if(!empty($binduser)){
                   $bind_fill = 1;
               }else{
                   $bind_fill = 2;
               }*/

            //查看用户是否有上级,没有的话,加上上级
            /*   if(!empty($rec_user_id)){
                   $rec_user_id_arr = explode("=",$rec_user_id);
                   $rec_user_id = $rec_user_id_arr[1];
                   $this->updateTeam($rec_user_id,$checkUser['id']);
               }

              ;*/
            $res_data = [
                'uid'=>$checkUser['user_id'],
                'nickname'=>$user_info_arr['nickName'],
                'gender'=>$user_info_arr['gender'],
                'avatar'=>$user_info_arr['avatarUrl'],
                'loginip'=>$this->request->ip(),
                'coupon_status'=>$checkUser['coupon_status'],

            ];
            $data = [
                'data'=>$res_data
            ];
            $this->success('success',$data);
        }else{
            $this->error('error');
        }

    }


    //添加上级
    public function updateTeam($up_user_id,$low_user_id){
        //先看看下级用户是否有上级,如果有则不管了,没有则添加上级
        $check_low_user = Db::table("user_team")->where('low_user_id',$low_user_id)->find();

        if(empty($check_low_user)){
            $arr =[
                'up_user_id' => $up_user_id,
                'low_user_id' => $low_user_id,
                'create_at' => date("Y-m-d H:i:s",time()),
                'update_at' => date("Y-m-d H:i:s",time()),
            ];
            Db::table('user_team')->insert($arr);
        }
    }


    public function  test(){
        $arr = [
            ''
        ];
        var_dump();
        //   $redis = Cache::getHandler();
        //   echo"</pre>";
        //   print_r($redis);
        //  $redis = new Redis();
        //  for ($i=0;$i<10;$i++){
        //  $redis->lpush('tets_list',$i);
        //   }
        //  print_r($redis->lrange('tets_list',0,-1));
        /*$redis->set('myname','scs890302');
        $myname = $redis->get('myname');*/
        //var_dump($myname);


    }

    public function rd3_session($len) {
        $fp = @fopen('/dev/urandom','rb');
        $result = '';
        if ($fp !== FALSE) {
            $result .= @fread($fp, $len);
            @fclose($fp);
        } else {
            trigger_error('Can not open /dev/urandom.');
        }
        // convert from binary to string
        $result = base64_encode($result);
        // remove none url chars
        $result = strtr($result, '+/', '-_');
        return substr($result, 0, $len);
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


}
