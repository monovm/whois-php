<?php

namespace MonoVM\WhoisPhp;

use Exception;

class Whois
{
    protected $definitions = [];

    protected $socketPrefix = 'socket://';

    public function __construct()
    {
        $this->load();
    }

    protected function load()
    {
        $path = __DIR__ . '/dist.whois.json';
        $overridePath = __DIR__ . '/whois.json';
        $this->definitions = array_merge(
            $this->parseFile($path),
            $this->parseFile($overridePath),
        );
    }

    protected function parseFile($path)
    {
        $return = [];
        if (file_exists($path)) {
            $definitions = file_get_contents($path);
            if ($definitions = @json_decode($definitions, true)) {
                foreach ($definitions as $definition) {
                    $extensions = explode(',', $definition['extensions']);
                    unset($definition['extensions']);
                    foreach ($extensions as $extension) {
                        $return[$extension] = $definition;
                    }
                }
            } else {
                throw new Exception('dist.whois.json file not found!');
            }
        }

        return $return;
    }

    public function getSocketPrefix()
    {
        return $this->socketPrefix;
    }

    public function canLookup($tld)
    {
        return array_key_exists($tld, $this->definitions);
    }

    public function getFromDefinitions($tld, $key)
    {
        return isset($this->definitions[$tld][$key])
            ? $this->definitions[$tld][$key]
            : '';
    }

    protected function getUri($tld)
    {
        if ($this->canLookup($tld)) {
            $uri = $this->getFromDefinitions($tld, 'uri');
            if (empty($uri)) {
                throw new Exception('Uri not defined for whois service');
            }

            return $uri;
        }
        throw new Exception('Whois server not known for ' . $tld);
    }

    protected function isSocketLookup($tld)
    {
        if ($this->canLookup($tld)) {
            $uri = $this->getUri($tld);

            return substr($uri, 0, strlen($this->getSocketPrefix())) ==
                $this->getSocketPrefix();
        }
        throw new Exception('Whois server not known for ' . $tld);
    }

    protected function getAvailableMatchString($tld)
    {
        if ($this->canLookup($tld)) {
            return $this->getFromDefinitions($tld, 'available');
        }
        throw new Exception('Whois server not known for ' . $tld);
    }

    protected function getPremiumMatchString($tld)
    {
        if ($this->canLookup($tld)) {
            return $this->getFromDefinitions($tld, 'premium');
        }
        throw new Exception('Whois server not known for ' . $tld);
    }

    protected function httpWhoisLookup($domain, $uri)
    {
        $url = $uri . $domain;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $data = curl_exec($ch);
        if (curl_error($ch)) {
            curl_close($ch);
            throw new Exception(
                'Error: ' . curl_errno($ch) . ' - ' . curl_error($ch),
            );
        }
        curl_close($ch);

        return $data;
    }

    protected function socketWhoisLookup($domain, $server, $port)
    {
        $fp = @fsockopen($server, $port, $errorNumber, $errorMessage, 10);
        if ($fp === false) {
            throw new Exception(
                'Error: ' . $errorNumber . ' - ' . $errorMessage,
            );
        }
        @fwrite($fp, $domain . "\r\n");
        @stream_set_timeout($fp, 10);
        $data = '';
        while (!@feof($fp)) {
            $data .= @fread($fp, 4096);
        }
        @fclose($fp);

        return $data;
    }

    public function lookup($parts)
    {
        $sld = $parts['sld'];
        $tld = $parts['tld'];

        try {
            $uri = $this->getUri($tld);
            $availableMatchString = $this->getAvailableMatchString($tld);
            $premiumMatchString = $this->getPremiumMatchString($tld);
            $isSocketLookup = $this->isSocketLookup($tld);
        } catch (Exception $e) {
            return false;
        }
        $domain = $sld . $tld;

        try {
            if ($isSocketLookup) {
                $uri = substr($uri, strlen($this->getSocketPrefix()));
                $port = 43;
                if (strpos($uri, ':')) {
                    $port = explode(':', $uri, 2);
                    [$uri, $port] = $port;
                }
                $lookupResult = $this->socketWhoisLookup(
                    $domain,
                    $uri,
                    $port,
                );
            } else {
                $lookupResult = $this->httpWhoisLookup($domain, $uri);
            }
        } catch (\Exception $e) {
            $results = [];
            $results['result'] = 'error';
            $results['errordetail'] = $e->getMessage();

            return $results;
        }
        $lookupResult = ' ---' . $lookupResult;
        $results = [];
        if (strpos(strtolower($lookupResult), strtolower($availableMatchString)) !== false) {
            $results['result'] = 'available';
        } else if ($premiumMatchString && strpos(strtolower($lookupResult), strtolower($premiumMatchString)) !== false) {
            $results['result'] = 'premium';
        } else {
            $results['result'] = 'unavailable';
            if ($isSocketLookup) {
                $results['whois'] = nl2br(htmlentities($lookupResult));
            } else {
                $results['whois'] = nl2br(
                    htmlentities(strip_tags($lookupResult)),
                );
            }
        }

        return $results;
    }
}
