<?php
namespace Custom\WebBundle\Controller;

use Topxia\Common\Paginator;
use Symfony\Component\HttpFoundation\Request;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\FileToolkit;
use PHPExcel_IOFactory;
use PHPExcel_Cell;
use Topxia\Common\SimpleValidator;
use Symfony\Component\HttpFoundation\Response;
use Topxia\WebBundle\Controller\BaseController;

class CustomClassroomManageController extends BaseController
{   
    static public $startRow = 2;
    static public $titleRow = 1;
    
    public function batchCreateAction(Request $request,$id)
    {
        
        $this->getClassroomService()->tryManageClassroom($id);
        $classroom=$this->getClassroomService()->getClassroom($id);

         if ($request->getMethod() == 'POST') {
            $checkType=$request->request->get("rule");
            $file=$request->files->get('excel');

            $errorInfo=array();
            $checkInfo=array();
            $userCount=0;
            $allUserData=array();

            if(!is_object($file)){
                $this->setFlashMessage('danger', '请选择上传的文件');

                return $this->render('CustomWebBundle:CustomClassroomManage:users-excel.html.twig', 
                    array(
                        'classroom'=>$classroom
                    ));
            }
            if (FileToolkit::validateFileExtension($file,'xls xlsx')) {

                $this->setFlashMessage('danger', 'Excel格式不正确！');

                  return $this->render('CustomWebBundle:CustomClassroomManage:users-excel.html.twig', 
                    array(
                        'classroom'=>$classroom
                    ));
            }

            $objPHPExcel = PHPExcel_IOFactory::load($file);
            $objWorksheet = $objPHPExcel->getActiveSheet();
            $highestRow = $objWorksheet->getHighestRow(); 

            $highestColumn = $objWorksheet->getHighestColumn();
            $highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);   

            if($highestRow>1000){

                $this->setFlashMessage('danger', 'Excel超过1000行数据!');

                return $this->render('CustomWebBundle:CustomClassroomManage:users-excel.html.twig', 
                    array(
                        'classroom'=>$classroom
                    ));
            }

            $fieldArray=$this->getFieldArray();

            for ($col = 0;$col < $highestColumnIndex;$col++)
            {
                 $fieldTitle=$objWorksheet->getCellByColumnAndRow($col, $this::$titleRow)->getValue();
                 $strs[$col]=$fieldTitle."";
            }   
            $excelField=$strs;
            if(!$this->checkNecessaryFields($excelField)){

                $this->setFlashMessage('danger', '缺少必要的字段');

              return $this->render('CustomWebBundle:CustomClassroomManage:users-excel.html.twig', 
                array(
                    'classroom'=>$classroom
                ));
            }

          

            $fieldSort=$this->getFieldSort($excelField,$fieldArray);
           
            unset($fieldArray,$excelField);


            $repeatInfo=$this->checkRepeatData($row=$this::$startRow,$fieldSort,$highestRow,$objWorksheet);

            if($repeatInfo){

                $errorInfo[]=$repeatInfo;
                return $this->render('CustomWebBundle:CustomClassroomManage:userinfo.excel.step2.html.twig', array(
                    "errorInfo"=>$errorInfo,
                    'classroom'=>$classroom
                ));

            }

            for ($row = $this::$startRow;$row <= $highestRow;$row++) 
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
                           
                }else{
                      $errorInfo[]="第".$row."行的用户不存在！请检查"; 
                      continue;
                }

                //判断用户是否能添加到班级
                $user = $this->getUserService()->getUserByNickname($userData['nickname']);

                $isClassroomTeacher = $this->getClassroomService()->isClassroomTeacher($id, $user['id']);
                if($isClassroomTeacher){
                    $errorInfo[]="第".$row."行的用户是本班级的教师，不能添加!"; 
                    continue;
                }

