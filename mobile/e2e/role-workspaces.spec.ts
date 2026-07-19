import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page, type Route } from '@playwright/test'

const server = 'https://quiet-lotus-123.trycloudflare.com'
const instanceId = 'bda2fdb4-c8a4-4e0d-bc75-43ccd6b23811'
const meta = { current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null }

const accounts = [
  { email: 'customer@example.test', role: 'customer', workspace: 'customer', navigation: 'Customer navigation', tabs: ['Appointments', 'Feedback', 'Profile'] },
  { email: 'reception@example.test', role: 'receptionist', workspace: 'reception', navigation: 'Receptionist navigation', tabs: ['Today', 'Bookings', 'Customers', 'Payments'] },
  { email: 'staff@example.test', role: 'staff', workspace: 'staff', navigation: 'Therapist navigation', tabs: ['Today', 'Schedule', 'Guests', 'Earnings'] },
  { email: 'admin@example.test', role: 'admin', workspace: 'admin', navigation: 'Administrator navigation', tabs: ['Today', 'Ops', 'Manage', 'Insights', 'Control'] },
  { email: 'super@example.test', role: 'super_admin', workspace: 'admin', navigation: 'Administrator navigation', tabs: ['Today', 'Ops', 'Manage', 'Insights', 'Control'] },
] as const

function emptyApi(path: string): unknown {
  if (path === '/api/v1/customer/booking-options') return { data: { services: [{ id: 1, name: 'Signature Massage', description: 'A restorative full-body treatment.', duration_minutes: 60, price: '1200.00', therapists: [{ id: 1, name: 'Therapist' }] }], addons: [{ code: 'foot_spa', name: 'Foot Spa', price: '350.00', duration_minutes: 0 }], vouchers: [], booking_window: { timezone: 'Asia/Manila', opens_at: '13:00', closes_at: '00:00', slot_interval_minutes: 30, lead_time_minutes: 30, initial_month: '2026-07' } } }
  if (path === '/api/v1/reception/dashboard') return { data: { summary: { today: 0, upcoming: 0, customers: 0, payments_today: '0.00' }, today_appointments: [] } }
  if (path === '/api/v1/staff/dashboard') return { data: { profile: { id: 1, name: 'Therapist', specialization: 'Massage' }, summary: { assigned_today: 0, upcoming: 0, completed_today: 0, feedback: 0 }, commissions: { pending: '0.00', paid: '0.00', net: '0.00' }, today_appointments: [] } }
  if (path === '/api/v1/admin/dashboard') return { data: { summary: { today: 0, upcoming: 0, payments_today: '0.00', today_appointments: 0, upcoming_appointments: 0, today_revenue: '0.00', new_feedback: 0, available_rewards: 0, customers: 0, active_services: 0, bookable_therapists: 0 }, today_appointments: [], upcoming_appointments: [], is_super_admin: true } }
  if (path === '/api/v1/customer/profile') return { data: { name: 'Customer', email: 'customer@example.test', phone: null, address: null, contact_preference: null, customer_code: 'CUS-001', has_password: true, google_linked: false, contact_preferences: [] } }
  if (path === '/api/v1/admin/settings') return { data: { settings: { business_name: 'Casa Paraiso', contact_email: null, contact_phone: null, business_address: null, default_payment_method: 'cash' }, payment_methods: ['cash'], operating: { timezone: 'Asia/Manila', opens_at: '13:00', closes_at: '00:00', slot_interval_minutes: 30, commission_rate: '0.22' }, security: [] } }
  if (path === '/api/v1/admin/staff/options') return { data: { can_create: true, staff_types: ['therapist'], services: [] } }
  if (path === '/api/v1/admin/users') return { data: [], roles: ['customer', 'staff', 'receptionist', 'admin'], meta }
  if (path.includes('/appointments')) return { data: [], summary: { upcoming: 0, completed: 0, cancelled: 0, confirmed: 0, no_show: 0 }, meta }
  if (path.includes('/feedback')) return { data: [], eligible_appointments: [], summary: { awaiting_feedback: 0, submitted: 0, positive: 0, neutral: 0, negative: 0 }, meta }
  if (path.includes('/customers')) return { data: [], meta }
  if (path.includes('/transactions')) return { data: [], summary: { paid: '0.00', unpaid_count: 0, partial_count: 0 }, meta }
  if (path.includes('/commissions')) return { data: [], summary: { pending: '0.00', paid: '0.00', net: '0.00' }, staff: [], meta }
  if (path.includes('/staff')) return { data: [], summary: { active: 0, inactive: 0, bookable: 0 }, meta }
  if (path.includes('/services')) return { data: [], summary: { active: 0, inactive: 0 }, meta }
  if (path.includes('/promotions')) return { data: [], summary: { available: 0, reserved: 0, used: 0, expired: 0, dismissed: 0 }, settings: { promotion_voucher_validity_days: 90, validity_options: [90] }, presets: [], addons: [], meta }
  if (path.includes('/reports')) return { type: 'appointments', types: ['appointments'], columns: [], data: [], summary: { appointments: 0, revenue: '0.00', customers: 0, feedback: 0 }, meta }
  return { data: [] }
}

