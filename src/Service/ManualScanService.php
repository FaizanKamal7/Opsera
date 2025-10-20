<?php

namespace App\Service;

use App\Enum\DocumentManual\LanguageDocument;
use App\Enum\DocumentManual\SpecialDocument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\KernelInterface;


/**
 * Service for scanning and processing product manuals and safety guidelines.
 *
 * This service scans a directory structure containing product manuals in multiple languages
 * and safety guidelines, then returns structured data about found files and their metadata.
 * It supports processing SKU mapping files to associate multiple product IDs with the same manuals.
 *
 * The scan can be executed via Symfony console command:
 *  `php bin/console app:manuals:scan`
 *
 */
class ManualScanService
{
    private string $projectDir;

    public function __construct(
        #[Autowire('%env(MANUALS_DIRECTORY)%')]
        private string          $manualDir,
        private KernelInterface $kernel,
    )
    {
        $this->projectDir = $kernel->getProjectDir();
    }

    public function scan(): array
    {
        $directoryStructure = $this->scanDirectory($this->manualDir);
        $manuals = [];
        $safetyGuidelines = [];

        foreach ($directoryStructure as $category => $products) {
            foreach ($products as $productId => $productData) {
                $this->processProductDirectories(
                    $productData,
                    $category,
                    $productId,
                    $manuals,
                    $safetyGuidelines
                );
            }
        }

        return [
            'manuals' => $manuals,
            'safety_guidelines' => $safetyGuidelines
        ];
    }

    private function processProductDirectories(
        array  $productData,
        string $category,
        string $productId,
        array  &$manuals,
        array  &$safetyGuidelines
    ): void
    {
        foreach ($productData as $directoryName => $directoryContents) {
            if ($this->isLanguageDirectory($directoryName)) {
                $this->processLanguageDirectory(
                    $directoryContents,
                    $category,
                    $productId,
                    $directoryName,
                    $manuals
                );
            } elseif ($this->isSafetyGuidelinesDirectory($directoryName)) {
                $this->processSafetyGuidelinesDirectory(
                    $directoryContents,
                    $category,
                    $productId,
                    $directoryName,
                    $safetyGuidelines
                );
            }
        }
    }

    private function processLanguageDirectory(
        array  $files,
        string $category,
        string $productId,
        string $language,
        array  &$manuals
    ): void
    {
        foreach ($files as $file) {
            if ($this->isPdfFile($file)) {
                $filePath = $this->buildFilePath($category, $productId, $language, $file);
                $manuals[$productId][$language] = [
                    'file' => $filePath,
                    'date' => date("Y-m-d H:i:s", filemtime($filePath))
                ];
            } elseif ($this->isSkuFile($file)) {
                $this->processSkuFile($category, $productId, $language, $file, $manuals);
            }
        }
    }

    private function processSafetyGuidelinesDirectory(
        array  $languageDirectories,
        string $category,
        string $productId,
        string $guidelineDir, // Added parameter for the guideline directory
        array  &$safetyGuidelines
    ): void
    {
        foreach ($languageDirectories as $language => $files) {
            if (!in_array($language, LanguageDocument::values())) {
                continue;
            }

            foreach ($files as $file) {
                if ($this->isPdfFile($file)) {
                    $filePath = $this->buildFilePath(
                        $category,
                        $productId,
                        $guidelineDir,
                        $file,
                        $language
                    );
                    $safetyGuidelines[$productId][$language] = [
                        'file' => $filePath,
                        'date' => date("Y-m-d H:i:s", filemtime($filePath))
                    ];
                } elseif ($this->isSkuFile($file)) {
                    $this->processSkuFile(
                        $category,
                        $productId,
                        $language,
                        $file,
                        $safetyGuidelines,
                        $guidelineDir
                    );
                }
            }
        }
    }

    private function processSkuFile(
        string  $category,
        string  $productId,
        string  $language,
        string  $file,
        array   &$targetArray,
        ?string $subDirectory = null
    ): void
    {
        $pathParts = array_filter([
            $this->manualDir,
            $category,
            $productId,
            $subDirectory,
            $language,
            $file
        ]);

        $filePath = implode('/', $pathParts);
        $content = explode("\n", file_get_contents($filePath));

        foreach ($content as $line) {
            $cleanLine = trim(str_replace("\r", "", $line));
            if ($cleanLine !== '' && $cleanLine !== $productId && isset($targetArray[$productId][$language])) {
                $targetArray[$cleanLine][$language] = $targetArray[$productId][$language];
            }
        }
    }

