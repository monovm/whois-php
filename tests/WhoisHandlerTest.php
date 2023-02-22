<?php

use PHPUnit\Framework\TestCase;
use MonoVM\WhoisPhp\WhoisHandler;

class WhoisHandlerTest extends TestCase
{
    public function testGetTld()
    {
        $whoisHandler = WhoisHandler::whois('example.com');
        $this->assertEquals('.com', $whoisHandler->getTld());
    }

    public function testGetSld()
    {
        $whoisHandler = WhoisHandler::whois('example.com');
        $this->assertEquals('example', $whoisHandler->getSld());
    }

    public function testIsAvailable()
    {
        $whoisHandler = WhoisHandler::whois('example.com');
        $this->assertFalse($whoisHandler->isAvailable());
    }

    public function testIsValid()
    {
        $whoisHandler = WhoisHandler::whois('example.com');
        $this->assertTrue($whoisHandler->isValid());
    }

    public function testGetWhoisMessage()
    {
        $whoisHandler = WhoisHandler::whois('example.com');
        $this->assertStringContainsString('Domain Name: EXAMPLE.COM', $whoisHandler->getWhoisMessage());
    }
}
