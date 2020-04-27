<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 * Author: 当燃      
 * Date: 2015-09-09
 */
namespace app\admin\controller;

use app\common\repository\CompanyRepository;
use app\common\repository\UserRepository;
use think\Page;
use think\Db;
use app\admin\logic\ArticleCatLogic;

class Company extends Base {

    /**
     * @var CompanyRepository
     */
    private $companyRepository;

    /**
     * @var UserRepository;
     */
    private $userRepository;

    public function __construct(CompanyRepository $companyRepository,UserRepository $userRepository)
    {
        parent::__construct();
        $this->companyRepository = $companyRepository;
        $this->userRepository = $userRepository;
    }

    public function index(){
        $companyList = $this->companyRepository->companyList();
        $companyList = array_map(function($company){
            $company['update_time'] = date("Y-m-d H:i",$company['update_time']);
            return $company;
        },$companyList);
        $total = count($companyList);

        $this->assign('list',$companyList);

        $this->assign('total',$total);
        return $this->fetch('index');
    }


    /**
     * 添加修改编辑  短信模板
     */
    public  function addEditCompany(){

        $id = I('id/d',0);
        //print_r($id);exit;
        if(IS_POST)
        {
            $data = I('post.');
            $data['craete_time'] = time();

            if($id){
                $this->companyRepository->updateCompany($data,['id'=>$id]);
            }else{
                unset($data['id']);
              //  print_r($data);exit;
                $id = $this->companyRepository->addCompany($data);
            }
            $this->success("操作成功!!!",U('Admin/Company/index'));
            exit;
        }
        if($id){
            //进入编辑页面
             $info = $this->companyRepository->getCompanyById($id);
             $this->assign("info" , $info );
        }else{
            //进入添加页面
        }
        return $this->fetch("company");
    }



}