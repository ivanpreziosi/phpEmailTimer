<?php
/**
 * Script per la generazione di un countdown animato in formato GIF
 * 
 * Crea una GIF animata che mostra un countdown fino a una data/ora specificata.
 * Ogni frame rappresenta un secondo, con un massimo di 60 frame.
 * 
 * Parametri GET richiesti:
 * - time: timestamp o stringa data in formato riconoscibile da strtotime()
 * 
 * @author  Ivan Preziosi - forked and updated from https://github.com/goors/php-gif-countdown
 * @version 2.0
 */

// Carica la classe per la generazione di GIF animate
require_once 'AnimatedGif.php';

// ============================================================================
// CONFIGURAZIONE COUNTDOWN
// ============================================================================

/**
 * Configurazione visuale del countdown
 */
const BASE_IMAGE_PATH = __DIR__ . '/base.png';
const FONT_PATH = __DIR__ . '/font.ttf';
const FONT_SIZE = 60;
const FONT_COLOR_RGB = ["r"=>255,"g"=>255,"b"=>255];
const FONT_X_OFFSET = 60;
const FONT_Y_OFFSET = 95;
const FRAME_DELAY = 100; // Ritardo tra frame in centisecondi (100 = 1 secondo)
const MAX_FRAMES = 60;   // Numero massimo di frame da generare
const TIME_ZONE = 'Europe/Rome';   // Numero massimo di frame da generare

// Imposta il timezone per calcoli di data/ora corretti
date_default_timezone_set(TIME_ZONE);

// ============================================================================
// VALIDAZIONE INPUT
// ============================================================================

/**
 * Recupera e valida i parametri dalla query string
 */
$time = $_GET['time'] ?? null;

// Validazione del parametro time
if (!$time) {
    http_response_code(403);
    die("Errore: Parametro 'time' mancante");
}

// Verifica esistenza file richiesti
if (!file_exists(BASE_IMAGE_PATH)) {
    http_response_code(500);
    die("Errore: Immagine base non trovata");
}

if (!file_exists(FONT_PATH)) {
    http_response_code(500);
    die("Errore: Font non trovato");
}

// ============================================================================
// CALCOLO DATE
// ============================================================================

try {
    // Converte il parametro time in oggetto DateTime
    $future_date = new DateTime(date('r', strtotime($time)));
    $now = new DateTime(date('r', time()));
} catch (Exception $e) {
    http_response_code(400);
    die("Errore: Formato data non valido");
}

// ============================================================================
// GENERAZIONE FRAMES
// ============================================================================

$frames = [];
$delays = [];

/**
 * Genera i frame del countdown
 * Ogni frame rappresenta un secondo, partendo dal momento attuale
 */
for ($i = 0; $i < MAX_FRAMES; $i++) {
    // Calcola l'intervallo di tempo rimanente
    $interval = date_diff($future_date, $now);
    
    // Carica l'immagine base per questo frame
    $image = imagecreatefrompng(BASE_IMAGE_PATH);
    
    if ($image === false) {
        http_response_code(500);
        die("Errore: Impossibile caricare l'immagine base");
    }
    
    // Configura rendering per qualità ottimale
    imagealphablending($image, true);  // Abilita blending per antialiasing
    imagesavealpha($image, true);      // Preserva il canale alpha
    
    // Alloca il colore del testo per questa specifica immagine
    $fontColor = imagecolorallocate($image, FONT_COLOR_RGB["r"],FONT_COLOR_RGB["g"],FONT_COLOR_RGB["b"]);
    
    // ========================================================================
    // FORMATTAZIONE TESTO COUNTDOWN
    // ========================================================================
    
    if ($future_date <= $now) {
        // Countdown terminato: mostra zeri
        $text = '00:00:00:00';
        
        // Renderizza il testo sull'immagine
        imagettftext(
            $image, 
            FONT_SIZE, 
            0,              // Angolo di rotazione
            FONT_X_OFFSET, 
            FONT_Y_OFFSET, 
            $fontColor, 
            FONT_PATH, 
            $text
        );
        
        // Cattura l'output della GIF in memoria
        ob_start();
        imagegif($image);
        $frames[] = ob_get_contents();
        $delays[] = FRAME_DELAY;
        ob_end_clean();
        
        // Libera la memoria dell'immagine
        imagedestroy($image);
        
        // Countdown terminato, esci dal loop
        break;
        
    } else {
        // Countdown in corso: formatta come giorni:ore:minuti:secondi
        $text = $interval->format('%a:%H:%I:%S');
        
        // Aggiunge uno zero iniziale se i giorni sono < 10
        if (preg_match('/^[0-9]\:/', $text)) {
            $text = '0' . $text;
        }
        
        // Renderizza il testo sull'immagine
        imagettftext(
            $image, 
            FONT_SIZE, 
            0, 
            FONT_X_OFFSET, 
            FONT_Y_OFFSET, 
            $fontColor, 
            FONT_PATH, 
            $text
        );
        
        // Cattura l'output della GIF in memoria
        ob_start();
        imagegif($image);
        $frames[] = ob_get_contents();
        $delays[] = FRAME_DELAY;
        ob_end_clean();
        
        // Libera la memoria dell'immagine
        imagedestroy($image);
    }
    
    // Avanza di un secondo per il prossimo frame
    $now->modify('+1 second');
}

// ============================================================================
// HEADER HTTP PER PREVENIRE CACHING
// ============================================================================

/**
 * Imposta header per forzare il browser a non cachare l'immagine
 * Questo garantisce che il countdown sia sempre aggiornato
 */
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// ============================================================================
// GENERAZIONE E OUTPUT GIF ANIMATA
// ============================================================================

try {
    // Crea la GIF animata
    // Parametri: frames, delays, loops (0 = infinito), RGB trasparenza (non usato)
    $gif = new AnimatedGif($frames, $delays, 0);
    
    // Invia la GIF al browser
    $gif->display();
    
} catch (Exception $e) {
    http_response_code(500);
    die("Errore nella generazione della GIF: " . $e->getMessage());
}
