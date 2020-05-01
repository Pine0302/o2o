<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Author: 当燃      
 * Date: 2016-05-27
 */

namespace app\admin\controller;
use app\admin\logic\StoresubLogic;
use app\admin\logic\StorecountyLogic;
use think\Db;
use think\Page;
use fast\LatLngChange;
use app\common\library\Oss;

class Storesub extends Base{


	
	//店铺等级
	public function store_grade(){
		$model =  M('store_grade');
		$count = $model->where('1=1')->count();
		$Page = new Page($count,10);
		$list = $model->order('sg_id')->limit($Page->firstRow.','.$Page->listRows)->select();
		$this->assign('list',$list);
		$show = $Page->show();
		$this->assign('pager',$Page);
		$this->assign('page',$show);
		return $this->fetch();
	}
	
	public function grade_info(){
		$sg_id = I('sg_id');
		if($sg_id){
			$info = M('store_grade')->where("sg_id=$sg_id")->find();
			$this->assign('info',$info);
		}
		return $this->fetch();
	}
	
	public function grade_info_save(){
		$data = I('post.');
		if($data['sg_id'] > 0 || $data['act']=='del'){
			if($data['act'] == 'del'){
				if(M('store')->where(array('grade_id'=>$data['del_id']))->count()>0){
					respose('该等级下有开通店铺，不得删除');
				}else{
					$r = M('store_grade')->where("sg_id=".$data['del_id'])->delete();
					respose(1);
				}
			}else{
				$r = M('store_grade')->where("sg_id=".$data['sg_id'])->save($data);
			}
		}else{
			$r = M('store_grade')->add($data);
		}
		if($r){
			$this->success('编辑成功',U('Store/store_grade'));
		}else{
			$this->error('提交失败');
		}
	}
	
	public function store_class(){
		$model =  M('store_class');
		$count = $model->where('1=1')->count();
		$Page = new Page($count,10);
		$list = $model->order('sc_id')->limit($Page->firstRow.','.$Page->listRows)->select();
		$this->assign('list',$list);
		$show = $Page->show();
		$this->assign('pager',$Page);
		$this->assign('page',$show);
		return $this->fetch();
	}
	
	//店铺分类
	public function class_info(){
		$sc_id = I('sc_id');
		if($sc_id){
			$info = M('store_class')->where("sc_id=$sc_id")->find();
			$this->assign('info',$info);
		}
		return $this->fetch();
	}
	
	public function class_info_save(){
		$data = I('post.');
		if($data['sc_id'] > 0 || $data['act']=='del'){
			if($data['act']== 'del'){
				if(M('store')->where(array('sc_id'=>$data['del_id']))->count()>0){
					respose('该分类下有开通店铺，不得删除');
				}else{
					$r = M('store_class')->where("sc_id=".$data['del_id'])->delete();
					respose(1);
				}
			}else{
				$r = M('store_class')->where("sc_id=".$data['sc_id'])->save($data);
			}
		}else{
			$r = M('store_class')->add($data);
		}
		if($r){
			$this->success('编辑成功',U('Store/store_class'));
		}else{
			$this->error('提交失败');
		}
	}

    //设置推荐区县代理合伙人入驻设置
    public function store_sub_distribut() {
        $inc_type =  I('get.inc_type','shop_info');
        $this->assign('inc_type',$inc_type);
        $this->assign('config',tpCache($inc_type));//当前配置项
        return $this->fetch();
    }
    /*
    * 新增修改配置
    */
    public function handle()
    {
        $param = I('post.');
        $inc_type = $param['inc_type'];
        //unset($param['__hash__']);
        unset($param['inc_type']);
        tpCache($inc_type,$param);
        $this->success("操作成功",U('Storesub/store_sub_distribut',array('inc_type'=>$inc_type)));
    }

	//普通店铺列表
	////区县代理合伙人列表
	public function store_list(){
        //print_r(123);exit;
		$model =  M('store_sub');
		$seller_name = I('seller_name');
		if($seller_name) $map['seller_name'] = array('like',"%$seller_name%");
		$store_name = I('store_name');
		if($store_name) $map['store_name'] = array('like',"%$store_name%");
		$count = $model->where($map)->count();
		$Page = new Page($count,10);
		$list = $model->where($map)->order('store_id DESC')->limit($Page->firstRow.','.$Page->listRows)->select();
		foreach($list as $k=>$v) {
			/*$list[$k]['company_province'] = M('region')->where(array('parent_id'=>$v['city_id'],'level'=>3,'id'=>$v['district']))->getField('name');
			$list[$k]['apply'] = M('store_sub_apply')->field('contacts_name,contacts_mobile,company_address')->where(array('store_id'=>$v['store_id']))->find();*/
		}
		$this->assign('list',$list);
		$show = $Page->show();
		$this->assign('page',$show);
		$this->assign('pager',$Page);
		return $this->fetch();
	}

