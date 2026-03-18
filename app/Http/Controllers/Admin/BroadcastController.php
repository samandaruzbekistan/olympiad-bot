<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class BroadcastController extends Controller
{
    public function __construct(
        private readonly TelegramService $telegramService,
    ) {
    }

    public function create(): View
    {
        $totalUsers = User::whereNotNull('telegram_id')->count();

        return view('admin.broadcast.create', compact('totalUsers'));
    }

    public function send(Request $request): RedirectResponse
    {
        $request->validate([
            'message' => 'nullable|string|max:4096',
            'media' => 'nullable|file|max:51200|mimes:jpg,jpeg,png,gif,mp4,mov,avi,webm',
        ]);

        $message = $request->input('message');
        $media = $request->file('media');

        if (blank($message) && $media === null) {
            return back()->withErrors(['message' => 'Xabar matni yoki media fayl kiriting.'])->withInput();
        }

        $mediaType = $this->detectMediaType($media);
        $users = User::whereNotNull('telegram_id')->pluck('telegram_id');

        $sent = 0;
        $failed = 0;

        foreach ($users as $telegramId) {
            try {
                $this->sendToUser($telegramId, $message, $media, $mediaType);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Broadcast failed', [
                    'telegram_id' => $telegramId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('admin.broadcast.create')
            ->with('success', "Xabar yuborildi: {$sent} ta muvaffaqiyatli, {$failed} ta xatolik.");
    }

    private function sendToUser(string $telegramId, ?string $caption, ?UploadedFile $media, ?string $mediaType): void
    {
        $caption = blank($caption) ? null : $caption;

        if ($mediaType === 'photo' && $media !== null) {
            $this->telegramService->sendPhoto($telegramId, $media, $caption);
            return;
        }

        if ($mediaType === 'video' && $media !== null) {
            $this->telegramService->sendVideo($telegramId, $media, $caption);
            return;
        }

        if ($caption !== null) {
            $this->telegramService->sendMessage($telegramId, $caption);
        }
    }

    private function detectMediaType(?UploadedFile $file): ?string
    {
        if ($file === null) {
            return null;
        }

        $mime = $file->getMimeType() ?? '';

        if (str_starts_with($mime, 'image/')) {
            return 'photo';
        }

        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }

        return null;
    }
}
