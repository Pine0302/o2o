<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/21 0021
 * Time: 上午 10:44
 */
namespace app\common\library;
use PHPExcel_IOFactory;
use PHPExcel;


 class Importer
 {
     /**
      * 构造函数
      * @param $sessionKey string 用户在小程序登录后获取的会话密钥
      * @param $appid string 小程序的appid
      */
     public function __construct()
     {

     }


     /**
      *  数据导入
      * @param string $file excel文件
      * @param string $sheet
      * @return string   返回解析数据
      * @throws PHPExcel_Exception
      * @throws PHPExcel_Reader_Exception
      */
     function importExecl($file, $sheet=0){

         $objPHPExcel = new \PHPExcel();
         if (!file_exists($file)) {
             die('no file!');
         }
         $extension = strtolower( pathinfo($file, PATHINFO_EXTENSION) );

         if ($extension =='xlsx') {
             $objRead = new \PHPExcel_Reader_Excel2007($objPHPExcel);   //建立reader对象
             $objExcel = $objRead ->load($file);
         } else if ($extension =='xls') {
             $objRead = new \PHPExcel_Reader_Excel5($objPHPExcel);
             $objExcel = $objRead ->load($file);
         } else if ($extension=='csv') {
             $PHPReader = new \PHPExcel_Reader_CSV($objPHPExcel);
             //默认输入字符集
             $PHPReader->setInputEncoding('GBK');
             //默认的分隔符
             $PHPReader->setDelimiter(',');
             //载入文件
             $objExcel = $PHPReader->load($file);
         }
         $cellName = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');
         $obj = $objRead->load($file);  //建立excel对象
         $title =$objPHPExcel->getActiveSheet()->getTitle();
         $currSheet = $obj->getSheet($sheet);   //获取指定的sheet表
         $columnH = $currSheet->getHighestColumn();   //取得最大的列号
         $columnCnt = array_search($columnH, $cellName);
         $rowCnt = $currSheet->getHighestRow();   //获取总行数
         $shared = new \PHPExcel_Shared_Date();
         $data = array();
         for($_row=1; $_row<=$rowCnt; $_row++){  //读取内容
             for($_column=0; $_column<=$columnCnt; $_column++){
                 $cellId = $cellName[$_column].$_row;
                 $cellValue = $currSheet->getCell($cellId)->getValue();
                 //$cellValue = $currSheet->getCell($cellId)->getCalculatedValue();  #获取公式计算的值
                 if($cellValue instanceof PHPExcel_RichText){   //富文本转换字符串
                     $cellValue = $cellValue->__toString();
                 }

                 /* if(strpos($cellId,'D') !== false){
                      if($cellId!="D1"){
                          $cellValue =  $shared ->ExcelToPHP($cellValue);   //时间转换为时间戳
                          $cellValue = date("Y-m-d",$cellValue);
                      }
                  }*/
                 $data[$_row][$cellName[$_column]] = $cellValue;
             }
         }
         return $data;
     }



 }


