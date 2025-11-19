<?php

/**
 * AnimatedGif - Classe per la creazione di GIF animate
 * 
 * Questa classe permette di combinare più immagini GIF statiche in una singola GIF animata,
 * gestendo ritardi tra i frame, loop infiniti e trasparenza.
 * 
 * @author  Ivan Preziosi - forked and updated from https://github.com/goors/php-gif-countdown
 * @version 1.0
 */
class AnimatedGif
{
    /**
     * @var string Buffer contenente i dati binari della GIF finale
     */
    private $image = '';

    /**
     * @var array Buffer contenente le immagini GIF sorgente
     */
    private $buffer = [];

    /**
     * @var int Numero di loop dell'animazione (0 = infinito)
     */
    private $number_of_loops = 0;

    /**
     * @var int Metodo di disposizione del frame (2 = ripristina area con colore di sfondo)
     */
    private $DIS = 2;

    /**
     * @var int Colore trasparente codificato in formato RGB (24-bit packed integer)
     */
    private $transparent_colour = -1;

    /**
     * @var bool Flag per tracciare se stiamo processando il primo frame
     */
    private $first_frame = true;

    /**
     * Costruttore della classe AnimatedGif
     * 
     * @param array $source_images Array di stringhe contenenti i dati binari delle GIF sorgente
     * @param array $image_delays Array di interi con il ritardo in centisecondi (1/100s) per ogni frame
     * @param int $number_of_loops Numero di ripetizioni (0 = infinito, -1 = default a 0)
     * @param int $transparent_colour_red Componente rosso del colore trasparente (0-255, -1 = nessuna trasparenza)
     * @param int $transparent_colour_green Componente verde del colore trasparente (0-255, -1 = nessuna trasparenza)
     * @param int $transparent_colour_blue Componente blu del colore trasparente (0-255, -1 = nessuna trasparenza)
     * 
     * @throws Exception Se una delle immagini sorgente non è una GIF valida o è già animata
     */
    public function __construct(
        array $source_images, 
        array $image_delays, 
        $number_of_loops, 
        $transparent_colour_red = -1, 
        $transparent_colour_green = -1, 
        $transparent_colour_blue = -1
    ) {
        // Valida e imposta il numero di loop
        $this->number_of_loops = ($number_of_loops > -1) ? $number_of_loops : 0;
        
        // Imposta il colore trasparente se specificato
        $this->set_transparent_colour($transparent_colour_red, $transparent_colour_green, $transparent_colour_blue);
        
        // Carica e valida le immagini sorgente
        $this->buffer_images($source_images);

        // Costruisce la GIF animata
        $this->addHeader();
        
        $frame_count = count($this->buffer);
        for ($i = 0; $i < $frame_count; $i++) {
            $this->addFrame($i, $image_delays[$i]);
        }
    }

    /**
     * Imposta il colore trasparente per l'animazione
     * 
     * Codifica i valori RGB in un singolo intero a 24-bit per un confronto efficiente.
     * Formato: 0xRRGGBB (rosso nei bit più significativi)
     * 
     * @param int $red Componente rosso (0-255, -1 per disabilitare)
     * @param int $green Componente verde (0-255, -1 per disabilitare)
     * @param int $blue Componente blu (0-255, -1 per disabilitare)
     */
    private function set_transparent_colour($red, $green, $blue)
    {
        // Se tutti i componenti sono validi (>= 0), codifica in formato packed
        if ($red > -1 && $green > -1 && $blue > -1) {
            // Bit shifting: R | (G << 8) | (B << 16) = 0xBBGGRR
            $this->transparent_colour = $red | ($green << 8) | ($blue << 16);
        } else {
            // -1 indica nessuna trasparenza
            $this->transparent_colour = -1;
        }
    }

