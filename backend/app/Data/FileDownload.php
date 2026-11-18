<?php

declare(strict_types=1);

namespace App\Data;

use App\Integrations\Bitrix24\BitrixClient;
use Closure;
use Psr\Http\Message\StreamInterface;

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

    /** A file whose bytes come from an already opened stream (e.g. a Bitrix download). */
    public static function fromStream(string $name, ?string $mime, StreamInterface $body): self
    {
        return new self($name, $mime, function () use ($body): void {
            while (! $body->eof()) {
                echo $body->read(BitrixClient::DOWNLOAD_CHUNK_BYTES);
                flush();
            }
        });
    }
}
