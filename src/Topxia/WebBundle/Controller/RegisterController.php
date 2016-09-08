<?php
namespace Topxia\WebBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Topxia\WebBundle\Form\RegisterType;
use Gregwar\Captcha\CaptchaBuilder;
use Topxia\Common\SmsToolkit;
use Topxia\Common\SimpleValidator;

class RegisterController extends BaseController
{
     public function indexAction(Request $request)
    {
        $user = $this->getCurrentUser();
        if ($user->isLogin()) {
            return $this->createMessageResponse('info', '你已经登录了', null, 3000, $this->generateUrl('homepage'));
        }

        $registerEnable  = $this->getAuthService()->isRegisterEnabled();
        if (!$registerEnable) {
            return $this->createMessageResponse('info', '注册已关闭，请联系管理员', null, 3000, $this->generateUrl('homepage'));
        }
        
        if ($request->getMethod() == 'POST') {

            $registration = $request->request->all();
            $registration['mobile'] = isset($registration['verifiedMobile']) ? $registration['verifiedMobile'] : '';

            $registration['createdIp'] = $request->getClientIp();


            $authSettings = $this->getSettingService()->get('auth', array());

            //验证码校验
            $this->captchaEnabledValidator($authSettings,$registration,$request);
            //手机校验码
            if($this->smsCodeValidator($authSettings,$registration,$request)){
                $registration['verifiedMobile'] = '';
                $request->request->add(array_merge($request->request->all(),array('mobile'=>$registration['mobile'])));
                
                list($result, $sessionField, $requestField) = SmsToolkit::smsCheck($request, $scenario = 'sms_registration');
                if ($result){
                    $registration['verifiedMobile'] = $sessionField['to'];
                }else{
                    return $this->createMessageResponse('info', '手机短信验证错误，请重新注册');
                }
            }
            //ip次数限制
            if($this->registerLimitValidator($registration, $authSettings,$request)){
               return  $this->createMessageResponse('info', '由于您注册次数过多，请稍候尝试');
            }

            $user = $this->getAuthService()->register($registration);

            if(!isset($registration['nickname'])){
                return  $this->render("TopxiaWebBundle:Register:nickname-update.html.twig",array('user' => $user));        
            }else{
                $authSettings = $this->getSettingService()->get('auth', array());

                if(($authSettings && array_key_exists('email_enabled',$authSettings) && ($authSettings['email_enabled'] == 'closed') ) ||   !$this->isEmptyVeryfyMobile($user)){
                     $this->authenticateUser($user);
                }

                $goto = $this->generateUrl('register_submited', array(
                    'id' => $user['id'], 
                    'hash' => $this->makeHash($user),
                    'goto' => $this->getTargetPath($request),
                ));
             
                if ($this->getAuthService()->hasPartnerAuth()) {
                     $this->authenticateUser($user);
                    return $this->redirect($this->generateUrl('partner_login', array('goto' => $goto)));
                }

                $mailerSetting=$this->getSettingService()->get('mailer');

                if(!$mailerSetting['enabled'] && $this->isEmptyVeryfyMobile($user)){
                    return $this->redirect($this->getTargetPath($request));
                }
                return $this->redirect($goto);
            }

        }


        return $this->render("TopxiaWebBundle:Register:index.html.twig", array(
            'isRegisterEnabled' => $registerEnable,
            'registerSort'=>array(),
            '_target_path' => $this->getTargetPath($request),
        ));
    }


    public function nicknameUpdateAction(Request $request)
    {
        if ($request->getMethod() == 'POST') {

            $registration = $request->request->all();
            $this->getAuthService()->changeNickname($registration['id'], $registration['nickname']);
            $user =$this->getUserService()->getUser($registration['id']);

            $authSettings = $this->getSettingService()->get('auth', array());

            if(($authSettings && array_key_exists('email_enabled',$authSettings) && ($authSettings['email_enabled'] == 'closed') ) ||   !$this->isEmptyVeryfyMobile($user)){
                 $this->authenticateUser($user);
                 $this->sendRegisterMessage($user);
            }

            $goto = $this->generateUrl('register_submited', array(
                'id' => $user['id'], 
                'hash' => $this->makeHash($user),
                'goto' => $this->getTargetPath($request),
            ));
         
            if ($this->getAuthService()->hasPartnerAuth()) {
                 $this->authenticateUser($user);
                 $this->sendRegisterMessage($user);
                return $this->redirect($this->generateUrl('partner_login', array('goto' => $goto)));
            }

            $mailerSetting=$this->getSettingService()->get('mailer');

            if(!$mailerSetting['enabled'] && $this->isEmptyVeryfyMobile($user)){
                return $this->redirect($this->getTargetPath($request));
            }
            return $this->redirect($goto);
          
        }

        return $this->render("TopxiaWebBundle:Register:nickname-update.html.twig", array(
            'user' => $user,
            'registration' => $registration,
            ));
    }

