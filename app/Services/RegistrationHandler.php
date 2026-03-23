<?php

namespace App\Services;

use App\Models\BotSession;
use App\Models\District;
use App\Models\Region;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Client\RequestException;

class RegistrationHandler
{
    public const STATE_WAITING_FIRST_NAME = 'WAITING_FIRST_NAME';
    public const STATE_WAITING_LAST_NAME = 'WAITING_LAST_NAME';
    public const STATE_WAITING_BIRTH_DATE = 'WAITING_BIRTH_DATE';
    public const STATE_WAITING_PHONE = 'WAITING_PHONE';
    public const STATE_WAITING_REGION = 'WAITING_REGION';
    public const STATE_WAITING_DISTRICT = 'WAITING_DISTRICT';
    public const STATE_WAITING_SCHOOL = 'WAITING_SCHOOL';
    public const STATE_WAITING_GRADE = 'WAITING_GRADE';
    public const STATE_WAITING_SUBJECTS = 'WAITING_SUBJECTS';
    public const STATE_PROFILE_EDIT_FIRST_NAME = 'PROFILE_EDIT_FIRST_NAME';
    public const STATE_PROFILE_EDIT_LAST_NAME = 'PROFILE_EDIT_LAST_NAME';
    public const STATE_PROFILE_EDIT_SCHOOL = 'PROFILE_EDIT_SCHOOL';

    public function __construct(
        protected TelegramService $telegram,
        protected BotSessionService $sessions,
    ) {
    }

    public function handleCallback(array $callback): void
    {
        $data = $callback['data'] ?? null;
        $chatId = $callback['message']['chat']['id'] ?? null;
        $telegramId = $callback['from']['id'] ?? $chatId;

        if ($data !== 'register_start' || $chatId === null || $telegramId === null) {
            return;
        }

        $this->sessions->setState($telegramId, self::STATE_WAITING_FIRST_NAME);
        $this->askFirstName($chatId);
    }

    public function handleRegionDistrictCallback(array $callback): bool
    {
        $data = $callback['data'] ?? null;
        $chatId = $callback['message']['chat']['id'] ?? null;
        $messageId = $callback['message']['message_id'] ?? null;
        $telegramId = $callback['from']['id'] ?? null;

        if ($data === null || $chatId === null || $messageId === null || $telegramId === null) {
            return false;
        }

        if (str_starts_with((string) $data, 'region_')) {
            $regionId = (int) substr($data, 7);
            $region = Region::find($regionId);
            if ($region === null) {
                return true;
            }
            $this->sessions->setData($telegramId, 'selected_region_id', $regionId);
            $this->sessions->setState($telegramId, self::STATE_WAITING_DISTRICT);

            $districts = District::where('region_id', $regionId)->orderBy('name_uz')->get();
            $rows = $this->buildDistrictRows($districts);
            $this->telegram->editMessageText($chatId, $messageId, "Tumaningizni tanlang.", $rows);
            return true;
        }

        if (str_starts_with((string) $data, 'district_')) {
            $districtId = (int) substr($data, 9);
            $sessionData = $this->sessions->getData($telegramId);
            $regionId = $sessionData['selected_region_id'] ?? null;
            if ($regionId === null) {
                return true;
            }
            $district = District::where('id', $districtId)->where('region_id', $regionId)->first();
            if ($district === null) {
                return true;
            }
            $this->sessions->setData($telegramId, 'region_id', $regionId);
            $this->sessions->setData($telegramId, 'district_id', $districtId);
            $this->sessions->setState($telegramId, self::STATE_WAITING_SCHOOL);

            $this->telegram->editMessageText($chatId, $messageId, "✅ Viloyat va tuman tanlandi.", []);
            $this->telegram->sendMessage($chatId, "Maktab raqami yoki nomini kiriting:");
            return true;
        }

        return false;
    }