    /** 搜索地址，查询其对应的经纬度
     * @param string $address 地址
     * @param string $city  城市名
     * @return array
     */
    function getLatLng($address='',$city='')
    {
        $result = array();
        $ak = 'o7Rrd7VWb6AFmOhwltVqLBEV7d9hYlMb';//您的百度地图ak，可以去百度开发者中心去免费申请
        $url ="http://api.map.baidu.com/geocoder/v2/?callback=renderOption&output=json&address=".$address."&city=".$city."&ak=".$ak;
        $data = file_get_contents($url);
        $data = str_replace('renderOption&&renderOption(', '', $data);
        $data = str_replace(')', '', $data);
        $data = json_decode($data,true);
        if (!empty($data) && $data['status'] == 0) {
            $result['lat'] = $data['result']['location']['lat'];
            $result['lng'] = $data['result']['location']['lng'];
            return $result;//返回经纬度结果
        }else{
            return null;
        }

    }

    /**
     * 搜索地址，查询周边的位置  （）
     */
    public function query_address($key_words){
        $header[] = 'Referer: http://lbs.qq.com/webservice_v1/guide-suggestion.html';
        $header[] = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.139 Safari/537.36';
        $url ="http://apis.map.qq.com/ws/place/v1/suggestion/?&region=&key=NHHBZ-NL5KX-ZYM4A-7R7YF-DBJQT-7MB63&keyword=".$key_words;

        $ch = curl_init();
        //设置选项，包括URL
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_HTTPHEADER,$header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        //执行并获取HTML文档内容
        $output = curl_exec($ch);
        // print_r($output);die;
        //释放curl句柄
        curl_close($ch);
        // return $output;
        $result = json_decode($output,true);
        // print_r($result);
        // $res = $result['data'][0];
        return $result;
        //echo json_encode(['error_code'=>'SUCCESS','reason'=>'查询成功','result'=>$result);
    }



    //对添加区县代理的审核操作
    public function shenhe() {
        if($_POST) {
            $store_id = I('shenhe_id/d');
            $data['is_shenhe'] = 1;
            $res = M('store_sub')->where(array('store_id'=>$store_id))->save($data);
            if($res){
                $msn = array('status'=>1);
            }else {
                $msn = array('status'=>0);
            }
            $this->ajaxReturn($msn);
        }
    }
    //接受圈画的经纬度
    public function ajaxlnglat() {
        if(IS_AJAX) {
            $lng = $_POST['lng'];
            $lat = $_POST['lat'];
            foreach($lng as $k=>$v) {
                $data[$k]['lng'] = $v;
                $data[$k]['lat'] = $lat[$k];
            }
            $lnglat = json_encode($data);
            if($lnglat) {
                $msn = array('status'=>1,'msg'=>'经纬度获取成功','lnglat'=>$lnglat);
            }else {
                $msn = array('status'=>0,'msg'=>'经纬度获取失败');
            }
            $this->ajaxReturn($msn);exit;
        }
    }
    //百度地图经纬度转化为腾讯地图经纬度
    public function Convert_BD09_To_GCJ02($lat,$lng) {
        $x_pi = 3.14159265358979324 * 3000.0/180.0;
        $x = $lng - 0.0065;
        $y = $lat - 0.006;
        $z = sqrt($x*$x + $y*$y) - 0.00002*sin($y*$x_pi);
        $theta = atan2($y,$x) - 0.000003*cos($x*$x_pi);
        $lng = $z*cos($theta);
        $lat = $z*sin($theta);
        return array('lng'=>$lng,'lat'=>$lat);
    }
    //腾讯地图经纬度转化为百度地图经纬度
//    public function Convert_GCJ02_To_BD09($lat,$lng) {
//        $x_pi = 3.14159265358979324*3000.0/180.0;
//        $x = $lng;
//        $y = $lat;
//        $z = sqrt($x*$x + $y*$y) + 0.00002*sin($y*$x_pi);
//        $theta = atan2($y,$x) + 0.000003*cos($x*$x_pi);
//        $lng = $z * cos($theta) + 0.0065;
//        $lat = $z * sin($theta) + 0.006;
//        return array('lng'=>$lng,'lat'=>$lat);
//    }


