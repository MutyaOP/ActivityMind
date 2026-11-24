<?php

namespace App\Http\Controllers;

use App\Support\Preferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'theme'  => ['required', Rule::in(Preferences::THEMES)],
            'locale' => ['required', Rule::in(array_keys(Preferences::LOCALES))],
        ]);

        $request->user()->forceFill($data)->save();
        self::remember($data['locale'], $data['theme']);
        app()->setLocale($data['locale']);

        return redirect()->route('settings.edit')->with('status', __('Настройки сохранены.'));
    }

    /** Быстрое переключение языка (страницы входа и регистрации, переключатель в шапке). */
    public function locale(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, Preferences::LOCALES), 404);

        $request->user()?->forceFill(['locale' => $locale])->save();
        self::remember($locale);

        return redirect()->back(fallback: url('/'));
    }

    /** Запоминаем выбор и в cookie, чтобы после выхода страница входа была на том же языке и в той же теме. */
    private static function remember(string $locale, ?string $theme = null): void
    {
        Cookie::queue('locale', $locale, Preferences::COOKIE_MINUTES);
        if ($theme) {
            Cookie::queue('theme', $theme, Preferences::COOKIE_MINUTES);
        }
    }
}
