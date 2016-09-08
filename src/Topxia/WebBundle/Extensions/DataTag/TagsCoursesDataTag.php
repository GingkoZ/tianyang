<?php

namespace Topxia\WebBundle\Extensions\DataTag;

use Topxia\WebBundle\Extensions\DataTag\DataTag;

class TagsCoursesDataTag extends CourseBaseDataTag implements DataTag
{
    /**
     * 获取标签课程列表
     *
     * 可传入的参数：
     *   tags 必需 标签名称like array('aa')
     *   count    必需 课程数量，取值不超过10
     *
     * @param  array $arguments     参数
     * @return array 课程列表
     */
    public function getData(array $arguments)
    {
        $tags = $this->getTagService()->findTagsByNames($arguments['tags']);

        $tagIds = array();

        foreach ($tags as $tagId) {
            array_push($tagIds, $tagId['id']);
        }

        if (empty($arguments['status'])) {
            $status = 'published';
        } else {
            $status = $arguments['status'];
        }

        $condition = array(
            'tagIds' => $tagIds,
            'status' => $status
        );

        $courses = $this->getCourseService()->searchCourses($condition, 'createdTime', 0, $arguments['count']);

        return $this->getCourseTeachersAndCategories($courses);
    }

    protected function getTagService()
    {
        return $this->getServiceKernel()->createService('Taxonomy.TagService');
    }
}
