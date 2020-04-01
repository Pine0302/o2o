<?php

namespace app\api\controller;



use app\common\controller\Api;
use app\common\library\wx\WXBizDataCrypt;
use fast\Http;
use think\cache\driver\Redis;
use think\Db;
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

    public function login()
    {


        $data = $this->request->post();
        $this->wlog($data);
        $code = $data['code'];

        $mini_config_url = config('mini.url');
        $appid = config('Wxpay.APPID');
        $app_secret = config('Wxpay.APPSECRET');
        $login_url = $mini_config_url['wx_login']."?appid={$appid}&secret={$app_secret}&js_code={$code}&grant_type=authorization_code";
        $this->wlog($login_url);
        $result_json = Http::get($login_url);
        $result = json_decode($result_json,true);


        $sess_key = $this->rd3_session(16);

        //   error_log(var_export($result_json,1),3,"/data/wwwroot/mini3.pinecc.cn/runtime/test.txt");

        if(!empty($result['openid'])&&(!empty($result['session_key']))){

            $arr = [
                'openid'=>$result['openid'],
                'session_key'=>$result['session_key'],
                'sess_key'=>$sess_key,
            ];

            $this->wlog($arr);

            $result_set_redis = $this->redis->hmset($sess_key,$arr);
            $result = $this->registerUser($result['openid']);
            $auth_code = '1';
            $data = ['auth_code'=>$auth_code,'bind_mobile'=>1];
            $bizobj = ['data'=>$data];
            $this->success('成功', $bizobj);
        }else{
            $this->error('没有获取到数据');
        }

    }

    //注册用户
    public function registerUser($openid){
        $check_user = Db::name('users')->where('openid','=',$openid)->find();

        if(empty($check_user)){
            $data = [
                'openid'=>$openid,
                'is_new'=>1,
            ];
        }
        $this->wlog($data);

        Db::name('users')->insert($data);
        //给老用户发券
        if(!empty($rec_user_id)){
          //  $coupon_info = Db::name('plat_coupon')->where('is_rec','=',1)->find();
       //     $this->sendCoupon($rec_user_id,$coupon_info);
        }
        return 1;

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


    public function getUserMobile(){
       // $this->wlog('getUserMobile');
        $data = $this->request->post();
        $sess_key = $data['sess_key'];
        $sessionKey = $this->redis->hget($sess_key,'session_key');
        $appid = config('wxpay.APPID');
        $encryptedData = $data['encryptedData'];
        $iv = $data['iv'];



        $pc = new WXBizDataCrypt($appid, $sessionKey);

     //   $this->wlog($encryptedData);
     //   $this->wlog($iv);



        $mobile_info_json = $pc->decryptData($encryptedData, $iv, $data );

      // $this->wlog($mobile_info_json);

        $result = json_decode($mobile_info_json,true);


        if(!empty($result['purePhoneNumber'])){
            $user_info = $this->getGUserInfo($sess_key);
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


}
