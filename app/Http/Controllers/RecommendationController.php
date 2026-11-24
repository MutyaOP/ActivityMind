<?php

namespace App\Http\Controllers;

use App\Models\Recommendation;
use App\Services\RecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RecommendationController extends Controller
{
    public function index(Request $request, RecommendationService $service)
    {
        $user = $request->user();
        $service->generateIfStale($user);

        $filters = $request->validate([
            'filter' => ['nullable', 'in:unread'],
            'type'   => ['nullable', 'in:focus,break,habit,planning,other'],
        ]);

        $items = Recommendation::where('user_id', $user->id)
            ->when($filters['filter'] ?? null, fn ($q) => $q->where('is_read', false))
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->latest('created_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('recommendations.index', [
            'items'  => $items,
            'unread' => Recommendation::where('user_id', $user->id)->where('is_read', false)->count(),
        ]);
    }

    /** Принудительное обновление по кнопке. */
    public function generate(Request $request, RecommendationService $service)
    {
        $created = $service->generate($request->user());

        return back()->with('status', $created > 0
            ? "Новых рекомендаций: {$created}."
            : 'Пока нечего добавить: новых поводов для советов нет.');
    }

    public function read(Recommendation $recommendation)
    {
        Gate::authorize('update', $recommendation);

        $recommendation->update(['is_read' => true]);

        return back();
    }

    public function readAll(Request $request)
    {
        Recommendation::where('user_id', $request->user()->id)->where('is_read', false)->update(['is_read' => true]);

        return back()->with('status', 'Все рекомендации отмечены прочитанными.');
    }

    public function destroy(Recommendation $recommendation)
    {
        Gate::authorize('delete', $recommendation);

        $recommendation->delete();

        return back()->with('status', 'Рекомендация удалена.');
    }
}
