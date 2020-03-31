<?php

namespace app\common\classes;

use app\common\inter\OssInterface;

class OssClass
{
    public function uploadPic(OssInterface $Ioss,$server_path,$file_path,$file_name){
        $file_info = $Ioss->checkFileExist($file_path,$file_name);  //判断图片是否存在
        if($file_info['exist']==1){
            return $file_info['image'];
        }else{
            return $Ioss->uploadServerPicToOss($server_path,$file_path,$file_name);
        }
    }


}