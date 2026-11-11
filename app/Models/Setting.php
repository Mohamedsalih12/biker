<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Setting extends Model
{
    use LogsActivity;

    protected $table = 'settings';

    protected $fillable = [
        'website_name',
        'website_url',
        'page_title',
        'meta_keyword',
        'meta_description',
        'address',
        'phone_number_1',
        'phone_number_2',
        'email_1',
        'email_2',
        'facebook',
        'twitter',
        'instagram',
        'linkedin',
        'youtube',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('setting')->logOnlyDirty();
    }
}
