<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Task;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $this->authorize('viewAny', [Comment::class, $task]);

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        $comments = $task->comments()
            ->with('user:id,name,email')
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Comments retrieved successfully',
            'data' => $comments->items(),
            'meta' => [
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'per_page' => $comments->perPage(),
                'total' => $comments->total(),
            ],
        ]);
    }

    public function store(Request $request, Task $task): JsonResponse
    {
        $this->authorize('create', [Comment::class, $task]);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $comment = $task->comments()->create([
            'body' => $validated['body'],
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Comment created successfully',
            'data' => $comment->load('user:id,name,email'),
        ], 201);
    }

    public function update(Request $request, Comment $comment): JsonResponse
    {
        $this->authorize('update', $comment);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $comment->update($validated);

        return response()->json([
            'message' => 'Comment updated successfully',
            'data' => $comment->load('user:id,name,email'),
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
