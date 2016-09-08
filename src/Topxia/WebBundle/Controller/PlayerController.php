<?php
namespace Topxia\WebBundle\Controller;

use Topxia\Common\FileToolkit;
use Topxia\Service\Util\CloudClientFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PlayerController extends BaseController
{
    public function showAction(Request $request, $id, $context = array())
    {
        $file = $this->getUploadFileService()->getFile($id);

        if (empty($file)) {
            throw $this->createNotFoundException();
        }

        if ($file["storage"] == 'cloud' && $file["type"] == 'video') {
            if (!empty($file['convertParams']['hasVideoWatermark'])) {
                $file['videoWatermarkEmbedded'] = 1;
            }

            if ($this->setting("developer.balloon_player", 0)) {
                $player = "balloon-cloud-video-player";
            } else {
                $player = "cloud-video-player";
            }
        } elseif ($file["storage"] == 'local' && $file["type"] == 'video') {
            $player = "local-video-player";
        } elseif ($file["type"] == 'audio') {
            $player = "audio-player";
        }

        $url = $this->getPlayUrl($id);

        return $this->render('TopxiaWebBundle:Player:show.html.twig', array(
            'file'             => $file,
            'url'              => $url,
            'context'          => $context,
            'player'           => $player,
            'agentInWhiteList' => $this->agentInWhiteList($request->headers->get("user-agent"))
        ));
    }

    protected function agentInWhiteList($userAgent)
    {
        $whiteList = array("iPhone", "iPad", "Android");

        foreach ($whiteList as $value) {
            if (strpos($userAgent, $value) > -1) {
                return true;
            }
        }

        return false;
    }

    protected function getPlayUrl($id)
    {
        $file = $this->getUploadFileService()->getFile($id);

        if (empty($file)) {
            throw $this->createNotFoundException();
        }

        if (!in_array($file["type"], array("audio", "video"))) {
            throw $this->createAccessDeniedException();
        }

        if ($file['storage'] == 'cloud') {
            $factory = new CloudClientFactory();
            $client  = $factory->createClient();

            if (!empty($file['metas2']) && !empty($file['metas2']['sd']['key'])) {
                if (isset($file['convertParams']['convertor']) && ($file['convertParams']['convertor'] == 'HLSEncryptedVideo')) {
                    $token = $this->makeToken('hls.playlist', $file['id']);

                    if ($this->setting("developer.balloon_player")) {
                        $returnJson = true;
                    }

                    $params = array(
                        'id'    => $file['id'],
                        'token' => $token['token']
                    );

                    if (isset($returnJson)) {
                        $params['returnJson'] = $returnJson;
                    }

                    return $this->generateUrl('hls_playlist', $params, true);
                } else {
                    $result = $client->generateHLSQualitiyListUrl($file['metas2'], 3600);
                }
            } else {
                if (!empty($file['metas']) && !empty($file['metas']['hd']['key'])) {
                    $key = $file['metas']['hd']['key'];
                } else {
                    $key = $file['hashId'];
                }

                if ($key) {
                    $result = $client->generateFileUrl($client->getBucket(), $key, 3600);
                }
            }

            return $result['url'];
        } else {
            $token = $this->makeToken('local.media', $file['id']);

            return $this->generateUrl('player_local_media', array(
                'id'    => $id,
                'token' => $token['token']
            ));
        }
    }

    protected function makeToken($type, $fileId)
    {
        $token = $this->getTokenService()->makeToken($type, array(
            'data'     => array(
                'id' => $fileId
            ),
            'times'    => 3,
            'duration' => 3600,
            'userId'   => $this->getCurrentUser()->getId()
        ));
        return $token;
    }

    public function localMediaAction(Request $request, $id, $token)
    {
        $file = $this->getUploadFileService()->getFile($id);

        if (empty($file)) {
            throw $this->createNotFoundException();
        }

        if (!in_array($file["type"], array("audio", "video"))) {
            throw $this->createAccessDeniedException();
        }

        $token = $this->getTokenService()->verifyToken('local.media', $token);

        if ($token['userId'] != $this->getCurrentUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        $response = BinaryFileResponse::create($file['fullpath'], 200, array(), false);
        $response->trustXSendfileTypeHeader();

        $mimeType = FileToolkit::getMimeTypeByExtension($file['ext']);

        if ($mimeType) {
            $response->headers->set('Content-Type', $mimeType);
        }

        return $response;
    }

    protected function getTokenService()
    {
        return $this->getServiceKernel()->createService('User.TokenService');
    }

    protected function getUploadFileService()
    {
        return $this->getServiceKernel()->createService('File.UploadFileService');
    }
}
