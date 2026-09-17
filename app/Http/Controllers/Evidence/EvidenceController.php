<?php

namespace App\Http\Controllers\Evidence;

use App\Http\Controllers\Controller;
use App\Models\Evidence;
use App\Models\Measure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Доказательная база — реестр подтверждающих документов по всем мероприятиям,
 * [[Функциональные требования#4.6 Модуль «Доказательная база»]].
 */
class EvidenceController extends Controller
{
    public function index(): Response
    {
        $measures = Measure::query()
            ->orderBy('number')
            ->withCount('evidences')
            ->get(['id', 'number', 'title']);

        return Inertia::render('evidence/index', [
            'measures' => $measures,
        ]);
    }

    public function show(Request $request, Measure $measure): Response
    {
        $groups = $measure->evidences()
            ->with(['stage', 'period'])
            ->latest()
            ->get()
            ->groupBy(fn (Evidence $e) => $e->created_at->toDateString())
            ->map(fn ($items, $date) => [
                'date' => $date,
                'label' => $items->first()->created_at->format('d.m.Y'),
                'files' => $items->map(fn (Evidence $e) => [
                    'id' => $e->id,
                    'title' => $e->title,
                    'type' => $e->type->value,
                    'url' => $e->publicUrl(),
                    'stage_title' => $e->stage?->title,
                    'period' => $e->period?->month->format('Y-m'),
                    'uploaded_at' => $e->created_at->format('Y-m-d H:i'),
                ])->values(),
            ])
            ->values();

        return Inertia::render('evidence/show', [
            'measure' => [
                'id' => $measure->id,
                'number' => $measure->number,
                'title' => $measure->title,
            ],
            'groups' => $groups,
            'canDelete' => $request->user()->hasAnyRole(['administrator', 'developer']),
        ]);
    }

    public function destroy(Evidence $evidence): RedirectResponse
    {
        Gate::authorize('delete', $evidence);

        if ($evidence->type->value === 'file') {
            Storage::disk('public')->delete($evidence->path_or_url);
        }

        $evidence->delete();

        return back()->with('status', 'Документ удалён.');
    }
}
