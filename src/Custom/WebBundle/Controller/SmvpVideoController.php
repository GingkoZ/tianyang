<?php
namespace Custom\WebBundle\Controller;

use Topxia\WebBundle\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;


class SmvpVideoController extends BaseController
{
	public function lessonAction(Request $Request,$courseId,$lessonId)
	{
     
       $lesson = $this->getCourseService()->getCourseLesson($courseId, $lessonId);

       $url = $lesson['mediaUri'];
       //截取url去掉高度，宽度参数
       if(strpos($url,'script')){
            $url = substr($url, strpos($url,'src=')+5,(strpos($url,'&width')-strpos($url,'src=')-5));
       }else{
            $url = substr($url, 0,strpos($url,'&width'));
       }

        $url = $url . "&width=840|100%25&height=570|100%25";
         
        $url = "<script src='".$url."' language='javascript' charset='utf-8'></script>";

        $type = $Request->query->get("type");

     
        if( $type == "admin"){
          return $this->render('CustomWebBundle:Smvp:admin-show.html.twig'
          ,array('url' =>  $url,
            "courseId"=>$courseId,
            "lessonId"=>$lessonId
            )
          );
        }
        return $this->render('CustomWebBundle:Smvp:show.html.twig'
         	,array('url' =>  $url,
            "courseId"=>$courseId,
            "lessonId"=>$lessonId
            )
        );
        
	}
    private function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }
}