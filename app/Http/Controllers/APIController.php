<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\UploadFile;

class APIController extends Controller
{
    public function __construct()
    {
    }

    public function uploadFile(Request $request) {
        $file = $request->file;
        $orgType = $request->fileType;
        $orgName = $request->fileName;
        $orgSize = $request->fileSize;
        $toLang = $request->toLang;
        $fileName = time() . '_' . $orgName;

        $uploadFile = UploadFile::create([
            'org_type' => $orgType,
            'org_name' => $orgName,
            'org_size' => $orgSize,
            'file_name' => $fileName,
            'to_lang' => $toLang
        ]);

        Storage::disk('uploads')->put($fileName, file_get_contents($file));

        return response()->json([
            'message' => 'File uploaded successfully to server',
            'fileId' => $uploadFile->id
        ]);
    }

    public function convertFile(Request $request) {
        $fileId = $request->fileId;
        $uploadFile = UploadFile::find($fileId);

        if (!$uploadFile) {
            return response()->json([
                'message' => 'File not exist on server'
            ]);
        }
        
        $endpoint = "https://sandbox.zamzar.com/v1/jobs";
        $apiKey = env('ZAMZAR_API_KEY');
        $sourceFilePath = public_path() . '/uploads/' . $uploadFile->file_name;
        $targetFormat = "html";

        // Since PHP 5.5+ CURLFile is the preferred method for uploading files
        if(function_exists('curl_file_create')) {
            $sourceFile = curl_file_create($sourceFilePath);
        } else {
            $sourceFile = '@' . realpath($sourceFilePath);
        }

        $postData = array(
            "source_file" => $sourceFile,
            "target_format" => $targetFormat
        );

        $ch = curl_init(); // Init curl
        curl_setopt($ch, CURLOPT_URL, $endpoint); // API endpoint
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        // curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false); // Enable the @ prefix for uploading files
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return response as a string
        curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ":"); // Set the API key as the basic auth username
        $body = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($body, true);

        if (!isset($response['errors'])) {
            $uploadFile->job_id = $response['id'];
            $uploadFile->save();

            return response()->json([
                'message' => 'File converting started on ZamZar',
                'jobId' => $response['id']
            ]);
        } else {
            return response()->json([
                'message' => $response['errors'][0]['message']
            ]);
        }
    }
    
}
