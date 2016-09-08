<?php
namespace Topxia\MobileBundleV2\Processor\Impl;

use Topxia\Common\ArrayToolkit;
use Symfony\Component\HttpFoundation\Response;
use Topxia\MobileBundleV2\Processor\BaseProcessor;
use Topxia\MobileBundleV2\Processor\ClassRoomProcessor;
use Topxia\Service\Order\OrderRefundProcessor\OrderRefundProcessorFactory;

class ClassRoomProcessorImpl extends BaseProcessor implements ClassRoomProcessor
{
	public function after()
	{
		if (!class_exists('Classroom\Service\Classroom\Impl\ClassroomServiceImpl')) {
			$this->stopInvoke();
			return $this->createErrorResponse("no_classroom", "没有安装班级插件！");
		}
	}

    public function search()
    {
        $conditions = array(
            'status' => 'published',
            'private' => 0
        );

        $start  = (int) $this->getParam("start", 0);
        $limit  = (int) $this->getParam("limit", 10);

        $conditions['title'] = $this->getParam("title");
        $total = $this->getClassroomService()->searchClassroomsCount($conditions);
        $classrooms = $this->getClassroomService()->searchClassrooms(
            $conditions,
            array('recommendedSeq', 'desc'),
            $start,
            $limit
        );

        return array(
            "start" => $start,
            "limit" => $limit,
            "total" => $total,
            "data" => $this->filterClassRooms($classrooms)
        );
    }

    public function sign()
    {
        $classRoomId = $this->getParam("classRoomId", 0);
        $user  = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录，不能签到！");
        }

        $userSignStatistics = array();
        $member = $this->getClassroomService()->getClassroomMember($classRoomId, $user['id']);

