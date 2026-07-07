# Casa Paraiso Screen Flow

## Purpose

Define the MVP pages, role-based navigation, and main user journeys before Laravel routes, controllers, and Blade layouts are scaffolded.

The interface should use separate dashboards for admin, staff, and customer. Admin and staff use sidebar module navigation. Customers land on appointment-focused pages after login.

## Route Groups

- Public: unauthenticated pages for landing, service browsing, login, and registration.
- Auth: login, registration, password reset, logout, and authenticated redirects.
- Admin: full management access.
- Staff: daily operations access.
- Customer: appointment request, status, history, feedback, and profile access.

Planned route prefixes:

- `/`
- `/login`
- `/register`
- `/admin`
- `/staff`
- `/customer`

## Shared Layout Rules

- Use Laravel Blade server-rendered pages with Tailwind CSS.
- Use separate layout shells for admin/staff and customer-facing authenticated pages.
- Admin and staff layouts should use a persistent sidebar, top bar, page title, search/filter area where needed, and clear primary action buttons.
- Customer layout should be simpler and appointment-first, with navigation focused on appointments, feedback, and profile.
- All role dashboards should show only actions the current role is allowed to perform.
- Avoid SPA-only navigation patterns; pages should work as standard Laravel routes.

## Public Screens

### Landing Page

Access: guest and authenticated users.

Purpose:

- Introduce Casa Paraiso - Body and Wellness Spa.
- Show featured services, business contact details, and call to action.

Primary actions:

- Login.
- Register.
- Request appointment after authentication.

Main data:

- Active services.
- Basic business information.

### Service Listing

Access: guest and authenticated users.

Purpose:

- Let visitors view available spa services before logging in.

Primary actions:

- View service details.
- Register or login to request an appointment.

Main data:

- Active service name, description, duration, and price.

## Auth Screens

### Login

Access: guest users.

Purpose:

- Authenticate admin, staff, and customer users.

Post-login redirect:

- Admin users go to `/admin/dashboard`.
- Staff users go to `/staff/dashboard`.
- Customer users go to `/customer/appointments`.

### Register

Access: guest users.

Purpose:

- Allow customers to create accounts.

Rules:

- Public registration creates customer accounts only.
- Admin and staff accounts should be created by admin users.

Post-registration redirect:

- Customer users go to `/customer/appointments`.

## Admin Screens

Admin navigation uses sidebar modules.

### Admin Dashboard

Route: `/admin/dashboard`

Purpose:

- Give admin a management summary.

Main data:

- Today appointments.
- Pending appointment requests.
- Recent transactions.
- Revenue summary.
- Feedback sentiment summary.
- Promotion suggestions needing review.

Primary actions:

- Review pending appointments.
- Add appointment.
- Add transaction.
- View reports.

### Appointments

Route group: `/admin/appointments`

Purpose:

- Manage all customer appointment requests and bookings.

Screens:

- Appointment list/calendar.
- Appointment detail.
- Create appointment.
- Confirm/reschedule/cancel appointment.
- Mark completed or no-show.

Main data:

- Appointment number, customer, service, staff, requested time, scheduled time, status, notes.

Primary actions:

- Confirm pending request.
- Assign staff.
- Reschedule.
- Cancel.
- Complete.
- Record transaction.

### Customers

Route group: `/admin/customers`

Purpose:

- Manage customer records and behavior history.

Screens:

- Customer list.
- Customer detail.
- Customer appointment history.
- Customer transaction history.
- Customer feedback history.
- Customer promotion suggestions.

Main data:

- Profile, contact details, appointments, transactions, feedback, RFM-related activity.

Primary actions:

- View customer history.
- Update customer notes.
- Review promotion suggestions.

### Staff

Route group: `/admin/staff`

Purpose:

- Manage staff profiles, service eligibility, and schedules.

Screens:

- Staff list.
- Staff detail.
- Create/edit staff account.
- Weekly schedule editor.
- Schedule exceptions.
- Staff-service assignments.

Main data:

- Staff user, profile, bookable status, assigned services, weekly availability, exceptions.

