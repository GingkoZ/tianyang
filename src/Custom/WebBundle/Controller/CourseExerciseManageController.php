<?php
namespace Custom\WebBundle\Controller;

use Topxia\Common\ArrayToolkit;
use Symfony\Component\HttpFoundation\Request;
use Topxia\WebBundle\Controller\BaseController;
use Topxia\Common\Paginator;

class CourseExerciseManageController extends BaseController
{
    public function createExerciseAction(Request $request, $courseId, $lessonId)
    {   
        list($course, $lesson) = $this->getExerciseCourseAndLesson($courseId, $lessonId);
    
        if($request->getMethod() == 'POST') {
            $fields = $this->generateExerciseFields($request->request->all(), $course, $lesson);
            $exercise = $this->getExerciseService()->createExercise($fields);
            return $this->createJsonResponse($this->generateUrl('course_manage_lesson', array('id' => $course['id'])));
        }

        list($mediaExercises, $questions) =$this->buildMediaExercises($lessonId);
        $type = $request->query->get('type');  

        return $this->render('HomeworkBundle:CourseExerciseManage:exercise.html.twig', array(
        'course' => $course,
        'lesson' => $lesson,
        'type' => $type,
        'exercise' => array('id' => null),
        'exercises' => $mediaExercises,
        'questions' => $questions,
        ));

    }



    private function generateExerciseFields($fields, $course, $lesson)
    {
        $fields['ranges'] = array();
        $fields['choice'] = empty($fields['choice']) ? array() : $fields['ranges'][] = $fields['choice'];
        $fields['single_choice'] = empty($fields['single_choice']) ? array() : $fields['ranges'][] = $fields['single_choice'];
        $fields['uncertain_choice'] = empty($fields['uncertain_choice']) ? array() : $fields['ranges'][] = $fields['uncertain_choice'];
        $fields['fill'] = empty($fields['fill']) ? array() : $fields['ranges'][] = $fields['fill'];
        $fields['determine'] = empty($fields['determine']) ? array() : $fields['ranges'][] = $fields['determine'];
        $fields['essay'] = empty($fields['essay']) ? array() : $fields['ranges'][] = $fields['essay'];
        $fields['material'] = empty($fields['material']) ? array() : $fields['ranges'][] = $fields['material'];
        $fields['courseId'] = $course['id'];
        $fields['lessonId'] = $lesson['id'];

        return $fields;
    }

    private function buildMediaExercises($lessonId)
    {
        $mediaExercises = $this->getCoursewareService()->findMediaLessonExercisesByLessonId($lessonId);
        $questions = $this->getQuestionService()->findQuestionsByIds(ArrayToolkit::column($mediaExercises, 'questionId'));
        return array($mediaExercises, $questions);
    }

    private function getExerciseCourseAndLesson($courseId, $lessonId)
    {
        $course = $this->getCourseService()->tryManageCourse($courseId);
        
        $lesson = $this->getCourseService()->getCourseLesson($course['id'], $lessonId);
        if (empty($lesson)) {
            throw $this->createNotFoundException("课时(#{$lessonId})不存在！");
        }

        return array($course, $lesson);
    }


    private function getCoursewareService()
    {
        return $this->getServiceKernel()->createService('Custom:Courseware.CoursewareService');
    }

    private function getExerciseService()
    {
        return $this->getServiceKernel()->createService('Homework:Homework.ExerciseService');
    }

    private function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

    private function getQuestionService()
    {
        return $this->getServiceKernel()->createService('Question.QuestionService');
    }

}
