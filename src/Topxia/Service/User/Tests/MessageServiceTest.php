<?php

namespace Topxia\Service\User\Tests;

use Topxia\Service\Common\BaseTestCase;
use Topxia\Service\User\MessageService;
use Topxia\Service\User\UserService;
use Topxia\Common\ArrayToolkit;

class MessageServiceTest extends BaseTestCase
{
    /**
     * @group current
     */
    public function testDeleteMessagesByAdmin()
    {
        /*create*/
        $sender = $this->createSender();
        $receiver = $this->createReceiver();
        $content = "testSendMessage";
        for ($i=0; $i < 4; $i++) { 
            $this->getMessageService()->sendMessage($sender['id'], $receiver['id'], $i.$content.$i);
        }

        $conversation = $this->getMessageService()->getConversationByFromIdAndToId($receiver['id'],$sender['id']);
        $messageByConversation = $this->getMessageService()->findConversationMessages($conversation['id'], 0, 10);
        $this->getMessageService()->deleteMessagesByIds(ArrayToolkit::column($messageByConversation, 'id'));
    }

    public function testSendMessage()
    {
        /*create*/
        $sender = $this->createSender();
        $receiver = $this->createReceiver();
        $content = "testSendMessage";
        for ($i=0; $i < 4; $i++) { 
          $this->getMessageService()->sendMessage($sender['id'], $receiver['id'], $i.$content.$i);
        }

        $conversationToSender = $this->getMessageService()->getConversationByFromIdAndToId($receiver['id'],$sender['id']);
        $messagesByConversationToSender = $this->getMessageService()->findConversationMessages($conversationToSender['id'], 0, 10);
        
        /* test conversation */

        $this->assertEquals($conversationToSender['latestMessageUserId'], $sender['id']);
        $this->assertEquals($conversationToSender['toId'], $sender['id']);
        $this->assertEquals($conversationToSender['fromId'], $receiver['id']);
        $this->assertEquals($conversationToSender['messageNum'], 4);
        $this->assertEquals($conversationToSender['unreadNum'], 0);
        $this->assertEquals($conversationToSender['latestMessageContent'], "3testSendMessage3");

        /* test message */
        foreach ($messagesByConversationToSender as $messageByConversationToSender) {
            $this->assertEquals($messageByConversationToSender['fromId'], $sender['id']);
            $this->assertEquals($messageByConversationToSender['toId'], $receiver['id']);
        }

        $conversationToReceiver = $this->getMessageService()->getConversationByFromIdAndToId($sender['id'], $receiver['id']);
        $messagesByConversationToReceiver = $this->getMessageService()->findConversationMessages($conversationToReceiver['id'], 0, 10);
        
        /* test conversation */

        $this->assertEquals($conversationToReceiver['latestMessageUserId'], $sender['id']);
        $this->assertEquals($conversationToReceiver['toId'], $receiver['id']);
        $this->assertEquals($conversationToReceiver['fromId'], $sender['id']);
        $this->assertEquals($conversationToReceiver['messageNum'], 4);
        $this->assertEquals($conversationToReceiver['unreadNum'], 4);
        $this->assertEquals($conversationToReceiver['latestMessageContent'], "3testSendMessage3");
        
        /* test message */
        foreach ($messagesByConversationToReceiver as $messageByConversationToReceiver) {
            $this->assertEquals($messageByConversationToReceiver['fromId'], $sender['id']);
            $this->assertEquals($messageByConversationToReceiver['toId'], $receiver['id']);
        }

    }

    public function testShowConversation()
    {
        $sender = $this->createSender();
        $receiver = $this->createReceiver();
        $content = "testSendMessage";
        for ($i=0; $i < 4; $i++) { 
          $this->getMessageService()->sendMessage($sender['id'], $receiver['id'], $i.$content.$i);
        }

        /* get conversationToSender*/
        $conversationToSender = $this->getMessageService()->
            getConversationByFromIdAndToId($receiver['id'], $sender['id']);
        $updatedConversation = $this->getMessageService()->markConversationRead($conversationToSender['id']);
        
        /*测试conversation*/
        $this->assertEquals($updatedConversation['messageNum'], 4);
        $this->assertEquals($updatedConversation['unreadNum'], 0);
        $this->assertEquals($updatedConversation['fromId'], $receiver['id']);
        $this->assertEquals($updatedConversation['toId'], $sender['id']);
        $this->assertEquals($updatedConversation['latestMessageUserId'], $sender['id']);
        $this->assertEquals($updatedConversation['latestMessageContent'], "3testSendMessage3");

        /*get conversationToReceiver*/
        $conversationToReceiver = $this->getMessageService()->
            getConversationByFromIdAndToId($sender['id'], $receiver['id']);
        $updatedConversation = $this->getMessageService()->markConversationRead($conversationToReceiver['id']);

         /*测试conversation*/
        $this->assertEquals($updatedConversation['messageNum'], 4);
        $this->assertEquals($updatedConversation['unreadNum'], 0);
        $this->assertEquals($updatedConversation['toId'], $receiver['id']);
        $this->assertEquals($updatedConversation['fromId'], $sender['id']);
        $this->assertEquals($updatedConversation['latestMessageUserId'], $sender['id']);
        $this->assertEquals($updatedConversation['latestMessageContent'], "3testSendMessage3");

    }
    
