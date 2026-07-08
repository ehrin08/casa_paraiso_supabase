<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\PromotionSuggestion;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use HandlesIndexSorting;

    public const TYPES = ['appointments', 'transactions', 'customers', 'promotions', 'feedback'];

    public function index(Request $request)
    {
        $type = $this->type($request);
        $records = $this->query($type, $request)->paginate(15)->withQueryString();

        return view('admin.reports.index', [
            'type' => $type,
            'types' => self::TYPES,
            'records' => $records,
            'summary' => $this->summary(),
            'filters' => $request->only(['q', 'date_from', 'date_to', 'status', 'payment_status', 'sentiment_label', 'sort', 'direction']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $type = $this->type($request);
        $records = $this->query($type, $request)->limit(5000)->get();
        $filename = 'casa-paraiso-'.$type.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($type, $records): void {
            $handle = fopen('php://output', 'w');

            foreach ($this->csvRows($type, $records) as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function type(Request $request): string
    {
        $type = (string) $request->query('type', 'appointments');

        return in_array($type, self::TYPES, true) ? $type : 'appointments';
    }

    private function query(string $type, Request $request): Builder
    {
        return match ($type) {
            'transactions' => $this->transactionQuery($request),
            'customers' => $this->customerQuery($request),
            'promotions' => $this->promotionQuery($request),
            'feedback' => $this->feedbackQuery($request),
            default => $this->appointmentQuery($request),
        };
    }

    private function appointmentQuery(Request $request): Builder
    {
        $sorts = [
            'number' => 'appointments.appointment_number',
            'customer' => 'report_appointment_customers.name',
            'service' => 'report_appointment_services.name',
            'schedule' => 'appointments.requested_start_at',
            'status' => 'appointments.status',
        ];
        $sort = $this->indexSort($request, $sorts, 'schedule');
        $direction = $this->indexDirection($request, 'desc');
        $search = trim((string) $request->query('q'));

        return Appointment::query()
            ->with(['customerProfile.user', 'service', 'staffProfile.user'])
            ->leftJoin('customer_profiles as report_appointment_customer_profiles', 'report_appointment_customer_profiles.id', '=', 'appointments.customer_profile_id')
            ->leftJoin('users as report_appointment_customers', 'report_appointment_customers.id', '=', 'report_appointment_customer_profiles.user_id')
            ->leftJoin('services as report_appointment_services', 'report_appointment_services.id', '=', 'appointments.service_id')
            ->select('appointments.*')
            ->when($request->filled('status'), fn ($query) => $query->where('appointments.status', $request->query('status')))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('appointments.appointment_number', 'like', "%{$search}%")
                    ->orWhere('report_appointment_customers.name', 'like', "%{$search}%")
                    ->orWhere('report_appointment_services.name', 'like', "%{$search}%");
            }))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('appointments.requested_start_at', '>=', Carbon::parse($request->query('date_from'))))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('appointments.requested_start_at', '<=', Carbon::parse($request->query('date_to'))))
            ->orderBy($sorts[$sort], $direction)
            ->orderByDesc('appointments.requested_start_at');
    }

    private function transactionQuery(Request $request): Builder
    {
        $sorts = [
            'number' => 'transactions.transaction_number',
            'customer' => 'report_transaction_customers.name',
            'service' => 'report_transaction_services.name',
            'amount' => 'transactions.amount',
            'status' => 'transactions.payment_status',
            'created' => 'transactions.created_at',
        ];
        $sort = $this->indexSort($request, $sorts, 'created');
        $direction = $this->indexDirection($request, 'desc');
        $search = trim((string) $request->query('q'));

        return Transaction::query()
            ->with(['customerProfile.user', 'service', 'appointment'])
            ->leftJoin('customer_profiles as report_transaction_customer_profiles', 'report_transaction_customer_profiles.id', '=', 'transactions.customer_profile_id')
            ->leftJoin('users as report_transaction_customers', 'report_transaction_customers.id', '=', 'report_transaction_customer_profiles.user_id')
            ->leftJoin('services as report_transaction_services', 'report_transaction_services.id', '=', 'transactions.service_id')
            ->select('transactions.*')
            ->when($request->filled('payment_status'), fn ($query) => $query->where('transactions.payment_status', $request->query('payment_status')))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('transactions.transaction_number', 'like', "%{$search}%")
                    ->orWhere('report_transaction_customers.name', 'like', "%{$search}%")
                    ->orWhere('report_transaction_services.name', 'like', "%{$search}%");
            }))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('transactions.created_at', '>=', Carbon::parse($request->query('date_from'))))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('transactions.created_at', '<=', Carbon::parse($request->query('date_to'))))
            ->orderBy($sorts[$sort], $direction)
            ->orderByDesc('transactions.created_at');
    }

    private function customerQuery(Request $request): Builder
    {
        $sorts = [
            'name' => 'users.name',
            'code' => 'customer_profiles.customer_code',
            'appointments' => 'appointments_count',
            'transactions' => 'transactions_count',
            'feedback' => 'feedback_count',
        ];
        $sort = $this->indexSort($request, $sorts, 'name');
        $direction = $this->indexDirection($request);
        $search = trim((string) $request->query('q'));

        return CustomerProfile::query()
            ->with('user')
            ->withCount(['appointments', 'transactions', 'feedback', 'promotionSuggestions'])
            ->join('users', 'users.id', '=', 'customer_profiles.user_id')
            ->select('customer_profiles.*')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('customer_profiles.customer_code', 'like', "%{$search}%");
            }))
            ->orderBy($sorts[$sort], $direction)
            ->orderBy('users.name');
    }

    private function promotionQuery(Request $request): Builder
    {
        $sorts = [
            'customer' => 'report_promotion_customers.name',
            'segment' => 'report_rfm_segments.name',
            'monetary' => 'promotion_suggestions.monetary_total',
            'status' => 'promotion_suggestions.status',
            'created' => 'promotion_suggestions.created_at',
        ];
        $sort = $this->indexSort($request, $sorts, 'created');
        $direction = $this->indexDirection($request, 'desc');
        $search = trim((string) $request->query('q'));

        return PromotionSuggestion::query()
            ->with(['customerProfile.user', 'rfmSegment'])
            ->leftJoin('customer_profiles as report_promotion_customer_profiles', 'report_promotion_customer_profiles.id', '=', 'promotion_suggestions.customer_profile_id')
            ->leftJoin('users as report_promotion_customers', 'report_promotion_customers.id', '=', 'report_promotion_customer_profiles.user_id')
            ->leftJoin('rfm_segments as report_rfm_segments', 'report_rfm_segments.id', '=', 'promotion_suggestions.rfm_segment_id')
            ->select('promotion_suggestions.*')
            ->when($request->filled('status'), fn ($query) => $query->where('promotion_suggestions.status', $request->query('status')))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('report_promotion_customers.name', 'like', "%{$search}%")
                    ->orWhere('report_rfm_segments.name', 'like', "%{$search}%")
                    ->orWhere('promotion_suggestions.suggested_offer', 'like', "%{$search}%");
            }))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('promotion_suggestions.created_at', '>=', Carbon::parse($request->query('date_from'))))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('promotion_suggestions.created_at', '<=', Carbon::parse($request->query('date_to'))))
            ->orderBy($sorts[$sort], $direction)
            ->orderByDesc('promotion_suggestions.created_at');
    }

    private function feedbackQuery(Request $request): Builder
    {
        $sorts = [
            'customer' => 'report_feedback_customers.name',
            'service' => 'report_feedback_services.name',
            'rating' => 'feedback.rating',
            'sentiment' => 'feedback.sentiment_label',
            'submitted' => 'feedback.submitted_at',
        ];
        $sort = $this->indexSort($request, $sorts, 'submitted');
        $direction = $this->indexDirection($request, 'desc');
        $search = trim((string) $request->query('q'));

        return Feedback::query()
            ->with(['customerProfile.user', 'service'])
            ->leftJoin('customer_profiles as report_feedback_customer_profiles', 'report_feedback_customer_profiles.id', '=', 'feedback.customer_profile_id')
            ->leftJoin('users as report_feedback_customers', 'report_feedback_customers.id', '=', 'report_feedback_customer_profiles.user_id')
            ->leftJoin('services as report_feedback_services', 'report_feedback_services.id', '=', 'feedback.service_id')
            ->select('feedback.*')
            ->when($request->filled('sentiment_label'), fn ($query) => $query->where('feedback.sentiment_label', $request->query('sentiment_label')))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('report_feedback_customers.name', 'like', "%{$search}%")
                    ->orWhere('report_feedback_services.name', 'like', "%{$search}%")
                    ->orWhere('feedback.comment', 'like', "%{$search}%");
            }))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('feedback.submitted_at', '>=', Carbon::parse($request->query('date_from'))))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('feedback.submitted_at', '<=', Carbon::parse($request->query('date_to'))))
            ->orderBy($sorts[$sort], $direction)
            ->orderByDesc('feedback.submitted_at');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(): array
    {
        return [
            'appointments' => Appointment::query()->count(),
            'revenue' => Transaction::query()->where('payment_status', Transaction::PAYMENT_PAID)->sum('amount'),
            'customers' => CustomerProfile::query()->count(),
            'feedback' => Feedback::query()->count(),
        ];
    }

    /**
     * @param Collection<int, mixed> $records
     * @return array<int, array<int, string|int|float|null>>
     */
    private function csvRows(string $type, Collection $records): array
    {
        $rows = [];

        if ($type === 'transactions') {
            $rows[] = ['Number', 'Customer', 'Service', 'Amount', 'Status', 'Method', 'Paid At'];
            foreach ($records as $record) {
                $rows[] = [$record->transaction_number, $record->customerProfile?->user?->name, $record->service?->name, $record->amount, $record->payment_status, $record->payment_method, $record->paid_at?->toDateTimeString()];
            }
        } elseif ($type === 'customers') {
            $rows[] = ['Code', 'Name', 'Email', 'Phone', 'Appointments', 'Transactions', 'Feedback'];
            foreach ($records as $record) {
                $rows[] = [$record->customer_code, $record->user?->name, $record->user?->email, $record->user?->phone, $record->appointments_count, $record->transactions_count, $record->feedback_count];
            }
        } elseif ($type === 'promotions') {
            $rows[] = ['Customer', 'Segment', 'Recency', 'Frequency', 'Monetary', 'Offer', 'Status'];
            foreach ($records as $record) {
                $rows[] = [$record->customerProfile?->user?->name, $record->rfmSegment?->name, $record->recency_days, $record->frequency_count, $record->monetary_total, $record->suggested_offer, $record->status];
            }
        } elseif ($type === 'feedback') {
            $rows[] = ['Customer', 'Service', 'Rating', 'Sentiment', 'Submitted At', 'Comment'];
            foreach ($records as $record) {
                $rows[] = [$record->customerProfile?->user?->name, $record->service?->name, $record->rating, $record->sentiment_label, $record->submitted_at?->toDateTimeString(), $record->comment];
            }
        } else {
            $rows[] = ['Number', 'Customer', 'Service', 'Staff', 'Requested', 'Scheduled', 'Status'];
            foreach ($records as $record) {
                $rows[] = [$record->appointment_number, $record->customerProfile?->user?->name, $record->service?->name, $record->staffProfile?->user?->name, $record->requested_start_at?->toDateTimeString(), $record->scheduled_start_at?->toDateTimeString(), $record->status];
            }
        }

        return $rows;
    }
}
