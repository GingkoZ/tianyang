<?php
namespace Custom\Service\Course\Dao\Impl;
use Topxia\Service\Common\BaseDao;
use Custom\Service\Course\Dao\CustomCourseDao;
      
class CustomCourseDaoImpl extends BaseDao implements CustomCourseDao 
{

    protected $table = 'course';
    protected $course_lesson = 'course_lesson';
	
	/**
	 * Add by royakon for course match by id at 20160121
	 */
	public function findCourseById($id)
	{
		$sql = "SELECT * FROM {$this->table} WHERE id = ? and status != 'draft' and type != 'live' LIMIT 1";
		return $this->getConnection()->fetchAssoc($sql, array($id)) ?: null;
	}	

    public function findCoursesByNameLike($name)
    {
    	$name = "%{$name}%";
        $sql = "SELECT * FROM {$this->table} WHERE title LIKE ? and status != 'draft' and type != 'live'";
        return $this->getConnection()->fetchAll($sql, array($name));
    }
    
    public function findUsersLearnDataByOrgIdAndCourseId($orgId,$courseId,$startTime,$endTime){
    	
     // userId , case when status = 'finished' then 1 else 0 END finishedCount
    	$lesson_learn_sql ="select userId ,watchTime, startTime ,case when status = 'finished' then 1 else 0 END finishedCount  from course_lesson_learn where   courseId=".$courseId  ;
    	
    	if(!empty($startTime)){
    		$lesson_learn_sql  = $lesson_learn_sql.  " and startTime >= ".$startTime;
    	
    	}
    	if(!empty($endTime)){
    		$lesson_learn_sql  = $lesson_learn_sql. " and startTime <=  ".$endTime; 
    	}


    	$learnDataSql = "select d.userId,d.startTime startTime,sum(d.watchTime) watchTime,sum(d.finishedCount) learnLessonCount from (".$lesson_learn_sql.") d group by d.userId";

    	$strutsSql = "select u.id,u.email,u.nickname ,IFNULL(watchTime,0) watchTime,IFNULL(da.learnLessonCount,0) learnLessonCount, startTime from user u 
    	left join (".$learnDataSql.") da 
    	on u.id = da.userId 
    	where u.tyjh_organizationId = ".$orgId;

    	return $this->getConnection()->fetchAll($strutsSql, array());
    	
    }

	public function getTotalTimeByCourseId($courseId){
		$sql = "SELECT sum(length) FROM {$this->course_lesson}  where courseId= ?";
		return $this->getConnection()->fetchColumn($sql, array($courseId)); 

	}

	public function findLearnedStudentsByCourseIdAndOrgId($courseId,$orgId){
	
		$sql = "SELECT id from user where tyjh_organizationId =".$orgId." AND id IN (SELECT userId FROM course_member WHERE isLearned = 1 and  courseId = ".$courseId.") ";
		return $this->getConnection()->fetchColumn($sql, array()); 
	}

	public function getfinishedTimeByCourseIdAndUserIds($courseId,$userIds){
		$marks = str_repeat('?,', count($userIds) - 1) . '?';
		
		$sql = "SELECT userId,max(finishedTime) finishedTime FROM course_lesson_learn 
		where courseId=".$courseId." and userId in ({$marks}) group by userId";
		
		return $this->getConnection()->fetchAll($sql, array($userIds));
	}
    

  
}
