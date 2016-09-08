<?php

namespace Custom\AdminBundle\Controller;
use Topxia\WebBundle\Controller\BaseController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\FileToolkit;
use PHPExcel_IOFactory;
use PHPExcel_Cell;
use Topxia\Common\SimpleValidator;



class UserImporterController extends BaseController
{   
    public function importUserInfoByExcelAction(Request $request)
    {
        if ($request->getMethod() == 'POST') {
            $checkType=$request->request->get("rule");
            $organizationId=$request->request->get("organizationId");
            $file=$request->files->get('excel');
            $errorInfo=array();
            $checkInfo=array();
            $userCount=0;
            $allUserData=array();

            if(!is_object($file)){
                $this->setFlashMessage('danger', '请选择上传的文件');

                return $this->render('CustomAdminBundle:UserImporter:userinfo.excel.html.twig', array(
                ));
            }
            if (FileToolkit::validateFileExtension($file,'xls xlsx')) {

                $this->setFlashMessage('danger', 'Excel格式不正确！');

                return $this->render('CustomAdminBundle:UserImporter:userinfo.excel.html.twig', array(
                ));
            }

            $objPHPExcel = PHPExcel_IOFactory::load($file);
            $objWorksheet = $objPHPExcel->getActiveSheet();
            $highestRow = $objWorksheet->getHighestRow(); 

            $highestColumn = $objWorksheet->getHighestColumn();
            $highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);   

            if($highestRow>1000){

                $this->setFlashMessage('danger', 'Excel超过1000行数据!');

                return $this->render('CustomAdminBundle:UserImporter:userinfo.excel.html.twig', array(
                ));
            }

            $fieldArray=$this->getFieldArray();

            for ($col = 0;$col < $highestColumnIndex;$col++){

                $fieldTitle=$objWorksheet->getCellByColumnAndRow($col, 2)->getValue();
                $strs[$col]=$fieldTitle."";
            }   
            $excelField=$strs;
            if(!$this->checkNecessaryFields($excelField)){

                $this->setFlashMessage('danger', '缺少必要的字段');

                return $this->render('CustomAdminBundle:UserImporter:userinfo.excel.html.twig', array(
                ));
            }

            $fieldSort=$this->getFieldSort($excelField,$fieldArray);
            unset($fieldArray,$excelField);

            $repeatInfo=$this->checkRepeatData($row=3,$fieldSort,$highestRow,$objWorksheet);

            if($repeatInfo){

                $errorInfo[]=$repeatInfo;
                return $this->render('CustomAdminBundle:UserImporter:userinfo.excel.step2.html.twig', array(
                    "errorInfo"=>$errorInfo,
                ));

            }

            for ($row = 3;$row <= $highestRow;$row++) 
            {
                $strs=array();

                for ($col = 0;$col < $highestColumnIndex;$col++)
                {
                     $infoData=$objWorksheet->getCellByColumnAndRow($col, $row)->getFormattedValue();
                     $strs[$col]=$infoData."";
                     unset($infoData);
                }

                foreach ($fieldSort as $sort) {

                    $num=$sort['num'];
                    $key=$sort['fieldName'];

                    $userData[$key]=$strs[$num];
                    $fieldCol[$key]=$num+1;
                }
                unset($strs);
                $emptyData=array_count_values($userData);
                if(isset($emptyData[""])&&count($userData)==$emptyData[""]) {
                    $checkInfo[]="第".$row."行为空行，已跳过";
                    continue;
                }

                if($this->validFields($userData,$row,$fieldCol)){  
                    $errorInfo=array_merge($errorInfo,$this->validFields($userData,$row,$fieldCol));
                    continue;
                }

                if(!$this->getUserService()->isNicknameAvaliable($userData['nickname'])){ 

                    if($checkType=="ignore") {
                        $checkInfo[]="第".$row."行的用户已存在，已略过"; 
                        continue;
                    }
                    if($checkType=="update") {
                        $checkInfo[]="第".$row."行的用户已存在，将会更新";          
                    }
                    $userCount=$userCount+1; 
                    $allUserData[]= $userData;            
                    continue;
                }
                if(!$this->getUserService()->isEmailAvaliable($userData['email'])){          

                    if($checkType=="ignore") {
                        $checkInfo[]="第".$row."行的用户已存在，已略过";
                        continue;
                    };
                    if($checkType=="update") {
                        $checkInfo[]="第".$row."行的用户已存在，将会更新";
                    }  
                    $userCount=$userCount+1; 
                    $allUserData[]= $userData;     
                    continue;
                }

                $userCount=$userCount+1; 

                $allUserData[]= $userData;
                unset($userData);
            }

            $allUserData=json_encode($allUserData);

            return $this->render('CustomAdminBundle:UserImporter:userinfo.excel.step2.html.twig', array(
                'userCount'=>$userCount,
                'errorInfo'=>$errorInfo,
                'checkInfo'=>$checkInfo,
                'allUserData'=>$allUserData,
                'checkType'=>$checkType,
                'organizationId'=>$organizationId
            ));

        }

