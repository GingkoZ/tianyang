<?php
namespace Custom\WebBundle\Controller;

use Topxia\WebBundle\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Topxia\Common\ArrayToolkit;

class SettingsController extends BaseController
{
    public function profileAction(Request $request)
    {
        $user = $this->getCurrentUser();

        $profile = $this->getUserService()->getUserProfile($user['id']);
        $organization = $this->getOrganizationService()->getOrganizationById($user['tyjh_organizationId']);
        $profile['title'] = $user['title'];

        if ($request->getMethod() == 'POST') {
            $profile = $request->request->get('profile');
            $organizationId = $request->request->get('organizationId');
            $this->getUserService()->updateUserProfile($user['id'], $profile);
            $this->getUserService()->changeOrganizationId($user['id'],$organizationId);
            $this->setFlashMessage('success', '基础信息保存成功。');
            return $this->redirect($this->generateUrl('settings'));

        }

        $fields=$this->getUserFieldService()->getAllFieldsOrderBySeqAndEnabled();
        for($i=0;$i<count($fields);$i++){
            if(strstr($fields[$i]['fieldName'], "textField")) $fields[$i]['type']="text";
            if(strstr($fields[$i]['fieldName'], "varcharField")) $fields[$i]['type']="varchar";
            if(strstr($fields[$i]['fieldName'], "intField")) $fields[$i]['type']="int";
            if(strstr($fields[$i]['fieldName'], "floatField")) $fields[$i]['type']="float";
            if(strstr($fields[$i]['fieldName'], "dateField")) $fields[$i]['type']="date";
        }
        
        if (array_key_exists('idcard',$profile) && $profile['idcard']=="0") {
            $profile['idcard'] = "";
        }

        $fromCourse = $request->query->get('fromCourse');
        
        return $this->render('CustomWebBundle:Settings:profile.html.twig', array(
            'profile' => $profile,
            'fields'=>$fields,
            'fromCourse' => $fromCourse,
            'organization' => $organization
        ));
    }


    protected function getUserFieldService()
    {
        return $this->getServiceKernel()->createService('User.UserFieldService');
    }

    private function getOrganizationService()
    {
        return $this->getServiceKernel()->createService('Custom:Organization.OrganizationService');
    }

    protected function getUserService()
    {
        return $this->getServiceKernel()->createService('Custom:User.UserService');
    }
}