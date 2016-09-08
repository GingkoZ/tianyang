<?php
namespace Custom\AdminBundle\Controller;

use Topxia\WebBundle\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\Paginator;
use Symfony\Component\HttpFoundation\JsonResponse;
class CourseMatchController extends BaseController
{
	public function matchAction(Request $request)
    {
        $data = array();
        $queryString = $request->query->get('q');

		/**
		 * Add by royakon for course match by id at 20160121
		 */
		$course = $this->getCustomCourseService()->findCourseById($queryString);
		if ($course != null) {
			$data[] = array('id' => $course['id'], 'name' => $course['title'] );
		}   
    
        $courses = $this->getCustomCourseService()->findCoursesByNameLike($queryString);
        foreach ($courses as $course) {
            $data[] = array('id' => $course['id'],  'name' => $course['title'] );
        }
        return new JsonResponse($data);
    }


    private function getCustomCourseService()
    {
        return $this->getServiceKernel()->createService('Custom:Course.CustomCourseService');
    }


}