        return $this->render('CustomAdminBundle:UserImporter:userinfo.excel.html.twig', array(
        ));
    }


    public function importUserDataToBaseAction(Request $request)
    {   
        $userData=$request->request->get("data");
        $organizationId=$request->request->get("organizationId");
        $userData=json_decode($userData,true);
        $checkType=$request->request->get("checkType");
        $userByEmail=array();
        $userByNickname=array();
        $users=array();

        if($checkType=="ignore"){

            $this->getUserImporterService()->importUsers($organizationId,$userData);

        }
        if($checkType=="update"){
            
            foreach ($userData as $key => $user) {
                if ($user["gender"]=="男")$user["gender"]="male";
                if ($user["gender"]=="女")$user["gender"]="female";
                if ($user["gender"]=="")$user["gender"]="secret";

                if($this->getUserService()->getUserByNickname($user["nickname"])){
                    $userByNickname[]=$user;
                }
                elseif ($this->getUserService()->getUserByEmail($user["email"])){
                    $userByEmail[]=$user;
                }else {
                    $users[]=$user; 
                }      
            }
            $this->getUserImporterService()->importUpdateNickname($organizationId,$userByNickname); 
            $this->getUserImporterService()->importUpdateEmail($organizationId,$userByEmail); 
            $this->getUserImporterService()->importUsers($organizationId,$users);      
        }
        return $this->render('UserImporterBundle:UserImporter:userinfo.excel.step3.html.twig', array(
        ));
    }


    private function getFieldArray()
    {       
        $userFieldArray=array();

        $userFields=$this->getUserFieldService()->getAllFieldsOrderBySeqAndEnabled();
        $fieldArray=array(
            "nickname"=>'用户名',
            "email"=>'邮箱',
            "password"=>'密码',
            "truename"=>'姓名',
            "gender"=>'性别',
            "idcard"=>'身份证号',
            "mobile"=>'手机号码',
            "company"=>'公司',
            "job"=>'职业',
            "site"=>'个人主页',
            "weibo"=>'微博',
            "weixin"=>'微信',
            "qq"=>'QQ',
        );
        
        foreach ($userFields as $userField) {
            $title=$userField['title'];

            $userFieldArray[$userField['fieldName']]=$title;
        }
        $fieldArray=array_merge($fieldArray,$userFieldArray);
        return $fieldArray;
    }

    protected function getUserFieldService()
    {
        return $this->getServiceKernel()->createService('User.UserFieldService');
    }

    private function checkNecessaryFields($data)
    {       
        $data=implode("", $data);
        $data=$this->trim($data);
        $tmparray = explode("用户名",$data);
        if (count($tmparray)<=1) return false; 

        $tmparray = explode("邮箱",$data);
        if (count($tmparray)<=1) return false; 

        $tmparray = explode("密码",$data);
        if (count($tmparray)<=1) return false; 

        $tmparray = explode("姓名",$data);
        if (count($tmparray)<=1) return false;

        return true;
    }

    private function trim($data)
    {       
        $data=trim($data);
        $data=str_replace(" ","",$data);
        $data=str_replace('\n','',$data);
        $data=str_replace('\r','',$data);
        $data=str_replace('\t','',$data);

        return $data;
    }

    private function getFieldSort($excelField,$fieldArray)
    {       
        $fieldSort=array();
        foreach ($excelField as $key => $value) {

            $value=$this->trim($value);

            if(in_array($value, $fieldArray)){
                foreach ($fieldArray as $fieldKey => $fieldValue) {
                    if($value==$fieldValue) {
                        $fieldSort[]=array("num"=>$key,"fieldName"=>$fieldKey);
                        break;
                    }
                }
            }

         }

         return $fieldSort;
    }

    private function checkRepeatData($row,$fieldSort,$highestRow,$objWorksheet)
    {
        $errorInfo=array();
        $emailData=array();
        $nicknameData=array();

        foreach ($fieldSort as $key => $value) {
            if($value["fieldName"]=="nickname"){
                $nickNameCol=$value["num"];
            }
            if($value["fieldName"]=="email"){
                $emailCol=$value["num"];
            }
        }

        for ($row ;$row <= $highestRow;$row++) {

            $emailColData =$objWorksheet->getCellByColumnAndRow($emailCol, $row)->getValue(); 
            if($emailColData.""=="") continue;
            $emailData[]=$emailColData."";         
        }

        $errorInfo=$this->arrayRepeat($emailData);

        for ($row=3 ;$row <= $highestRow;$row++) {

            $nickNameColData=$objWorksheet->getCellByColumnAndRow($nickNameCol, $row)->getValue();      
            if($nickNameColData.""=="") continue;
            $nicknameData[]=$nickNameColData.""; 
        }

        $errorInfo.=$this->arrayRepeat($nicknameData);

        return $errorInfo;
    }

    private function arrayRepeat($array)
    {   
        $repeatArray=array();
        $repeatArrayCount=array_count_values($array);
        $repeatRow="";

        foreach ($repeatArrayCount as $key => $value) {
            if($value>1) {$repeatRow.="重复:<br>";
               for($i=1;$i<=$value;$i++){
                $row=array_search($key, $array)+3;
                $repeatRow.="第".$row."行"."    ".$key."<br>";
                unset($array[$row-3]);
               }
            }
        }

        return $repeatRow;
    }

    private function validFields($userData,$row,$fieldCol)
    {    
        $errorInfo=array();

        if (!SimpleValidator::email($userData['email'])) {
            $errorInfo[]="第 ".$row."行".$fieldCol["email"]." 列 的数据存在问题，请检查。";
        }

        if (!SimpleValidator::nickname($userData['nickname'])) {
            $errorInfo[]="第 ".$row."行".$fieldCol["nickname"]." 列 的数据存在问题，请检查。";
        }

        if (!SimpleValidator::password($userData['password'])) {
            $errorInfo[]="第 ".$row."行".$fieldCol["password"]." 列 的数据存在问题，请检查。";
        }

        if (!isset($userData['truename']) || empty($userData['truename']) || !SimpleValidator::truename($userData['truename'])) {
            $errorInfo[]="第 ".$row."行".$fieldCol["truename"]." 列 的数据存在问题，请检查。";
        }

        if (isset($userData['idcard']) &&$userData['idcard']!=""&& !SimpleValidator::idcard($userData['idcard'])) {
            $errorInfo[]="第 ".$row."行".$fieldCol["idcard"]." 列 的数据存在问题，请检查。";
        }

        if (isset($userData['mobile'])&&$userData['mobile']!=""&& !SimpleValidator::mobile($userData['mobile'])) {
            $errorInfo[]="第 ".$row."行".$fieldCol["mobile"]." 列 的数据存在问题，请检查。";
        }
        if (isset($userData['gender'])&&$userData['gender']!=""&& !in_array($userData['gender'], array("男","女"))){
            $errorInfo[]="第 ".$row."行".$fieldCol["gender"]." 列 的数据存在问题，请检查。";
        }

        if (isset($userData['qq'])&&$userData['qq']!=""&& !SimpleValidator::qq($userData['qq'])){
            $errorInfo[]="第 ".$row."行".$fieldCol["qq"]." 列 的数据存在问题，请检查。";
        }

        if (isset($userData['site'])&&$userData['site']!=""&& !SimpleValidator::site($userData['site'])){
            $errorInfo[]="第 ".$row."行".$fieldCol["site"]." 列 的数据存在问题，请检查。";
        }

        if (isset($userData['weibo'])&&$userData['weibo']!=""&& !SimpleValidator::site($userData['weibo'])){
            $errorInfo[]="第 ".$row."行".$fieldCol["weibo"]." 列 的数据存在问题，请检查。";
        }

        for($i=1;$i<=5;$i++){
            if (isset($userData['intField'.$i])&&$userData['intField'.$i]!=""&& !SimpleValidator::integer($userData['intField'.$i])){
            $errorInfo[]="第 ".$row."行".$fieldCol["intField".$i]." 列 的数据存在问题，请检查(必须为整数,最大到9位整数)。";
             }
            if (isset($userData['floatField'.$i])&&$userData['floatField'.$i]!=""&& !SimpleValidator::float($userData['floatField'.$i])){
            $errorInfo[]="第 ".$row."行".$fieldCol["floatField".$i]." 列 的数据存在问题，请检查(只保留到两位小数)。";
             }
            if (isset($userData['dateField'.$i])&&$userData['dateField'.$i]!=""&& !SimpleValidator::date($userData['dateField'.$i])){
            $errorInfo[]="第 ".$row."行".$fieldCol["dateField".$i]." 列 的数据存在问题，请检查(格式如XXXX-MM-DD)。";
             }
        }
        return $errorInfo;
    }

    protected function getUserImporterService()
    {
        return $this->getServiceKernel()->createService('Custom:UserImporter.UserImporterService');
    }

}
