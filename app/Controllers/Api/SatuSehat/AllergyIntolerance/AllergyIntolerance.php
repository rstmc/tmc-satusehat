<?php

namespace App\Controllers\Api\SatuSehat\AllergyIntolerance;

class AllergyIntolerance extends AllergyIntoleranceBase
{
    public function buildPayload($row, $encounterId)
    {
        // Organization ID from environment or config
        $orgId = getenv('SATUSEHAT_ORG_ID');

        // Logic Mapping
        // RiwayatAlergi: 1=Tidak Ada, 2=Ada
        $riwayatAlergi = $row['RiwayatAlergi'] ?? '1';
        
        if ($riwayatAlergi == '1') {
            return null; // Return null to indicate no payload
        }

        // RiwayatAlergiOpsi: 1=Obat, 2=Makanan, 3=Lainnya
        $RiwayatAlergiOpsi = $row['RiwayatAlergiOpsi'] ?? '3';
        
        $category = 'environment'; // Default
        $snomedCode = '419199007'; // Allergy to substance (disorder) - generic
        $snomedDisplay = 'Allergy to substance';
        
        if ($RiwayatAlergiOpsi == '1') {
            $category = 'medication';
            // Example generic code for drug allergy if specific not available
            $snomedCode = '416098002'; 
            $snomedDisplay = 'Drug allergy';
        } elseif ($RiwayatAlergiOpsi == '2') {
            $category = 'food';
            // Example generic code for food allergy
            $snomedCode = '414285001';
            $snomedDisplay = 'Food allergy';
        } elseif ($RiwayatAlergiOpsi == '3') {
            $category = 'environment'; // Or 'biologic' depending on context
            $snomedCode = '419199007';
            $snomedDisplay = 'Allergy to substance';
        }

        // Identifier
        // Use a unique combination if possible, e.g., Regno + timestamp or similar
        // Since we don't have a specific allergy ID, we might generate one or use a placeholder
        // Using Regno + '-allergy-' + index or similar might be better if multiple allergies
        $regNo = $row['RegNo'] ?? $row['Regno'] ?? 'Unknown';
        $identifierValue = $regNo . '-allergy-' . time(); 
        
        // Recorded Date
        $regDate = $row['RegDate'] ?? $row['Regdate'] ?? date('Y-m-d');
        $regTime = $row['RegTime'] ?? $row['Regtime'] ?? date('H:i:s');
        
        // Basic validation
        if (empty($regDate) || $regDate === '0000-00-00') {
            $regDate = date('Y-m-d');
        }
        
        $timestamp = strtotime("$regDate $regTime");
        if ($timestamp === false || $timestamp < 0) {
            $timestamp = time();
        }
        
        $recordedDate = date('c', $timestamp);

        $allergyText = $row['ReaksiAlergi'] ?? ('Alergi ' . $category);
        if (empty($allergyText)) {
            $allergyText = 'Alergi ' . $category;
        }

        $payload = [
            "resourceType" => "AllergyIntolerance",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/allergy/" . $orgId,
                    "use" => "official",
                    "value" => $identifierValue
                ]
            ],
            "clinicalStatus" => [
                "coding" => [
                    [
                        "system" => "http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical",
                        "code" => "active",
                        "display" => "Active"
                    ]
                ]
            ],
            "verificationStatus" => [
                "coding" => [
                    [
                        "system" => "http://terminology.hl7.org/CodeSystem/allergyintolerance-verification",
                        "code" => "confirmed",
                        "display" => "Confirmed"
                    ]
                ]
            ],
            "category" => [
                $category
            ],
            "code" => [
                "coding" => [
                    [
                        "system" => "http://snomed.info/sct",
                        "code" => $snomedCode,
                        "display" => $snomedDisplay
                    ]
                ],
                "text" => $allergyText
            ],
            "patient" => [
                "reference" => "Patient/" . ($row['IHSSatuSehat'] ?? ''),
                "display" => $row['Firstname'] ?? ''
            ],
            "encounter" => [
                "reference" => "Encounter/" . $encounterId,
                "display" => "Kunjungan " . ($row['Firstname'] ?? '')
            ],
            "recordedDate" => $recordedDate,
            "recorder" => [
                "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? 'N10000001'),
                "display" => $row['NmDoc'] ?? ''
            ]
        ];
        
        // Optional: Add Reaction if available and meaningful
        if (!empty($row['ReaksiAlergi'])) {
             $payload['reaction'] = [
                [
                    "manifestation" => [
                        [
                            "coding" => [
                                [
                                    "system" => "http://snomed.info/sct",
                                    "code" => "422587007", // Nausea (example) - ideally mapped from free text, but difficult without standard code
                                    "display" => "Nausea"
                                ]
                            ],
                            "text" => $row['ReaksiAlergi']
                        ]
                    ]
                ]
            ];
            // Since we can't map free text reaction to SNOMED easily, we might just put text in manifestation text
            // Or omit coding if not sure. SatuSehat usually requires at least one coding.
            // Let's use a generic 'Allergic reaction' code if we don't know
            $payload['reaction'][0]['manifestation'][0]['coding'][0]['code'] = '281647001';
            $payload['reaction'][0]['manifestation'][0]['coding'][0]['display'] = 'Adverse reaction';
        }

        return $payload;
    }

    public function push($row, $encounterId)
    {
        $payload = $this->buildPayload($row, $encounterId);
        
        if ($payload === null) {
             // Replicate original check logic for consistency
             $riwayatAlergi = $row['RiwayatAlergi'] ?? '1';
             if ($riwayatAlergi == '1') {
                return ['status' => 'skipped', 'message' => 'Tidak ada riwayat alergi'];
             }
             return null;
        }

        return $this->sendFHIRAllergyIntolerance($payload);
    }
}
