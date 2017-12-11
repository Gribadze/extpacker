<?php

namespace Converter;

use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Stichoza\GoogleTranslate\TranslateClient;

class Converter
{
    protected $basename;
    protected $translator;

    public function __construct($basename)
    {
        $this->basename = $basename;
        $this->translator = new TranslateClient;
    }

    public function translate($text, $target_lang)
    {
        $this->translator->setTarget($target_lang);
        return $this->translator->translate($text);
    }

    public function setProxy($host, $port, $user, $pass)
    {
        $proxy = sprintf('tcp://%s:%s@%s:%s', $host, $port, $user, $pass);
        $this->translator = new TranslateClient('en', 'en',  [
            'proxy' => ['http' => $proxy, 'https' => $proxy]
        ]);
    }

    public function unzipSource()
    {
        $filename = sprintf('resources/sources/%s.zip', $this->basename);

        $target = 'resources/tmp/' . $this->basename;
        if (!is_dir($target)) {
            mkdir($target, 0777, true);
        }
    
        $zip = new ZipArchive;
        if ($zip->open($filename) === true) {
            $zip->extractTo($target);
            $zip->close();
        } else {
            throw new \Exception('Fail to unzip: ' . $filename);
        }
    }

    public function restoreTemplate()
    {
        $source = 'resources/template';
        $target = 'resources/outputs/' . $this->basename;
        if (!is_dir($target)) {
            mkdir($target, 0777, true);
        }

        $sourceIterator = new RecursiveDirectoryIterator(
            $source, RecursiveDirectoryIterator::SKIP_DOTS
        );

        $iterator = new RecursiveIteratorIterator(
            $sourceIterator, RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                mkdir($target . '/' . $iterator->getSubPathName(), 0777);
            } else {
                copy($item, $target . '/' . $iterator->getSubPathName());
            }
        }
    }

    public function copyRenamedImages()
    {
        $source = 'resources/tmp/' . $this->basename . '/images';
        $target = 'resources/outputs/' . $this->basename . '/data/backgrounds';

        $filelist = glob($source . '/*.jpg');
        for ($i = 0; $i < 25; $i++) {
            if (!isset($filelist[$i])) {
                break;
            }

            copy($filelist[$i], $target . '/' . sprintf("bg-%02d.jpg", $i + 1));
        }
    }

    public function convertScreenshots($size)
    {
        $source = 'resources/tmp/' . $this->basename . '/' . $size;
        $target = sprintf(
            'resources/outputs/%s/data/screenshots_%s', $this->basename, $size
        );

        if (!is_dir($target)) {
            mkdir($target, 0777, true);
        }

        $filelist = glob($source . '/*.*');
        foreach ($filelist as $filename) {
            $contents = file_get_contents($filename);

            $im = imagecreatefromstring($contents);
            $mask = imagecreatefrompng(
                'resources/tmp/' . $this->basename . '/png_mask/' . $size . '.png'
            );

            // wtf!?
            imagecopy($im, $mask, 0, 0, 0, 0, imagesx($mask), imagesy($mask));

            $target_fname = $target . '/' . basename($filename);
            switch (exif_imagetype($filename)) {
                case IMAGETYPE_GIF:
                    imagegif($im, $target_fname);
                    break;
                case IMAGETYPE_PNG:
                    imagepng($im, $target_fname);
                    break;
                case IMAGETYPE_JPEG:
                    imagejpeg($im, $target_fname);
                    break;
                default:
                    throw new \Exception('Unsupported image type');
            }

            imagedestroy($im);
            imagedestroy($mask);
        }
    }

    public function convertIcons($formats, $source = '256x256')
    {
        $filelist = glob('resources/tmp/' . $this->basename . '/icons/*256*.*');
        $filename = array_shift($filelist);

        $contents = file_get_contents($filename);
        $im = imagecreatefromstring($contents);

        list($src_w, $src_h) = explode('x', $source, 2);
        foreach ($formats as $format) {
            list($dst_w, $dst_h) = explode('x', $format, 2);

            $icon = imagecreatetruecolor($dst_w, $dst_h);

            imagecolortransparent($icon, imagecolorallocatealpha($icon, 0, 0, 0, 127));
            imagealphablending($icon, false);
            imagesavealpha($icon, true);

            //imagecopyresized($icon, $im, 0, 0, 0, 0, $dst_w, $dst_h, $src_w, $src_h);
            imagecopyresampled($icon, $im, 0, 0, 0, 0, $dst_w, $dst_h, $src_w, $src_h);

            imagepng($icon, sprintf(
                'resources/outputs/%s/data/icons/%s.png', $this->basename, $dst_w
            ));

            imagedestroy($icon);
        }

        // Copy source. Just in case
        file_put_contents(sprintf(
            'resources/outputs/%s/data/icons/%s.png', $this->basename, $src_w
        ), $contents);

        imagedestroy($im);
    }

    public function archiveOutput()
    {
        $rootPath = realpath('resources/outputs/' . $this->basename);
        $tmpfname = tempnam(sys_get_temp_dir(), 'zip');

        $zip = new ZipArchive;
        $zip->open($tmpfname, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);

                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();

        rename($tmpfname, sprintf('resources/outputs/%s.zip', $this->basename));
    }

    public function translateTexts()
    {
        $source = 'resources/tmp/' . $this->basename;

      //$keys = file_get_contents($source . '/keys.txt');
        $desc = file_get_contents($source . '/desc.txt');
        
        // Remove BOM from keys.txt and desc.txt
        $bom = pack('H*','EFBBBF');
      //$keys = preg_replace("/^$bom/", '', $keys);
        $desc = preg_replace("/^$bom/", '', $desc);
        
        // normalize EOLs
        $desc = preg_replace("~(?<!\r)\n~", "\r\n", $desc);  

        // removing whitespaces and new lines outside double-quotes
        $desc = preg_replace('/(?| *(".*?") *| *(\'.*?\') *)| +|\r\n+/s', '$1', $desc);

        // triming lines
        $desc = preg_replace('/^\s+|\s+$/m', '', $desc);

        // replacing new lines to "\r\n"
        $desc = preg_replace('/(?!((["]*^"){2})*["]*$)\n+/', '\r\n', $desc);

        // decoding..
        $desc = json_decode($desc, true);
        
        $data = [
            'name'       => $desc['name']['message'],
            'short_desc' => $desc['short-desc']['message'],
            'func_desc'  => $desc['func-desc']['message'],
          //'keys'       => $keys,
        ];

        $target = 'resources/outputs/' . $this->basename . '/_locales';
        foreach (glob($target . '/*') as $locale) {
            $lang = basename($locale);
            $translations = $this->translateArray($data, $lang);
            if (!isset($translations['name'])) {
                continue;
            }

            $messages = [
                'extDesc' => [
                    'message' => $translations['short_desc']
                ],
                'extName' => [
                    'message' => $translations['name']
                ],
            ];

            // messages.json
            $messages_json = json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents($locale . '/messages.json', $messages_json);

            // description.txt
            $description = $translations['func_desc'] . PHP_EOL;// . $keys;
            file_put_contents($locale . '/description.txt', $description);
        }
    }

    public function translateArray(array $data, $lang)
    {
        $this->translate(array_values($data), $lang);

        $translation = [];
        foreach ($data as $key => $value) {
            $response = $this->translator->getResponse($value);
            if (!isset($response[0]) or !is_array($response[0])) {
                continue;
            }

            $translation[$key] = null;
            foreach ($response[0] as $lines) {
                $translation[$key] .= $lines[0];
            }

            $translation[$key] = trim($translation[$key]);
        }

        return $translation;
    }

    public function cleanup()
    {
        $this->removeDirRecursive('resources/tmp/' . $this->basename);
        $this->removeDirRecursive('resources/outputs/' . $this->basename);
    }

    public function removeDirRecursive($dir)
    {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!$this->removeDirRecursive(
                $dir . DIRECTORY_SEPARATOR . $item
            )) {
                return false;
            }
        }

        return rmdir($dir);
    }

    public function getLocales()
    {
        $contents = file_get_contents('resources/locales.json');

        return json_decode($contents);
    }
}
