<?php
/**
 * Name: Handle Pixel
 * Author: Shish <webmaster@shishnet.org>
 * Link: http://code.shishnet.org/shimmie2/
 * Description: Handle JPEG, PNG, GIF, etc files
 */

class PixelFileHandler extends DataHandlerExtension
{
    protected function supported_ext(string $ext): bool
    {
        $exts = ["jpg", "jpeg", "gif", "png"];
        $ext = (($pos = strpos($ext, '?')) !== false) ? substr($ext, 0, $pos) : $ext;
        return in_array(strtolower($ext), $exts);
    }

    protected function create_image_from_data(string $filename, array $metadata)
    {
        $image = new Image();

        $info = getimagesize($filename);
        if (!$info) {
            return null;
        }

        $image->width = $info[0];
        $image->height = $info[1];

        $image->filesize  = $metadata['size'];
        $image->hash      = $metadata['hash'];
        $image->filename  = (($pos = strpos($metadata['filename'], '?')) !== false) ? substr($metadata['filename'], 0, $pos) : $metadata['filename'];
        $image->ext       = (($pos = strpos($metadata['extension'], '?')) !== false) ? substr($metadata['extension'], 0, $pos) : $metadata['extension'];
        $image->tag_array = is_array($metadata['tags']) ? $metadata['tags'] : Tag::explode($metadata['tags']);
        $image->source    = $metadata['source'];

        return $image;
    }

    protected function check_contents(string $tmpname): bool
    {
        $valid = [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_JPEG];
        if (!file_exists($tmpname)) {
            return false;
        }
        $info = getimagesize($tmpname);
        if (is_null($info)) {
            return false;
        }
        if (in_array($info[2], $valid)) {
            return true;
        }
        return false;
    }

    protected function create_thumb(string $hash): bool
    {
        $outname = warehouse_path("thumbs", $hash);
        if (file_exists($outname)) {
            return true;
        }
        return $this->create_thumb_force($hash);
    }

    protected function create_thumb_force(string $hash): bool
    {
        global $config;

        $inname  = warehouse_path("images", $hash);
        $outname = warehouse_path("thumbs", $hash);

        $ok = false;

        switch ($config->get_string("thumb_engine")) {
            default:
            case 'gd':
                $ok = $this->make_thumb_gd($inname, $outname);
                break;
            case 'convert':
                $ok = $this->make_thumb_convert($inname, $outname);
                break;
        }

        return $ok;
    }

    public function onImageAdminBlockBuilding(ImageAdminBlockBuildingEvent $event)
    {
        $event->add_part("
			<form>
				<select class='shm-zoomer'>
					<option value='full'>Full Size</option>
					<option value='width'>Fit Width</option>
					<option value='height'>Fit Height</option>
					<option value='both'>Fit Both</option>
				</select>
			</form>
		", 20);
    }

    // IM thumber {{{
    private function make_thumb_convert(string $inname, string $outname): bool
    {
        global $config;

        $q = $config->get_int("thumb_quality");
        $convert = $config->get_string("thumb_convert_path");

        //  ffff imagemagick fails sometimes, not sure why
        //$format = "'%s' '%s[0]' -format '%%[fx:w] %%[fx:h]' info:";
        //$cmd = sprintf($format, $convert, $inname);
        //$size = shell_exec($cmd);
        //$size = explode(" ", trim($size));
        $size = getimagesize($inname);
        $tsize = get_thumbnail_size_scaled($size[0] , $size[1]);
        $w = $tsize[0];
        $h = $tsize[1];


        // running the call with cmd.exe requires quoting for our paths
        $format = '"%s" "%s[0]" -extent %ux%u -flatten -strip -thumbnail %ux%u -quality %u jpg:"%s"';
        $cmd = sprintf($format, $convert, $inname, $size[0], $size[1], $w, $h, $q, $outname);
        $cmd = str_replace("\"convert\"", "convert", $cmd); // quotes are only needed if the path to convert contains a space; some other times, quotes break things, see github bug #27
        exec($cmd, $output, $ret);

        log_debug('handle_pixel', "Generating thumbnail with command `$cmd`, returns $ret");

        if ($config->get_bool("thumb_optim", false)) {
            exec("jpegoptim $outname", $output, $ret);
        }

        return true;
    }
    // }}}
    // GD thumber {{{
    private function make_thumb_gd(string $inname, string $outname): bool
    {
        global $config;
        $thumb = $this->get_thumb($inname);
        $ok = imagejpeg($thumb, $outname, $config->get_int('thumb_quality'));
        imagedestroy($thumb);
        return $ok;
    }

    private function get_thumb(string $tmpname)
    {
        global $config;

        $info = getimagesize($tmpname);
        $width = $info[0];
        $height = $info[1];

        $memory_use = (filesize($tmpname)*2) + ($width*$height*4) + (4*1024*1024);
        $memory_limit = get_memory_limit();

        if ($memory_use > $memory_limit) {
        	$tsize = get_thumbnail_size_scaled($width, $height);
			$w = $tsize[0];
			$h = $tsize[1];
			$thumb = imagecreatetruecolor($w, min($h, 64));
            $white = imagecolorallocate($thumb, 255, 255, 255);
            $black = imagecolorallocate($thumb, 0, 0, 0);
            imagefill($thumb, 0, 0, $white);
            imagestring($thumb, 5, 10, 24, "Image Too Large :(", $black);
            return $thumb;
        } else {
            $image = imagecreatefromstring(file_get_contents($tmpname));
            $tsize = get_thumbnail_size_scaled($width, $height);

            $thumb = imagecreatetruecolor($tsize[0], $tsize[1]);
            imagecopyresampled(
                $thumb,
                $image,
                0,
                0,
                0,
                0,
                $tsize[0],
                $tsize[1],
                $width,
                $height
                    );
            return $thumb;
        }
    }
    // }}}
}
