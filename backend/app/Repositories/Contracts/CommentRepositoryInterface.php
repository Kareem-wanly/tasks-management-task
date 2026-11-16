<?php

namespace App\Repositories\Contracts;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CommentRepositoryInterface
{
    /** Newest first, with their authors. */
    public function paginateForTask(Task $task, int $perPage): LengthAwarePaginator;

    public function create(Task $task, User $author, string $body): Comment;

    /** @param  array<string, mixed>  $attributes */
    public function update(Comment $comment, array $attributes): void;

    public function delete(Comment $comment): void;

    /** The comment with its author loaded. */
    public function withAuthor(Comment $comment): Comment;
}
