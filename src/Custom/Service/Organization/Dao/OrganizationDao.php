<?php

namespace Custom\Service\Organization\Dao;
         

interface OrganizationDao
{
 	public function findNodesDataByParentId($parentId);

 	public function findOrganizationsByIds(array $ids);
 	
 	public function searchOrganizations(array $conditions, array $orderBy, $start, $limit);

    public function searchOrganizationCount(array $conditions);
    
 	public function createOrganization($organization);

 	public function getOrganizationById($id);

 	public function updateOrganization($id, $fields);

	public function deleteOrganization($id);

	public function getNodeByUserId($useId);

	public function findUserIdsByOrgId($orgId);

	public function findUserIdsByOrgIds($orgIds);

	public function findUsersByOrgIds($orgIds,$start, $limit);



}