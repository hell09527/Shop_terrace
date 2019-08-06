<?php
/**
 * Created by PhpStorm.
 * PROJECT_NAME: niushopProd
 * FILE_NAME: BcExcel.php
 * Author: Dai
 * Date: 2018/7/29
 * Time: 13:41
 */
namespace app\admin\controller;
use app\admin\controller\BaseController;

class BcExcel extends BaseController {
    public function excelList(){
        $this->display();
    }
//    导入
    public function import(){
        if(!empty($_FILES['file_stu']['name'])){
            $tmp_file   = $_FILES['file_stu']['tmp_name'];    //临时文件名
            $file_types = explode('.',$_FILES['file_stu']['name']); //  拆分文件名
            $file_type  = $file_types [count ( $file_types ) - 1];   //  文件类型
            /*判断是否为excel文件*/
            if($file_type == 'xls' || $file_type == 'xlsx'|| $file_type == 'csv'){    //  符合类型
                /*上传业务*/
                $upload = new \Think\Upload();
                $upload->maxSize   =     3145728 ;
                $upload->exts      =     array('xls', 'csv', 'xlsx');
                $upload->rootPath  =      './Public';
                $upload->savePath  =      '/Excel/';
                $upload->saveName  =      date('YmdHis');
                $info              =   $upload->upload();
                if(!$info) {    // 上传错误提示错误信息
                    $this->error($upload->getError());
                }else{  // 上传成功

                    //  读取文件
                    $filename='./Public'.$info['file_stu']['savepath'].$info['file_stu']['savename'];
                    import("Org.Yufan.ExcelReader");
                    vendor('PHPExcel.PHPExcel');
                    $reader = \PHPExcel_IOFactory::createReader('Excel2007'); //设置以Excel5格式(Excel97-2003工作簿)
                    $PHPExcel = $reader->load($filename); // 载入excel文件
                    $sheet = $PHPExcel->getSheet(0); // 读取第一個工作表
                    $highestRow = $sheet->getHighestRow(); // 取得总行数
                    var_dump($highestRow);
                    $highestColumm = $sheet->getHighestColumn(); // 取得总列数

                    /** 循环读取每个单元格的数据 */
                    $data = array();
                    for ($row = 2; $row <= $highestRow; $row++){//行数是以第1行开始

                        if($column = 'A'){
                            $data['name'] = $sheet->getCell($column.$row)->getValue();
                        }
                        if($column = 'B'){
                            $data['account'] = $sheet->getCell($column.$row)->getValue();
                        }
                        if($column = 'C'){
                            $data['password'] = $sheet->getCell($column.$row)->getValue();
                        }
                    }
                    return $data;
                }
            } else{ //  不符合类型业务
                $this->error('不是excel文件，请重新上传...');
            }
        }else{
            $this->error('(⊙o⊙)~没传数据就导入');
        }
    }
}