	/*添加店铺*/
	/*添加区县代理合伙人*/
	public function store_add(){
		if(IS_POST){
            $request_data = $this->request->request();

			$store_name = I('store_name');
            $user_name = I('user_name');
            $store_phone = I('store_phone');
			$seller_name = I('seller_name');
            $withdraw_percent = I('withdraw_percent');
			$image = I('image');
            $notice = I('notice');
            $type_name = I('type_name');
            $image2 = I('image2');
            $store_description = I('store_description');
            $meituan_grade = I('meituan_grade');
            $month_sale = I('month_sale');
            $average_comsume = I('average_consume');
            $package_fee = I('package_fee');
            $image_oss ='';
            if(!empty($image)){
                $ossLibraryObj = new Oss();
                $pos = strripos($image,'/',0);
                $file_name = mb_substr($image,$pos+1);
                $file_path = mb_substr($image,1,$pos);
                $server_path = "/opt/app-root/src/";
                $image_oss = $ossLibraryObj->uploadPicToOs($server_path,$file_path,$file_name);

            }
			if(M('store_sub')->where("store_name='$store_name'")->count()>0){
				$this->error("店铺名称已存在");
			}
			if(M('store_sub')->where("seller_name='$seller_name'")->count()>0){
				$this->error("区县代理合伙人账号已被占用");
			}

            /*$storesublist = M('store_sub')->where(array('district'=>$_POST['district'],'store_state'=>1))->find();
            if($storesublist){
                $this->error("此区县已有代理合伙人");
            }*/

            $longlat = I('store_lnglat');
            $longlat = explode(',',$longlat);

            $lnglat = I('lnglat');

            $langlat_arr = explode("-",str_replace("},{","}-{",$lnglat));
            $str_old = '';
            foreach($langlat_arr as $kl=>$vl){
                $arr = explode(",",$vl);
                $lng_arr = explode(":",$arr[0]);
                $lat_arr = explode(":",$arr[1]);
                $lng = floatval(mb_substr(mb_substr($lng_arr[1],6),0,strlen(mb_substr($lng_arr[1],6))-6));
                $lat = floatval(mb_substr(mb_substr($lat_arr[1],6),0,strlen(mb_substr($lat_arr[1],6))-6));
                $str_old = $str_old.$lat.",".$lng.";";
            }
            $str_old = substr($str_old,0,strlen($str_old)-1);
            $latLngChangeObj = new LatLngChange();
            $api_map_tencent = C('API_MAP_TENCENT');

            $tx_latlng_arr = $latLngChangeObj->convertLatLngToTencent($str_old,3,$api_map_tencent);  //type 3 百度经纬度转腾讯
            $tx_latlng = (count($tx_latlng_arr)>0) ? serialize($tx_latlng_arr):'';
            $store_latlng_tx_arr = $latLngChangeObj->convertLatLngToTencent($longlat[1].",".$longlat[0],3,$api_map_tencent);

            $store = array(
				'store_name'=>$store_name,
				'image2'=>$image2,
				'user_name'=>$user_name,
				'withdraw_percent'=>$withdraw_percent,
				'seller_name'=>$seller_name,
				'notice'=>$notice,
				'type_name'=>$type_name,
				'store_description'=>$store_description,
				'meituan_grade'=>$meituan_grade,
				'month_sale'=>$month_sale,
                'average_consume' => $average_comsume,
                'package_fee' => $package_fee,
				'image'=>$image,
				'image_oss'=>$image_oss,
				'tui_store_sub_id'=>I('tui_store_sub_id/d'),
				'store_address'=>I('store_address'),
				'store_phone'=>I('store_phone'),
                'lnglat'=>I('lnglat'),
                'lnglat_tx'=>$tx_latlng,
                'store_lng'=>$longlat[0],
				'store_lat'=>$longlat[1],
                'store_lng_tx'=>$store_latlng_tx_arr[0]['lng'],
                'store_lat_tx'=>$store_latlng_tx_arr[0]['lat'],
				'password'=>I('password'),
				'province_id'=>I('province_id'),
				'city_id'=>I('city_id'),
				'district'=>I('district'),
				'store_state'=>1,
                'is_shenhe'=>1,
				'store_time'=>I('store_time'),
				'store_end_time'=>I('store_end_time'),
                'store_time2'=>I('store_time2'),
                'store_end_time2'=>I('store_end_time2'),
				'is_own_shop'=>I('is_own_shop')
			);

          //  var_dump($store);exit;

			$storesubLogic = new StoresubLogic();
			$store_id = $storesubLogic->addStore($store);
			if($store_id){
			    $this->initSubStoreGoods($store_id);
				$this->success('分店添加成功',U('Storesub/store_list'));exit;
			}else{
				$this->error("分店添加失败");
			}
		}

		$province = M('region')->where(array('parent_id'=>0,'level'=>1))->select();
        $this->assign('province',$province);
        $storesub= M('store_sub')->field('lnglat,store_id,store_name,city_id,district')->select();
        foreach($storesub as $k=>$v) {
            //$storesub[$k]['lnglat'] = explode(',',$v['lnglat']);
            $storesub[$k]['company_district'] = M('region')
            ->where(array('parent_id'=>$v['city_id'],'level'=>3,'id'=>$v['district']))
            ->getField('name');
        }
        $this->assign('storesub',$storesub);
		$is_own_shop = I('is_own_shop',1);
		$this->assign('is_own_shop',$is_own_shop);
		return $this->fetch();
	}

	//初始化门店商品
	public function initSubStoreGoods($store_id){

        //找出总平台 products 和总平台 product_spec_price
        $product_list = Db::name('goods')
            ->where('goods_id','>',234)
            ->where('store_id','=',0)
            ->select();
        $plat_goods_ids = [];
        $plat_goods_ids_str = '';
        $arr_insert_store_goods = [];
        foreach($product_list as $kp=>$vp){
            $vp['parent_id']= $vp['goods_id'];
            $vp['store_id']= $store_id;
            $vp['goods_sn']='';
            $plat_goods_ids[] = $vp['goods_id'];
            $plat_goods_ids_str = $plat_goods_ids_str. $vp['goods_id'].",";
            unset($vp['goods_id']);
            $arr_insert_store_goods[] = $vp;
        }
        $plat_goods_ids_str = substr($plat_goods_ids_str,0,strlen($plat_goods_ids_str)-1);
        Db::name('goods')->insertAll($arr_insert_store_goods);
        $store_goods_list = Db::name('goods')->where('store_id','=',$store_id)->select();
        $arr_isnert_spec_list = [];
        foreach($store_goods_list as $ks=>$vs){
            $parent_spec_price_list = Db::name('spec_goods_price')->where("goods_id",'=',$vs['parent_id'])->select();
            foreach($parent_spec_price_list as $kp=>$vp){
                $vp['goods_id'] = $vs['goods_id'];
                unset( $vp['item_id']);
                $arr_isnert_spec_list[] = $vp;
            }
        }
        Db::name('spec_goods_price')->insertAll($arr_isnert_spec_list);

      /*  $parent_goods_plus = Db::name('goods_plus')
            ->where('goods_id','in',$plat_goods_ids_str)
            ->select();*/

        $arr_isnert_plus_list = [];
        foreach($store_goods_list as $ks=>$vs){
            $parent_plus_list = Db::name('goods_plus')->where("goods_id",'=',$vs['parent_id'])->select();
            foreach($parent_plus_list as $kp=>$vp){
                $vp['goods_id'] = $vs['goods_id'];
                unset( $vp['id']);
                $arr_isnert_plus_list[] = $vp;
            }
        }
        Db::name('goods_plus')->insertAll($arr_isnert_plus_list);




    }


