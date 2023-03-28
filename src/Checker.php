<?php

namespace MonoVM\WhoisPhp;

class Checker extends Whois
{
    private $parts;
    private $popularTLDs = ['.com', '.net', '.org', '.info'];

    protected function __construct(string $domain, array $options = [])
    {
        $parts = explode('.', $domain, 2);
        $this->parts = [
            'sld' => array_shift($parts),
            'tld' => count($parts) ? '.' . $parts[0] : null
        ];

        if (isset($options['popularTLDs']) && is_array($options['popularTLDs'])) {
            $this->popularTLDs = array_map(function ($tld) {
                if (strpos($tld, '.') === 0) {
                    return $tld;
                }
                return '.' . $tld;
            }, $options['popularTLDs']);
        }

        parent::__construct();
    }


    /**
     * Run whois lookup for domain(s).
     *
     * @param string|array $domains Domain or array of domains to lookup, TLD is optional for each domain.
     * @param array $options Options for WhoisGroup Class,
     ** you can set your own custom popularTLDs to lookup if tld is not provided in domain string.
     ** (eg: ["popularTLDs"=>[".info",".net"]])
     *
     * @return array An associative array with domain as key and status as value.
     */
    public static function whois($domains, array $options = []): array
    {

        if (is_string($domains)) {
            $domains = [$domains];
        }

        $results = [];
        array_map(function ($domain) use (&$results, $options) {
            $results = array_merge($results, (new self($domain, $options))->doLookup());
        }, $domains);

        return $results;
    }

    /**
     * Performs lookup for a domain by it parts.
     *
     * @return array An associative array with domain as key and status as value.
     */
    public function doLookup(): array
    {
        $parts = $this->parts;

        $tldsToLoop = $parts['tld'] ? [$parts['tld']] : $this->popularTLDs;
        $results = [];
        foreach ($tldsToLoop as $tld) {
            if (!parent::canLookup($tld)) {
                $results[$this->getDomainNameFromParts($parts)] = 'invalid';
                continue;
            }

            $parts['tld'] = $tld;
            $lookup = parent::lookup($parts);
            $results[$this->getDomainNameFromParts($parts)] = isset($lookup['result']) ? $lookup['result'] : 'unknown';
        }

        return $results;
    }

    /**
     * Get full domain name from array of sld and tld.
     *
     * @param array $parts An array containing domain parts (eg: ["sld"=>"monovm","tld"=>".com"]).
     * @return string Full domain name. (eg: monovm.com)
     */
    private function getDomainNameFromParts(array $parts): string
    {
        return $parts['sld'] . $parts['tld'];
    }
}
