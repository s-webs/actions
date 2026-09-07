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
    public function index(Request $request): Response
    {
        $query = Evidence::query()->with(['measure', 'stage', 'period']);

        $query
            ->when($request->filled('measure_id'), fn ($q) => $q->where('measure_id', $request->integer('measure_id')))
            ->when($request->filled('period_id'), fn ($q) => $q->where('period_id', $request->integer('period_id')))
            ->when($request->filled('form'), fn ($q) => $q->where('form', $request->string('form')));

        $evidences = $query->latest()->paginate(20)->withQueryString();

        $evidences->getCollection()->transform(fn (Evidence $e) => [
            'id' => $e->id,
            'title' => $e->title,
            'form' => $e->form,
            'type' => $e->type->value,
            'url' => $e->type->value === 'link' ? $e->path_or_url : Storage::disk('public')->url($e->path_or_url),
            'measure' => ['id' => $e->measure->id, 'number' => $e->measure->number, 'title' => $e->measure->title],
            'stage_title' => $e->stage?->title,
            'period' => $e->period?->month->format('Y-m'),
            'uploaded_at' => $e->created_at->format('Y-m-d H:i'),
        ]);

        return Inertia::render('evidence/index', [
            'evidences' => $evidences,
            'measures' => Measure::orderBy('number')->get(['id', 'number', 'title']),
            'filters' => $request->only(['measure_id', 'period_id', 'form']),
            'canDelete' => $request->user()->hasAnyRole(['coordinator', 'proctor']),
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