    //初始化经纬度数据
    public function datalnglat() {
        if(IS_AJAX) {
            $storesub = M('store_sub')
                ->where('show_pos','=',1)
                ->field('lnglat')->select();
            foreach($storesub as $k=>$v) {
                if($storesub[$k]['lnglat'] == NULL) {
                    unset($storesub[$k]);
                }else {
                    $data[] = $v['lnglat'];
                }
            }
            foreach($data as $k=>$v) {
                $data[$k] = json_decode($v,true);
            }
            if($data) {
                $msn = array('status'=>1,'lnglat'=>$data,'msg'=>'数据加载成功');
            }else {
                $msn = array('status'=>0,'lnglat'=>'','msg'=>'暂时没有数据');
            }
            $this->ajaxReturn($msn);exit;
        }
    }

    //根据服务区域地址获取经纬度
    public function areasearch() {
	    if(IS_AJAX) {
	        $address = I('address');
            if($address) {
                $msn = array('status'=>1,'addresslnglat'=>$address,'msg'=>'获取成功');
            }else {
                $msn = array('status'=>0,'addresslnglat'=>'','msg'=>'获取失败');
            }
            $this->ajaxReturn($msn);exit;
        }
    }

	//编辑外驻店铺
	//编辑区县代理合伙人
	public function store_info_edit(){

		if(IS_POST){

			$map =$_REQUEST;


			$store = $map['store'];

			unset($map['store']);

            $store_one = M('store_sub')->where(array('store_id'=>$store['store_id']))->find();

            //门店经纬度
            $res_lnglat = explode(',',$store['store_lnglat'] );
            $store['lng'] = $res_lnglat[0];
            $store['lat'] = $res_lnglat[1];
            $store['sfid'] = $map['sfid'];

            $store['notice'] = $map['notice'];
            $store['image2'] = $map['image2'];
            $store['store_description'] = $map['store_description'];
            $store['type_name'] = $map['type_name'];
            $store['meituan_grade'] = $map['meituan_grade'];
            $store['month_sale'] = $map['month_sale'];
            if($map['store_time2']&&($map['store_end_time2'])){
                $store['store_time2'] = $map['store_time2'];
                $store['store_end_time2'] = $map['store_end_time2'];
            }

            $lnglat = $store['lnglat'];

            $langlat_arr = explode("-",str_replace("},{","}-{",$lnglat));
            $str_old = '';
            foreach($langlat_arr as $kl=>$vl){
                $arr = explode(",",$vl);

                $lng_arr = explode(":",$arr[0]);
                $lat_arr = explode(":",$arr[1]);
                $lng = floatval(mb_substr(mb_substr($lng_arr[1],1),0,strlen(mb_substr($lng_arr[1],1))-1));

                $lat = floatval(mb_substr(mb_substr($lat_arr[1],1),0,strlen(mb_substr($lat_arr[1],1))-1));
                $str_old = $str_old.$lat.",".$lng.";";
            }
            $str_old = substr($str_old,0,strlen($str_old)-1);

            $latLngChangeObj = new LatLngChange();
            $api_map_tencent = C('API_MAP_TENCENT');

            $tx_latlng_arr = $latLngChangeObj->convertLatLngToTencent($str_old,3,$api_map_tencent);  //type 3 百度经纬度转腾讯

            $tx_latlng = (count($tx_latlng_arr)>0) ? serialize($tx_latlng_arr):'';
            $store_latlng_tx_arr = $latLngChangeObj->convertLatLngToTencent($res_lnglat[1].",".$res_lnglat[0],3,$api_map_tencent);

            $store['lnglat_tx'] = $tx_latlng;
            $store['store_lng_tx'] = $store_latlng_tx_arr[0]['lng'];
            $store['store_lat_tx'] = $store_latlng_tx_arr[0]['lat'];

            if ($store_one['password'] != $store['password']) {
                $store['password'] = encrypt($store['password']);
            }
            $store['average_consume']  = I('average_consume');
            $store['package_fee']  = I('package_fee');

           $result =  $a = M('store_sub')->where(array('store_id'=>$store['store_id']))->save($store);
           // var_dump($result);
           // var_dump($store);
            //exit;
			//$b = M('store_sub_apply')->where(array('store_id'=>$store['store_id']))->save($map);

			if($a){
				if($store['store_state'] == 0){
					//关闭店铺，同时下架店铺所有商品
				//	M('goods')->where(array('store_id'=>$store['store_id']))->save(array('is_on_sale'=>0));
				}
				$this->success('编辑成功',U('Storesub/store_list'));exit;
			}else{
                $this->success('编辑成功',U('Storesub/store_list'));exit;
			}
		}

		$store_id = I('store_id');

		if($store_id>0){
			$store = M('store_sub')->where("store_id=$store_id")->find();
			$store['store_lnglat'] = $store['store_lng'].','.$store['store_lat'];

            $store['company_province_name'] = M('region')->field('name')->where(array('parent_id'=>0,'level'=>1,'id'=>$store['province_id']))->find();
            $store['company_city_name'] = M('region')->field('name')->where(array('parent_id'=>$store['province_id'],'level'=>2,'id'=>$store['city_id']))->find();

   /*         $store['company_district_name'] = M('region')->field('name')->where(array('parent_id'=>$store['city_id'],'level'=>3,'id'=>$store['district']))->find();*/
			$this->assign('store',$store);

		/*	$apply = M('store_sub_apply')->where('store_id='.$store['store_id'])->find();
			$this->assign('apply',$apply);*/

            $storsub = M('store_sub')->field('store_id,store_name,store_phone,user_name,city_id,store_time,store_time2,store_end_time2')->where(['store_id'=>$store_id])->find();

            if(!empty($storsub['store_time'])){
                $storsub['store_time'] = date("H:i:s",$storsub['store_time']);
            }
            if(!empty($storsub['store_end_time'])){
                $storsub['store_end_time'] = date("H:i:s",$storsub['store_end_time']);
            }
            if(!empty($storsub['store_time2'])){
                $storsub['store_time2'] = date("H:i:s",$storsub['store_time2']);
            }
            if(!empty($storsub['store_end_time2'])){
                $storsub['store_end_time2'] = date("H:i:s",$storsub['store_end_time2']);
            }
         /*   foreach($storsub as $k=>$v) {
                $storsub[$k]['company_district'] = M('region')
                ->where(array('parent_id'=>$v['city_id'],'level'=>3,'id'=>$v['district']))
                ->getField('name');
            }*/
          //  var_dump($storsub);
            $this->assign('storesub',$storsub);
		}

		return $this->fetch();
	}
	
