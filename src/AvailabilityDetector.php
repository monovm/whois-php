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

        // PRIORITY 1: Check for explicit UNAVAILABILITY indicators (highest priority)
        if (self::containsUnavailabilityIndicators($whoisMessage, $tld)) {
            return false;
        }

        // PRIORITY 2: If domain contains clear registration indicators, it's NOT available
        if (self::containsRegistrationIndicators($whoisMessage, $tld)) {
            return false;
        }

        // PRIORITY 3: Check for availability keywords in the response
        if (self::containsAvailabilityKeywords($whoisMessage)) {
            return true;
        }

        // PRIORITY 4: Check for "No match" or similar patterns
        if (self::containsNoMatchPatterns($whoisMessage)) {
            return true;
        }

        // PRIORITY 5: Check for TLD-specific availability patterns
        if (!empty($tld) && self::checkTldSpecificPatterns($whoisMessage, $tld)) {
            return true;
        }

        // PRIORITY 6: Check if the response is too short/empty (likely available)
        if (self::isResponseTooShort($whoisMessage)) {
            return true;
        }

        // PRIORITY 7: Check for domain status indicators
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
            'contains_unavailability_indicators' => self::containsUnavailabilityIndicators($whoisMessage, $tld),
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
     * Check for explicit UNAVAILABILITY indicators (highest priority)
     */
    private static function containsUnavailabilityIndicators(string $whoisMessage, string $tld = ''): bool
    {
        $lowerMessage = strtolower($whoisMessage);

        // TLD-specific unavailability patterns (highest priority)
        $tldUnavailabilityPatterns = [
            '.com.au' => ['/---not available/i', '/---not found/i', '/not available/i', '/domain not available/i'],
            '.au' => ['/---not available/i', '/---not found/i', '/not available/i', '/domain not available/i'],
            '.uk' => ['/this domain has been registered/i', '/registered/i'],
            '.de' => ['/status:\s*connect/i', '/status:\s*registered/i', '/status:\s*active/i'],
            '.it' => ['/status:\s*active/i', '/status:\s*registered/i'],
            '.fr' => ['/status:\s*active/i', '/status:\s*registered/i'],
            '.ca' => ['/domain registered/i', '/status:\s*registered/i'],
            '.nl' => ['/status:\s*active/i', '/status:\s*in use/i'],
            '.be' => ['/status:\s*registered/i', '/status:\s*allocated/i'],
            '.eu' => ['/status:\s*registered/i'],
            '.ch' => ['/status:\s*registered/i'],
            '.li' => ['/status:\s*registered/i'],
            '.at' => ['/status:\s*registered/i'],
            '.dk' => ['/status:\s*active/i'],
            '.no' => ['/status:\s*active/i'],
            '.se' => ['/status:\s*active/i'],
            '.fi' => ['/status:\s*registered/i'],
            '.pt' => ['/status:\s*active/i'],
            '.es' => ['/status:\s*registered/i'],
            '.mx' => ['/status:\s*registered/i'],
            '.ar' => ['/status:\s*registered/i'],
            '.br' => ['/status:\s*registered/i'],
            '.cl' => ['/status:\s*registered/i'],
            '.pe' => ['/status:\s*registered/i'],
            '.co' => ['/status:\s*registered/i'],
            '.ve' => ['/status:\s*registered/i'],
            '.ec' => ['/status:\s*registered/i'],
            '.uy' => ['/status:\s*registered/i'],
            '.py' => ['/status:\s*registered/i'],
            '.bo' => ['/status:\s*registered/i'],
            '.hn' => ['/status:\s*registered/i'],
            '.ni' => ['/status:\s*registered/i'],
            '.cr' => ['/status:\s*registered/i'],
            '.gt' => ['/status:\s*registered/i'],
            '.sv' => ['/status:\s*registered/i'],
            '.pa' => ['/status:\s*registered/i'],
            '.do' => ['/status:\s*registered/i'],
            '.pr' => ['/status:\s*registered/i'],
            '.cu' => ['/status:\s*registered/i'],
            '.tt' => ['/status:\s*registered/i'],
            '.gy' => ['/status:\s*registered/i'],
            '.sr' => ['/status:\s*registered/i'],
            '.jm' => ['/status:\s*registered/i'],
            '.bb' => ['/status:\s*registered/i'],
            '.lc' => ['/status:\s*registered/i'],
            '.gd' => ['/status:\s*registered/i'],
            '.vc' => ['/status:\s*registered/i'],
            '.dm' => ['/status:\s*registered/i'],
            '.ag' => ['/status:\s*registered/i'],
            '.kn' => ['/status:\s*registered/i'],
            '.ai' => ['/status:\s*registered/i'],
            '.ms' => ['/status:\s*registered/i'],
            '.tc' => ['/status:\s*registered/i'],
            '.vg' => ['/status:\s*registered/i'],
            '.fk' => ['/status:\s*registered/i'],
            '.gs' => ['/status:\s*registered/i'],
            '.sh' => ['/status:\s*registered/i'],
            '.pn' => ['/status:\s*registered/i'],
            '.ki' => ['/status:\s*registered/i'],
            '.nr' => ['/status:\s*registered/i'],
            '.tv' => ['/status:\s*registered/i'],
            '.ws' => ['/status:\s*registered/i'],
            '.cc' => ['/status:\s*registered/i'],
            '.cx' => ['/status:\s*registered/i'],
            '.hm' => ['/status:\s*registered/i'],
            '.nf' => ['/status:\s*registered/i'],
            '.sj' => ['/status:\s*registered/i'],
            '.bv' => ['/status:\s*registered/i'],
            '.tf' => ['/status:\s*registered/i'],
            '.pm' => ['/status:\s*registered/i'],
            '.wf' => ['/status:\s*registered/i'],
            '.yt' => ['/status:\s*registered/i'],
            '.re' => ['/status:\s*registered/i'],
            '.mq' => ['/status:\s*registered/i'],
            '.gp' => ['/status:\s*registered/i'],
            '.gf' => ['/status:\s*registered/i'],
            '.pf' => ['/status:\s*registered/i'],
            '.nc' => ['/status:\s*registered/i'],
            '.vu' => ['/status:\s*registered/i'],
            '.tk' => ['/status:\s*registered/i'],
            '.to' => ['/status:\s*registered/i'],
            '.ws' => ['/status:\s*registered/i'],
            '.cc' => ['/status:\s*registered/i'],
            '.as' => ['/status:\s*registered/i'],
            '.ki' => ['/status:\s*registered/i'],
            '.fm' => ['/status:\s*registered/i'],
            '.nr' => ['/status:\s*registered/i'],
            '.pw' => ['/status:\s*registered/i'],
            '.cf' => ['/status:\s*registered/i'],
            '.ml' => ['/status:\s*registered/i'],
            '.ga' => ['/status:\s*registered/i'],
            '.gq' => ['/status:\s*registered/i'],
            '.cm' => ['/status:\s*registered/i'],
            '.bi' => ['/status:\s*registered/i'],
            '.ne' => ['/status:\s*registered/i'],
            '.cd' => ['/status:\s*registered/i'],
            '.dj' => ['/status:\s*registered/i'],
            '.km' => ['/status:\s*registered/i'],
            '.mg' => ['/status:\s*registered/i'],
            '.rw' => ['/status:\s*registered/i'],
            '.sc' => ['/status:\s*registered/i'],
            '.so' => ['/status:\s*registered/i'],
            '.st' => ['/status:\s*registered/i'],
            '.tz' => ['/status:\s*registered/i'],
            '.ug' => ['/status:\s*registered/i'],
            '.zm' => ['/status:\s*registered/i'],
            '.zw' => ['/status:\s*registered/i'],
        ];

        if (isset($tldUnavailabilityPatterns[$tld])) {
            foreach ($tldUnavailabilityPatterns[$tld] as $pattern) {
                if (preg_match($pattern, $whoisMessage)) {
                    return true; // Explicitly NOT available
                }
            }
        }

        // General unavailability patterns
        $generalUnavailabilityPatterns = [
            '/---not available/i',
            '/---not\s+found/i',
            '/---domain not found/i',
            '/not available/i',
            '/domain not available/i',
            '/status:\s*not available/i',
            '/registration status:\s*not available/i',
            '/domain status:\s*not available/i',
            '/status:\s*unavailable/i',
            '/status:\s*registered/i',
            '/status:\s*active/i',
            '/status:\s*client/i',
            '/this domain has been registered/i',
            '/domain registered/i',
            '/already registered/i',
            '/currently registered/i',
            '/is registered/i',
            '/registration:\s*registered/i',
        ];

        foreach ($generalUnavailabilityPatterns as $pattern) {
            if (preg_match($pattern, $whoisMessage)) {
                return true; // Explicitly NOT available
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
        $lowerMessage = strtolower($trimmedMessage);

        // Don't treat explicit unavailability messages as "short" (even if they are short)
        if (strpos($lowerMessage, 'not available') !== false ||
            strpos($lowerMessage, 'not found') !== false ||
            strpos($lowerMessage, 'unavailable') !== false ||
            strpos($lowerMessage, 'domain not found') !== false ||
            strpos($lowerMessage, 'no match') !== false ||
            strpos($lowerMessage, 'no entries found') !== false ||
            strpos($lowerMessage, '---not available') !== false ||
            strpos($lowerMessage, '---not found') !== false) {
            return false; // This is an explicit unavailability message, not "short"
        }

        // If message is very short (less than 100 characters) and doesn't contain unavailability indicators, likely available
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
            // Major TLDs
            '.com' => ['/no\s+match\s+for/i', '/domain\s+not\s+found/i'],
            '.net' => ['/no\s+match\s+for/i', '/domain\s+not\s+found/i'],
            '.org' => ['/domain\s+not\s+found/i', '/not\s+found/i'],
            '.uk' => ['/no\s+match/i', '/this\s+domain\s+is\s+available/i'],
            '.de' => ['/is\s+available\s+for\s+registration/i', '/status:\s*free/i'],
            '.fr' => ['/not\s+found/i', '/available/i'],
            '.it' => ['/available/i', '/status:\s*available/i'],
            '.au' => ['/---available/i', '/is\s+available\s+for\s+registration/i'],
            '.com.au' => ['/is\s+available\s+for\s+registration/i', '/available/i'],
            '.org.au' => ['/is\s+available\s+for\s+registration/i', '/available/i'],
            '.net.au' => ['/is\s+available\s+for\s+registration/i', '/available/i'],
            '.be' => ['/status:\s*available/i', '/free/i'],
            '.ca' => ['/not\s+found/i', '/available/i'],
            '.ch' => ['/---1:/i', '/available/i'],
            '.li' => ['/available/i', '/is\s+available/i'],
            '.eu' => ['/status:\s*available/i', '/available/i'],
            '.nl' => ['/\bis\s+free/i', '/available/i'],
            '.dk' => ['/available/i', '/is\s+available/i'],
            '.no' => ['/available/i', '/is\s+available/i'],
            '.se' => ['/available/i', '/is\s+available/i'],
            '.fi' => ['/available/i', '/is\s+available/i'],
            '.pt' => ['/available/i', '/is\s+available/i'],
            '.es' => ['/available/i', '/is\s+available/i'],

            // Asian TLDs
            '.jp' => ['/no\s+match!!/i', '/no\s+match/i'],
            '.cn' => ['/no\s+matching\s+record/i', '/not\s+found/i'],
            '.in' => ['/not\s+found/i', '/no\s+data\s+found/i'],
            '.hk' => ['/the\s+domain\s+has\s+not\s+been\s+registered/i'],
            '.tw' => ['/no\s+found/i', '/not\s+found/i'],
            '.sg' => ['/---not\s+found/i', '/domain\s+not\s+found/i'],
            '.my' => ['/does\s+not\s+exist\s+in\s+database/i'],
            '.ph' => ['/domain\s+is\s+available/i'],
            '.th' => ['/no\s+match\s+found/i'],
            '.vn' => ['/available/i', '/not\s+found/i'],
            '.id' => ['/domain\s+not\s+found/i', '/available/i'],

            // American TLDs
            '.us' => ['/not\s+found/i', '/domain\s+not\s+found/i'],
            '.mx' => ['/no_se_encontro_el_objeto/i', '/not\s+found/i'],
            '.br' => ['/no\s+match\s+for/i', '/domain\s+not\s+found/i'],
            '.ar' => ['/el\s+dominio\s+no\s+se\s+encuentra\s+registrado/i'],
            '.co' => ['/not\s+found/i', '/available/i'],
            '.cl' => ['/no\s+entries\s+found/i', '/available/i'],
            '.pe' => ['/not\s+found/i', '/available/i'],
            '.ve' => ['/no\s+entries\s+found/i', '/available/i'],
            '.ec' => ['/404/i', '/available/i'],

            // European TLDs
            '.ru' => ['/no\s+entries\s+found/i', '/not\s+found/i'],
            '.pl' => ['/no\s+information\s+available/i', '/available/i'],
            '.cz' => ['/no\s+entries\s+found/i', '/available/i'],
            '.sk' => ['/domain\s+not\s+found/i', '/available/i'],
            '.hu' => ['/no\s+match/i', '/available/i'],
            '.ro' => ['/no\s+entries\s+found/i', '/available/i'],
            '.rs' => ['/not\s+found/i', '/available/i'],
            '.me' => ['/not\s+found/i', '/available/i'],
            '.ba' => ['/not\s+found/i', '/available/i'],
            '.mk' => ['/no\s+entries\s+found/i', '/available/i'],
            '.al' => ['/no\s+entries\s+found/i', '/available/i'],
            '.md' => ['/no\s+object\s+found/i', '/available/i'],
            '.ua' => ['/no\s+entries\s+found/i', '/available/i'],

            // African TLDs
            '.za' => ['/available/i', '/not\s+found/i'],
            '.co.za' => ['/available/i', '/not\s+found/i'],
            '.ng' => ['/not\s+found/i', '/available/i'],
            '.ke' => ['/no\s+object\s+found/i', '/available/i'],
            '.ma' => ['/no\s+object\s+found/i', '/available/i'],
            '.tn' => ['/not\s+found/i', '/available/i'],
            '.eg' => ['/not\s+found/i', '/available/i'],
            '.ci' => ['/not\s+found/i', '/available/i'],
            '.sn' => ['/not\s+found/i', '/available/i'],

            // Oceanian TLDs
            '.nz' => ['/not\s+found/i', '/available/i'],
            '.ws' => ['/the\s+queried\s+object\s+does\s+not\s+exist/i'],
            '.cc' => ['/no\s+match/i', '/available/i'],
            '.to' => ['/no\s+match\s+for/i', '/available/i'],

            // Other TLDs
            '.im' => ['/was\s+not\s+found/i', '/available/i'],
            '.io' => ['/---domain\s+not\s+found/i', '/available/i'],
            '.sh' => ['/domain\s+not\s+found/i', '/available/i'],
            '.ac' => ['/domain\s+not\s+found/i', '/available/i'],
            '.gg' => ['/not\s+found/i', '/available/i'],
            '.je' => ['/not\s+found/i', '/available/i'],
            '.as' => ['/not\s+found/i', '/available/i'],
            '.ms' => ['/no\s+object\s+found/i', '/available/i'],
            '.tc' => ['/no\s+object\s+found/i', '/available/i'],
            '.vg' => ['/domain\s+not\s+found/i', '/available/i'],
            '.gs' => ['/no\s+object\s+found/i', '/available/i'],
            '.fm' => ['/domain\s+not\s+found/i', '/available/i'],
            '.nr' => ['/no\s+object\s+found/i', '/available/i'],
            '.pw' => ['/domain\s+not\s+found/i', '/available/i'],
            '.tk' => ['/domain\s+name\s+not\s+known/i', '/available/i'],
            '.ml' => ['/domain\s+not\s+found/i', '/available/i'],
            '.ga' => ['/domain\s+not\s+found/i', '/available/i'],
            '.cf' => ['/domain\s+not\s+found/i', '/available/i'],
            '.gq' => ['/domain\s+not\s+found/i', '/available/i'],
            '.cm' => ['/not\s+registered/i', '/available/i'],
            '.bi' => ['/domain\s+not\s+found/i', '/available/i'],
            '.ne' => ['/no\s+object\s+found/i', '/available/i'],
            '.cd' => ['/no\s+object\s+found/i', '/available/i'],
            '.dj' => ['/not\s+found/i', '/available/i'],
            '.km' => ['/not\s+found/i', '/available/i'],
            '.mg' => ['/no\s+object\s+found/i', '/available/i'],
            '.rw' => ['/not\s+found/i', '/available/i'],
            '.sc' => ['/not\s+found/i', '/available/i'],
            '.so' => ['/not\s+found/i', '/available/i'],
            '.st' => ['/not\s+found/i', '/available/i'],
            '.tz' => ['/no\s+entries\s+found/i', '/available/i'],
            '.ug' => ['/no\s+entries\s+found/i', '/available/i'],
            '.zm' => ['/not\s+found/i', '/available/i'],
            '.zw' => ['/no\s+information\s+available/i', '/available/i'],

            // Special cases
            '.ir' => ['/no\s+entries\s+found/i'],
            '.gr' => ['/not\s+exist/i'],
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
