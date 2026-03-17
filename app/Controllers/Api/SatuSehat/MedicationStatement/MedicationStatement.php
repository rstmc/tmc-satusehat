<?php

namespace App\Controllers\Api\SatuSehat\MedicationStatement;

class MedicationStatement extends MedicationStatementBase
{
    public function push($row, $encounterId)
    {
        // Validation
        if (empty($row['IHSSatuSehat'])) {
            return ['status' => 'failed', 'message' => 'Missing IHSSatuSehat'];
        }

        if (empty($encounterId)) {
            return ['status' => 'failed', 'message' => 'Missing Encounter ID'];
        }

        if (empty($row['KFA'])) {
            // If no KFA, we can't send valid medication info. 
            // Or we could send a display-only code if allowed, but KFA is preferred.
            return ['status' => 'skipped', 'message' => 'Missing KFA Code for ' . ($row['NamaObat'] ?? 'Unknown Drug')];
        }

        $orgId = getenv('SATUSEHAT_ORG_ID');
        
        // Construct unique identifier for this statement
        // Using NoResep + KodeObat or BLCode + KodeObat
        $identifierValue = ($row['NoResep'] ?? $row['Regno']) . '-' . ($row['KodeObat'] ?? 'UNK');

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
            $endTs = $ts + ((int)$row['JumlahHari'] * 86400);
            $status = (time() > $endTs) ? 'completed' : 'active';
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
                        "code" => "outpatient",
                        "display" => "Outpatient"
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
                "reference" => "Patient/" . $row['IHSSatuSehat'] // Or Practitioner if we have doctor info
            ],
            "dosage" => [
                [
                    "text" => $dosageText
                ]
            ]
        ];
        
        // Optional: Add dosage timing if Signa1/Signa2 available and numeric
        if (isset($row['Signa1']) && is_numeric($row['Signa1'])) {
            $payload['dosage'][0]['timing'] = [
                "repeat" => [
                    "frequency" => (int)$row['Signa1'],
                    "period" => 1,
                    "periodUnit" => "d" // Assuming per day
                ]
            ];
        }

        return $this->sendFHIRMedicationStatement($payload);
    }
}
