<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 * Author: 当燃      
 * Date: 2015-12-21
 */

namespace app\admin\controller;

use app\admin\logic\GoodsLogic;
use app\common\entity\MerchCashLogE;
use app\common\entity\OrderE;
use app\common\model\Order;
use think\Db;
use think\Page;
use app\common\repository\CompanyRepository;
use app\common\repository\UserRepository;


class Report extends Base
{

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

    public function _initialize(){
        parent::_initialize();

	}
	
	public function index(){
        ($this->begin > $this->end) && $this->error('起止时间选择错误！！！');
		$now = strtotime(date('Y-m-d'));
		$today['today_amount'] = M('order')->where("add_time>$now AND (pay_status=1 or pay_code='cod') and order_status in(1,2,4)")->sum('total_amount');//今日销售总额
		$today['today_order'] = M('order')->where("add_time>$now and (pay_status=1 or pay_code='cod') and order_status!=3")->count();//今日订单数
		$today['cancel_order'] = M('order')->where("add_time>$now AND order_status=3")->count();//今日取消订单
		if ($today['today_order'] == 0) {
			$today['sign'] = round(0, 2);
		} else {
			$today['sign'] = round($today['today_amount'] / $today['today_order'], 2);
		}
		$this->assign('today',$today);
        $select_year = $this->select_year;

        if(!empty($this->begin)&&(!empty($this->end))){
            $res = Db::name("order".$select_year)
                ->field(" COUNT(*) as tnum,sum(total_amount) as amount, FROM_UNIXTIME(add_time,'%Y-%m-%d') as gap ")
                ->where(" add_time >$this->begin and add_time < $this->end AND (pay_status=1 or pay_code='cod') and order_status in(1,2,4) ")
                ->group('gap')
                ->select();
        }else{
            $res = Db::name("order".$select_year)
                ->field(" COUNT(*) as tnum,sum(total_amount) as amount, FROM_UNIXTIME(add_time,'%Y-%m-%d') as gap ")
                ->where(" (pay_status=1 or pay_code='cod') and order_status in(1,2,4) ")
                ->group('gap')
                ->select();
        }

		foreach ($res as $val){
			$arr[$val['gap']] = $val['tnum'];
			$brr[$val['gap']] = $val['amount'];
			$tnum += $val['tnum'];
			$tamount += $val['amount'];
		}

		for($i=$this->begin;$i<=$this->end;$i=$i+24*3600){
			$tmp_num = empty($arr[date('Y-m-d',$i)]) ? 0 : $arr[date('Y-m-d',$i)];
			$tmp_amount = empty($brr[date('Y-m-d',$i)]) ? 0 : $brr[date('Y-m-d',$i)];
			$tmp_sign = empty($tmp_num) ? 0 : round($tmp_amount/$tmp_num,2);						
			$order_arr[] = $tmp_num;
			$amount_arr[] = $tmp_amount;			
			$sign_arr[] = $tmp_sign;
			$date = date('Y-m-d',$i);

			$start_time = $i;
			$end_time = $i + 24*60*60;
			$info = $this->saleOrderCount('',$start_time,$end_time);
			//print_r($info);


			$list[] = array(
			    'day'=>$date,
                'order_num'=>$info['total_count'],
                'amount'=>$info['total_amount'],
                'merch_amount'=>$info['total_merch'],
                'platfrom_amount'=>$info['total_platform'],
                'sign'=>$tmp_sign,
                'end'=>date('Y-m-d',$i+24*60*60));
			$day[] = $date;
		}

		!empty($list) && rsort($list);
		$this->assign('list',$list);
		$result = array('order'=>$order_arr,'amount'=>$amount_arr,'sign'=>$sign_arr,'time'=>$day);
		$this->assign('result',json_encode($result));
		return $this->fetch();
	}

