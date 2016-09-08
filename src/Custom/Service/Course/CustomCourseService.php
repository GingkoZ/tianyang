<?php
namespace Custom\Service\Course;

interface CustomCourseService
{
	/**
	 * Add by royakon for course match by id at 20160121
	 */
	public function findCourseById($id);
   
    public function findCoursesByNameLike($name);


    public function findUsersLearnDataByOrgIdAndCourseId($orgId,$courseId,$startTime,$endTime);


    public function getTotalTimeByCourseId($courseId);


    public function findLearnedStudentsCountByCourseIdAndOrgId($courseId,$orgId,$startTime,$endTime);

    public function getCourseDetailByCourseId($courseId);
}
