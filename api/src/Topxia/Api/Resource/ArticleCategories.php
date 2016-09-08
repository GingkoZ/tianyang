<?php

namespace Topxia\Api\Resource;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class ArticleCategories extends BaseResource
{
    public function get(Application $app, Request $request)
    {
        $categories = $this->getCategoryService()->getCategoryTree();

        return $this->filter($categories);
    }

    public function filter(&$res)
    {
        foreach ($res as &$category) {
            $category['createdTime'] = date('c', $category['createdTime']);
        }

        return $res;
    }

    protected function getCategoryService()
    {
        return $this->getServiceKernel()->createService('Article.CategoryService');
    }

}