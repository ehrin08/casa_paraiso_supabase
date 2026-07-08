<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $staffProfile = $request->user()->staffProfile;

        $feedback = Feedback::query()
            ->with(['customerProfile.user', 'service', 'appointment'])
            ->whereHas('appointment', fn ($query) => $query->where('staff_profile_id', $staffProfile?->id ?? 0))
            ->latest('submitted_at')
            ->paginate(12);

        return view('staff.feedback.index', [
            'feedback' => $feedback,
        ]);
    }

    public function show(Request $request, Feedback $feedback): View
    {
        $feedback->load(['customerProfile.user', 'service', 'appointment']);

        abort_unless((int) $feedback->appointment?->staff_profile_id === (int) ($request->user()->staffProfile?->id ?? 0), 403);

        return view('staff.feedback.show', [
            'feedback' => $feedback,
        ]);
    }
}
