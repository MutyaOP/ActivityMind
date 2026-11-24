<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Язык и тема интерфейса. У вошедшего пользователя хранятся в профиле,
 * у гостя (страницы входа и регистрации) — в cookie.
 */
class Preferences
{
    /** Подписи языков — на самом языке, чтобы их можно было узнать при любом текущем языке. */
    public const LOCALES = ['ru' => 'Русский', 'kk' => 'Қазақша', 'en' => 'English'];

    public const SHORT = ['ru' => 'RU', 'kk' => 'KZ', 'en' => 'EN'];

    public const THEMES = ['dark', 'light', 'system'];

    public const COOKIE_MINUTES = 60 * 24 * 365;

    public static function locale(Request $request): string
    {
        $locale = $request->user()?->locale ?? $request->cookie('locale');

        return array_key_exists((string) $locale, self::LOCALES) ? $locale : config('app.locale');
    }

    public static function theme(Request $request): string
    {
        $theme = $request->user()?->theme ?? $request->cookie('theme');

        return in_array($theme, self::THEMES, true) ? $theme : 'dark';
    }
}
