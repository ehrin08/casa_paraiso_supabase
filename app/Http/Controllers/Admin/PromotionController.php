<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromotionSuggestionStatusRequest;
use App\Models\PromotionRule;
use App\Models\PromotionSuggestion;
use App\Models\RfmSegment;
use App\Services\RfmPromotionGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status');

        $suggestions = PromotionSuggestion::query()
            ->with(['customerProfile.user', 'rfmSegment', 'promotionRule', 'reviewer'])
            ->when(in_array($status, PromotionSuggestion::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.promotions.index', [
            'suggestions' => $suggestions,
            'segments' => RfmSegment::query()->withCount(['promotionRules', 'promotionSuggestions'])->orderBy('name')->get(),
            'rules' => PromotionRule::query()->with('rfmSegment')->orderBy('name')->get(),
            'status' => $status,
            'summary' => [
                'suggested' => PromotionSuggestion::query()->where('status', PromotionSuggestion::STATUS_SUGGESTED)->count(),
                'applied' => PromotionSuggestion::query()->where('status', PromotionSuggestion::STATUS_APPLIED)->count(),
                'dismissed' => PromotionSuggestion::query()->where('status', PromotionSuggestion::STATUS_DISMISSED)->count(),
            ],
        ]);
    }

    public function generate(RfmPromotionGenerator $generator): RedirectResponse
    {
        $created = $generator->generate();

        return redirect()
            ->route('admin.promotions.index')
            ->with('status', 'promotions-generated')
            ->with('generated_count', $created->count());
    }

    public function show(PromotionSuggestion $promotion): View
    {
        $promotion->load(['customerProfile.user', 'rfmSegment', 'promotionRule', 'reviewer']);

        return view('admin.promotions.show', [
            'suggestion' => $promotion,
        ]);
    }

    public function update(PromotionSuggestionStatusRequest $request, PromotionSuggestion $promotion): RedirectResponse
    {
        $data = $request->validated();
        $status = $data['status'];

        $promotion->fill([
            'status' => $status,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => $promotion->reviewed_at ?: now(),
            'notes' => $data['notes'] ?? $promotion->notes,
        ]);

        if ($status === PromotionSuggestion::STATUS_APPLIED) {
            $promotion->applied_at = now();
            $promotion->dismissed_at = null;
        } elseif ($status === PromotionSuggestion::STATUS_DISMISSED) {
            $promotion->dismissed_at = now();
            $promotion->applied_at = null;
        }

        $promotion->save();

        return redirect()
            ->route('admin.promotions.show', $promotion)
            ->with('status', 'promotion-updated');
    }
}
