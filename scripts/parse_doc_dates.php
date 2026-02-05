<?php
require_once '../config/db.php';
require_once '../vendor/autoload.php';

use Smalot\PdfParser\Parser;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

if (!isset($_FILES['doc_archivo']) || $_FILES['doc_archivo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Error al subir el archivo']);
    exit;
}

// Get Entity Info
$entityType = isset($_POST['entity_type']) ? $_POST['entity_type'] : 'worker'; // worker | vehicle

// Worker Data
$workerRut = isset($_POST['worker_rut']) ? $_POST['worker_rut'] : '';
$workerName = isset($_POST['worker_name']) ? $_POST['worker_name'] : '';

// Vehicle Data
$vehiclePatente = isset($_POST['vehicle_patente']) ? $_POST['vehicle_patente'] : '';


$tmpPath = $_FILES['doc_archivo']['tmp_name'];
$fileType = mime_content_type($tmpPath);
$text = '';

try {
    if ($fileType === 'application/pdf') {
        $parser = new Parser();
        $pdf = $parser->parseFile($tmpPath);
        $text = $pdf->getText();
    } elseif (strpos($fileType, 'image/') === 0) {
        // Tesseract Logic placeholder
        echo json_encode(['success' => false, 'message' => 'Soporte OCR para imágenes requiere configuración adicional. Por favor use PDF.']);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Formato no soportado. Use PDF.']);
        exit;
    }

    $cleanText = preg_replace('/\s+/', ' ', $text);
    
    // --- 1. Date Extraction (Common Logic) ---
    $dates = [];
    $datePattern = '/(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})/';
    $keywords = ['vencimiento', 'vence', 'valido hasta', 'vigencia', 'fecha termino', 'caducidad', 'proxima revision'];
    $foundDate = null;
    $foundContext = '';

    preg_match_all($datePattern, $cleanText, $matches, PREG_OFFSET_CAPTURE);
    
    if (!empty($matches[0])) {
        $bestScore = -1;
        foreach ($matches[0] as $match) {
            $dateStr = $match[0];
            $offset = $match[1];
            $parts = preg_split('/[\/\-\.]/', $dateStr);
            if (count($parts) == 3) {
                $d = (int)$parts[0];
                $m = (int)$parts[1];
                $y = (int)$parts[2];
                if ($y < 100) $y += 2000;
                
                if (checkdate($m, $d, $y)) {
                    $isoDate = sprintf('%04d-%02d-%02d', $y, $m, $d);
                    $score = 0;
                    $context = substr($cleanText, max(0, $offset - 50), 50);
                    foreach ($keywords as $kw) {
                        if (stripos($context, $kw) !== false) {
                            $score += 10;
                            $foundContext = $kw;
                        }
                    }
                    if (strtotime($isoDate) > time()) $score += 5;
                    
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $foundDate = $isoDate;
                    }
                }
            }
        }
    }

    // --- 2. Identity Validation & Extraction ---
    $identityMatch = true; // Default to true if no data provided
    $identityMessage = "";
    $extractedRuts = [];
    $extractedPatentes = [];

    // Extract RUTs
    preg_match_all('/\b(\d{1,2}[\.]?\d{3}[\.]?\d{3}[-][0-9kK])\b/', $text, $rutMatches);
    if (!empty($rutMatches[0])) {
        $extractedRuts = array_unique($rutMatches[0]);
    }

    // Extract Patentes (Simple heuristic: 4 letters 2 numbers or 2 letters 4 numbers)
    // Using cleanText which has stripped extra spaces
    preg_match_all('/\b([A-Z]{4}\d{2}|[A-Z]{2}\d{4})\b/', preg_replace('/[^A-Z0-9]/', '', strtoupper($cleanText)), $patMatches);
     // The above regex on stripped text is risky (merges words). 
     // Better to use regex on original text with boundaries, but OCR is messy.
     // Let's try finding pattern in $cleanText (which preserved spaces)
     
    preg_match_all('/\b([A-Z]{4}[\s\-]??\d{2}|[A-Z]{2}[\s\-]??\d{4})\b/i', $cleanText, $patMatches);
    if (!empty($patMatches[0])) {
        $cleanedPatentes = [];
        foreach($patMatches[0] as $p) {
            $val = strtoupper(preg_replace('/[^A-Z0-9]/', '', $p));
            // Filter out common false positives if any (like dates formatted weirdly, though regex restricts)
            $cleanedPatentes[] = $val;
        }
        $extractedPatentes = array_unique($cleanedPatentes);
    }
    
    if ($entityType === 'worker') {
        if ($workerRut) {
            $rutClean = preg_replace('/[^0-9kK]/', '', $workerRut);
            $rutBody = substr($rutClean, 0, -1);
            
            if (strpos(str_replace('.', '', $cleanText), $rutBody) === false && 
                strpos($cleanText, $workerRut) === false) {
                $identityMatch = false;
                $identityMessage .= "No se encontró el RUT del trabajador en el documento. ";
            }
        }
        
        if ($workerName) {
            $nameParts = explode(' ', strtolower($workerName));
            $nameFound = false;
            foreach ($nameParts as $part) {
                if (strlen($part) > 3 && stripos($cleanText, $part) !== false) {
                    $nameFound = true;
                    break;
                }
            }
            if (!$nameFound && $identityMatch) {
                 // Warning optional
            }
        }
    } elseif ($entityType === 'vehicle') {
        if ($vehiclePatente) {
            $patenteClean = preg_replace('/[^A-Z0-9]/', '', strtoupper($vehiclePatente));
            $textAlphaNum = preg_replace('/[^A-Z0-9]/', '', strtoupper($cleanText));
            
            // Check for patente in text
            if (strpos($textAlphaNum, $patenteClean) === false) {
                 $identityMatch = false;
                 $identityMessage .= "No se encontró la patente $vehiclePatente en el documento. ";
            }
        }
    }

    // --- 3. Document Classification ---
    $docType = '';
    $docCategory = ''; // worker | vehicle
    $docKeywords = [];
    
    $workerKeywords = [
        'Antecedentes' => ['antecedentes', 'registro civil', 'prontuario'],
        'Licencia de Conducir' => ['licencia de conductor', 'licencia de conducir', 'municipalidad', 'clase b', 'clase a'],
        'Hoja de Vida' => ['hoja de vida', 'historial conductor', 'registro nacional de conductores'],
        'Cédula de Identidad' => ['cedula de identidad', 'run', 'republica de chile'],
        'Contrato' => ['contrato de trabajo', 'empleador', 'trabajador'],
        'Finiquito' => ['finiquito', 'termino de relacion'],
        'Examen Ocupacional' => ['examen ocupacional', 'bateria', 'altura fisica', 'evaluacion salud'],
        'Curso/Capacitación' => ['certificado de aprobacion', 'diploma', 'curso', 'capacitacion']
    ];

    $vehicleKeywords = [
        'Permiso de Circulación' => ['permiso de circulacion', 'municipalidad', 'departamento de transito'],
        'Revisión Técnica' => ['revision tecnica', 'prt', 'planta de revision'],
        'Seguro Obligatorio (SOAP)' => ['soap', 'seguro obligatorio', 'poliza'],
        'Padrón / Inscripción' => ['padron', 'inscripcion', 'registro civil', 'certificado de inscripcion'],
        'Certificación MLP' => ['minera los pelambres', 'acreditacion', 'mlp'],
        'Gases' => ['analisis de gases', 'emision de contaminantes']
    ];

    if ($entityType === 'worker') {
        $docKeywords = $workerKeywords;
    } elseif ($entityType === 'vehicle') {
        $docKeywords = $vehicleKeywords;
    } else {
        $docKeywords = array_merge($workerKeywords, $vehicleKeywords);
    }

    foreach ($docKeywords as $type => $kws) {
        foreach ($kws as $kw) {
            if (stripos($cleanText, $kw) !== false) {
                $docType = $type;
                // Determine category based on which array it came from
                if (array_key_exists($type, $workerKeywords)) $docCategory = 'worker';
                elseif (array_key_exists($type, $vehicleKeywords)) $docCategory = 'vehicle';
                break 2;
            }
        }
    }

    echo json_encode([
        'success' => true, 
        'date' => $foundDate,
        'doc_type' => $docType,
        'doc_category' => $docCategory,
        'identity_match' => $identityMatch,
        'identity_message' => $identityMessage,
        'extracted_ruts' => array_values($extractedRuts),
        'extracted_patentes' => array_values($extractedPatentes),
        'message' => "Análisis completado." . ($foundDate ? " Fecha detectada." : "")
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al procesar archivo: ' . $e->getMessage()]);
}