Primary actions:

- Create staff.
- Assign services.
- Edit schedule.
- Add schedule exception.

### Services

Route group: `/admin/services`

Purpose:

- Manage spa service catalog.

Screens:

- Service list.
- Create/edit service.
- Service detail.

Main data:

- Service name, description, duration, price, active status.

Primary actions:

- Add service.
- Update service.
- Activate/deactivate service.

### Transactions

Route group: `/admin/transactions`

Purpose:

- Manage manual payment and service transaction records.

Screens:

- Transaction list.
- Create transaction.
- Transaction detail.

Main data:

- Transaction number, customer, appointment, service, amount, payment status, payment method, recorded by, date.

Primary actions:

- Record payment.
- Update payment status.
- View customer transaction history.

### Promotions

Route group: `/admin/promotions`

Purpose:

- Review RFM segments, promotion rules, and stored promotion suggestions.

Screens:

- RFM segment list.
- Promotion rule list.
- Promotion suggestion queue.
- Promotion suggestion detail.

Main data:

- Customer, RFM segment, recency, frequency, monetary total, suggested offer, status.

Primary actions:

- Review suggestion.
- Mark applied.
- Dismiss suggestion.
- Update promotion rule.

### Feedback

Route group: `/admin/feedback`

Purpose:

- Review customer ratings, comments, and sentiment summaries.

Screens:

- Feedback list.
- Feedback detail.
- Sentiment summary.

Main data:

- Customer, appointment, service, rating, comment, sentiment label, submitted date.

Primary actions:

- Filter feedback.
- View related appointment/customer.

### Reports

Route group: `/admin/reports`

Purpose:

- Support management decisions without direct database access.

Screens:

- Appointment report.
- Transaction/revenue report.
- Customer activity report.
- Promotion suggestion report.
- Feedback sentiment report.

Main data:

- Filtered summaries and tabular records by date range, status, service, staff, customer, and sentiment.

Primary actions:

- Filter report.
- Export CSV.

### Settings

Route group: `/admin/settings`

Purpose:

- Manage basic application settings.

Screens:

- Business profile.
- User account management.
- System defaults.

Main data:

- Business contact information, operating assumptions, default payment methods, user status.

Primary actions:

- Update business details.
- Activate/deactivate user accounts.

## Staff Screens

Staff navigation uses sidebar modules focused on daily operations.

### Staff Dashboard

Route: `/staff/dashboard`

Purpose:

- Give staff a daily work view.

Main data:

- Today assigned appointments.
- Pending appointment requests that need action.
- Upcoming confirmed appointments.
- Recently completed appointments.

Primary actions:

- Review pending requests.
- Open assigned appointment.
- Record transaction.

### Staff Appointments

Route group: `/staff/appointments`

Purpose:

- Let staff handle assigned and pending appointments.

Screens:

- Today appointments.
- Pending requests.
- Appointment detail.
- Confirm/reschedule/cancel appointment.
- Complete appointment.

Rules:

- Staff can view assigned appointments.
- Staff can view pending requests for services they can perform.
- Staff cannot access admin-only settings.

Primary actions:

- Confirm appointment.
- Reschedule appointment.
- Mark completed.
- Mark no-show.
- Record transaction.

### Staff Customers

Route group: `/staff/customers`

Purpose:

- Let staff view customer details needed for service delivery.

Screens:

- Customer lookup.
- Customer detail.

Main data:

- Customer contact details, appointment history, relevant notes, feedback history.

Rules:

- Staff can view operational customer information.
- Staff should not manage system-level user settings.

### Staff Transactions

Route group: `/staff/transactions`

Purpose:

- Let staff record manual service payments.

Screens:

- Create transaction from appointment.
- Transaction list for staff-recorded or assigned appointments.
- Transaction detail.

Primary actions:

- Record payment.
- Update payment status where allowed.

### Staff Feedback

Route group: `/staff/feedback`

Purpose:

- Let staff view feedback related to services and appointments.

Screens:

- Feedback list.
- Feedback detail.

Rules:

