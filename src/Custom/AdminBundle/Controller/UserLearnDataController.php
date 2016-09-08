<?php
namespace Custom\AdminBundle\Controller;

use Topxia\WebBundle\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\Paginator;
class UserLearnDataController extends BaseController
{
	public function indexAction(Request $request)
	{
		$query = $request->query->all();

		$courseId = "";
		$orgId = "";
		//选择的组织机构
		$organization['name'] = "";
		//课程的名称
		$course['title'] = "";
		//课程总时长
		$totalTime = "";
		//组织机构全路径
		$fullOrganizationName = "";
		//子部门个数
		$childOrgnaizationCount = "";
		//所以学员统计
		$allStudentsCount = "";
		//正在学习人数
		$learningStudentsCount="";
		//没有学习人数
		$neverLearndStudentsCount = "";
		//完成学习人数
		$finishLearnedStudentCount = "";
		$averageRate = "";
		$averageLearnedTime = "";
		$startTime = "";
		$endTime = "";
		$orgUserLearnDataSummary = array();

		//
		$mode = "index";

		if(!empty($query)){
			$courseId =  $query["course"] ;
			$orgId =  $query["organizationId"] ;
		}
		//$query =  $request->request->all();
		
		
		if(empty($courseId) || empty($orgId)){
			$list = array();
		}else{
			$mode = "search";
			$organization = $this->getOrganizationService()->getOrganizationById($orgId);
			$course = $this->getCourseService()->getCourse($courseId);


			
			$totalTime = $this->getCustomCourseService()->getTotalTimeByCourseId($courseId);
			$fullOrganizationName = $this->getOrganizationService()->getOrganizationFullPathByOrgId($orgId);
			//所有子组织机构
			$allChilds = $this->getOrganizationService()->findAllChildsByParentId($orgId,array());

			

			$childOrgnaizationCount = count($allChilds);
			
			//$allChildOrgIds="";
			//foreach ($allChilds as $key => $value) {
			//	$allChildOrgIds[] = $value['id'];
			//}
			//$allChildOrgIds[]=$orgId;
			$allStudentsCount = count($this->getOrganizationService()->findUserIdsByOrgId($orgId));
			if(isset($query['startTime'])){
				 $startTime = strtotime($query['startTime']);
				
			}
			if(isset($query['endTime'])){
				 $endTime = strtotime($query['endTime']);
				
			}
    	
			$list = $this->getCustomCourseService()->findUsersLearnDataByOrgIdAndCourseId($orgId,$courseId,$startTime,$endTime);
			$orgUserLearnDataSummary = $this->findOrgUserLearnDataSummary($allChilds,$courseId,$startTime,$endTime);

			//所有学习过的学生数量
			$allLearnedStudentCount = 0;
			//所有人的听课率合计
			$allLearnedLessonRateTotal = 0;
			//所有人听课的总时长
			$allLearnedTimeTotal = 0;
			foreach ($list as $key => $value) {
				if(empty($value['startTime'])){
					$neverLearndStudentsCount ++;
				}else{
					$allLearnedStudentCount ++;
					$allLearnedLessonRateTotal += $value['learnLessonRate'];
					$allLearnedTimeTotal += $value['watchTime'];
					
				}
			}
			$averageRate = $allLearnedStudentCount == 0 ? 0 : intval($allLearnedLessonRateTotal / $allLearnedStudentCount);
			$averageLearnedTime = $allLearnedStudentCount == 0 ? 0 : intval($allLearnedTimeTotal / $allLearnedStudentCount);

			$finishLearnedStudentCount = $this->getCustomCourseService()->findLearnedStudentsCountByCourseIdAndOrgId($courseId,$orgId,$startTime,$endTime);
		}
		

		return $this->render('CustomAdminBundle:UserLearnData:index.html.twig', array(
			"list" => $list,
			"courseId" =>$courseId,
			"orgId" => $orgId,
			"orgName" => $organization['name'],
			"courseTitle" => $course['title'],
			"totalTime" => $totalTime,
			"fullOrganizationName" => $fullOrganizationName,
			"childOrgnaizationCount" => $childOrgnaizationCount,
			"mode" => $mode,
			"allStudentsCount" => $allStudentsCount,
			"learningStudentsCount" => $learningStudentsCount,
			"neverLearndStudentsCount" => $neverLearndStudentsCount,
			"finishLearnedStudentCount" => $finishLearnedStudentCount,
			"averageRate" => round($averageRate,2)."%",
			"averageLearnedTime" => $averageLearnedTime,
			"startTime" => $startTime,
			"endTime" => $endTime,
			"course" => $course,
			"orgUserLearnDataSummary" => $orgUserLearnDataSummary
			

		));

	}

