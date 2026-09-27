<?php

namespace App\Services\Security;

class UserAgentClassifier
{
    public function analyze(?string $userAgent): array
    {
        $raw = $this->sanitize($userAgent);

        if ($raw === null) {
            return [
                'user_agent' => null,
                'device_type' => 'Unknown',
                'device_manufacturer' => null,
                'device_model' => null,
                'os_name' => 'Unknown',
                'os_version' => null,
                'browser_name' => 'Unknown',
                'browser_version' => null,
            ];
        }

        $lower = strtolower($raw);

        return [
            'user_agent' => $raw,
            'device_type' => $this->detectDeviceType($lower),
            'device_manufacturer' => $this->detectManufacturer($lower),
            'device_model' => $this->detectModel($lower),
            'os_name' => $this->detectOperatingSystem($lower),
            'os_version' => $this->detectOperatingSystemVersion($raw, $lower),
            'browser_name' => $this->detectBrowser($lower),
            'browser_version' => $this->detectBrowserVersion($raw, $lower),
        ];
    }

    protected function sanitize(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        $trimmed = trim($userAgent);

        if ($trimmed === '' || mb_strlen($trimmed, 'UTF-8') > 2048) {
            return null;
        }

        return $trimmed;
    }

    protected function detectDeviceType(string $userAgent): string
    {
        if (preg_match('/bot|crawler|slurp|spider|bingpreview|headless|wget|curl|python-requests|httpx|go-http-client/i', $userAgent)) {
            return 'Bot/Crawler';
        }

        if (preg_match('/ipad|tablet|playbook|silk/i', $userAgent)) {
            return 'Tablet';
        }

        if (preg_match('/iphone|ipod|android.*mobile|mobile|windows phone|phone/i', $userAgent)) {
            return 'Mobile';
        }

        if (preg_match('/windows|macintosh|linux|x11|compatible; msie|intel mac|amd64|x86_64/i', $userAgent)) {
            return 'Desktop';
        }

        return 'Unknown';
    }

    protected function detectManufacturer(string $userAgent): ?string
    {
        if (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
            return 'Apple';
        }

        if (preg_match('/samsung|sm-|gt-|pixel|nexus|huawei|oneplus|xiaomi|motorola|lenovo/i', $userAgent)) {
            return preg_match('/samsung/i', $userAgent) ? 'Samsung'
                : (preg_match('/pixel|nexus/i', $userAgent) ? 'Google' : (preg_match('/huawei/i', $userAgent) ? 'Huawei' : (preg_match('/oneplus/i', $userAgent) ? 'OnePlus' : (preg_match('/xiaomi/i', $userAgent) ? 'Xiaomi' : (preg_match('/motorola/i', $userAgent) ? 'Motorola' : 'Unknown')))));
        }

        if (preg_match('/windows/i', $userAgent)) {
            return 'Microsoft';
        }

        if (preg_match('/macintosh|darwin/i', $userAgent)) {
            return 'Apple';
        }

        return null;
    }

    protected function detectModel(string $userAgent): ?string
    {
        if (preg_match('/iphone\s*os\s*[^;]+;\s*([^)]*)/i', $userAgent, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/android[^;]*;\s*([^;\)]+)/i', $userAgent, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/(Pixel|Nexus|Galaxy|iPhone|iPad|iPod|Surface)\b/i', $userAgent, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    protected function detectOperatingSystem(string $userAgent): string
    {
        if (preg_match('/android/i', $userAgent)) {
            return 'Android';
        }

        if (preg_match('/iphone|ipad|ipod|cpu os/i', $userAgent)) {
            return 'iOS';
        }

        if (preg_match('/windows/i', $userAgent)) {
            return 'Windows';
        }

        if (preg_match('/mac os x|macintosh|darwin/i', $userAgent)) {
            return 'macOS';
        }

        if (preg_match('/linux|x11/i', $userAgent)) {
            return 'Linux';
        }

        return 'Unknown';
    }

    protected function detectOperatingSystemVersion(string $userAgent, string $lower): ?string
    {
        if (preg_match('/Windows NT 10\.0/i', $userAgent)) {
            return '10';
        }

        if (preg_match('/Windows NT 6\.3/i', $userAgent)) {
            return '8.1';
        }

        if (preg_match('/Windows NT 6\.2/i', $userAgent)) {
            return '8';
        }

        if (preg_match('/Windows NT 6\.1/i', $userAgent)) {
            return '7';
        }

        if (preg_match('/Windows NT 6\.0/i', $userAgent)) {
            return 'Vista';
        }

        if (preg_match('/Mac OS X ([0-9_]+)/i', $userAgent, $matches)) {
            return str_replace('_', '.', $matches[1]);
        }

        if (preg_match('/Android\s*([0-9.]+)/i', $userAgent, $matches)) {
            return $matches[1];
        }

        if (preg_match('/iPhone OS ([0-9_]+)/i', $userAgent, $matches)) {
            return str_replace('_', '.', $matches[1]);
        }

        if (preg_match('/CPU OS ([0-9_]+)/i', $userAgent, $matches)) {
            return str_replace('_', '.', $matches[1]);
        }

        return null;
    }

    protected function detectBrowser(string $userAgent): string
    {
        if (preg_match('/edg(?:e)?\//i', $userAgent)) {
            return 'Edge';
        }

        if (preg_match('/opr\//i', $userAgent)) {
            return 'Opera';
        }

        if (preg_match('/chrome\//i', $userAgent)) {
            return 'Chrome';
        }

        if (preg_match('/firefox\//i', $userAgent)) {
            return 'Firefox';
        }

        if (preg_match('/safari\//i', $userAgent) && ! preg_match('/chrome\//i', $userAgent)) {
            return 'Safari';
        }

        return 'Unknown';
    }

    protected function detectBrowserVersion(string $userAgent, string $lower): ?string
    {
        foreach (['Edg', 'EdgiOS', 'OPR', 'Chrome', 'Firefox', 'Version', 'Safari'] as $token) {
            if (preg_match('/'.$token.'\/?\s*([0-9]+(?:\.[0-9]+)+)/i', $userAgent, $matches)) {
                return $matches[1];
            }
        }

        foreach (['EdgA', 'EdgiOS', 'OPR', 'Chrome', 'Firefox', 'Safari'] as $token) {
            if (preg_match('/'.$token.'\/?\s*([0-9]+(?:\.[0-9]+)?)/i', $userAgent, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}