    protected function isMobileRegister($registration){
        if(isset($registration['emailOrMobile']) && !empty($registration['emailOrMobile'])){
             if( SimpleValidator::mobile($registration['emailOrMobile'])){
                return true;
             }
        }elseif(isset($registration['mobile']) && !empty($registration['mobile'])){
            if( SimpleValidator::mobile($registration['mobile'])){
                return true;
             }
        }
        return false;
    }

     protected function isEmptyVeryfyMobile($user){
        if(isset($user['verifiedMobile']) && !empty($user['verifiedMobile'])){
              return false;
        }
        return true;
    }
    

    protected function protectiveRule($type,$ip)
    {
        switch ($type) {
            case 'middle':
                $condition=array(
                    'startTime'=>time()-24*3600,
                    'createdIp'=>$ip,);
                $registerCount=$this->getUserService()->searchUserCount($condition);
                if($registerCount > 30 ){
                    
                    return false;
                }
                return true;
                break;
            case 'high':
                $condition=array(
                    'startTime'=>time()-24*3600,
                    'createdIp'=>$ip,);
                $registerCount=$this->getUserService()->searchUserCount($condition);
                if($registerCount > 10 ){
                    
                    return false;
                }
                $registerCount=$this->getUserService()->searchUserCount(array(
                    'startTime'=>time()-3600,
                    'createdIp'=>$ip,));
                if($registerCount >= 1 ){
                    
                    return false;
                }
                return true;
                break;
            default:
                return true;
                break;
        }
    }

    public function userTermsAction(Request $request)
    {
        $setting = $this->getSettingService()->get('auth', array());

        return $this->render("TopxiaWebBundle:Register:user-terms.html.twig", array(
            'userTerms' => $setting['user_terms_body']
        ));
    }

    public function emailSendAction(Request $request, $id, $hash)
    {
        $user = $this->checkHash($id, $hash);
        if (empty($user)) {
            return $this->createJsonResponse(false);
        }

        $token = $this->getUserService()->makeToken('email-verify', $user['id'], strtotime('+1 day'));
        $this->sendVerifyEmail($token, $user);

        return $this->createJsonResponse(true);
    }


    public function submitedAction(Request $request, $id, $hash)
    {

        $user = $this->checkHash($id, $hash);

        if (empty($user)) {
            throw $this->createNotFoundException();
        }

        $auth = $this->getSettingService()->get('auth');
 
  
        if(!empty($user['verifiedMobile'])){
              return $this->redirect($this->generateUrl('homepage'));
        }

        if($auth && $auth['register_mode'] != 'mobile' && array_key_exists('email_enabled',$auth) && ($auth['email_enabled'] == 'opened')){

               return $this->render("TopxiaWebBundle:Register:email-verify.html.twig", array(
                'user' => $user,
                'hash' => $hash,
                'emailLoginUrl' => $this->getEmailLoginUrl($user['email']),
                '_target_path' => $this->getTargetPath($request),
                ));
           }else{
                /*return $this->render("TopxiaWebBundle:Register:submited.html.twig", array(
                'user' => $user,
                'hash' => $hash,
                'emailLoginUrl' => $this->getEmailLoginUrl($user['email']),
                '_target_path' => $this->getTargetPath($request),
                ));*/
                $this->authenticateUser($user);
                return $this->redirect($this->generateUrl('homepage'));
           }
    }

    protected function getTargetPath($request)
    {
        if ($request->query->get('goto')) {
            $targetPath = $request->query->get('goto');
        } else if ($request->getSession()->has('_target_path')) {
            $targetPath = $request->getSession()->get('_target_path');
        } else {
            $targetPath = $request->headers->get('Referer');
        }

        if ($targetPath == $this->generateUrl('login', array(), true)) {
            return $this->generateUrl('homepage');
        }

        $url = explode('?', $targetPath);

        if ($url[0] == $this->generateUrl('partner_logout', array(), true)) {
            return $this->generateUrl('homepage');
        }

        if ($url[0] == $this->generateUrl('password_reset_update', array(), true)) {
            $targetPath = $this->generateUrl('homepage', array(), true);
        }

        return $targetPath;
    }

