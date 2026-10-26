<?php
namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfilePhoto
{
    public static function store(UploadedFile $file): string
    {
        $source = @imagecreatefromstring($file->get());
        if (!$source) throw ValidationException::withMessages(['profile_photo' => 'Choose a valid JPG, PNG or WebP photo.']);
        $photo = imagecreatetruecolor(256, 256);
        imagefill($photo, 0, 0, imagecolorallocate($photo, 255, 255, 255));
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        imagecopyresampled($photo, $source, 0, 0, (int) (($width - $side) / 2), (int) (($height - $side) / 2), 256, 256, $side, $side);
        ob_start();
        imagejpeg($photo, null, 85);
        $bytes = ob_get_clean();
        imagedestroy($source);
        imagedestroy($photo);
        $path = 'profile-photos/'.Str::uuid().'.jpg';
        if (!Storage::disk('local')->put($path, $bytes)) {
            throw ValidationException::withMessages(['profile_photo' => 'The photo could not be saved. Please try again.']);
        }
        return $path;
    }
}
