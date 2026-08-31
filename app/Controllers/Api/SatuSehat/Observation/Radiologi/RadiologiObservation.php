<?php

namespace App\Controllers\Api\SatuSehat\Observation\Radiologi;

use App\Controllers\Api\SatuSehat\Observation\ObservationBase;
use App\Services\SatusehatService;
use App\Controllers\Api\SatuSehat\RadiologiMapping;

class RadiologiObservation extends ObservationBase
{
    public function __construct()
    {
        parent::__construct(new SatusehatService());
    }

    public function push($row, $encounterId)
    {
        $obsId = $row['SS_Observation_ID'] ?? $row['Observation_id'] ?? $row['ObservationId'] ?? $row['id'] ?? null;
        if (!empty($obsId)) {
            return $this->patch($obsId, $row);
        }

        if (empty($row['IHSSatuSehat'])) {
            return [
                'status' => 'error',
                'message' => 'IHS Patient ID tidak ditemukan.'
            ];
        }

        $mapping = RadiologiMapping::getMapping($row['KDDETAIL'] ?? ($row['ID'] ?? ''));

        if (!$mapping) {
            return [
                'status' => 'error',
                'message' => 'Mapping Radiologi tidak ditemukan untuk ID: ' . ($row['KDDETAIL'] ?? ($row['ID'] ?? '')) . '. Silahkan hubungi IT.'
            ];
        }

        $orgId = getenv('SATUSEHAT_ORG_ID');
        $identifierValue = "O-" . ($row['NoTran'] ?? uniqid());

        $dateOnly = date('Y-m-d', strtotime($row['Regdate'] ?? date('Y-m-d')));
        $timeOnly = date('H:i:s', strtotime($row['RegTime'] ?? date('H:i:s')));
        $effectiveDateTime = date('c', strtotime($dateOnly . ' ' . $timeOnly));

        $payload = [
            "resourceType" => "Observation",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/observation/" . $orgId,
                    "value" => $identifierValue
                ]
            ],
            "status" => "final",
            "category" => [
                [
                    "coding" => [
                        [
                            "system" => "http://terminology.hl7.org/CodeSystem/observation-category",
                            "code" => "imaging",
                            "display" => "Imaging"
                        ]
                    ]
                ]
            ],
            "code" => [
                "coding" => [],
                "text" => $mapping['name'] ?? ($row['NmTindakan'] ?? 'Pemeriksaan Radiologi')
            ],
        ];

        if ($mapping) {
            if (!empty($mapping['loinc'])) {
                $payload['code']['coding'][] = [
                    "system" => "http://loinc.org",
                    "code" => $mapping['loinc'],
                    "display" => $mapping['name']
                ];
            }
            if (!empty($mapping['snomed'])) {
                $payload['code']['coding'][] = [
                    "system" => "http://snomed.info/sct",
                    "code" => $mapping['snomed'],
                    "display" => $mapping['name']
                ];
            }
            if (!empty($mapping['xcode'])) {
                $payload['code']['coding'][] = [
                    "system" => "http://terminology.kemkes.go.id/CodeSystem/kptl",
                    "code" => $mapping['xcode'],
                    "display" => $mapping['name']
                ];
            }
        }

        $payload["subject"] = [
            "reference" => "Patient/" . $row['IHSSatuSehat'],
            "display" => $row['Firstname'] ?? ''
        ];
        $payload["encounter"] = [
            "reference" => "Encounter/" . $encounterId
        ];
        $payload["effectiveDateTime"] = $effectiveDateTime;
        $payload["issued"] = $effectiveDateTime;
        $payload["performer"] = [
            [
                "reference" => "Practitioner/" . ($row['kdDocSatuSehatRad'] ?? '10012572188'),
                "display" => $row['NmDocRad'] ?? 'Dokter Radiologist'
            ]
        ];
        $payload["valueString"] = $row['Hasil'] ?? '';
        $payload["basedOn"] = [];
        $payload["derivedFrom"] = [];

        if (!empty($row['ServiceRequestId'])) {
            $payload['basedOn'][] = [
                "reference" => "ServiceRequest/" . $row['ServiceRequestId']
            ];
        }

        if (!empty($row['ImagingStudyId'])) {
            $payload['derivedFrom'][] = [
                "reference" => "ImagingStudy/" . $row['ImagingStudyId']
            ];
        }

        return $this->sendFHIRObservation($payload);
    }

    public function patch($id, $row)
    {
        if (empty($id)) {
            return [
                'status'  => 'error',
                'message' => 'Observation ID is required for PATCH'
            ];
        }

        // Jika $row sudah berupa array JSON-patch operations: [ {"op": "replace", "path": "...", "value": "..."} ]
        if (isset($row[0]['op'])) {
            return $this->patchFHIRObservation($id, $row);
        }

        $operations = [];

        // 1. Update Hasil / valueString jika ada
        if (isset($row['Hasil']) || isset($row['valueString']) || isset($row['HASIL']) || isset($row['Kesan'])) {
            $hasilVal = $row['Hasil'] ?? $row['valueString'] ?? $row['HASIL'] ?? $row['Kesan'] ?? '';
            $operations[] = [
                'op'    => 'replace',
                'path'  => '/valueString',
                'value' => (string)$hasilVal
            ];
        }

        // 2. Update status jika ada
        if (!empty($row['status'])) {
            $operations[] = [
                'op'    => 'replace',
                'path'  => '/status',
                'value' => $row['status']
            ];
        }

        // 3. Update performer (Dokter Radiologi) jika ada
        if (!empty($row['kdDocSatuSehatRad']) || !empty($row['KdDocSatuSehat']) || !empty($row['KDDOCSATUSEHAT'])) {
            $docId = $row['kdDocSatuSehatRad'] ?? $row['KdDocSatuSehat'] ?? $row['KDDOCSATUSEHAT'];
            $docName = $row['NmDocRad'] ?? $row['NmDoc'] ?? $row['NMDOC'] ?? 'Dokter Radiologist';
            $operations[] = [
                'op'    => 'replace',
                'path'  => '/performer',
                'value' => [
                    [
                        'reference' => 'Practitioner/' . $docId,
                        'display'   => $docName
                    ]
                ]
            ];
        }

        // 4. Update derivedFrom (ImagingStudy) jika ada
        if (!empty($row['ImagingStudyId']) || !empty($row['ImagingStudy_id'])) {
            $imagingStudyId = $row['ImagingStudyId'] ?? $row['ImagingStudy_id'];
            $operations[] = [
                'op'    => 'replace',
                'path'  => '/derivedFrom',
                'value' => [
                    [
                        'reference' => 'ImagingStudy/' . $imagingStudyId
                    ]
                ]
            ];
        }

        // 5. Update basedOn (ServiceRequest) jika ada
        if (!empty($row['ServiceRequestId'])) {
            $operations[] = [
                'op'    => 'replace',
                'path'  => '/basedOn',
                'value' => [
                    [
                        'reference' => 'ServiceRequest/' . $row['ServiceRequestId']
                    ]
                ]
            ];
        }

        if (empty($operations)) {
            if (isset($row['Hasil'])) {
                $operations[] = [
                    'op'    => 'replace',
                    'path'  => '/valueString',
                    'value' => (string)$row['Hasil']
                ];
            } else {
                return [
                    'status' => 'success',
                    'id'     => $id,
                    'action' => 'no_change'
                ];
            }
        }

        return $this->patchFHIRObservation($id, $operations);
    }
}
