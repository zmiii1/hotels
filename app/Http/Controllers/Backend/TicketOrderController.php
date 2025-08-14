<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\TicketOrder;
use App\Models\BeachTicket;
use App\Models\PromoCode; // ✅ TAMBAH IMPORT INI
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TicketOrderController extends Controller
{
    /**
     * Display orders list with filters
     */
    public function index(Request $request)
    {
        $query = TicketOrder::with(['ticket', 'cashier'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        $query = $this->applyFilters($query, $request);

        $orders = $query->paginate(25);
        
        // Pass filters to view for maintaining state
        $filters = $request->only(['payment_method', 'order_type', 'date_from', 'date_to', 'search']);
        
        return view('backend.beach-tickets.orders.index', compact('orders', 'filters'));
    }
    
    /**
     * Show order details
     */
    public function show($orderCode)
    {
        $order = TicketOrder::with(['ticket' => function($query) {
            $query->with('benefits');
        }, 'cashier', 'promoCode'])
            ->where('order_code', $orderCode)
            ->firstOrFail();
            
        return view('backend.beach-tickets.orders.show', compact('order'));
    }
    
    /**
     * Create manual order form (Admin only)
     */
    public function create()
    {
        $tickets = BeachTicket::active()->get();
        return view('backend.beach-tickets.orders.create', compact('tickets'));
    }
    
    /**
     * Store manual order (Admin only)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'beach_ticket_id' => 'required|exists:beach_tickets,id',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'visit_date' => 'required|date',
            'quantity' => 'required|integer|min:1',
            'additional_request' => 'nullable|string',
            'payment_method' => 'required|string|in:cash,card',
            'payment_status' => 'required|string|in:paid,pending'
        ]);
        
        // Get ticket
        $ticket = BeachTicket::findOrFail($validated['beach_ticket_id']);
        
        // Calculate total price
        $totalPrice = $ticket->price * $validated['quantity'];
        
        // Create order
        $order = TicketOrder::create([
            'order_code' => $this->generateOrderCode(),
            'beach_ticket_id' => $validated['beach_ticket_id'],
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'],
            'customer_phone' => $validated['customer_phone'] ?? null,
            'visit_date' => $validated['visit_date'],
            'quantity' => $validated['quantity'],
            'additional_request' => $validated['additional_request'] ?? null,
            'subtotal' => $totalPrice,
            'total_price' => $totalPrice,
            'payment_method' => $validated['payment_method'],
            'payment_status' => $validated['payment_status'],
            'paid_at' => $validated['payment_status'] === 'paid' ? now() : null,
            'cashier_id' => Auth::id(),
            'is_offline_order' => true
        ]);
        
        return redirect()->route('backend.beach-tickets.orders.show', ['order_code' => $order->order_code])
            ->with('success', 'Order created successfully.');
    }
    
    /**
     * Print receipt for any order
     */
    public function printReceipt($orderCode)
    {
        $order = TicketOrder::with(['ticket' => function($query) {
            $query->with('benefits');
        }, 'cashier', 'promoCode'])
            ->where('order_code', $orderCode)
            ->firstOrFail();
        
        return view('backend.beach-tickets.orders.print_receipt', compact('order'));
    }
    
    /**
     * ✅ UPDATED: Mark an order as paid with promo code increment support
     */
    public function markAsPaid($id)
    {
        try {
            $order = TicketOrder::with('promoCode')->findOrFail($id);
            
            if ($order->payment_status === 'paid') {
                return redirect()->back()->with('info', 'Order is already paid.');
            }
            
            // Update order status
            $order->update([
                'payment_status' => 'paid',
                'paid_at' => now()
            ]);
            
            // ✅ INCREMENT PROMO CODE USAGE
            $this->incrementPromoCodeUsage($order);
            
            Log::info('Backend: Order marked as paid', [
                'order_code' => $order->order_code,
                'marked_by' => Auth::id()
            ]);
            
            return redirect()->back()->with('success', 'Order marked as paid successfully.');
            
        } catch (\Exception $e) {
            Log::error('Error marking order as paid: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error marking order as paid. Please try again.');
        }
    }

    public function destroy($id)
    {
        try {
            $order = TicketOrder::findOrFail($id);
            
            // Allow deletion with confirmation, but warn about paid orders
            if ($order->payment_status === 'paid') {
                // Still allow deletion but with stronger warning (handled in frontend confirmation)
                Log::warning("Deleting paid order: {$order->order_code} by user " . Auth::id());
            }
            
            $orderCode = $order->order_code;
            $order->delete();
            
            return redirect()->back()->with('success', "Order #{$orderCode} deleted successfully.");
            
        } catch (\Exception $e) {
            Log::error('Failed to delete order: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete order: ' . $e->getMessage());
        }
    }

    private function incrementPromoCodeUsage($order)
    {
        // Only increment if order has promo code and discount
        if ($order->promo_code_id && $order->discount > 0) {
            Log::info('🎟️ BACKEND: Found promo code to increment', [
                'order_code' => $order->order_code,
                'promo_code_id' => $order->promo_code_id,
                'discount' => $order->discount
            ]);
            
            $promoCode = PromoCode::find($order->promo_code_id);
            if ($promoCode) {
                $oldUsedCount = $promoCode->used_count;
                $promoCode->increment('used_count');
                $newUsedCount = $promoCode->fresh()->used_count;
                
                Log::info('✅ BACKEND: Promo code usage incremented', [
                    'order_code' => $order->order_code,
                    'promo_code' => $promoCode->code,
                    'old_used_count' => $oldUsedCount,
                    'new_used_count' => $newUsedCount,
                    'increment_success' => $newUsedCount > $oldUsedCount,
                    'marked_by' => Auth::id()
                ]);
            } else {
                Log::error('❌ BACKEND: Promo code not found', [
                    'order_code' => $order->order_code,
                    'promo_code_id' => $order->promo_code_id
                ]);
            }
        } else {
            Log::info('⚠️ BACKEND: No promo code to increment', [
                'order_code' => $order->order_code,
                'promo_code_id' => $order->promo_code_id,
                'discount' => $order->discount,
                'has_promo_id' => !!$order->promo_code_id,
                'has_discount' => $order->discount > 0
            ]);
        }
    }
    
    /**
     * Generate unique order code
     */
    private function generateOrderCode()
    {
        $prefix = 'TIX-';
        $code = strtoupper(substr(uniqid(), -7));
        
        // Check if code already exists
        $exists = TicketOrder::where('order_code', $prefix . $code)->exists();
        
        if ($exists) {
            return $this->generateOrderCode();
        }
        
        return $prefix . $code;
    }
    
    /**
     * Apply filters to orders query
     */
    private function applyFilters($query, $request)
    {
        // Filter by payment method
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter by order type
        if ($request->filled('order_type')) {
            if ($request->order_type === 'offline') {
                $query->where('is_offline_order', true);
            } elseif ($request->order_type === 'online') {
                $query->where('is_offline_order', false);
            }
        }

        // Filter by visit date range
        if ($request->filled('date_from')) {
            $query->whereDate('visit_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('visit_date', '<=', $request->date_to);
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('order_code', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        return $query;
    }
}