# Casa Paraiso UI/UX Audit Report

> **Audit Date**: July 20, 2026
> **Evaluation Scope**: Laravel 12 Blade Web Application & Mobile Vue 3 / Capacitor Application
> **Target Roles**: Guest & Auth, Customer, Receptionist, Therapist, Admin & Super Admin
> **Standards Compliance**: ISO/IEC 25010 Usability, WCAG 2.1 AA Accessibility, Casa Paraiso Brand & UI Guide
> **Verification note**: This report was cross-checked line-by-line against the actual `git diff` on this branch and against the current contents of every file it names. Several findings in an earlier draft of this report claimed fixes that never actually landed in code — those are corrected below and marked **⏳ Outstanding**, not fixed. Every "✅ Fixed" line has a matching diff; every "⏳ Outstanding" line was verified against the live file.

---

## Executive Summary

A UI/UX audit was conducted across all 5 user roles and both frontend surfaces (Blade Web and Mobile Vue 3). The application has strong architectural foundations, a well-defined design-token system (`resources/css/app.css` `@theme` tokens on web, `mobile/src/style.css` CSS custom properties on mobile), and generally good baseline accessibility (skip links, `aria-current`, `role="alert"`/`role="status"` conventions, accessible star-rating and calendar widgets).

A first remediation pass landed **10 real fixes** across both surfaces. However, roughly **half of the findings originally reported as "fixed" were not actually implemented** — the files involved were never modified. Those issues remain open. A verification pass also surfaced **6 additional real issues** not captured in the original audit at all (see [Additional Findings](#additional-findings-identified-during-verification)), most notably that the "standardize on shared components" fix never happened anywhere — status badges, pagination, and modal sheets are each reimplemented 3–4 different ways across the mobile app.

**Status split:**
- ✅ **Fixed and verified**: 10 findings (see per-finding status below)
- ⏳ **Outstanding** (claimed fixed previously, but not actually done): 7 findings
- 🆕 **Newly identified**: 6 findings

### ISO/IEC 25010 Usability Dimension Scores

| Dimension | Current Score | Target Score | Key Focus Areas |
| --- | --- | --- | --- |
| **Appropriateness Recognizability** | 82 / 100 | 95 / 100 | Visual hierarchy, brand expression, Cormorant Garamond / Manrope consistency, component reuse |
| **Learnability** | 85 / 100 | 96 / 100 | Non-color-only state badges, consistent status semantics across surfaces |
| **Operability** | 79 / 100 | 95 / 100 | Touch targets ≥44px (web) / ≥48px (mobile), keyboard focus traps on all dialogs, skip links |
| **User Error Protection** | 88 / 100 | 98 / 100 | Explicit ARIA error roles (`role="alert"`) on every error surface, not just some |
| **User Interface Aesthetics** | 83 / 100 | 95 / 100 | Design token compliance — several views still bypass tokens with raw hex |
| **Accessibility (WCAG 2.1 AA)** | 76 / 100 | 96 / 100 | Focus ring contrast (still overridden), dialog roles/focus traps on all sheets |

---

## Audit Findings by Surface & Severity

### 1. Web Application (Laravel Blade + Tailwind CSS)

#### 🔴 Critical Findings
1. **Color-Only Status Badges** (`resources/views/components/status-badge.blade.php`)
   Status badges relied solely on background/text color to differentiate `success`/`warning`/`danger` states.
   **✅ Fixed** — SVG check/warning/x icons added alongside the existing text label, plus matching icon logic in `mobile/src/components/MobileStatusBadge.vue`.

2. **Color-Only Calendar Status Dots** (`resources/views/customer/appointments/index.blade.php`)
   Calendar day cells used bare 2px colored dots with no accessible label.
   **✅ Fixed** — each dot now wraps a `<span class="sr-only">` announcing the status name.

3. **Missing Landmark on Mobile Navigation** (`resources/views/layouts/navigation.blade.php`)
   The customer bottom dock used a `<div>` instead of a semantic landmark.
   **✅ Fixed** — converted to `<nav aria-label="Customer navigation">` (open and close tags both updated).

4. **Missing Focus Trap in Drawer Navigation** (`resources/views/layouts/navigation.blade.php`)
   Keyboard focus is not trapped inside the mobile navigation drawer when it's open.
   **⏳ Outstanding** — the only change made to this file was the `<div>`→`<nav>` swap above (finding #3). No focus-trap directive (`x-trap`, a custom trap helper, `inert` on siblings, etc.) exists anywhere in the file. Keyboard users can still tab out of an open drawer into the page behind it.

5. **Missing Table Shell Label** (`resources/views/admin/dashboard.blade.php`)
   `<x-table-shell>` had no `:label` prop, falling back to a generic default.
   **✅ Fixed** — now passes `:label="__('Upcoming confirmed visits table')"`. Note: this fix was applied to this one call site only; a broader sweep of the ~20 other `<x-table-shell>` call sites across admin/reception/staff index views to check for the same generic-label fallback has not been done.

#### 🟠 Major Findings
1. **Focus Ring Contrast Override** (`resources/css/app.css`)
   `.casa-input:focus` sets `outline: none`, suppressing the global 3px `:focus-visible` ring defined at the top of the file for all other interactive elements.
   **⏳ Outstanding** — verified directly in the current file (`app.css:250-253`): `.casa-input:focus { outline: none; ... }` is still present, and `app.css` has zero diff on this branch. Any form input using `.casa-input` (which appears to be most of them) loses the high-contrast focus indicator on keyboard focus.

2. **Sub-44px Checkbox Touch Target** (`resources/views/auth/login.blade.php`)
   The "Remember me" checkbox's visual target was ~16px.
   **✅ Fixed** — checkbox enlarged to `size-5`, label wrapped in `min-h-[44px]` with padding.

3. **Missing Skip-to-Content Link** (`resources/views/layouts/app.blade.php`, `resources/views/layouts/guest.blade.php`)
   Keyboard users could not bypass navigation to reach main content.
   **✅ Fixed** — skip link added to both layouts, targeting a new `id="main-content"` on each `<main>`.

4. **Header Style Override in Table Shell** (`resources/views/admin/dashboard.blade.php`)
   Inline `bg-casa-bg` overrode the intended `bg-casa-sand` token on the table header row.
   **✅ Fixed** — standardized to `bg-casa-sand/72`.

---

### 2. Mobile Vue 3 Application (`mobile/src`)

#### 🔴 Critical Findings
1. **Design Token Bypass via Hardcoded Hex Colors**
   Scoped styles used raw hex values (`#334736`, `#4f6a4e`, `#dcd2c2`, etc.) instead of `var(--casa-*)` custom properties.
   **✅ Partially fixed** — 7 views were actually retokenized: `AdminDashboardView.vue`, `AdminRosterView.vue`, `CustomerAppointmentsView.vue`, `CustomerFeedbackView.vue`, `CustomerProfileView.vue`, `ReceptionDashboardView.vue`, `StaffAppointmentsView.vue`.
   **⏳ Outstanding** — the original report claimed **13 views** were fixed; only 7 were. Confirmed still hardcoded: `StaffEarningsView.vue`, `ReceptionPaymentsView.vue`, `AdminInsightsView.vue`, and `AdminControlView.vue` (the last of these wasn't even named in the original report — see [Additional Findings](#additional-findings-identified-during-verification)). A full inventory sweep of `mobile/src/views` for remaining `#`-prefixed hex in `<style>` blocks is still needed.

2. **Bypassed Display Font Token**
   Headings hardcoded `font-family: Georgia, serif` instead of `var(--font-display)`.
   **✅ Fixed** in the same 7 views as above. **⏳ Outstanding** in `StaffEarningsView.vue`, `ReceptionPaymentsView.vue`, `AdminInsightsView.vue`, `AdminControlView.vue` — all four still contain `font-family:Georgia,serif`.

3. **Missing ARIA Error Roles**
   Error messages lacked `role="alert"`/`role="status"`.
   **✅ Fixed** in `StaffAppointmentsView.vue`, `AdminDashboardView.vue`, `AdminRosterView.vue`.
   **⏳ Outstanding** — the original report also claimed `StaffEarningsView.vue` and `ReceptionPaymentsView.vue` were fixed; verified directly against the live files, both still render `<p v-if="store.error" class="alert">` with no `role` attribute at all. `AdminInsightsView.vue`'s error/notice paragraphs have the same gap and were never listed in the original report either.

4. **Un-trapped Modal Sheets**
   Inline sheets lack keyboard focus traps and Escape-key handling.
   **⏳ Outstanding, and worse than originally described.** The report claimed `AdminInsightsView.vue` and `ReceptionPaymentsView.vue` were routed through `useModalLifecycle`/`MobileModalSheet` — neither file was touched. Verified directly:
   - `ReceptionPaymentsView.vue`'s payment-form sheet has `role="dialog" aria-modal="true"` but no focus-trap mechanism (`v-mobile-modal` or `useModalLifecycle`) anywhere in the file.
   - `AdminInsightsView.vue`'s reward-settings sheet (`<section v-if="rewardOpen" class="sheet">`) has **no `role="dialog"` or `aria-modal` at all** — it's not just un-trapped, it's not marked as a dialog to assistive tech in the first place.

5. **Sub-48dp Touch Targets**
   Action buttons/pagination used 44px min-height instead of the 48px mobile standard.
   **✅ Fixed** — global rule in `mobile/src/style.css` raised from 44px to 48px, plus explicit fixes in `AdminRosterView.vue` and `StaffAppointmentsView.vue`.

#### 🟠 Major Findings
1. **Inline Duplication of Shared Components**
   Views hand-roll status badges, pagers, and stat grids instead of the existing `MobileStatusBadge`, `MobilePagination`, `MobileStatStrip`, `MobileModalSheet` components.
   **⏳ Outstanding — this fix never happened anywhere.** Confirmed by direct inspection: `MobilePagination.vue` is used by exactly one view (`CustomerAppointmentsView.vue`); `CustomerFeedbackView`, `StaffAppointmentsView`, `StaffEarningsView`, `ReceptionPaymentsView`, `AdminInsightsView` all hand-roll an identical prev/next pager. Status-pill styling is separately reimplemented in `MobileStatusBadge.vue`, `CustomerAppointmentsView.vue`, `ReceptionDashboardView.vue`, `ReceptionPaymentsView.vue`, and `StaffAppointmentsView.vue`, with **diverging color semantics** for the same status (e.g. `completed` renders as "success" in `MobileStatusBadge` but "warning" in `CustomerAppointmentsView`'s local copy). See [Additional Findings](#additional-findings-identified-during-verification) for full detail.

2. **Missing Section Landmark Labels**
   `<section>` wrappers lacked `aria-labelledby`.
   **✅ Fixed** in `AdminDashboardView.vue`, `AdminRosterView.vue`.
   **⏳ Outstanding** — `AdminInsightsView.vue` (also named in the original report) was never touched; its top-level `<section>` still has no `aria-labelledby`.

---

## Additional Findings Identified During Verification

These were found while reading the mobile app end-to-end to verify the claims above. They were not in the original report at all.

1. **`AdminControlView.vue` bypasses design tokens entirely** — its `<style>` block uses raw hex (`#334736`, `#67675f`, `#dcd2c2`, `#4f6a4e`, `#e9f2e8`, `#fffcf7`, `#f5f0e7`, etc.) throughout, the same violation as Critical Finding #1 above, but this file wasn't in scope of any pass so far.

2. **Status-badge concept fragmented 4+ ways** with inconsistent color semantics across `MobileStatusBadge.vue`, `CustomerAppointmentsView.vue`, `ReceptionDashboardView.vue`, `ReceptionPaymentsView.vue`, and `StaffAppointmentsView.vue`. A future palette or status-taxonomy change would need to be applied in 4+ places and would likely drift.

3. **Pagination hand-rolled in 5 places** despite `MobilePagination.vue` existing as the shared primitive — only `CustomerAppointmentsView.vue` uses it.

4. **Two parallel modal/focus-trap mechanisms coexist** — `MobileModalSheet.vue` + `useModalLifecycle` composable (the "official" path) vs. a separate `v-mobile-modal` directive used by `CustomerFeedbackView.vue` and `StaffAppointmentsView.vue` for hand-rolled `role="dialog"` sections. Both do the same job through different code paths, and (per Critical Finding #4 above) some sheets use neither.

5. **Inconsistent loading-state treatment** — `AdminDashboardView.vue`, `AdminRosterView.vue`, and `ReceptionDashboardView.vue` show either nothing (blank flash) or plain unstyled loading text, while `CustomerAppointmentsView`, `CustomerFeedbackView`, `CustomerProfileView`, and `StaffAppointmentsView`/`StaffEarningsView`/`ReceptionPaymentsView` consistently use the `MobileSkeleton` component via `useInitialLoad`. Perceived-performance is inconsistent between the customer-facing and staff/admin-facing halves of the app.

6. **`.page` top-padding values are hand-tuned per screen** rather than derived from the shared `--app-bar-height` token — values range from `1.25rem` to `5.2rem` to `8.2rem` across admin/staff/reception screens with no evident system, making future app-bar height changes error-prone.

---

## Out of Scope

This branch's diff also contains two changes unrelated to UI/UX, noted here only so they aren't mistaken for audit output:
- `app/Http/Middleware/MeasureApiRequest.php` — refactors response header assignment (`->header()` chain → `$response->headers->set()`), a backend behavior-preserving change.
- `tests/Feature/Api/MobileDemoApkDownloadTest.php` — refactors a test assertion chain to check headers directly rather than via `assertHeader`/`assertHeaderContains`.

---

## Remediation Plan (Outstanding Work Only)

Everything below is **not yet done** and reflects the actual remaining gap, not a restated list of completed work:

1. **Focus management, web**: add an Alpine focus-trap to the mobile drawer in `layouts/navigation.blade.php`; remove or override the `outline: none` on `.casa-input:focus` in `resources/css/app.css` so the global focus-visible ring survives on form inputs.
2. **ARIA error/status roles, mobile**: add `role="alert"`/`role="status"` to the error/notice paragraphs in `StaffEarningsView.vue`, `ReceptionPaymentsView.vue`, and `AdminInsightsView.vue`.
3. **Dialog semantics + focus traps, mobile**: give `AdminInsightsView.vue`'s reward-settings sheet proper `role="dialog" aria-modal="true"` and `aria-labelledby`; route it and `ReceptionPaymentsView.vue`'s payment-form sheet through `MobileModalSheet`/`useModalLifecycle` (or apply the `v-mobile-modal` directive consistently) so focus is trapped and Escape closes them.
4. **Design-token sweep, mobile**: retokenize `StaffEarningsView.vue`, `ReceptionPaymentsView.vue`, `AdminInsightsView.vue`, and `AdminControlView.vue` (raw hex → `var(--casa-*)`, `Georgia, serif` → `var(--font-display)`), matching the 7 views already done.
5. **Component standardization, mobile**: replace the hand-rolled pagers in `CustomerFeedbackView`, `StaffAppointmentsView`, `StaffEarningsView`, `ReceptionPaymentsView`, `AdminInsightsView` with `MobilePagination`; consolidate the 4+ status-badge implementations behind `MobileStatusBadge` with one agreed color mapping.
6. **Loading-state consistency, mobile**: add `MobileSkeleton` + `useInitialLoad` to `AdminDashboardView.vue`, `AdminRosterView.vue`, and `ReceptionDashboardView.vue`.
7. **Table shell label sweep, web**: audit the remaining `<x-table-shell>` call sites beyond `admin/dashboard.blade.php` for the same generic-label fallback.
8. **Padding-system cleanup, mobile**: derive `.page` top padding from `--app-bar-height` rather than per-screen hardcoded values.
