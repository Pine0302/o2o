<?php

namespace app\common\library;

use app\common\classes\OssClass;
use app\common\util\OssUtils;

/**
 * 图片上传到oss
 */
class Oss
{

    /**
     * @param $server_path  '/opt/app-root/src/';
     * @param $file_path     'public/upload/logo/2018/12-14/';
     * @param $file_name      '85fb7527281379cbdb4aabf732012a62.jpg';
     * @return mixed
     */
    public function  uploadPicToOs($server_path,$file_path,$file_name)
    {
        $OssObj = new OssClass();
        $ossUtilsObj = new OssUtils();
        $file_url = $OssObj->uploadPic($ossUtilsObj,$server_path,$file_path,$file_name);
        return $file_url;
    }

    public function checkPhoto($server_path,$file_path,$file_name){
        $OssObj = new OssClass();
        $ossUtilsObj = new OssUtils();
        $file_url = $OssObj->uploadPic($ossUtilsObj,$server_path,$file_path,$file_name);
        return $file_url;
    }
}
