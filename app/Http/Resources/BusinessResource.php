<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BusinessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $settings = is_array($this->settings) ? $this->settings : [];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'business_type' => $this->business_type,
            'custom_domain' => $this->custom_domain,
            'description' => $this->description,
            'logo' => $this->logo,
            'banner' => $this->banner,
            'favicon' => $this->favicon,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'postal_code' => $this->postal_code,
            'currency' => $this->currency,
            'language' => $this->language,
            'timezone' => $this->timezone,
            'tax_rate' => $this->tax_rate,
            'tax_included' => $this->tax_included,
            'status' => $this->status,
            'is_verified' => $this->is_verified,
            'theme' => $settings['theme'] ?? [],
            'meta' => [
                'title' => $this->meta_title,
                'description' => $this->meta_description,
                'keywords' => $this->meta_keywords ?? [],
            ],
        ];
    }
}
