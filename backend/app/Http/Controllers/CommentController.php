<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CommentController extends Controller
{
    use AuthorizesRequests;

    
    public function index(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $comments = $task->comments()
            ->with('user:id,name,email')
            ->latest()
            ->get();

        return response()->json([
            'data' => $comments
        ]);
    }

    public function store(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $this->authorize('create', [Comment::class, $task]);

        $comment = $task->comments()->create([
            'body'    => $validated['body'],
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Comment created successfully',
            'data'    => $comment->load('user:id,name,email')
        ], 201);
    }

    public function update(Request $request, Comment $comment): JsonResponse
    {
        $this->authorize('update', $comment);

        $validated = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $comment->update($validated);

        return response()->json([
            'message' => 'Comment updated successfully',
            'data'    => $comment->load('user:id,name,email'),
        ]);
    }

    public function destroy(Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return response()->json([
            'message' => 'Comment deleted successfully',
        ]);
    }
}