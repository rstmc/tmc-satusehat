<?php

namespace App\Controllers\Api\SatuSehat\MedicationDispense;

class MedicationDispense extends MedicationDispenseBase
{
    public function buildPayload($row, $encounterId, $medRequestId = null)
    {
        // Organization ID from environment or config
        $orgId = env('SATUSEHAT_ORG_ID') ?: getenv('SATUSEHAT_ORG_ID') ?: ($_ENV['SATUSEHAT_ORG_ID'] ?? '');

        // Identifier: gunakan NoResep jika ada, fallback ke regno-urutan
        $identifierValue = !empty($row['NoResep']) ? (string)$row['NoResep'] : (!empty($row['BLCode']) ? (string)$row['BLCode'] : (string)($row['Regno'] ?? 'UNKNOWN'));
        $urutan = (string)($row['Urutan'] ?? $row['KodeObat'] ?? '1');
        if (strpos($urutan, '-') !== false) {
            $parts = explode('-', $urutan);
            $urutan = $parts[0];
        }
        // Suffix '-d' membedakan MedicationDispense dari MedicationRequest yang pakai identifier yang sama
        $identifierItemValue = $identifierValue . '-' . $urutan . '-d';

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
        if (!empty($medRef)) {
            if (strpos($medRef, 'urn:uuid:') === 0 || strpos($medRef, 'Medication/') === 0) {
                // already has prefix
            } else {
                $medRef = "Medication/" . $medRef;
            }
        }

        $reqRef = $medRequestId ?? $row['MedicationRequestId'] ?? '';
        if (!empty($reqRef)) {
            if (strpos($reqRef, 'urn:uuid:') === 0) {
                // valid bundle internal urn:uuid
            } elseif (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $reqRef)) {
                $reqRef = "MedicationRequest/" . $reqRef;
            } elseif (strpos($reqRef, 'MedicationRequest/') === 0 && preg_match('/^MedicationRequest\/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $reqRef)) {
                // valid MedicationRequest/{uuid}
            } else {
                // invalid format, omit authorizingPrescription to prevent Rule 10393
                $reqRef = '';
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

        // daysSupply harus integer — pakai round() untuk handle decimal (misal 3.50 → 4)
        $daysSupply = 1;
        if (isset($row['JumlahHari']) && is_numeric($row['JumlahHari'])) {
            $rounded = (int)round((float)$row['JumlahHari']);
            if ($rounded > 0) {
                $daysSupply = $rounded;
            }
        }

        // Tentukan category berdasarkan jenis kunjungan:
        // - RJ bukan poli 30 → outpatient (semua obat adalah obat jalan)
        // - IGD (KdPoli=30) → cek ObatPulang: 1=discharge, 0=inpatient
        // - Rawat Inap (KdTuju != RJ) → cek ObatPulang: 1=discharge, 0=inpatient
        $kdTuju  = strtoupper(trim($row['KdTuju'] ?? 'RJ'));
        $kdPoli  = trim($row['KdPoli'] ?? '');
        $isIGD   = ($kdPoli === '30');
        $isRawatInap = ($kdTuju !== 'RJ');

        if (!$isIGD && !$isRawatInap) {
            // Rawat jalan biasa (non-IGD) → selalu outpatient
            $dispenseCategory     = 'outpatient';
            $dispenseCategoryDisp = 'Outpatient';
        } else {
            // IGD atau rawat inap → cek flag ObatPulang per item
            $obatPulang = !empty($row['ObatPulang']) && $row['ObatPulang'] == 1;
            if ($obatPulang) {
                $dispenseCategory     = 'discharge';
                $dispenseCategoryDisp = 'Discharge';
            } else {
                $dispenseCategory     = 'inpatient';
                $dispenseCategoryDisp = 'Inpatient';
            }
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
                        "code" => $dispenseCategory,
                        "display" => $dispenseCategoryDisp
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
            // authorizingPrescription: hanya kirim jika ada referensi MedicationRequest

            "quantity" => [
                "system" => "http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm",
                "code" => $drugFormCode,
                // quantity.value harus >= 0 (ambil absolute value jika minus, fallback ke 1)
                "value" => isset($row['Qty']) ? (abs((int)round((float)$row['Qty'])) ?: 1) : 1
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
                            "frequency" => isset($row['Signa1']) && is_numeric($row['Signa1']) && (int)$row['Signa1'] >= 1 ? (int)$row['Signa1'] : 1,
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

        // authorizingPrescription: hanya kirim jika ada MedicationRequest reference
        if (!empty($reqRef)) {
            $payload["authorizingPrescription"] = [
                ["reference" => $reqRef]
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
