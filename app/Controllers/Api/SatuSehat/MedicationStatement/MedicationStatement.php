<?php

namespace App\Controllers\Api\SatuSehat\MedicationStatement;

class MedicationStatement extends MedicationStatementBase
{
    public function buildPayload($row, $encounterId)
    {
        // Validation
        if (empty($row['IHSSatuSehat'])) {
            return null;
        }

        if (empty($encounterId)) {
            return null;
        }

        if (empty($row['KFA'])) {
            // If no KFA, we can't send valid medication info. 
            return null;
        }

        $orgId = env('SATUSEHAT_ORG_ID') ?: getenv('SATUSEHAT_ORG_ID') ?: ($_ENV['SATUSEHAT_ORG_ID'] ?? '');
        $urutan = (string)($row['Urutan'] ?? $row['KodeObat'] ?? '1');
        if (strpos($urutan, '-') !== false) {
            $parts = explode('-', $urutan);
            $urutan = $parts[0];
        }
        $identifierValue = (string)(($row['Regno'] ?? ($row['NoResep'] ?? '')) . '-' . $urutan . '-medication-statement');

        $dateInput = $row['TglResep'] ?? $row['RegDate'] ?? $row['Regdate'] ?? null;
        $timeInput = $row['RegTime'] ?? $row['Jam'] ?? '00:00:00';
        $dateTs = $dateInput ? strtotime($dateInput) : time();
        if ($dateTs === false || $dateInput === '0000-00-00') {
            $dateTs = strtotime(date('Y-m-d'));
        }
        $dateStr = date('Y-m-d', $dateTs);
        $timeTs = strtotime($timeInput);
        if ($timeTs === false) {
            $timeTs = strtotime('00:00:00');
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
        $dateAsserted = date('c', $ts);

        $status = 'active';
        if (isset($row['JumlahHari']) && is_numeric($row['JumlahHari'])) {
            // Pakai round() untuk handle decimal (misal 3.50 hari)
            $jumlahHariInt = (int)round((float)$row['JumlahHari']);
            if ($jumlahHariInt > 0) {
                $endTs = $ts + ($jumlahHariInt * 86400);
                $status = (time() > $endTs) ? 'completed' : 'active';
            }
        }

        // Dosage text construction
        $dosageText = $row['AturanPakai'] ?? '';
        if (!empty($row['Signa1']) && !empty($row['Signa2'])) {
            $dosageText .= ' ' . $row['Signa1'] . 'x' . $row['Signa2'];
        }
        if (!empty($row['KeteranganPakai'])) {
            $dosageText .= ' ' . $row['KeteranganPakai'];
        }
        $dosageText = trim($dosageText);
        if (empty($dosageText)) $dosageText = "Pakai sesuai instruksi";

        // Tentukan category berdasarkan jenis kunjungan (konsisten dengan MedicationRequest/Dispense)
        $kdTuju      = strtoupper(trim($row['KdTuju'] ?? 'RJ'));
        $kdPoli      = trim($row['KdPoli'] ?? '');
        $isIGD       = ($kdPoli === '30');
        $isRawatInap = !in_array($kdTuju, ['RJ', '2']);

        if (!$isIGD && !$isRawatInap) {
            $msCategory     = 'outpatient';
            $msCategoryDisp = 'Outpatient';
        } else {
            $obatPulang = !empty($row['ObatPulang']) && $row['ObatPulang'] == 1;
            $msCategory     = $obatPulang ? 'discharge' : 'inpatient';
            $msCategoryDisp = $obatPulang ? 'Discharge' : 'Inpatient';
        }

        $payload = [
            "resourceType" => "MedicationStatement",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/medicationstatement/" . $orgId,
                    "use" => "official",
                    "value" => $identifierValue
                ]
            ],
            "status" => $status,
            "category" => [
                "coding" => [
                    [
                        "system" => "http://terminology.hl7.org/CodeSystem/medication-statement-category",
                        "code" => $msCategory,
                        "display" => $msCategoryDisp
                    ]
                ]
            ],
            "medicationCodeableConcept" => [
                "coding" => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/kfa",
                        "code" => $row['KFA'],
                        "display" => $row['NamaObat'] ?? 'Unknown Medication'
                    ]
                ]
            ],
            "subject" => [
                "reference" => "Patient/" . $row['IHSSatuSehat']
            ],
            "context" => [
                "reference" => "Encounter/" . $encounterId
            ],
            "dateAsserted" => $dateAsserted,
            "informationSource" => [
                // Prioritaskan Practitioner jika ada, fallback ke Patient
                "reference" => !empty($row['KdDocSatuSehat'])
                    ? "Practitioner/" . $row['KdDocSatuSehat']
                    : "Patient/" . $row['IHSSatuSehat']
            ],
            "dosage" => [
                [
                    "text" => $dosageText
                ]
            ]
        ];

        // Optional: Add dosage timing if Signa1/Signa2 available and numeric >= 1
        if (isset($row['Signa1']) && is_numeric($row['Signa1']) && (int)$row['Signa1'] >= 1) {
            $payload['dosage'][0]['timing'] = [
                "repeat" => [
                    "frequency" => (int)$row['Signa1'],
                    "period" => 1,
                    "periodUnit" => "d" // Assuming per day
                ]
            ];
        }

        return $payload;
    }

    public function push($row, $encounterId)
    {
        $payload = $this->buildPayload($row, $encounterId);

        if ($payload === null) {
            // Re-validate to return specific error messages
            if (empty($row['IHSSatuSehat'])) {
                return ['status' => 'failed', 'message' => 'Missing IHSSatuSehat'];
            }
            if (empty($encounterId)) {
                return ['status' => 'failed', 'message' => 'Missing Encounter ID'];
            }
            if (empty($row['KFA'])) {
                return ['status' => 'skipped', 'message' => 'Missing KFA Code for ' . ($row['NamaObat'] ?? 'Unknown Drug')];
            }
            return ['status' => 'failed', 'message' => 'Failed to build payload'];
        }

        return $this->sendFHIRMedicationStatement($payload);
    }
}
