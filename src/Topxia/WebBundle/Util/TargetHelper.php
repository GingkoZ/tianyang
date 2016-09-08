<?php

namespace Topxia\WebBundle\Util;

use Topxia\Service\Common\ServiceKernel;

class TargetHelper
{

    protected $container;

    public function __construct ($container)
    {
        $this->container = $container;
    }

    public function getTargets(array $targets)
    {
        $targets = $this->parseTargets($targets);

        $datas = $this->loadTargetDatas($targets);

        foreach ($targets as $key => $target) {
            if (empty($datas[$target['type']]) || empty($datas[$target['type']][$target['id']])) {
                $targets[$key] = null;
            } else {
                $targets[$key] = $datas[$target['type']][$target['id']];
            }
        }

        return $targets;
    }

    private function loadTargetDatas($targets)
    {
        $groupedTargets = array();
        foreach ($targets as $target) {
            if ($target['type'] == 'unknow') {
                continue;
            }
            if (empty($groupedTargets[$target['type']])) {
                $groupedTargets[$target['type']] = array();
            }
            $groupedTargets[$target['type']][] = $target['id'];
        }

        $datas = array();
        foreach ($groupedTargets as $type => $ids) {
            $finderClass = __NAMESPACE__ . '\\' . ucfirst($type) . 'TargetFinder';
            $finder = new $finderClass($this->container);
            $datas[$type] = $finder->find($ids);
        }

        return $datas;
    }

    private function parseTargets($targets)
    {
        $parsedTargets = array();

        foreach ($targets as $target) {
            $explodedTarget = explode('/', $target);
            $lastTarget = end($explodedTarget);

            if (strpos($lastTarget, '-') === false) {
                $parsedTargets[$target] = array('type' => 'unknow', 'id' => 0);
            } else {
                list($type, $id) = explode('-', $lastTarget);
                $parsedTargets[$target] =  array('type' => $type, 'id' => $id);
            }
        }

        return $parsedTargets;
    }

}

abstract class AbstractTargetFinder
{
    protected $container;

    public function __construct ($container)
    {
        $this->container = $container;
    }

    abstract public function find(array $ids);
}

class CourseTargetFinder extends AbstractTargetFinder
{
    public function find(array $ids)
    {
        $courses = ServiceKernel::instance()->createService('Course.CourseService')->findCoursesByIds($ids);
        $targets = array();
        foreach ($courses as $id => $course) {
            $targets[$id] = array(
                'type' => 'course',
                'id' => $id,
                'simple_name' => $course['title'],
                'name' => $course['title'],
                'full_name' => $course['title'],
                'url' => $this->container->get('router')->generate('course_show', array('id' => $id))
            );
        }

        return $targets;
    }
}

class LessonTargetFinder extends AbstractTargetFinder
{
    public function find(array $ids)
    {
        $lessons = ServiceKernel::instance()->createService('Course.CourseService')->findLessonsByIds($ids);

        $targets = array();
        foreach ($lessons as $id => $lesson) {
            $targets[$id] = array(
                'type' => 'lesson',
                'id' => $id,
                'simple_name' => "课时{$lesson['number']}",
                'name' => $lesson['title'],
                'full_name' => "课时{$lesson['number']}：{$lesson['title']}",
                'url' => $this->container->get('router')->generate('course_learn', array('id' => $lesson['courseId'])) . '#lesson/' . $id,
            );
        }
        return $targets;
    }
}

class TestpaperTargetFinder extends AbstractTargetFinder
{
    public function find(array $ids)
    {
        $testpapers = ServiceKernel::instance()->createService('Testpaper.TestpaperService')->findTestpapersByIds($ids);

        $targets = array();
        foreach ($testpapers as $id => $testpaper) {
            $targets[$id] = array(
                'type' => 'testpaper',
                'id' => $id,
                'simple_name' => $testpaper['name'],
                'name' => $testpaper['name'],
                'full_name' => $testpaper['name'],
                'url' => '',
            );
        }
        return $targets;
    }
}