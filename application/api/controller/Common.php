<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\Area;
use app\common\model\Version;
use fast\Random;
use think\Config;
use fast\WaterMask;

/**
 * 公共接口
 */
class Common extends Api
{

    // protected $noNeedLogin = ['init'];
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 加载初始化
     *
     * @param string $version 版本号
     * @param string $lng 经度
     * @param string $lat 纬度
     */
    public function init()
    {
        if ($version = $this->request->request('version')) {
            $lng = $this->request->request('lng');
            $lat = $this->request->request('lat');
            $content = [
                'citydata'    => Area::getCityFromLngLat($lng, $lat),
                'versiondata' => Version::check($version),
                'uploaddata'  => Config::get('upload'),
                'coverdata'   => Config::get("cover"),
            ];
            $this->success('', $content);
        } else {
            $this->error(__('Invalid parameters'));
        }
    }

    /**
     * 上传文件
     *
     * @param File $file 文件流
     */
    public function upload()
    {
        $file = $this->request->file('file');
        if (empty($file)) {
            $this->error(__('No file upload or server upload limit exceeded'));
        }

        //判断是否已经存在附件
        $sha1 = $file->hash();

        $upload = Config::get('upload');

        preg_match('/(\d+)(\w+)/', $upload['maxsize'], $matches);
        $type = strtolower($matches[2]);
        $typeDict = ['b' => 0, 'k' => 1, 'kb' => 1, 'm' => 2, 'mb' => 2, 'gb' => 3, 'g' => 3];
        $size = (int)$upload['maxsize'] * pow(1024, isset($typeDict[$type]) ? $typeDict[$type] : 0);
        $fileInfo = $file->getInfo();
        $suffix = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
        $suffix = $suffix ? $suffix : 'file';

        $mimetypeArr = explode(',', strtolower($upload['mimetype']));
        $typeArr = explode('/', $fileInfo['type']);

        //验证文件后缀
        if ($upload['mimetype'] !== '*' &&
            (
                !in_array($suffix, $mimetypeArr)
                || (stripos($typeArr[0] . '/', $upload['mimetype']) !== false && (!in_array($fileInfo['type'], $mimetypeArr) && !in_array($typeArr[0] . '/*', $mimetypeArr)))
            )
        ) {
            $this->error(__('Uploaded file format is limited'));
        }
        $replaceArr = [
            '{year}'     => date("Y"),
            '{mon}'      => date("m"),
            '{day}'      => date("d"),
            '{hour}'     => date("H"),
            '{min}'      => date("i"),
            '{sec}'      => date("s"),
            '{random}'   => Random::alnum(16),
            '{random32}' => Random::alnum(32),
            '{filename}' => $suffix ? substr($fileInfo['name'], 0, strripos($fileInfo['name'], '.')) : $fileInfo['name'],
            '{suffix}'   => $suffix,
            '{.suffix}'  => $suffix ? '.' . $suffix : '',
            '{filemd5}'  => md5_file($fileInfo['tmp_name']),
        ];
        $savekey = $upload['savekey'];
        $savekey = str_replace(array_keys($replaceArr), array_values($replaceArr), $savekey);

        $uploadDir = substr($savekey, 0, strripos($savekey, '/') + 1);
        $fileName = substr($savekey, strripos($savekey, '/') + 1);
        //
        $splInfo = $file->validate(['size' => $size])->move(ROOT_PATH . '/public' . $uploadDir, $fileName);
        if ($splInfo) {
            $imagewidth = $imageheight = 0;
            if (in_array($suffix, ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'swf'])) {
                $imgInfo = getimagesize($splInfo->getPathname());
                $imagewidth = isset($imgInfo[0]) ? $imgInfo[0] : $imagewidth;
                $imageheight = isset($imgInfo[1]) ? $imgInfo[1] : $imageheight;
            }
            $params = array(
                'admin_id'    => 0,
                'user_id'     => (int)$this->auth->id,
                'filesize'    => $fileInfo['size'],
                'imagewidth'  => $imagewidth,
                'imageheight' => $imageheight,
                'imagetype'   => $suffix,
                'imageframes' => 0,
                'mimetype'    => $fileInfo['type'],
                'url'         => $uploadDir . $splInfo->getSaveName(),
                'uploadtime'  => time(),
                'storage'     => 'local',
                'sha1'        => $sha1,
            );
            $attachment = model("attachment");
            $attachment->data(array_filter($params));
            $attachment->save();
            \think\Hook::listen("upload_after", $attachment);
            $this->success(__('Upload successful'), [
                'url' => $uploadDir . $splInfo->getSaveName()
            ]);
        } else {
            // 上传失败获取错误信息
            $this->error($file->getError());
        }
    }


    //生成岗位详情页图片接口
    public function createApic($uid,$ori,$data){

        $time = time();
        $ori_2 = $time."_".$uid.".png";
        $recruit_title=$data['name'];
        $recruit_company=$data['company_name'];
        $recruit_lable=$data['keyword'];
        $recruit_salary=$data['salary']."元";
        $reward="入职奖".intval($data['reward']);
        $reward_up="推荐奖".intval($data['reward_up']);

        //生成招聘标题图片
        $recruit_title_file_name = $_SERVER['DOCUMENT_ROOT']."/shareimg/".$time."_".$uid."_"."title.png";
        $recruit_title_color = "3,99,177";
        $recruit_title_fontsize = 26;
        $recruit_title_font = 'SourceHanSerifSC-Bold.otf';

        $recruit_title_pic = $this->createWordPic(300,140,$recruit_title,$recruit_title_file_name,$recruit_title_color,$recruit_title_fontsize,$recruit_title_font);
        $r1 = $this->createSharePic("ori.png",$recruit_title_pic,15,140,1,$uid,$ori_2);

        unlink($recruit_title_file_name);

        if(($data['reward']!=0)&&($data['reward_up']!=0)){
            //生成入职奖
            $recruit_reward_file_name = $_SERVER['DOCUMENT_ROOT']."/shareimg/".$time."_".$uid."_"."reward.png";
            $recruit_reward_color = "255,97,48";
            $recruit_reward_fontsize = 22;
            $recruit_reward_font = 'SourceHanSerifSC-Bold.otf';
            $recruit_reward_pic = $this->createWordPic(600,120,$reward,$recruit_reward_file_name,$recruit_reward_color,$recruit_reward_fontsize,$recruit_reward_font);
            $r2 = $this->createSharePic($ori_2,$recruit_reward_pic,110,430,2,$uid,$ori_2);
            unlink($recruit_reward_pic);

            //生成分享奖
            $recruit_reward_up_file_name = $_SERVER['DOCUMENT_ROOT']."/shareimg/".$time."_".$uid."_"."reward_up.png";
            $recruit_reward_up_color = "255,97,48";
            $recruit_reward_up_fontsize = 22;
            $recruit_reward_up_font = 'SourceHanSerifSC-Bold.otf';
            $recruit_reward_up_pic = $this->createWordPic(600,120,$reward_up,$recruit_reward_up_file_name,$recruit_reward_up_color,$recruit_reward_up_fontsize,$recruit_reward_up_font);
            $r2 = $this->createSharePic($ori_2,$recruit_reward_up_pic,110,390,2,$uid,$ori_2);
            unlink($recruit_reward_up_pic);
        }elseif (($data['reward']==0)&&($data['reward_up']==0)){

        }elseif(($data['reward']==0)){
            //生成分享奖
            $recruit_reward_up_file_name = $_SERVER['DOCUMENT_ROOT']."/shareimg/".$time."_".$uid."_"."reward_up.png";
            $recruit_reward_up_color = "255,97,48";
            $recruit_reward_up_fontsize = 22;
            $recruit_reward_up_font = 'SourceHanSerifSC-Bold.otf';
            $recruit_reward_up_pic = $this->createWordPic(600,120,$reward_up,$recruit_reward_up_file_name,$recruit_reward_up_color,$recruit_reward_up_fontsize,$recruit_reward_up_font);
            $r2 = $this->createSharePic($ori_2,$recruit_reward_up_pic,110,410,2,$uid,$ori_2);
            unlink($recruit_reward_up_pic);
        }elseif($data['reward_up']==0){
            //生成入职奖
            $recruit_reward_file_name = $_SERVER['DOCUMENT_ROOT']."/shareimg/".$time."_".$uid."_"."reward.png";
            $recruit_reward_color = "255,97,48";
            $recruit_reward_fontsize = 22;
            $recruit_reward_font = 'SourceHanSerifSC-Bold.otf';
            $recruit_reward_pic = $this->createWordPic(600,120,$reward,$recruit_reward_file_name,$recruit_reward_color,$recruit_reward_fontsize,$recruit_reward_font);
            $r2 = $this->createSharePic($ori_2,$recruit_reward_pic,110,410,2,$uid,$ori_2);
            unlink($recruit_reward_pic);
        }

        //生成招聘公司图片
        $recruit_company_file_name = $_SERVER['DOCUMENT_ROOT']."/shareimg/".$time."_".$uid."_"."company.png";
        $recruit_company_color = "3,99,177";
        $recruit_company_fontsize = 26;
        $recruit_company_font = 'SourceHanSerifSC-Bold.otf';
        $recruit_company_pic = $this->createWordPic(600,120,$recruit_company,$recruit_company_file_name,$recruit_company_color,$recruit_company_fontsize,$recruit_company_font);
        $r2 = $this->createSharePic($ori_2,$recruit_company_pic,15,200,2,$uid,$ori_2);
        unlink($recruit_company_file_name);

        //生成标签
        $recruit_lable_file_name = $_SERVER['DOCUMENT_ROOT']."/shareimg/".$time."_".$uid."_"."lable.png";
        $recruit_lable_color = "3,99,177";
        $recruit_lable_fontsize = 26;
        $recruit_lable_font = 'SourceHanSerifSC-Bold.otf';
        $recruit_lable_pic = $this->createWordPic(600,130,$recruit_lable,$recruit_lable_file_name,$recruit_lable_color,$recruit_lable_fontsize,$recruit_lable_font);

        $r3 = $this->createSharePic($ori_2,$recruit_lable_pic,15,260,1,$uid,$ori_2);
        unlink($recruit_lable_file_name);

        //生成工资
        $recruit_salary_file_name = $_SERVER['DOCUMENT_ROOT']."/shareimg/".$time."_".$uid."_"."salary.png";
        $recruit_salary_color = "255,97,48";
        $recruit_salary_fontsize = 22;
        $recruit_salary_font = 'SourceHanSerifSC-Bold.otf';
        $recruit_salary_pic = $this->createWordPic(300,170,$recruit_salary,$recruit_salary_file_name,$recruit_salary_color,$recruit_salary_fontsize,$recruit_salary_font);
        $r4 = $this->createSharePic($ori_2,$recruit_salary_pic,420,135,1,$uid,$ori_2);
        unlink($recruit_salary_file_name);

        //生成二维码图片
        $post_img = "work_".$data['id']."_person_".$uid.".png";
        //   $post_img = "person_".$uid.".png";
        $qrocde_ori = $_SERVER['DOCUMENT_ROOT'].'/sharepic_work/'.$post_img;//需要加水印图片路径
        if (!file_exists("thumb_".$qrocde_ori)){
            $qrocde_new_pic = $this->scalePic($qrocde_ori,180,180,"thumb_");
        }else{
            $qrocde_new_pic = "thumb_".$qrocde_ori;
        }
        $r5 = $this->createSharePic($ori_2,$qrocde_new_pic,450,320,1,$uid,$ori_2);
        return $ori_2;
    }

    //生成活动详情页图片接口
    public function createTrainPic($uid,$ori,$data){

        $time = time();
        $ori_2 = $time."_".$uid.".png";
        $train_title=$data['name'];
        $train_person="人数:".$data['person'];
        $train_time="日期:".$data['train_time'];
        $reward_up="推荐奖:".floor($data['reward_up']);
        $fee= floor($data['fee']);

        //生成二维码图片
        $post_img = "train_".$data['id']."_person_".$uid.".png";
        //   $post_img = "person_".$uid.".png";

        //生成招聘标题图片
        $recruit_title_file_name = $_SERVER['DOCUMENT_ROOT']."/shareimg/".$time."_".$uid."_"."title.png";
        $recruit_title_color = "3,99,177";
        $recruit_title_fontsize = 26;
        $recruit_title_font = 'SourceHanSerifSC-Bold.otf';

        $recruit_title_pic = $this->createWordPic(500,140,$train_title,$recruit_title_file_name,$recruit_title_color,$recruit_title_fontsize,$recruit_title_font);

        $r1 = $this->createSharePic("ori.png",$recruit_title_pic,15,140,1,$uid,$ori_2);
       // var_dump(222);exit;
        //unlink($recruit_title_file_name);

        if(($data['reward_up']!=0)){
            //生成分享奖
            $recruit_reward_up_file_name = $_SERVER['DOCUMENT_ROOT']."/shareimg/".$time."_".$uid."_"."reward_up.png";
            $recruit_reward_up_color = "255,97,48";
            $recruit_reward_up_fontsize = 22;
            $recruit_reward_up_font = 'SourceHanSerifSC-Bold.otf';
            $recruit_reward_up_pic = $this->createWordPic(600,120,$reward_up,$recruit_reward_up_file_name,$recruit_reward_up_color,$recruit_reward_up_fontsize,$recruit_reward_up_font);
            $r2 = $this->createSharePic($ori_2,$recruit_reward_up_pic,110,430,2,$uid,$ori_2);
          //  unlink($recruit_reward_up_pic);
        }

        //生成招聘公司图片
        $recruit_company_file_name = $_SERVER['DOCUMENT_ROOT']."/shareimg/".$time."_".$uid."_"."person.png";
        $recruit_company_color = "3,99,177";
        $recruit_company_fontsize = 26;
        $recruit_company_font = 'SourceHanSerifSC-Bold.otf';
        $recruit_company_pic = $this->createWordPic(600,120,$train_person,$recruit_company_file_name,$recruit_company_color,$recruit_company_fontsize,$recruit_company_font);
        $r2 = $this->createSharePic($ori_2,$recruit_company_pic,15,200,2,$uid,$ori_2);
        //unlink($recruit_company_file_name);

        //生成标签
        $recruit_lable_file_name = $_SERVER['DOCUMENT_ROOT']."/shareimg/".$time."_".$uid."_"."time.png";
        $recruit_lable_color = "3,99,177";
        $recruit_lable_fontsize = 26;
        $recruit_lable_font = 'SourceHanSerifSC-Bold.otf';
        $recruit_lable_pic = $this->createWordPic(600,130,$train_time,$recruit_lable_file_name,$recruit_lable_color,$recruit_lable_fontsize,$recruit_lable_font);

        $r3 = $this->createSharePic($ori_2,$recruit_lable_pic,15,260,1,$uid,$ori_2);
        //unlink($recruit_lable_file_name);

        //生成工资
        $recruit_salary_file_name = $_SERVER['DOCUMENT_ROOT']."/shareimg/".$time."_".$uid."_"."fee.png";
        $recruit_salary_color = "255,97,48";
        $recruit_salary_fontsize = 22;
        $recruit_salary_font = 'SourceHanSerifSC-Bold.otf';
        $recruit_salary_pic = $this->createWordPic(300,170,$fee,$recruit_salary_file_name,$recruit_salary_color,$recruit_salary_fontsize,$recruit_salary_font);
        $r4 = $this->createSharePic($ori_2,$recruit_salary_pic,460,135,1,$uid,$ori_2);

        //生成工资
        $recruit_salary_file_name = $_SERVER['DOCUMENT_ROOT']."/shareimg/".$time."_".$uid."_"."yuan.png";
        $recruit_salary_color = "255,97,48";
        $recruit_salary_fontsize = 18;
        $recruit_salary_font = 'SourceHanSerifSC-Bold.otf';
        $recruit_salary_pic = $this->createWordPic(100,170,"元",$recruit_salary_file_name,$recruit_salary_color,$recruit_salary_fontsize,$recruit_salary_font);
        $r4 = $this->createSharePic($ori_2,$recruit_salary_pic,520,135,1,$uid,$ori_2);
      //  unlink($recruit_salary_file_name);

        $qrocde_ori = $_SERVER['DOCUMENT_ROOT'].'/sharepic_train/'.$post_img;//需要加水印图片路径
        if (!file_exists("thumb_".$qrocde_ori)){
            $qrocde_new_pic = $this->scalePic($qrocde_ori,180,180,"thumb_");
        }else{
            $qrocde_new_pic = "thumb_".$qrocde_ori;
        }

        $r5 = $this->createSharePic($ori_2,$qrocde_new_pic,450,320,1,$uid,$ori_2);
        return $ori_2;
        var_dump($ori_2);exit;
    }


    //生成分享图片
    public function createSharePic($ofile="ori.png",$post_img="person_5.png",$xposition=10,$yposition=10,$type=1,$uid="5",$ori_2){
        //1.二维码生成缩略图
        /* $qrocde_ori = $_SERVER['DOCUMENT_ROOT'].'/sharepic/'.$post_img;//需要加水印图片路径
         $qrocde_new = $this->scalePic($qrocde_ori,190,190,"thumb_");*/

        $ofile_arr = explode(".",$ofile);


        $file = $ofile_arr[0];
        /*$file = '58368dddc8c51_22';//需要加水印的图片
        $file_ext = '.jpeg';//扩展名*/
        $file_ext = $ofile_arr[1];
        //   $imgFileName = './'.$file.$file_ext;//需要加水印图片路径
        $imgFileName = $_SERVER['DOCUMENT_ROOT'].'/shareimg/'.$file.".".$file_ext;//需要加水印图片路径
        $obj = new WaterMask($imgFileName);  //实例化对象
        $obj->xposition = $xposition;       //x轴位置
        $obj->yposition = $yposition;       //y轴位置

        $obj->waterTypeStr = false;         //开启文字水印
        $obj->waterTypeImage = true;       //开启图片水印
        $obj->pos = 10;                 //定义水印图片位置
        //  $obj->waterImg = $_SERVER['DOCUMENT_ROOT'].'/sharepic/'.$post_img;            //水印图片
        $obj->waterImg = $post_img;            //水印图片
        $obj->transparent = 100;                   //水印透明度
        $obj->waterStr = '保险经纪人：刘测试  电话：02052552';             //水印文字
        $obj->fontSize = 9;                        //文字字体大小
        $obj->fontColor = array(0,0,0);                //水印文字颜色（RGB）
        $obj->fontFile = '/usr/share/fonts/microsoft_vista_kai.ttf';        //字体文件，这里是微软雅黑
        $obj->is_draw_rectangle = TRUE;            //开启绘制矩形区域
        // $ori_2 = $time."_".$uid.".png";
        //   $obj ->output_img =$_SERVER['DOCUMENT_ROOT'].'/shareimg/'.time()."_".$uid.".".$file_ext;//输出的图片路径
        $obj ->output_img =$_SERVER['DOCUMENT_ROOT'].'/shareimg/'.$ori_2;//输出的图片路径
        $return = $obj->output();
        return  ($obj ->output_img);



    }




    public function  createWordPic($length="300",$height="170",$text="招聘熟练工人",$filename="/data/wwwroot/mini3.pinecc.cn/public/shareimg/",$color="0,0,0",$font_size="18",$font="SourceHanSerifSC-Bold.otf"){
        //$text = iconv("gbk","utf-8",$text);//转码，避免乱码
        $block = imagecreatetruecolor($length,$height);//建立一个画板  x,y 宽度 高度
        $bg = imagecolorallocatealpha($block , 0 , 0 , 0 , 127);//拾取一个完全透明的颜色，不要用imagecolorallocate拾色
        $color_arr = explode(',',$color);
        //   $color = imagecolorallocate($block,255,0,0); //字体拾色
        $color = imagecolorallocate($block,$color_arr[0],$color_arr[1],$color_arr[2]); //字体拾色
        imagealphablending($block , false);//关闭混合模式，以便透明颜色能覆盖原画板
        imagefill($block , 0 , 0 , $bg);//填充
        $font = '/usr/share/fonts/'.$font;
        //  imagefttext($block,$font_size,0,10,20,$color,$font,$text);
        imagefttext($block,$font_size,0,20,30,$color,$font,$text);
        imagesavealpha($block , true);//设置保存PNG时保留透明通道信息
        ob_clean();
        header("content-type:image/png");
        imagepng($block,$filename);//生成图片
        imagedestroy($block);
        return $filename;
    }




    /**
     * @function 等比缩放函数(以保存的方式实现)
     * @param string $picname 被缩放的处理图片源
     * @param int $maxX 缩放后图片的最大宽度
     * @param int $maxY 缩放后图片的最大高度
     * @param string $pre 缩放后图片名的前缀名
     * @return string 返回后的图片名称(带路径),如a.jpg --> s_a.jpg
     */
    public function scalePic($picname,$maxX=200,$maxY=200,$pre='thumb_')
    {

        $info = getimagesize($picname); //获取图片的基本信息

        $width = $info[0];//获取宽度
        $height = $info[1];//获取高度

        //判断图片资源类型并创建对应图片资源
        $im = $this->getPicType($info[2],$picname);

        //计算缩放比例
        $scale = ($maxX/$width)>($maxY/$height)?$maxY/$height:$maxX/$width;

        //计算缩放后的尺寸
        $sWidth = floor($width*$scale);
        $sHeight = floor($height*$scale);
        //创建目标图像资源
        $nim = imagecreatetruecolor($sWidth,$sHeight);

        //等比缩放
        imagecopyresampled($nim,$im,0,0,0,0,$sWidth,$sHeight,$width,$height);
        //输出图像
        $newPicName = $this->outputImage($picname,$pre,$nim);
        //释放图片资源
        imagedestroy($im);
        imagedestroy($nim);
        return $newPicName;
    }


    /**
     * function 判断并返回图片的类型(以资源方式返回)
     * @param int $type 图片类型
     * @param string $picname 图片名字
     * @return 返回对应图片资源
     */
    public function getPicType($type,$picname)
    {
        //  ini_set('memory_limit','100M');
        $im=null;
        switch($type)
        {
            case 1:  //GIF
                $im = imagecreatefromgif($picname);
                break;
            case 2:  //JPG
                $im = imagecreatefromjpeg($picname);
                break;
            case 3:  //PNG
                $im = imagecreatefrompng($picname);
                break;
            case 4:  //BMP
                $im = imagecreatefromwbmp($picname);
                break;
            default:
                die("不认识图片类型");
                break;
        }
        return $im;
    }

    /**
     * function 输出图像
     * @param string $picname 图片名字
     * @param string $pre 新图片名前缀
     * @param resourse $nim 要输出的图像资源
     * @return 返回新的图片名
     */
    public function outputImage($picname,$pre,$nim)
    {
        $info = getimagesize($picname);
        $picInfo = pathInfo($picname);
        $newPicName = $picInfo['dirname'].'/'.$pre.$picInfo['basename'];//输出文件的路径
        switch($info[2])
        {
            case 1:
                imagegif($nim,$newPicName);
                break;
            case 2:
                imagejpeg($nim,$newPicName);
                break;
            case 3:
                imagepng($nim,$newPicName);
                break;
            case 4:
                imagewbmp($nim,$newPicName);
                break;
        }
        return $newPicName;
    }







}
