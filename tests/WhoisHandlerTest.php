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

    public function testGetAvailabilityDetails()
    {
        $whoisHandler = WhoisHandler::whois('example.com');
        $details = $whoisHandler->getAvailabilityDetails();
        
        $this->assertArrayHasKey('original_library_result', $details);
        $this->assertArrayHasKey('contains_availability_keywords', $details);
        $this->assertArrayHasKey('is_response_too_short', $details);
        $this->assertArrayHasKey('contains_no_match_patterns', $details);
        $this->assertArrayHasKey('tld_specific_patterns', $details);
        $this->assertArrayHasKey('domain_status_indicators', $details);
        $this->assertArrayHasKey('final_availability', $details);
        $this->assertArrayHasKey('whois_message_length', $details);
        $this->assertArrayHasKey('whois_message_preview', $details);
        
        $this->assertIsBool($details['original_library_result']);
        $this->assertIsBool($details['contains_availability_keywords']);
        $this->assertIsBool($details['is_response_too_short']);
        $this->assertIsBool($details['contains_no_match_patterns']);
        $this->assertIsBool($details['tld_specific_patterns']);
        $this->assertIsBool($details['domain_status_indicators']);
        $this->assertIsBool($details['final_availability']);
        $this->assertIsInt($details['whois_message_length']);
        $this->assertIsString($details['whois_message_preview']);
    }

    public function testEnhancedAvailabilityDetection()
    {
        // Test with a very likely available domain
        $availableDomain = 'thisisaveryunusualdomain' . time() . '.com';
        $whoisHandler = WhoisHandler::whois($availableDomain);
        
        // The enhanced detection should catch availability even if original library doesn't
        $details = $whoisHandler->getAvailabilityDetails();
        
        // At least one detection method should trigger for an available domain
        $detectionMethods = [
            $details['contains_availability_keywords'],
            $details['is_response_too_short'],
            $details['contains_no_match_patterns'],
            $details['tld_specific_patterns'],
            $details['domain_status_indicators']
        ];
        
        $this->assertTrue(
            in_array(true, $detectionMethods),
            'At least one enhanced detection method should work for available domains'
        );
    }
}