    private function scanDirectory(string $dir): array
    {
        $result = [];
        $contents = scandir($dir);

        foreach ($contents as $item) {
            if ($this->shouldSkipDirectory($item)) {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            $result[$item] = is_dir($path) ? $this->scanDirectory($path) : $item;
        }

        return $result;
    }

    private function buildFilePath(
        string  $category,
        string  $productId,
        string  $directory,
        string  $file,
        ?string $subDirectory = null
    ): string
    {
        $pathParts = [
            $this->manualDir,
            $category,
            $productId,
            $directory,
            $subDirectory,
            $file
        ];

        return implode('/', array_filter($pathParts));
    }

    private function isLanguageDirectory(string $name): bool
    {
        return in_array($name, LanguageDocument::values());
    }

    private function isSafetyGuidelinesDirectory(string $name): bool
    {
        return in_array($name, SpecialDocument::values());
    }

    private function isPdfFile(string $filename): bool
    {
        return !is_array($filename) && str_ends_with($filename, '.pdf');
    }

    private function isSkuFile(string $filename): bool
    {
        return !is_array($filename) && strtolower($filename) === 'sku.txt';
    }

    private function shouldSkipDirectory(string $name): bool
    {
        return in_array($name, [".", "..", ".svn", "Set"]);
    }

}


// ---- O R I G I N A L   S C R I P T
//
//
//function dirToArray($dir) {
//
//    $result = array();
//
//    $cdir = scandir($dir);
//    foreach ($cdir as $key => $value)
//    {
//        if (!in_array($value,array(".","..",".svn","Set")))
//        {
//            if (is_dir($dir . DIRECTORY_SEPARATOR . $value))
//            {
//                $result[$value] = dirToArray($dir . DIRECTORY_SEPARATOR . $value);
//            }
//            else
//            {
//                $result[] = $value;
//            }
//        }
//    }
//
//    return $result;
//}
////echo "<pre>";
////$root = "AnleitungenSVN";
//chdir('/var/www/service');
//$root = "manuals";
//$dir = dirToArray($root);
//$res = [];
//$resSafetyGuidelines = [];
////check all folders for pdf files
//foreach ($dir as $key => $sub) {
//    foreach ($sub as $key2 => $sub2) {
//        //Artikelebene
//        foreach ($sub2 as $key3 => $sub3) {
//            if(is_array($sub3) && in_array($key3,['DE','EN','FR','IT','ES','NL','SE','CE'])) {
//                foreach ($sub3 as $file) {
//                    if (!is_array($file) && strstr($file, '.pdf') !== false) {
//                        $res[$key2][$key3]['file'] = $root."/".$key."/".$key2."/".$key3."/".$file;
//                        $res[$key2][$key3]['date'] = date("Y-m-d H:i:s", filemtime($res[$key2][$key3]['file']));
//                    }
//                    //check all sku.txt files
//                    if (!is_array($file) && (strtolower($file) == 'sku.txt')) {
//                        $content = explode("\n",file_get_contents($root."/".$key."/".$key2."/".$key3."/".$file));
//                        foreach ($content as $line) {
//                            $l = str_replace("\r","",$line);
//                            if($l!=$key2 && trim($l)!="" && isset($res[$key2][$key3])) {
//                                $res[$l][$key3] = $res[$key2][$key3];
//                            }
//                        }
//                    }
//                }
//            } elseif(is_array($sub3) && in_array($key3,['SHB'])) {
//                foreach ($sub3 as $key4 => $sub4) {
//                    if(is_array($sub4) && in_array($key4,['DE','EN','FR','IT','ES','NL','SE'])) {
//                        foreach ($sub4 as $file) {
//                            if (!is_array($file) && strstr($file, '.pdf') !== false) {
//                                $resSafetyGuidelines[$key2][$key4]['file'] = $root."/".$key."/".$key2."/".$key3."/".$key4."/".$file;
//                                $resSafetyGuidelines[$key2][$key4]['date'] = date("Y-m-d H:i:s", filemtime($resSafetyGuidelines[$key2][$key4]['file']));
//                            }
//                            //check all sku.txt files
//                            if (!is_array($file) && (strtolower($file) == 'sku.txt')) {
//                                $content = explode("\n",file_get_contents($root."/".$key."/".$key2."/".$key3."/".$key4."/"."/".$file));
//                                foreach ($content as $line) {
//                                    $l = str_replace("\r","",$line);
//                                    if($l!=$key2 && trim($l)!="" && isset($resSafetyGuidelines[$key2][$key4])) {
//                                        $resSafetyGuidelines[$l][$key4] = $resSafetyGuidelines[$key2][$key4];
//                                    }
//                                }
//                            }
//                        }
//                    }
//                }
//            }
//        }
//    }
//}
//file_put_contents('manuallist.json',json_encode($res));
//file_put_contents('manuallist_shb.json',json_encode($resSafetyGuidelines));
