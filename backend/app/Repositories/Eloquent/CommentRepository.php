<?php

namespace App\Repositories\Eloquent;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\CommentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CommentRepository implements CommentRepositoryInterface
{
    public function paginateForTask(Task $task, int $perPage): LengthAwarePaginator
    {
        return $task->comments()->with('user:id,name,email')->latest()->paginate($perPage);
    }

    public function create(Task $task, User $author, string $body): Comment
    {
        return $task->comments()->create(['body' => $body, 'user_id' => $author->id]);
    }

    public function update(Comment $comment, array $attributes): void
    {
        $comment->update($attributes);
    }

    public function delete(Comment $comment): void
    {
        $comment->delete();
    }

    public function withAuthor(Comment $comment): Comment
    {
        return $comment->load('user:id,name,email');
    }
}
