<?php

namespace Topxia\WebBundle\Extensions\DataTag;

use Topxia\WebBundle\Extensions\DataTag\DataTag;
use Topxia\Common\ArrayToolkit;

class LatestCourseMembersDataTag extends CourseBaseDataTag implements DataTag  
{

    /**
     * 获取一个分类下的课程成员列表
     *
     * 可传入的参数：
     *   categoryId 选填 分类ID
     *   count    必需 学员数量，取值不能超过100
     * 
     * @param  array $arguments 参数
     * @return array 课程成员列表
     */
    public function getData(array $arguments)
    {	
        $this->checkCount($arguments);
        if (empty($arguments['categoryId'])) {
            $conditions = array('status' => 'published');
        }else {
            $conditions = array('status' => 'published', 'categoryId' => $arguments['categoryId']);        
        }
        $courses = $this->getCourseService()->searchCourses($conditions,'latest', 0, 1000);
        $courseIds = ArrayToolkit::column($courses, 'id');

        $conditions = array('courseIds' => $courseIds, 'unique' => true , 'role' => 'student');
        $memberIds = $this->getCourseService()->searchMemberIds($conditions, 'latest', 0, $arguments['count']);
        $memberIds = ArrayToolkit::column($memberIds, 'userId');
        $users = $this->getUserService()->findUsersByIds($memberIds);
        $users = ArrayToolkit::index($users, 'id');

        $courseMembers= array();
        foreach ($memberIds as $memberId) {
            $courseMembers[$memberId] = $users[$memberId];
        }
        return $this->unsetUserPasswords($courseMembers);
    }
}