    /**
     * 销量排行
     * @return mixed
     */
	public function saleTop(){
		$goods_name = I('goods_name');
		$where = [
            'od.pay_time'    => ['Between',"$this->begin,$this->end"],
            'od.order_status'=> ['notIN','3,5'],
            'og.is_send'    => 1,
        ];
        if(!empty($goods_name))$where['og.goods_name'] =['like', "%$goods_name%"];
         $count = Db::name('order_goods')->alias('og')
             ->field('sum(og.goods_num) as sale_num,sum(og.goods_num*og.goods_price) as sale_amount ')
             ->join('order od','og.order_id=od.order_id','LEFT')
             ->where($where)->group('og.goods_id')->count();
         $Page = new Page($count,$this->page_size);
         $res = Db::name('order_goods')->alias('og')
             ->field('og.goods_name,og.goods_id,og.goods_sn,sum(og.goods_num) as sale_num,sum(og.goods_num*og.goods_price) as sale_amount ')
             ->join('order od','og.order_id=od.order_id','LEFT')
             ->where($where)->group('og.goods_id')->order('sale_num DESC')
             ->limit($Page->firstRow,$Page->listRows)->cache(true,3600)->select();
		$this->assign('list',$res);
        $this->assign('page',$Page);
        $this->assign('p',I('p/d',1));
        $this->assign('page_size',$this->page_size);
		return $this->fetch();
	}

    /**
     * 统计报表 - 会员排行
     * @return mixed
     */
	public function userTop(){

		$mobile = I('mobile');
		$email = I('email');
        $order_where = [
            'o.add_time'=>['Between',"$this->begin,$this->end"],
            'o.pay_status'=>1,
            'o.order_status'=>['notIn','3,5']
        ];
		if($mobile){
			$user_where['mobile'] =$mobile;
		}		
		if($email){
            $user_where['email'] = $email;
		}
        if($user_where){   //有查询单个用户的条件就去找出user_id
            $user_id = Db::name('users')->where($user_where)->getField('user_id');
            $order_where['o.user_id']=$user_id;
        }

        $count = Db::name('order')->alias('o')->where($order_where)->group('o.user_id')->count();  //统计数量
        $Page = new Page($count,$this->page_size);
        $list = Db::name('order')->alias('o')
            ->field('count(o.order_id) as order_num,sum(o.total_amount) as amount,o.user_id,u.mobile,u.email,u.nickname')
            ->join('users u','o.user_id=u.user_id','LEFT')
            ->where($order_where)
            ->group('o.user_id')
            ->order('amount DESC')
            ->limit($Page->firstRow,$Page->listRows)
            ->cache(true)->select();   //以用户ID分组查询
        $this->assign('page',$Page);
        $this->assign('p',I('p/d',1));
        $this->assign('page_size',$this->page_size);
        $this->assign('list',$list);
		return $this->fetch();
	}

    /**
     * 用户订单
     * @return mixed
     */
    public function userOrder(){
        $orderModel = new Order();
        $user_id = trim(I('user_id'));
        // 搜索条件
        $condition=[
            'add_time'=>['Between',"$this->begin,$this->end"],
            'pay_status'=>1,
            'user_id' => $user_id,
            'order_status'=>['notIn','3,5'],
        ];
        $keyType = I("keytype");
        $keywords = I('keywords','','trim');

        $pay_code = input('pay_code');
        $order_sn = ($keyType && $keyType == 'order_sn') ? $keywords : I('order_sn') ;
        $order_sn ? $condition['order_sn'] = trim($order_sn) : false;
        $pay_code != '' ? $condition['pay_code'] = $pay_code : false;   //支付方式


        $count = $orderModel->where($condition)->count();
        $Page  = new Page($count,$this->page_size);
        $orderList = $orderModel->where($condition)
            ->limit("{$Page->firstRow},{$Page->listRows}")->order('add_time desc')->select();

        $this->assign('orderList',$orderList);
        $this->assign('user_id',$user_id);
        $this->assign('keywords',$keywords);
        $this->assign('page',$Page);// 赋值分页输出
        return $this->fetch();
    }

    public function saleOrder_bak(){
        $end_time = $this->begin+24*60*60;
        $order_where = "o.add_time>$this->begin and o.add_time<$end_time";  //交易成功的有效订单

        $order_count = Db::name('order')->alias('o')->where($order_where)->whereIn('order_status','1,2,4')->count();
        $Page = new Page($order_count,20);
        $order_list = Db::name('order')->alias('o')
            ->field('o.order_id,o.order_sn,o.goods_price,o.shipping_price,o.total_amount,o.add_time,u.user_id,u.nickname')
            ->join('users u','u.user_id = o.user_id','left')
            ->where($order_where)->whereIn('order_status','1,2,4')
            ->limit($Page->firstRow,$Page->listRows)->select();
        $this->assign('order_list',$order_list);
        $this->assign('page',$Page);
        return $this->fetch();
    }

