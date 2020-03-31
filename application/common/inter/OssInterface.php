<?php

namespace app\common\inter;

interface OssInterface
{
    public function __construct();
    public function uploadServerPicToOss($server_path,$file_path,$file_name);
    public function checkFileExist($file_path,$file_name);

}
