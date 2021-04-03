<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PDF;
use App\Models\UploadFile;
use Spatie\Browsershot\Browsershot;
use Nesk\Puphpeteer\Puppeteer;
use Nesk\Rialto\Data\JsFunction;
use Stichoza\GoogleTranslate\GoogleTranslate;

class APIController extends Controller
{
    public $_accessToken = '';
    public $_tokenType = '';
    public $workflows = [
        'CONVERTPDFTOWORD' => '0000000000000001',
        'CONVERTPDFTOEXCEL' => '0000000000000002',
        'CONVERTFILESTOPDF' => '0000000000000003',
        'COMBINEFILESTOPDF' => '0000000000000004',
        'TOHTML' => '0000000006AE5CDC',
        'TOPDF' => '0000000006AE5CC8'
    ];
    
    public function __construct()
    {
    }

    public function uploadFile(Request $request) {
        try {
            $file = $request->file;
            $fileType = $request->fileType;
            $fileName = $request->fileName;
            $fileSize = $request->fileSize;
            $fromLang = $request->fromLang;
            $toLang = $request->toLang;
            $uploadFile = UploadFile::create([
                'file_type' => $fileType,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'from_lang' => $fromLang,
                'to_lang' => $toLang
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
            $originPath = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
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
                $workflowId = $this->workflows['CONVERTFILESTOPDF'];
                
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

            $workflowId = $this->workflows['TOHTML'];
            
            $outputFileName = public_path() . '/uploads/' . $uploadFile->id . '/html/' . $fName . '.html';
            $translatedOutputFileName = public_path() . '/uploads/' . $uploadFile->id . '/html/' . $fName . '_translated.html';

            $b = $this->convertEasyPdfCloud($workflowId, $inputFileName, $outputFileName);
            
            if ($b === true) {
                
            } else {
                return response()->json([
                    'message' => 'EasyPDFCloud converting failed.'
                ], 500);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        } 
    }

    public function translateHTML(Request $request) {
        $uFileId = $request->uFileId;
        $uploadFile = UploadFile::find($uFileId);
        $fileName = $uploadFile->file_name;
        $arr = explode('.', $fileName);
        $fName = $arr[0];
        $outputFileName = public_path() . '/uploads/' . $uploadFile->id . '/html/' . $fName . '.html';

        $content = file_get_contents($outputFileName);
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        $tr = new GoogleTranslate();
        $tr->setSource($uploadFile->from_lang);
        $tr->setTarget($uploadFile->to_lang);

        $entities = $xpath->query('//text()');

        foreach ($xpath->query('//text()') as $text) {
            if ($text->parentNode->tagName === 'script' || $text->parentNode->tagName === 'style') {
                continue;
            }
            if (trim($text->nodeValue)) {
                $text->nodeValue = $tr->translate($text->nodeValue);
            }
        }

        $html = $dom->saveHTML();
        
        $fh = fopen($translatedOutputFileName, "wb");
        file_put_contents($translatedOutputFileName, $html);
        fclose($fh);

        $puppeteer = new Puppeteer();
        $browser = $puppeteer->launch([
            "ignoreHTTPSErrors"     => true,
            "args"                  => ['--no-sandbox', '--disable-setuid-sandbox']
        ]);
        $page = $browser->newPage();
        $page->goto($originPath . '/translation?uFileId=' . $uFileId, ["waitUntil" => "networkidle2"]);
        $pdfFileName = 'uploads/' . $uploadFile->id . '/pdf/' . $fName . '.pdf';

        $outputDirname = dirname($pdfFileName);
        if (!is_dir($outputDirname)) {
            mkdir($outputDirname, 0755, true);
        }

        $page->pdf([
            "path" => $pdfFileName,
            "format" => "A4"
        ]);
        $browser->close();

        return response()->json([
            'fileName' => $fName . '.pdf',
            'url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/" . $pdfFileName
        ], 200);
    }
}