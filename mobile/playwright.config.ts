import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: true,
  timeout: 120_000,
  retries: 0,
  reporter: 'line',
  expect: { toHaveScreenshot: { animations: 'disabled', maxDiffPixelRatio: 0.02 } },
  use: {
    baseURL: 'http://127.0.0.1:14173',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'android-small-320', use: { ...devices['Pixel 7'], viewport: { width: 320, height: 720 }, channel: 'chrome' } },
    { name: 'android-pixel-7', use: { ...devices['Pixel 7'], channel: 'chrome' } },
    { name: 'android-landscape-375', use: { ...devices['Pixel 7'], viewport: { width: 667, height: 375 }, channel: 'chrome' } },
    { name: 'android-tablet-768', use: { ...devices['Pixel 7'], viewport: { width: 768, height: 1024 }, channel: 'chrome' } },
  ],
  webServer: {
    command: 'npm run dev -- --host 127.0.0.1 --port 14173',
    url: 'http://127.0.0.1:14173',
    reuseExistingServer: false,
    timeout: 120_000,
  },
})
