<?php
namespace Topxia\MobileBundleV2\Processor\Impl;

use Topxia\MobileBundleV2\Processor\BaseProcessor;
use Topxia\MobileBundleV2\Processor\TestpaperProcessor;
use Topxia\Common\ArrayToolkit;


class TestpaperProcessorImpl extends BaseProcessor implements TestpaperProcessor
{
	public function reDoTestpaper()
	{
		$testId = $this->getParam("testId");
		$targetType = $this->getParam("targetType");
		$targetId= $this->getParam("targetId");
		$user = $this->controller->getUserByToken($this->request);

                        if (!$user->isLogin()) {
                            return $this->createErrorResponse('not_login', '您尚未登录，不能查看该课时');
                        }

                        $testpaper = $this->getTestpaperService()->getTestpaper($testId);

	        	$targets = $this->controller->get('topxia.target_helper')->getTargets(array($testpaper['target']));
	        	if ($targets[$testpaper['target']]['type'] != 'course') {
                        	return $this->createErrorResponse('error', '试卷只能属于课程');
	        	}

	        	$course = $this->getCourseService()->getCourse($targets[$testpaper['target']]['id']);

	        	if (empty($course)) {
                        	return $this->createErrorResponse('error', '试卷所属课程不存在！');
	        	}

	        	if (!$this->getCourseService()->canTakeCourse($course)) {
                        	return $this->createErrorResponse('error', '不是试卷所属课程老师或学生');
	        	}

	        	if (empty($testpaper)) {
                        	return $this->createErrorResponse('error', '试卷不存在！或已删除!');
	        	}

	        	$userId = $user['id'];
	        	$testResult = $this->getTestpaperService()->findTestpaperResultsByTestIdAndStatusAndUserId($testId, $userId, array('doing', 'paused'));

             	if ($testpaper['status'] == 'draft') {
	                        return $this->createErrorResponse('error', '该试卷未发布，如有疑问请联系老师！!');
	            }
	            if ($testpaper['status'] == 'closed') {
	                        return $this->createErrorResponse('error', '该试卷已关闭，如有疑问请联系老师！!');
	            }

	            $testResult = $this->getTestpaperService()->startTestpaper($testId, array('type' => $targetType, 'id' => $targetId));

        		return array(
            		    'testpaperResult'=>$testResult,
	                            'testpaper'=>$testpaper,
	                            'items'=>$this->getTestpaperItem($testResult)
	                            );
	}

	public function favoriteQuestion()
	{
		$user = $this->controller->getUserByToken($this->request);
                        if (!$user->isLogin()) {
                            return $this->createErrorResponse('not_login', '您尚未登录，不能查看该课时');
                        }

                        $id = $this->getParam("id");
                        $action = $this->getParam("action");
                        $targetType = $this->getParam("targetType");
                        $targetId = $this->getParam("targetId");
            	$target = $targetType."-".$targetId;

            	if ($action == "favorite") {
            		$this->getQuestionService()->favoriteQuestion($id, $target, $user['id']);
            	} else {
            		$this->getQuestionService()->unFavoriteQuestion($id, $target, $user['id']);
            	}
        
            	return true;
	}

	public function myTestpaper()	
	{
		$user = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', '您尚未登录，不能查看该课时');
        }

        $start = (int) $this->getParam("start", 0);
		$limit = (int) $this->getParam("limit", 10);
        $total = $this->getTestpaperService()->findTestpaperResultsCountByUserId($user['id']);

        $testpaperResults = $this->getTestpaperService()->findTestpaperResultsByUserId(
	        $user['id'],
	        $start,
	        $limit
		);

        $testpapersIds = ArrayToolkit::column($testpaperResults, 'testId');

		$testpapers = $this->getTestpaperService()->findTestpapersByIds($testpapersIds);
		$testpapers = ArrayToolkit::index($testpapers, 'id');

		$targets = ArrayToolkit::column($testpapers, 'target');
		$courseIds = array_map(function($target){
    		$course = explode('/', $target);
    		$course = explode('-', $course[0]);
    		return $course[1];
		}, $targets);

