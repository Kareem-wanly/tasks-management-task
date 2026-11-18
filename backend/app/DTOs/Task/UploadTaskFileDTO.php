<?php

declare(strict_types=1);

namespace App\DTOs\Task;

use Illuminate\Http\UploadedFile;

final readonly class UploadTaskFileDTO
{
    public function __construct(
        public UploadedFile $file,
    ) {}

    /**
     * @param  array{file: UploadedFile}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(file: $data['file']);
    }
}
