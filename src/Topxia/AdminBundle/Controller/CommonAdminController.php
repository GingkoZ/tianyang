<?php
namespace Topxia\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\Paginator;

class CommonAdminController extends BaseController
{
    public function addCommonAdminAction(Request $request)
    {
        $data = $request->request->all();

        $user = $this->getCurrentUser();

        $admin['url'] = $data['url'];
        $admin['title'] = $data['title'];
        $admin['userId'] = $user['id'];

        if ($this->getUserCommonAdminService()->getCommonAdminByUserIdAndUrl($user['id'],$admin['url'])) {

            return $this->createJsonResponse('error');
        }

        $admin = $this->getUserCommonAdminService()->addCommonAdmin($admin);

        return $this->render('TopxiaAdminBundle:CommonAdmin:li.html.twig',array(
            'admin'=>$admin));
    }

    public function commonAdminAction(Request $request)
    {   
        $user = $this->getCurrentUser();
        $url = $_SERVER['REQUEST_URI'];
     
        $isCollect = 0;

        if ($this->getUserCommonAdminService()->getCommonAdminByUserIdAndUrl($user['id'],$url)) {

            $isCollect = 1;
        }

        $admins = $this->getUserCommonAdminService()->findCommonAdminByUserId($user['id']);

        return $this->render('TopxiaAdminBundle:CommonAdmin:main.html.twig',array(
            'admins'=>$admins,
            'isCollect'=>$isCollect));
    }

    public function commonRemoveAction(Request $request,$id)
    {
        $url = $request->request->get('url');
        $this->getUserCommonAdminService()->deleteCommonAdmin($id);

        $user = $this->getCurrentUser();
   
        $isCollect = 0;
     
        if ($this->getUserCommonAdminService()->getCommonAdminByUserIdAndUrl($user['id'],$url)) {

            $isCollect = 1;
        }

        $admins = $this->getUserCommonAdminService()->findCommonAdminByUserId($user['id']);

        return $this->render('TopxiaAdminBundle:CommonAdmin:main.html.twig',array(
            'admins'=>$admins,
            'isCollect'=>$isCollect));
        
    }

    protected function getUserCommonAdminService()
    {
        return $this->getServiceKernel()->createService('User.UserCommonAdminService');
    }

}