		$courses = $this->getCourseService()->findCoursesByIds($courseIds);
		$data = array(
			'myTestpaperResults' => $this->filterMyTestpaperResults($testpaperResults, $testpapersIds),
    		'myTestpapers' => $this->filterMyTestpaper($testpapers),
    		'courses' => $this->filterMyTestpaperCourses($courses),
		);
		return array(
			"start"=>$start,
			"total"=>$total,
			"limit"=>$limit,
			"data"=>$data
		);
	}

	private function filterMyTestpaperResults($testpaperResults, $testIds)
	{
		$results = $testpaperResults;
		foreach ($testpaperResults as $key => $value) {
			if(!in_array($value['testId'], $testIds)){
				unset($results[$key]);
			}else{
				$results[$key]['beginTime'] = date('Y-m-d H:i:s', $value['beginTime']);
				$results[$key]['endTime'] = date('Y-m-d H:i:s', $value['endTime']);
				if($results[$key]['updateTime'] != 0){
					$results[$key]['updateTime'] = date('Y-m-d H:i:s', $value['updateTime']);
				}
				$results[$key]['checkedTime'] = date('Y-m-d H:i:s', $value['checkedTime']);
			}
		}
		return $results;
	}

	private function filterMyTestpaper($testpapers)
	{
		return array_map(function($testpaper){
			unset($testpaper['description']);
			unset($testpaper['metas']);

			return $testpaper;
		}, $testpapers);
	}

	private function filterMyTestpaperCourses($courses)
	{
		$courses = $this->controller->filterCourses($courses);
		return array_map(function($course){
			unset($course['about']);
			unset($course['teachers']);
			unset($course['goals']);
			unset($course['audiences']);
			unset($course['subtitle']);

			return $course;
		}, $courses);
	}

	public function uploadQuestionImage()
	{
		$user = $this->controller->getUserByToken($this->request);
                        if (!$user->isLogin()) {
                            return $this->createErrorResponse('not_login', '您尚未登录，不能查看该课时');
                        }
		$url = "";
		try {
			$file = $this->request->files->get('file');
			$group = $this->getParam("group", 'course');
			$record = $this->getFileService()->uploadFile($group, $file);
			$url = $this->controller->get('topxia.twig.web_extension')->getFilePath($record['uri']);
		} catch (\Exception $e) {
			$url = "";
		}

		$baseUrl = $this->request->getSchemeAndHttpHost();
		$url = empty($url) ? "" : $baseUrl . '/' .$url;
        		return $url;
	}

	public function finishTestpaper()
	{
		$id = $this->getParam("id");
		$testpaperResult = $this->getTestpaperService()->getTestpaperResult($id);

	        	if (!empty($testpaperResult) && !in_array($testpaperResult['status'], array('doing', 'paused'))) {
	            	return true;
	        	}

	        	$data = $this->request->request->all();
	            $answers = array_key_exists('data', $data) ? $data['data'] : array();
	            $usedTime = $data['usedTime'];
	            $user = $this->controller->getUserByToken($this->request);

	            //提交变化的答案
	            $results = $this->getTestpaperService()->submitTestpaperAnswer($id, $answers);

	            //完成试卷，计算得分
	            $testResults = $this->getTestpaperService()->makeTestpaperResultFinish($id);

	            $testpaperResult = $this->getTestpaperService()->getTestpaperResult($id);

	            $testpaper = $this->getTestpaperService()->getTestpaper($testpaperResult['testId']);
	            //试卷信息记录
	            $this->getTestpaperService()->finishTest($id, $user['id'], $usedTime);

	            $targets = $this->controller->get('topxia.target_helper')->getTargets(array($testpaper['target']));

	            $course = $this->controller->getCourseService()->getCourse($targets[$testpaper['target']]['id']);

	            if ($this->getTestpaperService()->isExistsEssay($testResults)) {
	            	$userUrl = $this->controller->generateUrl('user_show', array('id'=>$user['id']), true);
                		$teacherCheckUrl = $this->controller->generateUrl('course_manage_test_teacher_check', array('id'=>$testpaperResult['id']), true);

		            foreach ($course['teacherIds'] as $receiverId) {
		                $result = $this->getNotificationService()->notify($receiverId, 'default', "【试卷已完成】 <a href='{$userUrl}' target='_blank'>{$user['nickname']}</a> 刚刚完成了 {$testpaperResult['paperName']} ，<a href='{$teacherCheckUrl}' target='_blank'>请点击批阅</a>");
		            }
	            }

	            // @todo refactor. , wellming
	            $targets = $this->controller->get('topxia.target_helper')->getTargets(array($testpaperResult['target']));

	            if ($targets[$testpaperResult['target']]['type'] == 'lesson' && !empty($targets[$testpaperResult['target']]['id'])) {
	                $lessons = $this->controller->getCourseService()->findLessonsByIds(array($targets[$testpaperResult['target']]['id']));
	                if (!empty($lessons[$targets[$testpaperResult['target']]['id']])) {
	                    $lesson = $lessons[$targets[$testpaperResult['target']]['id']];
	                    $this->controller->getCourseService()->finishLearnLesson($lesson['courseId'], $lesson['id']);
	                }
	            }

	            return true;
	}

	public function showTestpaper()
	{
		$id = $this->getParam("id");
		$user = $this->controller->getUserByToken($this->request);
                        if (!$user->isLogin()) {
                            return $this->createErrorResponse('not_login', '您尚未登录，不能查看该课时');
                        }

                        $testpaperResult = $this->getTestpaperService()->getTestpaperResult($id);
                        if (!$testpaperResult) {
                            return $this->createErrorResponse('error', '试卷不存在');
                        }

                        $testpaper = $this->getTestpaperService()->getTestpaper($testpaperResult['testId']);

        		$result = $this->getTestpaperService()->showTestpaper($id);
        		$items = $result['formatItems'];

        		return array(
            		    'testpaperResult'=>$testpaperResult,
	                            'testpaper'=>$testpaper,
	                            'items'=>$this->getTestpaperItem($testpaperResult)
	                            );
	}

	public function doTestpaper()
	{
		$testId = $this->getParam("testId");
		$targetType = $this->getParam("targetType");
		$targetId= $this->getParam("targetId");
		$user = $this->controller->getUserByToken($this->request);
                        if (!$user->isLogin()) {
                            return $this->createErrorResponse('not_login', '您尚未登录，不能查看该课时');
                        }

                        $testpaper = $this->getTestpaperService()->getTestpaper($testId);

	        	$targets = $this->controller->get('topxia.target_helper')->getTargets(array($testpaper['target']));
	        	if ($targets[$testpaper['target']]['type'] != 'course') {
                        	return $this->createErrorResponse('error', '试卷只能属于课程');
	        	}

	        	$course = $this->getCourseService()->getCourse($targets[$testpaper['target']]['id']);

	        	if (empty($course)) {
                        	return $this->createErrorResponse('error', '试卷所属课程不存在！');
	        	}

	        	if (!$this->getCourseService()->canTakeCourse($course)) {
                        	return $this->createErrorResponse('error', '不是试卷所属课程老师或学生');
	        	}

	        	if (empty($testpaper)) {
                        	return $this->createErrorResponse('error', '试卷不存在！或已删除!');
	        	}

	        	$testpaperResult = $this->getTestpaperService()->findTestpaperResultByTestpaperIdAndUserIdAndActive($testId, $user['id']);

        		if (empty($testpaperResult)) {
	            	if ($testpaper['status'] == 'draft') {
	                        	return $this->createErrorResponse('error', '该试卷未发布，如有疑问请联系老师！!');
	            	}
	            	if ($testpaper['status'] == 'closed') {
	                        	return $this->createErrorResponse('error', '该试卷已关闭，如有疑问请联系老师！!');
	            	}

	            	$testpaperResult = $this->getTestpaperService()->startTestpaper($testId, array('type' => $targetType, 'id' => $targetId));
            		return array(
            		    'testpaperResult'=>$testpaperResult,
	                            'testpaper'=>$testpaper,
	                            'items'=>$this->getTestpaperItem($testpaperResult)
	                            );
            	}
            	if (in_array($testpaperResult['status'], array('doing', 'paused'))) {
            		return array(
            		    'testpaperResult'=>$testpaperResult,
	                            'testpaper'=>$testpaper,
	                            'items'=>$this->getTestpaperItem($testpaperResult)
	                            );
	        	} else {
	            	return $this->createErrorResponse('error', '试卷正在批阅！不能重新考试!');
	        	}
	}

	public function getTestpaperResult()
	{
	        $answerShowMode = $this->controller->setting('questions.testpaper_answers_show_mode','submitted');

	        // 不显示题目
	        if ($answerShowMode == "hide" ) {
	        	return $this->createErrorResponse('error', '网校已关闭交卷后答案解析的显示!');
	        }

	        $id = $this->getParam("id");
	        $user = $this->controller->getUserByToken($this->request);
	        $testpaperResult = $this->getTestpaperService()->getTestpaperResult($id);
	        if (!$testpaperResult) {
	            return $this->createErrorResponse('error', '不可以访问其他学生的试卷哦!');
	        }

	        //客观题自动批阅完后先显示答案解析
	        if ($answerShowMode == "reviewed" && $testpaperResult["status"] != "finished") {
	        	return $this->createErrorResponse('error', '试卷正在批阅，需要批阅完后才能显示答案解析!');
	        }

	        $testpaper = $this->getTestpaperService()->getTestpaper($testpaperResult['testId']);

	        $targets = $this->controller->get('topxia.target_helper')->getTargets(array($testpaper['target']));
	       
	        if ($testpaperResult['userId'] != $user['id']){
	            $course = $this->controller->getCourseService()->tryManageCourse($targets[$testpaper['target']]['id']);
	        }

	        if (empty($course) && $testpaperResult['userId'] != $user['id']){
	                        return $this->createErrorResponse('error', '不可以访问其他学生的试卷哦!');
	        }

	        $result = $this->getTestpaperService()->showTestpaper($id, true);
	        $items = $result['formatItems'];
	        $accuracy = $result['accuracy'];

	        $favorites = $this->getQuestionService()->findAllFavoriteQuestionsByUserId($testpaperResult['userId']);
	        return array(
	        	"testpaper"=>$testpaper,
	        	"items"=>$this->filterResultItems($items, true),
	        	"accuracy"=>$accuracy,
            	'paperResult' => $testpaperResult,
		'favorites' => ArrayToolkit::column($favorites, 'questionId')
	        	);
	}

	private function filterResultItems($items, $isShowTestResult)
	{
		$controller = $this;
		$newItems = array_map(function($item){
			return array_values($item);
		}, $items);

		return $this->coverTestpaperItems($newItems, $isShowTestResult);
	}

	private function getTestpaperItem($testpaperResult, $isShowTestResult = false)
	{
		$result = $this->getTestpaperService()->showTestpaper($testpaperResult['id']);
        		$items = $result['formatItems'];

        		return $this->coverTestpaperItems($items, $isShowTestResult);
	}

	public function filterQuestionStem($stem)
	{
		$ext = $this;
		$baseUrl = $this->request->getSchemeAndHttpHost();
        		$stem = preg_replace_callback('/\[image\](.*?)\[\/image\]/i', function($matches) use ($baseUrl, $ext) {
			$url = $ext->controller->get('topxia.twig.web_extension')->getFileUrl($matches[1]);
			$url = $baseUrl . $url;
            		return "<img src='{$url}' />";
       		 }, $stem);

        		return $stem;
	}

	private function coverTestpaperItems($items, $isShowTestResult)
	{
		$controller = $this;
		$result = array_map(function($item) use ($controller, $isShowTestResult){
			$item = array_map(function($itemValue) use ($controller, $isShowTestResult){
				$question = $itemValue['question'];
				
				if (isset($question['isDeleted']) && $question['isDeleted'] == true) {
					return null;
				}
				if (isset($itemValue['items'])) {
					$filterItems = array_values($itemValue['items']);
					$itemValue['items'] = array_map(function($filterItem)use ($controller, $isShowTestResult){
						return $controller->filterMetas($filterItem, $isShowTestResult);
					}, $filterItems);
				}

				$itemValue = $controller->filterMetas($itemValue, $isShowTestResult);
				return $itemValue;
				
			}, $item);
			if ($controller->arrayIsEmpty($item)) {
				return;
			}
			return array_values($item);
		}, $items);
		foreach ($result as $key => $value) {
			if (empty($value)) {
				unset($result[$key]);
			}
		}
		return $result;
	}

	public function arrayIsEmpty($array)
	{
		foreach ($array as $key => $value) {
			if (!empty($value)) {
				return false;
			}
		}

		return true;
	}

	public function filterMetas($itemValue, $isShowTestResult)
	{
		$question = $itemValue['question'];
		$question['stem'] = $this->filterQuestionStem($question['stem']);
		if (!$isShowTestResult && isset($question['testResult'])) {
			unset($question['testResult']);
		}
		$itemValue['question'] = $question;
		if (isset($question['metas'])) {
			$metas= $question['metas'];
			if (isset($metas['choices'])) {
				$metas= array_values($metas['choices']);
				$itemValue['question']['metas'] = $metas;
			}
		}

		$answer = $question['answer'];
		if (is_array($answer)) {
			$itemValue['question']['answer'] = array_map(function($answerValue){
				if (is_array($answerValue)) {
					return implode('|', $answerValue);
				}
				return $answerValue;
			}, $answer);
			return $itemValue;
		}
		
		return $itemValue;
	}
}