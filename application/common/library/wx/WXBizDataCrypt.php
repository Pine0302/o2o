<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/21 0021
 * Time: 上午 10:44
 */
namespace app\common\library\wx;
 class WXBizDataCrypt
{

     private $appid;
     private $sessionKey;

     /**
      * 构造函数
      * @param $sessionKey string 用户在小程序登录后获取的会话密钥
      * @param $appid string 小程序的appid
      */
     public function __construct( $appid, $sessionKey)
     {

         $this->sessionKey = $sessionKey;
         $this->appid = $appid;
     }


     /**
      * 检验数据的真实性，并且获取解密后的明文.
      * @param $encryptedData string 加密的用户数据
      * @param $iv string 与用户数据一同返回的初始向量
      * @param $data string 解密后的原文
      *
      * @return int 成功0，失败返回对应的错误码
      */
     public function decryptData( $encryptedData, $iv, &$data )
     {
       //  error_log(var_export($this->sessionKey,1),3,"/data/wwwroot/milkteas.pinecc.cn/test.txt");

         if (strlen($this->sessionKey) != 24) {
           //  return ErrorCode::$IllegalAesKey;
             return config('mini.error_code.IllegalAesKey');
         }
         $aesKey=base64_decode($this->sessionKey);


         if (strlen($iv) != 24) {
       //      return ErrorCode::$IllegalIv;
             return config('mini.error_code.IllegalIv');
         }
         $aesIV=base64_decode($iv);

         $aesCipher=base64_decode($encryptedData);

         /*error_log(var_export($aesCipher,1),3,"/data/wwwroot/milkteas.pinecc.cn/test.txt");
         error_log(var_export($aesKey,1),3,"/data/wwwroot/milkteas.pinecc.cn/test.txt");*/


         $result=openssl_decrypt( $aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);

         //error_log(var_export($result,1),3,"/data/wwwroot/milkteas.pinecc.cn/test.txt");


         $dataObj=json_decode( $result );
         if( $dataObj  == NULL )
         {
            // return ErrorCode::$IllegalBuffer;
             return config('mini.error_code.IllegalBuffer');
         }
         if( $dataObj->watermark->appid != $this->appid )
         {
           //  return ErrorCode::$IllegalBuffer;
             return config('mini.error_code.IllegalBuffer');
         }
         $data = $result;
      //   return ErrorCode::$OK;
     //    return config('mini.error_code.OK');
         return $data;

     }

}