	/*删除店铺*/
	/*区县代理合伙人信息*/
	public function store_del(){
		$store_id = I('del_id');
		if($store_id > 1){
			$store = M('store_sub')->where("store_id=$store_id")->find();
			if(M('goods')->where("store_id=$store_id")->count()>0){
			    $data = [
			        'status'=>2,
                    'msg'=>'该店铺有发布商品，不得删除'
                ];
				respose($data);
			}else{
			//	M('seller_sub')->where(array('store_id' => $store_id))->delete();
				M('store_sub')->where(array('store_id' => $store_id))->delete();
			//	M('store_sub_apply')->where(array("store_id"=>$store['store_id']))->delete();
			//	adminLog("区县代理合伙人".$store['seller_name']);
                $data = [
                    'status'=>1,

                ];
                respose($data);
			//	respose(1);
			}
		}else{
			respose('基础自营店，不得删除');
		}
	}

	//区县代理合伙人-师傅管理
	public function store_shifu_info(){
		$map['storesub_id'] = I('store_id');

        $model =  M('store_sub_shifu');

        $storesub_shifu = I('storesub_shifu');
        if($storesub_shifu) $map['storesub_shifu'] = array('like',"%$storesub_shifu%");

        $count = $model->where($map)->count();
        $Page = new Page($count,10);
        $list = $model->where($map)->order('id DESC')->limit($Page->firstRow.','.$Page->listRows)->select();

        foreach($list as $k=>$v) {
            $list[$k]['store_name'] = M('store_sub')->where(array('store_id'=>$v['storesub_id']))->getField('store_name');
            $list[$k]['company_district'] = M('region')->where(array('parent_id'=>$v['company_city'],'level'=>3,'id'=>$v['company_district']))->getField('name');
        }

        $this->assign('list',$list);
        
        $show = $Page->show();
        $this->assign('page',$show);
        $this->assign('pager',$Page);

        return $this->fetch();
	}

	//店铺信息
	public function store_info(){
		$store_id = I('store_id');
		$store = M('store')->where("store_id=".$store_id)->find();
		$this->assign('store',$store);
		$store_grade = M('store_grade')->where(array('sg_id'=>$store['grade_id']))->find();
		$this->assign('store_grade',$store_grade);
		$apply = M('store_apply')->where("user_id=".$store['user_id'])->find();
		$province_name = M('region')->where(array('id'=>$apply['company_province']))->getField('name');
		$city_name = M('region')->where(array('id'=>$apply['company_city']))->getField('name');
		$district_name = M('region')->where(array('id'=>$apply['company_district']))->getField('name');
		$this->assign('province_name',$province_name);
		$this->assign('city_name ',$city_name);
		$this->assign('district_name',$district_name);
		$this->assign('apply',$apply);
		$bind_class_list = M('store_bind_class')->where("store_id=".$store_id)->select();
		$goods_class = M('goods_category')->getField('id,name');
		for($i = 0, $j = count($bind_class_list); $i < $j; $i++) {
			$bind_class_list[$i]['class_1_name'] = $goods_class[$bind_class_list[$i]['class_1']];
			$bind_class_list[$i]['class_2_name'] = $goods_class[$bind_class_list[$i]['class_2']];
			$bind_class_list[$i]['class_3_name'] = $goods_class[$bind_class_list[$i]['class_3']];
		}
		$this->assign('bind_class_list',$bind_class_list);
		return $this->fetch();
	}
	