	//获取课程的详细信息，课程标题，课时总时长，课时数，每个课时时长
	private function getCourseDetailByCourseId($courseId){
		return $this->getCustomCourseService()->getCourseDetailByCourseId($courseId);

	}
	public function exportConfirmAction(Request $request){
		$query = $request->query->all();
		$courseId =  $query["course"] ;
		$orgId =  $query["organizationId"] ;
		$course = $this->getCourseService()->getCourse($courseId);
		$fullOrganizationName = $this->getOrganizationService()->getOrganizationFullPathByOrgId($orgId);
		return $this->render('CustomAdminBundle:UserLearnData:export-data.html.twig', 
			array(
				"course" => $course,
				"fullOrganizationName" => $fullOrganizationName
			));
	}
	public function exportAction(Request $request){
		//$query = $request->query->all();
		$includeChilds = "";
		$query = $request->request->all();
		$courseId =  $query["course"] ;
		$orgId =  $query["organizationId"] ;
		if(isset($query['includeChilds'])){
			$includeChilds = $query['includeChilds'];
		}
		
		$mode = $query['mode'];
		if(isset($query['startTime'])){
			$startTime = strtotime($query['startTime']);
				
		}
		if(isset($query['endTime'])){
			 $endTime = strtotime($query['endTime']);
			
		}

		$course = $this->getCourseService()->getCourse($courseId);
		$fullOrganizationName = $this->getOrganizationService()->getOrganizationFullPathByOrgId($orgId);
		
		$orgs[] = $orgId;
		$list = "";
		if(!empty($includeChilds)){
			$allChilds = $this->getOrganizationService()->findAllChildsByParentId($orgId,array());
			if(count($allChilds)>0){
				foreach ($allChilds as $key => $value) {
					$orgs[] = $value['id'];
				}
				
			}
		}
		if($mode == 'detail'){
			return $this->exportLearnedDetailData($course,$orgs,$courseId,$startTime,$endTime);
		}
		if($mode == 'summary'){
			return $this->exportLearnedDataSummary($orgs,$courseId,$startTime,$endTime,$fullOrganizationName);
		}
		
	}

	private function courseDetailDataExportStr($courseId){
		$courseDetail  = $this->getCourseDetailByCourseId($courseId);
		$lessons = $courseDetail['lessons'];
		$lessonsTotalLength = 0;
		$lessonsLine = array();

		foreach ($lessons as $key => $value) {
			$lessonsTotalLength = $lessonsTotalLength + $value['length'];
			$lessonLine = "";
			$lessonLine .=$value['title'].",";
			$lessonLine .=$value['length'] .",";
			$lessonsLine[] = $lessonLine;
		}
		$str = "课程标题:,".$courseDetail['title'].",课时数：,".count($lessons).",总时长（秒）：,".$lessonsTotalLength;
		$str.="\r\n";
		$str.="课时,时长(秒)";
		$str.="\r\n";
		$str .= implode("\r\n",$lessonsLine);
		$str.="\r\n";

		return $str;
	}

