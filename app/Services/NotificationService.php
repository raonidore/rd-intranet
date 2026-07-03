<?php

namespace App\Services;

class NotificationService
{
    public static function success(string $message, ?string $technicalDetails = null): void
    {
        $_SESSION['flash_tipo'] = 'success';
        $_SESSION['flash_msg'] = $message;

        if ($technicalDetails !== null) {
            $_SESSION['flash_tecnico'] = $technicalDetails;
        }
    }

    public static function error(string $message, ?string $technicalDetails = null): void
    {
        $_SESSION['flash_tipo'] = 'error';
        $_SESSION['flash_msg'] = $message;

        if ($technicalDetails !== null) {
            $_SESSION['flash_tecnico'] = $technicalDetails;
        }
    }

    public static function clear(): void
    {
        unset($_SESSION['flash_msg'], $_SESSION['flash_tipo'], $_SESSION['flash_tecnico']);
    }
}
