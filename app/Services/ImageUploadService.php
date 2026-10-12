<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Business;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

final class ImageUploadService
{
    public function uploadProductImage(UploadedFile $file, Business $business, Product $product): string
    {
        $manager = new ImageManager(new Driver());
        $encoded = $manager->read($file->getRealPath())
            ->scaleDown(width: 2400, height: 2400)
            ->toWebp(quality: 86);
        $path = 'businesses/'.$business->id.'/products/'.$product->id.'/'.Str::uuid().'.webp';
        $disk = Storage::disk((string) config('shopline.product_image_disk', 'public'));
        $disk->put($path, (string) $encoded, ['visibility' => 'public']);

        return $disk->url($path);
    }
}
