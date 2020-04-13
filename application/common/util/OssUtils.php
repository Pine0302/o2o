<?php
namespace app\common\util;
use app\common\inter\OssInterface;
use OSS\OssClient;
use OSS\Core\OssException;

/**
 * oss类，处理静态资源
 * Class Oss
 * @package O2O\Util
 */
class OssUtils implements OssInterface
{

    private static $accessKeyId = '';       //阿里云主账号AccessKey拥有所有API的访问权限，风险很高。强烈建议您创建并使用RAM账号进行API访问或日常运维，请登录 https://ram.console.aliyun.com 创建RAM账号。
    private static $accessKeySecret = '';
    private static $endpoint = '';          // Endpoint以杭州为例，其它Region请按实际情况填写。
    private static $bucket = '';            // 设置存储空间名称。

    public function __construct()
    {
        $oss_config = config('oss');
        $this->accessKeyId = $oss_config['accessKeyId'];
        $this->accessKeySecret = $oss_config['accessKeySecret'];
        $this->endpoint = $oss_config['endpoint'];
        $this->bucket = $oss_config['bucket'];
        $this->domain = $oss_config['domain'];
    }

    /**
     * 把服务器上图片传到阿里云oss里
     * @param $server_path
     * @param $file_path
     * @param $file_name
     * @return string
     * @throws OssException
     */
    public function uploadServerPicToOss($server_path,$file_path,$file_name){
        $ossClient = new OssClient($this->accessKeyId, $this->accessKeySecret, $this->endpoint);
        // 设置oss文件名称。
        $object = $file_path.$file_name;
        //设置本地文件名称
        $local_file = $server_path.$file_path.$file_name;
       try{
            $ossClient->uploadFile($this->bucket,$object,$local_file);
        } catch(OssException $e) {
            return '';
          /*  printf(__FUNCTION__ . ": FAILED\n");
            printf($e->getMessage() . "\n");
            return;*/
        }
        return $this->domain.$object;
    }


    public function checkFileExist($file_path,$file_name){
        $object = $file_path.$file_name;
        $ossClient = new OssClient($this->accessKeyId, $this->accessKeySecret, $this->endpoint);
        $exist = $ossClient->doesObjectExist($this->bucket, $object);
        if($exist==1){
            return ['exist'=>1,'image'=>$this->domain.$object];
        }else{
            return ['exist'=>0];
        }
    }

    /**
     * 上传本地目录内的文件或者目录到指定bucket的指定prefix的object中
     *
     * @param string $bucket bucket名称
     * @param string $prefix 需要上传到的object的key前缀，可以理解成bucket中的子目录，结尾不能是'/'，接口中会补充'/'
     * @param string $localDirectory 需要上传的本地目录
     * @param string $exclude 需要排除的目录
     * @param bool $recursive 是否递归的上传localDirectory下的子目录内容
     * @param bool $checkMd5
     * @return array 返回两个列表 array("succeededList" => array("object"), "failedList" => array("object"=>"errorMessage"))
     * @throws OssException
     */
    public function uploadDir123($bucket, $prefix, $localDirectory, $exclude = '.|..|.svn|.git', $recursive = false, $checkMd5 = true){}


    //获取图片
    public function getImage($image,$image_oss){
        if(!empty($image_oss)){
            return $image_oss;
        }else{
            $server_image_url = "https://".config('server_name').$image;
            return $server_image_url;
        }
    }


}