    /**
     * Carica e valida le immagini GIF sorgente
     * 
     * Verifica che ogni immagine sia:
     * 1. Una GIF valida (header GIF87a o GIF89a)
     * 2. Non già animata (controlla l'estensione NETSCAPE)
     * 
     * @param array $source_images Array di dati binari delle GIF
     * @throws Exception Se un'immagine non è valida o è già animata
     */
    private function buffer_images($source_images)
    {
        $image_count = count($source_images);
        
        for ($i = 0; $i < $image_count; $i++) {
            $this->buffer[] = $source_images[$i];
            
            // Verifica il magic number GIF (primi 6 byte)
            $header = substr($this->buffer[$i], 0, 6);
            if ($header != "GIF87a" && $header != "GIF89a") {
                throw new Exception('L\'immagine alla posizione ' . $i . ' non è una GIF valida');
            }

            // Calcola l'offset della color table globale
            // Byte 10: packed field contenente flag e dimensione color table
            // Formula: 13 (header + logical screen descriptor) + 3 * 2^(size+1) byte di color table
            $color_table_size = 2 << (ord($this->buffer[$i][10]) & 0x07);
            $offset = 13 + (3 * $color_table_size);
            
            // Scansiona i blocchi di dati per verificare se è già animata
            $continue_scan = true;
            for ($j = $offset; $continue_scan && $j < strlen($this->buffer[$i]); $j++) {
                switch ($this->buffer[$i][$j]) {
                    case "!": // Extension block
                        // Verifica se c'è l'estensione NETSCAPE (indica GIF animata)
                        if (substr($this->buffer[$i], ($j + 3), 8) == "NETSCAPE") {
                            throw new Exception('Non è possibile creare un\'animazione da una GIF già animata (posizione ' . $i . ')');
                        }
                        break;
                    case ";": // Trailer (fine del file GIF)
                        $continue_scan = false;
                        break;
                }
            }
        }
    }

    /**
     * Aggiunge l'header della GIF animata
     * 
     * Struttura dell'header:
     * 1. Signature (6 byte): "GIF89a"
     * 2. Logical Screen Descriptor (7 byte): dimensioni, color resolution, etc.
     * 3. Global Color Table (se presente nella prima immagine)
     * 4. Application Extension per il loop (se number_of_loops > 0)
     */
    private function addHeader()
    {
        // Signature GIF89a (necessaria per le animazioni)
        $this->image = 'GIF89a';

        // Verifica se la prima immagine ha una Global Color Table (bit più significativo del byte 10)
        if (ord($this->buffer[0][10]) & 0x80) {
            // Calcola la dimensione della color table
            $color_map_size = 3 * (2 << (ord($this->buffer[0][10]) & 0x07));
            
            // Copia Logical Screen Descriptor (7 byte: width, height, packed fields, bg color, aspect ratio)
            $this->image .= substr($this->buffer[0], 6, 7);
            
            // Copia Global Color Table
            $this->image .= substr($this->buffer[0], 13, $color_map_size);
            
            // Aggiunge Application Extension per il looping (estensione NETSCAPE2.0)
            if ($this->number_of_loops > 0) {
                $this->image .= "!\377\13NETSCAPE2.0\3\1" . 
                               $this->word($this->number_of_loops) . 
                               "\0";
            }
        }
    }

