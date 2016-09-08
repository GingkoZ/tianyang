<?php
namespace Custom\Service\Courseware\Impl;

use Topxia\Service\Common\BaseService;
use Custom\Service\Courseware\CoursewareService;
use Topxia\Service\Course\CourseService;
use Topxia\Common\ArrayToolkit;


class CoursewareServiceImpl extends BaseService implements CoursewareService
{

	public function getCourseware($id)
	{
		return $this->getCoursewareDao()->getCourseware($id);
	}

	public function createMediaLessonExercise($lessonId,$questionId,$showtime)
	{
		if(!$lessonId && !$questionId && !$showtime)
		{
			throw $this->createServiceException('参数缺失，创建练习失败！');
		}

        $createdTime = time();

       	return $this->getMediaLessonExerciseDao()->add($lessonId,$questionId,$showtime,$createdTime);
    }

    public function searchMediaLessonExerciseCount($fields)
	{
	    if (!ArrayToolkit::requireds($fields, array('lessonId','showtime'))) {
           	throw $this->createServiceException('参数缺失，创建练习失败！');
        }

        return $this->getMediaLessonExerciseDao()->searchMediaLessonExerciseCount($fields);
	}

	public function findMediaLessonExercisesByLessonId($lessonId)
	{
		return ArrayToolkit::index($this->getMediaLessonExerciseDao()->findByLessonId($lessonId), 'id');

	}

	public function deleteMediaLessonExerciseById($id)
	{
		return $this->getMediaLessonExerciseDao()->delete($id);

	}

	public function findQuestions($fields)
	{

		return $this->getQuestionsDao()->findQuestions($fields);
	}

	public function findMediaLessonExercises($lessonId,$showtime)
	{

		return $this->getMediaLessonExerciseDao()->findMediaLessonExercises($lessonId,$showtime);
		
	}

	public function findMediaLessonExerciseAction($lessonId)
	{
		return  ArrayToolkit::column($this->getMediaLessonExerciseDao()->findByLessonId($lessonId), 'showtime');
	}


	
	private function hasCourseManagerRole($courseId, $userId) 
	{
		if($this->getUserService()->hasAdminRoles($userId)){
			return true;
		}

		$member = $this->getMemberDao()->getMemberByCourseIdAndUserId($courseId, $userId);
		if ($member and ($member['role'] == 'teacher')) {
			return true;
		}

		return false;
	}

	private function getMemberDao ()
	{
    		
    		return $this->createDao('Course.CourseMemberDao');
	}

	private function getCoursewareDao ()
	{
   		 return $this->createDao('Custom:Courseware.CoursewareDao');
	}

   	 private function getUserService()
	{

		return $this->createService('User.UserService');
	}

	private function getMediaLessonExerciseDao()
	{

		return $this->createDao('Custom:Courseware.MediaLessonExerciseDao');
	}

	private function getQuestionsDao()
	{
		return $this->createDao('Custom:Courseware.QuestionsDao');

	}


}

