<?php

/**
 * AnimatedGif - Class for generating animated GIF files
 *
 * This class takes multiple static GIF images (provided as raw binary strings)
 * and combines them into a single animated GIF. It handles frame delays,
 * loop counts, disposal methods, transparency, global/local color tables,
 * and correct GIF89a formatting.
 *
 * The internal process includes validating each source GIF, extracting its
 * logical screen parameters, ensuring it is not already an animated GIF,
 * then assembling a final animation by adding a proper GIF89a header,
 * application extensions (for looping), graphic control blocks for each
 * frame, image descriptors, color tables, compressed image data, and
 * finally a closing trailer.
 *
 * This implementation is meant for full control over the GIF creation
 * pipeline, useful when generating animations dynamically, when precise
 * handling of palette, transparency or timing is required, or for systems
 * with tight control over binary output.
 *
 * @author  Ivan Preziosi - forked and updated from https://github.com/goors/php-gif-countdown
 * @version 2.3
 */
class AnimatedGif
{
    /**
     * @var string Binary buffer holding the final GIF animation.
     *
     * As frames are processed, this variable accumulates all the binary
     * sections required by the GIF89a format, eventually forming a
     * complete, playable GIF file.
     */
    private $image = '';

    /**
     * @var array Stores all validated source GIF images.
     *
     * Each element contains the raw binary data of an input image. These
     * GIFs must be valid static GIF files. Animated GIFs are rejected to
     * avoid nested animations or conflicts with internal control blocks.
     */
    private $buffer = [];

    /**
     * @var int Number of animation loops. 0 means infinite looping.
     *
     * In GIF terminology, a loop count is encoded inside a NETSCAPE2.0
     * application extension block that signals viewers to repeat the
     * animation the specified number of times.
     */
    private $number_of_loops = 0;

    /**
     * @var int Frame disposal method.
     *
     * The default (2) restores the background color for the area occupied
     * by the previous frame. This helps prevent unwanted artifacts when
     * frame durations, sizes, or palettes differ.
     */
    private $DIS = 2;

    /**
     * @var int Packed 24-bit RGB value for transparency (-1 = disabled).
     *
     * Transparency is supported only for colors present in a frame's local
     * or global color table. If enabled, the class searches for a matching
     * color entry in the palette to encode its index in the Graphic
     * Control Extension.
     */
    private $transparent_colour = -1;

    /**
     * @var bool Tracks whether the class is processing the very first frame.
     *
     * Useful to decide palette handling: the first frame often supplies the
     * global color table, while subsequent frames may have local tables
     * that need to be compared or merged.
     */
    private $first_frame = true;

    /**
     * Constructor
     *
     * Loads and validates all source GIFs, applies configuration (delays,
     * loop count, optional transparency), builds the GIF header, and
     * sequentially assembles each frame.
     *
     * @param array $source_images Raw GIF data strings.
     * @param array $image_delays Delay (in 1/100s) for each frame.
     * @param int   $number_of_loops Number of repetitions (0 = infinite,
     *                                -1 = interpreted as 0).
     * @param int   $transparent_colour_red   Red channel for transparency.
     * @param int   $transparent_colour_green Green channel.
     * @param int   $transparent_colour_blue  Blue channel.
     *
     * @throws Exception If a source image is invalid or already animated.
     */
    public function __construct(
        array $source_images,
        array $image_delays,
        $number_of_loops,
        $transparent_colour_red = -1,
        $transparent_colour_green = -1,
        $transparent_colour_blue = -1
    ) {
        $this->number_of_loops = ($number_of_loops > -1) ? $number_of_loops : 0;
        $this->set_transparent_colour($transparent_colour_red, $transparent_colour_green, $transparent_colour_blue);
        $this->buffer_images($source_images);

        $this->addHeader();

        $frame_count = count($this->buffer);
        for ($i = 0; $i < $frame_count; $i++) {
            $this->addFrame($i, $image_delays[$i]);
        }
    }

    /**
     * Sets the transparent color (if requested).
     *
     * Packs the RGB components into a single 24-bit integer for easy
     * comparison with palette entries. If any component is negative,
     * transparency is disabled.
     */
    private function set_transparent_colour($red, $green, $blue)
    {
        if ($red > -1 && $green > -1 && $blue > -1) {
            $this->transparent_colour = $red | ($green << 8) | ($blue << 16);
        } else {
            $this->transparent_colour = -1;
        }
    }

    /**
     * Loads and validates each source GIF.
     *
     * Ensures:
     * 1. The header is a valid GIF87a or GIF89a signature.
     * 2. The image is not animated (searches for a NETSCAPE loop extension).
     *
     * Rejecting animated GIFs prevents conflicting control blocks.
     *
     * @throws Exception If validation fails.
     */
    private function buffer_images($source_images)
    {
        $image_count = count($source_images);

        for ($i = 0; $i < $image_count; $i++) {
            $this->buffer[] = $source_images[$i];

            $header = substr($this->buffer[$i], 0, 6);
            if ($header != "GIF87a" && $header != "GIF89a") {
                throw new Exception('Source image at index ' . $i . ' is not a valid GIF');
            }

            $color_table_size = 2 << (ord($this->buffer[$i][10]) & 0x07);
            $offset = 13 + (3 * $color_table_size);

            $continue_scan = true;
            for ($j = $offset; $continue_scan && $j < strlen($this->buffer[$i]); $j++) {
                switch ($this->buffer[$i][$j]) {
                    case "!":
                        if (substr($this->buffer[$i], ($j + 3), 8) == "NETSCAPE") {
                            throw new Exception('Cannot use animated GIF as input (index ' . $i . ')');
                        }
                        break;
                    case ";":
                        $continue_scan = false;
                        break;
                }
            }
        }
    }

