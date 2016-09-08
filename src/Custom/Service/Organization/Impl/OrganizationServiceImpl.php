<?php
namespace Custom\Service\Organization\Impl;

use Custom\Service\Organization\OrganizationService;
use Topxia\Service\Common\BaseService;
use Topxia\Common\ArrayToolkit;


class OrganizationServiceImpl extends BaseService implements OrganizationService
{
    public function  findNodesDataByParentId($parentId){

        $organizations = $this->getOrganizationDao()->findNodesDataByParentId($parentId);


        foreach ($organizations as $key => $organization) {
            $nextLevelChilds = $this->findNodesDataByParentId($organization['id']);
            if(count($nextLevelChilds)>0) {
                $organization['isParent'] = true;
            } else {
                $organization['isParent'] = false;
            }

            $organization['self_level_users'] = count($this->findUserIdsByOrgId($organization['id']));

            $allChilds = $this->findAllChildsByParentId($organization['id'],array());
            
          
            $organization['all_childs'] = count($allChilds);
            $allOrgIds = ArrayToolkit::column($allChilds,"id"); 

            $organization['all_users'] =  $organization['self_level_users'] + count($this->findUserIdsByOrgIds($allOrgIds));

           
            $organizations[$key] = $organization;
        }
    
        return  $organizations;

    }

    public function findAllChildsByParentId($parentId,$orgs){
		$organizations = $this->findNodesDataByParentId($parentId);
		foreach ($organizations as $key => $value) {
			$orgs[] = $value;
			$gradeChildren = $this->findNodesDataByParentId($value['id']);
			if(count($gradeChildren)){
				$orgs = $this->findAllChildsByParentId($value['id'],$orgs);
			}
		}
		return $orgs;
		
	}

    public function findOrganizationsByIds(array $ids)
    {
        if(empty($ids)){
            return array();
        }
        $organizations = $this->getOrganizationDao()->findOrganizationsByIds($ids);
        return ArrayToolkit::index($organizations,'id');
    }

    public function searchOrganizations(array $conditions, array $orderBy, $start, $limit)
    {
        $organizations = $this->getOrganizationDao()->searchOrganizations($conditions, $orderBy, $start, $limit);
        return ArrayToolkit::index($organizations,'id');
    }

    public function searchOrganizationCount(array $conditions)
    {
        return $this->getOrganizationDao()->searchOrganizationCount($conditions);
    }

     public function getNodesByUseId($userId){
        $useRootNode = $this->getRootNodeByUserId($userId); 
        if(count($this->findNodesDataByParentId($useRootNode['id']))>0) {
            $useRootNode['isParent'] = true;
        } else {
            $useRootNode['isParent'] = false;
        }
        return $useRootNode;
     }

     private function getRootNodeByUserId($userId){
        $nodeId =  $this->getOrganizationDao()->getNodeByUserId($userId);
       
        $organization = $this->getOrganizationById($nodeId['tyjh_organizationId']);


        while ($organization['parentId'] != 0) {
           $organization =  $this->getOrganizationById($organization['parentId']);
        }  

        return $organization;  
          
     }


    public function createOrganization($organization)
    {
        

        $organization = ArrayToolkit::parts($organization, array('description','name', 'weight',  'parentId','code'));

        // if (!ArrayToolkit::requireds($organization, array('name', 'code', 'weight', 'parentId'))) {
        //     throw $this->createServiceException("缺少必要参数，添加组织机构失败");
        // }

        // $this->filterKnowledgeFields($knowledge);
        $organization = $this->getOrganizationDao()->createOrganization($organization);

        $this->getLogService()->info('organization', 'create', "添加组织机构 {$organization['name']}(#{$organization['id']})", $organization);

        return $organization;
    }

     public function getOrganizationById($id){
        return $this->getOrganizationDao()->getOrganizationById($id);
     }

    public function updateOrganization($id, $fields)
    {
        if($id) {
            $organization = $this->getOrganizationById($id);
            if (empty($organization)) {
                throw $this->createNoteFoundException("组织机构(#{$id})不存在，操作失败！");
            }
        }

        $fields = ArrayToolkit::parts($fields, array('description','name', 'code', 'weight',  'parentId'));
        if (empty($fields)) {
            throw $this->createServiceException('参数不正确，更新组织机构失败！');
        }

        // filterknowledgeFields里有个判断，需要用到这个$fields['groupId']
        // $fields['categoryId'] = $knowledge['categoryId'];

        // $this->filterKnowledgeFields($fields, $knowledge);

        $this->getLogService()->info('organization', 'update', "编辑组织机构 {$organization['name']}(#{$id})", $fields);

        return $this->getOrganizationDao()->updateOrganization($id, $fields);
    }

     public function deleteOrganization($id){
        $organization = $this->getOrganizationById($id);
        if (empty($organization)) {
            throw $this->createNoteFoundException("组织机构(#{$id})不存在，操作失败！");
        }
        $childs = $this->findNodesDataByParentId($organization['id']);
        if(count($childs)){
             throw $this->createNoteFoundException("组织机构(#{$id})不是底层节点，操作失败！");
        }
        $this->getOrganizationDao()->deleteOrganization($id);
     }

     public function findUserIdsByOrgId($orgId){
       $organization =  $this->getOrganizationById($orgId);
       if(empty($organization)){
        return array();
       }
       return $this->getOrganizationDao()->findUserIdsByOrgId($orgId);
     }
    public function getOrganizationFullPathByOrgId($orgId){

     	$organization = $this->getOrganizationById($orgId);
     	
        if (empty($organization)) {
            return "";
        }
      
        $fullPath = $organization['name'];
       
        while ($organization['parentId'] != 0) {
           $organization =  $this->getOrganizationById($organization['parentId']);
           $fullPath = $organization['name'].">".$fullPath;
        }  
      
        return $fullPath;
    }


     public function findUserIdsByOrgIds($orgIds){
         return $this->getOrganizationDao()->findUserIdsByOrgIds($orgIds);
     }

    public function findUsersByOrgIds($orgIds,$start, $limit){
        $users = $this->getOrganizationDao()->findUsersByOrgIds($orgIds,$start, $limit);
      
        foreach ($users as $key => &$value) {
            $orgId = $value['tyjh_organizationId'];
            $value['organizationFullPath'] = $this->getOrganizationFullPathByOrgId($orgId);

        }
       
        return $users;

    }



    protected function getUserService(){
         return $this->createService('User.UserService');
    }
    protected function getOrganizationDao()
    {
        return $this->createDao('Custom:Organization.OrganizationDao');
    }

    protected function getCategoryService()
    {
        return $this->createService('Taxonomy.CategoryService');
    }

    private function getLogService()
    {
        return $this->createService('System.LogService');
    }

}