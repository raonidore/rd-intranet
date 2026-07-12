<?php

namespace App\Services;

class PublicIpService
{
    private const ENDPOINTS = [
        'https://api.ipify.org',
        'https://ifconfig.me/ip',
        'https://icanhazip.com',
    ];

    public function obter(): ?string
    {
        foreach (self::ENDPOINTS as $url) {
            $ip = $this->tentar($url);
            if ($ip !== null) {
                return $ip;
            }
        }

        return null;
    }

    private function tentar(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $corpo = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($corpo === false || $codigo !== 200) {
            return null;
        }

        $ip = trim($corpo);

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : null;
    }
}
