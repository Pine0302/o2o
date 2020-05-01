<?php

/**
 * User: dyr
 * Date: 2017/11/24 0024
 * Time: 下午 3:00
 */

namespace app\common\behavior;
use app\common\logic\wechat\WechatUtil;
use app\common\library\Importer;
use think\Db;
use app\common\repository\CompanyRepository;
use app\common\repository\UserRepository;
class RiderCoin
{
    /**
     * @var CompanyRepository
     */
    private $companyRepository;

    /**
     * @var UserRepository;
     */
    private $userRepository;

    public function __construct()
    {
        $this->companyRepository = new CompanyRepository();
        $this->userRepository = new UserRepository();
    }

    public function run(&$info)
    {
        $file_Url = $info->getData('url');
        $file_path = $_SERVER['DOCUMENT_ROOT']."public".$file_Url;
        $importerObj = new Importer();
        $data = $importerObj->importExecl($file_path);
        //print_r($data);exit;
        $num = count($data)+1;
        $arr = [];
        $company_id = $data[2]['A'];

        for ($i=2;$i<$num;$i++){
            $arr[] = [
                'mobile'=>$data[$i]['B'],
                'coin'=>$data[$i]['C'],
            ];
        }
        if(!empty($arr)){
            array_map(function($charge) use ($company_id){
                $this->userRepository->updateUserMoneyByMobile($charge['mobile'],$charge['coin'],$company_id);
            },$arr);
        }
    }

}