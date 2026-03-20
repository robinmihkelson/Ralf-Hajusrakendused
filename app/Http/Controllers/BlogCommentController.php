<?php

namespace App\Http\Controllers;

use App\Models\BlogComment;
use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogCommentController extends Controller
{
    public function index(BlogPost $post): JsonResponse
    {
        $comments = $post->comments()->with('user:id,name')->orderByDesc('created_at')->get();

        return response()->json([
            'comments' => $comments,
        ]);
    }

    public function store(Request $request, BlogPost $post): JsonResponse
    {
        $payload = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);

        $comment = $post->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $payload['content'],
        ]);

        $comment->load('user:id,name');

        return response()->json($comment, 201);
    }

    public function destroy(Request $request, BlogComment $comment): JsonResponse
    {
        if (! $request->user()?->is_admin) {
            return response()->json(['error' => 'Only administrators can remove comments.'], 403);
        }

        $comment->delete();

        return response()->json(['success' => true]);
    }
}
