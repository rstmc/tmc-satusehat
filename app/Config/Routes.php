<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// ================== WEB (jika perlu) ==================
$routes->get('/', 'Home::index');

$routes->get('test/oracle', 'TestDb::oracle');
$routes->get('test/sqlserver', 'TestDb::sqlserver');




// ================== OPTIONS (CORS FIX) ==================
$routes->options('api/(:any)', function () {
    return response()->setStatusCode(200);
});

// ================== API ==================
$routes->group('api', ['namespace' => 'App\Controllers\Api'], function ($routes) {
    // Master Pasien Routes
    $routes->get('masterpasien/list', 'MasterPasien::list');
    $routes->get('masterpasien/get-satusehat-ihs/(:any)', 'MasterPasien::getSatuSehatIHS/$1');

    // Master Poli Routes
    $routes->get('masterpoli/list', 'MasterPoli::list');

    // Master Dokter Routes
    $routes->get('masterdokter/list', 'MasterDokter::list');
    $routes->get('masterdokter/get-satusehat-kode/(:any)', 'MasterDokter::getSatuSehatKode/$1');

    // Satu Sehat Routes
    $routes->get('satusehat/push-all', 'SatuSehat::pushAll');

    // DICOM Routes
    $routes->get('dicom/(:any)', 'DicomController::getDicomByDate/$1');
    $routes->post('dicom/update', 'DicomUpdateController::updatePatientId');
    $routes->post('dicom/update-by-patient', 'DicomUpdateByPatientController::updateByPatientId');
    $routes->post('dicom/send', 'DicomSendController::sendByPatientId');

    // Dokter Foto Routes
    $routes->post('dokterfoto/upload', 'DokterFoto::create');
    $routes->post('dokterfoto/delete/(:any)', 'DokterFoto::delete/$1');
    $routes->get('dokterfoto/list', 'DokterFoto::index');
    $routes->get('dokterfoto/list-dokter', 'DokterFoto::listDokter');

    // Public (no auth required)
    $routes->post('login', 'Login::index');

    // Protected (require JWT)
    $routes->post('radiologi', 'Radiologi::index', ['filter' => \App\Filters\AuthToken::class]);
    $routes->post('jadwaldokter', 'JadwalDokter::index', ['filter' => \App\Filters\AuthToken::class]);
    //$routes->post('radiologi/insert', 'RadiologiInsert::index', ['filter' => \App\Filters\AuthToken::class]);

    $routes->post('radiologi/list', 'RadiologiOracle::listData', ['filter' => \App\Filters\AuthToken::class]);
    $routes->post('radiologi/search', 'RadiologiOracle::searchByRegno', ['filter' => \App\Filters\AuthToken::class]);
    $routes->post('radiologi/insert', 'RadiologiOracle::insert', ['filter' => \App\Filters\AuthToken::class]);
    $routes->post('radiologi/update', 'RadiologiOracle::update', ['filter' => \App\Filters\AuthToken::class]);
    $routes->post('radiologi/delete', 'RadiologiOracle::delete', ['filter' => \App\Filters\AuthToken::class]);
});
