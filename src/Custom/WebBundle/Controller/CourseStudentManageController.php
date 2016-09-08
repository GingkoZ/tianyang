<?php
namespace Custom\WebBundle\Controller;

use Topxia\WebBundle\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\Paginator;

class CourseStudentManageController extends BaseController
{
	public function batchStudentsAction(Request $request, $id)
	{
		$courseSetting = $this->getSettingService()->get('course', array());
		
		if (!empty($courseSetting['teacher_manage_student'])) {
			$course = $this->getCourseService()->tryManageCourse($id);
		} else {
			$course = $this->getCourseService()->tryAdminCourse($id);
		}

		if ('POST' == $request->getMethod()) {
			$data = $request->request->all();
			$organizationId = $data['organizationId'];
			$isChildNode = $data['isChildNode'];
			$userIds = array();
			if($isChildNode == 'active'){
			$orgs = array();
			$orgs[] = $this->getOrganizationService()->getOrganizationById($organizationId);
			$orgs = $this->findAllChildsByParentId($organizationId,$orgs);
			$orgIds = array();
			foreach ($orgs as $key => $value) {
				$orgIds[] = $value['id'];
			}
				$userIds = $this->getOrganizationService()->findUserIdsByOrgIds($orgIds);
			}else{
				$userIds = $this->getOrganizationService()->findUserIdsByOrgId($organizationId);
			}
			
			foreach ($userIds as $key => $value) {
				if ($this->getCourseService()->isCourseStudent($course['id'], $value['id']) || $this->getCourseService()->isCourseTeacher($course['id'], $value['id'])) {

				}else{
					$this->getCourseService()->becomeStudent($course['id'],$value['id']);
					$this->getNotificationService()->notify($value['id'], 'student-create', array(
					'courseId' => $course['id'], 
					'courseTitle' => $course['title']
					));
				}
			}
			 return $this->createJsonResponse("true");
			// return $this->redirect($this->generateUrl('course_manage_students', array(
   //                 'id' => $course['id'], 
   //          )));

			// return $this->createStudentTrResponse($course, $member);
		}

		return $this->render('CustomWebBundle:CourseStudentManage:batchStudents.html.twig', array(
			'course'=>$course
		));
	}

	private function findAllChildsByParentId($parentId,$orgs){
		$organizations = $this->getOrganizationService()->findNodesDataByParentId($parentId);
		foreach ($organizations as $key => $value) {
			$orgs[] = $value;
			$gradeChildren = $this->getOrganizationService()->findNodesDataByParentId($value['id']);
			if(count($gradeChildren)){
				$orgs = $this->findAllChildsByParentId($value['id'],$orgs);
			}
		}
		return $orgs;
		
	}


// 	function get_array($id=0){
// 	$sql = "select id,title from class where pid= $id";
// 	$result = mysql_query($sql);//查询子类
// 	$arr = array();
// 	if($result && mysql_affected_rows()){//如果有子类
// 		while($rows=mysql_fetch_assoc($result)){ //循环记录集
// 			$rows['list'] = get_array($rows['id']); //调用函数，传入参数，继续查询下级
// 			$arr[] = $rows; //组合数组
// 		}
// 		return $arr;
// 	}
// }
	


	public function indexAction(Request $request, $id)
	{

		$course = $this->getCourseService()->tryManageCourse($id);

		$fields = $request->query->all();
		$nickname="";
		if(isset($fields['nickName'])){
            $nickname =$fields['nickName'];
        } 

		$paginator = new Paginator(
			$request,
			$this->getCourseService()->searchMemberCount(array('courseId'=>$course['id'],'role'=>'student','nickname'=>$nickname)),
			20
		);

		$students = $this->getCourseService()->searchMembers(
			array('courseId'=>$course['id'],'role'=>'student','nickname'=>$nickname),
			array('createdTime','DESC'),
			$paginator->getOffsetCount(),
			$paginator->getPerPageCount()
		);
		$studentUserIds = ArrayToolkit::column($students, 'userId');
		$users = $this->getUserService()->findUsersByIds($studentUserIds);
		$followingIds = $this->getUserService()->filterFollowingIds($this->getCurrentUser()->id, $studentUserIds);

		$progresses = array();
		foreach ($students as $student) {
			$progresses[$student['userId']] = $this->calculateUserLearnProgress($course, $student);
		}

		$courseSetting = $this->getSettingService()->get('course', array());
		$isTeacherAuthManageStudent = !empty($courseSetting['teacher_manage_student']) ? 1: 0;

		return $this->render('CustomWebBundle:CourseStudentManage:index.html.twig', array(
			'course' => $course,
			'students' => $students,
			'users'=>$users,
			'progresses' => $progresses,
			'followingIds' => $followingIds,
			'isTeacherAuthManageStudent' => $isTeacherAuthManageStudent,
			'paginator' => $paginator,
			'canManage' => $this->getCourseService()->canManageCourse($course['id']),
		));

	}


	private function calculateUserLearnProgress($course, $member)
	{
		if ($course['lessonNum'] == 0) {
			return array('percent' => '0%', 'number' => 0, 'total' => 0);
		}

		$percent = intval($member['learnedNum'] / $course['lessonNum'] * 100) . '%';

		return array (
			'percent' => $percent,
			'number' => $member['learnedNum'],
			'total' => $course['lessonNum']
		);
	}

    private function getOrganizationService()
    {
        return $this->getServiceKernel()->createService('Custom:Organization.OrganizationService');
    }
	private function getSettingService()
	{
		return $this->getServiceKernel()->createService('System.SettingService');
	}

	private function getCourseService()
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
}