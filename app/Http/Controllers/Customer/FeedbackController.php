<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\FeedbackRequest;
use App\Models\Appointment;
use App\Models\Feedback;
use App\Services\SentimentClassifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $customerProfile = $request->user()->customerProfile;

        $completedAppointments = Appointment::query()
            ->with(['service', 'feedback'])
            ->where('customer_profile_id', $customerProfile?->id ?? 0)
            ->where('status', Appointment::STATUS_COMPLETED)
            ->latest('completed_at')
            ->get();

        $feedback = Feedback::query()
            ->with(['service', 'appointment'])
            ->where('customer_profile_id', $customerProfile?->id ?? 0)
            ->latest('submitted_at')
            ->paginate(10);

        return view('customer.feedback.index', [
            'completedAppointments' => $completedAppointments,
            'feedback' => $feedback,
        ]);
    }

    public function create(Request $request): View
    {
        $customerProfile = $request->user()->customerProfile;
        $appointments = Appointment::query()
            ->with(['service', 'feedback'])
            ->where('customer_profile_id', $customerProfile?->id ?? 0)
            ->where('status', Appointment::STATUS_COMPLETED)
            ->whereDoesntHave('feedback')
            ->latest('completed_at')
            ->get();

        $selectedAppointmentId = $request->integer('appointment_id') ?: null;

        return view('customer.feedback.create', [
            'appointments' => $appointments,
            'selectedAppointmentId' => $selectedAppointmentId,
        ]);
    }

    public function store(FeedbackRequest $request, SentimentClassifier $classifier): RedirectResponse
    {
        $data = $request->validated();
        $customerProfile = $request->user()->customerProfile;
        $appointment = Appointment::query()
            ->with('feedback')
            ->where('customer_profile_id', $customerProfile?->id ?? 0)
            ->whereKey($data['appointment_id'])
            ->firstOrFail();

        if ($appointment->status !== Appointment::STATUS_COMPLETED) {
            throw ValidationException::withMessages(['appointment_id' => __('Feedback is available only for completed appointments.')]);
        }

        if ($appointment->feedback) {
            throw ValidationException::withMessages(['appointment_id' => __('Feedback was already submitted for this appointment.')]);
        }

        $sentiment = $classifier->classify((int) $data['rating'], $data['comment'] ?? null);

        Feedback::query()->create([
            'customer_profile_id' => $customerProfile->id,
            'appointment_id' => $appointment->id,
            'service_id' => $appointment->service_id,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'sentiment_label' => $sentiment['label'],
            'sentiment_score' => $sentiment['score'],
            'submitted_at' => now(),
        ]);

        return redirect()
            ->route('customer.feedback.index')
            ->with('status', 'feedback-submitted');
    }
}
