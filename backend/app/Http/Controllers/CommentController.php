<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteCommentRequest;
use App\Http\Requests\ListCommentsRequest;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Task;
use App\Services\CommentService;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    public function __construct(private readonly CommentService $comments) {}

    public function index(ListCommentsRequest $request, Task $task): JsonResponse
    {
        return $this->paginated($this->comments->list($task, $request->perPage()), 'Comments retrieved successfully');
    }

    public function store(StoreCommentRequest $request, Task $task): JsonResponse
    {
        return response()->json([
            'message' => 'Comment created successfully',
            'data' => new CommentResource($this->comments->create($request->user(), $task, $request->validated('body'))),
        ], 201);
    }

    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
    {
        return response()->json([
            'message' => 'Comment updated successfully',
            'data' => new CommentResource($this->comments->update($comment, $request->validated())),
        ]);
    }

    public function destroy(DeleteCommentRequest $request, Comment $comment): JsonResponse
    {
        $this->comments->delete($comment);

        return response()->json(['message' => 'Comment deleted successfully']);
    }
}
