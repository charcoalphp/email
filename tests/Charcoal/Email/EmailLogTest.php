<?php

namespace Charcoals\Tests\Email;

use PHPUnit_Framework_TestCase;

use DateTime;

use Charcoal\Email\EmailLog;

class EmailLogTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var EmailLog
     */
    public $obj;

    public function setUp()
    {
        $container = $GLOBALS['container'];
        $this->obj = new EmailLog([
            'logger' => $container['logger']
        ]);
    }

    public function testSetData()
    {
        $ret = $this->obj->setData([
            'type'         => 'email',
            'action'       => 'foo',
            'message_id'   => 'foobar',
            'campaign'     => 'phpunit',
            'to'           => 'phpunit@example.com',
            'from'         => 'charcoal@locomotive.ca',
            'subject'      => 'Foo bar',
            'send_status'  => 0,
            'send_error'   => 0,
            'sendTs'      => '2010-01-02 03:45:00',
            'ip'           => '1.2.3.4',
            'session_id'   => 'foobar'
        ]);
        $this->assertSame($this->obj, $ret);

        $this->assertEquals('email', $this->obj['type']);
        $this->assertEquals('foo', $this->obj['action']);
        $this->assertEquals('foobar', $this->obj['messageId']);
        $this->assertEquals('phpunit', $this->obj['campaign']);
        $this->assertEquals('phpunit@example.com', $this->obj['to']);
        $this->assertEquals('charcoal@locomotive.ca', $this->obj['from']);
        $this->assertEquals('Foo bar', $this->obj['subject']);
        $this->assertEquals(0, $this->obj['sendStatus']);
        $this->assertEquals(0, $this->obj['sendError']);
        $this->assertEquals(new DateTime('2010-01-02 03:45:00'), $this->obj['sendTs']);
        $this->assertEquals('1.2.3.4', $this->obj['ip']);
        $this->assertEquals('foobar', $this->obj['sessionId']);
    }

    public function testKey()
    {
        $this->assertEquals('id', $this->obj['key']);
    }
}
