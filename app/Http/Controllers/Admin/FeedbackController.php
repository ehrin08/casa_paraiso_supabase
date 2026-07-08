<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $sentiment = (string) $request->query('sentiment_label');

        $feedback = Feedback::query()
            ->with(['customerProfile.user', 'service', 'appointment'])
            ->when(in_array($sentiment, Feedback::SENTIMENT_LABELS, true), fn ($query) => $query->where('sentiment_label', $sentiment))
            ->latest('submitted_at')
            ->paginate(12)
            ->withQueryString();

        return view('admin.feedback.index', [
            'feedback' => $feedback,
            'sentiment' => $sentiment,
            'summary' => [
                'positive' => Feedback::query()->where('sentiment_label', Feedback::SENTIMENT_POSITIVE)->count(),
                'neutral' => Feedback::query()->where('sentiment_label', Feedback::SENTIMENT_NEUTRAL)->count(),
                'negative' => Feedback::query()->where('sentiment_label', Feedback::SENTIMENT_NEGATIVE)->count(),
            ],
        ]);
    }

    public function show(Feedback $feedback): View
    {
        $feedback->load(['customerProfile.user', 'service', 'appointment']);

        return view('admin.feedback.show', [
            'feedback' => $feedback,
        ]);
    }
}
