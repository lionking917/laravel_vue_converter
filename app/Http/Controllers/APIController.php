<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PDF;
use App\Models\UploadFile;
use JoggApp\GoogleTranslate\GoogleTranslateClient;

class APIController extends Controller
{
    public $_accessToken = '';
    public $_tokenType = '';
    public $workflows = [];
    
    public function __construct()
    {
        $this->workflows = [
            // 'CONVERTPDFTOWORD' => '0000000000000001',
            // 'CONVERTPDFTOEXCEL' => '0000000000000002',
            'CONVERT_FILES_TO_PDF' => env('CONVERT_FILES_TO_PDF'),
            // 'COMBINEFILESTOPDF' => '0000000000000004',
            'CONVERT_TO_HTML' => env('CONVERT_TO_HTML')
            // 'TOPDF' => '0000000006AE5CC8'
        ];
    }

    public function uploadFile(Request $request) {
        try {
            $file = $request->file;
            $fileType = $request->fileType;
            $fileName = $request->fileName;
            $fileSize = $request->fileSize;
            $fromLang = $request->fromLang;
            $targetLang = $request->targetLang;
            $uploadFile = UploadFile::create([
                'file_type' => $fileType,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'target_lang' => $targetLang
            ]);

            Storage::disk('uploads')->put('/' . $uploadFile->id . '/original/' . $fileName, file_get_contents($file));

            return response()->json([
                'uFileId' => $uploadFile->id
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function convertEasyPdfCloud($workflowId, $inputFileName, $outputFileName) {
        try {
            $client = new \Bcl\EasyPdfCloud\Client(env('PDFCLOUD_CLIENT_ID'), env('PDFCLOUD_CLIENT_SECRET'));

            $enableTestMode = true;

            $job = $client->startNewJobWithFilePath($workflowId, $inputFileName, $enableTestMode);
            // Wait until job execution is completed
            $result = $job->waitForJobExecutionCompletion();
            // Save output to file
            $outputDirname = dirname($outputFileName);
            if (!is_dir($outputDirname)) {
                mkdir($outputDirname, 0755, true);
            }
            $fh = fopen($outputFileName, "wb");
            file_put_contents($outputFileName, $result->getFileData()->getContents());
            fclose($fh);
            
            return true;
        } catch (\Throwable $th) {
            return $th->getMessage();
        }
    }

    public function convertFile(Request $request) {
        try {
            $uFileId = $request->uFileId;
            $uploadFile = UploadFile::find($uFileId);

            if (!$uploadFile) {
                return response()->json([
                    'message' => 'File not exist on server'
                ], 500);
            }

            $fileName = $uploadFile->file_name;
            $arr = explode('.', $fileName);
            $fName = $arr[0];

            $inputFileName = public_path() . '/uploads/' . $uploadFile->id . '/original/' . $uploadFile->file_name;

            if ($uploadFile->file_type !== 'application/pdf') {
                $workflowId = $this->workflows['CONVERT_FILES_TO_PDF'];
                
                $outputFileName = public_path() . '/uploads/' . $uploadFile->id . '/original/' . $fName . '.pdf';

                $b = $this->convertEasyPdfCloud($workflowId, $inputFileName, $outputFileName);

                if ($b === true) {
                    $inputFileName = $outputFileName;
                } else {
                    return response()->json([
                        'message' => $th->getMessage()
                    ], 500);
                }
            }

            $workflowId = $this->workflows['CONVERT_TO_HTML'];
            
            $outputFileName = public_path() . '/uploads/' . $uploadFile->id . '/html/' . $fName . '.html';

            $b = $this->convertEasyPdfCloud($workflowId, $inputFileName, $outputFileName);
            
            if ($b === true) {
                return response()->json([
                    'message' => 'File converting succeeded.'
                ], 200);
            } else {
                return response()->json([
                    'message' => 'File converting failed.'
                ], 500);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        } 
    }

    public function translateHTML(Request $request) {
        try {
            $uFileId = $request->uFileId;
            $translatedEntityCnt = (int)$request->translatedEntityCnt;

            $uploadFile = UploadFile::find($uFileId);
            
            $fileName = $uploadFile->file_name;
            $arr = explode('.', $fileName);
            $fName = $arr[0];
            $outputHtmlFileName = public_path() . '/uploads/' . $uploadFile->id . '/html/' . $fName . '.html';

            $content = file_get_contents($outputHtmlFileName);
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();
            $xpath = new \DOMXPath($dom);
            
            $cnt = 0;

            $entities = $xpath->query('//text()');
            $array = array();
            foreach($entities as $entity){
                $array[] = $entity;
            }
            $slicedArray = array_slice($array, $translatedEntityCnt);

            $tr = new GoogleTranslateClient([
                'api_key' => env('GOOGLE_TRANSLATE_API_KEY'),
                'default_target_translation' => 'en'
            ]);

            foreach ($slicedArray as $text) {
                if ($cnt == 200) break;

                $cnt ++;
                $translatedEntityCnt ++;
                
                if ($text->parentNode->tagName === 'script' || $text->parentNode->tagName === 'noscript' || $text->parentNode->tagName === 'style') {
                    continue;
                }
                if (trim($text->nodeValue)) {
                    try {
                        $outputs = $tr->translate($text->nodeValue, $uploadFile->target_lang);
                        $text->nodeValue = $outputs['text'];
                    } catch (\Throwable $th) {
                        $th = $th;
                    }
                }
            }

            $html = $dom->saveHTML();
            file_put_contents($outputHtmlFileName, $html);

            if ($translatedEntityCnt == count($entities)) {
                $outputPdfFileName = public_path() . '/uploads/' . $uploadFile->id . '/pdf/' . $fName . '.pdf';
                $pdfFileName = 'uploads/' . $uploadFile->id . '/pdf/' . $fName . '.pdf';
                $outputDirname = dirname($outputPdfFileName);
                if (!is_dir($outputDirname)) {
                    mkdir($outputDirname, 0755, true);
                }

                // $new_elm = $dom->createElement('style', 'body {font-family: DejaVu Sans;}');
                // $new_elm->setAttribute('type', 'text/css');

                // // Inject the new <style> Tag in the document head
                // $head = $dom->getElementsByTagName('head')->item(0);
                // $head->appendChild($new_elm);

                // $html = $dom->saveHTML();
                // file_put_contents($outputHtmlFileName, $html);

                $config = [
                    'mode' => '+aCJK', 
                    // "allowCJKoverflow" => true, 
                    "autoScriptToLang" => true,
                    // "allow_charset_conversion" => false,
                    "autoLangToFont" => true,
                ];

                // $mpdf = new PDF($config);
                // $mpdf->loadHTML($html);

                // $mpdf->SetAutoFont();
                // $mpdf->autoScriptToLang = true;
                // $mpdf->autoLangToFont   = true;

                PDF::loadHTML($html, $config)->save($outputPdfFileName);
                return response()->json([
                    'isTranslationFinished' => true,
                    'translatedEntityCnt' => $translatedEntityCnt,
                    'fileName' => $fName . '.pdf',
                    'url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/" . $pdfFileName
                ], 200);
            } else {
                return response()->json([
                    'isTranslationFinished' => false,
                    'translatedEntityCnt' => $translatedEntityCnt
                ], 200);
            }
        } catch(\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }
}