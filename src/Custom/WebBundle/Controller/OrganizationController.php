<?php
namespace Custom\WebBundle\Controller;

use Topxia\WebBundle\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\Paginator;
class OrganizationController extends BaseController
{

    public function getNodesAction(Request $request)
    {
        $query = $request->request->all();
        $parentId = empty($query['id']) ? 0 : $query['id'];
        $organizations = $this->getOrganizationService()->findNodesDataByParentId($parentId);
     
        return $this->createJsonResponse($organizations);
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


 



    private function getOrganizationService()
    {
        return $this->getServiceKernel()->createService('Custom:Organization.OrganizationService');
    }


}