<?php
namespace Custom\Service\Organization\Dao\Impl;
       // Custom\Service\Organization\Dao\Impl\OrganizationDaoImpl
use Topxia\Service\Common\BaseDao;
use Custom\Service\Organization\Dao\OrganizationDao;
      
class OrganizationDaoImpl extends BaseDao implements OrganizationDao 
{

    protected $table = 'tianyangtax_organization';

    public function findNodesDataByParentId($parentId)
    {
        $sql = "SELECT * FROM {$this->table} WHERE parentId = ? order By weight ";
        return $this->getConnection()->fetchAll($sql, array($parentId)) ? : array();
    }
    
    public function findOrganizationsByIds(array $ids)
    {
        if(empty($ids)){ return array(); }
        $marks = str_repeat('?,', count($ids) - 1) . '?';
        $sql ="SELECT * FROM {$this->table} WHERE id IN ({$marks});";
        return $this->getConnection()->fetchAll($sql, $ids) ? : array();
    }
    
    public function searchOrganizations(array $conditions, array $orderBy, $start, $limit)
    {
        $this->filterStartLimit($start, $limit);
        $builder = $this->createOrganizationQueryBuilder($conditions)
            ->select('*')
            ->orderBy($orderBy[0], $orderBy[1])
            ->setFirstResult($start)
            ->setMaxResults($limit);
        return $builder->execute()->fetchAll() ? : array();
    }

    public function searchOrganizationCount(array $conditions)
    {
        $builder = $this->createOrganizationQueryBuilder($conditions)
            ->select('COUNT(id)');
        return $builder->execute()->fetchColumn(0);
    }

    private function createOrganizationQueryBuilder($conditions)
    {
        $conditions = array_filter($conditions,function($v){
            if($v === 0){
                return true;
            }
                
            if(empty($v)){
                return false;
            }
            return true;
        });

        if (isset($conditions['name'])) {
            $conditions['name'] = "%{$conditions['name']}%";
        }

        $builder = $this->createDynamicQueryBuilder($conditions)
            ->from($this->table, 'tianyangtax_organization')
            ->andWhere('name LIKE :name');

        return $builder;
    }

    public function updateOrganization($id, $fields)
    {
        $this->getConnection()->update($this->table, $fields, array('id' => $id));
        return $this->getOrganizationById($id);
    }   

    public function getOrganizationById($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($id));
    }

    
    public function createOrganization($organization)
    {
        $affected = $this->getConnection()->insert($this->table, $organization);
        if ($affected <= 0) {
            throw $this->createDaoException('Insert organization error.');
        }
        return $this->getOrganizationById($this->getConnection()->lastInsertId());
    }

    public function deleteOrganization($id){
        return $this->getConnection()->delete($this->table, array('id' => $id));
    }

    public function getNodeByUserId($useId){
        $sql = "SELECT tyjh_organizationId FROM user  WHERE id = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($useId));
    }


    public function  findUserIdsByOrgId($orgId){
        $sql = "SELECT id FROM user WHERE tyjh_organizationId = ?  ";
        return $this->getConnection()->fetchAll($sql, array($orgId)) ? : array();
    }


    public function findUserIdsByOrgIds($orgIds){
        if(empty($orgIds)){
            return array();
        }
        $marks = str_repeat('?,', count($orgIds) - 1) . '?';
        $sql ="SELECT id FROM user WHERE tyjh_organizationId IN ({$marks});";
        return $this->getConnection()->fetchAll($sql, $orgIds);

        // $sql = "SELECT id FROM user WHERE tyjh_organizationId in ?  ";
        // return $this->getConnection()->fetchAll($sql, array($orgIds)) ? : array();
    }

   public function findUsersByOrgIds($orgIds,$start, $limit){
    $this->filterStartLimit($start, $limit);
    if(empty($orgIds)){
        return array();
    }
    $marks = str_repeat('?,', count($orgIds) - 1) . '?';
    $sql ="SELECT * FROM user WHERE tyjh_organizationId IN ({$marks})  LIMIT {$start}, {$limit}";
    return $this->getConnection()->fetchAll($sql, $orgIds);
       
   }

  
}