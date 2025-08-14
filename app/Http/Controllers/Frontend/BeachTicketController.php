<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\BeachTicket;
use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BeachTicketController extends Controller
{
    public function index()
    {
        try {
            // Ambil semua tiket aktif berdasarkan kategori
            $lalassaRegular = BeachTicket::active()
                ->where('beach_name', 'lalassa')
                ->where('ticket_type', 'regular')
                ->orderBy('name')
                ->get();
                
            $lalassaBundling = BeachTicket::active()
                ->where('beach_name', 'lalassa')
                ->where('ticket_type', 'bundling')
                ->orderBy('name')
                ->get();
                
            $bodurRegular = BeachTicket::active()
                ->where('beach_name', 'bodur')
                ->where('ticket_type', 'regular')
                ->orderBy('name')
                ->get();
                
            $bodurBundling = BeachTicket::active()
                ->where('beach_name', 'bodur')
                ->where('ticket_type', 'bundling')
                ->orderBy('name')
                ->get();

            // Debug log
            Log::info('Beach tickets loaded', [
                'lalassa_regular_count' => $lalassaRegular->count(),
                'lalassa_bundling_count' => $lalassaBundling->count(),
                'bodur_regular_count' => $bodurRegular->count(),
                'bodur_bundling_count' => $bodurBundling->count(),
            ]);

            return view('frontend.beach-tickets.index', compact(
                'lalassaRegular', 
                'lalassaBundling', 
                'bodurRegular', 
                'bodurBundling'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading beach tickets: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return redirect()->back()->with('error', 'Unable to load beach tickets.');
        }
    }

    public function show($id)
    {
        try {
            $ticket = BeachTicket::with('benefits')->findOrFail($id);
            
            $relatedTickets = BeachTicket::active()
                ->where('id', '!=', $ticket->id)
                ->orderBy('beach_name')
                ->orderBy('ticket_type')
                ->get();
            
            Session::put('selected_ticket', $ticket->id);
            
            return view('frontend.beach-tickets.show', compact('ticket', 'relatedTickets'));
        } catch (\Exception $e) {
            Log::error('Error showing beach ticket: ' . $e->getMessage(), [
                'ticket_id' => $id,
            ]);
            
            return redirect()->route('beach-tickets.index')
                ->with('error', 'The requested ticket could not be found.');
        }
    }

    public function checkout(Request $request)
    {
        $ticketId = Session::get('selected_ticket');
        
        if (!$ticketId) {
            return redirect()->route('beach-tickets.index')
                ->with('error', 'No ticket selected. Please select a ticket first.');
        }
        
        try {
            $ticket = BeachTicket::with('benefits')->findOrFail($ticketId);
            
            $visitDate = $request->visit_date ?? now()->format('Y-m-d');
            $quantity = $request->quantity ?? 1;
            $additionalRequest = $request->additional_request ?? '';
            
            if ($quantity < 1) {
                return redirect()->back()
                    ->with('error', 'Quantity must be at least 1.');
            }
            
            $totalPrice = $ticket->price * $quantity;
            
            Session::put('checkout_data', [
                'ticket_id' => $ticket->id,
                'visit_date' => $visitDate,
                'quantity' => $quantity,
                'additional_request' => $additionalRequest,
                'total_price' => $totalPrice
            ]);
            
            return view('frontend.beach-tickets.checkout', compact(
                'ticket', 
                'visitDate', 
                'quantity', 
                'totalPrice'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading checkout page: ' . $e->getMessage(), [
                'ticket_id' => $ticketId,
            ]);
            
            return redirect()->route('beach-tickets.index')
                ->with('error', 'An error occurred during checkout. Please try again.');
        }
    }

    /**
     * Apply promo code for beach tickets (AJAX endpoint)
     */
    public function applyPromo(Request $request)
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string',
                'subtotal' => 'required|numeric|min:0',
                'ticket_id' => 'required|exists:beach_tickets,id',
                'visit_date' => 'required|date'
            ]);
            
            $searchCode = strtoupper(trim($validated['code']));
            
            Log::info('Frontend promo code request:', $validated);
            
            $promoCode = PromoCode::where('code', $searchCode)
                ->where('is_active', true)
                ->where('applies_to', 'beach_tickets')
                ->first();
                
            Log::info('Found promo code:', $promoCode ? $promoCode->toArray() : 'Not found');
                
            if (!$promoCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Promo code not found or not applicable to beach tickets'
                ]);
            }
            
            // Use visit_date for validation
            $visitDate = Carbon::parse($validated['visit_date'])->format('Y-m-d');
            $startDate = Carbon::parse($promoCode->start_date)->format('Y-m-d');
            $endDate = Carbon::parse($promoCode->end_date)->format('Y-m-d');
            
            Log::info('Date validation:', [
                'visit_date' => $visitDate,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'visit_date_is_valid' => ($visitDate >= $startDate && $visitDate <= $endDate)
            ]);
            
            // Check if visit date is within promo period
            if ($visitDate < $startDate || $visitDate > $endDate) {
                return response()->json([
                    'success' => false,
                    'message' => "Promo code is not valid for visit date {$visitDate}. Valid period: {$startDate} to {$endDate}"
                ]);
            }
            
            // Check usage limit
            if ($promoCode->max_uses && $promoCode->used_count >= $promoCode->max_uses) {
                return response()->json([
                    'success' => false,
                    'message' => 'This promo code has reached its usage limit'
                ]);
            }
            
            // Check minimum purchase
            if ($promoCode->min_purchase && $validated['subtotal'] < $promoCode->min_purchase) {
                return response()->json([
                    'success' => false,
                    'message' => 'Minimum purchase of Rp ' . number_format($promoCode->min_purchase, 0, ',', '.') . ' required'
                ]);
            }
            
            // Check specific ticket restrictions
            $specificTickets = $promoCode->beachTickets()->pluck('beach_tickets.id')->toArray();
            
            if (count($specificTickets) > 0 && !in_array($validated['ticket_id'], $specificTickets)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This promo code is not applicable to the selected ticket'
                ]);
            }
            
            // Calculate discount with max_discount
            $subtotal = $validated['subtotal'];
            $discount = 0;
            
            if ($promoCode->discount_type === 'percentage') {
                $percentageDiscount = ($subtotal * $promoCode->discount_value) / 100;
                
                // Apply max_discount cap if exists
                if ($promoCode->max_discount && $promoCode->max_discount > 0) {
                    $discount = min($percentageDiscount, $promoCode->max_discount);
                } else {
                    $discount = $percentageDiscount;
                }
            } else {
                $discount = min($promoCode->discount_value, $subtotal);
            }
            
            $discount = min($discount, $subtotal);
            $total = $subtotal - $discount;
            
            Log::info('Discount calculation:', [
                'subtotal' => $subtotal,
                'discount_type' => $promoCode->discount_type,
                'discount_value' => $promoCode->discount_value,
                'max_discount' => $promoCode->max_discount,
                'calculated_discount' => $discount,
                'final_total' => $total
            ]);
            
            // Store promo in session for order processing
            Session::put('applied_promo', [
                'code' => $promoCode->code,
                'discount' => $discount,
                'promo_id' => $promoCode->id
            ]);
            
            return response()->json([
                'success' => true,
                'discount' => $discount,
                'total' => $total,
                'message' => 'Promo code applied successfully!',
                'promo_code' => [
                    'code' => $promoCode->code,
                    'discount_type' => $promoCode->discount_type,
                    'discount_amount' => $promoCode->discount_value,
                    'max_discount' => $promoCode->max_discount
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Promo code error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }
}