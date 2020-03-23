<?php

namespace app\api\controller;

use app\common\controller\Api;
use \Firebase\JWT\JWT;

/**
 * 示例接口
 */
class Test extends Api
{

    public function test(){

        $key = "o2opine";
        $payload = array(
            "iss" => "http://example.org",
            "aud" => "http://example.com",
            "iat" => 1356999524,
            "nbf" => 1357000000,
            'sub' => '123123',
            'name' => 'shenchaosong',
            'openid' => 'qwe123'
        );

        /**
         * IMPORTANT:
         * You must specify supported algorithms for your application. See
         * https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40
         * for a list of spec-compliant algorithms.
         */
        $jwt = JWT::encode($payload, $key);
        $decoded = JWT::decode($jwt, $key, array('HS256'));

        print_r($jwt);
        print_r($decoded);

        /*
         NOTE: This will now be an object instead of an associative array. To get
         an associative array, you will need to cast it as such:
        */

        $decoded_array = (array) $decoded;

        /**
         * You can add a leeway to account for when there is a clock skew times between
         * the signing and verifying servers. It is recommended that this leeway should
         * not be bigger than a few minutes.
         *
         * Source: http://self-issued.info/docs/draft-ietf-oauth-json-web-token.html#nbfDef
         */
        JWT::$leeway = 60; // $leeway in seconds
        $decoded = JWT::decode($jwt, $key, array('HS256'));

    }

    public function tt(){
        print_r($_SERVER);exit;
        $key = "o2opine";
        $jwt = '';
        $decoded = JWT::decode($jwt, $key, array('HS256'));
        print_r($decoded);
    }
    

}