	private function exportLearnedDetailData($course,$orgs,$courseId,$startTime,$endTime){
	
		foreach ($orgs as $key => $value) {
			$tempList =  $this->getCustomCourseService()->findUsersLearnDataByOrgIdAndCourseId($value,$courseId,$startTime,$endTime);
			foreach ($tempList as $key => $value) {
				$list[] = $value;
			}
		}
		$courseDetail  = $this->getCourseDetailByCourseId($courseId);
		$lessons = $courseDetail['lessons'];
		$str = $this->courseDetailDataExportStr($courseId);
	
		$str .= "姓名,手机号,用户名,邮箱,学习总时长(秒),听课率,所属组织";
		 foreach ($lessons as $lesson) {
		 	$str.=','.$lesson['title'];
		}
		$str.="\r\n";

		$students = array();

		foreach ($list as $itme) {
			$oneLine = "";
			$oneLine .= $itme['truename'].",";
			$oneLine .= $itme['mobile'].",";
			$oneLine .= $itme['nickname'].",";
			$oneLine .= $itme['email'].",";
			$oneLine .= $itme['watchTime'].",";
			$oneLine .= $itme['learnLessonRate'].",";
			$oneLine .= $itme['organizationFullPath'];
			foreach ($lessons as $lesson) {
				$conditions = array();
				if(!empty($startTime)){
					$conditions['startTime']  = $startTime;
				}
				if(!empty($startTime)){
					$conditions['endTime']  = $endTime;
				}
				$conditions['lessonId'] = $lesson['id'];
				$conditions['courseId'] = $lesson['courseId'];
				$conditions['userId'] = $itme['id'];
				
				$learnedTime = $this->getCourseService()->searchWatchTime($conditions);
		
			 	$oneLine .= ",".intval($learnedTime);
			}
			$students[] = $oneLine; 

		};

		$str .= implode("\r\n",$students);
		$str = chr(239) . chr(187) . chr(191) . $str;
		
		$filename = "《".$course['title']."》的学习记录.csv";

		$userId = $this->getCurrentUser()->id;
	

		$response = new Response();
		// Content-type:text/html;charset=utf-8
		// $response->headers->set('Content-type', 'text/html','charset=utf-8');
		$response->headers->set('Content-type', 'text/csv');
		$response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
		$response->headers->set('Content-length', strlen($str));
		$response->setContent($str);

		return $response;
		
	}

	private function findOrgUserLearnDataSummary($allChilds,$courseId,$startTime,$endTime){

		$summaryData = array();
		$orgs = array();
		if(count($allChilds)>0){
			foreach ($allChilds as $key => $value) {
				$orgs[] = $value['id'];
			}
			
		}
		foreach ($orgs as $key => $value) {
			$fullOrganizationName = $this->getOrganizationService()->getOrganizationFullPathByOrgId($value);
			$allChilds = $this->getOrganizationService()->findAllChildsByParentId($value,array());
			$allStudentsCount = count($this->getOrganizationService()->findUserIdsByOrgId($value));
			$finishLearnedStudentCount = $this->getCustomCourseService()->findLearnedStudentsCountByCourseIdAndOrgId($courseId,$value,$startTime,$endTime);

			$tempList =  $this->getCustomCourseService()->findUsersLearnDataByOrgIdAndCourseId($value,$courseId,$startTime,$endTime);
			//所有学习过的学生数量
			$allLearnedStudentCount = 0;
			//所有人的听课率合计
			$allLearnedLessonRateTotal = 0;
			//所有人听课的总时长
			$allLearnedTimeTotal = 0;
			$neverLearndStudentsCount = 0;
			foreach ($tempList as $key => $value) {
				if(empty($value['startTime'])){
					$neverLearndStudentsCount ++;
				}else{
					$allLearnedStudentCount ++;
					$allLearnedLessonRateTotal += $value['learnLessonRate'];
					$allLearnedTimeTotal += $value['watchTime'];
					
				}
			}
			$averageRate = $allLearnedStudentCount == 0 ? 0 : intval($allLearnedLessonRateTotal / $allLearnedStudentCount);
			$averageLearnedTime = $allLearnedStudentCount == 0 ? 0 : intval($allLearnedTimeTotal / $allLearnedStudentCount);
			
	
			$summaryData[] = array(
				'fullOrganizationName' =>  $fullOrganizationName,
				'allChildCount' => count($allChilds),
				'allStudentsCount' => $allStudentsCount,
				'averageLearnedTime' => $averageLearnedTime,
				'averageRate' => $averageRate."%",
				'neverLearndStudentsCount' => $neverLearndStudentsCount,
				'finishLearnedStudentCount' => $finishLearnedStudentCount,
				'learningStudentCount' => $allStudentsCount - $neverLearndStudentsCount - $finishLearnedStudentCount
				);
			

		}
		return $summaryData;
	}