    public function saleOrder(){

        $end_time = $this->begin+24*60*60;
        if($_REQUEST['start_time']&&($_REQUEST['end_time'])){
            $this->begin = strtotime($_REQUEST['start_time']);
            $end_time = strtotime($_REQUEST['end_time']);
        }
        $order_where = "c.create_time>$this->begin and c.create_time<$end_time";  //交易成功的有效订单
        $store_id = '';
        if(!empty($_REQUEST['store_name'])){
            $store_name = $_REQUEST['store_name'];
            $order_where .=" and ss.store_name = '$store_name' ";
            $store_info = Db::name('store_sub')->where('store_name','=',$store_name)->find();
            $store_id = $store_info['store_id'];
        }
        $is_export = isset($_REQUEST['is_export']) ? $_REQUEST['is_export'] : 0;

        if($is_export == 1){

            $cash_total_list = Db::name('merch_cash_log')
                ->alias('c')
                ->join('tp_order o', 'o.order_sn = c.order_no', 'INNER')
                ->field('o.order_id,o.order_sn,o.goods_price,o.shipping_price,o.total_amount,o.order_status,o.add_time,u.user_id,u.nickname,c.*,o.mobile,o.package_fee,ss.store_name')
                ->join('users u','u.user_id = o.user_id','left')
                ->join('tp_store_sub ss','ss.store_id = o.store_id','left')
                ->where($order_where)
                ->whereIn('c.type','1,3')
                ->order(['c.id'=>'desc'])
                ->select();


            $cash_total_list = array_map(function($cash){
                if($cash['type']==MerchCashLogE::TYPE['merch_retreat']){
                    $fuhao = "-";
                }else{
                    $fuhao = '';
                }
                $cash['platform_profit'] = $fuhao.($cash['ori_cash'] - $cash['cash']);
                $cash['cash'] = $fuhao.($cash['cash']);
                $cash['order_status_ch'] = OrderE::ORDER_STATUS_TIP[$cash['order_status']];
                $cash['cash_status_ch'] = MerchCashLogE::STATUS_CH[$cash['status']];
                //获取商户公司
                $company_info= $this->companyRepository->getCompanyByRiderId($cash['user_id']);
                if(!empty($company_info)){
                    $cash['company_name'] = $company_info['name'];
                }else{
                    $cash['company_name'] = '-';
                }
                $cash['add_time'] = date("Y-m-d H:i:s",$cash['add_time']);
                return $cash;
            },$cash_total_list);




            $strTable ='<table width="500" border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">id</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单编号</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">商品总价</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">餐盒费</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单总价</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">商家收入</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">平台利润</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">商户名称</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">骑手公司</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="100">订单状态</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="100">收益状态</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="100">下单日期</td>';
            $strTable .= '</tr>';
            if(is_array($cash_total_list)){
                //$region	= get_region_list();
                foreach($cash_total_list as $k=>$val){
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['order_id'].'</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['order_sn'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_price'].' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['package_fee'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'."{$val['total_amount']}".' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['cash'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['platform_profit'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['store_name'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['company_name'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['order_status_ch'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['cash_status_ch'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['add_time'].'</td>';
                    $strTable .= '</tr>';
                }
            }
            $strTable .='</table>';
            unset($cash_total_list);
            downloadExcel($strTable,'cash');
            exit();
        }

        $order_count = Db::name('merch_cash_log')
            ->alias('c')
            ->join('tp_order o', 'o.order_sn = c.order_no', 'INNER')
            ->join('tp_store_sub ss','ss.store_id = o.store_id','left')
            ->where($order_where)
            ->whereIn('c.type','1,3')
            ->count();
        $Page = new Page($order_count,20);


