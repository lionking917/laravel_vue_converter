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

    public function uploadFile(Request $request){
        $file = $request->file;
        $file_type = $request->file_type;
        $file_size = $request->file_size;
        $conv_lang = $request->conv_lang;
        $uuid = uniqid();

        $uploadFile = UploadFile::create([
            'uuid' => $uuid,
            'file_type' => $file_type,
            'file_size' => $file_size,
            'conv_lang' => $conv_lang
        ]);

        $file_name = Storage::disk('uploads')->put('/', $file);

        $uploadFile->file_name = $file_name;
        $uploadFile->save();

        return response()->json([
            'message' => 'File uploaded successfully',
            'fileId' => $uploadFile->id
        ]);
    }
    
}
