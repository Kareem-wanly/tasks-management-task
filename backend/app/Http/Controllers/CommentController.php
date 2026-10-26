<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Task;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Http\Resources\CommentResource;

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

    public function store(StoreCommentRequest $request, Task $task): JsonResponse
    {
        $this->authorize('create', [Comment::class, $task]);

        $comment = $task->comments()->create([
            'body' => $request->validated('body'),
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Comment created successfully',
            'data' => new CommentResource($comment->load('user:id,name,email')),
        ], 201);
    }

    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
    {
        $this->authorize('update', $comment);

        $comment->update($request->validated());

        return response()->json([
            'message' => 'Comment updated successfully',
            'data' => new CommentResource($comment->load('user:id,name,email')),
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
