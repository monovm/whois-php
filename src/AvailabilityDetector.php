<?php

namespace MonoVM\WhoisPhp;

class AvailabilityDetector
{
    /**
     * Enhanced domain availability detection using multiple methods
     */
    public static function isAvailable(string $whoisMessage, string $tld = '', bool $originalResult = false): bool
    {
        // Method 1: Use the original library's built-in check
        if ($originalResult) {
            return true;
        }

        // Early check: If domain contains clear registration indicators, it's NOT available
        if (self::containsRegistrationIndicators($whoisMessage, $tld)) {
            return false;
        }

        // Method 2: Check for availability keywords in the response
        if (self::containsAvailabilityKeywords($whoisMessage)) {
            return true;
        }

        // Method 3: Check if the response is too short/empty (likely available)
        if (self::isResponseTooShort($whoisMessage)) {
            return true;
        }

        // Method 4: Check for "No match" or similar patterns
        if (self::containsNoMatchPatterns($whoisMessage)) {
            return true;
        }

        // Method 5: Check for TLD-specific availability patterns
        if (!empty($tld) && self::checkTldSpecificPatterns($whoisMessage, $tld)) {
            return true;
        }

        // Method 6: Check for domain status indicators
        if (self::checkDomainStatusIndicators($whoisMessage)) {
            return true;
        }

        return false;
    }

    /**
     * Get detailed availability information for debugging
     */
    public static function getAvailabilityDetails(string $whoisMessage, string $tld = '', bool $originalResult = false): array
    {
        return [
            'original_library_result' => $originalResult,
            'contains_registration_indicators' => self::containsRegistrationIndicators($whoisMessage, $tld),
            'contains_availability_keywords' => self::containsAvailabilityKeywords($whoisMessage),
            'is_response_too_short' => self::isResponseTooShort($whoisMessage),
            'contains_no_match_patterns' => self::containsNoMatchPatterns($whoisMessage),
            'tld_specific_patterns' => !empty($tld) ? self::checkTldSpecificPatterns($whoisMessage, $tld) : false,
            'domain_status_indicators' => self::checkDomainStatusIndicators($whoisMessage),
            'final_availability' => self::isAvailable($whoisMessage, $tld, $originalResult),
            'whois_message_length' => strlen($whoisMessage),
            'whois_message_preview' => substr($whoisMessage, 0, 200) . (strlen($whoisMessage) > 200 ? '...' : ''),
        ];
    }

    /**
     * Check for keywords that indicate domain availability
     */
    private static function containsAvailabilityKeywords(string $whoisMessage): bool
    {
        $availabilityKeywords = [
            'no match',
            'not found',
            'no data found',
            'no entries found',
            'available for registration',
            'domain status: available',
            'no matching record',
            'not registered',
            'free',
            'no object found',
            'no such domain',
            'object does not exist',
            'nothing found',
            'no domain',
            'domain not found',
            'status: available',
            'registration status: available',
            'status:\tavailable',
            'status:\t\tavailable',
            'status: free',
            'is free',
            'domain name not known',
            'domain has not been registered',
            'domain name has not been registered',
            'is available for purchase',
            'no se encontro el objeto',
            'object_not_found',
            'el dominio no se encuentra registrado',
            'domain is available',
            'domain does not exist',
            'does not exist in database',
            'was not found',
            'not exist',
            'no está registrado',
            'is available for registration',
            'not registered',
            'no entries found',
            'not found...',
        ];

        // Filter out comment lines and informational text
        $lines = explode("\n", $whoisMessage);
        $relevantLines = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comment lines, empty lines, and informational lines
            if (empty($line) || 
                str_starts_with($line, '%') || 
                str_starts_with($line, '#') || 
                str_starts_with($line, ';') ||
                str_starts_with($line, '>>>') ||
                str_starts_with($line, '---') ||
                stripos($line, 'available on web at') !== false ||
                stripos($line, 'find the terms and conditions') !== false) {
                continue;
            }
            $relevantLines[] = $line;
        }
        
        $filteredMessage = strtolower(implode(' ', $relevantLines));

