<?php

namespace Topxia\Service\User\Tests;

use Topxia\Service\Common\BaseTestCase;
use Topxia\Service\User\UserService;
use Topxia\Service\Common\ServiceException;
use Topxia\Common\ArrayToolkit;

class CategoryServiceTest extends BaseTestCase
{

    public function setUp()
    {
        parent::setUp();
        $this->getCategoryService()->addGroup(array('id' => 1, 'code' => 'code1', 'name' => '课程分类1', 'depth' => 3));
        $this->getCategoryService()->addGroup(array('id' => 2, 'code' => 'code2', 'name' => '课程分类2', 'depth' => 3));
        $this->getCategoryService()->addGroup(array('id' => 3, 'code' => 'code3', 'name' => '课程分类3', 'depth' => 3));
    }

    /**
     * @group add
     * @expectedException Topxia\Service\Common\ServiceException
     */
    public function testAddCategoryWithNoParentId()
    {
        $category = array('name' => '测试分类1', 'code' => 'code', 'weight' => 100, 'groupId' => 1);
        $category = $this->getCategoryService()->createCategory($category);

        $this->assertNotEmpty($category);
        $this->assertEquals('1', $category['path']);
        $this->assertEquals('code', $category['code']);
        $this->assertEquals('测试分类1', $category['name']);
        $this->assertEquals(100, $category['weight']);
        $this->assertEquals(1, $category['groupId']);
        $this->assertEquals(0, $category['parentId']);
    }

    /**
    * @group add 
    * @expectedException Topxia\Service\Common\ServiceException
    */
    public function testAddCategoryWithNotExistParentId()
    {
        $category = array('name' => '', 'code' => 'code', 'weight' => 100, 'groupId' => 1, 'parentId' => 11111);
        $this->getCategoryService()->createCategory($category);
    }

    /**
     * @group add
     * @expectedException Topxia\Service\Common\ServiceException
     */
    public function testAddCategoryWithEmptyCategoryName()
    {
        $category = array('name' => '', 'code' => 'code', 'weight' => 100, 'groupId' => 1);
        $this->getCategoryService()->createCategory($category);
    }


    /**
    * @group add
    * @expectedException Topxia\Service\Common\ServiceException
    */
    public function testAddCategoryWithNotExistGroupId()
    {
        $category = array('name' => 'name', 'code' => 'code', 'weight' => 100, 'groupId' => 100000);
        $this->getCategoryService()->createCategory($category);
    }

    /**
     * @group add
     * @expectedException Topxia\Service\Common\ServiceException
     */
    public function testAddCategoryWithCodeAlreayExist()
    {
        $categoryA = array('name' => '测试分类1', 'code' => 'code', 'weight' => 100, 'groupId' => 1);
        $this->getCategoryService()->createCategory($categoryA);
        $categoryB = array('name' => '测试分类1', 'code' => 'code', 'weight' => 50, 'groupId' => 1);
        $this->getCategoryService()->createCategory($categoryB);
    }
    
     /**
     * @group get
     * @expectedException Topxia\Service\Common\ServiceException
     */
    public function testGetCategory()
    {
        $categoryA = array('name' => '测试分类1', 'code' => 'code', 'weight' => 100, 'groupId' => 1);
        $createdCategory = $this->getCategoryService()->createCategory($categoryA);
        $foundCategory = $this->getCategoryService()->getCategory($createdCategory['id']);
        $this->assertEquals($createdCategory, $foundCategory);
    }

    /**
     * @group get
     */
    public function testGetCategoryWithNotExistCategoryId()
    {
        $foundCategory = $this->getCategoryService()->getCategory(999);
        $this->assertFalse($foundCategory);
    }

    /**
     * @group get
     * @expectedException Topxia\Service\Common\ServiceException
     */
    public function testGetCategoryByCode()
    {
        $categoryA = array('name' => '测试分类1', 'code' => 'code', 'weight' => 100, 'groupId' => 1);
        $createdCategory = $this->getCategoryService()->createCategory($categoryA);
        $foundCategory = $this->getCategoryService()->getCategoryByCode('code');
        $this->assertEquals($createdCategory, $foundCategory);
    }

    /**
     * @group get
     * @expectedException Topxia\Service\Common\ServiceException
     */
    public function testGetCategoryByCodeWithNotExistCategoryCode()
    {
        $categoryA = array('name' => '测试分类1', 'code' => 'code', 'weight' => 100, 'groupId' => 1);
        $createdCategory = $this->getCategoryService()->createCategory($categoryA);
        $foundCategory = $this->getCategoryService()->getCategoryByCode('xxxx');
        $this->assertFalse($foundCategory);
    }

