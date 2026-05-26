<?php
// app/Modules/Subscriptions/Controllers/PaymentController.php

namespace App\Modules\Subscriptions\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\Subscriptions\Models\CompanyPaymentMethod;
use App\Modules\Subscriptions\Services\PaymentGatewayService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    protected $paymentGateway;

    public function __construct(PaymentGatewayService $paymentGateway)
    {
        $this->paymentGateway = $paymentGateway;
    }

    public function index(Company $company)
    {
        $paymentMethods = $company->paymentMethods()->latest()->get();
        return view('subscriptions::payment-methods.index', compact('company', 'paymentMethods'));
    }

    public function create(Company $company)
    {
        return view('subscriptions::payment-methods.create', compact('company'));
    }

    public function store(Request $request, Company $company)
    {
        $request->validate([
            'payment_method_nonce' => 'required|string',
            'set_as_default' => 'boolean'
        ]);

        try {
            // Get payment method details from gateway
            // $paymentMethodDetails = $this->paymentGateway->getPaymentMethod($request->payment_method_nonce);
            
            // For demo purposes
            $paymentMethod = CompanyPaymentMethod::create([
                'company_id' => $company->id,
                'type' => 'card',
                'last_four' => '4242',
                'expiry_month' => '12',
                'expiry_year' => '2026',
                'payment_provider' => 'stripe',
                'provider_customer_id' => 'cus_' . uniqid(),
                'provider_payment_method_id' => 'pm_' . uniqid(),
                'is_default' => $request->boolean('set_as_default', false)
            ]);

            if ($paymentMethod->is_default) {
                $paymentMethod->setAsDefault();
            }

            return redirect()->route('subscriptions.payment-methods.index', $company)
                ->with('success', 'Payment method added successfully!');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to add payment method: ' . $e->getMessage());
        }
    }

    public function setDefault(CompanyPaymentMethod $paymentMethod)
    {
        $paymentMethod->setAsDefault();
        
        return back()->with('success', 'Default payment method updated!');
    }

    public function destroy(CompanyPaymentMethod $paymentMethod)
    {
        if ($paymentMethod->is_default) {
            return back()->with('error', 'Cannot delete default payment method');
        }
        
        $paymentMethod->delete();
        
        return back()->with('success', 'Payment method removed successfully!');
    }
}