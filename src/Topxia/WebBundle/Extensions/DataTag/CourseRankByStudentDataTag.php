<?php

namespace Topxia\WebBundle\Extensions\DataTag;

use Topxia\WebBundle\Extensions\DataTag\DataTag;

class CourseRankByStudentDataTag extends CourseBaseDataTag implements DataTag  
{

    /**
     * 获取按学生数排行的课程
     *
     * 可传入的参数：
     *   count    必需 课程数量，取值不能超过100
     * 
     * @param  array $arguments 参数
     * @return array 课程列表
     */

    public function getData(array $arguments)
    {	
        $this->checkCount($arguments);
     
        $conditions = array('status' => 'published');
        $conditions['parentId'] = 0;

    	$courses = $this->getCourseService()->searchCourses($conditions,'studentNum', 0, $arguments['count']);
        
        return $this->getCourseTeachersAndCategories($courses);    
    }

}
