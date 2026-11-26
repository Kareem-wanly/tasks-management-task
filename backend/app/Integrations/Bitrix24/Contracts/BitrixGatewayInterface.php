<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Contracts;

use Psr\Http\Message\StreamInterface;

/**
 * The only way into Bitrix24's REST API (ARC-15): every call goes through
 * it, with a user's or the portal's stored token.
 */
interface BitrixGatewayInterface
{
    public const MAX_BATCH_COMMANDS = 50;

    /** Bytes per read when streaming a download. */
    public const DOWNLOAD_CHUNK_BYTES = 1048576;

    /** List methods return fixed pages of 50 rows. */
    public const PAGE_SIZE = 50;

    /**
     * Call a method with the token of a user, so Bitrix applies that user's
     * permissions and records the change under their name.
     *
     * @param  array<string, mixed>  $params
     * @return array<mixed> The decoded body: result, plus next/total for list methods.
     */
    public function callAsUser(HasBitrixToken $user, string $method, array $params = [], ?int $timeout = null): array;

    /**
     * Call a REST 3.0 method (/rest/api/...) as a user, e.g. task results.
     *
     * @param  array<string, mixed>  $params
     * @return array<mixed> The decoded body: result (with item or items).
     */
    public function callV3AsUser(HasBitrixToken $user, string $method, array $params = []): array;

    /**
     * @param  array<string, mixed>  $params
     * @return array<mixed>
     */
    public function callV3AsService(string $method, array $params = []): array;

    /**
     * Opens a Bitrix download link (it carries the caller's token: never send
     * it to the browser). Called before any response header is sent, so a
     * failure is a normal API error, not an error page saved as the file.
     */
    public function openDownload(string $url): StreamInterface;

    /**
     * Sends a file to an upload URL Bitrix gave us (disk.folder.uploadfile
     * without fileContent), as multipart streamed from disk: the file is
     * never read into memory or base64-encoded.
     *
     * @return array<mixed>
     */
    public function uploadFile(string $uploadUrl, string $field, string $path, string $name, int $timeout): array;

    /**
     * Opens the link and hands the file to $write in chunks, so a large file
     * never sits in memory.
     *
     * @param  callable(string): void  $write
     */
    public function streamDownload(string $url, callable $write): void;

    /**
     * Call a method with the service account's token (background sync).
     *
     * @param  array<string, mixed>  $params
     * @return array<mixed>
     */
    public function callAsService(string $method, array $params = []): array;

    /**
     * Call a method with a raw access token (e.g. the one in the install
     * event). The token cannot be refreshed here, so expiry is final.
     *
     * @param  array<string, mixed>  $params
     * @return array<mixed>
     */
    public function callWithToken(string $accessToken, string $method, array $params = []): array;

    /**
     * Run up to 50 calls in one request. Commands Bitrix turns down for its
     * rate limit (in result_error, the batch itself succeeds) are sent again
     * on their own, with the same backoff as single calls.
     *
     * @param  array<string, array{0: string, 1?: array<string, mixed>}>  $commands  key => [method, params]
     * @return array<mixed> result.result, result_error, result_total, result_next, result_time
     */
    public function batch(HasBitrixToken $holder, array $commands, bool $halt = false): array;

    /**
     * Run a batch with the service account's token (background sync).
     *
     * @param  array<string, array{0: string, 1?: array<string, mixed>}>  $commands
     * @return array<mixed>
     */
    public function batchAsService(array $commands, bool $halt = false): array;

    /**
     * Walk every page of a list method with the service account: one call
     * for the first page and its total, then the rest batched 50 at a time.
     *
     * @param  callable(array<mixed>, int): void  $onPage  Receives each page's `result` and the total Bitrix reported.
     * @param  array<string, mixed>  $params
     * @return int The total Bitrix reported.
     */
    public function eachPageAsService(string $method, array $params, callable $onPage): int;
}
