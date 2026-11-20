<?php

namespace App\Http\Controllers\Api\Subscription;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    protected TenantContext $tenantContext;

    public function __construct(TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    /**
     * Get all active subscription plans.
     */
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::with('features')
            ->where('is_active', true)
            ->orderBy('monthly_price', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    /**
     * Get the current subscription and usage metrics for the active tenant.
     */
    public function current(): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId() ?: auth()->user()->memberships()->value('tenant_id');
        $tenant = Tenant::findOrFail($tenantId);

        $subscription = Subscription::with('plan.features')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->first();

        // Count current usage metrics
        $studentCount = DB::table('students')->where('tenant_id', $tenantId)->whereNull('deleted_at')->count();
        $staffCount = DB::table('employees')->where('tenant_id', $tenantId)->whereNull('deleted_at')->count();
        $branchCount = DB::table('school_branches')->where('tenant_id', $tenantId)->whereNull('deleted_at')->count();

        $daysRemaining = null;
        $isExpired = false;

        if ($subscription && $subscription->current_period_end) {
            $daysRemaining = (int) ceil(now()->diffInDays($subscription->current_period_end, false));
            $isExpired = $daysRemaining < 0;
        } elseif ($tenant->trial_ends_at) {
            $daysRemaining = (int) ceil(now()->diffInDays($tenant->trial_ends_at, false));
            $isExpired = $daysRemaining < 0;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'status' => $tenant->status,
                    'trial_ends_at' => $tenant->trial_ends_at,
                ],
                'subscription' => $subscription,
                'days_remaining' => max(0, $daysRemaining ?? 0),
                'is_expired' => $isExpired,
                'usage' => [
                    'students' => $studentCount,
                    'staff' => $staffCount,
                    'branches' => $branchCount,
                    'limits' => $subscription?->plan ? [
                        'max_students' => $subscription->plan->max_students,
                        'max_staff' => $subscription->plan->max_staff,
                        'max_branches' => $subscription->plan->max_branches,
                        'storage_limit_gb' => $subscription->plan->storage_limit_gb,
                    ] : null,
                ],
            ],
        ]);
    }

    /**
     * Subscribe or renew a subscription plan.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:subscription_plans,id'],
            'billing_cycle' => ['required', 'in:monthly,annual'],
            'payment_method' => ['nullable', 'string', 'in:card,upi,bank_transfer,mock_gateway'],
        ]);

        $tenantId = $this->tenantContext->getTenantId() ?: auth()->user()->memberships()->value('tenant_id');
        $tenant = Tenant::findOrFail($tenantId);
        $plan = SubscriptionPlan::findOrFail($validated['plan_id']);

        $amount = $validated['billing_cycle'] === 'annual' ? $plan->annual_price : $plan->monthly_price;
        $periodStart = now();
        $periodEnd = $validated['billing_cycle'] === 'annual' ? now()->addYear() : now()->addMonth();
        $gracePeriodEnd = (clone $periodEnd)->addDays(7);

        return DB::transaction(function () use ($tenant, $plan, $validated, $amount, $periodStart, $periodEnd, $gracePeriodEnd) {
            // Find or create subscription
            $subscription = Subscription::firstOrNew(['tenant_id' => $tenant->id]);
            $subscription->fill([
                'plan_id' => $plan->id,
                'billing_cycle' => $validated['billing_cycle'],
                'status' => 'active',
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'grace_period_ends_at' => $gracePeriodEnd,
                'cancelled_at' => null,
            ]);
            $subscription->save();

            // Generate Invoice
            $invoiceNumber = 'INV-' . strtoupper(date('Ym')) . '-' . strtoupper(Str::random(6));
            $invoice = SubscriptionInvoice::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'invoice_number' => $invoiceNumber,
                'subtotal' => $amount,
                'tax_amount' => 0.00,
                'total_amount' => $amount,
                'currency' => $plan->currency ?? 'INR',
                'status' => 'paid',
                'due_date' => now()->toDateString(),
                'paid_at' => now(),
            ]);

            // Record Payment
            SubscriptionPayment::create([
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'transaction_reference' => 'TXN-' . strtoupper(Str::random(12)),
                'amount' => $amount,
                'payment_method' => $validated['payment_method'] ?? 'card',
                'gateway' => 'internal_checkout',
                'gateway_response' => ['status' => 'success', 'timestamp' => now()->toIso8601String()],
                'status' => 'success',
                'paid_at' => now(),
            ]);

            // Reactivate tenant if suspended or trial
            if ($tenant->status !== 'active') {
                $tenant->update([
                    'status' => 'active',
                    'suspended_at' => null,
                    'suspension_reason' => null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Subscription activated successfully.',
                'data' => [
                    'subscription' => $subscription->fresh(['plan']),
                    'invoice' => $invoice,
                ],
            ], 200);
        });
    }

    /**
     * Cancel an active subscription.
     */
    public function cancel(Request $request): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId() ?: auth()->user()->memberships()->value('tenant_id');

        $subscription = Subscription::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->firstOrFail();

        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled. Access remains active until period end.',
            'data' => $subscription,
        ]);
    }

    /**
     * Get paginated invoice history for the current tenant.
     */
    public function invoices(Request $request): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId() ?: auth()->user()->memberships()->value('tenant_id');

        $invoices = SubscriptionInvoice::with('payments')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $invoices->items(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }
}

