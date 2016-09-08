<?php 
namespace Custom\AdminBundle\Controller;
use Topxia\WebBundle\Controller\BaseController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\Paginator;

class UserController extends BaseController 
{

    public function indexAction (Request $request)
    {
        $fields = $request->query->all();
        $fields = $this->fillConditions($fields);
        $conditions = array(
            'roles'=>'',
            'keywordType'=>'',
            'keyword'=>''
        );

        if(!empty($fields)){
            $conditions =$fields;
        }

        $paginator = new Paginator(
            $this->get('request'),
            $this->getUserService()->searchUserCount($conditions),
            20
        );

        $users = $this->getUserService()->searchUsers(
            $conditions,
            array('createdTime', 'DESC'),
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $profiles = $this->getUserService()->findUserProfilesByIds(ArrayToolkit::column($users,'id'));
        $organizations = $this->getOrganizationService()->findOrganizationsByIds(ArrayToolkit::column($users,'tyjh_organizationId'));
        $app = $this->getAppService()->findInstallApp("UserImporter");
        $enabled = $this->getSettingService()->get('plugin_userImporter_enabled');
        
        $showUserExport = false;
        if(!empty($app) && array_key_exists('version', $app) && $enabled){
            $showUserExport = version_compare($app['version'], "1.0.2", ">=");
        }


        $userKeyWordType = array(
            'organizationName' => '组织名称',
            'nickname' => '用户名',
            'email' => '邮件地址',
            'loginIp' => '登录IP',
            'truename' => '姓名'
        );

        return $this->render('CustomAdminBundle:User:index.html.twig', array(
            'users' => $users ,
            'paginator' => $paginator,
            'showUserExport' => $showUserExport,
            'userKeyWordType' => $userKeyWordType,
            'profiles' =>$profiles,
            'organizations'=>$organizations
        ));
    }


    public function editAction(Request $request, $id)
    {
        $user = $this->getUserService()->getUser($id);

        $profile = $this->getUserService()->getUserProfile($user['id']);
        $profile['title'] = $user['title'];
        $organization = $this->getOrganizationService()->getOrganizationById($user['tyjh_organizationId']);
        if ($request->getMethod() == 'POST') {
            $profile = $this->getUserService()->updateUserProfile($user['id'], $request->request->all());
            $organizationId = $request->request->get('organizationId');
            $this->getUserService()->changeOrganizationId($id,$organizationId);
            $this->getLogService()->info('user', 'edit', "管理员编辑用户资料 {$user['nickname']} (#{$user['id']})", $profile);
            return $this->redirect($this->generateUrl('settings'));
        }

        $fields=$this->getFields();

        return $this->render('CustomAdminBundle:User:edit-modal.html.twig', array(
            'user' => $user,
            'profile'=>$profile,
            'fields'=>$fields,
            'organization'=>$organization
        ));
    }

    public function showAction(Request $request, $id)
    {
        $user = $this->getUserService()->getUser($id);
        $profile = $this->getUserService()->getUserProfile($id);
        $profile['title'] = $user['title'];
        $organization = $this->getOrganizationService()->getOrganizationById($user['tyjh_organizationId']);
        $fields=$this->getFields();
            
        return $this->render('CustomAdminBundle:User:show-modal.html.twig', array(
            'user' => $user,
            'profile' => $profile,
            'fields'=>$fields,
            'organization'=>$organization
        ));
    }
    
    private function fillConditions($fields)
    {
        if(empty($fields) || !array_key_exists('keywordType', $fields)){
            return $fields;
        }

        if($fields['keywordType']=='organizationName'){
            $organizations = $this->getOrganizationService()->searchOrganizations(
                array('name'=>$fields['keyword']),
                array('id', 'DESC'),
                0,
                PHP_INT_MAX
            );
            $fields['tyjh_organizationIds'] = ArrayToolkit::column($organizations,'id');
        }

        if($fields['keywordType']=='truename' && !trim($fields['keyword'])==''){
            $profiles = $this->getUserService()->findProfilesByTruename($fields['keyword']);
            $fields['ids'] = ArrayToolkit::column($profiles,'id');
        }

        return $fields;
    }

    private function getFields()
    {
        $fields=$this->getUserFieldService()->getAllFieldsOrderBySeqAndEnabled();
        for($i=0;$i<count($fields);$i++){
            if(strstr($fields[$i]['fieldName'], "textField")) $fields[$i]['type']="text";
            if(strstr($fields[$i]['fieldName'], "varcharField")) $fields[$i]['type']="varchar";
            if(strstr($fields[$i]['fieldName'], "intField")) $fields[$i]['type']="int";
            if(strstr($fields[$i]['fieldName'], "floatField")) $fields[$i]['type']="float";
            if(strstr($fields[$i]['fieldName'], "dateField")) $fields[$i]['type']="date";
        }

        return $fields;
    }

    protected function getAppService()
    {
        return $this->getServiceKernel()->createService('CloudPlatform.AppService');
    }

    protected function getUserFieldService()
    {
        return $this->getServiceKernel()->createService('User.UserFieldService');
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

    protected function getUserService()
    {
        return $this->getServiceKernel()->createService('Custom:User.UserService');
    }

    private function getOrganizationService()
    {
        return $this->getServiceKernel()->createService('Custom:Organization.OrganizationService');
    }

}