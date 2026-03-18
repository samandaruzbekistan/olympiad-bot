<?php

namespace App\Services;

class BotManager
{
    public function __construct(
        protected TelegramService $telegram,
        protected StartHandler $startHandler,
        protected RegistrationHandler $registrationHandler,
        protected OlympiadHandler $olympiadHandler,
    ) {
    }

    public function handle(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);

            return;
        }

        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }
    }

    public function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        $text   = $message['text'] ?? '';

        if ($chatId === null) {
            return;
        }

        if (is_string($text) && str_starts_with($text, '/')) {
            $this->handleCommand($chatId, $text, $message);

            return;
        }

        if (isset($message['contact']) || $text !== '') {
            $this->handlePlainMessage($chatId, $text, $message);
        }
    }

    public function handleCallback(array $callback): void
    {
        $callbackId = $callback['id'] ?? null;
        $data = $callback['data'] ?? null;

        if ($data === 'register_start') {
            $this->registrationHandler->handleCallback($callback);
            if ($callbackId !== null) {
                $this->telegram->answerCallback($callbackId);
            }
            return;
        }

        if (is_string($data) && (str_starts_with($data, 'region_') || str_starts_with($data, 'district_'))) {
            if ($this->registrationHandler->handleRegionDistrictCallback($callback) && $callbackId !== null) {
                $this->telegram->answerCallback($callbackId);
            }
            return;
        }

        if (is_string($data) && (str_starts_with($data, 'grade_') || str_starts_with($data, 'subject_toggle_') || $data === 'subjects_confirm')) {
            if ($this->registrationHandler->handleGradeSubjectCallback($callback) && $callbackId !== null) {
                $this->telegram->answerCallback($callbackId);
            }
            return;
        }

        if (is_string($data) && str_starts_with($data, 'olympiad_')) {
            $this->olympiadHandler->showOlympiadDetails($callback);
            if ($callbackId !== null) {
                $this->telegram->answerCallback($callbackId);
            }
            return;
        }

        if (is_string($data) && str_starts_with($data, 'participate_')) {
            $this->olympiadHandler->handleParticipation($callback);
            if ($callbackId !== null) {
                $this->telegram->answerCallback($callbackId);
            }
            return;
        }

        if (is_string($data) && str_starts_with($data, 'ticket_')) {
            $this->olympiadHandler->handleTicketRequest($callback);
            if ($callbackId !== null) {
                $this->telegram->answerCallback($callbackId);
            }
            return;
        }

        if (is_string($data) && str_starts_with($data, 'payments_page_')) {
            $chatId = $callback['message']['chat']['id'] ?? null;
            $messageId = $callback['message']['message_id'] ?? null;
            $telegramId = $callback['from']['id'] ?? null;
            if ($chatId !== null && $telegramId !== null && $messageId !== null) {
                $user = \App\Models\User::where('telegram_id', $telegramId)->first();
                if ($user !== null) {
                    $page = (int) substr($data, strlen('payments_page_'));
                    $this->showUserPayments($chatId, $user, $page, $messageId);
                }
            }
            if ($callbackId !== null) {
                $this->telegram->answerCallback($callbackId);
            }
            return;
        }

        if ($data === 'main_menu') {
            $chatId = $callback['message']['chat']['id'] ?? null;
            $messageId = $callback['message']['message_id'] ?? null;
            if ($chatId !== null && $messageId !== null) {
                $this->telegram->deleteMessage($chatId, $messageId);
                $this->registrationHandler->showMainMenu($chatId);
            } elseif ($chatId !== null) {
                $this->registrationHandler->showMainMenu($chatId);
            }
            if ($callbackId !== null) {
                $this->telegram->answerCallback($callbackId);
            }
            return;
        }

        if (is_string($data) && str_starts_with($data, 'menu_')) {
            $this->handleMenuCallback($callback);
            if ($callbackId !== null) {
                $this->telegram->answerCallback($callbackId);
            }
            return;
        }

        if (is_string($data) && str_starts_with($data, 'profile_edit_')) {
            $this->registrationHandler->handleProfileEditCallback($callback);
            if ($callbackId !== null) {
                $this->telegram->answerCallback($callbackId);
            }
            return;
        }

        if ($callbackId === null) {
            return;
        }

        $this->telegram->answerCallback($callbackId);
    }

    protected function handleMenuCallback(array $callback): void
    {
        $data = $callback['data'] ?? null;
        $chatId = $callback['message']['chat']['id'] ?? null;
        $telegramId = $callback['from']['id'] ?? null;
        if ($chatId === null || $telegramId === null) {
            return;
        }
        $user = \App\Models\User::where('telegram_id', $telegramId)->first();
        if ($data === 'menu_olympiads') {
            $this->olympiadHandler->showOlympiads($chatId, $telegramId);
        } elseif ($data === 'menu_payments' && $user !== null) {
            $this->showUserPayments($chatId, $user);
        } elseif ($data === 'menu_profile') {
            $this->sendProfileContent($chatId, $telegramId);
        }
    }

    protected function handleCommand(int|string $chatId, string $text, array $message): void
    {
        $command = trim(strtok($text, ' '));

        if ($command === '/start') {
            $this->handleStartCommand($chatId, $message);

            return;
        }

        $this->handleUnknownCommand($chatId, $command, $message);
    }

    protected function handlePlainMessage(int|string $chatId, string $text, array $message): void
    {
        $telegramId = $message['from']['id'] ?? $chatId;
        $user = \App\Models\User::where('telegram_id', $telegramId)->first();

        if ($user !== null && $text !== '') {
            if ($text === '🏆 Olimpiadalar') {
                $this->olympiadHandler->showOlympiads($chatId, $telegramId);
                return;
            }
            if ($text === '💳 To\'lovlarim') {
                $this->showUserPayments($chatId, $user);
                return;
            }
            if ($text === '👤 Profil') {
                $this->sendProfileContent($chatId, $telegramId);
                return;
            }
            if ($text === 'ℹ️ Tashkilot haqida') {
                $this->telegram->sendMessage($chatId, "ℹ️ Tashkilot haqida ma'lumot. Tez orada bu yerda batafsil ma'lumot bo'ladi.");
                return;
            }
        }

        $this->registrationHandler->handleMessage($message);
    }

    protected function sendProfileContent(int|string $chatId, int|string $telegramId): void
    {
        $user = \App\Models\User::where('telegram_id', $telegramId)->first();
        if ($user === null) {
            $this->telegram->sendMessage($chatId, "Profil topilmadi. Avval ro'yxatdan o'ting.");
            return;
        }
        $region = $user->region;
        $district = $user->district;
        $subjects = $user->subjects->pluck('name')->join(', ') ?: '—';
        $text = "👤 <b>Profil</b>\n\n";
        $text .= "Ism: {$user->first_name}\n";
        $text .= "Familiya: " . ($user->last_name ?? '—') . "\n";
        $text .= "Telefon: {$user->phone}\n";
        $text .= "Viloyat: " . ($region?->name_uz ?? '—') . "\n";
        $text .= "Tuman: " . ($district?->name_uz ?? '—') . "\n";
        $text .= "Maktab: " . ($user->school ?? '—') . "\n";
        $text .= "Sinf: " . ($user->grade ? $user->grade . '-sinf' : '—') . "\n";
        $text .= "Fanlar: {$subjects}";
        $keyboard = [
            'inline_keyboard' => [
                [['text' => "✏️ Hamma ma'lumotlarni yangilash", 'callback_data' => 'profile_edit_all']],
            ],
        ];
        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    protected function showUserPayments(int|string $chatId, \App\Models\User $user, int $page = 1, ?int $editMessageId = null): void
    {
        $perPage = 10;
        $registrations = \App\Models\Registration::with('olympiad')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        $total = $registrations->count();

        if ($total === 0) {
            $text = "💳 <b>To'lovlarim</b>\n\nSizda hali to'lovlar mavjud emas.";
            if ($editMessageId !== null) {
                $this->telegram->editMessageText($chatId, $editMessageId, $text);
            } else {
                $this->telegram->sendMessage($chatId, $text);
            }
            return;
        }

        $totalPages = (int) ceil($total / $perPage);
        $page = max(1, min($page, $totalPages));
        $items = $registrations->slice(($page - 1) * $perPage, $perPage);

        $statusLabels = [
            'paid' => '✅ To\'langan',
            'pending' => '⏳ Kutilmoqda',
            'failed' => '❌ Muvaffaqiyatsiz',
        ];

        $text = "💳 <b>To'lovlarim</b> ({$page}/{$totalPages})\n\n";
        $num = ($page - 1) * $perPage;

        foreach ($items as $reg) {
            $num++;
            $title = $reg->olympiad?->title ?? '—';
            $status = $statusLabels[$reg->payment_status] ?? $reg->payment_status;
            $price = $reg->olympiad?->price ? number_format((int) $reg->olympiad->price, 0, '.', ' ') . " so'm" : '—';
            $date = $reg->created_at->format('d.m.Y');
            $ticket = $reg->ticket_number ? "🎟 {$reg->ticket_number}" : '';

            $text .= "<b>{$num}.</b> {$title}\n";
            $text .= "   {$status} · {$price} · {$date}";
            if ($ticket !== '') {
                $text .= "\n   {$ticket}";
            }
            $text .= "\n\n";
        }

        $buttons = [];
        $navRow = [];
        if ($page > 1) {
            $navRow[] = ['text' => '⬅️ Oldingi', 'callback_data' => 'payments_page_' . ($page - 1)];
        }
        if ($page < $totalPages) {
            $navRow[] = ['text' => 'Keyingi ➡️', 'callback_data' => 'payments_page_' . ($page + 1)];
        }
        if (! empty($navRow)) {
            $buttons[] = $navRow;
        }

        if ($editMessageId !== null) {
            $this->telegram->editMessageText($chatId, $editMessageId, $text, ! empty($buttons) ? $buttons : null);
        } else {
            $keyboard = ! empty($buttons) ? ['inline_keyboard' => $buttons] : null;
            $this->telegram->sendMessage($chatId, $text, $keyboard);
        }
    }

    protected function handleStartCommand(int|string $chatId, array $message): void
    {
        $this->startHandler->handle($message);
    }

    protected function handleUnknownCommand(int|string $chatId, string $command, array $message): void
    {
        $this->telegram->sendMessage($chatId, "Unknown command: {$command}");
    }
}
