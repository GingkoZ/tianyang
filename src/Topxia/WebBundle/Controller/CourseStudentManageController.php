<?php
namespace Topxia\WebBundle\Controller;

use Topxia\Common\Paginator;
use Topxia\Common\ArrayToolkit;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CourseStudentManageController extends BaseController
{
    public function indexAction(Request $request, $id)
    {
        $course = $this->getCourseService()->tryManageCourse($id);

        $fields    = $request->query->all();
        $condition = array();

        if (isset($fields['nickname'])) {
            $condition['nickname'] = $fields['nickname'];
        }

        $condition = array_merge($condition, array('courseId' => $course['id'], 'role' => 'student'));

        $paginator = new Paginator(
            $request,
            $this->getCourseService()->searchMemberCount($condition),
            20
        );

        $students = $this->getCourseService()->searchMembers(
            $condition,
            array('createdTime', 'DESC'),
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $studentUserIds = ArrayToolkit::column($students, 'userId');
        $users          = $this->getUserService()->findUsersByIds($studentUserIds);
        $followingIds   = $this->getUserService()->filterFollowingIds($this->getCurrentUser()->id, $studentUserIds);

        $progresses = array();

        foreach ($students as $student) {
            $progresses[$student['userId']] = $this->calculateUserLearnProgress($course, $student);
        }

        $courseSetting              = $this->getSettingService()->get('course', array());
        $isTeacherAuthManageStudent = !empty($courseSetting['teacher_manage_student']) ? 1 : 0;
        $default                    = $this->getSettingService()->get('default', array());
        return $this->render('TopxiaWebBundle:CourseStudentManage:index.html.twig', array(
            'course'                     => $course,
            'students'                   => $students,
            'users'                      => $users,
            'progresses'                 => $progresses,
            'followingIds'               => $followingIds,
            'isTeacherAuthManageStudent' => $isTeacherAuthManageStudent,
            'paginator'                  => $paginator,
            'canManage'                  => $this->getCourseService()->canManageCourse($course['id']),
            'default'                    => $default
        ));
    }

    public function createAction(Request $request, $id)
    {
        $courseSetting = $this->getSettingService()->get('course', array());

        if (!empty($courseSetting['teacher_manage_student'])) {
            $course = $this->getCourseService()->tryManageCourse($id);
        } else {
            $course = $this->getCourseService()->tryAdminCourse($id);
        }

        $currentUser = $this->getCurrentUser();

        if ('POST' == $request->getMethod()) {
            $data = $request->request->all();
            $user = $this->getUserService()->getUserByLoginField($data['queryfield']);

            $data["isAdminAdded"] = 1;

            list($course, $member, $order) = $this->getCourseMemberService()->becomeStudentAndCreateOrder($user["id"], $course["id"], $data);
            return $this->createStudentTrResponse($course, $member);
        }

        $default = $this->getSettingService()->get('default', array());
        return $this->render('TopxiaWebBundle:CourseStudentManage:create-modal.html.twig', array(
            'course'  => $course,
            'default' => $default
        ));
    }

    public function removeAction(Request $request, $courseId, $userId)
    {
        $courseSetting = $this->getSettingService()->get('course', array());

        if (!empty($courseSetting['teacher_manage_student'])) {
            $course = $this->getCourseService()->tryManageCourse($courseId);
        } else {
            $course = $this->getCourseService()->tryAdminCourse($courseId);
        }

        $this->getCourseService()->removeStudent($courseId, $userId);

        $this->getNotificationService()->notify($userId, 'student-remove', array(
            'courseId'    => $course['id'],
            'courseTitle' => $course['title']
        ));

        return $this->createJsonResponse(true);
    }

    public function exportCsvAction(Request $request, $id)
    {
        $gender        = array('female' => '女', 'male' => '男', 'secret' => '秘密');
        $courseSetting = $this->getSettingService()->get('course', array());

        if (isset($courseSetting['teacher_export_student']) && $courseSetting['teacher_export_student'] == "1") {
            $course = $this->getCourseService()->tryManageCourse($id);
        } else {
            $course = $this->getCourseService()->tryAdminCourse($id);
        }

        $userinfoFields = array();

        if (isset($courseSetting['userinfoFields'])) {
            $userinfoFields = array_diff($courseSetting['userinfoFields'], array('truename', 'job', 'mobile', 'qq', 'company', 'gender', 'idcard', 'weixin'));
        }

        $courseMembers = $this->getCourseService()->searchMembers(array('courseId' => $course['id'], 'role' => 'student'), array('createdTime', 'DESC'), 0, 20000);

        $userFields = $this->getUserFieldService()->getAllFieldsOrderBySeqAndEnabled();

        $fields['weibo'] = "微博";

        foreach ($userFields as $userField) {
            $fields[$userField['fieldName']] = $userField['title'];
        }

        $userinfoFields = array_flip($userinfoFields);

        $fields = array_intersect_key($fields, $userinfoFields);

        if (!$courseSetting['buy_fill_userinfo']) {
            $fields = array();
        }

        $studentUserIds = ArrayToolkit::column($courseMembers, 'userId');

        $users = $this->getUserService()->findUsersByIds($studentUserIds);
        $users = ArrayToolkit::index($users, 'id');

        $profiles = $this->getUserService()->findUserProfilesByIds($studentUserIds);
        $profiles = ArrayToolkit::index($profiles, 'id');

        $progresses = array();

        foreach ($courseMembers as $student) {
            $progresses[$student['userId']] = $this->calculateUserLearnProgress($course, $student);
        }

        $str = "用户名,Email,加入学习时间,学习进度,姓名,性别,QQ号,微信号,手机号,公司,职业,头衔";

        foreach ($fields as $key => $value) {
            $str .= ",".$value;
        }

        $str .= "\r\n";

        $students = array();

        foreach ($courseMembers as $courseMember) {
            $member = "";
            $member .= $users[$courseMember['userId']]['nickname'].",";
            $member .= $users[$courseMember['userId']]['email'].",";
            $member .= date('Y-n-d H:i:s', $courseMember['createdTime']).",";
            $member .= $progresses[$courseMember['userId']]['percent'].",";
            $member .= $profiles[$courseMember['userId']]['truename'] ? $profiles[$courseMember['userId']]['truename']."," : "-".",";
            $member .= $gender[$profiles[$courseMember['userId']]['gender']].",";
            $member .= $profiles[$courseMember['userId']]['qq'] ? $profiles[$courseMember['userId']]['qq']."," : "-".",";
            $member .= $profiles[$courseMember['userId']]['weixin'] ? $profiles[$courseMember['userId']]['weixin']."," : "-".",";
            $member .= $profiles[$courseMember['userId']]['mobile'] ? $profiles[$courseMember['userId']]['mobile']."," : "-".",";
            $member .= $profiles[$courseMember['userId']]['company'] ? $profiles[$courseMember['userId']]['company']."," : "-".",";
            $member .= $profiles[$courseMember['userId']]['job'] ? $profiles[$courseMember['userId']]['job']."," : "-".",";
            $member .= $users[$courseMember['userId']]['title'] ? $users[$courseMember['userId']]['title']."," : "-".",";

            foreach ($fields as $key => $value) {
                $member .= $profiles[$courseMember['userId']][$key] ? $profiles[$courseMember['userId']][$key]."," : "-".",";
            }

            $students[] = $member;
        };

        $str .= implode("\r\n", $students);
        $str = chr(239).chr(187).chr(191).$str;

        $filename = sprintf("course-%s-students-(%s).csv", $course['id'], date('Y-n-d'));

        $userId = $this->getCurrentUser()->id;

        $response = new Response();
        $response->headers->set('Content-type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $response->headers->set('Content-length', strlen($str));
        $response->setContent($str);

        return $response;
    }

    public function remarkAction(Request $request, $courseId, $userId)
    {
        $course = $this->getCourseService()->tryManageCourse($courseId);
        $user   = $this->getUserService()->getUser($userId);
        $member = $this->getCourseService()->getCourseMember($courseId, $userId);

        if ('POST' == $request->getMethod()) {
            $data   = $request->request->all();
            $member = $this->getCourseService()->remarkStudent($course['id'], $user['id'], $data['remark']);
            return $this->createStudentTrResponse($course, $member);
        }

        $default = $this->getSettingService()->get('default', array());
        return $this->render('TopxiaWebBundle:CourseStudentManage:remark-modal.html.twig', array(
            'member'  => $member,
            'user'    => $user,
            'course'  => $course,
            'default' => $default
        ));
    }

    public function checkStudentAction(Request $request, $id)
    {
        $keyword = $request->query->get('value');
        $user    = $this->getUserService()->getUserByLoginField($keyword);

        if (!$user) {
            $response = array('success' => false, 'message' => '该用户不存在');
        } else {
            $isCourseStudent = $this->getCourseService()->isCourseStudent($id, $user['id']);

            if ($isCourseStudent) {
                $response = array('success' => false, 'message' => '该用户已是本课程的学员了');
            } else {
                $response = array('success' => true, 'message' => '');
            }

            $isCourseTeacher = $this->getCourseService()->isCourseTeacher($id, $user['id']);

            if ($isCourseTeacher) {
                $response = array('success' => false, 'message' => '该用户是本课程的教师，不能添加');
            }
        }

        return $this->createJsonResponse($response);
    }

    public function showAction(Request $request, $courseId, $userId)
    {
        if (!$this->getCurrentUser()->isAdmin()) {
            throw $this->createAccessDeniedException('您无权查看学员详细信息！');
        }

        $user             = $this->getUserService()->getUser($userId);
        $profile          = $this->getUserService()->getUserProfile($userId);
        $profile['title'] = $user['title'];

        $userFields = $this->getUserFieldService()->getAllFieldsOrderBySeqAndEnabled();

        for ($i = 0; $i < count($userFields); $i++) {
            if (strstr($userFields[$i]['fieldName'], "textField")) {
                $userFields[$i]['type'] = "text";
            }

            if (strstr($userFields[$i]['fieldName'], "varcharField")) {
                $userFields[$i]['type'] = "varchar";
            }

            if (strstr($userFields[$i]['fieldName'], "intField")) {
                $userFields[$i]['type'] = "int";
            }

            if (strstr($userFields[$i]['fieldName'], "floatField")) {
                $userFields[$i]['type'] = "float";
            }

            if (strstr($userFields[$i]['fieldName'], "dateField")) {
                $userFields[$i]['type'] = "date";
            }
        }

        return $this->render('TopxiaWebBundle:CourseStudentManage:show-modal.html.twig', array(
            'user'       => $user,
            'profile'    => $profile,
            'userFields' => $userFields
        ));
    }

    public function definedShowAction(Request $request, $courseId, $userId)
    {
        $profile = $this->getUserService()->getUserProfile($userId);

        $userFields = $this->getUserFieldService()->getAllFieldsOrderBySeqAndEnabled();

        for ($i = 0; $i < count($userFields); $i++) {
            if (strstr($userFields[$i]['fieldName'], "textField")) {
                $userFields[$i]['type'] = "text";
            }

            if (strstr($userFields[$i]['fieldName'], "varcharField")) {
                $userFields[$i]['type'] = "varchar";
            }

            if (strstr($userFields[$i]['fieldName'], "intField")) {
                $userFields[$i]['type'] = "int";
            }

            if (strstr($userFields[$i]['fieldName'], "floatField")) {
                $userFields[$i]['type'] = "float";
            }

            if (strstr($userFields[$i]['fieldName'], "dateField")) {
                $userFields[$i]['type'] = "date";
            }
        }

        $course = $this->getSettingService()->get('course', array());

        $userinfoFields = array();

        if (isset($course['userinfoFields'])) {
            $userinfoFields = $course['userinfoFields'];
        }

        return $this->render('TopxiaWebBundle:CourseStudentManage:defined-show-modal.html.twig', array(
            'profile'        => $profile,
            'userFields'     => $userFields,
            'userinfoFields' => $userinfoFields
        ));
    }

    public function importAction($id)
    {
        $course = $this->getCourseService()->tryManageCourse($id);
        return $this->render('TopxiaWebBundle:CourseStudentManage:import.html.twig', array(
            'course' => $course
        ));
    }

    public function excelDataImportAction($id)
    {
        $course = $this->getCourseService()->tryManageCourse($id);

        if ($course['status'] != 'published') {
            throw $this->createNotFoundException("未发布课程不能导入学员!");
        }

        return $this->render('TopxiaWebBundle:CourseStudentManage:import.step3.html.twig', array(
            'course' => $course
        ));
    }

    protected function calculateUserLearnProgress($course, $member)
    {
        if ($course['lessonNum'] == 0) {
            return array('percent' => '0%', 'number' => 0, 'total' => 0);
        }

        $percent = intval($member['learnedNum'] / $course['lessonNum'] * 100).'%';

        return array(
            'percent' => $percent,
            'number'  => $member['learnedNum'],
            'total'   => $course['lessonNum']
        );
    }

    protected function createStudentTrResponse($course, $student)
    {
        $courseSetting              = $this->getSettingService()->get('course', array());
        $isTeacherAuthManageStudent = !empty($courseSetting['teacher_manage_student']) ? 1 : 0;

        $user        = $this->getUserService()->getUser($student['userId']);
        $isFollowing = $this->getUserService()->isFollowed($this->getCurrentUser()->id, $student['userId']);
        $progress    = $this->calculateUserLearnProgress($course, $student);
        $default     = $this->getSettingService()->get('default', array());
        return $this->render('TopxiaWebBundle:CourseStudentManage:tr.html.twig', array(
            'course'                     => $course,
            'student'                    => $student,
            'user'                       => $user,
            'progress'                   => $progress,
            'isFollowing'                => $isFollowing,
            'isTeacherAuthManageStudent' => $isTeacherAuthManageStudent,
            'default'                    => $default
        ));
    }

    protected function getCourseMemberService()
    {
        return $this->getServiceKernel()->createService('Course.CourseMemberService');
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

    protected function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

    protected function getNotificationService()
    {
        return $this->getServiceKernel()->createService('User.NotificationService');
    }

    protected function getOrderService()
    {
        return $this->getServiceKernel()->createService('Order.OrderService');
    }

    protected function getUserFieldService()
    {
        return $this->getServiceKernel()->createService('User.UserFieldService');
    }
}