    public function handleGradeSubjectCallback(array $callback): bool
    {
        $data = $callback['data'] ?? null;
        $chatId = $callback['message']['chat']['id'] ?? null;
        $messageId = $callback['message']['message_id'] ?? null;
        $telegramId = $callback['from']['id'] ?? null;

        if ($data === null || $chatId === null || $messageId === null || $telegramId === null) {
            return false;
        }

        $sessionData = $this->sessions->getData($telegramId);

        if (str_starts_with((string) $data, 'grade_')) {
            $grade = (int) substr($data, 6);
            if ($grade < 1 || $grade > 11) {
                return true;
            }
            $this->sessions->setData($telegramId, 'grade', $grade);
            $this->sessions->setState($telegramId, self::STATE_WAITING_SUBJECTS);
            $this->sessions->setData($telegramId, 'subject_ids', []);

            $subjects = Subject::orderBy('name')->get();
            $rows = $this->buildSubjectRows($subjects, []);
            $text = $this->buildSubjectMessageText($subjects, []);
            $this->safeEditMessageText($chatId, $messageId, $text, $rows);
            return true;
        }

        if (str_starts_with((string) $data, 'subject_toggle_')) {
            $subjectId = (int) substr($data, strlen('subject_toggle_'));
            $selected = $this->normalizeSubjectIds($sessionData['subject_ids'] ?? []);

            $key = array_search($subjectId, $selected, true);
            if ($key === false) {
                $selected[] = $subjectId;
            } else {
                array_splice($selected, $key, 1);
            }
            $selected = array_values($selected);
            $this->sessions->setData($telegramId, 'subject_ids', $selected);

            $subjects = Subject::orderBy('name')->get();
            $rows = $this->buildSubjectRows($subjects, $selected);
            $text = $this->buildSubjectMessageText($subjects, $selected);
            $this->safeEditMessageText($chatId, $messageId, $text, $rows);
            return true;
        }

        if ($data === 'subjects_confirm') {
            $selected = $this->normalizeSubjectIds($sessionData['subject_ids'] ?? []);
            $selected = array_values(array_filter($selected, fn (int $id) => $id > 0));
            if ($selected !== []) {
                $selected = Subject::whereIn('id', $selected)->pluck('id')->all();
            }

            $allData = $this->sessions->getData($telegramId);

            $user = User::updateOrCreate(
                ['telegram_id' => $telegramId],
                [
                    'phone' => $allData['phone'] ?? '',
                    'first_name' => $allData['first_name'] ?? '',
                    'last_name' => $allData['last_name'] ?? '',
                    'birth_date' => $allData['birth_date'] ?? null,
                    'region_id' => $allData['region_id'] ?? null,
                    'district_id' => $allData['district_id'] ?? null,
                    'school' => $allData['school'] ?? null,
                    'grade' => $allData['grade'] ?? null,
                ]
            );

            if ($selected === []) {
                $user->subjects()->detach();
            } else {
                $user->subjects()->sync($selected);
            }
            $this->sessions->clear($telegramId);

            try {
                $this->telegram->deleteMessage($chatId, $messageId);
            } catch (\Throwable) {}
            $this->telegram->sendMessage($chatId, "✅ Ro'yxatdan o'tdingiz!");
            $this->showMainMenu($chatId);
            return true;
        }

        return false;
    }

    private function safeEditMessageText(int|string $chatId, int $messageId, string $text, array $inlineKeyboard): void
    {
        try {
            $this->telegram->editMessageText($chatId, $messageId, $text, $inlineKeyboard);
        } catch (RequestException $e) {
            if ($e->response->status() !== 400 || strpos((string) $e->response->body(), 'message is not modified') === false) {
                throw $e;
            }
        }
    }

    private function normalizeSubjectIds(mixed $subjectIds): array
    {
        if (! is_array($subjectIds)) {
            return [];
        }
        return array_values(array_map('intval', $subjectIds));
    }

    private function buildSubjectMessageText($subjects, array $selectedIds): string
    {
        $selectedIds = $this->normalizeSubjectIds($selectedIds);
        $text = "📚 <b>Yo'nalishingizni tanlang</b>\n\n";
        $text .= "Qaysi fanlarda qatnashmoqchisiz?\nKeraklilarini bosing, so'ng «Tasdiqlash» ni bosing.\n\n";

        $names = [];
        foreach ($selectedIds as $id) {
            $s = $subjects->firstWhere('id', $id);
            if ($s !== null) {
                $names[] = $s->name;
            }
        }

        if (empty($names)) {
            $text .= "📋 Tanlangan fanlar: <i>hali tanlanmadi</i>";
        } else {
            $text .= "📋 <b>Tanlangan fanlar:</b>\n";
            foreach ($names as $i => $name) {
                $text .= "  " . ($i + 1) . ". " . $name . "\n";
            }
        }
        return $text;
    }