async function mockBackend(page: Page): Promise<void> {
  await page.route(`${server}/**`, async (route: Route) => {
    const request = route.request()
    const url = new URL(request.url())
    if (url.pathname === '/api/v1/meta') {
      await route.fulfill({ json: { data: { service: 'casa-paraiso-mobile-api', api_version: 'v1', instance_id: instanceId, timezone: 'Asia/Manila', server_time: new Date().toISOString(), supported_auth: ['password', 'google'], pairing: { protocol: 2, enabled: true } } } })
      return
    }
    if (url.pathname === '/api/v1/auth/login') {
      const email = String(request.postDataJSON().email)
      const account = accounts.find((candidate) => candidate.email === email) ?? accounts[0]
      await route.fulfill({ json: { data: { token: '1|test-token', token_type: 'Bearer', expires_at: new Date(Date.now() + 86_400_000).toISOString(), user: { id: 1, name: 'Test User', email, phone: null, role: account.role, workspace: account.workspace, email_verified: true } } } })
      return
    }
    if (url.pathname === '/api/v1/auth/logout') {
      await route.fulfill({ status: 204 })
      return
    }
    await route.fulfill({ json: emptyApi(url.pathname) })
  })
}

async function pairAndSignIn(page: Page, email: string): Promise<void> {
  await mockBackend(page)
  await page.goto('/')
  await page.getByRole('button', { name: 'Request an appointment' }).click()
  await page.getByLabel('Casa Paraiso link').fill(server)
  await page.getByRole('button', { name: 'Connect' }).click()
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password').fill('password')
  await page.getByRole('button', { name: 'Sign in', exact: true }).click()
}

for (const account of accounts) {
  test(`${account.role} workspace navigation is usable and accessible`, async ({ page }, testInfo) => {
    await pairAndSignIn(page, account.email)
    const navigation = page.getByRole('navigation', { name: account.navigation })
    await expect(navigation).toBeVisible()

    const navigationButtons = navigation.getByRole('button')
    for (let index = 0; index < await navigationButtons.count(); index += 1) {
      const box = await navigationButtons.nth(index).boundingBox()
      expect(box?.height ?? 0).toBeGreaterThanOrEqual(48)
    }

    for (const tab of account.tabs) {
      await navigation.getByRole('button', { name: tab }).click()
      await expect(navigation.getByRole('button', { name: tab })).toHaveAttribute('aria-current', 'page')
      const viewportFits = await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)
      expect(viewportFits).toBe(true)
      const results = await new AxeBuilder({ page }).analyze()
      expect(results.violations).toEqual([])

      if (testInfo.project.name === 'android-pixel-7') {
        if (account.role === 'customer' && tab === 'Appointments') await expect(page).toHaveScreenshot('customer-appointments.png')
        if (account.role === 'receptionist' && tab === 'Today') await expect(page).toHaveScreenshot('reception-dashboard.png')
        if (account.role === 'admin' && tab === 'Insights') await expect(page).toHaveScreenshot('admin-insights.png')
      }
    }

    if (account.role === 'super_admin') {
      await expect(page.getByRole('button', { name: 'User access' })).toBeVisible()
    } else if (account.role === 'admin') {
      await expect(page.getByRole('button', { name: 'User access' })).toHaveCount(0)
    }

  })
}

test('landing, connection, and sign-in screens remain phone-first', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'android-pixel-7', 'Visual onboarding coverage uses the reference Android viewport.')
  await mockBackend(page)
  await page.goto('/')
  await expect(page.getByRole('heading', { name: /Let the day soften here/i })).toBeVisible()
  await expect(page).toHaveScreenshot('landing-phone.png')
  await page.getByRole('button', { name: 'Request an appointment' }).click()
  await expect(page).toHaveScreenshot('connect-phone.png')
  await page.getByLabel('Casa Paraiso link').fill(server)
  await page.getByRole('button', { name: 'Connect' }).click()
  await expect(page).toHaveScreenshot('sign-in-phone.png')
})

test('full-screen booking sheet manages focus and dismissal', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'android-pixel-7', 'Modal interaction coverage uses the reference Android viewport.')
  await pairAndSignIn(page, 'customer@example.test')
  const trigger = page.getByRole('button', { name: 'Book an appointment' })
  await trigger.click()
  const dialog = page.getByRole('dialog', { name: 'Book an appointment' })
  await expect(dialog).toBeVisible()
  await expect(page.getByRole('button', { name: 'Close' })).toBeFocused()
  await expect.poll(() => page.evaluate(() => document.body.style.overflow)).toBe('hidden')
  await expect(page).toHaveScreenshot('customer-booking-form.png')
  await page.keyboard.press('Escape')
  await expect(dialog).toHaveCount(0)
  await expect(trigger).toBeFocused()
})
