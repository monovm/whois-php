<?php

use PHPUnit\Framework\TestCase;
use MonoVM\WhoisPhp\WhoisHandler;
use MonoVM\WhoisPhp\Checker;
use MonoVM\WhoisPhp\AvailabilityDetector;

class IntegrationTest extends TestCase
{
    public function testAvailabilityDetectorStandalone()
    {
        // Test the availability detector with known available indicators
        $availableMessage = "No match for domain test123.com";
        $this->assertTrue(AvailabilityDetector::isAvailable($availableMessage, '.com'));
        
        // Use a more realistic unavailable message with registration details
        $unavailableMessage = "Domain Name: EXAMPLE.COM\n" .
                             "Registrar: Example Registrar Inc.\n" .
                             "Registrar WHOIS Server: whois.example.com\n" .
                             "Registrar URL: http://www.example.com\n" .
                             "Updated Date: 2023-01-01T00:00:00Z\n" .
                             "Creation Date: 2020-01-01T00:00:00Z\n" .
                             "Registry Expiry Date: 2025-01-01T00:00:00Z\n" .
                             "Registrant Name: Example Organization\n" .
                             "Name Server: NS1.EXAMPLE.COM\n" .
                             "Name Server: NS2.EXAMPLE.COM\n" .
                             "DNSSEC: unsigned";
        $this->assertFalse(AvailabilityDetector::isAvailable($unavailableMessage, '.com'));
    }

    public function testWhoisHandlerIntegration()
    {
        // Test with a likely available domain
        $timestamp = time();
        $testDomain = "very-unusual-test-domain-{$timestamp}.com";
        
        $whoisHandler = WhoisHandler::whois($testDomain);
        $details = $whoisHandler->getAvailabilityDetails();
        
        // Should have all the expected keys
        $expectedKeys = [
            'original_library_result',
            'contains_availability_keywords',
            'is_response_too_short',
            'contains_no_match_patterns',
            'tld_specific_patterns',
            'domain_status_indicators',
            'final_availability',
            'whois_message_length',
            'whois_message_preview'
        ];
        
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $details);
        }
        
        // For a very unusual domain, enhanced detection should work
        $this->assertTrue($details['final_availability']);
    }

    public function testCheckerIntegration()
    {
        // Test with multiple domains using Checker class
        $timestamp = time();
        $testDomains = [
            "unusual-test-domain-{$timestamp}.com",
            "another-test-domain-{$timestamp}.net"
        ];
        
        $results = Checker::whois($testDomains);
        
        // Should return results for both domains
        $this->assertCount(2, $results);
        
        foreach ($testDomains as $domain) {
            $this->assertArrayHasKey($domain, $results);
            // Should be either 'available' or 'unavailable' (enhanced detection should work)
            $this->assertContains($results[$domain], ['available', 'unavailable', 'premium']);
        }
    }

    public function testCheckerWithoutTLD()
    {
        // Test Checker with domain without TLD (should check popular TLDs)
        $timestamp = time();
        $domainBase = "unusual-test-{$timestamp}";
        
        $results = Checker::whois($domainBase);
        
        // Should return multiple results for popular TLDs
        $this->assertGreaterThan(1, count($results));
        
        // Should include .com, .net, .org, .info
        $expectedDomains = [
            $domainBase . '.com',
            $domainBase . '.net',
            $domainBase . '.org',
            $domainBase . '.info'
        ];
        
        foreach ($expectedDomains as $expectedDomain) {
            $this->assertArrayHasKey($expectedDomain, $results);
        }
    }

    public function testRegisteredDomainDetection()
    {
        // Test with a known registered domain
        $whoisHandler = WhoisHandler::whois('example.com');
        $this->assertFalse($whoisHandler->isAvailable());
        
        $checker = Checker::whois(['example.com']);
        $this->assertEquals('unavailable', $checker['example.com']);
    }

    public function testEnhancedDetectionAccuracy()
    {
        // Test that enhanced detection is more accurate than original
        $timestamp = time();
        $testDomain = "this-should-be-available-{$timestamp}.com";
        
        $whoisHandler = WhoisHandler::whois($testDomain);
        $details = $whoisHandler->getAvailabilityDetails();
        
        // At least one enhanced detection method should trigger for available domain
        $enhancedMethods = [
            $details['contains_availability_keywords'],
            $details['is_response_too_short'],
            $details['contains_no_match_patterns'],
            $details['tld_specific_patterns'],
            $details['domain_status_indicators']
        ];
        
        $this->assertTrue(
            in_array(true, $enhancedMethods),
            'Enhanced detection should identify available domain using at least one method'
        );
    }

    public function testBackwardCompatibility()
    {
        // Test that the integration doesn't break existing functionality
        
        // WhoisHandler basic methods should still work
        $whoisHandler = WhoisHandler::whois('example.com');
        $this->assertIsString($whoisHandler->getTld());
        $this->assertIsString($whoisHandler->getSld());
        $this->assertIsBool($whoisHandler->isValid());
        $this->assertIsBool($whoisHandler->isAvailable());
        $this->assertIsString($whoisHandler->getWhoisMessage());
        
        // Checker should still work
        $results = Checker::whois('example.com');
        $this->assertIsArray($results);
        $this->assertArrayHasKey('example.com', $results);
        
        // Should return known status values
        $this->assertContains($results['example.com'], ['available', 'unavailable', 'premium', 'invalid']);                                                     
    }

    public function testUnsupportedTldDetection()
    {
        // Test various unsupported TLD messages
        $unsupportedMessages = [
            'TLD is not supported',
            'The domain extension .test is not supported by this WHOIS server',
            'Whois server not known for .example',
            'No WHOIS server available for this TLD',
            'Unsupported domain extension: .internal',
            'Extension not supported',
            'WHOIS SERVER NOT KNOWN',
        ];

        foreach ($unsupportedMessages as $message) {
            // Should throw exception when detecting unsupported TLD
            try {
                AvailabilityDetector::isAvailable($message, '.test', false);
                $this->fail('Expected exception for unsupported TLD message: ' . $message);
            } catch (\Exception $e) {
                $this->assertEquals('TLD is not supported by the WHOIS server', $e->getMessage());
            }
        }

        // Test that normal messages don't trigger unsupported TLD detection
        $normalMessages = [
            'This is a very short response',
            'google.com is available for registration.',
            'Domain not found',
            'No match for domain',
        ];

        foreach ($normalMessages as $message) {
            // Should not throw exception for normal messages
            try {
                $result = AvailabilityDetector::isAvailable($message, '.com', false);
                $this->assertTrue(true, 'Normal message should not throw exception');
            } catch (\Exception $e) {
                $this->fail('Normal message should not throw unsupported TLD exception: ' . $e->getMessage());
            }
        }

        // Test getAvailabilityDetails with unsupported TLD
        $details = AvailabilityDetector::getAvailabilityDetails('TLD is not supported', '.test', false);
        $this->assertTrue($details['contains_unsupported_tld_messages'], 'Should detect unsupported TLD messages');
        $this->assertEquals('unsupported_tld', $details['final_availability'], 'Final availability should be unsupported_tld');
    }
}
