<?php

namespace App\Services;

use Config\Services;

class SatusehatService
{
    protected $client;

    public function __construct()
    {
        $this->client = Services::curlrequest();
    }

    public function token(): string
    {
        $res = $this->client->post(
            getenv('SATUSEHAT_AUTH_URL') . '?grant_type=client_credentials',
            [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'client_id'     => getenv('SATUSEHAT_CLIENT_ID'),
                    'client_secret' => getenv('SATUSEHAT_CLIENT_SECRET'),
                ],
                'http_errors' => false,
                'timeout' => (int)(getenv('SATUSEHAT_TIMEOUT') ?: 10),
                'connect_timeout' => (int)(getenv('SATUSEHAT_CONNECT_TIMEOUT') ?: 3),
            ]
        );

        $json = json_decode($res->getBody(), true);

        if (!isset($json['access_token'])) {
            throw new \Exception('Gagal mendapatkan token SatuSehat: ' . json_encode($json));
        }

        return $json['access_token'];
    }

    public function get(string $resource, array $query = []): array
    {
        $token = $this->token();
        
        try {
            $url = getenv('SATUSEHAT_BASE_URL') . "/fhir-r4/v1/{$resource}";
            
            // Append query string if exists
            if (!empty($query)) {
                $url .= '?' . http_build_query($query);
            }

            $res = $this->client->get($url, [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type'  => 'application/json',
                ],
                'http_errors' => false,
                'timeout' => (int)(getenv('SATUSEHAT_TIMEOUT') ?: 10),
                'connect_timeout' => (int)(getenv('SATUSEHAT_CONNECT_TIMEOUT') ?: 3),
            ]);

            $body = json_decode($res->getBody(), true);
            
            // Cek OperationOutcome jika ada error dari FHIR
            if (isset($body['resourceType']) && $body['resourceType'] === 'OperationOutcome') {
                 $issues = array_map(function($issue) {
                    return $issue['diagnostics'] ?? $issue['details']['text'] ?? 'Unknown error';
                 }, $body['issue'] ?? []);
                 throw new \Exception("Satusehat Error: " . implode(", ", $issues));
            }

            if ($res->getStatusCode() >= 400) {
                 throw new \Exception("HTTP Error " . $res->getStatusCode() . ": " . $res->getBody());
            }

            return $body;

        } catch (\Throwable $e) {
            throw $e;
        }
    }

    public function post(string $resource, array $payload): array
    {
        $token = $this->token();
        
        try {
            $res = $this->client->post(
                getenv('SATUSEHAT_BASE_URL') . "/fhir-r4/v1/{$resource}",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$token}",
                        'Content-Type'  => getenv('SATUSEHAT_CONTENT_TYPE') ?: 'application/json',
                    ],
                    'json' => $payload, // Gunakan 'json' option agar otomatis encode dan set content-type
                    'http_errors' => false,
                    'timeout' => (int)(getenv('SATUSEHAT_TIMEOUT') ?: 10),
                    'connect_timeout' => (int)(getenv('SATUSEHAT_CONNECT_TIMEOUT') ?: 3),
                ]
            );

            $body = json_decode($res->getBody(), true);
            
            // Cek OperationOutcome jika ada error dari FHIR
            if (isset($body['resourceType']) && $body['resourceType'] === 'OperationOutcome') {
                 $issues = array_map(function($issue) {
                    return $issue['diagnostics'] ?? $issue['details']['text'] ?? 'Unknown error';
                 }, $body['issue'] ?? []);
                 throw new \Exception("Satusehat Error: " . implode(", ", $issues));
            }

            if ($res->getStatusCode() >= 400) {
                 throw new \Exception("HTTP Error " . $res->getStatusCode() . ": " . $res->getBody());
            }

            return $body;

        } catch (\Throwable $e) {
            throw $e;
        }
    }

    public function put(string $resource, string $id, array $payload): array
    {
        $token = $this->token();
        
        try {
            $res = $this->client->put(
                getenv('SATUSEHAT_BASE_URL') . "/fhir-r4/v1/{$resource}/{$id}",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$token}",
                        'Content-Type'  => getenv('SATUSEHAT_CONTENT_TYPE') ?: 'application/json',
                    ],
                    'json' => $payload,
                    'http_errors' => false,
                    'timeout' => (int)(getenv('SATUSEHAT_TIMEOUT') ?: 10),
                    'connect_timeout' => (int)(getenv('SATUSEHAT_CONNECT_TIMEOUT') ?: 3),
                ]
            );

            $body = json_decode($res->getBody(), true);
            
            if (isset($body['resourceType']) && $body['resourceType'] === 'OperationOutcome') {
                 $issues = array_map(function($issue) {
                    return $issue['diagnostics'] ?? $issue['details']['text'] ?? 'Unknown error';
                 }, $body['issue'] ?? []);
                 throw new \Exception("Satusehat Error: " . implode(", ", $issues));
            }

            if ($res->getStatusCode() >= 400) {
                 throw new \Exception("HTTP Error " . $res->getStatusCode() . ": " . $res->getBody());
            }

            return $body;

        } catch (\Throwable $e) {
            throw $e;
        }
    }

    public function formatIndonesianDate($dateStr)
    {
        if (empty($dateStr)) return '';
        $timestamp = strtotime($dateStr);
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $months = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];

        $dayName = $days[date('w', $timestamp)];
        $day = date('d', $timestamp);
        $month = $months[(int)date('m', $timestamp)];
        $year = date('Y', $timestamp);

        return "$dayName, $day $month $year";
    }
}
