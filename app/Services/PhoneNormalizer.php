<?php

namespace App\Services;

class PhoneNormalizer
{
    public function normalize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $phone = trim((string) $value);

        // Убираем маркеры Excel и знак "+" в начале.
        $phone = preg_replace('/^[=+\s]+/', '', $phone);

        // Буквы запрещены.
        if (preg_match('/[a-zA-Z]/', $phone)) {
            return null;
        }

        // Убираем разрешённые разделители.
        $phone = preg_replace('/[\s().-]+/', '', $phone);

        // После нормализации разрешены только цифры.
        if (!preg_match('/^\d+$/', $phone)) {
            return null;
        }

        // Украинский номер в локальном формате.
        if (preg_match('/^0\d{9}$/', $phone)) {
            $phone = '38' . $phone;
        }

        // Итоговый формат: 380XXXXXXXXX.
        if (!preg_match('/^380\d{9}$/', $phone)) {
            return null;
        }

        return $phone;
    }
}