                $isClassroomStudent = $this->getClassroomService()->isClassroomStudent($id, $user['id']);
                if($isClassroomStudent){
                    $checkInfo[]="第".$row."行用户已是本班级的学员了，已跳过";
                    continue;
                } else {
                    $userCount=$userCount+1; 
                    $allUserData[]= $userData;    
                    continue;
                }
                    unset($userData);
            }
        


            $allUserData=json_encode($allUserData);



            return $this->render('CustomWebBundle:CustomClassroomManage:userinfo.excel.step2.html.twig', array(
                'userCount'=>$userCount,
                'errorInfo'=>$errorInfo,
                'checkInfo'=>$checkInfo,
                'allUserData'=>$allUserData,
                'checkType'=>$checkType,
                'classroom'=>$classroom
            ));

        }

    

        return $this->render('CustomWebBundle:CustomClassroomManage:users-excel.html.twig', 
            array(
            'classroom'=>$classroom
        ));
    }

    public function batchBaseAction(Request $request,$id)
    {   
        
        $userData=$request->request->get("data");

        $userData=json_decode($userData,true);
        
        $userByEmail=array();
        $userByNickname=array();
        $users=array();
       
        $this->getClassroomService()->tryManageClassroom($id);
        $classroom=$this->getClassroomService()->getClassroom($id);
        $currentUser = $this->getCurrentUser();
        foreach ($userData as $key => $value) {
            $user = $this->getUserService()->getUserByNickname($value['nickname']);
            $this->becomeStudent($user,$classroom,$currentUser);
        }

        
        return $this->render('CustomWebBundle:CustomClassroomManage:userinfo.excel.step3.html.twig', array(
            'classroom' => $classroom
        ));
    }

    private function becomeStudent($user,$classroom,$currentUser){
        $order = $this->getOrderService()->createOrder(array(
            'userId' => $user['id'],
            'title' => "购买班级《{$classroom['title']}》(管理员添加)",
            'targetType' => 'classroom',
            'targetId' => $classroom['id'],
            'amount' => 0,
            'payment' => 'none',
            'snPrefix' => 'CR',
        ));

        $this->getOrderService()->payOrder(array(
            'sn' => $order['sn'],
            'status' => 'success', 
            'amount' => $order['amount'], 
            'paidTime' => time(),
        ));

        $info = array(
            'orderId' => $order['id'],
            'note'  => $currentUser['nickname']."批量添加",
        );

        $this->getClassroomService()->becomeStudent($order['targetId'], $order['userId'], $info);

        $member = $this->getClassroomService()->getClassroomMember($classroom['id'], $user['id']);
        
        $userUrl = $this->generateUrl('user_show', array('id'=>$currentUser['id']), true);
        $this->getNotificationService()->notify($member['userId'], 'default', "您被<a href='{$userUrl}' target='_blank'><strong>{$currentUser['nickname']}</strong></a>加入班级<strong>{$classroom['title']}</strong>成为正式学员");

        $this->getLogService()->info('classroom', 'add_student', "班级《{$classroom['title']}》(#{$classroom['id']})，添加学员{$user['nickname']}(#{$user['id']})，备注：{$currentUser['nickname']}批量添加");
    }
    private function validFields($userData,$row,$fieldCol)
    {    
        $errorInfo=array();
        if (!SimpleValidator::nickname($userData['nickname'])) {
            $errorInfo[]="第 ".$row."行".$fieldCol["nickname"]." 列 的数据存在问题，请检查。";
        }
        return $errorInfo;
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
    private function arrayRepeat($array)
    {   

        $repeatArray=array();
        $repeatArrayCount=array_count_values($array);

        $repeatRow="";

        foreach ($repeatArrayCount as $key => $value) {

            if($value>1) {$repeatRow.="重复:<br>";
               for($i=1;$i<=$value;$i++){
                $row=array_search($key, $array)+$this::$startRow;
                $repeatRow.="第".$row."行"."    ".$key."<br>";
                unset($array[$row-$this::$startRow]);
               }
            }
        }
        return $repeatRow;
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
        }

        for ($row=$this::$startRow ;$row <= $highestRow;$row++) {
            $nickNameColData=$objWorksheet->getCellByColumnAndRow($nickNameCol, $row)->getValue();      
            if($nickNameColData.""=="") continue;
            $nicknameData[]=$nickNameColData.""; 
        }
       $errorInfo = $this->arrayRepeat($nicknameData);

        return $errorInfo;
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
    private function checkNecessaryFields($data)
    {       
        $data=implode("", $data);
        $data=$this->trim($data);
        $tmparray = explode("用户名",$data);
        if (count($tmparray)<=1) return false; 

        return true;
    }
    private function getFieldArray()
    {       
        $userFieldArray=array();

        $userFields=$this->getUserFieldService()->getAllFieldsOrderBySeqAndEnabled();

        $fieldArray=array(
                "nickname"=>'用户名'
                );
        
        foreach ($userFields as $userField) {
            $title=$userField['title'];

            $userFieldArray[$userField['fieldName']]=$title;
        }
        $fieldArray=array_merge($fieldArray,$userFieldArray);

        return $fieldArray;
    }



    public function createAction(Request $request, $id)
    {
        $this->getClassroomService()->tryManageClassroom($id);
        $classroom=$this->getClassroomService()->getClassroom($id);

        $currentUser = $this->getCurrentUser();

        if ('POST' == $request->getMethod()) {
            $data = $request->request->all();
            $user = $this->getUserService()->getUserByNickname($data['nickname']);
            if (empty($user)) {
                throw $this->createNotFoundException("用户{$data['nickname']}不存在");
            }

            if ($this->getClassroomService()->isClassroomStudent($classroom['id'], $user['id'])) {
                throw $this->createNotFoundException("用户已经是学员，不能添加！");
            }

            $order = $this->getOrderService()->createOrder(array(
                'userId' => $user['id'],
                'title' => "购买班级《{$classroom['title']}》(管理员添加)",
                'targetType' => 'classroom',
                'targetId' => $classroom['id'],
                'amount' => $data['price'],
                'payment' => 'none',
                'snPrefix' => 'CR',
            ));

            $this->getOrderService()->payOrder(array(
                'sn' => $order['sn'],
                'status' => 'success', 
                'amount' => $order['amount'], 
                'paidTime' => time(),
            ));

            $info = array(
                'orderId' => $order['id'],
                'note'  => $data['remark'],
            );

            $this->getClassroomService()->becomeStudent($order['targetId'], $order['userId'], $info);

            $member = $this->getClassroomService()->getClassroomMember($classroom['id'], $user['id']);
            
            $userUrl = $this->generateUrl('user_show', array('id'=>$currentUser['id']), true);
            $this->getNotificationService()->notify($member['userId'], 'default', "您被<a href='{$userUrl}' target='_blank'><strong>{$currentUser['nickname']}</strong></a>加入班级<strong>{$classroom['title']}</strong>成为正式学员");

            $this->getLogService()->info('classroom', 'add_student', "班级《{$classroom['title']}》(#{$classroom['id']})，添加学员{$user['nickname']}(#{$user['id']})，备注：{$data['remark']}");

            return $this->createStudentTrResponse($classroom, $member);
        }

        return $this->render('CustomWebBundle:CourseStudentManage:batchStudents.html.twig',array(
            'classroom'=>$classroom,
        ));
    }



    protected function getClassroomService()
    {
        return $this->getServiceKernel()->createService('Classroom:Classroom.ClassroomService');
    }

    protected function getClassroomReviewService()
    {
        return $this->getServiceKernel()->createService('Classroom:Classroom.ClassroomReviewService');
    }
    
    protected function getLevelService()
    {
        return $this->getServiceKernel()->createService('Vip:Vip.LevelService');
    }

    protected function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

    private function getNotificationService()
    {
        return $this->getServiceKernel()->createService('User.NotificationService');
    }

    private function getOrderService()
    {
        return $this->getServiceKernel()->createService('Order.OrderService');
    }

    protected function getUserFieldService()
    {
        return $this->getServiceKernel()->createService('User.UserFieldService');
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

    protected function getThreadService()
    {
        return $this->getServiceKernel()->createService('Thread.ThreadService');
    }
}
