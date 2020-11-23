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
        try {
            $file = $request->file;
            $fileType = $request->fileType;
            $fileName = $request->fileName;
            $fileSize = $request->fileSize;
            $toLang = $request->toLang;

            $uploadFile = UploadFile::create([
                'file_type' => $fileType,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'to_lang' => $toLang
            ]);

            Storage::disk('uploads')->put('/' . $uploadFile->id . '/original/' . $fileName, file_get_contents($file));

            return response()->json([
                'message' => 'File uploaded successfully to server',
                'uFileId' => $uploadFile->id
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'File uploading to server failed'
            ], 500);
        }
    }

    public function convertFile(Request $request) {
        try {
            $uFileId = $request->uFileId;
            $uploadFile = UploadFile::find($uFileId);

            if (!$uploadFile) {
                return response()->json([
                    'message' => 'File not exist on server'
                ]);
            }
            
            $endpoint = "https://sandbox.zamzar.com/v1/jobs";
            $apiKey = env('ZAMZAR_API_KEY');
            $sourceFilePath = public_path() . '/uploads/' . $uploadFile->id . '/original/' . $uploadFile->file_name;
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

            if ($response && !isset($response['errors'])) {
                $uploadFile->job_id = $response['id'];
                $uploadFile->status = 'initialising';
                $uploadFile->save();

                return response()->json([
                    'message' => 'File converting started on ZamZar',
                    'jobId' => $response['id']
                ]);
            } else {
                return response()->json([
                    'message' => 'File uploading to Zamzar failed'
                ], 500);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'File uploading to Zamzar failed'
            ], 500);
        } 
    }

    public function checkJob(Request $request) {
        try {
            $uFileId = $request->uFileId;
            $jobId = $request->jobId;
          
            $endpoint = "https://sandbox.zamzar.com/v1/jobs/$jobId";
            $apiKey = env('ZAMZAR_API_KEY');

            $ch = curl_init(); // Init curl
            curl_setopt($ch, CURLOPT_URL, $endpoint); // API endpoint
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return response as a string
            curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ":"); // Set the API key as the basic auth username
            $body = curl_exec($ch);
            curl_close($ch);

            $job = json_decode($body, true);

            if ($job['status'] == 'successful') {
                $uploadFile = UploadFile::find($uFileId);

                if ($uploadFile) {
                    $uploadFile->target_files = json_encode($job['target_files']);
                    $uploadFile->status = 'successful';
                    $uploadFile->save();
                }

                return response()->json([
                    'message' => 'Zamzar job finished successfully',
                    'status' => 'successful',
                    'targetFiles' => $targetFiles
                ]);
            } else {
                return response()->json([
                    'message' => 'Zamzar job still doing...',
                    'status' => 'initialising'
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Checking Zamzar job failed'
            ], 500);
        }
    }

    public function downloadFile(Request $request) {
        try {
            $uFileId = $request->uFileId;
            $targetFile = $request->targetFile;

            $localFilename = public_path() . '/uploads/' . $uFileId . '/html/' . $targetFile['name'];
            $endpoint = "https://sandbox.zamzar.com/v1/files/$fileId/content";
            $apiKey = env('ZAMZAR_API_KEY');

            $ch = curl_init(); // Init curl
            curl_setopt($ch, CURLOPT_URL, $endpoint); // API endpoint
            curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ":"); // Set the API key as the basic auth username
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

            $fh = fopen($localFilename, "wb");
            curl_setopt($ch, CURLOPT_FILE, $fh);

            $body = curl_exec($ch);
            curl_close($ch);

            return response()->json([
                'message' => 'Downloaded zamzar file ' . $targetFile['name'] . ' to server successfully'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Downloading zamzar file to server failed'
            ], 500);
        }
    }
    
}