	/*验证店铺名称，店铺登陆账号*/
	public function store_check(){

        $request_data = $this->request->request();
		$store_name = I('store_name');
		$seller_name = I('seller_name');
		$user_name = I('user_name');
		$res = array('status'=>1);
		if($store_name && M('store')->where("store_name='$store_name'")->count()>0){
			$res = array('status'=>-1,'msg'=>'店铺名称已存在');
		}
		
		if(!empty($user_name)){
			if(!check_email($user_name) && !check_mobile($user_name)){
				$res = array('status'=>-1,'msg'=>'店主账号格式有误');
			}
			if(M('users')->where("email='$user_name' or mobile='$user_name'")->count()>0){
				$res = array('status'=>-1,'msg'=>'会员名称已被占用');
			}
		}

		if($seller_name && M('seller')->where("seller_name='$seller_name'")->count()>0){
			$res = array('status'=>-1,'msg'=>'此账号名称已被占用');
		}
		respose($res);
	}
	
	/*编辑自营店铺*/
	public function store_edit(){
		if(IS_POST){
			$data = I('post.');
			if(M('store')->where("store_id=".$data['store_id'])->save($data)){
				$this->success('编辑店铺成功',U('Store/store_own_list'));
				exit;
			}else{
				$this->error('编辑失败');
			}
		}
		$store_id = I('store_id',0);
		$store = M('store')->where("store_id=$store_id")->find();
		$this->assign('store',$store);
		return $this->fetch();
	}
	
	//自营店铺列表
	public function store_own_list(){
		$model =  M('store');
		$map['is_own_shop'] = 1 ;
		$grade_id = I("grade_id");
		if($grade_id>0) $map['grade_id'] = $grade_id;
		$sc_id =I('sc_id');
		if($sc_id>0) $map['sc_id'] = $sc_id;
		$store_state = I("store_state");
		if($store_state>0)$map['store_state'] = $store_state;
		$seller_name = I('seller_name');
		if($seller_name) $map['seller_name'] = array('like',"%$seller_name%");
		$store_name = I('store_name');
		if($store_name) $map['store_name'] = array('like',"%$store_name%");
		$count = $model->where($map)->count();
		$Page = new Page($count,10);
		$list = $model->where($map)->order('store_id DESC')->limit($Page->firstRow.','.$Page->listRows)->select();
		$this->assign('list',$list);	
		$show = $Page->show();
		$this->assign('page',$show);
		$this->assign('pager',$Page);
		$store_grade = M('store_grade')->getField('sg_id,sg_name');
		$this->assign('store_grade',$store_grade);
		$this->assign('store_class',M('store_class')->getField('sc_id,sc_name'));
		return $this->fetch();
	}
	
	//店铺申请列表
	public function apply_list(){
		$model =  M('store_apply');
		$map['apply_state'] = array('neq',1);
		$sg_id = I("sg_id");
		if($sg_id>0) $map['sg_id'] = $sg_id;
		$sc_id =I('sc_id');
		if($sc_id>0) $map['sc_id'] = $sc_id;
		$seller_name = I('seller_name');
		if($seller_name) $map['seller_name'] = array('like',"%$seller_name%");
		$store_name = I('store_name');
		if($store_name) $map['store_name'] = array('like',"%$store_name%");
		$count = $model->where($map)->count();
		$Page = new Page($count,10);
		$list = $model->where($map)->order('add_time desc')->limit($Page->firstRow.','.$Page->listRows)->select();
		$this->assign('list',$list);
		$show = $Page->show();
		$this->assign('pager',$Page);
		$this->assign('page',$show);
		$this->assign('store_grade',M('store_grade')->getField('sg_id,sg_name'));
		$this->assign('store_class',M('store_class')->getField('sc_id,sc_name'));
		return $this->fetch();
	}
	
	public function apply_del(){
		$id = I('del_id');
		if($id && M('store_apply')->where(array('id'=>$id))->delete()){
			$this->success('操作成功',U('Store/apply_list'));
		}else{
			$this->error('操作失败');
		}
	}
	//经营类目申请列表
	public function apply_class_list(){
		$state = I('state');
		if($state != ""){
			$bind_class = M('store_bind_class')->where(array('state'=>$state))->order('bid desc')->select();
		}else{
			$bind_class = M('store_bind_class')->order('bid desc')->select();
		}		
		$goods_class = M('goods_category')->getField('id,name');
		for($i = 0, $j = count($bind_class); $i < $j; $i++) {
			$bind_class[$i]['class_1_name'] = $goods_class[$bind_class[$i]['class_1']];
			$bind_class[$i]['class_2_name'] = $goods_class[$bind_class[$i]['class_2']];
			$bind_class[$i]['class_3_name'] = $goods_class[$bind_class[$i]['class_3']];
			$store = M('store')->where("store_id=".$bind_class[$i]['store_id'])->find();
			$bind_class[$i]['store_name'] = $store['store_name'];
			$bind_class[$i]['seller_name'] = $store['seller_name'];
		}
		$this->assign('bind_class',$bind_class);
		return $this->fetch();
	}
	