    private function buildSubjectRows($subjects, array $selectedIds = []): array
    {
        $selectedIds = $this->normalizeSubjectIds($selectedIds);
        $rows = [];
        $row = [];
        foreach ($subjects as $s) {
            $label = in_array($s->id, $selectedIds, true) ? '✅ ' . $s->name : $s->name;
            $row[] = ['text' => $label, 'callback_data' => 'subject_toggle_' . $s->id];
            if (count($row) >= 2) {
                $rows[] = $row;
                $row = [];
            }
        }
        if (count($row) > 0) {
            $rows[] = $row;
        }
        $rows[] = [['text' => '✅ Tasdiqlash', 'callback_data' => 'subjects_confirm']];
        return $rows;
    }

    public function showMainMenu(int|string $chatId): void
    {
        $keyboard = [
            'keyboard' => [
                [
                    ['text' => '🏆 Olimpiadalar'],
                    ['text' => '💳 To\'lovlarim'],
                ],
                [
                    ['text' => '👤 Profil'],
                    ['text' => 'ℹ️ Tashkilot haqida'],
                ],
            ],
            'resize_keyboard' => true,
        ];
        $this->telegram->sendMessage($chatId, "Asosiy menyu:", $keyboard);
    }

    public function handleProfileEditCallback(array $callback): bool
    {
        $data = $callback['data'] ?? null;
        $chatId = $callback['message']['chat']['id'] ?? null;
        $telegramId = $callback['from']['id'] ?? null;
        if ($data === null || $chatId === null || $telegramId === null) {
            return false;
        }
        $user = User::where('telegram_id', $telegramId)->first();
        if ($user === null) {
            return true;
        }
        if ($data === 'profile_edit_all') {
            $this->sessions->setState($telegramId, self::STATE_PROFILE_EDIT_FIRST_NAME);
            $this->telegram->sendMessage($chatId, "Yangi ismni yozing:");
            return true;
        }
        return false;
    }

    public function handleProfileEditMessage(int|string $chatId, int|string $telegramId, string $text): bool
    {
        $user = User::where('telegram_id', $telegramId)->first();
        if ($user === null) {
            return false;
        }
        $session = $this->sessions->getSession($telegramId);
        if ($session->state === self::STATE_PROFILE_EDIT_FIRST_NAME) {
            $user->update(['first_name' => $text]);
            $this->sessions->setState($telegramId, self::STATE_PROFILE_EDIT_LAST_NAME);
            $this->telegram->sendMessage($chatId, "Yangi familiyani yozing:");
            return true;
        }
        if ($session->state === self::STATE_PROFILE_EDIT_LAST_NAME) {
            $user->update(['last_name' => $text]);
            $this->sessions->setState($telegramId, self::STATE_PROFILE_EDIT_SCHOOL);
            $this->telegram->sendMessage($chatId, "Yangi maktab raqami yoki nomini yozing:");
            return true;
        }
        if ($session->state === self::STATE_PROFILE_EDIT_SCHOOL) {
            $user->update(['school' => $text]);
            $this->sessions->setState($telegramId, BotSessionService::STATE_IDLE);
            $this->telegram->sendMessage($chatId, "✅ Barcha ma'lumotlar yangilandi.");
            return true;
        }
        return false;
    }

    private function buildRegionRows(): array
    {
        $regions = Region::orderBy('name_uz')->get();
        $buttons = [];
        foreach ($regions as $r) {
            $buttons[] = ['text' => $r->name_uz, 'callback_data' => 'region_' . $r->id];
        }
        return array_chunk($buttons, 2);
    }

    private function buildDistrictRows($districts): array
    {
        $buttons = [];
        foreach ($districts as $d) {
            $buttons[] = ['text' => $d->name_uz, 'callback_data' => 'district_' . $d->id];
        }
        return array_chunk($buttons, 2);
    }

    public function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        $telegramId = $message['from']['id'] ?? $chatId;

        if ($chatId === null || $telegramId === null) {
            return;
        }

        /** @var BotSession $session */
        $session = $this->sessions->getSession($telegramId);

        $text = trim((string) ($message['text'] ?? ''));
        $profileEditStates = [self::STATE_PROFILE_EDIT_FIRST_NAME, self::STATE_PROFILE_EDIT_LAST_NAME, self::STATE_PROFILE_EDIT_SCHOOL];
        if (in_array($session->state, $profileEditStates) && $text !== '') {
            if ($this->handleProfileEditMessage($chatId, $telegramId, $text)) {
                return;
            }
        }