        $cash_list = Db::name('merch_cash_log')
            ->alias('c')
            ->join('tp_order o', 'o.order_sn = c.order_no', 'INNER')
            ->field('c.*,o.order_id,o.order_sn,o.goods_price,o.shipping_price,o.total_amount,o.order_status,o.add_time,o.user_id as user_id,u.nickname,o.mobile,o.package_fee,ss.store_name')
            ->join('users u','u.user_id = o.user_id','left')
            ->join('tp_store_sub ss','ss.store_id = o.store_id','left')
            ->where($order_where)
            ->whereIn('c.type','1,3')
            ->order(['c.id'=>'desc'])
            ->limit($Page->firstRow,$Page->listRows)
            ->select();


        $cash_list = array_map(function($cash){
            if($cash['type']==MerchCashLogE::TYPE['merch_retreat']){
                $fuhao = "-";
            }else{
                $fuhao = '';
            }
            $cash['platform_profit'] = $fuhao.($cash['ori_cash'] - $cash['cash']);
            $cash['cash'] = $fuhao.($cash['cash']);
            $cash['order_status_ch'] = OrderE::ORDER_STATUS_TIP[$cash['order_status']];
            $cash['cash_status_ch'] = MerchCashLogE::STATUS_CH[$cash['status']];
            //获取商户公司
            $company_info= $this->companyRepository->getCompanyByRiderId($cash['user_id']);
            if(!empty($company_info)){
                $cash['company_name'] = $company_info['name'];
            }else{
                $cash['company_name'] = '--';
            }
            return $cash;
        },$cash_list);
        $start_time = $this->begin;
        $end_time = $end_time;
        $info = $this->saleOrderCount($store_id,$start_time,$end_time);

