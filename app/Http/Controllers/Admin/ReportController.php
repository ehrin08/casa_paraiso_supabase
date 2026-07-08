<?php

namespace App\Http\Controllers\Admin;

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
            'filters' => $request->only(['date_from', 'date_to', 'status', 'payment_status', 'sentiment_label']),
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
            'customers' => $this->customerQuery(),
            'promotions' => $this->promotionQuery($request),
            'feedback' => $this->feedbackQuery($request),
            default => $this->appointmentQuery($request),
        };
    }

    private function appointmentQuery(Request $request): Builder
    {
        return Appointment::query()
            ->with(['customerProfile.user', 'service', 'staffProfile.user'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('requested_start_at', '>=', Carbon::parse($request->query('date_from'))))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('requested_start_at', '<=', Carbon::parse($request->query('date_to'))))
            ->latest('requested_start_at');
    }

    private function transactionQuery(Request $request): Builder
    {
        return Transaction::query()
            ->with(['customerProfile.user', 'service', 'appointment'])
            ->when($request->filled('payment_status'), fn ($query) => $query->where('payment_status', $request->query('payment_status')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', Carbon::parse($request->query('date_from'))))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', Carbon::parse($request->query('date_to'))))
            ->latest();
    }

    private function customerQuery(): Builder
    {
        return CustomerProfile::query()
            ->with('user')
            ->withCount(['appointments', 'transactions', 'feedback', 'promotionSuggestions'])
            ->join('users', 'users.id', '=', 'customer_profiles.user_id')
            ->select('customer_profiles.*')
            ->orderBy('users.name');
    }

    private function promotionQuery(Request $request): Builder
    {
        return PromotionSuggestion::query()
            ->with(['customerProfile.user', 'rfmSegment'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', Carbon::parse($request->query('date_from'))))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', Carbon::parse($request->query('date_to'))))
            ->latest();
    }

    private function feedbackQuery(Request $request): Builder
    {
        return Feedback::query()
            ->with(['customerProfile.user', 'service'])
            ->when($request->filled('sentiment_label'), fn ($query) => $query->where('sentiment_label', $request->query('sentiment_label')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('submitted_at', '>=', Carbon::parse($request->query('date_from'))))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('submitted_at', '<=', Carbon::parse($request->query('date_to'))))
            ->latest('submitted_at');
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
