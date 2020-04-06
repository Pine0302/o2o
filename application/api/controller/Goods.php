<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\wx\WXBizDataCrypt;
use fast\AreaInclude;
use fast\Http;
use think\cache\driver\Redis;
use think\Db;
use think\Session;
use think\Cache;
use app\api\controller\Common;
use app\api\library\NoticeHandle;
use fast\Algor;
/**
 * 工作相关接口
 */
class Goods extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    //  protected $noNeedLogin = ['test1","login'];
    protected $noNeedLogin = ['*'];
    // 无需鉴权的接口,*表示全部
//    /protected $noNeedRight = ['test2'];
    protected $noNeedRight = ['*'];


    public function test1()
    {
        $this->success('返回成功', ['action' => 'test1']);
    }

    //商品类型分类列表
    public function goodsType(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'];
        $user_info = $this->getGUserInfo($sess_key);
        $type_list = Db::name('goods_category')
            ->where('is_show','=',1)
            ->field("id,name,image")
            ->order('sort_order asc')
            ->select();
        foreach($type_list as $kt=>$vt){
            $picurl = "https://".$_SERVER['HTTP_HOST'].$vt['image'];
            $type_list[$kt]['image'] = $picurl;
        }
        $data = [
            'data'=>$type_list,
        ];
        $this->success('success', $data);
    }

    //所有产品列
    public function allGoodsList(){
        $data = $this->request->post();
        $openid = $this->analysisUserJwtToken();
        $user_info = $this->getGUserInfo($openid);

        $store_id = $data['store_id'];

        $type_list = Db::name('goods_category')
            ->where('is_show','=',1)
            ->field("id,name,image")
            ->order('sort_order asc')
            ->select();

        $goods_list = Db::name('goods_category c')
            ->join('tp_goods g','c.id=g.cat_id','right')
            ->where('c.is_show','=',1)
            ->where('g.store_id','=',$store_id)
            ->where('g.is_on_sale',1)
            ->field("g.goods_id,g.original_img,g.other_price,g.goods_remark,g.goods_name,g.shop_price,g.store_count,c.name as cat_name,c.id as cat_id,g.sort")
            ->order('c.sort_order asc,g.sort asc')
            ->select();
        $all_goods = [];
        foreach($type_list as $kt=>$vt){
            $type_goods_list = [];
            foreach($goods_list as $kg=>$vg){
                if($vg['cat_id']==$vt['id']){
                    $picurl = "https://".$_SERVER['HTTP_HOST'].$vg['original_img'];
                    $sepc_count = Db::name('spec_goods_price')->where("goods_id",'=',$vg['goods_id'])->count();
                    $has_spec = ($sepc_count>0) ? 1 : 2;
                    $sold_out = ($vg['store_count']==0) ? 1 : 2;// 1:售磐 2:未售磐
                    $remark_simple = $vg['goods_remark'];
                    if(mb_strlen($vg['goods_remark'])>28){
                        $remark_simple = mb_substr($remark_simple,0,28)."...";
                    }
                    $type_goods_list[] = [
                        'id'=>$vg['goods_id'],
                        'image'=>$picurl,
                        'price'=>number_format($vg['shop_price']),
                        'other'=>number_format($vg['other_price']),
                        'description'=>$vg['goods_remark'],
                        'description_simple'=>$remark_simple,
                        'name'=>$vg['goods_name'],
                        'has_spec'=> $has_spec,
                        'sold_out'=>$sold_out,
                        'cat_id'=>$vg['cat_id'],
                        'sort'=>$vg['sort'],
                    ];
                }
            }
            $all_goods[] = [
                'cat_id'=>$vt['id'],
                'cat_name'=>$vt['name'],
                'goods_list'=>$type_goods_list,
            ];
        }
        $data = [
            'data'=>$all_goods,
        ];
        $this->success('success', $data);
    }

    //商品类型分类列表
    public function typeGoodsList(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'];
        $user_info = $this->getGUserInfo($sess_key);
        $store_id = $data['store_id'];
        $type_id = $data['type_id'];
        $goods_list = Db::name('goods')
            ->where('is_on_sale',1)
            ->where('cat_id','=',$type_id)
            ->where('store_id','=',$store_id)
            ->field("goods_id,original_img,goods_remark,goods_name,shop_price,store_count")
            ->order('sort asc')
            ->select();
        $arr = [];
        foreach($goods_list as $kt=>$vt){
            $picurl = "https://".$_SERVER['HTTP_HOST'].$vt['original_img'];
            $goods_list[$kt]['picurl'] = $picurl;
            //查看该商品是否有规格
            $sepc_count = Db::name('spec_goods_price')->where("goods_id",'=',$vt['goods_id'])->count();
            $has_spec = ($sepc_count>0) ? 1 : 2;
            $sold_out = ($vt['store_count']==0) ? 1 : 2;// 1:售磐 2:未售磐
            $remark_simple = $vt['goods_remark'];
            if(mb_strlen($vt['goods_remark'])>28){
                $remark_simple = mb_substr($remark_simple,0,28)."...";
            }
            $arr[] = [
                'id'=>$vt['goods_id'],
                'picurl'=>$picurl,
          //      'price'=>intval($vt['shop_price']),
                'price'=>number_format($vt['shop_price']),
                'remark'=>$vt['goods_remark'],
                'remark_simple'=>$remark_simple,
                'name'=>$vt['goods_name'],
                'has_spec'=> $has_spec,
                'sold_out'=>$sold_out
            ];
        }
        $data = [
            'data'=>$arr,
        ];
        $this->success('success', $data);
    }

    //商品规格价格展示
    public function specItemPrice(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'];
        $user_info = $this->getGUserInfo($sess_key);

        $goods_id = $data['good_id'];
        $sepc_info = Db::name('spec_goods_price')->where("goods_id",'=',$goods_id)->select();
        $goods_info = Db::name('goods')->where("goods_id",'=',$goods_id)->field('goods_id,default_spec_item')->find();
        $default_spec_item = unserialize($goods_info['default_spec_item']);

        //获取所有规格
        $key_arr = [];//所有规格项
        $key_spec = [];//所有规格类型
        foreach($sepc_info as $ks=>$vs){
            $key_arr_spec = explode("_",$vs['key']);
            foreach($key_arr_spec as $kk=>$vk){
                $key_arr[] = $vk;
            }

        }

        $key_arr = array_unique($key_arr);  //所有规格项


        $key_spec_itme = explode("_",$sepc_info[0]['key']);
        $key_spec_item_str = '';
        foreach($key_spec_itme as $kk=>$vk){
            $key_spec_item_str = $key_spec_item_str.$vk.",";
        }
        $key_spec_item_str = substr($key_spec_item_str,0,strlen($key_spec_item_str)-1);
        $key_spec_info = Db::name('spec_item')->where('id','in',$key_spec_item_str)->field('spec_id')->select();
        $key_spec_str= '';
        foreach($key_spec_info as $kk=>$vk){
            $key_spec_str = $key_spec_str.$vk['spec_id'].",";
        }
        $key_spec_str = substr($key_spec_str,0,strlen($key_spec_str)-1);
        $key_spec_arr = Db::name('spec')
            ->where('id','in',$key_spec_str)
            ->order('sort desc')
            ->select();
       // var_dump($key_arr);
        $arr_spec_item_list = [];
        foreach($key_spec_arr as $kk=>$vk){
            $item_list_spec = [];
            $total_item = [];
            $total_item_spec = Db::name('spec_item')->where('spec_id','=',$vk['id'])->field('id,item,order_index')->order('order_index desc')->select();
            foreach($total_item_spec as $kt=>$vt){
                if(in_array($vt['id'],$key_arr)){
                    $total_item[] = $vt['id'];
                }
            }
            $total_item_spec_change = [];
            foreach($total_item_spec as $kt=>$vt){
                $total_item_spec_change[$vt['id']]=$vt;
            }

            foreach($total_item as $kt=>$vt){
                $is_default = 2;
                if(!empty($default_spec_item)){
                    if(in_array($vt,$default_spec_item)){
                        $is_default = 1;
                    }
                }


                $item_list_spec[] = [
                    'id'=>$vt,
                    'name'=>$total_item_spec_change[$vt]['item'],
                    'is_default'=>$is_default,
                ];
            }
            $arr_spec_item_list[] = [
                'id'=>$vk['id'],
                'name'=>$vk['name'],
                'order'=>$vk['order'],
                'itemList'=>$item_list_spec,
                'itemList'=>$item_list_spec,
            ];      //不带加料规格列表
        }


        //获取加料规格
        /*$plus_spec = Db::name('goods_plus')
            ->alias('p')
            ->join('tp_plus_attr a','a.id=p.plus_id')
            ->where('p.goods_id','=',$goods_id)
            ->field('p.id,p.plus_id ,a.name,a.price')
            ->order('a.sort desc')
            ->select();*/
        $plus_spec = [];


        //不带加料的价格列表
        $sepc_info_price = [];
        foreach($sepc_info as $ks=>$vs){
            $sepc_info_price[] = [
                'item'=> $vs['key'],
                'item_spec'=> $vs['key'],
                //'price'=> intval($vs['price']),
                'price'=> number_format($vs['price']),
            ];
        }

        if(count($plus_spec)>0){
            $arr_spec_item_list_plus = [];
            $plus_spec_change = [];
            foreach($plus_spec as $kp=>$vp){
                $arr_spec_item_list_plus[] = [
                    'id'=>$vp['plus_id'],
                    'name'=>$vp['name'],
                    'is_default'=>2,
                ];
                $plus_spec_change[$vp['plus_id']] = $vp;
            }
            $arr_spec_item_list_plus_item = [                 //加料规格列表
                'id'=>'-1',
                'name'=>'加料',
                'order'=>50,
                'itemList'=>$arr_spec_item_list_plus,
            ];

            $arr_spec_item_list_front = [];
            $arr_spec_item_list_end = [];
            foreach($arr_spec_item_list as $karr=>$varr){
                if($varr['order']>50){
                    $arr_spec_item_list_front[] = $varr;
                }else{
                    $arr_spec_item_list_end[] = $varr;
                }
            }
            $arr_spec_item_list_front[] = $arr_spec_item_list_plus_item;

            $specItemList = array_merge($arr_spec_item_list_front,$arr_spec_item_list_end);    //带加料的规格列表



            //把plus_id 转化为abc 用来做组合函数
            $map_plus = config('mapplus');
            $num = count($plus_spec);
            $arr_map_plus = [];
            $arr_map = [];
            $str_map = '';
            for ($i=0;$i<$num;$i++){
                $arr_map_plus[$map_plus[$i]] = $plus_spec[$i]['plus_id'];
                $arr_map[] = $map_plus[$i];
                $str_map = $str_map.$map_plus[$i];
            }
            $arr_map_result = [];
            $AlgorObj = new Algor();

            $AlgorObj->get_combinations($str_map,$arr_map_result);

            $plus_spec_attr = [];
            foreach($arr_map_result as $kp=>$vp){
                $vp_arr = str_split($vp,1);
                $spec_arr = [];
                $item = '';
                $price = 0 ;
                foreach($vp_arr as $kv=>$vv){
                    $spec_arr[] = $arr_map_plus[$vv];
                    $item = $item."_".$arr_map_plus[$vv];
                    $price = $price + $plus_spec_change[$arr_map_plus[$vv]]['price'];
                }
                $plus_spec_attr[] = [
                    'item'=>$item,
                 //   'price'=>intval($price),
                    'price'=>number_format($price),
                ];
            }

            $arr_spec_price = [];
        //    var_dump(count($plus_spec_attr));
        //    var_dump(count($sepc_info_price));
          //  exit;
            foreach($plus_spec_attr as $kp=>$vp){
                foreach($sepc_info_price as $ks=>$vs){
                    $item = $vs['item'].$vp['item'];
                    $price = $vs['price'] + $vp['price'];
                    $arr_spec_price[] = [
                        'item'=>$item,
                        'item_spec'=>$vs['item'],
                     //   'price'=>intval($price),
                        'price'=>number_format($price),
                    ];
                }
            }
            $priceList = array_merge($sepc_info_price,$arr_spec_price);

        }else{

            $specItemList =$arr_spec_item_list;
            $priceList = $sepc_info_price;
        }

        $arr = [
            'specItemList'=>$specItemList,
            'priceList'=>$priceList,
        ];
        $data = [
            'data'=>$arr,
        ];
        $this->success('success', $data);
    }




    //组合多种key
    public function explode2sort($key){
        $arr = explode("_",$key);
        $num = count($arr);
        $AlgorObj = new Algor();
        $result =   $AlgorObj->arrangement($arr,$num);
        //$this->wlog($result);
     //   var_dump($result);
        $str_result = '';
        foreach($result as $kr=>$vr){
            $str_spec = implode("_",$vr);
            $str_result = $str_result.$str_spec.",";
        }
        $str_result = substr($str_result,0,strlen($str_result)-1);
        return $str_result;
    }

    //组合多种key
    //针对spec_key 在前  attr_key 在后 的情况 优化组合数量
    public function explode2sortNew($spec_key,$key){
        if($spec_key==$key){
            return $this->explode2sort($spec_key);
        }else{
            $spec_length = strlen($spec_key)+1;
            $new_key = substr($key,$spec_length);
            $spec_key_result = $this->explode2sort($spec_key);

            $spec_key_result_arr = explode(',',$spec_key_result);
            $new_key_result = $this->explode2sort($new_key);
            $new_key_result_arr = explode(',',$new_key_result);
            $arr = [];
            foreach($spec_key_result_arr as $ks=>$vs){
                $arr[] = $vs."_".$new_key;
               /*
                foreach($new_key_result_arr as $kn=>$vn){
                    $arr[] = $vs."_".$vn;
                }*/
            }
            $str_result = '';
            foreach($arr as $kr=>$vr){
                $str_result = $str_result.$vr.",";
            }
            $str_result = substr($str_result,0,strlen($str_result)-1);
            return $str_result;
        }
    }


    //todo 组合多种key
    public function explode2group($spec_key){
        $spec_key="79_80_81";

        $plus_spec = explode("_",$spec_key);

        $map_plus = config('mapplus');
        $num = count($plus_spec);
        $arr_map_plus = [];
        $arr_map = [];
        $str_map = '';
        for ($i=0;$i<$num;$i++){
            $arr_map_plus[$map_plus[$i]] = $plus_spec[$i];
            $arr_map[] = $map_plus[$i];
            $str_map = $str_map.$map_plus[$i];
        }


        $arr_map_result = [];
        $AlgorObj = new Algor();
        $AlgorObj->get_combinations($str_map,$arr_map_result);

        var_dump($arr_map_result);exit;
    }

    //添加到购物车
    public function addToCart(){

        $stratTime = microtime(true);
        $startMemory = memory_get_usage();





        $data = $this->request->post();

        $goods_id = $data['good_id'];
        $num = $data['num'];
        $type = $data['type'] ?? 1;
        $key = $data['key'] ?? "";
        $spec_key = $data['spec_key'] ?? "";
        $sess_key = $data['sess_key'];

        $user_info = $this->getGUserInfo($sess_key);

        $goods_info = Db::name('goods')->where("goods_id",'=',$goods_id)->field('goods_id,shop_price as goods_price,store_id,goods_name')->find();

        if(!empty($spec_key)){  //有规格
            $spec_key_str = $this->explode2sort($spec_key);

          //  $spec_key_arr = explode(",",$spec_key_str);
          //  var_dump($spec_key_str);
        //    $this->wlog($spec_key_str);
            //$this->wlog(count($spec_key_arr));
            $spec_info = Db::name('spec_goods_price')
                ->where("goods_id",'=',$goods_id)
                ->where("key",'in',$spec_key_str)
                ->find();
        //    var_dump($spec_info);
        //   exit;
          //  var_dump($spec_info);
            //查看购物车是否有该商品,有的话数量+,没有的话插入数据
            /*******************testetsetestset*************************/
            //$key_str = $this->explode2sort($key);
            $key_str = $this->explode2sortNew($spec_key,$key);

            $check_cart = Db::name('cart')
                ->where('user_id','=',$user_info['user_id'])
                ->where('goods_id','=',$goods_id)
                ->where('key','in',$key_str)
                ->find();
            if(!empty($check_cart)){                            //购物车有该数据
             /*   var_dump("有该数据");*/
                $goods_num = $check_cart['goods_num'];
                if($type==1){
                    $new_num = $goods_num + $num;
                }else{
                    $new_num = $goods_num - $num;
                    if($new_num<0){
                        $new_num = 0;
                    }
                }

                if($key == $spec_key ){  //没有加料
                /*    var_dump("无加料");*/
                    $spec_key_price = $key_price = $spec_info['price'];
                    $map = [
                        'key_price'=>number_format($spec_info['price']),
                        'spec_key_price'=>number_format($spec_info['price']),
                        'goods_price'=>$goods_info['goods_price'],
                        'goods_num'=>$new_num,
                    ];
                   // Db::name('cart')->where($map)->setInc('goods_num', $num);
                }else{                  //有加料
              /*      var_dump("有加料");*/
                    $spec_key_price = number_format($spec_info['price']);
                    $spec_len = strlen($spec_key)+1;
                    $plus_str = substr($key,$spec_len);
                    $plus_str = str_replace("_",",",$plus_str);
                  //  $plus_arr = exlode("_",$plus_str);
                    $plus_price = Db::name('plus_attr')->where('id','in',$plus_str)->sum('price');
                    $key_price = $spec_key_price + $plus_price;
                    $map = [
                        'key_price'=>$key_price,
                        'spec_key_price'=>$spec_info['price'],
                        'goods_price'=>$goods_info['goods_price'],
                        'goods_num'=>$new_num,
                    ];
                  //  var_dump($map);
                }
            /*    echo "更新数据";
                var_dump($map);*/
                Db::name('cart')
                    ->where('goods_id','=',$goods_id)
                    ->where('key','in',$key_str)
                    ->where('user_id','=',$user_info['user_id'])
                    ->update($map);
                $cart_id = $check_cart['id'];
            }else{    //购物车无该数据
                $now = time();
          //      var_dump("无该数据");
                if($key == $spec_key ) {  //没有加料
             //       var_dump("没有加料");
                    $arr_insert = [
                        'user_id'=>$user_info['user_id'],
                        'goods_id'=>$goods_id,
                        'goods_name'=>$goods_info['goods_name'],
                        'goods_price'=>$goods_info['goods_price'],
                        'goods_num'=>$num,
                        'item_id'=>$spec_info['item_id'],
                        'spec_key'=>$spec_info['key'],
                        'spec_key_name'=>$spec_info['key_name'],
                        'spec_key_price'=>$spec_info['price'],
                        'key'=>$spec_info['key'],
                        'key_name'=>$spec_info['key_name'],
                        'key_price'=>$spec_info['price'],
                        'add_time'=>1,  //默认选中
                        'prom_type'=>1,
                        'store_id'=>$goods_info['store_id'],
                    ];
                }else{                    //有加料
                /*    var_dump("有加料");*/

                    $spec_key_price = $spec_info['price'];
                    $spec_len = strlen($spec_key)+1;
                    $plus_str = substr($key,$spec_len);
                    $plus_str = str_replace("_",",",$plus_str);
                    $plus_price = 0;
                    //  $plus_arr = exlode("_",$plus_str);
                    $plus_list = Db::name('plus_attr')
                        ->where('id','in',$plus_str)
                        ->field("price,name")
                        ->select();

                    $plus_name = '加料:';
                    foreach($plus_list as $kp=>$vp){
                        $plus_name = $plus_name.$vp['name'].",";
                        $plus_price = $plus_price + $vp['price'];
                    }
                    $plus_name = mb_substr($plus_name,0,mb_strlen($plus_name)-1);
                    $key_name =  $spec_info['key_name']." ".$plus_name;
                    $key_price = $spec_key_price + $plus_price;
                    $arr_insert = [
                        'user_id'=>$user_info['user_id'],
                        'goods_id'=>$goods_id,
                        'goods_name'=>$goods_info['goods_name'],
                        'goods_price'=>$goods_info['goods_price'],
                        'goods_num'=>$num,
                        'item_id'=>$spec_info['item_id'],
                        'spec_key'=>$spec_info['key'],
                        'spec_key_name'=>$spec_info['key_name'],
                        'spec_key_price'=>$spec_info['price'],
                        'key'=>$key,
                        'key_name'=>$key_name,
                        'key_price'=>$key_price,
                        'add_time'=>1,  //默认选中
                        'prom_type'=>1,
                        'store_id'=>$goods_info['store_id'],
                        ];
                }
              /*  echo "插入数据";
                var_dump($arr_insert);*/
                $cart_id = Db::name('cart')->insertGetId($arr_insert);
            }

        }else{  //无规格
            //查看购物车是否有该商品,有的话数量+,没有的话插入数据
            $check_cart = Db::name('cart')
                ->where('user_id','=',$user_info['user_id'])
                ->where('goods_id','=',$goods_id)
                ->find();
            if(!empty($check_cart)){    //购物车有该商品
                $goods_num = $check_cart['goods_num'];
                if($type==1){
                    $new_num = $goods_num + $num;
                }else{
                    $new_num = $goods_num - $num;
                    if($new_num<0){
                        $new_num = 0;
                    }
                }
                $map = [
                    'key_price'=>$goods_info['goods_price'],
                    'spec_key_price'=>$goods_info['goods_price'],
                    'goods_price'=>$goods_info['goods_price'],
                    'goods_num'=>$new_num,
                ];
                Db::name('cart')
                    ->where('goods_id','=',$goods_id)
                    ->where('user_id','=',$user_info['user_id'])
                    ->update($map);
                $cart_id = $check_cart['id'];

            }else{                      //购物车没有该商品
                $arr_insert = [
                    'user_id'=>$user_info['user_id'],
                    'goods_id'=>$goods_id,
                    'goods_name'=>$goods_info['goods_name'],
                    'goods_price'=>$goods_info['goods_price'],
                    'goods_num'=>$num,
                    'spec_key_price'=>$goods_info['goods_price'],
                    'key_price'=>$goods_info['goods_price'],
                    'add_time'=>1,  //默认选中
                    'prom_type'=>1,
                    'store_id'=>$goods_info['store_id'],
                ];
                $cart_id = Db::name('cart')->insertGetId($arr_insert);
            }
         }

         //清除该用户购物车数量为0的cart_id
        $this->wipeZeroCart($user_info['user_id']);


        $endTime = microtime(true);
        $runtime = ($endTime - $stratTime) * 1000; //将时间转换为毫秒
        $endMemory = memory_get_usage();
        $usedMemory = ($endMemory - $startMemory) / 1024;
        $this->wlog( "addToCart".PHP_EOL);
        $this->wlog( "运行时间: {$runtime} 毫秒".PHP_EOL);
        $this->wlog( "耗费内存: {$usedMemory} K".PHP_EOL);



        $this->success("success");
    }

    //清除该用户购物车数量为0的cart_id
    public function wipeZeroCart($user_id){
        Db::name('cart')
            ->where('goods_num','=',0)
            ->delete();
    }

    //我的购物车列表
    public function cartList(){


        $stratTime = microtime(true);
        $startMemory = memory_get_usage();



        $data = $this->request->post();
        $sess_key = $data['sess_key'];
        $store_id = $data['store_id'];
        $user_info = $this->getGUserInfo($sess_key);
        $cart_list = Db::name('cart')
            ->where('user_id','=',$user_info['user_id'])
            ->where('store_id','=',$store_id)
            ->select();
        $goodList = [];
        $price = 0;
        $item_num = 0;
        $goods_info = [];
        foreach($cart_list as $kc=>$vc){
            if(empty($goods_info[$vc['goods_id']])){
                $goods_info[$vc['goods_id']] = $vc['goods_num'];
            }else{
                $goods_info[$vc['goods_id']] = $goods_info[$vc['goods_id']] + $vc['goods_num'];
            }
            $item_price = $vc['key_price'] * $vc['goods_num'] ;
            $price = $price + $item_price;
            $item_num = $item_num + $vc['goods_num'];
            $goodList[] = [
                'cart_id'=>$vc['id'],
                'name'=>$vc['goods_name'],
                'spec'=>$vc['key'],
                'spec_item'=>$vc['key_name'],
                'num'=>$vc['goods_num'],
                'price'=>$vc['key_price'],
                'total_price'=>$item_price,
            ];
        }
        $goods_info_new = [];
        foreach($goods_info as $kg=>$vg){
            $goods_info_new[] = [
                'goods_id'=>$kg,
                'goods_num'=>$vg,
            ];
        }
        $respond_arr = [
            'goodList'=>$goodList,
            'goods_info'=>$goods_info_new,
            'price'=>$price,
            'total_num'=>$item_num,
        ];
        $data = [
            'data'=>$respond_arr,
        ];


        $endTime = microtime(true);
        $runtime = ($endTime - $stratTime) * 1000; //将时间转换为毫秒
        $endMemory = memory_get_usage();
        $usedMemory = ($endMemory - $startMemory) / 1024;
        $this->wlog( "cartList".PHP_EOL);
        $this->wlog( "运行时间: {$runtime} 毫秒".PHP_EOL);
        $this->wlog( "耗费内存: {$usedMemory} K".PHP_EOL);


        $this->success('success', $data);
    }


    //修改购物车数量
    public function editCart(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'];
        $cart_id = $data['cart_id'];
        $num = $data['num'];
        $user_info = $this->getGUserInfo($sess_key);
       // $check_cart = Db::name('cart')->where('id','=',$cart_id)->find();
        Db::name('cart')->where('id','=',$cart_id)->update(['goods_num'=>$num]);
        $this->wipeZeroCart($user_info['user_id']);
        $this->success('success');
    }

    //清空购物车
    public function emptyCart(){
        $data = $this->request->post();
        $sess_key = $data['sess_key'];
        $store_id = $data['store_id'];

        $user_info = $this->getGUserInfo($sess_key);
        $result = Db::name('cart')
            ->where('user_id','=',$user_info['user_id'])
            ->where('store_id','=',$store_id)
            ->delete();
        $this->success('success');
    }










}