    public function emailVerifyAction(Request $request, $token)
    {

        $token = $this->getUserService()->getToken('email-verify', $token);

        if (empty($token)) {
            $currentUser = $this->getCurrentUser();
            if (empty($currentUser) || $currentUser['id'] == 0) {
                return $this->render('TopxiaWebBundle:Register:email-verify-error.html.twig');
            } else {
                return $this->redirect($this->generateUrl('settings'));
            }
        }

        $user = $this->getUserService()->getUser($token['userId']);
        if (empty($user)) {
            return $this->createNotFoundException();
        }

        $this->authenticateUser($user);
        $this->getUserService()->setEmailVerified($user['id']);

        if (strtoupper($request->getMethod()) ==  'POST') {
            $this->getUserService()->deleteToken('email-verify', $token['token']);
            return $this->createJsonResponse(true);
        }

        return $this->render('TopxiaWebBundle:Register:email-verify-success.html.twig', array(
            'token' => $token,
        ));
    }

    protected function makeHash($user)
    {
        $string = $user['id'] . $user['email'] . $this->container->getParameter('secret');
        return md5($string);
    }

    protected function checkHash($userId, $hash)
    {
        $user = $this->getUserService()->getUser($userId);
        if (empty($user)) {
            return false;
        }

        if ($this->makeHash($user) !== $hash) {
            return false;
        }

        return $user;
    }

    public function emailCheckAction(Request $request)
    {

        $email = $request->query->get('value');
        $email = str_replace('!', '.', $email);
        list($result, $message) = $this->getAuthService()->checkEmail($email);
        return $this->validateResult($result, $message);
    }

    public function mobileCheckAction(Request $request)
    {
        $mobile = $request->query->get('value');
        list($result, $message) = $this->getAuthService()->checkMobile($mobile);
        
        return $this->validateResult($result, $message);
    }

    public function emailOrMobileCheckAction(Request $request){
        $emailOrMobile = $request->query->get('value');
         $emailOrMobile = str_replace('!', '.', $emailOrMobile);
        list($result, $message) = $this->getAuthService()->checkEmailOrMobile($emailOrMobile);
        return $this->validateResult($result, $message);
    }
    protected function validateResult($result, $message){
        if ($result == 'success') {
           $response = array('success' => true, 'message' => '');
        } else {
           $response = array('success' => false, 'message' => $message);
        }
        return $this->createJsonResponse($response);
    }

    public function nicknameCheckAction(Request $request)
    {
        $nickname = $request->query->get('value');
        $randomName = $request->query->get('randomName');
        list($result, $message) = $this->getAuthService()->checkUsername($nickname,$randomName);
        return $this->validateResult($result, $message);
    }

    public function captchaModalAction()
    {
        return $this->render('TopxiaWebBundle:Register:captcha-modal.html.twig',array());
    }

    public function captchaCheckAction(Request $request)
    {
        $captchaFilledByUser = strtolower($request->query->get('value')); 
        
        if ($request->getSession()->get('captcha_code') == $captchaFilledByUser) {
            $response = array('success' => true, 'message' => '验证码正确');
        } else {
            $request->getSession()->set('captcha_code',mt_rand(0,999999999)); 
            $response = array('success' => false, 'message' => '验证码错误');
        }
        return $this->createJsonResponse($response);
    }

    protected function getUserFieldService()
    {
        return $this->getServiceKernel()->createService('User.UserFieldService');
    }

    public function getEmailLoginUrl ($email)
    {
        $host = substr($email, strpos($email, '@') + 1);

        if ($host == 'hotmail.com') {
            return 'http://www.' . $host;
        }

        if ($host == 'gmail.com') {
            return 'http://mail.google.com';
        }

        return 'http://mail.' . $host;
    }


    public function analysisAction(Request $request)
    {
        return $this->render('TopxiaWebBundle:Register:analysis.html.twig',array());
    }

