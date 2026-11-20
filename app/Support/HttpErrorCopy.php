<?php

namespace App\Support;

class HttpErrorCopy
{
    /**
     * English source lines. The page translates them with __().
     *
     * @var array<int, array{title: string, message: string}>
     */
    private const PAGES = [
        400 => ['title' => 'Bad Request', 'message' => 'The request could not be read. Check it and try again.'],
        401 => ['title' => 'Unauthorized', 'message' => 'You need to sign in before opening this page.'],
        402 => ['title' => 'Payment Required', 'message' => 'This action is not available.'],
        403 => ['title' => 'Forbidden', 'message' => 'You do not have permission to open this page.'],
        404 => ['title' => 'Not Found', 'message' => 'This page is not in the system.'],
        405 => ['title' => 'Method Not Allowed', 'message' => 'This action is not allowed on this page.'],
        408 => ['title' => 'Request Timeout', 'message' => 'The request took too long. Try again.'],
        409 => ['title' => 'Conflict', 'message' => 'This record was changed by someone else. Refresh and try again.'],
        410 => ['title' => 'Gone', 'message' => 'This page is no longer available.'],
        413 => ['title' => 'Content Too Large', 'message' => 'The file or request is too large.'],
        414 => ['title' => 'URI Too Long', 'message' => 'The address is too long.'],
        415 => ['title' => 'Unsupported Media Type', 'message' => 'This file type is not accepted.'],
        419 => ['title' => 'Page Expired', 'message' => 'This page has expired. Go back, refresh, and try again.'],
        422 => ['title' => 'Unprocessable Content', 'message' => 'Some of the details could not be saved. Check them and try again.'],
        429 => ['title' => 'Too Many Requests', 'message' => 'Too many attempts. Wait a moment and try again.'],
        500 => ['title' => 'Server Error', 'message' => 'Something went wrong on our side. Try again in a moment.'],
        501 => ['title' => 'Not Implemented', 'message' => 'This action is not available yet.'],
        502 => ['title' => 'Bad Gateway', 'message' => 'The system could not reach a service it needs. Try again in a moment.'],
        503 => ['title' => 'Service Unavailable', 'message' => 'The system is briefly unavailable. Try again in a moment.'],
        504 => ['title' => 'Gateway Timeout', 'message' => 'A service took too long to answer. Try again in a moment.'],
    ];

    /**
     * @return array{title: string, message: string}
     */
    public static function forStatus(int $status): array
    {
        if (isset(self::PAGES[$status])) {
            return self::PAGES[$status];
        }

        if ($status >= 500) {
            return self::PAGES[500];
        }

        return [
            'title' => 'Something went wrong',
            'message' => 'This page could not be opened.',
        ];
    }
}
