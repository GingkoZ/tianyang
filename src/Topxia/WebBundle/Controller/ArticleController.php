<?php
namespace Topxia\WebBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Topxia\Common\Paginator;
use Topxia\Common\ArrayToolkit;

class ArticleController extends BaseController
{

    public function indexAction(Request $request)
    {
        $categoryTree = $this->getCategoryService()->getCategoryTree();

        $conditions = array(
            'status' => 'published',
        );

        $paginator = new Paginator(
            $this->get('request'),
            $this->getArticleService()->searchArticlesCount($conditions),
            $this->setting('article.pageNums', 10)
        );

        $latestArticles = $this->getArticleService()->searchArticles(
            $conditions,
            'published',
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $categoryIds = ArrayToolkit::column($latestArticles, 'categoryId');

        $categories = $this->getCategoryService()->findCategoriesByIds($categoryIds);

        $featuredConditions = array(
            'status' => 'published',
            'featured' => 1,
            'hasPicture' => 1,
        );

        $featuredArticles = $this->getArticleService()->searchArticles(
            $featuredConditions,
            'normal',
            0,
            5
        );
        $promotedConditions = array(
            'status' => 'published',
            'promoted' => 1,
        );

        $promotedArticles = $this->getArticleService()->searchArticles(
            $promotedConditions,
            'normal',
            0,
            2
        );

        $promotedCategories = array();
        foreach ($promotedArticles as $key => $value) {
            $promotedCategories[$value['id']] = $this->getCategoryService()->getCategory($value['categoryId']);
        }

        return $this->render('TopxiaWebBundle:Article:index.html.twig', array(
            'categoryTree' => $categoryTree,
            'latestArticles' => $latestArticles,
            'featuredArticles' => $featuredArticles,
            'promotedArticles' => $promotedArticles,
            'promotedCategories' => $promotedCategories,
            'paginator' => $paginator,
            'categories' => $categories,
        ));
    }

    public function categoryNavAction(Request $request, $categoryCode)
    {
        list($rootCategories, $categories, $activeIds) = $this->getCategoryService()->makeNavCategories($categoryCode);

        return $this->render('TopxiaWebBundle:Article/Part:category.html.twig', array(
            'rootCategories' => $rootCategories,
            'categories' => $categories,
            'categoryCode' => $categoryCode,
            'activeIds' => $activeIds,
        ));
    }

    public function categoryAction(Request $request, $categoryCode)
    {
        $category = $this->getCategoryService()->getCategoryByCode($categoryCode);

        if (empty($category)) {
            throw $this->createNotFoundException('资讯栏目页面不存在');
        }

        $conditions = array(
            'categoryId' => $category['id'],
            'includeChildren' => true,
            'status' => 'published',
        );

        $paginator = new Paginator(
            $this->get('request'),
            $this->getArticleService()->searchArticlesCount($conditions),
            $this->setting('article.pageNums', 10)
        );

        $articles = $this->getArticleService()->searchArticles(
            $conditions,
            'published',
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $categoryIds = ArrayToolkit::column($articles, 'categoryId');

        $categories = $this->getCategoryService()->findCategoriesByIds($categoryIds);
        return $this->render('TopxiaWebBundle:Article:list.html.twig', array(
            'categoryCode' => $categoryCode,
            'category' => $category,
            'articles' => $articles,
            'paginator' => $paginator,
            'categories' => $categories,
        ));
    }

    public function detailAction(Request $request, $id)
    {
        $article = $this->getArticleService()->getArticle($id);

        if (empty($article)) {
            throw $this->createNotFoundException('文章已删除或者未发布！');
        }

        if ($article['status'] != 'published') {
            return $this->createMessageResponse('error', '文章不是发布状态，请查看！');
        }

        $conditions = array(
            'status' => 'published',
        );

        $createdTime = $article['createdTime'];

        $currentArticleId = $article['id'];
        $articlePrevious = $this->getArticleService()->getArticlePrevious($currentArticleId);
        $articleNext = $this->getArticleService()->getArticleNext($currentArticleId);

        $articleSetting = $this->getSettingService()->get('article', array());
        $categoryTree = $this->getCategoryService()->getCategoryTree();

        $category = $this->getCategoryService()->getCategory($article['categoryId']);
        if (empty($article['tagIds'])) {
            $article['tagIds'] = array();
        }
        $tags = $this->getTagService()->findTagsByIds($article['tagIds']);
        $tagNames = ArrayToolkit::column($tags, 'name');

        $seoKeyword = "";
        if ($tags) {
            $seoKeyword = ArrayToolkit::column($tags, 'name');
            $seoKeyword = implode(",", $seoKeyword);
        }

        $this->getArticleService()->hitArticle($id);

        $breadcrumbs = $this->getCategoryService()->findCategoryBreadcrumbs($category['id']);

        $conditions = array(
            'targetId' => $id,
            'targetType' => 'article',
            'parentId' => 0,
        );

        $paginator = new Paginator(
            $request,
            $this->getThreadService()->searchPostsCount($conditions),
            10
        );

        $posts = $this->getThreadService()->searchPosts(
            $conditions,
            array('createdTime' => 'DESC'),
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $users = $this->getUserService()->findUsersByIds(ArrayToolkit::column($posts, 'userId'));

        $conditions = array(
            'targetType' => 'article',
        );


        $conditions = array(
            'targetId' => $id,
            'targetType' => 'article',
        );

        $count = $this->getThreadService()->searchPostsCount($conditions);

        $conditions = array(
            'type' => 'article',
            'status' => 'published',
        );

        $articles = $this->getArticleService()->searchArticles($conditions, 'normal', 0, 10);

        $sameTagArticles = array();
        foreach ($articles as $key => $value) {
            if (array_intersect($value['tagIds'], $article['tagIds']) && $value['id'] != $article['id'] && !empty($value['thumb'])) {
                $sameTagArticles[] = $this->getArticleService()->getArticle($value['id']);
            }
        }

        $user = $this->getCurrentUser();

        $userLike = $this->getArticleService()->getArticleLike($id, $user['id']);

        $articleBody = $article['body'];

        $articleBody = strip_tags($articleBody,'');

        $articleBody = preg_replace("/ /","",$articleBody); 

        return $this->render('TopxiaWebBundle:Article:detail.html.twig', array(
            'categoryTree' => $categoryTree,
            'articleSetting' => $articleSetting,
            'articlePrevious' => $articlePrevious,
            'article' => $article,
            'articleNext' => $articleNext,
            'tags' => $tags,
            'seoKeyword' => $seoKeyword,
            'seoDesc' => $articleBody,
            'breadcrumbs' => $breadcrumbs,
            'categoryName' => $category['name'],
            'categoryCode' => $category['code'],
            'posts' => $posts,
            'users' => $users,
            'paginator' => $paginator,
            'service' => $this->getThreadService(),
            'count' => $count,
            'tagNames' => $tagNames,
            'sameTagArticles' => $sameTagArticles,
            'userLike' => $userLike,
            'category' => $category
        ));
    }

    public function postAction(Request $request, $id)
    {
        if ($request->getMethod() == "POST") {
            $fields = $request->request->all();

            $post['content'] = $fields['content'];
            $post['targetType'] = 'article';
            $post['targetId'] = $id;

            $user = $this->getCurrentUser();

            if (!$user->isLogin()) {
                $this->createAccessDeniedException('用户没有登录,不能评论!');
            }

            $post = $this->getThreadService()->createPost($post);
            return $this->render('TopxiaWebBundle:Thread/Part:post-item.html.twig', array(
                'post' => $post,
                'author' => $user,
                'service' => $this->getThreadService(),
                'postReplyUrl' => $this->generateUrl('article_post_reply', array('articleId' => $id, 'postId' => $post['id'])),
            ));
        }
    }

    public function postReplyAction(Request $request, $articleId, $postId)
    {
        $fields = $request->request->all();
        $fields['content'] = $this->autoParagraph($fields['content']);
        $fields['targetId'] = $articleId;
        $fields['targetType'] = 'article';
        $fields['parentId'] = $postId;

        $post = $this->getThreadService()->createPost($fields);

        return $this->render('TopxiaWebBundle:Thread:subpost-item.html.twig', array(
            'post' => $post,
            'author' => $this->getCurrentUser(),
            'service' => $this->getThreadService(),
        ));
    }

    public function postJumpAction(Request $request, $articleId, $postId)
    {
        $article = $this->getArticleService()->getArticle($articleId);
        if (empty($article)) {
            throw $this->createNotFoundException();
        }

        $post = $this->getThreadService()->getPost($postId);
        if ($post && $post['parentId']) {
            $post = $this->getThreadService()->getPost($post['parentId']);
        }

        if (empty($post)) {
            return $this->redirect($this->generateUrl("article_detail", array(
                "id" => $articleId,
            )));
        }

        $position = $this->getThreadService()->getPostPostionInArticle($articleId, $post['id']);

        $page = ceil($position / 10);

        return $this->redirect($this->generateUrl("article_detail", array(
            'id' => $articleId,
            'page' => $page,
        ))."#post-{$post['id']}");
    }

    public function subpostsAction(Request $request, $targetId, $postId, $less = false)
    {
        $paginator = new Paginator(
            $request,
            $this->getThreadService()->findPostsCountByParentId($postId),
            10
        );

        $paginator->setBaseUrl($this->generateUrl('article_post_subposts', array('targetId' => $targetId, 'postId' => $postId)));

        $posts = $this->getThreadService()->findPostsByParentId($postId, $paginator->getOffsetCount(), $paginator->getPerPageCount());
        $users = $this->getUserService()->findUsersByIds(ArrayToolkit::column($posts, 'userId'));

        return $this->render('TopxiaWebBundle:Thread:subposts.html.twig', array(
            'parentId' => $postId,
            'targetId' => $targetId,
            'posts' => $posts,
            'users' => $users,
            'paginator' => $paginator,
            'less' => $less,
            'service' => $this->getThreadService(),
        ));
    }

    public function popularArticlesBlockAction()
    {
        $conditions = array(
            'type' => 'article',
            'status' => 'published',
        );

        $articles = $this->getArticleService()->searchArticles($conditions, 'popular', 0, 6);

        return $this->render('TopxiaWebBundle:Article:popular-articles-block.html.twig', array(
            'articles' => $articles,
        ));
    }

    public function recommendArticlesBlockAction()
    {
        $conditions = array(
            'type' => 'article',
            'status' => 'published',
            'promoted' => 1,
        );

        $articles = $this->getArticleService()->searchArticles($conditions, 'normal', 0, 6);

        return $this->render('TopxiaWebBundle:Article:recommend-articles-block.html.twig', array(
            'articles' => $articles,
        ));
    }

    public function tagAction(Request $request, $name)
    {
        $tag = $this->getTagService()->getTagByName($name);
        if (empty($tag)) {
            $this->createAccessDeniedException('标签不存在!');
        }
        $conditions = array(
            'status' => 'published',
            'tagId' => $tag['id']
        );

        $paginator = new Paginator(
            $this->get('request'),
            $this->getArticleService()->searchArticlesCount($conditions),
            $this->setting('article.pageNums', 10)
        );

        $articles = $this->getArticleService()->searchArticles(
            $conditions,
            'published',
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $categoryIds = ArrayToolkit::column($articles, 'categoryId');

        $categories = $this->getCategoryService()->findCategoriesByIds($categoryIds);

        return $this->render('TopxiaWebBundle:Article:list-articles-by-tag.html.twig', array(
            'articles' => $articles,
            'tag' => $tag,
            'categories' => $categories,
            'paginator' => $paginator
        ));
    }

    protected function autoParagraph($text)
    {
        if (trim($text) !== '') {
            $text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
            $text = preg_replace("/\n\n+/", "\n\n", str_replace(array("\r\n", "\r"), "\n", $text));
            $texts = preg_split('/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY);
            $text = '';
            foreach ($texts as $txt) {
                $text .= '<p>'.nl2br(trim($txt, "\n"))."</p>\n";
            }
            $text = preg_replace('|<p>\s*</p>|', '', $text);
        }

        return $text;
    }

    protected function getRootCategory($categoryTree, $category)
    {
        $start = false;
        foreach (array_reverse($categoryTree) as $treeCategory) {
            if ($treeCategory['id'] == $category['id']) {
                $start = true;
            }

            if ($start && $treeCategory['depth'] == 1) {
                return $treeCategory;
            }
        }

        return;
    }

    protected function getSubCategories($categoryTree, $rootCategory)
    {
        $categories = array();

        $start = false;
        foreach ($categoryTree as $treeCategory) {
            if ($start && ($treeCategory['depth'] == 1) && ($treeCategory['id'] != $rootCategory['id'])) {
                break;
            }

            if ($treeCategory['id'] == $rootCategory['id']) {
                $start = true;
            }

            if ($start == true) {
                $categories[] = $treeCategory;
            }
        }

        return $categories;
    }

    public function likeAction(Request $request, $articleId)
    {
        $this->getArticleService()->like($articleId);
        $article = $this->getArticleService()->getArticle($articleId);
        
        return $this->createJsonResponse($article);
    }

    public function cancelLikeAction(Request $request, $articleId)
    {

        $this->getArticleService()->cancelLike($articleId);
        $article = $this->getArticleService()->getArticle($articleId);
        
        return $this->createJsonResponse($article);
    }

    protected function getCategoryService()
    {
        return $this->getServiceKernel()->createService('Article.CategoryService');
    }

    protected function getArticleService()
    {
        return $this->getServiceKernel()->createService('Article.ArticleService');
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

    protected function getTagService()
    {
        return $this->getServiceKernel()->createService('Taxonomy.TagService');
    }

    protected function getThreadService()
    {
        return $this->getServiceKernel()->createService('Thread.ThreadService');
    }
}
