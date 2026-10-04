<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');
require_once '../../bootstrap.php';

use NFePHP\DA\NFSe\Danfse;

$xml = file_get_contents(__DIR__ . '/fixtures/nfse-v2.xml');

try {
    $danfse = new Danfse($xml);

    // Métodos opcionais:
    // $danfse->setPrintCanhoto(false);
    // $danfse->setPrintCanhotoCutLine(true);
    // $danfse->setPrintBackgrounds(false); // fora do padrão visual da NT 008
    // $danfse->setAsCanceled();
    // $danfse->setAsSubstituted();

    $pdf = $danfse->render();
    header('Content-Type: application/pdf');
    echo $pdf;
} catch (\Exception $e) {
    echo $e->getMessage();
}
