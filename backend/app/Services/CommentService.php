<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\CommentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Task comments. Replaced by the task chat in Bitrix24 step 4.7.
 */
class CommentService
{
    public function __construct(private readonly CommentRepositoryInterface $comments) {}

    public function list(Task $task, int $perPage): LengthAwarePaginator
    {
        return $this->comments->paginateForTask($task, $perPage);
    }

    public function create(User $author, Task $task, string $body): Comment
    {
        return $this->comments->withAuthor($this->comments->create($task, $author, $body));
    }

    /** @param  array<string, mixed>  $data */
    public function update(Comment $comment, array $data): Comment
    {
        $this->comments->update($comment, $data);

        return $this->comments->withAuthor($comment);
    }

    public function delete(Comment $comment): void
    {
        $this->comments->delete($comment);
    }
}
