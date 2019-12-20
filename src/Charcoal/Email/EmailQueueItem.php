<?php

namespace Charcoal\Email;

use Exception;
use InvalidArgumentException;

// Module `pimple/pimple` dependencies
use Pimple\Container;

// Module `charcoal/factory` dependencies
use Charcoal\Factory\FactoryInterface;

// Module `charcoal-core` dependencies
use Charcoal\Model\AbstractModel;

// Module `charcoal-queue` dependencies
use Charcoal\Queue\QueueItemInterface;
use Charcoal\Queue\QueueItemTrait;

// Intra-module dependencies
use \Charcoal\Email\Email;

/**
 * Email queue item.
 */
class EmailQueueItem extends AbstractModel implements QueueItemInterface
{
    use QueueItemTrait;
    use EmailAwareTrait;

    /**
     * The queue item ID.
     *
     * @var string|null $ident
     */
    protected $ident;

    /**
     * The recipient's email address.
     *
     * @var string $to
     */
    protected $to;

    /**
     * The sender's email address.
     *
     * @var string $from
     */
    protected $from;

    /**
     * The email subject.
     *
     * @var string $subject.
     */
    protected $subject;

    /**
     * The HTML message body.
     *
     * @var string $msgHtml
     */
    protected $msgHtml;

    /**
     * The plain-text message body.
     *
     * @var string $msgTxt
     */
    protected $msgTxt;

    /**
     * The campaign ID.
     *
     * @var string $campaign
     */
    protected $campaign;

    /**
     * @var FactoryInterface $emailFactory
     */
    protected $emailFactory;



    /**
     * Get the primary key that uniquely identifies each queue item.
     *
     * @return string
     */
    public function key()
    {
        return 'id';
    }

    /**
     * Set the queue item's ID.
     *
     * @param  string|null $ident The unique queue item identifier.
     * @throws InvalidArgumentException If the identifier is not a string.
     * @return self
     */
    public function setIdent($ident)
    {
        if ($ident === null) {
            $this->ident = null;
            return $this;
        }

        if (!is_string($ident)) {
            throw new InvalidArgumentException(
                'Ident needs to be a string'
            );
        }

        $this->ident = $ident;

        return $this;
    }

    /**
     * Set the recipient's email address.
     *
     * @param  string|array $email An email address.
     * @return self
     */
    public function setTo($email)
    {
        $this->to = $this->parseEmail($email);
        return $this;
    }

    /**
     * Set the sender's email address.
     *
     * @param  string|array $email An email address.
     * @return self
     */
    public function setFrom($email)
    {
        $this->from = $this->parseEmail($email);
        return $this;
    }

    /**
     * Set the email subject.
     *
     * @param  string $subject The email subject.
     * @throws InvalidArgumentException If the subject is not a string.
     * @return self
     */
    public function setSubject($subject)
    {
        if (!is_string($subject)) {
            throw new InvalidArgumentException(
                'Subject needs to be a string'
            );
        }

        $this->subject = $subject;

        return $this;
    }

    /**
     * Set the email's HTML message body.
     *
     * @param  string $body The HTML message body.
     * @throws InvalidArgumentException If the message is not a string.
     * @return self
     */
    public function setMsgHtml($body)
    {
        if (!is_string($body)) {
            throw new InvalidArgumentException(
                'HTML message needs to be a string'
            );
        }

        $this->msgHtml = $body;

        return $this;
    }

    /**
     * Set the email's plain-text message body.
     *
     * @param  string $body The plain-text mesage body.
     * @throws InvalidArgumentException If the message is not a string.
     * @return self
     */
    public function setMsgTxt($body)
    {
        if (!is_string($body)) {
            throw new InvalidArgumentException(
                'Plan-text message needs to be a string'
            );
        }

        $this->msgTxt = $body;

        return $this;
    }

    /**
     * Set the campaign ID.
     *
     * @param  string $campaign The campaign identifier.
     * @throws InvalidArgumentException If the campaign is not a string.
     * @return self
     */
    public function setCampaign($campaign)
    {
        if (!is_string($campaign)) {
            throw new InvalidArgumentException(
                'Campaign ID needs to be a string'
            );
        }

        $this->campaign = $campaign;

        return $this;
    }

    /**
     * Process the item.
     *
     * @param  callable $callback        An optional callback routine executed after the item is processed.
     * @param  callable $successCallback An optional callback routine executed when the item is resolved.
     * @param  callable $failureCallback An optional callback routine executed when the item is rejected.
     * @return boolean|null  Success / Failure
     */
    public function process(
        callable $callback = null,
        callable $successCallback = null,
        callable $failureCallback = null
    ) {
        if ($this->processed() === true) {
            // Do not process twice, ever.
            return null;
        }

        $email = $this['emailFactory']->create('email');

        $email->setData($this->data());

        try {
            $res = $email->send();
            if ($res === true) {
                $this->setProcessed(true);
                $this->setProcessedDate('now');
                $this->update(['processed', 'processed_date']);

                if ($successCallback !== null) {
                    $successCallback($this);
                }
            } else {
                if ($failureCallback !== null) {
                    $failureCallback($this);
                }
            }

            if ($callback !== null) {
                $callback($this);
            }

            return $res;
        } catch (Exception $e) {
            // Todo log error
            if ($failureCallback !== null) {
                $failureCallback($this);
            }

            return false;
        }
    }

    /**
     * @param Container $container Pimple DI container.
     * @return void
     */
    protected function setDependencies(Container $container)
    {
        parent::setDependencies($container);
        $this->setEmailFactory($container['email/factory']);
    }

    /**
     * Hook called before saving the item.
     *
     * @return boolean
     * @see \Charcoal\Queue\QueueItemTrait::preSaveQueueItem()
     */
    protected function preSave()
    {
        parent::preSave();

        $this->preSaveQueueItem();

        return true;
    }

    /**
     * @param FactoryInterface $factory The factory to create email objects.
     * @return void
     */
    private function setEmailFactory(FactoryInterface $factory)
    {
        $this->emailFactory = $factory;
    }
}
