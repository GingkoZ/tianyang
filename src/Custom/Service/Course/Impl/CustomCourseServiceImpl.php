<?php
namespace Custom\Service\Course\Impl;

use Custom\Service\Course\CustomCourseService;
use Topxia\Service\Common\BaseService;
use Topxia\Common\ArrayToolkit;


class CustomCourseServiceImpl extends BaseService implements CustomCourseService
{
	/**
	 * Add by royakon for Course match by id at 20160121
	 */
	public function findCourseById($id){
		return $this->getCustomCourseDao()->findCourseById($id);
	}

	public function findCoursesByNameLike($name){
		 return $this->getCustomCourseDao()->findCoursesByNameLike($name);
	}

	public function getTotalTimeByCourseId($courseId){
		return $this->getCustomCourseDao()->getTotalTimeByCourseId($courseId);
	}

	public function findUsersLearnDataByOrgIdAndCourseId($orgId,$courseId,$startTime,$endTime){
		$list = $this ->getCustomCourseDao()->findUsersLearnDataByOrgIdAndCourseId($orgId,$courseId,$startTime,$endTime);
		
		$totalLessonCount = $this->getCourseService()->searchLessonCount(array("courseId"=>$courseId));
		


		$organizationFullPath = $this->getOrganizationService()->getOrganizationFullPathByOrgId($orgId);
		foreach ($list as $key => $value) {
			$user = $this->getUserService()->getUserProfile($value['id']);
			$list[$key]['truename'] = $user['truename'];
			$list[$key]['mobile'] = $user['mobile'];
			$list[$key]['learnLessonRate'] = $totalLessonCount == 0  ?  0 : (round($value['learnLessonCount'] / $totalLessonCount,2)*100) ."%";
			

			$list[$key]['organizationFullPath'] = $organizationFullPath;
		}
		return $list;
	}

	 public function getCourseDetailByCourseId($courseId){
	 	$course = $this-> getCourseService()->getCourse($courseId);
	 	$lessons = $this->getCourseService()->getCourseLessons($courseId);
	 	$course['lessons'] = $lessons;
	 	return $course;
	 }


	public function findLearnedStudentsCountByCourseIdAndOrgId($courseId,$orgId,$startTime,$endTime){
		$userIds = $this->getCustomCourseDao()->findLearnedStudentsByCourseIdAndOrgId($courseId,$orgId);
		$finshedTime = $this->getCustomCourseDao()->getfinishedTimeByCourseIdAndUserIds($courseId,$userIds);
		
		$resutlCoutn = 0;
		foreach ($finshedTime as $key => $value) {
			
			if(!empty($startTime) and empty($endTime)){
				
				if(intval($value['finishedTime']) >= $startTime){
					
					$resutlCoutn ++;
				}
			}
			else if(!empty($endTime) and empty($startTime)){
				if(intval($value['finishedTime']) <= $endTime){
					$resutlCoutn ++;
				}
			}else if (!empty($endTime) and !empty($startTime)){
				if(intval($value['finishedTime']) <= $endTime and $value['finishedTime'] >= $startTime){
					$resutlCoutn ++;
				}
			}else{
				$resutlCoutn ++;
			}
		}
	
		
		return $resutlCoutn;
	}


	
    protected function getCustomCourseDao()
    {
        return $this->createDao('Custom:Course.CustomCourseDao');
    }

    protected function getOrganizationService()
    {
        return $this->createService('Custom:Organization.OrganizationService');
    }

    protected function getUserService()
    {
        return $this->createService('User.UserService');
    }
    protected function getCourseService()
    {
        return $this->createService('Course.CourseService');
    }

   

}
