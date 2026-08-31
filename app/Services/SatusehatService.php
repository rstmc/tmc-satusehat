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
        // Gunakan cache agar tidak request token baru untuk setiap API call (mencegah Rate Limit)
        $token = cache('satusehat_access_token');
        if ($token) {
            return $token;
        }

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

        // Token SatuSehat biasanya valid 1 jam (3600 detik). Kita cache selama 3000 detik (50 menit) untuk mencegah throttling limit.
        cache()->save('satusehat_access_token', $json['access_token'], 3000);

        return $json['access_token'];
    }

    /**
     * Helper to execute API requests with automatic exponential backoff retry on HTTP 429 (Rate Limit).
     */
    private function executeWithRetry(callable $requestFunc, int $maxRetries = 3)
    {
        $attempt = 0;
        while (true) {
            $attempt++;
            try {
                return $requestFunc();
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                $isRateLimit = (stripos($msg, '429') !== false || stripos($msg, 'QuotaViolation') !== false || stripos($msg, 'Rate limit') !== false);
                
                if ($isRateLimit && $attempt <= $maxRetries) {
                    $waitTime = $attempt * 2; // 2s, 4s, 6s
                    log_message('warning', "SATUSEHAT Rate Limit (429) hit. Waiting {$waitTime}s before retry {$attempt}/{$maxRetries}...");
                    sleep($waitTime);
                    continue;
                }
                throw $e;
            }
        }
    }

    public function get(string $resource, array $query = []): array
    {
        return $this->executeWithRetry(function () use ($resource, $query) {
            $token = $this->token();
            
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
                 $errMessage = implode(", ", $issues);
                 
                 // Jika error karena search kriteria kurang spesifik, coba tambahkan filter _count=50 jika belum ada
                 if (stripos($errMessage, 'search criteria are not selective enough') !== false && !isset($query['_count'])) {
                     $query['_count'] = 50;
                     return $this->get($resource, $query);
                 }
                 
                 throw new \Exception("Satusehat Error: " . $errMessage);
            }

            if ($res->getStatusCode() >= 400) {
                 throw new \Exception("HTTP Error " . $res->getStatusCode() . ": " . $res->getBody());
            }

            return $body;
        });
    }

    public function post(string $resource, array $payload, array $extraHeaders = []): array
    {
        return $this->executeWithRetry(function () use ($resource, $payload, $extraHeaders) {
            $token = $this->token();
            
            $headers = array_merge([
                'Authorization' => "Bearer {$token}",
                'Content-Type'  => getenv('SATUSEHAT_CONTENT_TYPE') ?: 'application/json',
            ], $extraHeaders);
            $this->sanitizePayloadDates($payload);
            $res = $this->client->post(
                getenv('SATUSEHAT_BASE_URL') . "/fhir-r4/v1/{$resource}",
                [
                    'headers' => $headers,
                    'json' => $payload,
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
        });
    }

    public function postBundle(array $bundlePayload, array $extraHeaders = []): array
    {
        return $this->executeWithRetry(function () use ($bundlePayload, $extraHeaders) {
            $token = $this->token();
            
            $headers = array_merge([
                'Authorization' => "Bearer {$token}",
                'Content-Type'  => getenv('SATUSEHAT_CONTENT_TYPE') ?: 'application/json',
            ], $extraHeaders);
            $this->sanitizePayloadDates($bundlePayload);
            $res = $this->client->post(
                getenv('SATUSEHAT_BASE_URL') . "/fhir-r4/v1",
                [
                    'headers' => $headers,
                    'json' => $bundlePayload,
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

            // Kemkes mengembalikan body berupa array of rule violations (baik HTTP 200 maupun HTTP 400)
            // contoh: [{"ruleNumber":"20002","message":"Found duplicate: ..."}]
            if (is_array($body) && !isset($body['resourceType']) && !isset($body['id']) && !isset($body['entry'])) {
                if (!empty($body) && isset($body[0]['ruleNumber'])) {
                    $messages = array_map(fn($r) => $r['message'] ?? 'Unknown rule error', $body);
                    throw new \Exception("Satusehat Error: " . implode(", ", $messages));
                }
            }

            if ($res->getStatusCode() >= 400) {
                 if (is_array($body) && !isset($body['resourceType']) && !empty($body) && isset($body[0]['ruleNumber'])) {
                     $messages = array_map(fn($r) => $r['message'] ?? 'Unknown rule error', $body);
                     throw new \Exception("Satusehat Error: " . implode(", ", $messages));
                 }

                 if (is_array($body) && isset($body['resourceType']) && $body['resourceType'] === 'Bundle') {
                      $errors = [];
                      if (!empty($body['entry'])) {
                           foreach ($body['entry'] as $index => $entry) {
                                $entryResponse = $entry['response'] ?? null;
                                if ($entryResponse && isset($entryResponse['status']) && (int)$entryResponse['status'] >= 400) {
                                     $outcome = $entryResponse['outcome'] ?? null;
                                     $resourceType = $bundlePayload['entry'][$index]['resource']['resourceType'] ?? 'UnknownResource';
                                     $diagnostics = 'Unknown error';
                                     if ($outcome && isset($outcome['issue'])) {
                                          $issues = array_map(function($issue) {
                                               return $issue['diagnostics'] ?? $issue['details']['text'] ?? 'Unknown error';
                                          }, $outcome['issue']);
                                          $diagnostics = implode(', ', $issues);
                                     }
                                     $errors[] = "[{$resourceType}]: {$entryResponse['status']} - {$diagnostics}";
                                }
                           }
                      }
                      if (!empty($errors)) {
                           throw new \Exception("Satusehat Bundle Error: " . implode("; ", $errors));
                      }
                 }
                 throw new \Exception("HTTP Error " . $res->getStatusCode() . ": " . $res->getBody());
            }

            return $body;
        });
    }

    public function put(string $resource, string $id, array $payload): array
    {
        return $this->executeWithRetry(function () use ($resource, $id, $payload) {
            $token = $this->token();
            
            $this->sanitizePayloadDates($payload);
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
        });
    }

    public function patch(string $resource, string $id, array $payload, array $extraHeaders = []): array
    {
        return $this->executeWithRetry(function () use ($resource, $id, $payload, $extraHeaders) {
            $token = $this->token();
            
            $headers = array_merge([
                'Authorization' => "Bearer {$token}",
                'Content-Type'  => 'application/json-patch+json',
            ], $extraHeaders);

            $res = $this->client->patch(
                getenv('SATUSEHAT_BASE_URL') . "/fhir-r4/v1/{$resource}/{$id}",
                [
                    'headers' => $headers,
                    'body' => json_encode($payload),
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
        });
    }

    public function delete(string $resource, string $id): array
    {
        return $this->executeWithRetry(function () use ($resource, $id) {
            $token = $this->token();
            
            $res = $this->client->delete(
                getenv('SATUSEHAT_BASE_URL') . "/fhir-r4/v1/{$resource}/{$id}",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$token}",
                    ],
                    'http_errors' => false,
                    'timeout' => (int)(getenv('SATUSEHAT_TIMEOUT') ?: 10),
                    'connect_timeout' => (int)(getenv('SATUSEHAT_CONNECT_TIMEOUT') ?: 3),
                ]
            );

            $body = json_decode($res->getBody(), true) ?: [];
            
            if ($res->getStatusCode() >= 400) {
                 throw new \Exception("HTTP Error " . $res->getStatusCode() . " on delete: " . $res->getBody());
            }

            return $body;
        });
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

    /**
     * Memastikan format datetime FHIR valid dan berada dalam rentang yang diizinkan SatuSehat:
     * - Tidak boleh sebelum 3 Juni 2014 (2014-06-03)
     * - Tidak boleh di masa depan (Future date > waktu saat ini)
     */
    public function sanitizeFhirDateTime($dateInput = null, $timeInput = null, int $addSeconds = 0): string
    {
        $dateStr = !empty($dateInput) ? date('Y-m-d', strtotime($dateInput) ?: time()) : date('Y-m-d');
        $timeStr = !empty($timeInput) ? date('H:i:s', strtotime($timeInput) ?: time()) : date('H:i:s');

        $ts = strtotime("$dateStr $timeStr");
        if ($ts === false) {
            $ts = time();
        }

        $ts += $addSeconds;

        $minTs = strtotime('2014-06-03 00:00:00');
        $nowTs = time();

        if ($ts < $minTs) {
            $ts = $minTs;
        }
        if ($ts > $nowTs) {
            $ts = $nowTs;
        }

        return date('c', $ts);
    }

    /**
     * Secara rekursif memvalidasi dan membatasi field tanggal/waktu pada payload FHIR
     * agar tidak melanggar aturan Kemenkes (tidak boleh masa depan atau sebelum 3 Juni 2014).
     */
    public function sanitizePayloadDates(array &$payload): array
    {
        $restrictedDateKeys = [
            'effectiveDateTime',
            'date',
            'recordedDate',
            'recordedDateTime',
            'authoredOn',
            'authored',
            'issued',
            'occurrenceDateTime',
            'performedDateTime',
            'onsetDateTime',
        ];

        $minTs = strtotime('2014-06-03 00:00:00');
        $nowTs = time();

        foreach ($payload as $key => &$value) {
            if (is_array($value)) {
                $this->sanitizePayloadDates($value);
            } elseif (is_string($value) && in_array($key, $restrictedDateKeys, true)) {
                $ts = strtotime($value);
                if ($ts !== false) {
                    if ($ts > $nowTs) {
                        $value = date('c', $nowTs);
                    } elseif ($ts < $minTs) {
                        $value = date('c', $minTs);
                    }
                }
            }
        }

        return $payload;
    }
}