    /**
     * Aggiunge un singolo frame all'animazione
     * 
     * Processa ogni frame aggiungendo:
     * 1. Graphic Control Extension (ritardo, trasparenza, disposal method)
     * 2. Image Descriptor
     * 3. Local Color Table (se diversa dalla globale)
     * 4. Dati dell'immagine compressi
     * 
     * @param int $frame Indice del frame nel buffer
     * @param int $delay Ritardo in centisecondi (1/100s) prima del prossimo frame
     */
    private function addFrame($frame, $delay)
    {
        // Calcola l'offset dove iniziano i dati dell'immagine (dopo header e color table)
        $locals_str_offset = 13 + 3 * (2 << (ord($this->buffer[$frame][10]) & 0x07));
        $locals_end_length = strlen($this->buffer[$frame]) - $locals_str_offset - 1;
        $locals_tmp = substr($this->buffer[$frame], $locals_str_offset, $locals_end_length);

        // Ottieni informazioni sulle color table
        $global_len = 2 << (ord($this->buffer[0][10]) & 0x07);
        $locals_len = 2 << (ord($this->buffer[$frame][10]) & 0x07);
        $global_rgb = substr($this->buffer[0], 13, 3 * $global_len);
        $locals_rgb = substr($this->buffer[$frame], 13, 3 * $locals_len);

        // Crea Graphic Control Extension
        // Struttura: Extension Introducer (!) + Label (F9) + Block Size (04) + Packed Field + Delay + Transparent Index + Block Terminator
        $locals_ext = "!\xF9\x04" . 
                     chr(($this->DIS << 2)) .  // Disposal method (bits 2-4)
                     chr(($delay >> 0) & 0xFF) . // Delay low byte
                     chr(($delay >> 8) & 0xFF) . // Delay high byte
                     "\x0\x0"; // Transparent color index (0) + Block Terminator

        // Gestione della trasparenza
        if ($this->transparent_colour > -1 && (ord($this->buffer[$frame][10]) & 0x80)) {
            // Cerca il colore trasparente nella Local Color Table
            for ($j = 0; $j < $locals_len; $j++) {
                $r = ord($locals_rgb[3 * $j + 0]);
                $g = ord($locals_rgb[3 * $j + 1]);
                $b = ord($locals_rgb[3 * $j + 2]);
                
                // Confronta con il colore trasparente (decodifica da formato packed)
                if ($r == (($this->transparent_colour >> 16) & 0xFF) &&
                    $g == (($this->transparent_colour >> 8) & 0xFF) &&
                    $b == (($this->transparent_colour >> 0) & 0xFF)) {
                    
                    // Imposta il flag di trasparenza (bit 0) e l'indice del colore trasparente
                    $locals_ext = "!\xF9\x04" . 
                                 chr(($this->DIS << 2) + 1) . // Disposal + transparency flag
                                 chr(($delay >> 0) & 0xFF) . 
                                 chr(($delay >> 8) & 0xFF) . 
                                 chr($j) . // Indice del colore trasparente
                                 "\x0";
                    break;
                }
            }
        }

        // Estrae Image Descriptor e dati immagine
        $locals_img = "";
        switch ($locals_tmp[0]) {
            case "!": // Extension block prima dell'immagine
                $locals_img = substr($locals_tmp, 8, 10);
                $locals_tmp = substr($locals_tmp, 18);
                break;
            case ",": // Image Descriptor diretto
                $locals_img = substr($locals_tmp, 0, 10);
                $locals_tmp = substr($locals_tmp, 10);
                break;
        }

        // Gestione della Local Color Table
        if ((ord($this->buffer[$frame][10]) & 0x80) && !$this->first_frame) {
            // Se le color table sono identiche, usa quella globale
            if ($global_len == $locals_len && 
                $this->blockCompare($global_rgb, $locals_rgb, $global_len)) {
                $this->image .= ($locals_ext . $locals_img . $locals_tmp);
            } else {
                // Color table diversa: imposta il flag Local Color Table nell'Image Descriptor
                $byte = ord($locals_img[9]); // Packed field dell'Image Descriptor
                $byte |= 0x80;  // Imposta Local Color Table flag
                $byte &= 0xF8;  // Pulisci i bit di dimensione
                $byte |= (ord($this->buffer[$frame][10]) & 0x07); // Copia dimensione
                $locals_img[9] = chr($byte);
                
                // Aggiungi con Local Color Table
                $this->image .= ($locals_ext . $locals_img . $locals_rgb . $locals_tmp);
            }
        } else {
            // Primo frame o nessuna Local Color Table
            $this->image .= ($locals_ext . $locals_img . $locals_tmp);
        }

        $this->first_frame = false;
    }

    /**
     * Aggiunge il trailer della GIF (marker di fine file)
     */
    private function addFooter()
    {
        $this->image .= ";"; // GIF Trailer (0x3B)
    }

    /**
     * Confronta due color table byte per byte
     * 
     * @param string $global_block Dati della Global Color Table
     * @param string $local_block Dati della Local Color Table
     * @param int $len Numero di colori da confrontare
     * @return bool True se le color table sono identiche
     */
    private function blockCompare($global_block, $local_block, $len)
    {
        // Ogni colore è 3 byte (RGB)
        for ($i = 0; $i < $len; $i++) {
            if ($global_block[3 * $i + 0] != $local_block[3 * $i + 0] ||
                $global_block[3 * $i + 1] != $local_block[3 * $i + 1] ||
                $global_block[3 * $i + 2] != $local_block[3 * $i + 2]) {
                return false;
            }
        }
        return true;
    }

    /**
     * Converte un intero in formato little-endian a 2 byte
     * 
     * Utilizzato per codificare valori come ritardi e numero di loop
     * nel formato richiesto dalla specifica GIF
     * 
     * @param int $int Valore intero (0-65535)
     * @return string 2 byte in formato little-endian
     */
    private function word($int)
    {
        return chr($int & 0xFF) . chr(($int >> 8) & 0xFF);
    }

    /**
     * Restituisce i dati binari della GIF animata
     * 
     * @return string Dati binari della GIF completa
     */
    public function getAnimation()
    {
        return $this->image;
    }

    /**
     * Invia la GIF animata direttamente al browser
     * 
     * Imposta gli header HTTP appropriati e invia i dati binari
     */
    public function display()
    {
        $this->addFooter();
        header('Content-Type: image/gif');
        echo $this->image;
    }
}
