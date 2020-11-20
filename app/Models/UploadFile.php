<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UploadFile extends Model
{
    protected $table = 'upload_files';

    protected $fillable = [
        'org_type', 'org_name', 'org_size', 'file_name', 'html_name', 'conv_name', 'job_id', 'file_id', 'to_lang', 'status'
    ];
}
