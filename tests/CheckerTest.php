<?php

use MonoVM\WhoisPhp\Checker;
use PHPUnit\Framework\TestCase;


final class CheckerTest extends TestCase
{
    public function testSingleDomainWhoisWithTLD()
    {
        $this->assertArrayHasKey('monovm.com', Checker::whois('monovm.com'), 'No whois result for monovm.com');
    }

    public function testSingleDomainWhoisWithInvalidTLD()
    {
        $this->assertEquals('invalid', Checker::whois('monovm.aninvalidtld')['monovm.aninvalidtld'], 'Invalid status for invalid tld');
    }

    public function testSingleDomainWhoisWithoutTLD()
    {
        $result = Checker::whois('monovm', ['popularTLDs' => ['.info', '.net']]);
        $this->assertArrayHasKey('monovm.net', $result, 'No whois result for monovm.net');
    }

    public function testMultipleDomainsWhois()
    {
        $domains = ['monovm.com', 'google.com', 'bing'];
        $result = Checker::whois($domains);
        $this->assertGreaterThanOrEqual(count($domains), count(array_keys($result)), 'Group whois results count should at least be equal to domains count');
    }
}