- Staff can view feedback but should not edit submitted customer feedback.

## Customer Screens

Customer navigation is appointment-first.

### My Appointments

Route: `/customer/appointments`

Purpose:

- Primary customer landing page after login.

Main data:

- Upcoming appointments.
- Pending requests.
- Appointment history.
- Booking status.

Primary actions:

- Request appointment.
- View appointment details.
- Cancel pending request if allowed.
- Submit feedback for completed appointment.

### Request Appointment

Route: `/customer/appointments/create`

Purpose:

- Let customers submit appointment requests.

Main data:

- Active services.
- Available staff or preferred staff where supported.
- Preferred date/time.
- Customer notes.

Rules:

- Submitted requests start as `pending`.
- Staff/admin must confirm before the booking is final.

Primary actions:

- Submit request.

### Appointment Detail

Route: `/customer/appointments/{appointment}`

Purpose:

- Let customers view status and details for one appointment.

Main data:

- Appointment number, service, requested time, scheduled time, assigned staff, status, notes.

Primary actions:

- Submit feedback for completed appointment.
- Cancel pending request if allowed.

### Feedback

Route group: `/customer/feedback`

Purpose:

- Let customers submit and view their own service feedback.

Screens:

- Feedback form.
- My feedback history.

Rules:

- One feedback record per completed appointment in the MVP.
- Customers can view their own feedback only.

### Profile

Route: `/customer/profile`

Purpose:

- Let customers manage their own profile.

Main data:

- Name, email, phone, address, contact preference.

Primary actions:

- Update profile.
- Change password through auth flow.

## Primary User Journeys

### Customer Appointment Request

1. Customer registers or logs in.
2. Customer lands on My Appointments.
3. Customer opens Request Appointment.
4. Customer selects service and preferred date/time.
5. System creates a `pending` appointment.
6. Customer sees the request in My Appointments.

### Staff Confirmation

1. Staff opens pending requests.
2. Staff reviews customer, service, requested time, and availability.
3. Staff assigns staff member and scheduled start/end time.
4. System prevents overlapping confirmed appointments for the assigned staff member.
5. Appointment becomes `confirmed`.

### Service Completion And Payment

1. Staff opens a confirmed appointment.
2. Staff marks the appointment completed.
3. Staff records a manual transaction.
4. Transaction becomes part of customer history and RFM calculation input.

### Feedback Submission

1. Customer opens a completed appointment.
2. Customer submits rating and comment.
3. System stores sentiment label.
4. Admin sees feedback in feedback reports and dashboard summaries.

### Promotion Review

1. Admin opens promotion suggestions.
2. Admin reviews customer RFM segment and suggested offer.
3. Admin marks suggestion reviewed, applied, or dismissed.
4. Suggestion remains stored for audit and reporting.

## Access Matrix

| Area | Admin | Staff | Customer | Guest |
| --- | --- | --- | --- | --- |
| Public landing/services | Yes | Yes | Yes | Yes |
| Admin dashboard | Yes | No | No | No |
| Staff dashboard | No | Yes | No | No |
| Customer appointments | No | No | Own only | No |
| Appointments management | All | Assigned/pending only | Own only | No |
| Services management | Yes | View only if needed | View active services | View active services |
| Staff management | Yes | No | No | No |
| Customer records | Yes | Operational access | Own profile only | No |
| Transactions | All | Allowed operational records | Own history if exposed | No |
| Promotions | Yes | Review/apply if allowed | No | No |
| Feedback | All | Related view only | Own feedback only | No |
| Reports | Yes | Limited operational reports | No | No |
| Settings | Yes | No | Own profile only | No |

## MVP Coverage Check

- Appointment scheduling is covered by customer request screens, admin appointment management, and staff appointment management.
- Service and staff management are covered by admin modules.
- Customer records are covered by admin customer screens and staff customer lookup.
- Manual transactions are covered by admin and staff transaction screens.
- RFM promotion suggestions are covered by admin promotions.
- Feedback and sentiment analytics are covered by customer feedback and admin feedback screens.
- Reports and exports are covered by admin reports.