    /**
     * Builds the global header of the animated GIF.
     *
     * Includes:
     * 1. GIF89a signature.
     * 2. Logical Screen Descriptor copied from the first source image.
     * 3. Global Color Table (if present).
     * 4. Optional NETSCAPE2.0 loop extension.
     */
    private function addHeader()
    {
        $this->image = 'GIF89a';

        if (ord($this->buffer[0][10]) & 0x80) {
            $color_map_size = 3 * (2 << (ord($this->buffer[0][10]) & 0x07));
            $this->image .= substr($this->buffer[0], 6, 7);
            $this->image .= substr($this->buffer[0], 13, $color_map_size);

            if ($this->number_of_loops > 0) {
                $this->image .= "!\377\13NETSCAPE2.0\3\1" .
                               $this->word($this->number_of_loops) .
                               "\0";
            }
        }
    }

    /**
     * Adds a frame to the animation.
     *
     * Each frame requires:
     * - A Graphic Control Extension (for delay, disposal, transparency).
     * - An Image Descriptor (position, size, palette flags).
     * - Optional Local Color Table.
     * - LZW-compressed image data.
     *
     * The method reconstructs the relevant sections from the source GIF,
     * adapting them to the global animation structure.
     */
    private function addFrame($frame, $delay)
    {
        $locals_str_offset = 13 + 3 * (2 << (ord($this->buffer[$frame][10]) & 0x07));
        $locals_end_length = strlen($this->buffer[$frame]) - $locals_str_offset - 1;
        $locals_tmp = substr($this->buffer[$frame], $locals_str_offset, $locals_end_length);

        $global_len = 2 << (ord($this->buffer[0][10]) & 0x07);
        $locals_len = 2 << (ord($this->buffer[$frame][10]) & 0x07);
        $global_rgb = substr($this->buffer[0], 13, 3 * $global_len);
        $locals_rgb = substr($this->buffer[$frame], 13, 3 * $locals_len);

        $locals_ext = "!\xF9\x04" .
                      chr(($this->DIS << 2)) .
                      chr(($delay >> 0) & 0xFF) .
                      chr(($delay >> 8) & 0xFF) .
                      "\x0\x0";

        if ($this->transparent_colour > -1 && (ord($this->buffer[$frame][10]) & 0x80)) {
            for ($j = 0; $j < $locals_len; $j++) {
                $r = ord($locals_rgb[3 * $j + 0]);
                $g = ord($locals_rgb[3 * $j + 1]);
                $b = ord($locals_rgb[3 * $j + 2]);

                if ($r == (($this->transparent_colour >> 16) & 0xFF) &&
                    $g == (($this->transparent_colour >> 8) & 0xFF) &&
                    $b == (($this->transparent_colour >> 0) & 0xFF)) {

                    $locals_ext = "!\xF9\x04" .
                                  chr(($this->DIS << 2) + 1) .
                                  chr(($delay >> 0) & 0xFF) .
                                  chr(($delay >> 8) & 0xFF) .
                                  chr($j) .
                                  "\x0";
                    break;
                }
            }
        }

        $locals_img = "";
        switch ($locals_tmp[0]) {
            case "!":
                $locals_img = substr($locals_tmp, 8, 10);
                $locals_tmp = substr($locals_tmp, 18);
                break;
            case ",":
                $locals_img = substr($locals_tmp, 0, 10);
                $locals_tmp = substr($locals_tmp, 10);
                break;
        }

        if ((ord($this->buffer[$frame][10]) & 0x80) && !$this->first_frame) {
            if ($global_len == $locals_len &&
                $this->blockCompare($global_rgb, $locals_rgb, $global_len)) {
                $this->image .= ($locals_ext . $locals_img . $locals_tmp);
            } else {
                $byte = ord($locals_img[9]);
                $byte |= 0x80;
                $byte &= 0xF8;
                $byte |= (ord($this->buffer[$frame][10]) & 0x07);
                $locals_img[9] = chr($byte);

                $this->image .= ($locals_ext . $locals_img . $locals_rgb . $locals_tmp);
            }
        } else {
            $this->image .= ($locals_ext . $locals_img . $locals_tmp);
        }

        $this->first_frame = false;
    }

    /**
     * Appends the GIF trailer byte.
     *
     * This marks the end of the animated GIF.
     */
    private function addFooter()
    {
        $this->image .= ";";
    }

    /**
     * Compares two color tables byte-by-byte.
     *
     * Used to determine whether a frame's local palette matches the global
     * palette. If identical, the local one can be omitted to reduce GIF size.
     */
    private function blockCompare($global_block, $local_block, $len)
    {
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
     * Encodes an integer into a 2-byte little-endian binary string.
     *
     * Required by the GIF format for storing values such as delay and loop
     * count inside extensions.
     */
    private function word($int)
    {
        return chr($int & 0xFF) . chr(($int >> 8) & 0xFF);
    }

    /**
     * Returns the complete binary animated GIF.
     */
    public function getAnimation()
    {
        return $this->image;
    }

    /**
     * Outputs the GIF directly to the browser with appropriate headers.
     */
    public function display()
    {
        $this->addFooter();
        header('Content-Type: image/gif');
        echo $this->image;
    }
}
