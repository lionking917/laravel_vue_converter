<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UploadFile extends Model
{
    protected $table = 'upload_files';

    protected $fillable = [
        'file_type', 'file_name', 'file_size', 'src_splitted_html', 'trns_splitted_html', 'to_lang', 'job_id', 'target_files', 'status'
    ];
}
