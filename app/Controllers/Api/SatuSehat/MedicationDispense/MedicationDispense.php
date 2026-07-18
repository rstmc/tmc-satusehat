<?php

namespace App\Controllers\Api\SatuSehat\MedicationDispense;

class MedicationDispense extends MedicationDispenseBase
{
    public function buildPayload($row, $encounterId, $medRequestId = null)
    {
        // Organization ID from environment or config
        $orgId = getenv('SATUSEHAT_ORG_ID');

        $identifierValue = $row['NoResep'] ?? '123456788';
        $urutan = $row['Urutan'] ?? $row['KodeObat'] ?? '1';
        if (strpos($urutan, '-') !== false) {
            $parts = explode('-', $urutan);
            $urutan = $parts[0];
        }
        $identifierItemValue = $identifierValue . '-' . $urutan;

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
        if (!empty($reqRef)) {
            if (strpos($reqRef, 'urn:uuid:') !== 0) {
                $reqRef = "MedicationRequest/" . $reqRef;
            }
        } else {
            $reqRef = '';
        }

        $satuanRaw = $row['Satuan'] ?? $row['SatuanObat'] ?? 'TAB';
        $sUpper = strtoupper(trim($satuanRaw));
        $mapping = [
            'TABLET' => 'TAB',
            'TAB' => 'TAB',
            'CAPSULE' => 'CAP',
            'CAPSUL' => 'CAP',
            'KAPSUL' => 'CAP',
            'CAP' => 'CAP',
            'BOTOL' => 'ORALSOL',
            'BOTTLE' => 'ORALSOL',
            'BOT' => 'ORALSOL',
            'AMPUL' => 'TAB',
            'AMP' => 'TAB',
            'VIAL' => 'TAB',
            'BOX' => 'TAB',
            'PC' => 'TAB',
            'PCS' => 'TAB',
            'PIECE' => 'TAB',
            'SACHET' => 'TAB',
            'SUPP' => 'SUPP',
            'SYRUP' => 'SYR',
            'SIRUP' => 'SYR'
        ];
        $drugFormCode = $mapping[$sUpper] ?? 'TAB';

        $daysSupply = 1;
        if (isset($row['JumlahHari']) && is_numeric($row['JumlahHari']) && (int)$row['JumlahHari'] > 0) {
            $daysSupply = (int)$row['JumlahHari'];
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
                "code" => $drugFormCode,
                "value" => isset($row['Qty']) ? (float)$row['Qty'] : 1
            ],
            "daysSupply" => [
                "value" => $daysSupply,
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
