<?php
namespace Vda\Messaging\Stomp;

use \Vda\Messaging\IMessenger;
use \Vda\Messaging\Message;
use \Vda\Messaging\MessagingException;
use \Vda\Messaging\Stomp\Client\IStompClient;
use \Vda\Messaging\Subscription;

class StompMessenger implements IMessenger
{
    /**
     * @var IStompClient
     */
    private $stomp;
    private $subscriptions = array();
    private $lastActivityTime;
    private $checkForTimeoutInterval = 30;

    public function __construct(IStompClient $stomp)
    {
        $this->stomp = $stomp;
    }

    public function send($destination, Message $message)
    {
        $this->ensureConnected();

        try {
            $msg = $this->stomp->adaptMessage($message);

            if ($this->lastActivityTime < time() - $this->checkForTimeoutInterval) {
                $msg->setHeader('receipt', 'foo');
            }

            $this->stomp->send($destination, $msg);

            $this->lastActivityTime = time();
        } catch (MessagingException $e) {
            $this->stomp->disconnect();
            throw $e;
        }
    }

    public function subscribe(Subscription $subscription)
    {
        if (array_key_exists($subscription->getId(), $this->subscriptions)) {
            throw new MessagingException(
                "Subscription '{$subscription->getId()}' is already exists for this connection"
            );
        }

        $this->ensureConnected();

        $this->subscriptions[$subscription->getId()] = $subscription;

        $this->processSubscription($subscription);
    }

    public function unsubscribe(Subscription $subscription)
    {
        $this->ensureConnected();

        if (array_key_exists($subscription->getId(), $this->subscriptions)) {
            unset($this->subscriptions[$subscription->getId()]);
        }

        $this->stomp->unsubscribe(
            $subscription->getDestination(),
            $this->stomp->adaptSubscription($subscription, 'UNSUBSCRIBE')->getHeaders()
        );
    }

    public function receive($timeout = -1)
    {
        $this->ensureConnected();

        $this->stomp->setReadTimeout($timeout);

        try {
            $m = $this->stomp->readMessage();
        } catch (MessagingException $e) {
            $this->stomp->disconnect();
            throw $e;
        }

        if (!is_null($m)) {
            return new Message($m->getBody(), $m->getHeader('message-id'));
        }

        return null;
    }

    public function ack($messageId)
    {
        $this->setLastActivityTime();

        $this->stomp->ack($messageId);
    }

    private function ensureConnected()
    {
        if (!$this->stomp->isConnected()) {
            $this->stomp->connect();

            $this->setLastActivityTime();

            foreach ($this->subscriptions as $s) {
                $this->processSubscription($s);
            }
        }
    }

    private function processSubscription(Subscription $subscription)
    {
        $this->stomp->subscribe(
            $subscription->getDestination(),
            $this->stomp->adaptSubscription($subscription)->getHeaders()
        );
    }

    private function setLastActivityTime()
    {
        $this->lastActivityTime = time();
    }
}
