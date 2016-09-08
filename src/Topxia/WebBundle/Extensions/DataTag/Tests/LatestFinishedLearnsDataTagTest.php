<?php

namespace Topxia\WebBundle\Extensions\DataTag\Test;

use Topxia\Service\Common\BaseTestCase;
use Topxia\WebBundle\Extensions\DataTag\LatestFinishedLearnsDataTag;

class LatestFinishedLearnsDataTagTest extends BaseTestCase
{   

    public function testGetData()
    {
        $course1 = array(
            'type' => 'normal',
            'title' => 'course1'
        );
        $course1 = $this->getCourseService()->createCourse($course1);

        $this->getCourseService()->publishCourse($course1['id']);

        $lesson1 =array(
        	'courseId' => $course1['id'],
        	'title' => 'lesson1',
        	'type' => 'text'
        );

        $lesson2 =array(
        	'courseId' => $course1['id'],
        	'title' => 'lesson2',
        	'type' => 'text'
        );
        $lesson3 =array(
        	'courseId' => $course1['id'],
        	'title' => 'lesson3',
        	'type' => 'text'
        );
        $lesson1 = $this->getCourseService()->createLesson($lesson1);
        $lesson2 = $this->getCourseService()->createLesson($lesson2);        
        $lesson3 = $this->getCourseService()->createLesson($lesson3);

        $this->getCourseService()->publishLesson($course1['id'],$lesson1['id']);
        $this->getCourseService()->publishLesson($course1['id'],$lesson2['id']);
        $this->getCourseService()->publishLesson($course1['id'],$lesson3['id']);
        $this->getCourseService()->startLearnLesson($course1['id'],$lesson1['id']);
        $this->getCourseService()->startLearnLesson($course1['id'],$lesson2['id']);
        $this->getCourseService()->startLearnLesson($course1['id'],$lesson3['id']);
        $this->getCourseService()->finishLearnLesson($course1['id'],$lesson1['id']);
        $this->getCourseService()->finishLearnLesson($course1['id'],$lesson2['id']);

        $datatag = new LatestFinishedLearnsDataTag();
        $learns = $datatag->getData(array('count' => 5));
        $this->assertEquals(2,count($learns));

    }

    private function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

}