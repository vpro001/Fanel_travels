# Flights through Galileo (Travelport)

Flights are searched, booked and ticketed automatically on Fanel's own Galileo (Travelport+, 1G) office
through the **Travelport Universal API** (SOAP/XML). Code: `includes/galileo.php`, chosen by
`FLIGHT_SUPPLIER = 'galileo'` in `config.php` (`includes/flights.php` picks the supplier).

Hotels still use Duffel Stays.

## How the money moves

1. The customer pays Fanel in naira by Paystack. The price is the Galileo fare + Fanel's service fee
   (`includes/pricing.php`). A Nigerian office normally prices in NGN, so no exchange rate is involved.
2. After Paystack confirms the payment, the site books and tickets on Fanel's Galileo office with
   form of payment **Cash** (agency).
3. The airline is paid the way Fanel already pays for tickets issued on that office: BSP Nigeria
   (IATA) or the consolidator that owns the ticketing office. There is no prepaid balance to top up.
4. Every booking stores what the customer paid (`total_kobo`), the fare when they paid
   (`supplier_amount`) and what the airline charged at ticketing (`charged_amount`), with the
   Galileo PNR and ticket numbers, so it can be matched against the BSP / consolidator statement.

## What happens after payment (automatic)

| Step | uAPI request | If it fails |
|---|---|---|
| 1. Price the same flights and booking classes again | `AirPriceReq` | Fare gone, or price went up → **needs attention**, nothing is booked |
| 2. Create the PNR (names, passport DOCS, contact, Cash) | `AirCreateReservationReq` | Refused → needs attention. **No clear answer** → needs attention, and the site will not book again until staff confirm no PNR exists |
| 3. Issue the e-tickets | `AirTicketingReq` | Needs attention with the PNR; "Try again" only re-runs ticketing, never books twice |
| 4. Save ticket numbers, email the customer | | |

`TRAVELPORT_TICKETING = 'hold'` stops after step 2: the PNR is booked and staff issue the ticket in
Smartpoint, then press **Mark as ticketed** in the back office.

## What Fanel needs from Travelport

Ask the Travelport (Galileo) account manager for **Universal API access** for the website:

- **Username and password** (`Universal API/uAPI…`) → `TRAVELPORT_USERNAME`, `TRAVELPORT_PASSWORD`
- **Target branch** (`P7…`) linked to Fanel's PCC → `TRAVELPORT_BRANCH`
- The **endpoint** (pre-production first, then production) → `TRAVELPORT_ENDPOINT`
- The **schema version** the account is on (e.g. `v52_0`) → `TRAVELPORT_SCHEMA`
- **Ticketing rights** for the PCC through the API, with Cash form of payment
- The fares to return: published, and Fanel's private / negotiated fares if any

Travelport usually requires a certification test in pre-production before production access.

## Going live

1. Put the pre-production details in `config.php`, keep `FLIGHT_DATA = 'live'`, and run
   `database/galileo.sql` once on an existing database.
2. Search, book and ticket a test booking (Travelport pre-production does not issue real tickets).
3. Check the PNR in Smartpoint: names, DOCS, contact, fare, and that the ticket number matches the
   back office.
4. Switch to the production endpoint and credentials. Do one real cheap domestic booking and void it
   in Smartpoint the same day.

## Not yet checked against a real Travelport account

The requests follow the uAPI documentation and were tested end to end against a stand-in server.
On the first pre-production run, check:

- Infants: whether Galileo needs the infant tied to a specific adult in the request
- Children's age (the search uses age 10; the booking sends the real age)
- Ticket numbers matched to travellers by name
- Whether Fanel's office returns prices in NGN (if not, set the exchange rate for that currency in the admin)