        try {
            if ($this->getClassroomService()->canTakeClassroom($classRoomId) || (isset($member) && $member['role'] == "auditor")) {
                $this->getSignService()->userSign($user['id'], 'classroom_sign', $classRoomId);

                $userSignStatistics = $this->getSignService()->getSignUserStatistics($user['id'], 'classroom_sign', $classRoomId);
            }
        } catch(\Exception $e) {
            return $this->createErrorResponse('error', $e->getMessage());
        }
        return array(
            'isSignedToday' => true,
            'userSignStatistics' => $userSignStatistics
        );
    }

    public function getTodaySignInfo()
    {
        $classRoomId = $this->getParam("classRoomId", 0);
        $user  = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录，不能查看班级！");
        }
        $classroom = $this->getClassroomService()->getClassroom($classRoomId);

        $isSignedToday = $this->getSignService()->isSignedToday($user['id'], 'classroom_sign', $classroom['id']);

        $week = array('日','一','二','三','四','五','六');

        $userSignStatistics = $this->getSignService()->getSignUserStatistics($user['id'], 'classroom_sign', $classroom['id']);

        $day = date('d', time());

        $signDay = $this->getSignService()->getSignRecordsByPeriod($user['id'], 'classroom_sign', $classroom['id'], date('Y-m', time()), date('Y-m-d', time()+3600));
        $notSign = $day-count($signDay);

        if (!empty($userSignStatistics)) {
            $userSignStatistics['createdTime'] = date('c', $userSignStatistics['createdTime']);
        }
        return array(
            'isSignedToday' => $isSignedToday,
            'userSignStatistics' => $userSignStatistics,
            'notSign' => $notSign,
            'week' => $week[date('w', time())]
        );
    }

    public function getAnnouncements()
    {
        $start    = (int) $this->getParam("start", 0);
        $limit    = (int) $this->getParam("limit", 10);
        $classRoomId = $this->getParam("classRoomId", 0);
        if (empty($classRoomId)) {
            return array();
        }

        $conditions = array(
            'targetType' => "classroom",
            'targetId' => $classRoomId
        );

        $announcements = $this->getAnnouncementService()->searchAnnouncements($conditions, array('createdTime','DESC'), $start, $limit);
        $announcements = array_values($announcements);
        return $this->filterAnnouncements($announcements);
    }

	public function getRecommendClassRooms()
	{
		$conditions = array(
            'status' => 'published',
            'private' => 0
        );

        $start  = (int) $this->getParam("start", 0);
        $limit  = (int) $this->getParam("limit", 10);

        $total = $this->getClassroomService()->searchClassroomsCount($conditions);
        $classrooms = $this->getClassroomService()->searchClassrooms(
            $conditions,
            array('recommendedSeq', 'desc'),
            $start,
            $limit
        );

        $allClassrooms = array();
        for ($i=0; $i < count($classrooms); $i++) { 
        	if ($classrooms[$i]["recommendedTime"] > 0) {
        		$allClassrooms[] = $classrooms[$i];
        	}
        }

        return array(
	            "start" => $start,
	            "limit" => $limit,
	            "total" => $total,
	            "data" => $this->filterClassRooms($allClassrooms)
	    ); 
	}

	public function getLatestClassrooms()
	{
		$conditions = array(
            'status' => 'published',
            'private' => 0,
        );

        $start  = (int) $this->getParam("start", 0);
        $limit  = (int) $this->getParam("limit", 10);

        $total = $this->getClassroomService()->searchClassroomsCount($conditions);
        $classrooms = $this->getClassroomService()->searchClassrooms(
            $conditions,
            array('createdTime', 'desc'),
            $start,
            $limit
        );
        $allClassrooms = array_values($classrooms);
        return array(
	            "start" => $start,
	            "limit" => $limit,
	            "total" => $total,
	            "data" => $this->filterClassRooms($allClassrooms)
	    ); 
	}

	public function exitClassRoom($classRoomId, $user)
	{
    		$member = $this->getClassroomService()->getClassroomMember($classRoomId, $user["id"]);

    		if (empty($member)) {
        		throw $this->createErrorResponse('error', '您不是班级的学员。');
    		}

    		if (!in_array($member["role"], array("auditor", "student"))) {
    			return $this->createErrorResponse('error', "您不是班级的学员。");
    		}

    		if (!empty($member['orderId'])) {
        		return $this->createErrorResponse('error', "有关联的订单，不能直接退出学习。");
    		}

    		try {
    			$this->getClassroomService()->exitClassroom($classRoomId, $user["id"]);
    		} catch (\Exception $e) {
    			return $this->createErrorResponse('error', $e->getMessage());
    		}
    		
    		return true;
	}

	public function unLearn()
	{	
		$classRoomId = $this->getParam("classRoomId");
		$targetType = $this->getParam("targetType");

        if(!in_array($targetType, array("course", "classroom"))) {
            throw $this->createErrorResponse('error', '退出学习失败');
        }
        $processor = OrderRefundProcessorFactory::create($targetType);

        $target = $processor->getTarget($classRoomId);
        $user = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
        	return $this->createErrorResponse('not_login', "您尚未登录，不能学习班级！");
    	}

        $member = $processor->getTargetMember($classRoomId, $user["id"]);
        if (empty($member) || empty($member['orderId'])) {
            return $this->exitClassRoom($classRoomId, $user);
        }

        $order = $this->getOrderService()->getOrder($member['orderId']);
        if (empty($order)) {
            return $this->createErrorResponse('error', '您尚未购买，不能退学。');
        }

        $data = $this->request->request->all();
        	$reason = empty($data['reason']) ? array() : $data['reason'];
        	$amount = empty($data['applyRefund']) ? 0 : null;

        	try {
        		if(isset($data["applyRefund"]) && $data["applyRefund"] ){
            			$refund = $processor->applyRefundOrder($member['orderId'], $amount, $reason, $this->container);
            	} else {
                		$processor->removeStudent($order['targetId'], $user['id']);
            	}
        	} catch(\Exception $e) {
        		return $this->createErrorResponse('error', $e->getMessage());
        	}

        	return true;
	}

	public function getTeachers()
	{
		$classRoomId = $this->getParam("classRoomId", 0);
        $classroom = $this->getClassroomService()->getClassroom($classRoomId);
        if (empty($classroom)) {
			return $this->createErrorResponse('error', "班级不存在!");
		}
        $headTeacher = $this->getClassroomService()->findClassroomMembersByRole($classRoomId, 'headTeacher', 0, 1);
        $assistants = $this->getClassroomService()->findClassroomMembersByRole($classRoomId, 'assistant', 0, PHP_INT_MAX);
        $studentAssistants = $this->getClassroomService()->findClassroomMembersByRole($classRoomId, 'studentAssistant', 0, PHP_INT_MAX);
        $members = $this->getClassroomService()->findClassroomMembersByRole($classRoomId, 'teacher', 0, PHP_INT_MAX);
        $members = array_merge($headTeacher, $members, $assistants,$studentAssistants);
        $members = ArrayToolkit::index($members, 'userId');
        $teacherIds = ArrayToolkit::column($members, 'userId');
        $teachers = $this->getUserService()->findUsersByIds($teacherIds);

        $sortTeachers = array();
        foreach ($members as $key => $member) {
        	$teacher = $teachers[$member['userId']];
        	$teacher["memberRole"] = $member["role"];
            $sortTeachers[] = $teacher;
        }

        return $this->controller->filterUsers($sortTeachers);
	}

	public function getStudents()
	{
		$classRoomId = $this->getParam("classRoomId", 0);
		$start   = (int) $this->getParam("start", 0);
        $limit   = (int) $this->getParam("limit", 10);
		$classroom = $this->getClassroomService()->getClassroom($classRoomId);
		if (empty($classroom)) {
			return $this->createErrorResponse('error', "班级不存在!");
		}

		$total = (int) $classroom["studentNum"];

		if ($limit == -1) {
			$limit = $total;
		}
		$students = $this->getClassroomService()->findClassroomStudents($classRoomId, 0, $limit);
        $users = $this->getUserService()->findUsersByIds(ArrayToolkit::column($students, 'userId'));

        $users = $this->controller->filterUsers($users);
        return array(
            "start" => $start,
            "limit" => $limit,
            "total" => $classroom["studentNum"],
            "data" => array_values($users)
        );
	}

	public function getReviews()
    {
        $classRoomId = $this->getParam("classRoomId", 0);
        
        $start   = (int) $this->getParam("start", 0);
        $limit   = (int) $this->getParam("limit", 10);

        $conditions = array('classroomId'=>$classRoomId);
        $total   = $this->getClassroomReviewService()->searchReviewCount($conditions);
        $reviews = $this->getClassroomReviewService()->searchReviews(
        	$conditions, 
        	array('createdTime', 'DESC' ),
        	$start, 
        	$limit
        );

        $reviews = $this->controller->filterReviews($reviews);
        return array(
            "start" => $start,
            "limit" => $limit,
            "total" => $total,
            "data" => $reviews
        );
    }

    public function getReviewInfo()
    {
        $classRoomId = $this->getParam("classRoomId", 0);
        $classroom = $this->getClassroomService()->getClassroom($classRoomId);

        $conditions = array('classroomId'=>$classRoomId);
        $total = $this->getClassroomReviewService()->searchReviewCount($conditions);
        $reviews = $this->getClassroomReviewService()->searchReviews(
        	$conditions, 
        	array('createdTime', 'DESC' ),
        	0, 
        	$total
        );

        $progress = array(0, 0, 0, 0, 0);
        foreach ($reviews as $key => $review) {
            $rating = $review["rating"] < 1 ? 1 : $review["rating"];
            $progress[$review["rating"] - 1] ++;
        }
        return array(
            "info" => array(
                "ratingNum" => $classroom["ratingNum"],
                "rating" => $classroom["rating"],
            ),
            "progress" => $progress
        );
    }

	public function learnByVip()
	{
		$classRoomId = $this->getParam("classRoomId");
		if (!$this->controller->setting('vip.enabled')) {
        	return $this->createErrorResponse('not_login', "网校未开启会员体系");
    	}

    	$user = $this->controller->getUserByToken($this->request);
    	if (!$user->isLogin()) {
    		return $this->createErrorResponse('not_login', "您尚未登录，不能学习班级！");
		}
		try {
			$this->getClassroomService()->becomeStudent($classRoomId, $user['id'], array('becomeUseMember' => true));
		} catch (\Exception $e) {
			return $this->createErrorResponse('error', $e->getMessage());
		}
    	
    	return true;
	}

	public function getClassRoomMember()
	{
		$classRoomId = $this->getParam("classRoomId");
	        	$user  = $this->controller->getUserByToken($this->request);
	        	if (!$user->isLogin()) {
            		return $this->createErrorResponse('not_login', "您尚未登录，不能查看班级！");
        		}
	        	if (empty($classRoomId)) {
	        	    return null;
	        	}
	        	$member = $user ? $this->getClassroomService()->getClassroomMember($classRoomId, $user["id"]) : null;
	        	if ($member && $member['locked']) {
	         	   return null;
	       	}

	        	return empty($member) ? new Response("null") : $member;
	}

	public function getClassRoom()
	{
		$id = $this->getParam("id");
		$classroom = $this->getClassroomService()->getClassroom($id);

        $user = $this->controller->getUserByToken($this->request);
        $userId = empty($user) ? 0 : $user["id"];
        $member = $user ? $this->getClassroomService()->getClassroomMember($classroom['id'], $userId) : null;
		$vipLevels = array();
    	if ($this->controller->setting('vip.enabled')) {
        	$vipLevels = $this->controller->getLevelService()->searchLevels(array(
            		'enabled' => 1
        	), 0, 100);
    	}

    	$checkMemberLevelResult = null;
    	if ($this->controller->setting('vip.enabled')) {
        	$classroomMemberLevel = $classroom['vipLevelId'] > 0 ? $this->controller->getLevelService()->getLevel($classroom['vipLevelId']) : null;
    	}

    	$teacherIds = $classroom["teacherIds"];
    	$users = $this->controller->getUserService()->findUsersByIds(empty($teacherIds) ? array() : $teacherIds);
    	$classroom["teachers"] = array_values($this->filterUsersFiled($users));
		return array(
			"classRoom" => $this->filterClassRoom($classroom, false),
			"member" => $member,
			"vip" => $checkMemberLevelResult,
			"vipLevels" => $vipLevels
			);
	}

	private function filterClassRoom($classroom, $isList = true)
	{
		if (empty($classroom)) {
        	return null;
    	}

    	$classrooms = $this->filterClassRooms(array($classroom), $isList);

    	return current($classrooms);
	}

	public function filterClassRooms($classrooms, $isList = true)
	{
		if (empty($classrooms)) {
			return array();
		}

        $coinSetting = $this->controller->getCoinSetting();
		$self = $this->controller;
		$container = $this->getContainer();

		return array_map(function($classroom) use ($self, $container, $isList, $coinSetting) {

			$classroom['smallPicture'] = $container->get('topxia.twig.web_extension')->getFilePath($classroom['smallPicture'], 'course-large.png', true);
            $classroom['middlePicture'] = $container->get('topxia.twig.web_extension')->getFilePath($classroom['middlePicture'], 'course-large.png', true);
            $classroom['largePicture'] = $container->get('topxia.twig.web_extension')->getFilePath($classroom['largePicture'], 'course-large.png', true);
			
			$classroom['recommendedTime'] = date("c", $classroom['recommendedTime']);
			$classroom['createdTime'] = date("c", $classroom['createdTime']);
			if ($isList) {
				$classroom["about"] = mb_substr($classroom["about"], 0, 20, "utf-8");
			}
			$classroom['about'] = $self->convertAbsoluteUrl($container->get('request'), $classroom['about']);

            $service = $classroom['service'];            
            if (!empty($service)) {
                $searchIndex = array_search('studyPlan', $service);
                if ($searchIndex !== false) {
                    array_splice($service, $searchIndex, 1);
                    $classroom['service'] = $service;
                }
            }

            if (!empty($coinSetting)) {
                $classroom["priceType"] = $coinSetting["priceType"];
                $classroom["coinName"] = $coinSetting["name"];
                $classroom["coinPrice"] = (string)((float)$classroom["price"] * (float)$coinSetting["cashRate"]);
            }
            
			return $classroom;
		}, $classrooms);
	}

    public function getClassRoomCourses()
    {
        $classroomId = $this->getParam("classRoomId");
        $user = $this->controller->getUserByToken($this->request);
        $classroom = $this->getClassroomService()->getClassroom($classroomId);
        if (empty($classroom)) {
            return $this->createErrorResponse('error', "没有找到该班级");
        }

        $courses = $this->getClassroomService()->findActiveCoursesByClassroomId($classroomId);
        
        return $this->controller->filterCourses($courses);
    }

	public function getClassRoomCoursesAndProgress()
	{
		$classroomId = $this->getParam("classRoomId");
		$user = $this->controller->getUserByToken($this->request);
		$classroom = $this->getClassroomService()->getClassroom($classroomId);
		if (empty($classroom)) {
    		return $this->createErrorResponse('error', "没有找到该班级");
		}

		$courses = $this->getClassroomService()->findActiveCoursesByClassroomId($classroomId);
		$progressArray = array();
        $user = $this->controller->getUserByToken($this->request);

		foreach ($courses as $key => $course) {
       	    $courseMember = $this->getCourseService()->getCourseMember($course['id'], $user["id"]);

            $lessonNum = (float)$course['lessonNum'];
            $progress = $lessonNum == 0 ? 0 : (float)$courseMember['learnedNum'] / $lessonNum;
            
            $lastLesson = null;
            if ($user) {
                $userLearnStatus = $this->getCourseService()->getUserLearnLessonStatuses($user['id'], $course['id']);
                $lessonIds = array_keys($userLearnStatus ? $userLearnStatus : array());
                $lastLesson = $this->getCourseService()->getLesson(end($lessonIds));
            }
            $progressArray[$course['id']] = array(
                "lastLesson" => $this->filterLastLearnLesson($lastLesson),
                "progress" => (int)($progress * 100) . "%",
                "progressValue" => $progress
            );
        }

        return array(
            'courses' => $this->controller->filterCourses($courses),
            'progress' => $progressArray
        );
	}

    private function filterLastLearnLesson($lastLesson)
    {
        if (empty($lastLesson)) {
            return $lastLesson;
        }
        foreach ($lastLesson as $key => $value) {
            if (!in_array($key, array('id', 'title', 'courseId', 'itemType'))) {
                unset($lastLesson[$key]);
            }
        }

        return $lastLesson;
    }

	public function myClassRooms()
	{	
		$start  = (int) $this->getParam("start", 0);
        $limit  = (int) $this->getParam("limit", 10);

		$user = $this->controller->getUserByToken($this->request);
       		if (!$user->isLogin()) {
            		return $this->createErrorResponse('not_login', "您尚未登录，不能查看班级！");
        		}
	        $progresses = array();
	        $classrooms=array();

	        $studentClassrooms=$this->getClassroomService()->searchMembers(array('role'=>'student','userId'=>$user->id),array('createdTime','desc'),0,9999);
	        $auditorClassrooms=$this->getClassroomService()->searchMembers(array('role'=>'auditor','userId'=>$user->id),array('createdTime','desc'),0,9999);

	        $total  = 0;
	        $total += $this->getClassroomService()->searchMemberCount(array('role'=>'student','userId'=>$user->id),array('createdTime','desc'),0,9999);
	        $total += $this->getClassroomService()->searchMemberCount(array('role'=>'auditor','userId'=>$user->id),array('createdTime','desc'),0,9999);
	        
	        $classrooms=array_merge($studentClassrooms,$auditorClassrooms);

	        $classroomIds=ArrayToolkit::column($classrooms,'classroomId');

	        $classrooms=$this->getClassroomService()->findClassroomsByIds($classroomIds);

	        foreach ($classrooms as $key => $classroom) {
	            
	            $courses=$this->getClassroomService()->findCoursesByClassroomId($classroom['id']);
	            $coursesCount=count($courses);

	            $classrooms[$key]['coursesCount']=$coursesCount;
	            
	            $classroomId= array($classroom['id']);
	            $member=$this->getClassroomService()->findMembersByUserIdAndClassroomIds($user->id, $classroomId);
	            $time=time()-$member[$classroom['id']]['createdTime'];
	            $day=intval($time/(3600*24));

	            $classrooms[$key]['day']=$day;
	            $progresses[$classroom['id']] = $this->calculateUserLearnProgress($classroom, $user->id);
	        }

	        $classrooms = $this->filterMyClassRoom($classrooms,$progresses);
	        return array(
	        	"start"=>$start,
	        	"total"=>$total,
	        	"limit"=>$total,
	        	"data"=>array_values($classrooms)
	        	);
	}

	private function filterMyClassRoom($classrooms, $progresses)
	{
        $classrooms = $this->filterClassRooms($classrooms);
		return array_map(function($classroom) use($progresses) {
			$progresse = $progresses[$classroom["id"]];
			$classroom["percent"] = $progresse["percent"];
		    $classroom["number"] = $progresse["number"];
		    $classroom["total"] = $progresse["total"];

			unset($classroom["description"]);
			unset($classroom["about"]);
			unset($classroom["teacherIds"]);
			unset($classroom["service"]);
			return $classroom;
		}, $classrooms);
	}

	private function calculateUserLearnProgress($classroom, $userId)
	    {
	        $courses=$this->getClassroomService()->findCoursesByClassroomId($classroom['id']);
	        $courseIds = ArrayToolkit::column($courses,'id');
	        $findLearnedCourses = array();
	        foreach ($courseIds as $key => $value) {
	            $LearnedCourses=$this->getCourseService()->findLearnedCoursesByCourseIdAndUserId($value,$userId);
	            if (!empty($LearnedCourses)) {
	                $findLearnedCourses[] = $LearnedCourses;
	            }
	        }

	        $learnedCoursesCount = count($findLearnedCourses);
	        $coursesCount=count($courses);

	        if ($coursesCount == 0) {
	            return array('percent' => '0%', 'number' => 0, 'total' => 0);
	        }

	        $percent = intval($learnedCoursesCount / $coursesCount * 100) . '%';

	        return array (
	            'percent' => $percent,
	            'number' => $learnedCoursesCount,
	            'total' => $coursesCount
	        );
	    }

	public function getClassRooms()
	{
		$start = (int) $this->getParam("start", 0);
        $limit = (int) $this->getParam("limit", 10);
        $category = $this->getParam("category", 0);

        $title = $this->getParam("title", "");
        $sort = $this->getParam("sort", "createdTime");
        $conditions = array(
            'status' => 'published',
            'title' => $title,
        );

        if (!empty($category)) {
            $categoryArray = $this->getCategoryService()->getCategory($category);
            $childrenIds = $this->getCategoryService()->findCategoryChildrenIds($categoryArray['id']);
            $categoryIds = array_merge($childrenIds, array($categoryArray['id']));
            $conditions['categoryIds'] = $categoryIds;
        }

        $total = $this->getClassroomService()->searchClassroomsCount($conditions);

        $classrooms = $this->getClassroomService()->searchClassrooms(
                $conditions,
                array($sort,'desc'),
                $start,
                $limit
        );

        return array(
            "start" => $start,
            "limit" => $limit,
            "total" => $total,
            "data" => $this->filterClassRooms($classrooms)
        );
	}

    private function getSignService()
    {
        return $this->controller->getService('Sign.SignService');
    }

	private function getCategoryService()
	{
    	return $this->controller->getService('Taxonomy.CategoryService');
	}

    private function getClassroomService() 
    {
    	return $this->controller->getService('Classroom:Classroom.ClassroomService');
    }

    protected function getClassroomOrderService()
    {
        return $this->controller->getService('Classroom:Classroom.ClassroomOrderService'); 
    }

    protected function getClassroomReviewService()
    {
        return $this->controller->getService('Classroom:Classroom.ClassroomReviewService');
    }
}