<?php

namespace App\Controllers\Api\SatuSehat\MedicationDispense;

class MedicationDispense extends MedicationDispenseBase
{
    public function buildPayload($row, $encounterId, $medRequestId = null)
    {
        // Organization ID from environment or config
        $orgId = getenv('SATUSEHAT_ORG_ID');

        $identifierValue = $row['NoResep'] ?? '123456788';
        // Use a unique suffix if available, or default
        $identifierItemValue = $identifierValue . '-' . ($row['Urutan'] ?? '1');

        // Timestamps
        $dateInput = $row['TglResep'] ?? $row['RegDate'] ?? $row['Regdate'] ?? date('Y-m-d');
        $timeInput = $row['RegTime'] ?? $row['Jam'] ?? date('H:i:s');
        $dateTs = strtotime($dateInput);
        if ($dateTs === false || $dateInput === '0000-00-00') {
            $dateTs = strtotime(date('Y-m-d'));
        }
        $dateStr = date('Y-m-d', $dateTs);
        $timeTs = strtotime($timeInput);
        if ($timeTs === false) {
            $timeTs = strtotime(date('H:i:s'));
        }
        $timeStr = date('H:i:s', $timeTs);
        $ts = strtotime($dateStr . ' ' . $timeStr);
        if ($ts === false) {
            $ts = time();
        }
        $minTs = strtotime('2014-06-03 00:00:00');
        $nowTs = time();
        if ($ts < $minTs) {
            $ts = $minTs;
        }
        if ($ts > $nowTs) {
            $ts = $nowTs;
        }
        $preparedTime = date('c', $ts);
        $handedOverTime = date('c', $ts);

        $medRef = $row['MedicationId'] ?? '';
        if (strpos($medRef, 'urn:uuid:') !== 0) {
            $medRef = "Medication/" . $medRef;
        }

        $reqRef = $medRequestId ?? $row['MedicationRequestId'] ?? '';
        if (strpos($reqRef, 'urn:uuid:') !== 0) {
            $reqRef = "MedicationRequest/" . $reqRef;
        }

        $payload = [
            "resourceType" => "MedicationDispense",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/prescription/" . $orgId,
                    "use" => "official",
                    "value" => $identifierValue
                ],
                [
                    "system" => "http://sys-ids.kemkes.go.id/prescription-item/" . $orgId,
                    "use" => "official",
                    "value" => $identifierItemValue
                ]
            ],
            "status" => "completed",
            "category" => [
                "coding" => [
                    [
                        "system" => "http://terminology.hl7.org/fhir/CodeSystem/medicationdispense-category",
                        "code" => "outpatient",
                        "display" => "Outpatient"
                    ]
                ]
            ],
            "medicationReference" => [
                "reference" => $medRef,
                "display" => $row['NamaObat'] ?? ''
            ],
            "subject" => [
                "reference" => "Patient/" . ($row['IHSSatuSehat'] ?? ''),
                "display" => $row['Firstname'] ?? ''
            ],
            "context" => [
                "reference" => "Encounter/" . $encounterId
            ],
            "performer" => [
                [
                    "actor" => [
                        "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? ''), 
                        "display" => $row['NmDoc'] ?? ''
                    ]
                ]
            ],
            "authorizingPrescription" => [
                [
                    "reference" => $reqRef
                ]
            ],
            "quantity" => [
                "system" => "http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm",
                "code" => (!empty($row['Satuan']) ? $row['Satuan'] : 'TAB'),
                "value" => isset($row['Qty']) ? (float)$row['Qty'] : 1
            ],
            "daysSupply" => [
                "value" => isset($row['JumlahHari']) ? (int)$row['JumlahHari'] : 1,
                "unit" => "Day",
                "system" => "http://unitsofmeasure.org",
                "code" => "d"
            ],
            "whenPrepared" => $preparedTime,
            "whenHandedOver" => $handedOverTime,
            "dosageInstruction" => [
                [
                    "sequence" => 1,
                    "text" => $row['AturanPakai'] ?? ($row['KeteranganPakai'] ?? 'Ikuti petunjuk dokter'),
                    "timing" => [
                        "repeat" => [
                            "frequency" => isset($row['Signa1']) && is_numeric($row['Signa1']) ? (int)$row['Signa1'] : 1,
                            "period" => 1,
                            "periodUnit" => "d"
                        ]
                    ],
                    "route" => [
                        "coding" => [
                            [
                                "system" => "http://www.whocc.no/atc",
                                "code" => "O", // Oral as default, needs mapping based on form
                                "display" => "Oral"
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $locationId = trim($row['LocationId'] ?? (getenv('SATUSEHAT_LOCATION_ID') ?: ''));
        if ($locationId !== '') {
            $payload["location"] = [
                "reference" => "Location/" . $locationId,
                "display" => $row['NamaLokasi'] ?? 'Instalasi Farmasi'
            ];
        }

        return $payload;
    }

    public function push($row, $encounterId, $medRequestId = null)
    {
        $payload = $this->buildPayload($row, $encounterId, $medRequestId);
        return $this->sendFHIRMedicationDispense($payload);
    }
}
