<?php
declare(strict_types=1);

require __DIR__ . '/../../src/arranque.php'; 

use Aplicacion\Nucleo\Enrutador; 
use Aplicacion\Nucleo\Peticion; 
use Aplicacion\Seguridad\VerificadorClaveApi; 

$peticion = Peticion::crearDesdeLasVariablesDelServidor(); 

VerificadorClaveApi::verificarClaveApi($peticion); 

$enrutador = new Enrutador();
(require __DIR__ . '/../../src/rutas.php')($enrutador); 
$enrutador->atenderPeticion($peticion); 
