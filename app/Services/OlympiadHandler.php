<?php

namespace App\Services;

use App\Models\Olympiad;
use App\Models\OlympiadType;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class OlympiadHandler
{
    public function __construct(
        protected TelegramService $telegram,
        protected ClickPaymentService $clickPayments,
        protected PaymePaymentService $paymePayments,
        protected PaymentService $paymentService,
        protected TicketImageService $ticketImageService,
        protected TicketService $ticketService,
        protected RegistrationHandler $registrationHandler,
    ) {
    }

    /* ------------------------------------------------------------------ */
    /*  Step 1: Show olympiad types                                        */
    /* ------------------------------------------------------------------ */

    public function showOlympiads(int|string $chatId, int|string $telegramId): void
    {
        $types = OlympiadType::whereHas('olympiads', fn ($q) => $q->where('status', 'active'))
            ->orderBy('name')
            ->get();

        if ($types->isEmpty()) {
            // Tur yo'q — barcha aktiv olimpiadalarni chiqar
            $this->showAllOlympiads($chatId);
            return;
        }

        $rows = [];
        foreach ($types as $type) {
            $rows[] = [['text' => $type->name, 'callback_data' => 'otype_' . $type->id]];
        }

        // Turi belgilanmagan olimpiadalar ham bo'lsa
        $noTypeCount = Olympiad::where('status', 'active')->whereNull('type_id')->count();
        if ($noTypeCount > 0) {
            $rows[] = [['text' => '📋 Boshqa olimpiadalar', 'callback_data' => 'otype_0']];
        }

        $rows[] = [['text' => '⬅️ Bosh menu', 'callback_data' => 'main_menu']];

        $this->telegram->sendMessage(
            $chatId,
            "🏆 <b>Olimpiada turini tanlang:</b>",
            ['inline_keyboard' => $rows],
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Step 2: Show olympiads by selected type                           */
    /* ------------------------------------------------------------------ */

    public function showOlympiadsByType(array $callback): void
    {
        $data = $callback['data'] ?? null;
        $chatId = $callback['message']['chat']['id'] ?? null;
        $messageId = $callback['message']['message_id'] ?? null;

        if ($chatId === null || ! is_string($data)) {
            return;
        }

        $typeId = (int) substr($data, strlen('otype_'));

        $query = Olympiad::where('status', 'active')->orderBy('start_date');

        if ($typeId === 0) {
            $query->whereNull('type_id');
            $typeName = 'Boshqa olimpiadalar';
        } else {
            $type = OlympiadType::find($typeId);
            if ($type === null) {
                return;
            }
            $query->where('type_id', $typeId);
            $typeName = $type->name;
        }

        $olympiads = $query->get();

        if ($olympiads->isEmpty()) {
            $rows = [
                [['text' => '⬅️ Turlar ro\'yxati', 'callback_data' => 'back_to_types']],
                [['text' => '⬅️ Bosh menu', 'callback_data' => 'main_menu']],
            ];
            if ($messageId !== null) {
                $this->telegram->editMessageText($chatId, $messageId, "❌ Bu turda hozircha olimpiadalar yo'q.", $rows);
            } else {
                $this->telegram->sendMessage($chatId, "❌ Bu turda hozircha olimpiadalar yo'q.", ['inline_keyboard' => $rows]);
            }
            return;
        }

        $rows = [];
        foreach ($olympiads as $olympiad) {
            $date = $olympiad->start_date?->format('d.m.Y') ?? '';
            $label = $olympiad->title . ($date ? " ({$date})" : '');
            $rows[] = [['text' => $label, 'callback_data' => 'olympiad_' . $olympiad->id]];
        }
        $rows[] = [['text' => '⬅️ Turlar ro\'yxati', 'callback_data' => 'back_to_types']];
        $rows[] = [['text' => '⬅️ Bosh menu', 'callback_data' => 'main_menu']];

        $text = "🏆 <b>{$typeName}</b>\n\nOlimpiadani tanlang:";

        if ($messageId !== null) {
            $this->telegram->editMessageText($chatId, $messageId, $text, $rows);
        } else {
            $this->telegram->sendMessage($chatId, $text, ['inline_keyboard' => $rows]);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Step 3: Show olympiad details                                      */
    /* ------------------------------------------------------------------ */

    public function showOlympiadDetails(array $callback): void
    {
        $data = $callback['data'] ?? null;
        $chatId = $callback['message']['chat']['id'] ?? null;
        $telegramId = $callback['from']['id'] ?? null;

        if ($chatId === null || $telegramId === null || ! is_string($data)) {
            return;
        }

        $id = (int) substr($data, strlen('olympiad_'));
        $olympiad = Olympiad::with('type')->find($id);

        if ($olympiad === null) {
            $this->telegram->sendMessage($chatId, "❌ Olimpiada topilmadi.");
            return;
        }

        $user = User::where('telegram_id', $telegramId)->first();
        $registration = $user
            ? Registration::where('user_id', $user->id)->where('olympiad_id', $olympiad->id)->first()
            : null;

        $alreadyPaid = $registration !== null && $registration->payment_status === 'paid';

        $text = "🏆 <b>{$olympiad->title}</b>";
        if ($olympiad->type) {
            $text .= "  <i>[{$olympiad->type->name}]</i>";
        }
        $text .= "\n\n";
        if ($olympiad->description) {
            $text .= "📝 {$olympiad->description}\n\n";
        }
        $date = $olympiad->start_date?->format('d.m.Y H:i') ?? '—';
        $text .= "📅 Sana: {$date}\n";
        $text .= "📍 Manzil: " . ($olympiad->location_name ?? '—') . "\n";

        $typeId = $olympiad->type_id ?? 0;
        $backButton = ['text' => '⬅️ Olimpiadalar', 'callback_data' => 'otype_' . $typeId];

        if ($alreadyPaid) {
            $text .= "\n✅ <b>Siz ushbu olimpiada ishtirokchisisiz!</b>";
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🎫 Biletni ko\'rish', 'callback_data' => 'ticket_' . $registration->id]],
                    [$backButton],
                    [['text' => '⬅️ Bosh menu', 'callback_data' => 'main_menu']],
                ],
            ];
        } else {
            $text .= "💰 Narxi: " . number_format((int) $olympiad->price, 0, '.', ' ') . " so'm";
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '✅ Ishtirok etish', 'callback_data' => 'participate_' . $olympiad->id]],
                    [$backButton],
                    [['text' => '⬅️ Bosh menu', 'callback_data' => 'main_menu']],
                ],
            ];
        }

        $logoPath = $this->resolveLogoPath($olympiad->logo);

        if ($logoPath !== null) {
            try {
                $this->telegram->sendPhoto($chatId, $logoPath, $text);
                $this->telegram->sendMessage($chatId, "Quyidagi tugmalardan foydalaning:", $keyboard);
            } catch (\Throwable $e) {
                Log::warning('Failed to send olympiad logo', ['error' => $e->getMessage()]);
                $this->telegram->sendMessage($chatId, $text, $keyboard);
            }
        } else {
            $this->telegram->sendMessage($chatId, $text, $keyboard);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Participation (payment)                                            */
    /* ------------------------------------------------------------------ */

    public function handleParticipation(array $callback): void
    {
        $data = $callback['data'] ?? null;
        $chatId = $callback['message']['chat']['id'] ?? null;
        $messageId = $callback['message']['message_id'] ?? null;
        $telegramId = $callback['from']['id'] ?? null;

        if ($chatId === null || $messageId === null || $telegramId === null || ! is_string($data)) {
            return;
        }

        $user = User::where('telegram_id', $telegramId)->first();
        if ($user === null) {
            $this->telegram->sendMessage($chatId, "❌ Avval ro'yxatdan o'ting.");
            return;
        }

        $olympiadId = (int) substr($data, strlen('participate_'));
        $olympiad = Olympiad::find($olympiadId);

        if ($olympiad === null) {
            $this->telegram->sendMessage($chatId, "❌ Olimpiada topilmadi.");
            return;
        }

        $registration = Registration::firstOrCreate(
            ['user_id' => $user->id, 'olympiad_id' => $olympiad->id],
            ['status' => 'pending', 'payment_status' => 'pending'],
        );

        if ($registration->payment_status === 'paid') {
            $this->telegram->editMessageText(
                $chatId,
                (int) $messageId,
                "✅ Siz ushbu olimpiada ishtirokchisisiz!",
                [
                    [['text' => '🎫 Biletni ko\'rish', 'callback_data' => 'ticket_' . $registration->id]],
                    [['text' => '⬅️ Bosh menu', 'callback_data' => 'main_menu']],
                ],
            );
            return;
        }

        $this->paymentService->createForRegistration($registration);

        $clickUrl = $this->clickPayments->generatePaymentLink($registration);
        $paymeUrl = $this->paymePayments->generatePaymentLink($registration);

        $price = number_format((int) $olympiad->price, 0, '.', ' ');

        $this->telegram->editMessageText(
            $chatId,
            (int) $messageId,
            "💳 To'lov turini tanlang:\n\n🏆 {$olympiad->title}\n💰 Narxi: {$price} so'm",
            [
                [['text' => '💳 Click orqali to\'lash', 'url' => $clickUrl]],
                [['text' => '💳 Payme orqali to\'lash', 'url' => $paymeUrl]],
                [['text' => '⬅️ Bosh menu', 'callback_data' => 'main_menu']],
            ],
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Ticket                                                             */
    /* ------------------------------------------------------------------ */

    public function handleTicketRequest(array $callback): void
    {
        $data = $callback['data'] ?? null;
        $chatId = $callback['message']['chat']['id'] ?? null;
        $telegramId = $callback['from']['id'] ?? null;

        if ($chatId === null || $telegramId === null || ! is_string($data)) {
            return;
        }

        $registrationId = (int) substr($data, strlen('ticket_'));
        $registration = Registration::with(['user', 'olympiad'])->find($registrationId);

        if ($registration === null) {
            $this->telegram->sendMessage($chatId, "❌ Ro'yxat topilmadi.");
            return;
        }

        $user = User::where('telegram_id', $telegramId)->first();
        if ($user === null || $registration->user_id !== $user->id) {
            $this->telegram->sendMessage($chatId, "❌ Bu bilet sizga tegishli emas.");
            return;
        }

        if ($registration->payment_status !== 'paid') {
            $this->telegram->sendMessage($chatId, "❌ To'lov hali amalga oshirilmagan.");
            return;
        }

        if ($registration->ticket_number === null) {
            $this->ticketService->createTicket($registration->id);
            $registration->refresh();
        }

        try {
            $pngData = $this->ticketImageService->render($registration);

            $olympiad = $registration->olympiad;
            $location = $olympiad->location_name ?? '—';
            if (! empty($olympiad->location_address)) {
                $location .= ' (' . $olympiad->location_address . ')';
            }
            $date = $olympiad->start_date?->format('d.m.Y H:i') ?? "Noma'lum";

            $caption = "🎫 <b>Sizning biletingiz</b>\n\n"
                . "🏆 {$olympiad->title}\n"
                . "🎟 Ticket: <b>{$registration->ticket_number}</b>\n"
                . "📍 Manzil: {$location}\n"
                . "📅 Sana: {$date}";

            $this->telegram->sendPhotoFromBinary(
                $chatId,
                $pngData,
                "ticket-{$registration->ticket_number}.png",
                $caption,
            );
        } catch (\Throwable $e) {
            Log::error('Failed to generate ticket image', [
                'registration_id' => $registration->id,
                'error' => $e->getMessage(),
            ]);
            $this->telegram->sendMessage(
                $chatId,
                "🎫 Bilet: <b>{$registration->ticket_number}</b>\n\nRasm generatsiya qilishda xatolik yuz berdi.",
            );
        }

        // Bilet yuborilgandan keyin bosh menyuga qaytish
        $this->registrationHandler->showMainMenu($chatId);
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function showAllOlympiads(int|string $chatId): void
    {
        $olympiads = Olympiad::where('status', 'active')
            ->orderBy('start_date')
            ->limit(15)
            ->get();

        if ($olympiads->isEmpty()) {
            $this->telegram->sendMessage(
                $chatId,
                "🏆 Mavjud olimpiadalar hozircha yo'q.",
                ['inline_keyboard' => [[['text' => '⬅️ Bosh menu', 'callback_data' => 'main_menu']]]],
            );
            return;
        }

        $rows = [];
        foreach ($olympiads as $olympiad) {
            $date = $olympiad->start_date?->format('d.m.Y') ?? '';
            $label = $olympiad->title . ($date ? " ({$date})" : '');
            $rows[] = [['text' => $label, 'callback_data' => 'olympiad_' . $olympiad->id]];
        }
        $rows[] = [['text' => '⬅️ Bosh menu', 'callback_data' => 'main_menu']];

        $this->telegram->sendMessage($chatId, "🏆 <b>Olimpiadalar:</b>", ['inline_keyboard' => $rows]);
    }

    private function resolveLogoPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $localPath = storage_path('app/public/' . ltrim($path, '/'));

        return is_file($localPath) ? $localPath : null;
    }
}
