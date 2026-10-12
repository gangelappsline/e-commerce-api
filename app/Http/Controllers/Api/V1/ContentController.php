<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Mail\ContactFormMail;
use App\Models\BlogPost;
use App\Models\Business;
use App\Models\ContactMessage;
use App\Models\Newsletter;
use App\Support\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ContentController extends BaseController
{
    public function blogPosts(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $query = BlogPost::query()->forBusiness($business->id)->where('status', 'published')
            ->whereNotNull('published_at')->where('published_at', '<=', now());
        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }
        $paginator = $query->orderByDesc('published_at')->paginate(min(max((int) $request->integer('per_page', 15), 1), 100));
        $paginator->getCollection()->transform(fn (BlogPost $post): array => $post->only([
            'id', 'title', 'slug', 'excerpt', 'featured_image', 'category', 'tags', 'published_at', 'view_count',
        ]));

        return $this->paginatedResponse($paginator, 'Blog posts retrieved successfully.');
    }

    public function blogPost(Request $request, string $slug): JsonResponse
    {
        $business = $this->business($request);
        $post = BlogPost::query()->forBusiness($business->id)->where('status', 'published')
            ->whereNotNull('published_at')->where('published_at', '<=', now())->where('slug', $slug)->firstOrFail();
        $post->increment('view_count');

        return $this->successResponse($post->only(['id', 'title', 'slug', 'excerpt', 'content', 'featured_image', 'category', 'tags', 'published_at', 'view_count']), 'Blog post retrieved successfully.');
    }

    public function blogCategories(Request $request): JsonResponse
    {
        $categories = BlogPost::query()->forBusiness($this->business($request)->id)
            ->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now())
            ->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')
            ->map(fn (string $category): array => ['name' => $category, 'slug' => Str::slug($category)]);

        return $this->successResponse($categories, 'Blog categories retrieved successfully.');
    }

    public function subscribe(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['sometimes', 'nullable', 'string', 'max:150'],
        ]);
        $newsletter = Newsletter::query()->where('business_id', $business->id)->where('email', mb_strtolower($data['email']))->first();
        if ($newsletter === null) {
            $newsletter = Newsletter::create([
                'business_id' => $business->id,
                'email' => mb_strtolower($data['email']),
                'name' => $data['name'] ?? null,
                'is_subscribed' => true,
                'subscribed_at' => now(),
            ]);
        } else {
            $newsletter->forceFill([
                'name' => $data['name'] ?? $newsletter->name,
                'is_subscribed' => true,
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
            ])->save();
        }

        return $this->successResponse($newsletter->only(['id', 'email', 'name', 'is_subscribed', 'subscribed_at']), 'Newsletter subscription saved successfully.', 201);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        Newsletter::query()->where('business_id', $business->id)->where('email', mb_strtolower($data['email']))
            ->update(['is_subscribed' => false, 'unsubscribed_at' => now()]);

        return $this->successResponse(null, 'Newsletter subscription updated successfully.');
    }

    public function contact(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:10000'],
        ]);
        $message = ContactMessage::create([
            'business_id' => $business->id,
            ...$data,
        ]);
        $recipient = $business->email ?: $business->owner?->email;
        if (is_string($recipient) && $recipient !== '') {
            Mail::to($recipient)->queue(new ContactFormMail([
                'name' => $business->name,
                'subject' => $data['subject'] ?? 'Customer contact form',
                'message' => $data['message'],
                'sender_name' => $data['name'],
                'sender_email' => $data['email'],
            ]));
        }

        return $this->successResponse(['id' => $message->id], 'Your message has been sent successfully.', 201);
    }

    private function business(Request $request): Business
    {
        $business = $request->attributes->get('tenant');
        if (! $business instanceof Business) {
            throw new ApiException('STORE_NOT_FOUND', 'Store context is required.', 404);
        }

        return $business;
    }
}