    public function captchaAction(Request $request)
    {
        $imgBuilder = new CaptchaBuilder;
        $imgBuilder->build($width = 150, $height = 32, $font = null);
        $request->getSession()->set('captcha_code',strtolower($imgBuilder->getPhrase()));

        ob_start();
        $imgBuilder->output();
        $str = ob_get_clean();
        $imgBuilder = null;
        
        $headers = array(
            'Content-type' => 'image/jpeg',
            'Content-Disposition' => 'inline; filename="'."reg_captcha.jpg".'"');
        
        return new Response($str, 200, $headers);
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

    protected function getMessageService()
    {
        return $this->getServiceKernel()->createService('User.MessageService');
    }

    protected function getNotificationService()
    {
        return $this->getServiceKernel()->createService('User.NotificationService');
    }

    protected function getAuthService()
    {
        return $this->getServiceKernel()->createService('User.AuthService');
    }

    protected function  sendRegisterMessage($user)
    {
        $senderUser = array();
        $auth = $this->getSettingService()->get('auth', array());

        if (empty($auth['welcome_enabled'])) {
            return ;
        }

        if ($auth['welcome_enabled'] != 'opened') {
            return ;
        }

        if (empty($auth['welcome_sender'])) {
            return ;
        }
        
        $senderUser = $this->getUserService()->getUserByNickname($auth['welcome_sender']);
        if (empty($senderUser)) {
            return ;
        }

        $welcomeBody = $this->getWelcomeBody($user);
        if (empty($welcomeBody)) {
            return true;
        }

        if (strlen($welcomeBody) >= 1000) {
            $welcomeBody = $this->getWebExtension()->plainTextFilter($welcomeBody, 1000);
        }

        $this->getMessageService()->sendMessage($senderUser['id'], $user['id'], $welcomeBody);
        $conversation = $this->getMessageService()->getConversationByFromIdAndToId($user['id'], $senderUser['id']);
        $this->getMessageService()->deleteConversation($conversation['id']);

    }

    protected function getWelcomeBody($user)
    {
        $site = $this->getSettingService()->get('site', array());
        $valuesToBeReplace = array('{{nickname}}', '{{sitename}}', '{{siteurl}}');
        $valuesToReplace = array($user['nickname'], $site['name'], $site['url']);
        $welcomeBody = $this->setting('auth.welcome_body', '注册欢迎的内容');
        $welcomeBody = str_replace($valuesToBeReplace, $valuesToReplace, $welcomeBody);
        return $welcomeBody;
    }

    protected function sendVerifyEmail($token, $user)
    {
        $site = $this->getSettingService()->get('site', array());
        $emailTitle = $this->setting('auth.email_activation_title',
            '请激活你的帐号 完成注册');
        $emailBody = $this->setting('auth.email_activation_body', ' 验证邮箱内容');

        $valuesToBeReplace = array('{{nickname}}', '{{sitename}}', '{{siteurl}}', '{{verifyurl}}');
        $verifyurl = $this->generateUrl('register_email_verify', array('token' => $token), true);
        $valuesToReplace = array($user['nickname'], $site['name'], $site['url'], $verifyurl);
        $emailTitle = str_replace($valuesToBeReplace, $valuesToReplace, $emailTitle);
        $emailBody = str_replace($valuesToBeReplace, $valuesToReplace, $emailBody);
        try {
            $this->sendEmail($user['email'], $emailTitle, $emailBody);
        } catch(\Exception $e) {
            $this->getLogService()->error('user', 'register', '注册激活邮件发送失败:' . $e->getMessage());
        }
    }

    protected function getWebExtension()
    {
        return $this->container->get('topxia.twig.web_extension');
    }


    //validate captcha
    protected function captchaEnabledValidator($authSettings,$registration,$request){
         if (array_key_exists('captcha_enabled',$authSettings) && ($authSettings['captcha_enabled'] == 1) && !isset($registration['mobile'])){                
            $captchaCodePostedByUser = strtolower($registration['captcha_code']);
            $captchaCode = $request->getSession()->get('captcha_code');                   
            if (!isset($captchaCodePostedByUser)||strlen($captchaCodePostedByUser)<5){   
                throw new \RuntimeException('验证码错误。');    
            }                   
            if (!isset($captchaCode)||strlen($captchaCode)<5){    
                throw new \RuntimeException('验证码错误。');    
            }
            if ($captchaCode != $captchaCodePostedByUser){ 
                $request->getSession()->set('captcha_code',mt_rand(0,999999999));  
                throw new \RuntimeException('验证码错误。');
            }
            $request->getSession()->set('captcha_code',mt_rand(0,999999999));
        }
    }

    protected function smsCodeValidator($authSettings,$registration,$request){

        if ( 
            ($authSettings['register_mode'] == 'mobile' or $authSettings['register_mode'] == 'email_or_mobile')
            &&isset($registration['mobile']) && !empty($registration['mobile'])
            &&($this->setting('cloud_sms.sms_enabled') == '1')
            &&($this->setting('cloud_sms.sms_registration') == 'on')){
            return true;

        }
    }

    protected function registerLimitValidator($registration, $authSettings, $request){
        $registration['createdIp'] = $request->getClientIp();

        if(isset($authSettings['register_protective'])){
            $status=$this->protectiveRule($authSettings['register_protective'],$registration['createdIp']);
            if(!$status){
                return  true;
            }
        }
    }
}
