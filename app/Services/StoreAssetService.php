<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Business;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

final class StoreAssetService
{
    public function store(UploadedFile $file, Business $business, string $kind): string
    {
        $manager = new ImageManager(new Driver());
        $limit = $kind === 'logo' ? 1200 : 2400;
        $encoded = $manager->read($file->getRealPath())
            ->scaleDown(width: $limit, height: $limit)
            ->toWebp(quality: 86);
        $path = 'businesses/'.$business->id.'/store/'.$kind.'.webp';
        $disk = Storage::disk((string) config('shopline.product_image_disk', 'public'));
        $disk->put($path, (string) $encoded, ['visibility' => 'public']);

        return $disk->url($path);
    }
}