        foreach ($availabilityKeywords as $keyword) {
            if (strpos($filteredMessage, strtolower($keyword)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for clear registration indicators that mean domain is NOT available
     */
    private static function containsRegistrationIndicators(string $whoisMessage, string $tld = ''): bool
    {
        $lowerMessage = strtolower($whoisMessage);
        
        // Strong indicators that domain is registered
        $registrationIndicators = [
            'domain:',
            'ascii:',
            'nserver:',
            'nameserver:',
            'name server:',
            'registrar:',
            'registrant:',
            'creation date:',
            'created:',
            'expiry date:',
            'expires:',
            'updated:',
            'last updated:',
            'admin contact:',
            'technical contact:',
            'billing contact:',
            'registry domain id:',
            'registrar whois server:',
            'domain status: client',
            'dnssec:',
        ];

        $foundIndicators = 0;
        foreach ($registrationIndicators as $indicator) {
            if (strpos($lowerMessage, $indicator) !== false) {
                $foundIndicators++;
            }
        }

        // For .ir domains, be extra careful - if we see domain: and nserver: it's definitely registered
        if ($tld === '.ir') {
            if (strpos($lowerMessage, 'domain:') !== false && strpos($lowerMessage, 'nserver:') !== false) {
                return true;
            }
        }

        // For .de domains, "Status: connect" means registered
        if ($tld === '.de' && strpos($lowerMessage, 'status: connect') !== false) {
            return true;
        }

        // If we find 3 or more registration indicators, it's likely registered
        return $foundIndicators >= 3;
    }

    /**
     * Check if WHOIS response is too short to contain real registration data
     */
    private static function isResponseTooShort(string $whoisMessage): bool
    {
        $trimmedMessage = trim($whoisMessage);

        // If message is very short (less than 100 characters), likely available
        if (strlen($trimmedMessage) < 100) {
            return true;
        }

        // Count meaningful lines (ignore empty lines and comments)
        $lines = explode("\n", $trimmedMessage);
        $meaningfulLines = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && 
                !str_starts_with($line, '%') && 
                !str_starts_with($line, '#') && 
                !str_starts_with($line, ';') &&
                !str_starts_with($line, '>>>') &&
                !str_starts_with($line, '---')) {
                $meaningfulLines++;
            }
        }

        // If less than 5 meaningful lines, likely available
        return $meaningfulLines < 5;
    }

    /**
     * Check for "No match" type patterns that indicate availability
     */
    private static function containsNoMatchPatterns(string $whoisMessage): bool
    {
        $noMatchPatterns = [
            '/no\s+match/i',
            '/not\s+found/i',
            '/no\s+data\s+found/i',
            '/no\s+entries\s+found/i',
            '/no\s+matching\s+record/i',
            '/object\s+does\s+not\s+exist/i',
            '/no\s+such\s+domain/i',
            '/domain\s+not\s+found/i',
            '/status:\s*available/i',
            '/registration\s+status:\s*available/i',
            '/status:\s*free/i',
            '/\bis\s+free\b/i',
            '/domain\s+name\s+not\s+known/i',
            '/domain\s+has\s+not\s+been\s+registered/i',
            '/domain\s+name\s+has\s+not\s+been\s+registered/i',
            '/\bis\s+available\s+for/i',
            '/no\s+se\s+encontro\s+el\s+objeto/i',
            '/object_not_found/i',
            '/el\s+dominio\s+no\s+se\s+encuentra\s+registrado/i',
            '/domain\s+is\s+available/i',
            '/domain\s+does\s+not\s+exist/i',
            '/does\s+not\s+exist\s+in\s+database/i',
            '/was\s+not\s+found/i',
            '/not\s+exist/i',
            '/no\s+está\s+registrado/i',
            '/---available/i',
            '/---not\s+found/i',
            '/---domain\s+not\s+found/i',
            '/%error:103/i',
            '/404/i', // For RDAP servers
        ];

        foreach ($noMatchPatterns as $pattern) {
            if (preg_match($pattern, $whoisMessage)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for TLD-specific availability patterns
     */
    private static function checkTldSpecificPatterns(string $whoisMessage, string $tld): bool
    {
        $tldPatterns = [
            '.com' => ['/no\s+match\s+for/i'],
            '.net' => ['/no\s+match\s+for/i'],
            '.org' => ['/domain\s+not\s+found/i'],
            '.uk' => ['/no\s+match/i'],
            '.de' => ['/status:\s*free/i'],
            '.fr' => ['/not\s+found/i'],
            '.it' => ['/available/i'],
            '.au' => ['/---available/i'],
            '.be' => ['/status:\s*available/i'],
            '.ca' => ['/not\s+found/i'],
            '.ch' => ['/---1:/i'],
            '.eu' => ['/status:\s*available/i'],
            '.jp' => ['/no\s+match!!/i'],
            '.nl' => ['/\bis\s+free/i'],
            '.ru' => ['/no\s+entries\s+found/i'],
            '.br' => ['/no\s+match\s+for/i'],
            '.mx' => ['/no_se_encontro_el_objeto/i'],
            '.ar' => ['/el\s+dominio\s+no\s+se\s+encuentra\s+registrado/i'],
            '.gr' => ['/not\s+exist/i'],
            '.ph' => ['/domain\s+is\s+available/i'],
            '.my' => ['/does\s+not\s+exist\s+in\s+database/i'],
            '.tw' => ['/no\s+found/i'],
            '.hk' => ['/the\s+domain\s+has\s+not\s+been\s+registered/i'],
            '.im' => ['/was\s+not\s+found/i'],
            '.ec' => ['/404/i'], // RDAP server
            '.ir' => ['/no\s+entries\s+found/i'],
            '.de' => ['/is\s+available\s+for\s+registration/i'],
        ];

        if (isset($tldPatterns[$tld])) {
            foreach ($tldPatterns[$tld] as $pattern) {
                if (preg_match($pattern, $whoisMessage)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check for domain status indicators that suggest availability
     */
    private static function checkDomainStatusIndicators(string $whoisMessage): bool
    {
        $lowerMessage = strtolower($whoisMessage);
        
        // Check for explicit availability status indicators
        $statusIndicators = [
            'status: available',
            'status:\tavailable',
            'status: free',
            'registration status: available',
            'domain status: available',
            'availability: available',
            'state: available',
            'status = available',
            'status=available',
        ];

        foreach ($statusIndicators as $indicator) {
            if (strpos($lowerMessage, $indicator) !== false) {
                return true;
            }
        }

        // Check for absence of typical registration fields
        $registrationFields = [
            'registrar:',
            'creation date:',
            'created:',
            'expiry date:',
            'expires:',
            'name server:',
            'nameserver:',
            'registrant:',
            'admin contact:',
            'technical contact:',
        ];

        $foundFields = 0;
        foreach ($registrationFields as $field) {
            if (strpos($lowerMessage, $field) !== false) {
                $foundFields++;
            }
        }

        // If we find very few registration fields, domain might be available
        return $foundFields < 2;
    }
}
