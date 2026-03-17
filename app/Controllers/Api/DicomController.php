<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\HTTP\ResponseInterface;

class DicomController extends ResourceController
{
    private $orthancUrl = 'http://192.168.106.89:8042/';

    public function getDicomByDate($date)
    {
        // Format tanggal dari parameter: YYYY-MM-DD
        $startDateFormatted = date('Ymd', strtotime($date)); // ubah ke format DICOM
        log_message('debug', 'Tanggal input (Ymd): ' . $startDateFormatted);

        $url = $this->orthancUrl . "studies";
        $username = 'orthanc';
        $password = 'orthanc';

        $client = \Config\Services::curlrequest();

        try {
            // Ambil semua study dari Orthanc
            $response = $client->request('GET', $url, [
                'auth' => [$username, $password]
            ]);

            $data = json_decode($response->getBody());
            if (empty($data)) {
                return $this->failNotFound("Tidak ada studi DICOM ditemukan untuk tanggal $date");
            }

            $result = [];
            foreach ($data as $studyId) {
                $detailUrl = $this->orthancUrl . "studies/$studyId";
                $detailResponse = $client->request('GET', $detailUrl, [
                    'auth' => [$username, $password]
                ]);

                $studyDetail = json_decode($detailResponse->getBody());

                $studyDate = $studyDetail->MainDicomTags->StudyDate ?? null;
                log_message('debug', "Study ID: $studyId | StudyDate: $studyDate");

                // ✅ Perbandingan dengan format sama (YYYYmmdd)
                if ($studyDate === $startDateFormatted) {
                    $patientData = $studyDetail->PatientMainDicomTags ?? new \stdClass();
                // Ambil PatientID asli
                $rawPatientId = $patientData->PatientID ?? 'Unknown';

                 // Proses Format: TMC01-xx.xx.xx
                 if ($rawPatientId !== 'Unknown' && is_numeric($rawPatientId)) {
                 // 1. str_pad: Menambahkan '0' di depan sampai panjangnya 6 karakter
                 $paddedId = str_pad($rawPatientId, 6, '0', STR_PAD_LEFT);

                 // 2. str_split: Memecah string menjadi array per 2 karakter (chunk size = 2)
                 //    '000123' menjadi ['00', '01', '23']
                 $chunks = str_split($paddedId, 2);

                 // 3. implode: Menggabungkan array dengan separator titik '.'
                 //    ['00', '01', '23'] menjadi '00.01.23'
                 $formattedPatientId = 'TMC01-' . implode('.', $chunks);
                 } else {
                 // Jika bukan angka atau Unknown, biarkan apa adanya (sesuaikan jika mau paksa format)
                $formattedPatientId = $rawPatientId;
             }


                    $result[] = [
                        'PatientID'        => $patientData->PatientID ?? 'Unknown',
                        'PatientName'      => $patientData->PatientName ?? 'Unknown',
                        'PatientBirthDate' => $patientData->PatientBirthDate ?? 'Unknown',
                        'PatientSex'       => $patientData->PatientSex ?? 'Unknown',
                        'StudyDate'        => $studyDate,
                        'StudyDescription' => $studyDetail->MainDicomTags->StudyDescription ?? 'Unknown',
                        'AccessionNumber'  => $studyDetail->MainDicomTags->AccessionNumber ?? 'Unknown',
                    ];
                }
            }

            if (empty($result)) {
                return $this->respond([], ResponseInterface::HTTP_OK); // hasil kosong tapi sukses
            }

            return $this->respond($result, ResponseInterface::HTTP_OK);

        } catch (\Exception $e) {
            log_message('error', 'Error: ' . $e->getMessage());
            return $this->failServerError('Terjadi kesalahan saat mengambil data DICOM.');
        }
    }
}