    public function testDeleteMessageBySenderUser(){
        $sender = $this->createSender();
        $receiver = $this->createReceiver();
        $content = "testSendMessage";
        for ($i=0; $i < 4; $i++) { 
          $this->getMessageService()->sendMessage($sender['id'], $receiver['id'], $i.$content.$i);
        }
        $conversationToSender= $this->getMessageService()->
            getConversationByFromIdAndToId($receiver['id'], $sender['id']);
        $messagesCount = $this->getMessageService()->getConversationMessageCount($conversationToSender['id']);
        $messagesOfConversation = $this->getMessageService()->findConversationMessages($conversationToSender['id'], 0 ,$messagesCount);
        $updatedConversation = $this->getMessageService()->getConversation($conversationToSender['id']);
       
        /* test conversation */
        foreach ($messagesOfConversation as $messageOfConversation) {
           $this->getMessageService()->deleteConversationMessage($conversationToSender['id'], $messageOfConversation['id']);
            $this->assertEquals($updatedConversation['unreadNum'], 0);    
            $this->assertEquals($updatedConversation['fromId'], $receiver['id']);    
            $this->assertEquals($updatedConversation['toId'], $sender['id']);    
            $this->assertEquals($updatedConversation['latestMessageUserId'], $sender['id']);    
            $this->assertEquals($updatedConversation['latestMessageContent'], "3testSendMessage3");    
        }
        $updatedConversation = $this->getMessageService()->getConversation($conversationToSender['id']);
         $this->assertEquals($updatedConversation['messageNum'], 0);
         $this->assertEquals($updatedConversation['unreadNum'], 0); 

        /* test relation count*/
        $messagesCount = $this->getMessageService()->getConversationMessageCount($conversationToSender['id']);
        $this->assertEquals($messagesCount, 0);
    }


    public function testDeleteMessageByReceiverUser(){
        $sender = $this->createSender();
        $receiver = $this->createReceiver();
        $content = "testSendMessage";
        for ($i=0; $i < 4; $i++) { 
          $this->getMessageService()->sendMessage($sender['id'], $receiver['id'], $i.$content.$i);
        }
        $conversationToReceiver= $this->getMessageService()->
            getConversationByFromIdAndToId($sender['id'], $receiver['id']);
        $messagesCount = $this->getMessageService()->getConversationMessageCount($conversationToReceiver['id']);
        $messagesOfConversation = $this->getMessageService()->findConversationMessages($conversationToReceiver['id'], 0 ,$messagesCount);
        $updatedConversation = $this->getMessageService()->getConversation($conversationToReceiver['id']);
       
        /* test conversation */
        foreach ($messagesOfConversation as $messageOfConversation) {
           $this->getMessageService()->deleteConversationMessage($conversationToReceiver['id'], $messageOfConversation['id']); 
            $this->assertEquals($updatedConversation['fromId'], $sender['id']);    
            $this->assertEquals($updatedConversation['toId'], $receiver['id']);    
            $this->assertEquals($updatedConversation['latestMessageUserId'], $sender['id']);    
            $this->assertEquals($updatedConversation['latestMessageContent'], "3testSendMessage3");    
        }
        $updatedConversation = $this->getMessageService()->getConversation($conversationToReceiver['id']);
        $this->assertEquals($updatedConversation['messageNum'], 0);
        $this->assertEquals($updatedConversation['unreadNum'], 0);

        /* test relation count*/
        $messagesCount = $this->getMessageService()->getConversationMessageCount($conversationToReceiver['id']);
        $this->assertEquals($messagesCount, 0);
    }

    public function testDeleteConversationByUser(){
        $sender = $this->createSender();
        $receiver = $this->createReceiver();
        $content = "testSendMessage";
        for ($i=0; $i < 4; $i++) { 
          $this->getMessageService()->sendMessage($sender['id'], $receiver['id'], $i.$content.$i);
        }
        /*  for sender */
        $conversationToSender= $this->getMessageService()->
            getConversationByFromIdAndToId($receiver['id'], $sender['id']);
        $this->getMessageService()->deleteConversation($conversationToSender['id']);
        $conversationToSender = $this->getMessageService()->getConversation($conversationToSender['id']);
        $this->assertEmpty($conversationToSender);
        $messagesCount = $this->getMessageService()->getConversationMessageCount($conversationToSender['id']);
        $this->assertEmpty($messagesCount);

        /* for receiver */
        $conversationToReceiver = $this->getMessageService()->
            getConversationByFromIdAndToId($sender['id'], $receiver['id']);
        $this->getMessageService()->deleteConversation($conversationToReceiver['id']);
        $conversationToReceiver = $this->getMessageService()->getConversation($conversationToReceiver['id']);
        $this->assertEmpty($conversationToReceiver);
        $messagesCount = $this->getMessageService()->getConversationMessageCount($conversationToReceiver['id']);
        $this->assertEmpty($messagesCount);

    }


    protected function createSender(){
    $sender = array();
    $sender['email'] = "sender@sender.com";
    $sender['nickname'] = "sender";
    $sender['password']= "sender";
    return $this->getUserService()->register($sender);
    }

    protected function createReceiver(){
    $receiver = array();
    $receiver['email'] = "receiver@receiver.com";
    $receiver['nickname'] = "receiver";
    $receiver['password']= "receiver";
    return $this->getUserService()->register($receiver);
    }

    protected function getUserService(){
    return $this->getServiceKernel()->createService('User.UserService');
    }

    protected function getMessageService(){
    return $this->getServiceKernel()->createService('User.MessageService');
    }

}