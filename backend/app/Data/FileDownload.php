<?php

namespace App\Data;

use Closure;

/**
 * A file ready to be sent: the controller streams it with send().
 */
final readonly class FileDownload
{
    /**
     * @param  Closure(): void  $send  Writes the file's bytes to the output.
     */
    public function __construct(
        public string $name,
        public ?string $mime,
        public Closure $send,
    ) {}
}