        $this->assign('total_amount',$info['total_amount']);
        $this->assign('total_count',$info['total_count']);
        $this->assign('total_merch',$info['total_merch']);
        $this->assign('total_platform',$info['total_platform']);
        $this->assign('order_list',$cash_list);
        $this->assign('store_name',$store_name);
        $this->assign('page',$Page);
        return $this->fetch();
    }

    public function saleOrderCount($store_id="",$start_time,$end_time){


        $order_where = "c.create_time>$start_time and c.create_time<$end_time";  //交易成功的有效订单

        if(!empty($store_id)){

            $order_where .=" and ss.store_id = '$store_id' ";
        }

        $cash_total_list = Db::name('merch_cash_log')
            ->alias('c')
            ->join('tp_order o', 'o.order_sn = c.order_no', 'INNER')
            ->field('o.order_id,o.order_sn,o.goods_price,o.shipping_price,o.total_amount,o.order_status,o.add_time,u.user_id,u.nickname,c.*,o.mobile,o.package_fee,ss.store_name')
            ->join('users u','u.user_id = o.user_id','left')
            ->join('tp_store_sub ss','ss.store_id = o.store_id','left')
            ->where($order_where)
            ->whereIn('c.type','1,3')
            ->order(['c.id'=>'desc'])
            ->select();

        $total_amount = 0;
        $total_count = 0;
        $total_merch = 0;

        $cash_total_list = array_map(function($cash) use(&$total_amount,&$total_merch,&$total_count){
            if($cash['type']==MerchCashLogE::TYPE['merch_retreat']){
                $fuhao = "-";
                $total_amount -= $cash['ori_cash'];
                $total_merch -= $cash['cash'];
                $total_count--;
            }else{
                $fuhao = '';
                $total_amount += $cash['ori_cash'];
                $total_merch += $cash['cash'];
                $total_count++;
            }
        },$cash_total_list);

        $arr = [
            'total_amount'=>$total_amount,
            'total_count'=>$total_count,
            'total_merch'=>$total_merch,
            'total_platform'=>$total_amount-$total_merch,
        ];
        return $arr;

    }




    /**
     * 销售明细列表
     */
	public function saleList(){
        $cat_id = I('cat_id',0);
        $brand_id = I('brand_id',0);
        $goods_id = I('goods_id',0);
        $where = "o.add_time>$this->begin and o.add_time<$this->end and o.order_status in(1,2,4) and og.is_send = 1 ";  //交易成功的有效订单
        if($cat_id>0){
            $where .= " and (g.cat_id=$cat_id or g.extend_cat_id=$cat_id)";
            $this->assign('cat_id',$cat_id);
        }
        if($brand_id>0){
            $where .= " and g.brand_id=$brand_id";
            $this->assign('brand_id',$brand_id);
        }

        if($goods_id >0){
        	$where .= " and og.goods_id=$goods_id";
        }
        $count = Db::name('order_goods')->alias('og')
            ->join('order o','og.order_id=o.order_id ','left')
            ->join('goods g','og.goods_id = g.goods_id','left')
            ->where($where)->count();  //统计数量
        $Page = new Page($count,20);
        $show = $Page->show();

        $res = Db::name('order_goods')->alias('og')->field('og.*,o.user_id,o.order_sn,o.shipping_name,o.pay_name,o.add_time,og.spec_key_name')
            ->join('order o','og.order_id=o.order_id ','left')
            ->join('goods g','og.goods_id = g.goods_id','left')
            ->where($where)->limit($Page->firstRow,$Page->listRows)
            ->order('o.add_time desc')->select();
        $this->assign('list',$res);
        $this->assign('pager',$Page);
        $this->assign('page',$show);

        $GoodsLogic = new GoodsLogic();
        $brandList = $GoodsLogic->getSortBrands();  //获取排好序的品牌列表
        $categoryList = $GoodsLogic->getSortCategory(); //获取排好序的分类列表
        $this->assign('categoryList',$categoryList);
        $this->assign('brandList',$brandList);
        return $this->fetch();
	}
	
	public function user(){
		$today = strtotime(date('Y-m-d'));
		$month = strtotime(date('Y-m-01'));
		$user['today'] = D('users')->where("reg_time>$today")->count();//今日新增会员
		$user['month'] = D('users')->where("reg_time>$month")->count();//本月新增会员
		$user['total'] = D('users')->count();//会员总数
		$user['user_money'] = D('users')->sum('user_money');//会员余额总额
		$res = M('order')->cache(true)->distinct(true)->field('user_id')->select();
		$user['hasorder'] = count($res);
		$this->assign('user',$user);
		$sql = "SELECT COUNT(*) as num,FROM_UNIXTIME(reg_time,'%Y-%m-%d') as gap from __PREFIX__users where reg_time>$this->begin and reg_time<$this->end group by gap";
		$new = DB::query($sql);//新增会员趋势
		foreach ($new as $val){
			$arr[$val['gap']] = $val['num'];
		}
		
		for($i=$this->begin;$i<=$this->end;$i=$i+24*3600){
			$brr[] = empty($arr[date('Y-m-d',$i)]) ? 0 : $arr[date('Y-m-d',$i)];
			$day[] = date('Y-m-d',$i);
		}		
		$result = array('data'=>$brr,'time'=>$day);
		$this->assign('result',json_encode($result));					
		return $this->fetch();
	}

	public function expense_log(){
		$map = array();
		$admin_id = I('admin_id');
		if($this->begin && $this->end){
			$map['addtime'] = array('between',"$this->begin,$this->end");
		}
		if($admin_id){
			$map['admin_id'] = $admin_id;
		}
		$count = Db::name('expense_log')->where($map)->count();
		$page = new Page($count);
		$lists  = Db::name('expense_log')->where($map)->limit($page->firstRow.','.$page->listRows)->order('id desc')->select();
		$this->assign('page',$page->show());
		$this->assign('total_count',$count);
		$this->assign('list',$lists);
		$admin = Db::name('admin')->getField('admin_id,user_name');
		$this->assign('admin',$admin);
		$typeArr = array('','会员提现','订单取消','订单退款');//数据库设计问题  原订单退款=订单取消，其他=订单退款
		$this->assign('typeArr',$typeArr);
		return $this->fetch();
	}

    //财务统计
    public function finance(){
        $begin = $this->begin;
        $end_time = $this->end;
        $order = Db::name('order')->alias('o')
            ->where(['o.pay_status'=>1])->whereTime('o.add_time', 'between', [$begin, $end_time])
            ->order('o.add_time asc')->getField('order_id,o.*');  //以时间升序
        $order_id_arr = get_arr_column($order,'order_id');
        $order_ids = implode(',',$order_id_arr);            //订单ID组
        $order_goods = Db::name('order_goods')->where(['is_send'=>['in','1,2'],'order_id'=>['in',$order_ids]])->group('order_id')
            ->order('order_id asc')->getField('order_id,sum(goods_num*cost_price) as cost_price,sum(goods_num*member_goods_price) as goods_amount');  //订单商品退货的不算
        $frist_key = key($order);  //第一个key
        $sratus_date = strtotime(date('Y-m-d',$order["$frist_key"]['add_time']));  //有数据那天为循环初始时间，大范围查询可以避免前面输出一堆没用的数据
        $key = array_keys($order);
        $lastkey = end($key);//最后一个key
        $end_date = strtotime(date('Y-m-d',$order["$lastkey"]['add_time']))+24*3600;  //数据最后时间为循环结束点，大范围查询可以避免前面输出一堆没用的数据
        for($i=$sratus_date;$i<=$end_date;$i=$i+24*3600){   //循环时间
            $date = $day[] = date('Y-m-d',$i);
            $everyday_end_time = $i+24*3600;
            $goods_amount=$cost_price =$shipping_amount=$coupon_amount=$order_prom_amount=$total_amount=0.00; //初始化变量
            foreach ($order as $okey => $oval){   //循环订单
                $for_order_id = $oval['order_id'];
                if (!isset($order_goods["$for_order_id"])){
                    unset($order[$for_order_id]);           //去掉整个订单都了退货后的
                }
                if($oval['add_time'] >= $i && $oval['add_time']<$everyday_end_time){      //统计同一天内的数据
                    $goods_amount      += $oval['goods_price'];
                    $total_amount      += $oval['total_amount'];
                    $cost_price        += $order_goods["$for_order_id"]['cost_price']; //订单成本价
                    $shipping_amount   += $oval['shipping_price'];
                    $coupon_amount     += $oval['coupon_price'];
                    $order_prom_amount += $oval['order_prom_amount'];
                    unset($order[$okey]);  //省的来回循环
                }
            }
            //拼装输出到图表的数据
            $goods_arr[]    = $goods_amount;
            $total_arr[]    = $total_amount;
            $cost_arr[]     = $cost_price ;
            $shipping_arr[] = $shipping_amount;
            $coupon_arr[]   = $coupon_amount;

            $list[] = [
                'day'=>$date,
                'goods_amount'      => $goods_amount,
                'total_amount'      => $total_amount,
                'cost_amount'       => $cost_price,
                'shipping_amount'   => $shipping_amount,
                'coupon_amount'     => $coupon_amount,
                'order_prom_amount' => $order_prom_amount,
                'end'=>$everyday_end_time,
            ];  //拼装列表
        }
        rsort($list);
        $this->assign('list',$list);
        $result = ['goods_arr'=>$goods_arr,'cost_arr'=>$cost_arr,'shipping_arr'=>$shipping_arr,'coupon_arr'=>$coupon_arr,'time'=>$day];
        $this->assign('result',json_encode($result));
        return $this->fetch();
    }
    
  /**
     * 运营概况详情
     * @return mixed
     */
    public function financeDetail(){
        $begin = $this->begin;
        $end_time = $this->begin+24*60*60;
        $order_where = [
            'o.pay_status'=>1,
            'o.shipping_status'=>1,
            'og.is_send'=>['in','1,2']];  //交易成功的有效订单
        $order_count = Db::name('order')->alias('o')
            ->join('order_goods og','o.order_id = og.order_id','left')->join('users u','u.user_id = o.user_id','left')
            ->whereTime('o.add_time', 'between', [$begin, $end_time])->where($order_where)
            ->group('o.order_id')->count();
        $Page = new Page($order_count,50);

        $order_list = Db::name('order')->alias('o')
            ->field('o.*,u.user_id,u.nickname,SUM(og.cost_price) as coupon_amount')
            ->join('order_goods og','o.order_id = og.order_id','left')->join('users u','u.user_id = o.user_id','left')
            ->where($order_where)->whereTime('o.add_time', 'between', [$begin, $end_time])
            ->group('o.order_id')->limit($Page->firstRow,$Page->listRows)->select();
        $this->assign('order_list',$order_list);
        $this->assign('page',$Page);
        return $this->fetch();
    }
}