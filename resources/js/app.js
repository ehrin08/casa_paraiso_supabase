import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

window.customerCalendarBooking = (config) => ({
    availabilityUrl: config.availabilityUrl,
    serviceId: config.initialServiceId || '',
    staffId: config.initialStaffId || '',
    month: config.initialMonth,
    selectedDate: '',
    selectedSlot: config.initialSlot || '',
    slotsByDate: {},
    loading: false,
    error: '',
    weekDays: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
    slotPreviewLimit: 2,

    init() {
        if (this.selectedSlot) {
            this.selectedDate = this.selectedSlot.slice(0, 10);
        }

        if (this.serviceId) {
            this.fetchAvailability();
        }
    },

    get monthLabel() {
        const [year, month] = this.month.split('-').map(Number);
        return new Date(year, month - 1, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    },

    get calendarDays() {
        const [year, month] = this.month.split('-').map(Number);
        const firstDay = new Date(year, month - 1, 1);
        const daysInMonth = new Date(year, month, 0).getDate();
        const days = [];

        for (let index = 0; index < firstDay.getDay(); index += 1) {
            days.push({ key: `blank-${index}`, label: '', date: null, available: false, previewSlots: [], moreSlots: 0 });
        }

        for (let day = 1; day <= daysInMonth; day += 1) {
            const date = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const slots = this.slotsByDate[date] || [];

            days.push({
                key: date,
                label: day,
                date,
                available: slots.length > 0,
                previewSlots: slots.slice(0, this.slotPreviewLimit),
                moreSlots: Math.max(0, slots.length - this.slotPreviewLimit),
            });
        }

        return days;
    },

    get selectedDateSlots() {
        return this.selectedDate ? (this.slotsByDate[this.selectedDate] || []) : [];
    },

    get selectedDateLabel() {
        if (!this.selectedDate) {
            return 'Choose a highlighted date.';
        }

        return new Date(`${this.selectedDate}T00:00:00`).toLocaleDateString(undefined, {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
        });
    },

    get selectedSlotLabel() {
        if (!this.selectedSlot) {
            return '';
        }

        return new Date(this.selectedSlot.replace(' ', 'T')).toLocaleString(undefined, {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    },

    serviceChanged() {
        this.selectedDate = '';
        this.selectedSlot = '';
        this.fetchAvailability();
    },

    staffChanged() {
        this.selectedDate = '';
        this.selectedSlot = '';
        this.fetchAvailability();
    },

    previousMonth() {
        const [year, month] = this.month.split('-').map(Number);
        const date = new Date(year, month - 2, 1);
        this.month = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        this.selectedDate = '';
        this.selectedSlot = '';
        this.fetchAvailability();
    },

    nextMonth() {
        const [year, month] = this.month.split('-').map(Number);
        const date = new Date(year, month, 1);
        this.month = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        this.selectedDate = '';
        this.selectedSlot = '';
        this.fetchAvailability();
    },

    selectDate(date) {
        this.selectedDate = date;
        this.selectedSlot = '';
    },

    chooseSlot(slot) {
        this.selectedSlot = slot.starts_at;
    },

    moreSlotsLabel(day) {
        return day.moreSlots ? `+${day.moreSlots} more` : '';
    },

    fetchAvailability() {
        this.error = '';
        this.slotsByDate = {};

        if (!this.serviceId) {
            return Promise.resolve();
        }

        this.loading = true;

        return window.axios.get(this.availabilityUrl, {
            params: {
                service_id: this.serviceId,
                preferred_staff_profile_id: this.staffId || null,
                month: this.month,
            },
        }).then((response) => {
            this.slotsByDate = response.data.dates || {};

            if (this.selectedDate && !this.slotsByDate[this.selectedDate]) {
                this.selectedDate = '';
                this.selectedSlot = '';
            }
        }).catch(() => {
            this.error = 'Availability could not be loaded. Try another service or month.';
        }).finally(() => {
            this.loading = false;
        });
    },
});

Alpine.start();

const loadingElement = document.querySelector('[data-page-loading]');
let loadingFallbackTimer = null;

const showPageLoading = () => {
    if (!loadingElement) {
        return;
    }

    window.clearTimeout(loadingFallbackTimer);
    loadingElement.classList.add('is-visible');
    loadingElement.setAttribute('aria-hidden', 'false');

    loadingFallbackTimer = window.setTimeout(() => {
        hidePageLoading();
    }, 12000);
};

const hidePageLoading = () => {
    if (!loadingElement) {
        return;
    }

    window.clearTimeout(loadingFallbackTimer);
    loadingElement.classList.remove('is-visible');
    loadingElement.setAttribute('aria-hidden', 'true');
};

const isModifiedClick = (event) => {
    return event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;
};

const linkUrl = (link) => {
    try {
        return new URL(link.href, window.location.href);
    } catch {
        return null;
    }
};

const shouldSkipNavigationUrl = (url) => {
    if (!url || url.origin !== window.location.origin) {
        return true;
    }

    if (url.pathname.includes('/export') || url.pathname.includes('/availability')) {
        return true;
    }

    return url.pathname === window.location.pathname
        && url.search === window.location.search
        && Boolean(url.hash);
};

const shouldHandleLink = (link, event) => {
    if (!link || event.defaultPrevented || isModifiedClick(event)) {
        return false;
    }

    if (link.target && link.target !== '_self') {
        return false;
    }

    if (link.hasAttribute('download') || link.hasAttribute('data-no-loading')) {
        return false;
    }

    const url = linkUrl(link);

    if (shouldSkipNavigationUrl(url)) {
        return false;
    }

    return url.href !== window.location.href;
};

const closestLink = (target, selector = 'a[href]') => {
    return target instanceof Element ? target.closest(selector) : null;
};

const panelHost = document.querySelector('[data-panel-host]');
const panelContent = panelHost?.querySelector('[data-panel-content]');
const panelStatus = panelHost?.querySelector('[data-panel-status]');
const panelTitle = panelHost?.querySelector('[data-panel-title]');
const panelDialog = panelHost?.querySelector('.casa-panel');

const panelPathPrefixes = [
    '/admin/appointments/',
    '/admin/customers/',
    '/admin/staff/',
    '/admin/services/',
    '/admin/transactions/',
    '/admin/promotions/',
    '/admin/feedback/',
    '/staff/appointments/',
    '/staff/customers/',
    '/staff/transactions/',
    '/staff/feedback/',
    '/customer/appointments/',
    '/customer/feedback/',
];

const isPanelEligibleUrl = (url) => {
    if (!url || shouldSkipNavigationUrl(url)) {
        return false;
    }

    return panelPathPrefixes.some((prefix) => url.pathname.startsWith(prefix));
};

const isPanelLink = (link) => {
    if (!panelHost || !link || link.hasAttribute('data-no-panel')) {
        return false;
    }

    if (link.target && link.target !== '_self') {
        return false;
    }

    const url = linkUrl(link);

    return link.hasAttribute('data-panel-link') || isPanelEligibleUrl(url);
};

const setPanelLoading = (url) => {
    panelHost.classList.add('is-open', 'is-loading');
    panelHost.setAttribute('aria-hidden', 'false');
    document.body.classList.add('overflow-y-hidden');

    if (panelTitle) {
        panelTitle.textContent = 'Loading';
    }

    if (panelStatus) {
        panelStatus.textContent = `Opening ${url.pathname}`;
    }

    if (panelContent) {
        panelContent.innerHTML = '';
    }

    window.setTimeout(() => panelDialog?.focus(), 20);
};

const closePanel = () => {
    if (!panelHost) {
        return;
    }

    panelHost.classList.remove('is-open', 'is-loading');
    panelHost.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('overflow-y-hidden');

    if (panelContent) {
        panelContent.innerHTML = '';
    }
};

const panelPageHtml = (doc) => {
    const header = doc.querySelector('[data-page-header]');
    const main = doc.querySelector('[data-page-content]');

    if (!main) {
        return null;
    }

    return `
        <div class="casa-panel-page">
            ${header ? `<header>${header.innerHTML}</header>` : ''}
            <main>${main.innerHTML}</main>
        </div>
    `;
};

const openPanel = async (url) => {
    if (!panelHost || !panelContent) {
        window.location.href = url.href;
        return;
    }

    setPanelLoading(url);

    try {
        const response = await window.fetch(url.href, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-Casa-Panel': '1',
            },
        });

        if (!response.ok) {
            throw new Error(`Panel request failed with ${response.status}`);
        }

        const text = await response.text();
        const doc = new DOMParser().parseFromString(text, 'text/html');
        const pageHtml = panelPageHtml(doc);

        if (!pageHtml) {
            window.location.href = url.href;
            return;
        }

        panelContent.innerHTML = pageHtml;
        panelHost.classList.remove('is-loading');

        const heading = panelContent.querySelector('h1, h2');
        if (panelTitle) {
            panelTitle.textContent = heading?.textContent?.trim() || 'Workspace panel';
        }

        window.Alpine?.initTree(panelContent);
        panelContent.querySelector('input, select, textarea, button, a')?.focus({ preventScroll: true });
    } catch {
        window.location.href = url.href;
    }
};

document.addEventListener('click', (event) => {
    const closeTrigger = event.target instanceof Element ? event.target.closest('[data-panel-close], [data-panel-backdrop]') : null;

    if (closeTrigger) {
        event.preventDefault();
        closePanel();
        return;
    }

    const link = closestLink(event.target);

    if (!link || event.defaultPrevented || isModifiedClick(event) || !isPanelLink(link)) {
        return;
    }

    const url = linkUrl(link);

    if (!url) {
        return;
    }

    event.preventDefault();
    openPanel(url);
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && panelHost?.classList.contains('is-open')) {
        closePanel();
    }
});

