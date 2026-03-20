<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogPostController extends Controller
{
    public function index(): JsonResponse
    {
        $posts = BlogPost::with(['comments.user:id,name'])
            ->withCount('comments')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'posts' => $posts,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        $post = BlogPost::create($payload);

        return response()->json($post, 201);
    }

    public function show(BlogPost $post): JsonResponse
    {
        $post->load([
            'comments' => fn ($query) => $query->orderByDesc('created_at'),
            'comments.user:id,name',
        ]);

        return response()->json([
            'post' => $post,
        ]);
    }

    public function update(Request $request, BlogPost $post): JsonResponse
    {
        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        $post->update($payload);

        return response()->json($post->fresh());
    }

    public function destroy(BlogPost $post): JsonResponse
    {
        $post->delete();

        return response()->json(['success' => true]);
    }
}