	//查看-添加店铺经营类目
	public function store_class_info(){
		$store_id = I('store_id');
		$store = M('store')->where(array('store_id'=>$store_id))->find();
		$this->assign('store',$store);
		if(IS_POST){
			$data = I('post.');
			$data['state'] = 1;
			$where = 'class_3 ='.$data['class_3'].' and store_id='.$store_id;
			if(M('store_bind_class')->where($where)->count()>0){
				$this->error('该店铺已申请过此类目');
			}
			$data['commis_rate'] = M('goods_category')->where(array('id'=>$data['class_3']))->getField('commission');
			if(M('store_bind_class')->add($data)){
				adminLog('添加店铺经营类目，类目编号:'.$data['class_3'].',店铺编号:'.$data['store_id']);
				$this->success('添加经营类目成功');exit;
			}else{
				$this->error('操作失败');
			}
		}
		$bind_class_list = M('store_bind_class')->where('store_id='.$store_id)->select();
		$goods_class = M('goods_category')->getField('id,name');
		for($i = 0, $j = count($bind_class_list); $i < $j; $i++) {
			$bind_class_list[$i]['class_1_name'] = $goods_class[$bind_class_list[$i]['class_1']];
			$bind_class_list[$i]['class_2_name'] = $goods_class[$bind_class_list[$i]['class_2']];
			$bind_class_list[$i]['class_3_name'] = $goods_class[$bind_class_list[$i]['class_3']];
		}
		$this->assign('bind_class_list',$bind_class_list);
		$cat_list = M('goods_category')->where("parent_id = 0")->select();
		$this->assign('cat_list',$cat_list);
		return $this->fetch();
	}
	
	
	public function apply_class_save(){
		$data = I('post.');
		if($data['act']== 'del'){
			$r = M('store_bind_class')->where("bid=".$data['del_id'])->delete();
			respose(1);
		}else{
			$data = I('get.');
			$r = M('store_bind_class')->where("bid=".$data['bid'])->save(array('state'=>$data['state']));
		}
		if($r){
			$this->success('操作成功',U('Store/apply_class_list'));
		}else{
			$this->error('提交失败');
		}
	}
	
	//店铺申请信息详情
	public function apply_info(){
		$id = I('id');
		$apply = M('store_apply')->where("id=$id")->find();
		$province_name = M('region')->where(array('id'=>$apply['company_province']))->getField('name');
		$city_name = M('region')->where(array('id'=>$apply['company_city']))->getField('name');
		$district_name = M('region')->where(array('id'=>$apply['company_district']))->getField('name');
		$this->assign('province_name',$province_name);
		$this->assign('city_name',$city_name);
		$this->assign('district_name',$district_name);
		$goods_cates = M('goods_category')->getField('id,name,commission');
		if(!empty($apply['store_class_ids'])){
			$store_class_ids = unserialize($apply['store_class_ids']);
			foreach ($store_class_ids as $val){
				$cat = explode(',', $val);
				$bind_class_list[] = array('class_1'=>$goods_cates[$cat[0]]['name'],'class_2'=>$goods_cates[$cat[1]]['name'],
						'class_3'=>$goods_cates[$cat[2]]['name'].'(分佣比例：'.$goods_cates[$cat[2]]['commission'].'%)',
						'value'=>$val,
				);
			}
			$this->assign('bind_class_list',$bind_class_list);
		}
		$this->assign('apply',$apply);
		$apply_log = M('admin_log')->where(array('log_type'=>1))->select();
		$this->assign('apply_log',$apply_log);
		$this->assign('store_grade',M('store_grade')->select());
		return $this->fetch();
	}
	
	//审核店铺开通申请
	public function review(){
		$data = I('post.');
		if($data['id']){
			$apply = M('store_apply')->where(array('id'=>$data['id']))->find();
			if(empty($apply['store_name'])){
				$this->error('店铺名称不能为空.');
			}
			if($apply && M('store_apply')->where("id=".$data['id'])->save($data)){				
				if($data['apply_state'] == 1){
					$users = M('users')->where(array('user_id'=>$apply['user_id']))->find();
					if(empty($users)) $this->error('申请会员不存在或已被删除，请检查');
					$time = time();$store_end_time = $time+24*3600*365;//开店时长
					$store = array('user_id'=>$apply['user_id'],'seller_name'=>$apply['seller_name'],
							'user_name'=>empty($users['email']) ? $users['mobile'] : $users['email'],
							'grade_id'=>$data['sg_id'],'store_name'=>$apply['store_name'],'sc_id'=>$apply['sc_id'],
							'company_name'=>$apply['company_name'],'store_phone'=>$apply['store_person_mobile'],
							'store_address'=>empty($apply['store_address']) ? '待完善' : $apply['store_address'] ,
							'store_time'=>$time,'store_state'=>1,'store_qq'=>$apply['store_person_qq'],
							'store_end_time'=>$store_end_time,'province_id'=>$apply['company_province'],
							'city_id'=>$apply['company_city'],'district'=>$apply['company_district']							
					);
					$store_id = M('store')->add($store);//通过审核开通店铺
					if($store_id){
						$seller = array('seller_name'=>$apply['seller_name'],'user_id'=>$apply['user_id'],
							'group_id'=>0,'store_id'=>$store_id,'is_admin'=>1
						);
						M('seller')->add($seller);//点击店铺管理员
						//绑定商家申请类目
						if(!empty($apply['store_class_ids'])){
							$goods_cates = M('goods_category')->where(array('level'=>3))->getField('id,name,commission');
							$store_class_ids = unserialize($apply['store_class_ids']);
							foreach ($store_class_ids as $val){
								$cat = explode(',', $val);
								$bind_class = array('store_id'=>$store_id,'commis_rate'=>$goods_cates[$cat[2]]['commission'],
										'class_1'=>$cat[0],'class_2'=>$cat[1],'class_3'=>$cat[2],'state'=>1);
								M('store_bind_class')->add($bind_class);
							}
						}
						$store_logic = new StoreLogic();
						$store_logic->store_init_shipping($store_id);//初始化店铺物流
					}
					adminLog($apply['store_name'].'开店申请审核通过',1);
				}else if($data['apply_state'] == 2){
					adminLog($apply['store_name'].'开店申请审核未通过，备注信息：'.$data['review_msg'],1);
				}
				$this->success('操作成功',U('Store/store_list'));
			}else{
				$this->error('提交失败',U('Store/apply_list'));
			}
		}
	}


