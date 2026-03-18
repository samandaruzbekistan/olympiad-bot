<?php

namespace App\Services;

use App\Models\Registration;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use RuntimeException;

class TicketImageService
{
    private const FONT_SIZE_NAME = 28;
    private const FONT_SIZE_EVENT = 35;
    private const FONT_SIZE_TICKET_ID = 20;
    private const FONT_SIZE_DATE = 18;
    private const FONT_SIZE_TIME = 18;
    private const FONT_SIZE_LOCATION = 16;
    private const QR_SIZE = 400;
    private const QR_MARGIN = 100;

    /**
     * Render a ticket image as a PNG binary string (no file saved to disk).
     */
    public function render(Registration $registration): string
    {
        $registration->loadMissing(['user', 'olympiad']);

        $user = $registration->user;
        $olympiad = $registration->olympiad;

        if ($user === null || $olympiad === null) {
            throw new RuntimeException('Registration must have user and olympiad.');
        }

        $templatePath = public_path('img/template.jpg');
        $fontPath = public_path('fonts/ARIALBD 1.TTF');

        if (! file_exists($templatePath)) {
            throw new RuntimeException("Template image not found: {$templatePath}");
        }
        if (! file_exists($fontPath)) {
            throw new RuntimeException("Font file not found: {$fontPath}");
        }

        $image = imagecreatefromjpeg($templatePath);
        if ($image === false) {
            throw new RuntimeException('Failed to load template image.');
        }

        $black = imagecolorallocate($image, 0x22, 0x22, 0x22);
        $white = imagecolorallocate($image, 0xff, 0xff, 0xff);
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        $participantName = mb_strtoupper(trim($user->first_name . ' ' . ($user->last_name ?? '')));
        $eventTitle = mb_strtoupper($olympiad->title ?? '');
        $ticketId = $registration->ticket_number ?? 'N/A';
        $date = $olympiad->start_date?->format('d.m.Y') ?? '—';
        $time = $olympiad->start_date?->format('H:i') ?? '—';

        $locationParts = array_filter([
            $olympiad->location_name,
            $olympiad->location_address,
        ]);
        $location = mb_strtoupper(implode(', ', $locationParts) ?: '—');

        // Left section
        $this->drawText($image, self::FONT_SIZE_NAME, 295, 150, $participantName, $fontPath, $black, 900);
        $this->drawText($image, self::FONT_SIZE_EVENT, 30, 400, $eventTitle, $fontPath, $black, 900);

        // Right panel
        $this->drawText($image, self::FONT_SIZE_TICKET_ID, 960, 120, $ticketId, $fontPath, $black);
        $this->drawText($image, self::FONT_SIZE_TICKET_ID, 960, 232, $date.' YIL', $fontPath, $black);
        $this->drawText($image, self::FONT_SIZE_TICKET_ID, 960, 342, $time . ' DA', $fontPath, $black);
        $this->drawText($image, self::FONT_SIZE_TICKET_ID, 960, 455, $location, $fontPath, $black, 280);

        $this->drawText($image, self::FONT_SIZE_TICKET_ID, 1580, 555, $ticketId, $fontPath, $white);

        // QR Code
        $qrContent = config('app.url') . '/ticket/' . $registration->ticket_number;
        $qrImage = $this->generateQrImage($qrContent);

        if ($qrImage !== null) {
            $qrX = $imageWidth - self::QR_SIZE - self::QR_MARGIN;
            $qrY = $imageHeight - self::QR_SIZE - self::QR_MARGIN-50;
            imagecopyresampled(
                $image,
                $qrImage,
                $qrX,
                $qrY,
                0,
                0,
                self::QR_SIZE,
                self::QR_SIZE,
                imagesx($qrImage),
                imagesy($qrImage),
            );
            imagedestroy($qrImage);
        }

        ob_start();
        imagepng($image);
        $pngData = ob_get_clean();
        imagedestroy($image);

        if ($pngData === false || $pngData === '') {
            throw new RuntimeException('Failed to render ticket PNG.');
        }

        return $pngData;
    }

    /**
     * Draw anti-aliased text with optional max-width truncation.
     */
    private function drawText(
        \GdImage $image,
        int $fontSize,
        int $x,
        int $y,
        string $text,
        string $fontPath,
        int $color,
        ?int $maxWidth = null,
    ): void {
        if ($maxWidth !== null) {
            $text = $this->truncateToFit($text, $fontSize, $fontPath, $maxWidth);
        }

        imagettftext($image, $fontSize, 0, $x, $y, $color, $fontPath, $text);
    }

    /**
     * Truncate text with "..." if it exceeds the given pixel width.
     */
    private function truncateToFit(string $text, int $fontSize, string $fontPath, int $maxWidth): string
    {
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
        if ($bbox === false) {
            return $text;
        }

        $textWidth = abs($bbox[2] - $bbox[0]);
        if ($textWidth <= $maxWidth) {
            return $text;
        }

        while (mb_strlen($text) > 1) {
            $text = mb_substr($text, 0, -1);
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $text . '...');
            if ($bbox === false) {
                break;
            }
            if (abs($bbox[2] - $bbox[0]) <= $maxWidth) {
                return $text . '...';
            }
        }

        return $text;
    }

    private function generateQrImage(string $content): ?\GdImage
    {
        try {
            $builder = new Builder(
                errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                size: self::QR_SIZE,
                margin: 0,
            );
            $result = $builder->build(data: $content);

            $qrPng = $result->getString();
            $qrImage = imagecreatefromstring($qrPng);

            return $qrImage !== false ? $qrImage : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
