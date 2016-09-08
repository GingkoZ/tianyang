<?php

namespace Topxia\WebBundle\Extensions\DataTag\Test;

use Topxia\Service\Common\BaseTestCase;
use Topxia\WebBundle\Extensions\DataTag\TeacherCoursesDataTag;

class TeacherCoursesDataTagTest extends BaseTestCase
{   

    public function testGetData()
    {
    	$user1 = $this->getUserService()->register(array(
            'email' => '1234@qq.com',
            'nickname' => 'user1',
            'password' => '123456',
            'confirmPassword' => '123456',
            'createdIp' => '127.0.0.1'
        ));
        $this->getUserService()->changeUserRoles($user1['id'],array('ROLE_USER', 'ROLE_TEACHER'));
        $course1 = array(
            'type' => 'normal',
            'title' => 'course1'
        );
        $course2 = array(
            'type' => 'normal',
            'title' => 'course2'
        );
        $course3 = array(
            'type' => 'normal',
            'title' => 'course3'
        );

        $course1 = $this->getCourseService()->createCourse($course1);
        $course2 = $this->getCourseService()->createCourse($course2);
        $course3 = $this->getCourseService()->createCourse($course3);

        $this->getCourseService()->publishCourse($course1['id']);
        $this->getCourseService()->publishCourse($course2['id']);
        $this->getCourseService()->publishCourse($course3['id']);
        $this->getCourseService()->setCourseTeachers($course1['id'],array(array('id' => $user1['id'],'isVisible' => 1)));
     	$this->getCourseService()->setCourseTeachers($course2['id'],array(array('id' => $user1['id'],'isVisible' => 1)));
        $datatag = new TeacherCoursesDataTag();
        $courses = $datatag->getData(array('userId' =>$user1['id'], 'count' => 5));
        $this->assertEquals(2,count($courses));

    }
    public function getUserService()
    {
        return $this->getServiceKernel()->createService('User.UserService');
    }
    public function getCourseService()
    {
    	return $this->getServiceKernel()->createService('Course.CourseService');
    }
}