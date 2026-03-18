<?php

namespace App\Controllers\Api\SatuSehat\DiagnosticReport;

use App\Controllers\Api\SatuSehat\DiagnosticReport\DiagnosticReportBase;
use App\Services\SatusehatService;

class DiagnosticReport extends DiagnosticReportBase
{
    public function __construct()
    {
        parent::__construct(new SatusehatService());
    }

    public function buildPayload($row, $encounterId)
    {
        $orgId = getenv('SATUSEHAT_ORG_ID');
        $reportId = $row['NoTran']; // Unique ID for Report

        $effectiveDateTime = date('c', strtotime($row['TglHasil'] ?? date('c')));
        $issued = $effectiveDateTime;

        // Default Category and Code (General Lab)
        $categoryCode = "LAB";
        $categoryDisplay = "Laboratory";
        $loincCode = "11502-2";
        $loincDisplay = "Laboratory report";

        // Logic to detect Microbiology / Sputum based on AsalSampel or Kategori
        $asalSampel = strtolower($row['AsalSampel'] ?? '');
        $kategori = strtoupper($row['Kategori'] ?? '');

        // Check for Microbiology (MB)
        if (strpos($asalSampel, 'sputum') !== false || strpos($asalSampel, 'dahak') !== false || $kategori === 'MB' || $kategori === 'MIKROBIOLOGI') {
            $categoryCode = "MB";
            $categoryDisplay = "Microbiology";

            // Specific check for Sputum/Acid fast stain
            if (strpos($asalSampel, 'sputum') !== false || strpos($asalSampel, 'dahak') !== false) {
                $loincCode = "11477-7";
                $loincDisplay = "Microscopic observation [Identifier] in Sputum by Acid fast stain";
            }
        }

        $payload = [
            "resourceType" => "DiagnosticReport",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/diagnostic/" . $orgId . "/lab",
                    "use" => "official",
                    "value" => $reportId
                ]
            ],
            "status" => "final",
            "category" => [
                [
                    "coding" => [
                        [
                            "system" => "http://terminology.hl7.org/CodeSystem/v2-0074",
                            "code" => $categoryCode,
                            "display" => $categoryDisplay
                        ]
                    ]
                ]
            ],
            "code" => [
                "coding" => [
                    [
                        "system" => "http://loinc.org",
                        "code" => $loincCode,
                        "display" => $loincDisplay
                    ]
                ],
                "text" => $loincDisplay
            ],
            "subject" => [
                "reference" => "Patient/" . $row['IHSSatuSehat']
            ],
            "encounter" => [
                "reference" => "Encounter/" . $encounterId
            ],
            "effectiveDateTime" => $effectiveDateTime,
            "issued" => $issued,
            "performer" => [
                [
                    "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? '')
                ],
                [
                    "reference" => "Organization/" . $orgId
                ]
            ],
            "result" => [], // To be populated
            "specimen" => [], // To be populated
            "basedOn" => [], // To be populated
            "conclusion" => $row['Kesan'] ?? ''
        ];

        // Add Conclusion Code if available
        if (!empty($row['Kesan'])) {
            $kesan = strtolower($row['Kesan']);
            $snomedCode = null;
            $snomedDisplay = null;

            if (strpos($kesan, 'positif') !== false || strpos($kesan, 'positive') !== false || strpos($kesan, '+') !== false) {
                $snomedCode = "260347006";
                $snomedDisplay = "+";
            } elseif (strpos($kesan, 'negatif') !== false || strpos($kesan, 'negative') !== false || strpos($kesan, '-') !== false) {
                $snomedCode = "260385009";
                $snomedDisplay = "-";
            }

            if ($snomedCode) {
                $payload['conclusionCode'] = [
                    [
                        "coding" => [
                            [
                                "system" => "http://snomed.info/sct",
                                "code" => $snomedCode,
                                "display" => $snomedDisplay
                            ]
                        ]
                    ]
                ];
            }
        }

        if (!empty($row['SpecimenId'])) {
            $specRef = $row['SpecimenId'];
            if (strpos($specRef, 'urn:uuid:') !== 0) {
                $specRef = "Specimen/" . $specRef;
            }
            $payload['specimen'][] = [
                "reference" => $specRef
            ];
        }

        if (!empty($row['ServiceRequestId'])) {
            $reqRef = $row['ServiceRequestId'];
            if (strpos($reqRef, 'urn:uuid:') !== 0) {
                $reqRef = "ServiceRequest/" . $reqRef;
            }
            $payload['basedOn'][] = [
                "reference" => $reqRef
            ];
        }

        if (!empty($row['ObservationIds']) && is_array($row['ObservationIds'])) {
            foreach ($row['ObservationIds'] as $obsId) {
                $obsRef = $obsId;
                if (strpos($obsRef, 'urn:uuid:') !== 0) {
                    $obsRef = "Observation/" . $obsRef;
                }
                $payload['result'][] = [
                    "reference" => $obsRef
                ];
            }
        }

        return $payload;
    }

    public function push($row, $encounterId)
    {
        $payload = $this->buildPayload($row, $encounterId);
        return $this->sendFHIRDiagnosticReport($payload);
    }
}