        if (isset($message['contact']) && $session->state === self::STATE_WAITING_PHONE) {
            $phone = trim((string) ($message['contact']['phone_number'] ?? ''));
            if ($phone !== '') {
                $this->sessions->setData($telegramId, 'phone', $phone);
                $this->sessions->setState($telegramId, self::STATE_WAITING_REGION);
                $this->askRegion($chatId, $telegramId);
            }
            return;
        }

        if ($text === '') {
            return;
        }

        switch ($session->state) {
            case self::STATE_WAITING_FIRST_NAME:
                $this->sessions->setData($telegramId, 'first_name', $text);
                $this->sessions->setState($telegramId, self::STATE_WAITING_LAST_NAME);
                $this->askLastName($chatId);
                break;

            case self::STATE_WAITING_LAST_NAME:
                $this->sessions->setData($telegramId, 'last_name', $text);
                $this->sessions->setState($telegramId, self::STATE_WAITING_BIRTH_DATE);
                $this->askBirthDate($chatId);
                break;

            case self::STATE_WAITING_BIRTH_DATE:
                $parsed = $this->parseBirthDate($text);
                if ($parsed === null) {
                    $this->telegram->sendMessage(
                        $chatId,
                        "❌ Noto'g'ri format. Iltimos, tug'ilgan sanangizni quyidagi formatda kiriting:\n\n<b>Misol: 15.06.2010</b>\n\n(kun.oy.yil)",
                    );
                    break;
                }
                $this->sessions->setData($telegramId, 'birth_date', $parsed);
                $this->sessions->setState($telegramId, self::STATE_WAITING_PHONE);
                $this->askPhone($chatId);
                break;

            case self::STATE_WAITING_PHONE:
                $this->telegram->sendMessage($chatId, "Iltimos, quyidagi tugmani bosing va telefon raqamingizni yuboring.");
                break;

            case self::STATE_WAITING_SCHOOL:
                $this->sessions->setData($telegramId, 'school', $text);
                $this->sessions->setState($telegramId, self::STATE_WAITING_GRADE);
                $this->askGrade($chatId);
                break;

            default:
                break;
        }
    }

    private function parseBirthDate(string $text): ?string
    {
        $text = trim($text);
        // 15.06.2010 yoki 15/06/2010 yoki 15-06-2010
        if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})$/', $text, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];
            if (checkdate($month, $day, $year) && $year >= 1950 && $year <= date('Y')) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }
        return null;
    }

    protected function askFirstName(int|string $chatId): void
    {
        $this->telegram->sendMessage($chatId, "Ismingizni kiriting:");
    }

    protected function askLastName(int|string $chatId): void
    {
        $this->telegram->sendMessage($chatId, "Familiyangizni kiriting:");
    }

    protected function askBirthDate(int|string $chatId): void
    {
        $this->telegram->sendMessage(
            $chatId,
            "📅 Tug'ilgan sanangizni kiriting:\n\n<b>Misol: 15.06.2010</b>\n\n(kun.oy.yil)",
        );
    }

    protected function askPhone(int|string $chatId): void
    {
        $keyboard = [
            'keyboard' => [
                [['text' => '📱 Telefon raqamini yuborish', 'request_contact' => true]],
            ],
            'one_time_keyboard' => true,
            'resize_keyboard' => true,
        ];
        $this->telegram->sendMessage($chatId, "Telefon raqamingizni yuborish uchun quyidagi tugmani bosing.", $keyboard);
    }

    protected function askRegion(int|string $chatId, int|string $telegramId): void
    {
        $this->telegram->sendMessage($chatId, "Viloyatingizni tanlang.", ['remove_keyboard' => true]);
        $rows = $this->buildRegionRows();
        $response = $this->telegram->sendMessage($chatId, "Quyidagilardan birini tanlang:", ['inline_keyboard' => $rows]);
        $messageId = $response->json('result.message_id');
        if ($messageId !== null) {
            $this->sessions->setData($telegramId, 'region_message_id', $messageId);
        }
    }

    protected function askGrade(int|string $chatId): void
    {
        $rows = [];
        for ($i = 1; $i <= 11; $i++) {
            $rows[] = [['text' => (string) $i . '-sinf', 'callback_data' => 'grade_' . $i]];
        }
        $keyboard = ['inline_keyboard' => array_chunk(array_merge(...$rows), 3)];
        $this->telegram->sendMessage($chatId, "Sinfingizni tanlang (1–11):", $keyboard);
    }
}
