<?php
namespace app\common\util;
use Firebase\JWT\JWT;

/**
 * oss类，处理静态资源
 * Class Oss
 * @package O2O\Util
 */
class JwtUtil
{

    private static $key = '';
    private static $iss = '';
    private static $expire_time = '';

    public function __construct()
    {
        $oss_config = config('jwt');
        $this->key = $oss_config['key'];
        $this->iss = $oss_config['iss'];
        $this->expire_time = $oss_config['expire_time'];
    }

    //签发Token
    public function signToken($user_data)
    {
        $time = time(); //当前时间
        $token = [
            'iss' => $this->iss, //签发者 可选
            'iat' => $time, //签发时间
            'exp' => $time+$this->expire_time,//过期时间,这里设置2个小时
            'data' => [ //自定义信息，不要定义敏感信息
                'openid' => $user_data['openid'],
            ]
        ];
        return JWT::encode($token, $this->key,'HS256'); //输出Token


    }

    //解析token
    public function analysisToken($token){
        $decoded = JWT::decode($token, $this->key,array('HS256'));
        return $decoded->data;
    }

}