document.addEventListener('click', (event) => {
    const link = closestLink(event.target);

    if (shouldHandleLink(link, event)) {
        showPageLoading();
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-loading')) {
        return;
    }

    if (form.target && form.target !== '_self') {
        return;
    }

    const actionUrl = form.action ? new URL(form.action, window.location.href) : new URL(window.location.href);

    if (shouldSkipNavigationUrl(actionUrl)) {
        return;
    }

    showPageLoading();
});

window.addEventListener('pageshow', hidePageLoading);

const prefetchedUrls = new Set();

const prefetchLink = (link) => {
    if (!link?.matches('a[data-prefetch][href]')) {
        return;
    }

    const url = linkUrl(link);

    if (shouldSkipNavigationUrl(url) || url.href === window.location.href || prefetchedUrls.has(url.href)) {
        return;
    }

    const prefetch = document.createElement('link');
    prefetch.rel = 'prefetch';
    prefetch.href = url.href;
    prefetch.as = 'document';

    document.head.appendChild(prefetch);
    prefetchedUrls.add(url.href);
};

['mouseover', 'focusin', 'touchstart'].forEach((eventName) => {
    document.addEventListener(eventName, (event) => {
        prefetchLink(closestLink(event.target, 'a[data-prefetch][href]'));
    }, { passive: true });
});
