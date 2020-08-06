<?php

declare(strict_types=1);

namespace Charcoal\Email;

use Exception;
use InvalidArgumentException;

// // From 'psr/log' (PSR-3)
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

// From 'phpmailer/phpmailer'
use PHPMailer\PHPMailer\PHPMailer;

// From 'locomotivemtl/charcoal-config'
use Charcoal\Config\AbstractEntity;
use Charcoal\Config\ConfigurableInterface;
use Charcoal\Config\ConfigurableTrait;

// From 'locomotivemtl/charcoal-factory'
use Charcoal\Factory\FactoryInterface;

// From 'locomotivemtl/charcoal-view'
use Charcoal\View\GenericView;
use Charcoal\View\ViewableInterface;
use Charcoal\View\ViewableTrait;

// From 'locomotivemtl/charcoal-queue'
use Charcoal\Queue\QueueableInterface;
use Charcoal\Queue\QueueableTrait;

/**
 * Default implementation of the `EmailInterface`.
 */
class Email extends AbstractEntity implements
    ConfigurableInterface,
    EmailInterface,
    LoggerAwareInterface,
    QueueableInterface,
    ViewableInterface
{
    use ConfigurableTrait;
    use LoggerAwareTrait;
    use QueueableTrait;
    use ViewableTrait;
    use EmailAwareTrait;

    /**
     * The campaign ID.
     *
     * @var string $campaign
     */
    private $campaign;

    /**
     * The recipient email address(es).
     *
     * @var array $to
     */
    private $to = [];

    /**
     * The CC recipient email address(es).
     *
     * @var array $cc
     */
    private $cc = [];

    /**
     * The BCC recipient email address(es).
     *
     * @var array $bcc
     */
    private $bcc = [];

    /**
     * The sender's email address.
     *
     * @var string $from
     */
    private $from;

    /**
     * The email address to reply to the message.
     *
     * @var string $replyTo
     */
    private $replyTo;

    /**
     * The email subject.
     *
     * @var string $subject
     */
    private $subject;

    /**
     * The HTML message body.
     *
     * @var string $msgHtml
     */
    private $msgHtml;

    /**
     * The plain-text message body.
     *
     * @var string $msgTxt
     */
    private $msgTxt;

    /**
     * @var array $attachments
     */
    private $attachments = [];

    /**
     * Whether the email should be logged.
     *
     * @var boolean $log
     */
    private $log;

    /**
     * Whether the email should be tracked.
     *
     * @var boolean $track
     */
    private $track;

    /**
     * The data to pass onto the view controller.
     *
     * @var array $templateData
     */
    private $templateData = [];

    /**
     * @var PHPMailer $phpMailer PHP Mailer instance.
     */
    private $phpMailer;

    /**
     * @var FactoryInterface $templateFactory
     */
    private $templateFactory;

    /**
     * @var FactoryInterface $queueItemFactory
     */
    private $queueItemFactory;

    /**
     * @var FactoryInterface $logFactory
     */
    private $logFactory;

    /**
     * Construct a new Email object.
     *
     * @param array $data Dependencies and settings.
     */
    public function __construct(array $data)
    {
        $this->phpMailer = new PHPMailer(true);
        $this->setLogger($data['logger']);
        $this->setView($data['view']);
        $this->setConfig($data['config']);
        $this->setTemplateFactory($data['template_factory']);
        $this->setQueueItemFactory($data['queue_item_factory']);
        $this->setLogFactory($data['log_factory']);
    }

    /**
     * Set the campaign ID.
     *
     * @param  string $campaign The campaign identifier.
     * @return self
     */
    public function setCampaign(string $campaign)
    {

        $this->campaign = $campaign;

        return $this;
    }

    /**
     * Get the campaign identifier.
     *
     * If it has not been explicitely set, it will be auto-generated (with uniqid).
     *
     * @return string
     */
    public function campaign()
    {
        if ($this->campaign === null) {
            $this->campaign = $this->generateCampaign();
        }

        return $this->campaign;
    }

    /**
     * Set the recipient email address(es).
     *
     * @param string|array $email The recipient email address(es).
     * @throws InvalidArgumentException If the email address is invalid.
     * @return self
     */
    public function setTo($email)
    {
        if (is_string($email)) {
            $email = [ $email ];
        }

        if (!is_array($email)) {
            throw new InvalidArgumentException(
                'Must be an array of recipients.'
            );
        }

        $this->to = [];

        // At this point, `$email` can be an _email array_ or an _array of emails_...
        if (isset($email['email'])) {
            // Means we're not dealing with multiple emails
            $this->addTo($email);
        } else {
            foreach ($email as $recipient) {
                $this->addTo($recipient);
            }
        }

        return $this;
    }

    /**
     * Add a recipient email address.
     *
     * @param  mixed $email The recipient email address to add.
     * @throws InvalidArgumentException If the email address is invalid.
     * @return self
     */
    public function addTo($email)
    {
        $this->to[] = $this->parseEmail($email);
        return $this;
    }

    /**
     * Get the recipient's email addresses.
     *
     * @return string[]
     */
    public function to()
    {
        return $this->to;
    }

    /**
     * Set the carbon copy (CC) recipient email address(es).
     *
     * @param string|array $email The CC recipient email address(es).
     * @throws InvalidArgumentException If the email address is invalid.
     * @return self
     */
    public function setCc($email)
    {
        if (is_string($email)) {
            $email = [ $email ];
        }

        if (!is_array($email)) {
            throw new InvalidArgumentException(
                'Must be an array of CC recipients.'
            );
        }

        $this->cc = [];

        // At this point, `$email` can be an _email array_ or an _array of emails_...
        if (isset($email['email'])) {
            // Means we're not dealing with multiple emails
            $this->addCc($email);
        } else {
            foreach ($email as $recipient) {
                $this->addCc($recipient);
            }
        }

        return $this;
    }

    /**
     * Add a CC recipient email address.
     *
     * @param mixed $email The CC recipient email address to add.
     * @throws InvalidArgumentException If the email address is invalid.
     * @return self
     */
    public function addCc($email)
    {
        $this->cc[] = $this->parseEmail($email);
        return $this;
    }

    /**
     * Get the CC recipient's email address.
     *
     * @return string[]
     */
    public function cc()
    {
        return $this->cc;
    }

    /**
     * Set the blind carbon copy (BCC) recipient email address(es).
     *
     * @param string|array $email The BCC recipient email address(es).
     * @throws InvalidArgumentException If the email address is invalid.
     * @return self
     */
    public function setBcc($email)
    {
        if (is_string($email)) {
            // Means we have a straight email
            $email = [ $email ];
        }

        if (!is_array($email)) {
            throw new InvalidArgumentException(
                'Must be an array of BCC recipients.'
            );
        }

        $this->bcc = [];

        // At this point, `$email` can be an _email array_ or an _array of emails_...
        if (isset($email['email'])) {
            // Means we're not dealing with multiple emails
            $this->addBcc($email);
        } else {
            foreach ($email as $recipient) {
                $this->addBcc($recipient);
            }
        }

        return $this;
    }

    /**
     * Add a BCC recipient email address.
     *
     * @param mixed $email The BCC recipient email address to add.
     * @throws InvalidArgumentException If the email address is invalid.
     * @return self
     */
    public function addBcc($email)
    {
        $this->bcc[] = $this->parseEmail($email);
        return $this;
    }

    /**
     * Get the BCC recipient's email address.
     *
     * @return string[]
     */
    public function bcc()
    {
        return $this->bcc;
    }

    /**
     * Set the sender's email address.
     *
     * @param  string|array $email An email address.
     * @throws InvalidArgumentException If the email is not a string or an array.
     * @return self
     * @todo   Implement optional "Sender" field.
     */
    public function setFrom($email)
    {
        $this->from = $this->parseEmail($email);
        return $this;
    }

    /**
     * Get the sender's email address.
     *
     * @return string
     */
    public function from()
    {
        if ($this->from === null) {
            $this->setFrom($this->config()->defaultFrom());
        }

        return $this->from;
    }

    /**
     * Set email address to reply to the message.
     *
     * @param  mixed $email The sender's "Reply-To" email address.
     * @throws InvalidArgumentException If the email is not a string or an array.
     * @return self
     */
    public function setReplyTo($email)
    {
        $this->replyTo = $this->parseEmail($email);
        return $this;
    }

    /**
     * Get email address to reply to the message.
     *
     * @return string
     */
    public function replyTo()
    {
        if ($this->replyTo === null) {
            $this->replyTo = $this->config()->defaultReplyTo();
        }

        return $this->replyTo;
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
     * Get the email subject.
     *
     * @return string The emails' subject.
     */
    public function subject()
    {
        return $this->subject;
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
     * Get the email's HTML message body.
     *
     * If the message is not explitely set, it will be
     * auto-generated from a template view.
     *
     * @return string
     */
    public function msgHtml()
    {
        if ($this->msgHtml === null) {
            $this->msgHtml = $this->generateMsgHtml();
        }
        return $this->msgHtml;
    }

    /**
     * Set the email's plain-text message body.
     *
     * @param string $body The message's text body.
     * @throws InvalidArgumentException If the parameter is invalid.
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
     * Get the email's plain-text message body.
     *
     * If the plain-text message is not explitely set,
     * it will be auto-generated from the HTML message.
     *
     * @return string
     */
    public function msgTxt()
    {
        if ($this->msgTxt === null) {
            $this->msgTxt = $this->stripHtml($this->msgHtml());
        }

        return $this->msgTxt;
    }

    /**
     * Set the email's attachments.
     *
     * @param  array $attachments The file attachments.
     * @return self
     */
    public function setAttachments(array $attachments)
    {
        foreach ($attachments as $att) {
            $this->addAttachment($att);
        }

        return $this;
    }

    /**
     * Add an attachment to the email.
     *
     * @param  mixed $attachment A single file attachment.
     * @return self
     */
    public function addAttachment($attachment)
    {
        $this->attachments[] = $attachment;
        return $this;
    }

    /**
     * Get the email's attachments.
     *
     * @return array
     */
    public function attachments()
    {
        return $this->attachments;
    }

    /**
     * Enable or disable logging for this particular email.
     *
     * @param  boolean $log The log flag.
     * @return self
     */
    public function setLog($log)
    {
        $this->log = !!$log;
        return $this;
    }

    /**
     * Determine if logging is enabled for this particular email.
     *
     * @return boolean
     */
    public function log()
    {
        if ($this->log === null) {
            $this->log = $this->config()->defaultLog();
        }
        return $this->log;
    }

    /**
     * Enable or disable tracking for this particular email.
     *
     * @param boolean $track The track flag.
     * @return self
     */
    public function setTrack($track)
    {
        $this->track = !!$track;
        return $this;
    }

    /**
     * Determine if tracking is enabled for this particular email.
     *
     * @return boolean
     */
    public function track()
    {
        if ($this->track === null) {
            $this->track = $this->config()->defaultTrack();
        }
        return $this->track;
    }

    /**
     * Send the email to all recipients
     *
     * @return boolean Success / Failure.
     * @todo Implement methods and property for toggling rich-text vs. plain-text
     *       emails (`$mail->isHTML(true)`).
     */
    public function send(): bool
    {
        $this->logger->debug(
            'Attempting to send an email',
            $this->to()
        );

        $mail = $this->phpMailer;

        try {
            $this->setSmtpOptions($mail);

            $mail->CharSet = 'UTF-8';

            // Setting reply-to field, if required.
            $replyTo = $this->replyTo();
            if ($replyTo) {
                $replyArr = $this->emailToArray($replyTo);
                $mail->addReplyTo($replyArr['email'], $replyArr['name']);
            }

            // Setting from (sender) field.
            $from = $this->from();
            $fromArr = $this->emailToArray($from);
            $mail->setFrom($fromArr['email'], $fromArr['name']);

            // Setting to (recipients) field(s).
            $to = $this->to();
            foreach ($to as $recipient) {
                $toArr = $this->emailToArray($recipient);
                $mail->addAddress($toArr['email'], $toArr['name']);
            }

            // Setting cc (carbon-copy) field(s).
            $cc = $this->cc();
            foreach ($cc as $ccRecipient) {
                $ccArr = $this->emailToArray($ccRecipient);
                $mail->addCC($ccArr['email'], $ccArr['name']);
            }

            // Setting bcc (black-carbon-copy) field(s).
            $bcc = $this->bcc();
            foreach ($bcc as $bccRecipient) {
                $bccArr = $this->emailToArray($bccRecipient);
                $mail->addBCC($bccArr['email'], $bccArr['name']);
            }

            // Setting attachment(s), if required.
            $attachments = $this->attachments();
            foreach ($attachments as $att) {
                $mail->addAttachment($att);
            }

            $mail->isHTML(true);

            $mail->Subject = $this->subject();
            $mail->Body    = $this->msgHtml();
            $mail->AltBody = $this->msgTxt();

            $ret = $mail->send();

            $this->logSend($ret, $mail);

            return $ret;
        } catch (Exception $e) {
            $this->logger->error(
                sprintf('Error sending email: %s', $e->getMessage())
            );
        }
    }

    /**
     * Set the SMTP's options for PHPMailer.
     *
     * @param PHPMailer $mail The PHPMailer to setup.
     * @return void
     */
    protected function setSmtpOptions(PHPMailer $mail)
    {
        $config = $this->config();
        if (!$config['smtp']) {
            return;
        }

        $this->logger->debug(
            sprintf('Using SMTP "%s" server to send email', $config['smtp_hostname'])
        );

        $mail->isSMTP();
        $mail->Host       = $config['smtp_hostname'];
        $mail->Port       = $config['smtp_port'];
        $mail->SMTPAuth   = $config['smtp_auth'];
        $mail->Username   = $config['smtp_username'];
        $mail->Password   = $config['smtp_password'];
        $mail->SMTPSecure = $config['smtp_security'];
    }

    /**
     * Enqueue the email for each recipient.
     *
     * @param mixed $ts A date/time to initiate the queue processing.
     * @return boolean Success / Failure.
     */
    public function queue($ts = null): bool
    {
        $recipients = $this->to();
        $author     = $this->from();
        $subject    = $this->subject();
        $msgHtml    = $this->msgHtml();
        $msgTxt     = $this->msgTxt();
        $campaign   = $this->campaign();
        $queueId    = $this->queueId();

        foreach ($recipients as $to) {
            if (is_string($to) && !empty($to)) {
                $queueItem = $this->queueItemFactory()->create(EmailQueueItem::class);

                $queueItem->setTo($to);
                $queueItem->setFrom($author);
                $queueItem->setSubject($subject);
                $queueItem->setMsgHtml($msgHtml);
                $queueItem->setMsgTxt($msgTxt);
                $queueItem->setCampaign($campaign);
                $queueItem->setProcessingDate($ts);
                $queueItem->setQueueId($queueId);

                $res = $queueItem->save();
            } else {
                $this->logger->warning('Could not queue email, null or empty value');
            }
        }

        return true;
    }

    /**
     * Log the queue event.
     *
     * @return void
     * @todo Implement log qeueing.
     */
    protected function logQueue()
    {
    }

    /**
     * Set the template data for the view.
     *
     * @param array $data The template data.
     * @return Email Chainable
     */
    public function setTemplateData(array $data)
    {
        $this->templateData = $data;
        return $this;
    }

    /**
     * Get the template data for the view.
     *
     * @return array
     */
    public function templateData()
    {
        return $this->templateData;
    }

    /**
     * Get the custom view controller for rendering
     * the email's HTML message.
     *
     * Unlike typical `ViewableInterface` objects, the view controller is not
     * the email itself but an external "email" template.
     *
     * @see    ViewableInterface::viewController()
     * @return \Charcoal\App\Template\TemplateInterface|array
     */
    public function viewController()
    {
        $templateIdent = $this->templateIdent();

        if (!$templateIdent) {
            return [];
        }

        $templateFactory = clone($this->templateFactory());
        $templateFactory->setDefaultClass(GenericEmailTemplate::class);
        $template = $templateFactory->create($templateIdent);

        $template->setData($this->templateData());

        return $template;
    }

    /**
     * @param FactoryInterface $factory The factory to use to create email template objects.
     * @return Email Chainable
     */
    protected function setTemplateFactory(FactoryInterface $factory)
    {
        $this->templateFactory = $factory;
        return $this;
    }

    /**
     * @return FactoryInterface
     */
    protected function templateFactory()
    {
        return $this->templateFactory;
    }

    /**
     * @param FactoryInterface $factory The factory to use to create email queue item objects.
     * @return Email Chainable
     */
    protected function setQueueItemFactory(FactoryInterface $factory)
    {
        $this->queueItemFactory = $factory;
        return $this;
    }

    /**
     * @return FactoryInterface
     */
    protected function queueItemFactory()
    {
        return $this->queueItemFactory;
    }

    /**
     * @param FactoryInterface $factory The factory to use to create log objects.
     * @return Email Chainable
     */
    protected function setLogFactory(FactoryInterface $factory)
    {
        $this->logFactory = $factory;
        return $this;
    }

    /**
     * @return FactoryInterface
     */
    protected function logFactory()
    {
        return $this->logFactory;
    }

    /**
     * Get the email's HTML message from the template, if applicable.
     *
     * @see    ViewableInterface::renderTemplate()
     * @return string
     */
    protected function generateMsgHtml()
    {
        $templateIdent = $this->templateIdent();

        if (!$templateIdent) {
            $message = '';
        } else {
            $message = $this->renderTemplate($templateIdent);
        }

        return $message;
    }

    /**
     * Generates a unique identifier ideal for a campaign ID.
     *
     * @return string
     */
    protected function generateCampaign()
    {
        return uniqid();
    }

    /**
     * Allow an object to define how the key getter are called.
     *
     * @param string $key The key to get the getter from.
     * @return string The getter method name, for a given key.
     */
    protected function getter($key)
    {
        $getter = $key;
        return $this->camelize($getter);
    }

    /**
     * Allow an object to define how the key setter are called.
     *
     * @param string $key The key to get the setter from.
     * @return string The setter method name, for a given key.
     */
    protected function setter($key)
    {
        $setter = 'set_'.$key;
        return $this->camelize($setter);
    }

    /**
     * Convert an HTML string to plain-text.
     *
     * @param string $html The HTML string to convert.
     * @return string The resulting plain-text string.
     */
    protected function stripHtml($html)
    {
        $str = html_entity_decode($html);

        // Strip HTML (Replace br with newline, remove "invisible" tags and strip other tags)
        $str = preg_replace('#<br[^>]*?>#siu', "\n", $str);
        $str = preg_replace(
            [
                '#<applet[^>]*?.*?</applet>#siu',
                '#<embed[^>]*?.*?</embed>#siu',
                '#<head[^>]*?>.*?</head>#siu',
                '#<noframes[^>]*?.*?</noframes>#siu',
                '#<noscript[^>]*?.*?</noscript>#siu',
                '#<noembed[^>]*?.*?</noembed>#siu',
                '#<object[^>]*?.*?</object>#siu',
                '#<script[^>]*?.*?</script>#siu',
                '#<style[^>]*?>.*?</style>#siu'
            ],
            '',
            $str
        );
        $str = strip_tags($str);

        // Trim whitespace
        $str = str_replace("\t", '', $str);
        $str = preg_replace('#\n\r|\r\n#', "\n", $str);
        $str = preg_replace('#\n{3,}#', "\n\n", $str);
        $str = preg_replace('/ {2,}/', ' ', $str);
        $str = implode("\n", array_map('trim', explode("\n", $str)));
        $str = trim($str)."\n";
        return $str;
    }

    /**
     * Log the send event for each recipient.
     *
     * @param  boolean $result Success or failure.
     * @param  mixed   $mailer The raw mailer.
     * @return void
     */
    protected function logSend($result, $mailer)
    {
        if ($this->log() === false) {
            return;
        }

        if (!$result) {
            $this->logger->error('Email could not be sent.');
        } else {
            $this->logger->debug(
                sprintf('Email "%s" sent successfully.', $this->subject()),
                $this->to()
            );
        }

        $recipients = array_merge(
            $this->to(),
            $this->cc(),
            $this->bcc()
        );

        foreach ($recipients as $to) {
            $log = $this->logFactory()->create('charcoal/email/email-log');

            $log->setQueueId($this->queueId());
            $log->setMessageId($mailer->getLastMessageId());
            $log->setCampaign($this->campaign());
            $log->setSendTs('now');
            $log->setFrom($mailer->From);
            $log->setTo($to);
            $log->setSubject($this->subject());

            if (!empty($mailer->getSMTPInstance()->getError()['smtp_code'])) {
                $log->setErrorCode($mailer->getSMTPInstance()->getError()['smtp_code']);
            }

            $log->save();
        }
    }

    /**
     * Temporary hack to fulfills the Configurable Interface.
     *
     * @return EmailConfig
     */
    public function createConfig()
    {
        // This should really be avoided.
        $this->logger->warning('AbstractEmail::createConfig() was called, but should not.');
        return new \Charcoal\Email\EmailConfig();
    }
}