    /**
     * @group get
     * @expectedException Topxia\Service\Common\ServiceException
     */
    public function testfindCategories()
    {
        $categoryA = array('name' => '测试分类1', 'code' => 'codeA', 'weight' => 100, 'groupId' => 1);
        $categoryB = array('name' => '测试分类2', 'code' => 'codeB', 'weight' => 10, 'groupId' => 1);
        $createdCategoryA = $this->getCategoryService()->createCategory($categoryA);
        $createdCategoryB = $this->getCategoryService()->createCategory($categoryB);
        $categories = $this->getCategoryService()->findCategories(1);
        $this->assertContains($createdCategoryA, $categories);
        $this->assertContains($createdCategoryB, $categories);
    }

     /**
     * @group get
     * @expectedException Topxia\Service\Common\ServiceException
     */
    public function testfindCategoriesWithNotExistGroupId()
    {
        $this->getCategoryService()->findCategories(999);
    }

     /**
     * @group get
     * @expectedException Topxia\Service\Common\ServiceException
     */
    public function testGetCategoryTree()
    {
        $categoryA = array('name' => '测试分类1', 'code' => 'codeA', 'weight' => 100, 'groupId' => 1);
        $createdCategoryA = $this->getCategoryService()->createCategory($categoryA);
        $categoryB = array('name' => '测试分类2', 'code' => 'codeB', 'weight' => 10, 'groupId' => 1, 'parentId' => $createdCategoryA['id']);
        $createdCategoryB = $this->getCategoryService()->createCategory($categoryB);
        $categoryC = array('name' => '测试分类3', 'code' => 'codeC', 'weight' => 20, 'groupId' => 1, 'parentId' => $createdCategoryB['id']);
        $this->getCategoryService()->createCategory($categoryC);
        $categories = $this->getCategoryService()->getCategoryTree(1);

        $this->assertEquals(3, count($categories));
        $paths = array('1', '1|2', '1|2|3');
        foreach ($categories as $key => $category) {
            $this->assertContains($category['path'], $paths);
        }
    }

    /**
     * @group get
     * @expectedException Topxia\Service\Common\ServiceException
     */
    public function testGetCategoryTreeWithNotExistGroupId()
    {
        $this->getCategoryService()->getCategoryTree(999);
    }

    /**
     * @group update
     * @expectedException Topxia\Service\Common\ServiceException
     */
    public function testUpdateCategory()
    {
        $categoryA = array('name' => '测试分类1', 'code' => 'code', 'weight' => 100, 'groupId' => 1);
        $createdCategory = $this->getCategoryService()->createCategory($categoryA);
        $updateCategory = $this->getCategoryService()->updateCategory($createdCategory['id'], array(
            'code'=>'xxx',
            'name'=>'测试分类2',
            'weight'=>20,
            'groupId'=>1)); 

        $this->assertEquals('xxx', $updateCategory['code']);
        $this->assertEquals('测试分类2', $updateCategory['name']);
        $this->assertEquals(20, $updateCategory['weight']);
        $this->assertEquals(1, $updateCategory['groupId']);
        $this->assertEquals(0, $updateCategory['parentId']);
        $this->assertEquals('1', $updateCategory['path']);
    }

    /**
     * @group delete
     * @expectedException Topxia\Service\Common\ServiceException
     */
    public function testDeleteCategory()
    {
        $categoryA = array('name' => '测试分类1', 'code' => 'code', 'weight' => 100, 'groupId' => 1);
        $createdCategory = $this->getCategoryService()->createCategory($categoryA);
        $result = $this->getCategoryService()->deleteCategory($createdCategory['id']);
        $category = $this->getCategoryService()->getCategory($createdCategory['id']);
        $this->assertEquals(1, $result);
        $this->assertFalse($category);
        
        $result = $this->getCategoryService()->deleteCategory($createdCategory['id']);
        $this->assertEquals(0, $result);
    }

    /**
     * @group group
     */
    public function testGetGroups()
    {
        $groups = $this->getCategoryService()->getGroups(0, 2);
        $this->assertEquals(2, count($groups));
        $this->assertContains(array('id'=>1, 'code'=>'code1', 'name'=>'课程分类1', 'depth'=>3), $groups);
        $this->assertContains(array('id'=>2, 'code'=>'code2', 'name'=>'课程分类2', 'depth'=>3), $groups);
    }

    /**
     * @group group
     */
    public function testGetGroup()
    {
        $group = $this->getCategoryService()->getGroup(1);
        $this->assertEquals(array('id'=>1, 'code'=>'code1', 'name'=>'课程分类1', 'depth'=>3), $group);
        
        $group = $this->getCategoryService()->getGroup(999);
        $this->assertFalse($group); 
    }

    /**
     * @group current
     */
    public function testGetGroupByCode()
    {
        $group = $this->getCategoryService()->getGroupByCode('code1');
        $this->assertEquals(array('id'=>1, 'code'=>'code1', 'name'=>'课程分类1', 'depth'=>3), $group);
        
        $group = $this->getCategoryService()->getGroupByCode('xxx');
        $this->assertFalse($group); 
    }

    protected function getCategoryService()
    {
        return $this->getServiceKernel()->createService('Taxonomy.CategoryService');
    }
}