<?php 
namespace Custom\WebBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Topxia\Service\Course\CourseService;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\Paginator;
use Topxia\WebBundle\Controller\BaseController;

class CustomCourseExerciseController extends BaseController
{
    public function addAction(Request $request, $lessonId, $courseId)
    {

         $course = $this->getCourseService()->tryManageCourse($courseId);  
         $lesson = $this->getCourseService()->getCourseLesson($course['id'], $lessonId);

         $seconds = $request->query->get('showtime');
         $strpos = (int)strpos($seconds,"."); 
         $showtime = (int)substr($seconds,0,$strpos);

         $mediaLessonExerciseCount=$this->getCoursewareService()->searchMediaLessonExerciseCount(array('lessonId' => $lessonId,'showtime' => $showtime));
         if ($request->getMethod() == 'POST') {
              
            $conditions = $request->request->all();

            if($mediaLessonExerciseCount>=5){

                $this->createJsonResponse(flase);
                
            }else{

                $this->getCoursewareService()->createMediaLessonExercise($lessonId,$conditions['questionId'],$conditions['showtime']); 
            }
             list($mediaExercises, $questions) =$this->buildMediaExercises($lessonId);
             return $this->render("CustomWebBundle:CourseExerciseManage:execise-questions-table.html.twig",array(
                "exercises" => $mediaExercises,
                "questions" => $questions,
                "course" => $course,
                "lesson" => $lesson 
             ));
        }

        $questions= $this->buildQuestions($request,$courseId,$lessonId);
        return $this->render('CustomWebBundle:CoursewareExercise:add-modal.html.twig',array(
        'questions' => $questions,
        'showtime' => $showtime,
        'lessonId' => $lessonId,
        'courseId' => $courseId,
        'mediaLessonExerciseCount' =>$mediaLessonExerciseCount
        ));
    }

    private function buildMediaExercises($lessonId)
    {
        $mediaExercises = $this->getCoursewareService()->findMediaLessonExercisesByLessonId($lessonId);
        $questions = $this->getQuestionService()->findQuestionsByIds(ArrayToolkit::column($mediaExercises, 'questionId'));
        return array($mediaExercises, $questions);
    }


    public function findQuestionsExerciseAction(Request $request)
    {
        
        $seconds = $request->query->get("showtime");
        $strpos = (int)strpos($seconds,"."); 
        $showtime = (int)substr($seconds,0,$strpos);

        $lessonId = $request->query->get("lessonId");
        $question = $this->findQuestionsExercise($lessonId,$showtime);   
        
        if($question){

            if($question["type"] == "determine"){

                $question["types"] = "determine";

            }else{

                $question["types"] = "choice";

            }

            return $this->render('CustomWebBundle:CoursewareExercise:question-modal.html.twig',array('questions'=>$question));

        }else{

            return $this->createJsonResponse(false);
        }
    }

    public function findMediaLessonExerciseAction(Request $request)
    {
        
        $lessonId = $request->query->get("lessonId");
        $question = $this->getCoursewareService()->findMediaLessonExerciseAction($lessonId);
        return $this->createJsonResponse($question);
    }

    public function QuestionsAnswerCheckExerciseAction(Request $request,$questionId)
    {
        $answer = $request->request->all();

        if(empty($answer)){

            $result = array('answer' => 'false', 'message'=>'没有答案');
        }

        $question = $this->getQuestionService()->getQuestion($questionId);
        
        if ($answer['answer'] == $question['answer']){
            
            $result =  array('answer' => 'true', 'message'=>'回答正确');
        
        } else {
            
            $result = array('answer' => 'false', 'message'=>'回答错误');
        }

        return $this->createJsonResponse($result);
    }


    public function deleteMediaLessonExerciseAction(Request $request, $id)
    {
        
        $this->getCoursewareService()->deleteMediaLessonExerciseById($id);
        return $this->createJsonResponse(true);
    }


    private function buildQuestions($request,$courseId,$lessonId)
    {
        $conditions= $request->query->all();
        $conditions['types'] = array('choice', 'single_choice', 'uncertain_choice', 'determine');
        $conditions['courseId']=$courseId; 
        $conditions['lessonId']=$lessonId;
    
        $questions=$this->getCoursewareService()->findQuestions($conditions);

        return $questions;
    }



    private function findQuestionsExercise($lessonId,$showtime)
    {

      $mediaExercises = $this->getCoursewareService()->findMediaLessonExercises($lessonId,$showtime);
      
      if($mediaExercises){
        $mediaExercisesCount = count($mediaExercises)-1;
        $mediaExercisesRand = rand(0,$mediaExercisesCount);
        $questions = $this->getQuestionService()->getQuestion($mediaExercises[$mediaExercisesRand]['questionId']);
       
        return $questions;
      }
      
    }


    private function getCoursewareService()
    {
        return $this->getServiceKernel()->createService('Custom:Courseware.CoursewareService');
    }

    private function getQuestionService()
    {
        return $this->getServiceKernel()->createService('Question.QuestionService');
    }



    private function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

}