	public function reopen_list()
	{
		$list = M('store_reopen')->where('')->select();
		$this->assign('list', $list);
		return $this->fetch();
	}


    /**
     * 提现申请记录
     */
    public function withdrawals()
    {
        $this->get_withdrawals_list();
        $this->assign('withdraw_status', C('WITHDRAW_STATUS_O2O'));
        return $this->fetch();
    }

    public function get_withdrawals_list($status = '')
    {
        $id = I('selected/a');
        $user_id = I('user_id/d');
        $realname = I('realname');
        $bank_card = I('bank_card');
        $create_time = urldecode(I('create_time'));
        $create_time = $create_time ? $create_time : date('Y-m-d H:i:s', strtotime('-1 year')) . ',' . date('Y-m-d H:i:s', strtotime('+1 day'));
        $create_time3 = explode(',', $create_time);
        $this->assign('start_time', $create_time3[0]);
        $this->assign('end_time', $create_time3[1]);
       // $where['w.update_time'] = array(array('gt', strtotime($create_time3[0])), array('lt', strtotime($create_time3[1])));

        $status = empty($status) ? I('status') : $status;
        if ($status !== '') {
            $where['w.status'] = $status;
        } else {
            //$where['w.status'] = ['lt', 2];
        }
        if ($id) {
            $where['w.id'] = ['in', $id];
        }
        $user_id && $where['u.user_id'] = $user_id;
        $realname && $where['w.realname'] = array('like', '%' . $realname . '%');
        $bank_card && $where['w.bank_card'] = array('like', '%' . $bank_card . '%');
        /*$export = I('export');
        if ($export == 1) {
            $strTable = '<table width="500" border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">申请人</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="100">提现金额</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行名称</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行账号</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">开户人姓名</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">申请时间</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">提现备注</td>';
            $strTable .= '</tr>';
            $remittanceList = Db::name('withdrawals')->alias('w')->field('w.*,u.nickname')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->order("w.id desc")->select();
            if (is_array($remittanceList)) {
                foreach ($remittanceList as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['nickname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['money'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['bank_name'] . '</td>';
                    $strTable .= '<td style="vnd.ms-excel.numberformat:@">' . $val['bank_card'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['realname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i:s', $val['create_time']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['remark'] . '</td>';
                    $strTable .= '</tr>';
                }
            }
            $strTable .= '</table>';
            unset($remittanceList);
            downloadExcel($strTable, 'remittance');
            exit();
        }*/
//        $where['w.status'] = ['gt', 1];
        $where['w.type'] = ['eq', 2];


        $count = Db::name('merch_cash_log')->alias('w')->where($where)->count();
        $Page = new Page($count, 20);
        $list = Db::name('merch_cash_log')->alias('w')->field('w.*,u.*')->join('__SELLER_SUB__ u', 'u.store_id = w.store_id', 'INNER')->where($where)->order("w.id desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();
        //print_r($list);exit;
        //$this->assign('create_time',$create_time2);
        $show = $Page->show();
        $this->assign('show', $show);
        $this->assign('list', $list);
        $this->assign('pager', $Page);
        C('TOKEN_ON', false);
    }

    public function accept_withdraw(){
        $data = $_REQUEST;
        $id = $data['shenhe_id'];
        $with_info = Db::name('merch_cash_log')->where('id','=',$id)->find();
        Db::name('merch_cash_log')->where('id','=',$id)->update(['status'=>3,'update_time'=>time()]);
        Db::name('seller_sub')->where('store_id','=',$with_info['store_id'])->inc('withdraw_money',$with_info['cash'])->dec('withdrawing_money',$with_info['cash'])->update();
        $msn = array('status'=>1);
        $this->ajaxReturn($msn);
    }

    public function deny_withdraw(){
        $data = $_REQUEST;
        $id = $data['shenhe_id'];
        $with_info = Db::name('merch_cash_log')->where('id','=',$id)->find();
        Db::name('merch_cash_log')->where('id','=',$id)->update(['status'=>4,'update_time'=>time()]);
        Db::name('seller_sub')->where('store_id','=',$with_info['store_id'])->inc('merch_money',$with_info['cash'])->dec('withdrawing_money',$with_info['cash'])->update();
        $msn = array('status'=>1);
        $this->ajaxReturn($msn);
    }

}