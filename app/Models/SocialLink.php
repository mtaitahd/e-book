<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SocialLink extends Model
{
    public const PLATFORMS = ['facebook' => 'Facebook', 'x' => 'X', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn'];
    public $timestamps = false;
    protected $primaryKey = 'platform';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['platform', 'url'];
}
