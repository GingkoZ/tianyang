<?php
namespace Topxia\WebBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\Paginator;
use Topxia\Service\Question\Type\QuestionTypeFactory;

class CourseTestpaperManageController extends BaseController
{
    public function indexAction(Request $request, $courseId)
    {
        $course = $this->getCourseService()->tryManageCourse($courseId);

        $conditions = array();
        $conditions['target'] = "course-{$course['id']}";
        $paginator = new Paginator(
            $this->get('request'),
            $this->getTestpaperService()->searchTestpapersCount($conditions),
            10
        );

        $testpapers = $this->getTestpaperService()->searchTestpapers(
            $conditions,
            array('createdTime' ,'DESC'),
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $users = $this->getUserService()->findUsersByIds(ArrayToolkit::column($testpapers, 'updatedUserId')); 
        
        return $this->render('TopxiaWebBundle:CourseTestpaperManage:index.html.twig', array(
            'course' => $course,
            'testpapers' => $testpapers,
            'users' => $users,
            'paginator' => $paginator,

        ));
    }

    public function createAction(Request $request, $courseId)
    {
        $course = $this->getCourseService()->tryManageCourse($courseId);

        if ($request->getMethod() == 'POST') {
            $fields = $request->request->all();
            $fields['ranges'] = empty($fields['ranges']) ? array() : explode(',', $fields['ranges']);
            $fields['target'] = "course-{$course['id']}";
            $fields['pattern'] = 'QuestionType';
            list($testpaper, $items) = $this->getTestpaperService()->createTestpaper($fields);
            return $this->redirect($this->generateUrl('course_manage_testpaper_items',array('courseId' => $course['id'], 'testpaperId' => $testpaper['id'])));
        }

        $typeNames = $this->get('topxia.twig.web_extension')->getDict('questionType');
        $types = array();
        
        foreach ($typeNames as $type => $name) {
            $typeObj = QuestionTypeFactory::create($type);
            $types[] = array(
                'key' => $type,
                'name' => $name,
                'hasMissScore' => $typeObj->hasMissScore(),
            );
        }

        $conditions["types"] = ArrayToolkit::column($types,"key");
        $conditions["courseId"] = $course["id"];
        $questionNums = $this->getQuestionService()->getQuestionCountGroupByTypes($conditions);
        $questionNums = ArrayToolkit::index($questionNums, "type");
        return $this->render('TopxiaWebBundle:CourseTestpaperManage:create.html.twig', array(
            'course'    => $course,
            'ranges' => $this->getQuestionRanges($course),
            'types' => $types,
            'questionNums' => $questionNums
        ));
    }

    public function getQuestionCountGroupByTypesAction(Request $request, $courseId)
    {
        $params = $request->query->all();
        $course = $this->getCourseService()->tryManageCourse($courseId);
        if(empty($course)){
            return $this->createJsonResponse(array());
        }

        $typeNames = $this->get('topxia.twig.web_extension')->getDict('questionType');
        $types = array();
        
        foreach ($typeNames as $type => $name) {
            $typeObj = QuestionTypeFactory::create($type);
            $types[] = array(
                'key' => $type,
                'name' => $name,
                'hasMissScore' => $typeObj->hasMissScore(),
            );
        }

        $conditions["types"] = ArrayToolkit::column($types,"key");
        if($params["range"] == "course") {
            $conditions["courseId"] = $course["id"];
        } else if($params["range"] == "lesson"){
            $targets = $params["targets"];
            $targets = explode(',', $targets);
            $conditions["targets"] = $targets;
        }

        $questionNums = $this->getQuestionService()->getQuestionCountGroupByTypes($conditions);
        $questionNums = ArrayToolkit::index($questionNums, "type");
        return $this->createJsonResponse($questionNums);
    }

    public function buildCheckAction(Request $request, $courseId)
    {
        $course = $this->getCourseService()->tryManageCourse($courseId);

        $data = $request->request->all();
        $data['target'] = "course-{$course['id']}";
        $data['ranges'] = empty($data['ranges']) ? array() : explode(',', $data['ranges']);
        $result = $this->getTestpaperService()->canBuildTestpaper('QuestionType', $data);
        return $this->createJsonResponse($result);
    }

    public function updateAction(Request $request, $courseId, $id)
    {
        $course = $this->getCourseService()->tryManageCourse($courseId);

        $testpaper = $this->getTestpaperService()->getTestpaper($id);
        if (empty($testpaper)) {
            throw $this->createNotFoundException('试卷不存在');
        }

        if ($request->getMethod() == 'POST') {
            $data = $request->request->all();
            $testpaper = $this->getTestpaperService()->updateTestpaper($id, $data);
            $this->setFlashMessage('success', '试卷信息保存成功！');
            return $this->redirect($this->generateUrl('course_manage_testpaper', array('courseId' => $course['id'])));
        }

        return $this->render('TopxiaWebBundle:CourseTestpaperManage:update.html.twig', array(
            'course'    => $course,
            'testpaper' => $testpaper,
        ));
    }

    public function deleteAction(Request $request, $courseId, $testpaperId)
    {
        $course = $this->getCourseService()->tryManageCourse($courseId);
        $testpaper = $this->getTestpaperWithException($course, $testpaperId);
        $this->getTestpaperService()->deleteTestpaper($testpaper['id']);

        return $this->createJsonResponse(true);
    }

    public function deletesAction(Request $request, $courseId)
    {   
        $course = $this->getCourseService()->tryManageCourse($courseId);

        $ids = $request->request->get('ids');

        foreach (is_array($ids) ? $ids : array() as $id) {
            $testpaper = $this->getTestpaperWithException($course, $id);
            $this->getTestpaperService()->deleteTestpaper($id);
        }

        return $this->createJsonResponse(true);
    }

    public function publishAction (Request $request, $courseId, $id)
    {
        $course = $this->getCourseService()->tryManageCourse($courseId);

        $testpaper = $this->getTestpaperWithException($course, $id);

        $testpaper = $this->getTestpaperService()->publishTestpaper($id);

        $user = $this->getUserService()->getUser($testpaper['updatedUserId']);

        return $this->render('TopxiaWebBundle:CourseTestpaperManage:tr.html.twig', array(
            'testpaper' => $testpaper,
            'user' => $user,
            'course' => $course,
        ));
    }

    public function closeAction (Request $request, $courseId, $id)
    {
        $course = $this->getCourseService()->tryManageCourse($courseId);

        $testpaper = $this->getTestpaperWithException($course, $id);

        $testpaper = $this->getTestpaperService()->closeTestpaper($id);

        $user = $this->getUserService()->getUser($testpaper['updatedUserId']);

        return $this->render('TopxiaWebBundle:CourseTestpaperManage:tr.html.twig', array(
            'testpaper' => $testpaper,
            'user' => $user,
            'course' => $course,
        ));
    }

    protected function getTestpaperWithException($course, $testpaperId)
    {
        $testpaper = $this->getTestpaperService()->getTestpaper($testpaperId);
        if (empty($testpaper)) {
            throw $this->createNotFoundException();
        }

        if ($testpaper['target'] != "course-{$course['id']}") {
            throw $this->createAccessDeniedException();
        }
        return $testpaper;
    }

    public function itemsAction(Request $request, $courseId, $testpaperId)
    {
        $course = $this->getCourseService()->tryManageCourse($courseId);

        $testpaper = $this->getTestpaperService()->getTestpaper($testpaperId);
        if(empty($testpaper)){
            throw $this->createNotFoundException('试卷不存在');
        }

        if ($request->getMethod() == 'POST') {
            $data = $request->request->all();
            if (empty($data['questionId']) || empty($data['scores'])) {
                return $this->createMessageResponse('error', '试卷题目不能为空！');
            }
            if (count($data['questionId']) != count($data['scores'])) {
                return $this->createMessageResponse('error', '试卷题目数据不正确');
            }

            $data['questionId'] = array_values($data['questionId']);
            $data['scores'] = array_values($data['scores']);

            $items = array();
            foreach ($data['questionId'] as $index => $questionId) {
                $items[] = array('questionId' => $questionId, 'score' => $data['scores'][$index]);
            }

            $this->getTestpaperService()->updateTestpaperItems($testpaper['id'], $items);

            if (isset($data['passedScore'])) {
                $this->getTestpaperService()->updateTestpaper($testpaperId, array('passedScore'=>$data['passedScore']));
            }

            $this->setFlashMessage('success', '试卷题目保存成功！');
            return $this->redirect($this->generateUrl('course_manage_testpaper',array( 'courseId' => $courseId)));
        }

        $items = $this->getTestpaperService()->getTestpaperItems($testpaper['id']);
        $questions = $this->getQuestionService()->findQuestionsByIds(ArrayToolkit::column($items, 'questionId'));

        $targets = $this->get('topxia.target_helper')->getTargets(ArrayToolkit::column($questions, 'target'));

        $subItems = array();
        $hasEssay = false;
        $scoreTotal = 0;
        foreach ($items as $key => $item) {
            if ($item['questionType'] == 'essay') {
                $hasEssay = true;
            }

            $scoreTotal = $scoreTotal+$item['score'];
            if ($item['parentId'] > 0) {
                $subItems[$item['parentId']][] = $item;
                unset($items[$key]);
            }
        }

        $passedScoreDefault = ceil($scoreTotal*0.6);
        return $this->render('TopxiaWebBundle:CourseTestpaperManage:items.html.twig', array(
            'course' => $course,
            'testpaper' => $testpaper,
            'items' => ArrayToolkit::group($items, 'questionType'),
            'subItems' => $subItems,
            'questions' => $questions,
            'targets' => $targets,
            'hasEssay' => $hasEssay,
            'passedScoreDefault' => $passedScoreDefault
        ));
    }

    public function itemsResetAction(Request $request, $courseId, $testpaperId)
    {
        $course = $this->getCourseService()->tryManageCourse($courseId);

        $testpaper = $this->getTestpaperService()->getTestpaper($testpaperId);
        if(empty($testpaper)){
            throw $this->createNotFoundException('试卷不存在');
        }

        if ($request->getMethod() == 'POST') {
            $data = $request->request->all();
            $data['target'] = "course-{$course['id']}";
            $data['ranges'] = explode(',', $data['ranges']);
            $this->getTestpaperService()->buildTestpaper($testpaper['id'], $data);
            return $this->redirect($this->generateUrl('course_manage_testpaper_items', array('courseId' => $courseId, 'testpaperId' => $testpaperId)));
        }

        $typeNames = $this->get('topxia.twig.web_extension')->getDict('questionType');
        $types = array();
        foreach ($typeNames as $type => $name) {
            $typeObj = QuestionTypeFactory::create($type);
            $types[] = array(
                'key' => $type,
                'name' => $name,
                'hasMissScore' => $typeObj->hasMissScore(),
            );
        }

        $conditions["types"] = ArrayToolkit::column($types,"key");
        $conditions["courseId"] = $course["id"];
        $questionNums = $this->getQuestionService()->getQuestionCountGroupByTypes($conditions);
        $questionNums = ArrayToolkit::index($questionNums, "type");
        return $this->render('TopxiaWebBundle:CourseTestpaperManage:items-reset.html.twig', array(
            'course'    => $course,
            'testpaper' => $testpaper,
            'ranges' => $this->getQuestionRanges($course),
            'types' => $types,
            'questionNums' => $questionNums
        ));
    }

    public function itemPickerAction(Request $request, $courseId, $testpaperId)
    {
        $course = $this->getCourseService()->tryManageCourse($courseId);
        $testpaper = $this->getTestpaperService()->getTestpaper($testpaperId);
        if (empty($testpaper)) {
            throw $this->createNotFoundException();
        }

        $conditions = $request->query->all();

        if (empty($conditions['target'])) {
            $conditions['targetPrefix'] = "course-{$course['id']}";
        }

        $conditions['parentId'] = 0;
        $conditions['excludeIds'] = empty($conditions['excludeIds']) ? array() : explode(',', $conditions['excludeIds']);

        if (!empty($conditions['keyword'])) {
            $conditions['stem'] = $conditions['keyword'];
        }


        $replace = empty($conditions['replace']) ? '' : $conditions['replace'];
        
        $paginator = new Paginator(
            $request,
            $this->getQuestionService()->searchQuestionsCount($conditions),
            7
        );

        $questions = $this->getQuestionService()->searchQuestions(
                $conditions, 
                array('createdTime' ,'DESC'), 
                $paginator->getOffsetCount(),
                $paginator->getPerPageCount()
        );

        $targets = $this->get('topxia.target_helper')->getTargets(ArrayToolkit::column($questions, 'target'));
        return $this->render('TopxiaWebBundle:CourseTestpaperManage:item-picker-modal.html.twig', array(
            'course' => $course,
            'testpaper' => $testpaper,
            'questions' => $questions,
            'replace' => $replace,
            'paginator' => $paginator,
            'targetChoices' => $this->getQuestionRanges($course, true),
            'targets' => $targets,
            'conditions' => $conditions,
        ));
        
    }

    public function itemPickedAction(Request $request, $courseId, $testpaperId)
    {
        $course = $this->getCourseService()->tryManageCourse($courseId);
        $testpaper = $this->getTestpaperService()->getTestpaper($testpaperId);
        if (empty($testpaper)) {
            throw $this->createNotFoundException();
        }

        $question = $this->getQuestionService()->getQuestion($request->query->get('questionId'));
        if (empty($question)) {
            throw $this->createNotFoundException();
        }

        if ($question['subCount'] > 0) {
            $subQuestions = $this->getQuestionService()->findQuestionsByParentId($question['id']);
        } else {
            $subQuestions = array();
        }

        $targets = $this->get('topxia.target_helper')->getTargets(array($question['target']));

        return $this->render('TopxiaWebBundle:CourseTestpaperManage:item-picked.html.twig', array(
            'course'    => $course,
            'testpaper' => $testpaper,
            'question' => $question,
            'subQuestions' => $subQuestions,
            'targets' => $targets,
            'type' => $question['type']
        ));

    }

    public function itemsGetAction(Request $request, $courseId)
    {
        $course = $this->getCourseService()->tryManageCourse($courseId);

        $testpaperId = $request->request->get('testpaperId');
        
        $testpaper = $this->getTestpaperService()->getTestpaper($testpaperId);
        if (empty($testpaper)) {
            throw $this->createNotFoundException();
        }

        $items = $this->getTestpaperService()->getItemsCountByParams(array('testId'=>$testpaperId,'parentIdDefault'=>0),$gourpBy='questionType');
        $subItems = $this->getTestpaperService()->getItemsCountByParams(array('testId'=>$testpaperId,'parentId'=>0));
        
        $items = ArrayToolkit::index($items,'questionType');
        $objectiveQuestionsCount = 0;
        $subjectiveQuestionsCount = 0;
        foreach($items as $key => $item){
            if ($key == 'essay' || $key == 'material') {
                $subjectiveQuestionsCount = $subjectiveQuestionsCount + $item['num'];
            } else {
                $objectiveQuestionsCount = $objectiveQuestionsCount + $item['num'];
            }
        }
        

        $objectiveQuestionsCountHour = number_format(($objectiveQuestionsCount*5)/60,1);
        $suggestHours = number_format(($objectiveQuestionsCount*5)/60,1) + $subjectiveQuestionsCount*0.5;  
        $multiple = ceil($suggestHours / 0.5)*0.5;
        $suggestHours = $suggestHours > $multiple ? ($multiple+0.5) : $multiple;
        

        $items['material'] = $subItems[0];
        
        return $this->render('TopxiaWebBundle:CourseTestpaperManage:item-get-table.html.twig', array(
            'suggestHours' => $suggestHours,
            'items' => $items
        ));
    }

    protected function getQuestionRanges($course, $includeCourse = false)
    {
        $lessons = $this->getCourseService()->getCourseLessons($course['id']);
        $ranges = array();

        if ($includeCourse == true) {
            $ranges["course-{$course['id']}"] = '本课程';
        }

        foreach ($lessons as  $lesson) {
            if ($lesson['type'] == 'testpaper') {
                continue;
            }
            $ranges["course-{$lesson['courseId']}/lesson-{$lesson['id']}"] = "课时{$lesson['number']}： {$lesson['title']}";
        }

        return $ranges;
    }

    protected function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

    protected function getTestpaperService()
    {
        return $this->getServiceKernel()->createService('Testpaper.TestpaperService');
    }

    protected function getQuestionService()
    {
        return $this->getServiceKernel()->createService('Question.QuestionService');
    }
}