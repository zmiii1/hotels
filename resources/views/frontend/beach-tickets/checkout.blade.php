@extends('frontend.main')

@section('main')

<link rel="stylesheet" href="{{ asset('frontend/assets/css/beach-tickets.css') }}">
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="btc-container">
    <a href="{{ url()->previous() }}" class="btc-return-link">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
        Return to ticket
    </a>

    <div class="btc-row">
        <div class="btc-col btc-col-7">
            <div class="btc-form-container">
                <h3 class="btc-title">Customer Information</h3>
                <form action="{{ route('ticket-orders.store') }}" method="POST" id="checkoutForm">
                    @csrf
                    
                    {{-- TAMBAHAN BARU: Hidden inputs untuk promo --}}
                    <input type="hidden" name="promo_code" id="hiddenPromoCode" value="">
                    <input type="hidden" name="discount_amount" id="hiddenDiscountAmount" value="0">
                    
                    <div class="btc-form-group">
                        <input type="text" class="btc-form-control @error('customer_name') is-invalid @enderror" 
                               placeholder="Name" id="customerName" name="customer_name" value="{{ old('customer_name') }}" required>
                        @error('customer_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="btc-form-group">
                        <input type="tel" class="btc-form-control @error('customer_phone') is-invalid @enderror" 
                               placeholder="Phone Number" id="customerPhone" name="customer_phone" value="{{ old('customer_phone') }}" required>
                        @error('customer_phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="btc-form-group">
                        <input type="email" class="btc-form-control @error('customer_email') is-invalid @enderror" 
                               placeholder="Email Address" id="customerEmail" name="customer_email" value="{{ old('customer_email') }}" required>
                        @error('customer_email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="btc-payment-info">
                        <h3 class="btc-title">Payment Method</h3>
                        <p>You'll be redirected to Xendit's secure payment page to complete your payment.</p>
                        
                        <input type="hidden" name="payment_method" value="xendit">
                        
                        <div class="btc-payment-box">
                            <div class="btc-payment-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="5" width="18" height="14" rx="2"/>
                                    <path d="M3 10h18"/>
                                </svg>
                            </div>
                            <div class="btc-payment-text">
                                <strong>Xendit Payment Gateway</strong>
                                <span>Pay with credit card, bank transfer, e-wallet or QRIS</span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="btc-col btc-col-5">
            <div class="btc-summary">
                <h3 class="btc-summary-title">Order Summary</h3>
                <div class="btc-order-item">
                    <img src="{{ $ticket->image_url }}" class="btc-order-img" alt="{{ $ticket->name }}">
                    <div>
                        <h5>{{ $ticket->name }}</h5>
                        <p>{{ $ticket->formatted_price }}</p>
                        <p><strong>Benefits:</strong></p>
                        <ul class="btc-benefits-list">
                            @foreach($ticket->benefits as $benefit)
                                <li>{{ $benefit->benefit_name }}</li>
                            @endforeach
                        </ul>
                        <p class="small"><strong>Date of your visit:</strong> {{ \Carbon\Carbon::parse($visitDate)->format('d F Y') }}</p>
                        <p class="small"><strong>Quantity:</strong> {{ $quantity }}</p>
                    </div>
                </div>
                
                {{-- Promo Code Section --}}
                <div class="btc-promo-section">
                    <div class="btc-promo-input-group">
                        <input type="text" 
                               class="btc-form-control" 
                               id="promoCodeInput" 
                               placeholder="Enter promo code" 
                               maxlength="50">
                        <button type="button" 
                                class="btc-promo-btn" 
                                id="applyPromoBtn">
                            Apply
                        </button>
                    </div>
                    <div id="promoMessage" class="btc-promo-message" style="display: none;"></div>
                </div>
                
                <div class="btc-price-row">
                    <span>Subtotal</span>
                    <span id="subtotalAmount">Rp. {{ number_format($totalPrice, 0, ',', '.') }}</span>
                </div>
                
                {{-- Discount Row (hidden by default) --}}
                <div class="btc-price-row btc-discount-row" id="discountRow" style="display: none;">
                    <span>Discount</span>
                    <span id="discountAmount" class="btc-discount-text">- Rp. 0</span>
                </div>
                
                <div class="btc-total-row">
                    <span>Total</span>
                    <span id="totalAmount">Rp. {{ number_format($totalPrice, 0, ',', '.') }}</span>
                </div>
                
                <button class="btc-btn" onclick="document.getElementById('checkoutForm').submit()">
                    Process Payment
                </button>
            </div>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎫 Checkout page initialized - V3 FIXED');
    
    const checkoutForm = document.getElementById('checkoutForm');
    const promoCodeInput = document.getElementById('promoCodeInput');
    const applyPromoBtn = document.getElementById('applyPromoBtn');
    const promoMessage = document.getElementById('promoMessage');
    const subtotalAmount = document.getElementById('subtotalAmount');
    const discountRow = document.getElementById('discountRow');
    const discountAmount = document.getElementById('discountAmount');
    const totalAmount = document.getElementById('totalAmount');
    
    // TAMBAHAN BARU: Get hidden inputs
    const hiddenPromoCode = document.getElementById('hiddenPromoCode');
    const hiddenDiscountAmount = document.getElementById('hiddenDiscountAmount');
    
    // Debug: Check if all elements exist
    console.log('🔍 Elements check:', {
        checkoutForm: !!checkoutForm,
        promoCodeInput: !!promoCodeInput,
        applyPromoBtn: !!applyPromoBtn,
        hiddenPromoCode: !!hiddenPromoCode,
        hiddenDiscountAmount: !!hiddenDiscountAmount
    });
    
    // Original values
    const originalSubtotal = {{ $totalPrice }};
    let currentDiscount = 0;
    let appliedPromoCode = null;
    
    console.log('💰 Original subtotal:', originalSubtotal);
    
    // Prevent form submission if promo is being applied
    let isApplyingPromo = false;
    
    // Form validation
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(event) {
            if (isApplyingPromo) {
                event.preventDefault();
                console.log('⏳ Promo is being applied, preventing form submission');
                return false;
            }
            
            const customerName = document.getElementById('customerName');
            const customerPhone = document.getElementById('customerPhone');
            const customerEmail = document.getElementById('customerEmail');
            
            if (!customerName || !customerPhone || !customerEmail) {
                event.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            if (!customerName.value || !customerPhone.value || !customerEmail.value) {
                event.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            // PERBAIKAN: Data promo sudah ada di hidden inputs, tidak perlu tambah lagi
            console.log('📤 Submitting form with promo:', {
                promo_code: hiddenPromoCode.value,
                discount_amount: hiddenDiscountAmount.value
            });
        });
    }
    
    // Apply promo code
    if (applyPromoBtn) {
        applyPromoBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            console.log('🎯 Apply button clicked');
            
            const promoCode = promoCodeInput ? promoCodeInput.value.trim() : '';
            console.log('📝 Promo code:', promoCode);
            
            if (!promoCode) {
                showPromoError('Please enter a promo code');
                return;
            }
            
            if (isApplyingPromo) {
                console.log('⏳ Already applying promo, ignoring click');
                return;
            }
            
            // Set applying state
            isApplyingPromo = true;
            
            // Show loading state
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Applying...';
            
            // Reset previous discount
            resetDiscount();
            
            // Get CSRF token
            const csrfTokenElement = document.querySelector('meta[name="csrf-token"]');
            
            if (!csrfTokenElement) {
                showPromoError('Security token not found. Please refresh the page.');
                resetPromoButton();
                isApplyingPromo = false;
                return;
            }
            
            const csrfToken = csrfTokenElement.getAttribute('content');
            
            // Prepare request data
            const requestData = {
                code: promoCode,
                subtotal: originalSubtotal,
                ticket_id: {{ $ticket->id }},
                visit_date: '{{ $visitDate }}'
            };
            
            console.log('📤 Request data:', requestData);
            
            // AJAX request
            fetch('{{ route("beach-tickets.apply-promo") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(requestData)
            })
            .then(response => response.json())
            .then(data => {
                console.log('📨 Promo response:', data);
                
                if (data.success) {
                    // Apply discount
                    currentDiscount = parseFloat(data.discount) || 0;
                    appliedPromoCode = data.promo_code ? data.promo_code.code : promoCode;
                    
                    // PERBAIKAN UTAMA: Update hidden inputs immediately
                    hiddenPromoCode.value = appliedPromoCode;
                    hiddenDiscountAmount.value = currentDiscount;
                    
                    console.log('✅ Hidden inputs updated:', {
                        promo: hiddenPromoCode.value,
                        discount: hiddenDiscountAmount.value
                    });
                    
                    updatePricing();
                    showPromoSuccess(data.message || 'Promo code applied successfully!');
                    
                    // Update button state
                    applyPromoBtn.innerHTML = '<i class="bx bx-check me-1"></i>Applied';
                    applyPromoBtn.className = 'btc-promo-btn btc-promo-btn-success';
                    promoCodeInput.disabled = true;
                } else {
                    showPromoError(data.message || 'Invalid promo code');
                    resetPromoButton();
                }
            })
            .catch(error => {
                console.error('💥 Error:', error);
                showPromoError('Request failed. Please try again.');
                resetPromoButton();
            })
            .finally(() => {
                isApplyingPromo = false;
            });
        });
    }
    
    // Allow Enter key to apply promo
    if (promoCodeInput) {
        promoCodeInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (applyPromoBtn && !isApplyingPromo) {
                    applyPromoBtn.click();
                }
            }
        });
    }
    
    function updatePricing() {
        console.log('🔄 Updating pricing...');
        console.log('💰 Original subtotal:', originalSubtotal);
        console.log('💰 Current discount:', currentDiscount);
        
        const finalTotal = Math.max(0, originalSubtotal - currentDiscount);
        
        console.log('💰 Final total:', finalTotal);
        
        // Show discount row
        if (currentDiscount > 0) {
            if (discountRow) {
                discountRow.style.display = 'flex';
            }
            if (discountAmount) {
                discountAmount.textContent = '- Rp. ' + formatNumber(currentDiscount);
            }
            console.log('✅ Discount row shown');
        } else {
            if (discountRow) {
                discountRow.style.display = 'none';
            }
            console.log('❌ Discount row hidden');
        }
        
        // Update total
        if (totalAmount) {
            const newTotalText = 'Rp. ' + formatNumber(finalTotal);
            totalAmount.textContent = newTotalText;
            console.log('✅ Total updated:', newTotalText);
        }
    }
    
    function resetDiscount() {
        console.log('🧹 Resetting discount');
        currentDiscount = 0;
        appliedPromoCode = null;
        // PERBAIKAN: Reset hidden inputs juga
        hiddenPromoCode.value = '';
        hiddenDiscountAmount.value = '0';
        updatePricing();
        hidePromoMessage();
    }
    
    function resetPromoButton() {
        if (applyPromoBtn) {
            applyPromoBtn.disabled = false;
            applyPromoBtn.innerHTML = 'Apply';
            applyPromoBtn.className = 'btc-promo-btn';
        }
        if (promoCodeInput) {
            promoCodeInput.disabled = false;
        }
        console.log('🔄 Promo button reset');
    }
    
    function showPromoSuccess(message) {
        if (promoMessage) {
            promoMessage.textContent = message;
            promoMessage.className = 'btc-promo-message btc-promo-success';
            promoMessage.style.display = 'block';
        }
        console.log('✅ Success message shown:', message);
    }
    
    function showPromoError(message) {
        if (promoMessage) {
            promoMessage.textContent = message;
            promoMessage.className = 'btc-promo-message btc-promo-error';
            promoMessage.style.display = 'block';
        }
        console.log('❌ Error message shown:', message);
    }
    
    function hidePromoMessage() {
        if (promoMessage) {
            promoMessage.style.display = 'none';
        }
    }
    
    function formatNumber(number) {
        const formatted = new Intl.NumberFormat('id-ID').format(number);
        console.log('🔢 Formatting number:', number, '->', formatted);
        return formatted;
    }
    
    // Global debug function
    window.debugPromo = function() {
        console.log('🐛 PROMO DEBUG INFO:');
        console.log('Original subtotal:', originalSubtotal);
        console.log('Current discount:', currentDiscount);
        console.log('Applied promo code:', appliedPromoCode);
        console.log('Hidden promo code:', hiddenPromoCode.value);
        console.log('Hidden discount:', hiddenDiscountAmount.value);
        console.log('Is applying promo:', isApplyingPromo);
    };
});
</script>

<style>
/* Promo Code Styles */
.btc-promo-section {
    margin-bottom: 20px;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.btc-promo-input-group {
    display: flex;
    gap: 8px;
    margin-bottom: 10px;
}

.btc-promo-input-group .btc-form-control {
    flex: 1;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
}

.btc-promo-btn {
    padding: 10px 20px;
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 5px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 80px;
}

.btc-promo-btn:hover {
    background-color: #0056b3;
}

.btc-promo-btn:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
}

.btc-promo-btn-success {
    background-color: #28a745 !important;
}

.btc-promo-btn-success:hover {
    background-color: #218838 !important;
}

.btc-promo-message {
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
}

.btc-promo-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.btc-promo-error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.btc-discount-row {
    color: #28a745;
}

.btc-discount-text {
    font-weight: 600;
}

/* Spinner styles */
.spinner-border-sm {
    width: 1rem;
    height: 1rem;
    border-width: 0.125em;
}
</style>