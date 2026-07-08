<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionRequest;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\Transaction;
use App\Services\TransactionNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class TransactionController extends Controller
{
    use HandlesIndexSorting;

    public function index(Request $request): View
    {
        $status = (string) $request->query('payment_status');
        $search = trim((string) $request->query('q'));
        $sorts = [
            'number' => 'transactions.transaction_number',
            'customer' => 'transaction_customers.name',
            'service' => 'transaction_services.name',
            'amount' => 'transactions.amount',
            'status' => 'transactions.payment_status',
            'paid_at' => 'transactions.paid_at',
            'created' => 'transactions.created_at',
        ];
        $sort = $this->indexSort($request, $sorts, 'created');
        $direction = $this->indexDirection($request, 'desc');

        $transactions = Transaction::query()
            ->with(['customerProfile.user', 'service', 'appointment', 'recorder'])
            ->leftJoin('customer_profiles as transaction_customer_profiles', 'transaction_customer_profiles.id', '=', 'transactions.customer_profile_id')
            ->leftJoin('users as transaction_customers', 'transaction_customers.id', '=', 'transaction_customer_profiles.user_id')
            ->leftJoin('services as transaction_services', 'transaction_services.id', '=', 'transactions.service_id')
            ->select('transactions.*')
            ->when(in_array($status, Transaction::PAYMENT_STATUSES, true), fn ($query) => $query->where('transactions.payment_status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('transactions.transaction_number', 'like', "%{$search}%")
                        ->orWhere('transaction_customers.name', 'like', "%{$search}%")
                        ->orWhere('transaction_services.name', 'like', "%{$search}%");
                });
            })
            ->orderBy($sorts[$sort], $direction)
            ->orderByDesc('transactions.created_at')
            ->paginate(12)
            ->withQueryString();

        $formData = $this->formData(new Transaction([
            'payment_status' => Transaction::PAYMENT_PAID,
            'payment_method' => Transaction::METHOD_CASH,
            'paid_at' => now(),
        ]));

        return view('admin.transactions.index', [
            'transactions' => $transactions,
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'summary' => [
                'paid' => Transaction::query()->where('payment_status', Transaction::PAYMENT_PAID)->sum('amount'),
                'unpaid' => Transaction::query()->whereIn('payment_status', [Transaction::PAYMENT_UNPAID, Transaction::PAYMENT_PARTIAL])->sum('amount'),
                'count' => Transaction::query()->count(),
            ],
            ...$formData,
        ]);
    }

    public function create(Request $request): View
    {
        $transaction = new Transaction([
            'payment_status' => Transaction::PAYMENT_PAID,
            'payment_method' => Transaction::METHOD_CASH,
            'paid_at' => now(),
        ]);

        if ($request->integer('appointment_id')) {
            $appointment = Appointment::query()->with(['service', 'customerProfile.user'])->find($request->integer('appointment_id'));

            if ($appointment) {
                $transaction->appointment_id = $appointment->id;
                $transaction->customer_profile_id = $appointment->customer_profile_id;
                $transaction->service_id = $appointment->service_id;
                $transaction->amount = $appointment->service?->price;
            }
        }

        return view('admin.transactions.create', $this->formData($transaction));
    }

    public function store(TransactionRequest $request, TransactionNumber $numbers): RedirectResponse
    {
        $transaction = $this->persistTransaction(new Transaction, $request, $numbers);

        return redirect()
            ->route('admin.transactions.show', $transaction)
            ->with('status', 'transaction-created');
    }

    public function show(Transaction $transaction): View
    {
        $transaction->load(['customerProfile.user', 'service', 'appointment', 'recorder']);

        return view('admin.transactions.show', [
            'transaction' => $transaction,
        ]);
    }

    public function edit(Transaction $transaction): View
    {
        return view('admin.transactions.edit', $this->formData($transaction));
    }

    public function update(TransactionRequest $request, Transaction $transaction, TransactionNumber $numbers): RedirectResponse
    {
        $this->persistTransaction($transaction, $request, $numbers);

        return redirect()
            ->route('admin.transactions.show', $transaction)
            ->with('status', 'transaction-updated');
    }

    private function persistTransaction(Transaction $transaction, TransactionRequest $request, TransactionNumber $numbers): Transaction
    {
        $data = $request->validated();

        if (! empty($data['appointment_id'])) {
            $appointment = Appointment::query()->findOrFail($data['appointment_id']);
            $data['customer_profile_id'] = $appointment->customer_profile_id;
            $data['service_id'] = $appointment->service_id;
        }

        $transaction->fill([
            'transaction_number' => $transaction->transaction_number ?: $numbers->next(),
            'customer_profile_id' => $data['customer_profile_id'],
            'appointment_id' => $data['appointment_id'] ?? null,
            'service_id' => $data['service_id'] ?? null,
            'amount' => $data['amount'],
            'payment_status' => $data['payment_status'],
            'payment_method' => $data['payment_method'] ?? null,
            'paid_at' => ! empty($data['paid_at'])
                ? Carbon::parse($data['paid_at'])
                : ($data['payment_status'] === Transaction::PAYMENT_PAID ? now() : null),
            'recorded_by' => $transaction->recorded_by ?: $request->user()->id,
            'notes' => $data['notes'] ?? null,
        ])->save();

        return $transaction;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Transaction $transaction): array
    {
        return [
            'transaction' => $transaction,
            'customers' => CustomerProfile::query()->with('user')->get()->sortBy('user.name'),
            'services' => Service::query()->orderBy('name')->get(),
            'appointments' => Appointment::query()
                ->with(['customerProfile.user', 'service'])
                ->whereIn('status', [Appointment::STATUS_CONFIRMED, Appointment::STATUS_COMPLETED])
                ->latest('scheduled_start_at')
                ->get(),
        ];
    }
}
