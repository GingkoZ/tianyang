<?php
namespace Custom\Service\Organization;

interface OrganizationService
{
    public function findNodesDataByParentId($parentId);

    public function findOrganizationsByIds(array $ids);

    public function searchOrganizations(array $conditions, array $orderBy, $start, $limit);

    public function searchOrganizationCount(array $conditions);

    public function createOrganization($organization);

    public function getOrganizationById($id);

    public function updateOrganization($id, $fields);

    public function deleteOrganization($id);

    public function getNodesByUseId($useId);

    public function findUserIdsByOrgId($orgId);

    public function findUserIdsByOrgIds($orgIds);

    public function getOrganizationFullPathByOrgId($orgId);


   public function findAllChildsByParentId($parentId,$orgs);

   public function findUsersByOrgIds($orgIds,$start, $limit);

 

   

}