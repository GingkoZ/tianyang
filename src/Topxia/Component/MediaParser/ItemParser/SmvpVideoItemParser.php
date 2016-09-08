<?php
namespace Topxia\Component\MediaParser\ItemParser;
use Topxia\Service\Common\ServiceKernel;

class SmvpVideoItemParser extends AbstractItemParser
{


    // <script src="http://pub.smvp.cn/publishing/smvp.js?
    //publisherId=349341838019230465&playerId=353234268091597870&
    // videoId=672241825867643349&width=640&height=480" language="javascript" 
    // charset="utf-8"></script>

	public function parse($url)
	{
        $smvpToken = $this->getKernel()->getParameter('smvpToken');
        $videoId = substr($url, strpos($url,'videoId')+8,(strpos($url,'&width')-strpos($url,'videoId')-8));
        $client = new SMVP($smvpToken);
        $resultStr = $client->entries_get(array("id"=>$videoId));
		
        // $result = explode(",",$resultStr);
        $result = json_decode($resultStr,true);
      
        $item = array();
        $videoCode = $result['error_code'];
		if ($videoCode == 'RESOURCE_NOT_FOUND') {
            throw $this->createParseException("指定的视频未找到！");
        }
        if ($videoCode == 'RESOURCE_PERMISSION_DENIED') {
            throw $this->createParseException("您没有权限获取指定的视频！");
        }

       $videoStatus = $result['status'];
       if($videoStatus != 'FINISHED'){
               throw $this->createParseException("该视频没有准备就绪，不能播放！");
       }
        $videoActivate = $result['activated'];
        if( !$videoActivate === true ){
              throw $this->createParseException("该视频没有发布，不能播放！");
        }

     

        $videoId =  $result['id'];

        $item['type'] = 'video';
        $item['source'] = 'smvp';
        $item['uuid'] = 'smvp:' . $videoId;

        $item['name'] = $result['title'];
        $item['duration'] = $result['duration']/1000;
      
        $item['pictures'] = array(
            array('url' => $result['snapshot_url'])
        );

         $item['files'] = array(
            array('url' => $url, 'type' => 'iframe'),
        );

        return $item;
	}

    public function detect($url)
    {

       // $findme = "http://pub.smvp.cn/publishing/smvp.js";
        $findme = "http://pub.video.capitalcloud.net/publishing/smvp.js";
        $pos = strpos($url, $findme);

        // 注意这里使用的是 ===。简单的 == 不能像我们期待的那样工作，
        // 因为 'a' 是第 0 位置上的（第一个）字符。
        if ($pos === false) {
           return false;
        } else {
           return true;
        }
       
     
    }
    protected function getKernel()
    {
        return ServiceKernel::instance();
    }
}

class SMVP {

    function post($url, $params, $headers=array()) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    } else {
        echo 'curl should be installed.';
    }
}

    private $api_url = 'http://api.alpha.smvp.cn/';
    private $token = NULL;

    function __construct($access_token, $url=NULL) {
        if ( $url ) $this->api_url = $url;
            $this->token = $access_token;
    }
    
    function __destruct() {}
   
    public function setToken($access_token) {
        $this->token = $access_token;
    }

    public function getToken() {
        return $this->token;
    }
    
    public function setURL($url) {
        $this->api_url = $url;
    }

    public function getURL() {
        return $this->api_url;
    }

    public function __call($method, $args) {
        $post_data = array();
        if ( count($args) > 0 ) {
            $arguments = $args[0];
            foreach($arguments as $arg=>$argvalue) {
                $v = $argvalue;
                if ($arg != 'file') $v = json_encode($argvalue);
                $post_data[$arg] = $v;
            }
        }
        $headers = array('Authorization:SMVP_TOKEN|'.$this->token);
        $request_url = $this->api_url;
        if (substr($request_url, -1) != '/') {
            $request_url = $request_url.'/';
        }
        $request_url = $request_url.(str_replace('_', '/', $method));
        return $this->post($request_url, $post_data, $headers);
    }
}
