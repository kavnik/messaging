<?php
namespace Vda\Messaging;

use Vda\ServiceIntegration\Event\IEventService;
use Vda\ServiceIntegration\Event\Event;
use Vda\Util\BeanUtil;

class EventService implements IEventService
{
    /**
     * @var IMessengerFactory
     */
    private $messengerFactory;

    /**
     * @var IMessageProducer
     */
    private $publisher;

    private $publisherId;

    /**
     * @var \Log_Logger
     */
    private $log;

    public function __construct(
        IMessengerFactory $messengerFactory,
        $publisherId = 'event-publisher'
    ) {
        $this->messengerFactory = $messengerFactory;
        $this->publisherId = $publisherId;
        $this->log = \Log_LoggerFactory::getLogger('service-event-publishing');
    }

    public function publish(Event $event, $suppressExceptions = false)
    {
        try {
            if (empty($this->publisher)) {
                $this->publisher = $this->messengerFactory->createMessenger(
                    $this->publisherId
                );
            }

            $this->publisher->send(
                '/topic/' . trim($event->getChannel(), '/'),
                $this->eventToMessage($event)
            );
        } catch (MessagingException $e) {
            $this->log->warn("Message broadcast failed", $e);

            if (!$suppressExceptions) {
                throw new \RuntimeException("Failed to broadcast event", 0, $e);
            }
        }
    }

    private function sendMessage(Message $message)
    {
    }

    private function eventToMessage(Event $event)
    {
        $message = new Message(
            BeanUtil::toJson($event, Event::getTransientFields())
        );

        $message->setPersistent($event->isPersistent());
        $message->setPriority($event->getPriority());

        return $message;
    }
}
