<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Models\BeachTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BeachPromoCodeController extends Controller
{
    public function applyPromo(Request $request)
    {
        try {
            // UPDATE: Sesuaikan dengan format yang dikirim dari frontend
            $validated = $request->validate([
                'code' => 'required|string|max:50',
                'subtotal' => 'required|numeric|min:0',
                'ticket_id' => 'required|exists:beach_tickets,id',
                'visit_date' => 'required|date'
            ]);
            
            $searchCode = strtoupper(trim($validated['code']));
            
            Log::info('Frontend promo code request:', $validated);
            
            // Find promo code for beach tickets
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
            
            // Validate promo code for this specific request
            $validation = $promoCode->isValidForBeachTicket(
                $validated['ticket_id'], 
                $validated['subtotal'], 
                $validated['visit_date']
            );
            
            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $validation['message']
                ]);
            }
            
            $discount = $validation['discount'];
            $newTotal = $validated['subtotal'] - $discount;
            
            // Store promo code in session with updated format
            Session::put('applied_promo', [
                'code' => $promoCode->code,
                'discount' => $discount,
                'promo_id' => $promoCode->id
            ]);
            
            Log::info('Promo code applied successfully:', [
                'code' => $promoCode->code,
                'discount' => $discount,
                'new_total' => $newTotal
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Promo code applied successfully!',
                'discount' => $discount,
                'total' => $newTotal,
                'promo_code' => [
                    'code' => $promoCode->code,
                    'discount_type' => $promoCode->discount_type,
                    'discount_amount' => $promoCode->discount_value,
                    'max_discount' => $promoCode->max_discount
                ]
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error in promo code:', $e->errors());
            
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data. Please check your input and try again.'
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Error applying promo code:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while applying promo code. Please try again.'
            ], 500);
        }
    }
    
    public function removePromo(Request $request)
    {
        try {
            // Clear promo from session
            Session::forget('applied_promo');
            
            // Get checkout data to recalculate original total
            $checkoutData = Session::get('checkout_data');
            
            if (!$checkoutData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No checkout data found in session.'
                ]);
            }
            
            $ticketId = $checkoutData['ticket_id'];
            $quantity = $checkoutData['quantity'];
            
            $ticket = BeachTicket::findOrFail($ticketId);
            $originalTotal = $ticket->price * $quantity;
            
            Log::info('Promo code removed', [
                'original_total' => $originalTotal
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Promo code removed successfully.',
                'total' => $originalTotal,
                'discount' => 0
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error removing promo code:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while removing promo code.'
            ], 500);
        }
    }
}