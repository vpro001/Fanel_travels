# Booking status rules

A booking's status only changes through one function per kind of booking. Each function refuses changes that aren't listed here, and makes the change in a single `UPDATE … WHERE status IN (…)`, so two processes can never both make the same change. Every change is written to `audit_log`.

## Flight bookings: `flight_status()` in `includes/booking.php`

```
pending_payment ──paid──▶ paid ──▶ ticketing ──▶ confirmed ──▶ cancellation_requested ──▶ cancelled ──▶ refunded
      │                    │           │             │                                      ▲
      │                    └───────────┴──▶ needs_attention ──▶ confirmed / cancelled / refunded / ticketing
      ├──▶ payment_failed ──▶ (pay again) pending_payment
      └──▶ expired
```

| New status | Allowed from | Who / when |
|---|---|---|
| `paid` | pending_payment, payment_failed, expired | `confirm_payment()` after Paystack verify says `success` with the right amount and currency. A late payment on an expired booking still counts as paid, then needs staff. |
| `payment_failed` | pending_payment | Paystack verify says `failed` or `abandoned`. |
| `expired` | pending_payment, payment_failed | The airline's price hold (`offer_expires_at`) passed without payment. |
| `ticketing` | paid, needs_attention | `issue_ticket()` starts the Duffel order. **This is the lock**: only the process that makes this change creates the order. |
| `confirmed` | ticketing, needs_attention | Duffel returned a booking reference (PNR). |
| `needs_attention` | paid, ticketing | Anything that stops the ticket after payment: price rose, fare gone, order failed, amount mismatch, unclear airline answer. Admin is emailed. |
| `cancellation_requested` | confirmed | Customer asks to cancel (Phase 6). |
| `cancelled` | confirmed, cancellation_requested, needs_attention | Staff. |
| `refunded` | cancelled, needs_attention | Staff, after the refund is sent. |

## Payments: `payments` table

`initialized` → `success` | `failed` | `abandoned`. Each payment has a unique reference (`FNF-XXXXXXXX-xxxxxx`). `fulfilled_at` is set once, inside the same transaction that locks the row (`SELECT … FOR UPDATE`). That makes the callback page and the webhook safe to run in any order, any number of times.

## Why a paid booking is never lost

1. The booking and payment rows are saved **before** the customer goes to Paystack.
2. Three independent paths confirm the payment: the customer's return to `payment-callback.php`, the Paystack webhook, and the booking page itself (it asks Paystack again when a booking is still waiting).
3. If the airline order fails after payment, the booking goes to `needs_attention`, the admin is emailed, and the customer is told a person will contact them. The Duffel order is **never retried automatically**, because Duffel's docs warn that a retried order can create a second booking.

## Hotel bookings: `hotel_status()` in `includes/stays.php`

Same shape as flights, with `booking` in place of `ticketing`:
`pending_payment → paid → booking → confirmed`, with `payment_failed`, `expired`, `needs_attention`, `cancellation_requested`, `cancelled` and `refunded` allowed from the same statuses as flights. `book_hotel()` only runs for the process that moves the booking from `paid` to `booking`.

## Tour bookings (`includes/tours.php`, `admin/tour-bookings.php`)

`pending_payment → deposit_paid → paid → confirmed`, or `pending_payment → paid` when paid in full. Also `payment_failed`, `cancellation_requested`, `cancelled` (seats returned) and `refunded`. Seats are reserved on the first successful payment, only if enough are left.

## Visa applications (`includes/visa.php`, `admin/visa.php`, `visa-track.php`)

`submitted → under_review → documents_requested → processing → approved / refused → completed`

- `submitted` can also go straight to `documents_requested`. `documents_requested` returns to `under_review` automatically when the customer has uploaded every required document that was missing or sent back.
- Every change goes into `visa_status_history` (public notes show on the tracking page) and emails the customer. Updates that don't change the status (appointment booked, fee paid, file shared) are history rows with `from_status = to_status`.

**Document checklist** (`visa_checklist`, one row per document per application, copied from the destination's `checklist` when the customer applies; lines starting with `*` are required at application time):
`missing → received → accepted`, or `received → rejected → received` when staff send it back with a reason. Staff can add items or make an optional item required; "Ask the customer for documents" emails everything that's outstanding.

**Service fee** (`fee_status` on the application): `unpaid → paid` (Paystack via `confirm_payment()`, or recorded by staff for transfer / cash / POS) or `unpaid → waived`. `visa_fee_paid()` only changes an `unpaid` row, so the webhook and the return page can't record it twice. Customers can pay online once the application has left `submitted` and the fee is above ₦0.
