import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: true,
  retries: 0,
  reporter: 'line',
  use: {
    baseURL: 'http://127.0.0.1:14173',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [{
    name: 'android-phone-chrome',
    use: { ...devices['Pixel 7'], channel: 'chrome' },
  }],
  webServer: {
    command: 'npm run dev -- --host 127.0.0.1 --port 14173',
    url: 'http://127.0.0.1:14173',
    reuseExistingServer: false,
    timeout: 120_000,
  },
})
