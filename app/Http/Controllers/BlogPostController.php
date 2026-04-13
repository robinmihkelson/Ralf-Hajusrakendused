<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogPostController extends Controller
{
    public function index(): JsonResponse
    {
        $posts = BlogPost::with(['user:id,name', 'comments.user:id,name'])
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

        $post = BlogPost::create([
            ...$payload,
            'user_id' => $request->user()->id,
        ]);

        return response()->json($post, 201);
    }

    public function show(BlogPost $post): JsonResponse
    {
        $post->load([
            'user:id,name',
            'comments' => fn ($query) => $query->orderByDesc('created_at'),
            'comments.user:id,name',
        ]);

        return response()->json([
            'post' => $post,
        ]);
    }

    public function update(Request $request, BlogPost $post): JsonResponse
    {
        $authorization = $this->authorizePostChange($request, $post);

        if ($authorization instanceof JsonResponse) {
            return $authorization;
        }

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        $post->update($payload);

        return response()->json($post->fresh());
    }

    public function destroy(Request $request, BlogPost $post): JsonResponse
    {
        $authorization = $this->authorizePostChange($request, $post);

        if ($authorization instanceof JsonResponse) {
            return $authorization;
        }

        $post->delete();

        return response()->json(['success' => true]);
    }

    private function authorizePostChange(Request $request, BlogPost $post): ?JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Authentication required to manage posts.'], 401);
        }

        if ($user->is_admin || $post->user_id === $user->id) {
            return null;
        }

        return response()->json(['error' => 'Only the author or administrator can manage posts.'], 403);
    }
}
