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

    public function testRedemptionPeriodDetection()
    {
        // Test redemption period detection for .de domain
        $redemptionResponse = " ---Domain: elitefollow.de<br />\nStatus: redemptionPeriod<br />";
        
        $isAvailable = AvailabilityDetector::isAvailable($redemptionResponse, '.de', false);
        $this->assertFalse($isAvailable, 'Domain in redemption period should be detected as unavailable');
        
        $details = AvailabilityDetector::getAvailabilityDetails($redemptionResponse, '.de', false);
        $this->assertTrue($details['contains_unavailability_indicators'], 'Should detect redemptionPeriod as unavailability indicator');
        $this->assertFalse($details['final_availability'], 'Final result should be unavailable for redemption period domain');
        
        // Test other domain status that should be unavailable
        $statusesToTest = [
            'Status: redemptionPeriod',
            'Status: redemption period',
            'Status: redemption',
            'Status: pendingDelete',
            'Status: pending delete',
            'Status: serverHold',
            'Status: server hold',
        ];
        
        foreach ($statusesToTest as $status) {
            $response = " ---Domain: test.de<br />\n$status<br />";
            $isAvailable = AvailabilityDetector::isAvailable($response, '.de', false);
            $this->assertFalse($isAvailable, "Domain with '$status' should be detected as unavailable");
        }
        
        // Test that normal registered status also works
        $registeredResponse = " ---Domain: test.de<br />\nStatus: connect<br />";
        $isAvailable = AvailabilityDetector::isAvailable($registeredResponse, '.de', false);
        $this->assertFalse($isAvailable, 'Domain with Status: connect should be detected as unavailable');
    }

    public function testServerBusyAndRateLimitDetection()
    {
        // Test server busy and rate limit messages
        $serverErrorMessages = [
            'Server is busy now, please try again later.',
            'Server busy, try again later',
            'Please try again later',
            'Service temporarily unavailable',
            'Rate limit exceeded',
            'Too many requests',
            'Quota exceeded',
            'Connection timed out',
            'Request timeout',
        ];
        
        foreach ($serverErrorMessages as $message) {
            try {
                AvailabilityDetector::isAvailable($message, '.shop', false);
                $this->fail("Expected exception for server error message: $message");
            } catch (\Exception $e) {
                // Should throw an exception
                $this->assertStringContainsString('unavailable', strtolower($e->getMessage()), 
                    "Exception message should indicate unavailability for: $message");
            }
        }
        
        // Test that proper WHOIS responses still work
        $validRegisteredResponse = "Domain Name: GOLDOZI.SHOP\nRegistrar: PDR Ltd.\nCreation Date: 2019-05-26\nName Server: AV1.7HOSTIR.COM";
        $isAvailable = AvailabilityDetector::isAvailable($validRegisteredResponse, '.shop', false);
        $this->assertFalse($isAvailable, 'Valid registered domain response should be detected as unavailable');
        
        $validAvailableResponse = "DOMAIN NOT FOUND";
        $isAvailable = AvailabilityDetector::isAvailable($validAvailableResponse, '.shop', false);
        $this->assertTrue($isAvailable, 'Valid available domain response should be detected as available');
    }

    public function testSxRestrictedDomainDetection()
    {
        // The .sx registry (CIRA backend) returns an error/restriction notice
        // for registered-but-restricted/reserved names instead of a full WHOIS
        // record. This response has zero registration fields, so the naive
        // "few fields => available" heuristic wrongly reported it as available.
        // Real response for the registered domain kick.sx:
        $restrictedResponse = " ---Error code: 01044\n" .
            "Error message: The domain name requested has usage restrictions applied to it. Please see your Registrar for more details.\n\n" .
            "%\n% Use of CIRA's WHOIS service is governed by the Terms of Use in its Legal\n" .
            "% Notice, available at https://www.cira.ca/en/resources/documents/about/website-terms-use\n%\n" .
            "% (c) 2026 Canadian Internet Registration Authority, (http://www.cira.ca/)\n";

        $isAvailable = AvailabilityDetector::isAvailable($restrictedResponse, '.sx', false);
        $this->assertFalse($isAvailable, 'Registered/restricted .sx domain (e.g. kick.sx) must NOT be reported as available');

        // A normal registered .sx domain (full record) must be unavailable.
        $registeredResponse = " ---Domain Name: google.sx\nRegistrar: MarkMonitor Inc.\n" .
            "Creation Date: 2012-09-22T02:52:06Z\nRegistry Expiry Date: 2026-09-22T02:52:06Z\n" .
            "Name Server: NS1.GOOGLE.COM\nDomain Status: clientTransferProhibited";
        $this->assertFalse(
            AvailabilityDetector::isAvailable($registeredResponse, '.sx', false),
            'Registered .sx domain with a full WHOIS record must be unavailable'
        );

        // A genuinely available .sx domain returns "Not found:" and must stay available.
        $availableResponse = " ---Not found: definitelyfree99999zzz.sx\n";
        $originalMatch = strpos(strtolower($availableResponse), strtolower('Not found:')) !== false;
        $this->assertTrue(
            AvailabilityDetector::isAvailable($availableResponse, '.sx', $originalMatch),
            'Unregistered .sx domain ("Not found:") must be available'
        );
    }

    /**
     * Regression: .site and .online moved off whois.centralnic.com, which now answers
     * "DOMAIN NOT FOUND" for every query on those TLDs and made every domain look available.
     */
    public function testSiteAndOnlineUseCurrentRegistryServers()
    {
        $whois = new \MonoVM\WhoisPhp\Whois();

        $this->assertSame(
            'socket://whois.nic.site',
            $whois->getFromDefinitions('.site', 'uri'),
            '.site must not be routed to the stale whois.centralnic.com server'
        );
        $this->assertSame(
            'socket://whois.nic.online',
            $whois->getFromDefinitions('.online', 'uri'),
            '.online must not be routed to the stale whois.centralnic.com server'
        );

        // A registered .site domain returns a full record and must be unavailable.
        $registeredResponse = " ---Domain Name: google.site\nRegistry Domain ID: D8752736-CNIC\n" .
            "Registrar: MarkMonitor Inc.\nCreation Date: 2015-07-08T09:03:40.000Z\n" .
            "Registry Expiry Date: 2027-07-08T23:59:59.000Z\n" .
            "Name Server: NS1.GOOGLE.COM\nDomain Status: clientTransferProhibited\nDNSSEC: unsigned";
        $this->assertFalse(
            AvailabilityDetector::isAvailable($registeredResponse, '.site', false),
            'Registered .site domain with a full WHOIS record must be unavailable'
        );

        // An unregistered .site domain returns the Radix/Tucows banner and must stay available.
        $availableResponse = " ---\n>>> Domain definitelyfree99999zzz.site is available for registration\n\n" .
            ">>> Please visit https://rdap.radix.host/registrars/ for a list of accredited\nregistrars\n";
        $marker = 'is available for registration';
        $originalMatch = strpos(strtolower($availableResponse), strtolower($marker)) !== false;
        $this->assertTrue($originalMatch, 'Configured .site available marker must match the registry banner');
        $this->assertTrue(
            AvailabilityDetector::isAvailable($availableResponse, '.site', $originalMatch),
            'Unregistered .site domain must be available'
        );
    }
}
