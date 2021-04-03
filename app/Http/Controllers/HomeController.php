<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UploadFile;

class HomeController extends Controller
{
    public function __construct()
    {
    }

    public function index() {
        return view('index');
    }

    public function translation(Request $request) {
        $uFileId = $request->uFileId;
        $uploadFile = UploadFile::find($uFileId);
        $fileName = $uploadFile->file_name;
        $arr = explode('.', $fileName);
        $fName = $arr[0];
        
        $htmlFilename = public_path() . '/uploads/' . $uFileId . '/html/' . $fName . '_translated.html';

        $content = file_get_contents($htmlFilename);
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($content);
        libxml_clear_errors();
        
        return($dom->saveHTML());
    }
    
}
