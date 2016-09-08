<?php
namespace Custom\AdminBundle\Controller;

use Topxia\WebBundle\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\Paginator;
class OrganizationController extends BaseController
{
	public function indexAction(Request $request)
    {
        return $this->render('CustomAdminBundle:Organization:index.html.twig', array(
            
        ));
    }


    public function selectModalAction(Request $request)
    {
        return $this->render('CustomAdminBundle:Organization:organization-check-modal.html.twig', array(
            
        ));
    }
    



    public function getNodesByUseIdAction(Request $request)
    {
        
        $nodId = $request->request->get('id');

        if(empty($nodId)){
            $query = $request->query->all();
            $userId = empty($query['userId']) ? 0 : $query['userId'];
            $organizations = $this->getOrganizationService()->getNodesByUseId($userId);
            return $this->createJsonResponse($organizations);
        }else{
            $organizations = $this->getOrganizationService()->findNodesDataByParentId($nodId);
            return $this->createJsonResponse($organizations);
        }
        
       
        
       
    }
    public function createAction(Request $request)
    {
        if ($request->getMethod() == 'POST') {
            $fields =  $request->request->all();
            $organization = $this->getOrganizationService()->createOrganization($fields);
            $result = array(
                'tid' => $fields['tid'],
                'organization' => $organization
            );
            return $this->createJsonResponse($result);
        }


        $query =  $request->query->all();
        $organization = array(
            'id' => 0,
            'name' => '',
            'code' => '',
            'description'=>'',
            'weight' => 0
        );
        
        if(empty($query['pid'])) {
            $organization['parentId'] = 0;
            $organization['tid'] = null;
        } else {
            $organization['parentId'] =  $query['pid'];
            $organization['tid'] = $query['tid'];
        }

        // if(empty($query['seq'])) {
        //     $knowledge['sequence'] = count($this->getKnowledgeService()->findKnowledgeByCategoryIdAndParentId($categoryId, 0)) +1;
        // } else {
            // $organization['sequence'] = 100;
        // }

        return $this->render('CustomAdminBundle:Organization:modal.html.twig', array(
            'organization' => $organization,
        ));
    }

    public function editAction(Request $request)
    {
        $query = $request->query->all();
        if ($request->getMethod() == 'POST') {
            $fields =  $request->request->all();
            $id = $fields['id'];
            $organization = $this->getOrganizationService()->updateOrganization($id, $fields);
            $result = array(
                'tid' => $fields['tid'],
                'type' => 'edit',
                'organization' => $organization,
            );
            return $this->createJsonResponse($result);
        }

        $organization = $this->getOrganizationService()->getOrganizationById($query['id']);
        if(empty($organization)) {
            $organization = array(
                'id' => 0,
                'name' => '',
                'code' => '',
                'description'=>'',
                'parentId' => 0,
                'weight' => 0
            );
        }
        $organization['tid'] = $query['tid'];
        return $this->render('CustomAdminBundle:Organization:modal.html.twig', array(
            'organization' => $organization,
        ));
    }

    public function deleteAction(Request $request)
    {
        $id = $request->request->get('id');
        $organization = $this->getOrganizationService()->getOrganizationById($id);
        if(empty($organization)){
            return $this->createJsonResponse(array("false"=>"要删除的组织机构不存在"));
        }
        $deleteCount = $this->getOrganizationService()->deleteOrganization($id);
        return $this->createJsonResponse($deleteCount);
    }

    public function userListAction(Request $request){
       $query =  $request->query->all();
       $orgId = $query['orgId'];
       $leave = $query["leave"];
       $orgIds = array();
       if($leave == 'self'){
            $orgIds[] = $orgId;

       }
       if($leave == 'all'){
            $allOrgs = $this->getOrganizationService()->findAllChildsByParentId($orgId,array());
            $orgIds = ArrayToolkit::column($allOrgs,"id");
            $orgIds[] = $orgId;
       }

       $paginator = new Paginator(
            $this->get('request'),
            count($this->getOrganizationService()->findUserIdsByOrgIds($orgIds)),
            20
        );

        $users = $this->getOrganizationService()->findUsersByOrgIds(
            $orgIds,
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );
      
      

       $profiles =  $this->getUserService()->findUserProfilesByIds(ArrayToolkit::column($users,'id'));
      
        return $this->render('CustomAdminBundle:User:user-list-modal.html.twig', array(
            'users' => $users,
            'profiles' => $profiles,
            'paginator' => $paginator
        ));

    }

    private function getOrganizationService()
    {
        return $this->getServiceKernel()->createService('Custom:Organization.OrganizationService');
    }

    // private function getUserService()
    // {
    //     return $this->getServiceKernel()->createService('User.UserService');
    // }


}