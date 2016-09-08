<?php
namespace Topxia\WebBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\Paginator;

class TeacherController extends BaseController
{
    public function indexAction()
    {

        $conditions = array(
            'roles'=>'ROLE_TEACHER',
            'locked'=>0
        );

        $paginator = new Paginator(
            $this->get('request'),
            $this->getUserService()->searchUserCount($conditions),
            20
        );

        $teachers = $this->getUserService()->searchUsers(
            $conditions,
            array('promotedTime', 'DESC'),
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $user = $this->getCurrentUser();
        $teacherIds = ArrayToolkit::column($teachers, 'id');
        $profiles = $this->getUserService()->findUserProfilesByIds($teacherIds);
        $myFollowings = $this->getUserService()->filterFollowingIds($user['id'], $teacherIds);
        return $this->render('TopxiaWebBundle:Teacher:index.html.twig', array(
            'teachers' => $teachers ,
            'profiles' => $profiles,
            'paginator' => $paginator,
            'Myfollowings' => $myFollowings,
        ));
    }
    
	public function searchAction($request, $keyword) {
		$conditions = array(
			'roles' => 'ROLE_TEACHER',
			'locked' => 0 
		);
		
		if (!empty($keyword)) {
			$conditions['nickname'] = $keyword;
		}
		
		$teachers = $this->getUserService()->searchUsers($conditions, array(
            'nickname',
            'ASC'
		), 0, 1000);
		
		return $this->createJsonResponse($teachers);
	}
}