	private function exportLearnedDataSummary($orgs,$courseId,$startTime,$endTime,$exportOrganizationName){
		$summaryData = array();
		foreach ($orgs as $key => $value) {
			$fullOrganizationName = $this->getOrganizationService()->getOrganizationFullPathByOrgId($value);
			$allChilds = $this->getOrganizationService()->findAllChildsByParentId($value,array());
			$allStudentsCount = count($this->getOrganizationService()->findUserIdsByOrgId($value));
			$finishLearnedStudentCount = $this->getCustomCourseService()->findLearnedStudentsCountByCourseIdAndOrgId($courseId,$value,$startTime,$endTime);

			$tempList =  $this->getCustomCourseService()->findUsersLearnDataByOrgIdAndCourseId($value,$courseId,$startTime,$endTime);
			//所有学习过的学生数量
			$allLearnedStudentCount = 0;
			//所有人的听课率合计
			$allLearnedLessonRateTotal = 0;
			//所有人听课的总时长
			$allLearnedTimeTotal = 0;
			$neverLearndStudentsCount = 0;
			foreach ($tempList as $key => $value) {
				if(empty($value['startTime'])){
					$neverLearndStudentsCount ++;
				}else{
					$allLearnedStudentCount ++;
					$allLearnedLessonRateTotal += $value['learnLessonRate'];
					$allLearnedTimeTotal += $value['watchTime'];
					
				}
			}
			$averageRate = $allLearnedStudentCount == 0 ? 0 : intval($allLearnedLessonRateTotal / $allLearnedStudentCount);
			$averageLearnedTime = $allLearnedStudentCount == 0 ? 0 : intval($allLearnedTimeTotal / $allLearnedStudentCount);
			
	
			$summaryData[] = array(
				'fullOrganizationName' =>  $fullOrganizationName,
				'allChildCount' => count($allChilds),
				'allStudentCount' => $allStudentsCount,
				'averageLearnedTime' => $averageLearnedTime,
				'averageRate' => $averageRate."%",
				'neverLearndStudentsCount' => $neverLearndStudentsCount,
				'finishLearnedStudentCount' => $finishLearnedStudentCount,
				'learningStudentCount' => $allStudentsCount - $neverLearndStudentsCount - $finishLearnedStudentCount
				);
			

		}
		$str = $this->courseDetailDataExportStr($courseId);
		$str .= "组织名称,包含子部门个数,学员人数,平均听课时长（秒）,平均听课率,完全未听人数,正在听课人数,已完成听课";
		$str.="\r\n";

		$students = array();

		foreach ($summaryData as $itme) {
			$oneLine = "";
			$oneLine .= $itme['fullOrganizationName'].",";
			$oneLine .= $itme['allChildCount'].",";
			$oneLine .= $itme['allStudentCount'].",";
			$oneLine .= $itme['averageLearnedTime'].",";
			$oneLine .= $itme['averageRate'].",";
			$oneLine .= $itme['neverLearndStudentsCount'].",";
			$oneLine .= $itme['learningStudentCount'].",";
			$oneLine .= $itme['finishLearnedStudentCount'].",";
			$students[] = $oneLine;   
		};

		$str .= implode("\r\n",$students);
		$str = chr(239) . chr(187) . chr(191) . $str;
		
		$filename = $exportOrganizationName."的学习情况汇总.csv";

		$userId = $this->getCurrentUser()->id;

		$response = new Response();
		$response->headers->set('Content-type', 'text/csv');
		$response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
		$response->headers->set('Content-length', strlen($str));
		$response->setContent($str);

		return $response;
	}

	




    private function getOrganizationService()
    {
        return $this->getServiceKernel()->createService('Custom:Organization.OrganizationService');
    }
    private function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

    private function getCustomCourseService()
    {
        return $this->getServiceKernel()->createService('Custom:Course.CustomCourseService');
    }


}