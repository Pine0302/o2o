<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
/**
 * error code 说明.
 * <ul>

 *    <li>-41001: encodingAesKey 非法</li>
 *    <li>-41003: aes 解密失败</li>
 *    <li>-41004: 解密后得到的buffer非法</li>
 *    <li>-41005: base64加密失败</li>
 *    <li>-41016: base64解密失败</li>
 * </ul>
 */

return [
    'url' =>[
        'wx_login'=>'https://api.weixin.qq.com/sns/jscode2session',
    ],
    'error_code'=>[
        'ok' => 0 ,
        'IllegalAesKey' => -41001 ,
        'IllegalIv' => -41002 ,
        'IllegalBuffer' => -41003 ,
        'DecodeBase64Error' => -41004 ,
    ]

];
