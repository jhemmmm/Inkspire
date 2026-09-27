'use strict';

/* ================================================================
   INKSPIRE UNIFIED PORTAL — JavaScript
   Handles: Login routing, Frontline Portal (FL), Artist Portal (AR)
   ================================================================ */

const $ = (id) => document.getElementById(id);
const $$ = (sel) => document.querySelectorAll(sel);

// ── Shared File Validation State ─────────────────────────────────
let _valFiles = []; // files staged for validation from upload zone
let _valOverallStatus = null; // 'passed' | 'warned' | 'failed'

// ── Utility Functions ─────────────────────────────────────────────
function generateQueueId() {
    const letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const digits = '0123456789';
    const l = () => letters[Math.floor(Math.random() * letters.length)];
    const d = () => digits[Math.floor(Math.random() * digits.length)];
    return `${l()}${l()}${d()}${d()}${d()}`;
}
function now() {
    return new Date().toLocaleTimeString('en-PH', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    });
}
function today() {
    return new Date().toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

// ══════════════════════════════════════════════════════════════════
//  ACCOUNTING PORTAL (AC)
// ══════════════════════════════════════════════════════════════════

// ── AC Router ────────────────────────────────────────────────────
const AcRouter = (() => {
    const pages = {};
    const navItems = {};
    let current = null;
    return {
        register(id, fn) {
            pages[id] = fn;
        },
        go(id) {
            document
                .querySelectorAll('#ac-app-shell .page')
                .forEach((p) => p.classList.add('hidden'));
            document
                .querySelectorAll("[data-shell='ac']")
                .forEach((n) => n.classList.remove('active'));
            const page = document.getElementById(`ac-page-${id}`);
            if (page) {
                page.classList.remove('hidden');
                page.style.animation = 'fadeIn 0.25s ease';
            }
            const nav = document.querySelector(
                `[data-shell='ac'][data-page='${id}']`,
            );
            if (nav) nav.classList.add('active');
            // Update page title in topnav
            const titleEl = document.getElementById('ac-page-title');
            if (titleEl && nav)
                titleEl.textContent =
                    nav.querySelector('span:last-child')?.textContent || '';
            current = id;
            if (pages[id]) pages[id]();
        },
    };
})();

// ══════════════════════════════════════════════════════════════════
// ACCOUNTING PORTAL — DATA MODEL + ALL 5 CRITICALS
// ══════════════════════════════════════════════════════════════════

// ── AR Data ──────────────────────────────────────────────────────
const acArData = [
    {
        jo: 'JO-2026-0891',
        client: 'Acme Corp',
        entryType: 'Partial Payment', // CRITICAL 4: entry type
        total: 45000,
        paid: 20000,
        balance: 25000,
        daysOut: 15,
        agingBracket: 'Current',
        collectionStatus: 'Pending', // CRITICAL 1
        invoiceDate: 'Apr 11, 2026',
        dueDate: 'May 11, 2026',
        service: 'Tarpaulin Print ×30',
        log: [
            {
                action: 'AR entry created — partial payment recorded',
                date: 'Apr 11, 2026',
                by: 'System',
            },
        ],
    },
    {
        jo: 'JO-2026-0842',
        client: 'Globex Inc',
        entryType: 'Partial Payment',
        total: 12500,
        paid: 0,
        balance: 12500,
        daysOut: 45,
        agingBracket: '30–60 Days',
        collectionStatus: 'Follow-up',
        invoiceDate: 'Apr 8, 2026',
        dueDate: 'May 8, 2026',
        service: 'Tarpaulin Print ×50',
        log: [
            {
                action: 'Follow-up call placed',
                date: 'May 22, 2026',
                by: 'AC001',
            },
            {
                action: 'Reminder sent manually',
                date: 'May 15, 2026',
                by: 'AC001',
            },
            {
                action: 'AR entry created — partial payment recorded',
                date: 'Apr 8, 2026',
                by: 'System',
            },
        ],
    },
    {
        jo: 'JO-2026-0715',
        client: 'Initech',
        entryType: 'Partial Payment',
        total: 8200,
        paid: 1000,
        balance: 7200,
        daysOut: 95,
        agingBracket: '90+ Days',
        collectionStatus: 'Collections',
        invoiceDate: 'Feb 19, 2026',
        dueDate: 'Mar 19, 2026',
        service: 'ID Cards ×100',
        log: [
            {
                action: 'Escalated to collections',
                date: 'May 10, 2026',
                by: 'AC001',
            },
            {
                action: 'Warning notice sent',
                date: 'Apr 20, 2026',
                by: 'AC001',
            },
            {
                action: 'AR entry created — partial payment recorded',
                date: 'Feb 19, 2026',
                by: 'System',
            },
        ],
    },
    {
        jo: 'JO-2026-0880',
        client: 'Stark Industries',
        entryType: 'Partial Payment',
        total: 150000,
        paid: 150000,
        balance: 0,
        daysOut: 0,
        agingBracket: 'Current',
        collectionStatus: 'Paid',
        invoiceDate: 'May 20, 2026',
        dueDate: 'May 27, 2026',
        service: 'Banner ×10 · Large Format',
        log: [
            {
                action: 'Balance settled — AR entry closed',
                date: 'May 25, 2026',
                by: 'System',
            },
            {
                action: 'AR entry created — partial payment recorded',
                date: 'May 20, 2026',
                by: 'System',
            },
        ],
    },
    {
        jo: 'JO-2026-0799',
        client: 'Wayne Ent.',
        entryType: 'Partial Payment',
        total: 65400,
        paid: 30000,
        balance: 35400,
        daysOut: 72,
        agingBracket: '60–90 Days',
        collectionStatus: 'Warning Sent',
        invoiceDate: 'Mar 14, 2026',
        dueDate: 'Apr 14, 2026',
        service: 'Sintra Board Signs ×8',
        log: [
            {
                action: 'Warning notice sent',
                date: 'May 18, 2026',
                by: 'AC001',
            },
            {
                action: 'Follow-up call — no answer',
                date: 'May 5, 2026',
                by: 'AC001',
            },
            {
                action: 'AR entry created — partial payment recorded',
                date: 'Mar 14, 2026',
                by: 'System',
            },
        ],
    },
    // CRITICAL 4: Cancellation Fee Unpaid entry
    {
        jo: 'JO-2026-0923',
        client: 'Doe, Jane A.',
        entryType: 'Cancellation Fee — Unpaid',
        total: 25,
        paid: 0,
        balance: 25,
        daysOut: 8,
        agingBracket: 'Current',
        collectionStatus: 'Pending',
        invoiceDate: 'May 18, 2026',
        dueDate: 'May 25, 2026',
        service: 'Cancellation Fee (Design Started)',
        log: [
            {
                action: 'Cancellation fee logged — client refused payment',
                date: 'May 18, 2026',
                by: 'System',
            },
            {
                action: 'AR entry created — cancellation fee unpaid',
                date: 'May 18, 2026',
                by: 'System',
            },
        ],
    },
];

let acActiveEntry = null;

// ── Helpers ───────────────────────────────────────────────────────
function acAgingBadge(bracket) {
    const m = {
        Current: 'badge-success',
        '30–60 Days': 'badge-warning',
        '60–90 Days': 'badge-warning',
        '90+ Days': 'badge-error',
    };
    return `<span class="badge ${m[bracket] || 'badge-neutral'}">${bracket}</span>`;
}

function acStatusBadge(s) {
    const m = {
        Pending: { cls: 'badge-neutral', dot: '#9ca3af' },
        'Follow-up': { cls: 'badge-primary', dot: 'var(--primary)' },
        'Warning Sent': { cls: 'badge-warning', dot: 'var(--warning)' },
        Collections: { cls: 'badge-error', dot: 'var(--error)' },
        Paid: { cls: 'badge-success', dot: 'var(--success)' },
        'Written Off': { cls: 'badge-neutral', dot: '#6b7280' },
    };
    const v = m[s] || m['Pending'];
    return `<span class="badge ${v.cls} badge-dot">${s}</span>`;
}

function acEntryTypeBadge(t) {
    if (t === 'Cancellation Fee — Unpaid')
        return `<span style="font-family:var(--font-mono);font-size:10px;font-weight:600;padding:2px 8px;border-radius:var(--radius-full);background:var(--error-container);color:var(--error);border:1px solid var(--error);">${t}</span>`;
    return `<span style="font-family:var(--font-mono);font-size:10px;font-weight:600;padding:2px 8px;border-radius:var(--radius-full);background:var(--surface-container);color:var(--on-surface-variant);border:1px solid var(--outline-variant);">${t}</span>`;
}

function acFmt(n) {
    return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2 });
}

function acToday() {
    return new Date().toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

// ── CRITICAL 5: Date range filter helpers ─────────────────────────
function acGetDateFilter(prefix) {
    const from = document.getElementById(prefix + '-date-from')?.value;
    const to = document.getElementById(prefix + '-date-to')?.value;
    return { from, to };
}

// ── AR Dashboard render ───────────────────────────────────────────
function acRenderDashboard() {
    const searchEl = document.getElementById('ac-dash-search');
    const agingEl = document.getElementById('ac-dash-aging');
    const typeEl = document.getElementById('ac-dash-type');
    const search = searchEl?.value.toLowerCase() || '';
    const aging = agingEl?.value || '';
    const type = typeEl?.value || '';

    const filtered = acArData.filter((r) => {
        const matchSearch =
            !search ||
            r.jo.toLowerCase().includes(search) ||
            r.client.toLowerCase().includes(search);
        const matchAging = !aging || r.agingBracket === aging;
        const matchType = !type || r.entryType === type;
        return matchSearch && matchAging && matchType;
    });

    const tbody = document.getElementById('ac-dash-tbody');
    if (!tbody) return;

    if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:24px;color:var(--outline);">No AR entries found.</td></tr>`;
        return;
    }

    tbody.innerHTML = filtered
        .map((r) => {
            const isWrittenOff = r.collectionStatus === 'Written Off';
            const isCancFee = r.entryType === 'Cancellation Fee — Unpaid';
            const rowStyle = isWrittenOff
                ? 'opacity:0.55;'
                : isCancFee
                  ? 'background:rgba(186,26,26,0.03);'
                  : '';
            return `<tr style="border-bottom:1px solid var(--outline-variant);cursor:pointer;${rowStyle}"
      onmouseover="this.style.background='var(--surface-container-low)'"
      onmouseout="this.style.background='${isCancFee ? 'rgba(186,26,26,0.03)' : ''}'"
      onclick="acOpenEntry('${r.jo}')">
      <td style="padding:12px 16px;font-family:var(--font-mono);font-size:12px;color:var(--primary);font-weight:600;">${r.jo}</td>
      <td style="padding:12px 16px;font-weight:600;">${r.client}</td>
      <td style="padding:12px 16px;">${acEntryTypeBadge(r.entryType)}</td>
      <td style="padding:12px 16px;text-align:right;font-family:var(--font-mono);font-size:12px;">${acFmt(r.total)}</td>
      <td style="padding:12px 16px;text-align:right;font-family:var(--font-mono);font-size:12px;">${acFmt(r.paid)}</td>
      <td style="padding:12px 16px;text-align:right;font-family:var(--font-mono);font-size:12px;font-weight:700;${r.balance > 0 ? 'color:var(--error);' : 'color:var(--success);'}">${acFmt(r.balance)}</td>
      <td style="padding:12px 16px;text-align:right;font-family:var(--font-mono);font-size:12px;">${r.daysOut}</td>
      <td style="padding:12px 16px;">${acAgingBadge(r.agingBracket)}</td>
      <td style="padding:12px 16px;">${acStatusBadge(r.collectionStatus)}</td>
    </tr>`;
        })
        .join('');
}

// ── AR Entry Detail ───────────────────────────────────────────────
function acOpenEntry(joNum) {
    const r = acArData.find((x) => x.jo === joNum);
    if (!r) return;
    acActiveEntry = r;
    AcRouter.go('ac-receivables');
}

function acRenderEntryDetail() {
    const r = acActiveEntry;
    if (!r) return;

    // Header
    const titleEl = document.getElementById('ac-entry-title');
    const subEl = document.getElementById('ac-entry-sub');
    if (titleEl) titleEl.textContent = r.jo + ' · ' + r.client;
    if (subEl) subEl.textContent = r.agingBracket + ' · ' + r.collectionStatus;

    // Amounts
    const setT = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    };
    setT('ac-entry-total', acFmt(r.total));
    setT('ac-entry-paid', acFmt(r.paid));
    setT('ac-entry-balance', acFmt(r.balance));
    const balEl = document.getElementById('ac-entry-balance');
    if (balEl)
        balEl.style.color = r.balance > 0 ? 'var(--error)' : 'var(--success)';

    // Details grid
    setT('ac-entry-invoice-date', r.invoiceDate);
    setT('ac-entry-due-date', r.dueDate);
    setT('ac-entry-days-out', r.daysOut);
    setT('ac-entry-service', r.service);

    // Entry type badge
    const typeEl = document.getElementById('ac-entry-type-badge');
    if (typeEl) typeEl.innerHTML = acEntryTypeBadge(r.entryType);

    // CRITICAL 1: collection status dropdown
    const sel = document.getElementById('ac-status-select');
    if (sel) sel.value = r.collectionStatus;

    // Status badge in header
    const sbEl = document.getElementById('ac-entry-status-badge');
    if (sbEl) sbEl.innerHTML = acStatusBadge(r.collectionStatus);

    // CRITICAL 2: Written Off section visibility
    const woSection = document.getElementById('ac-written-off-section');
    if (woSection)
        woSection.classList.toggle(
            'hidden',
            r.collectionStatus !== 'Written Off' &&
                r.collectionStatus !== 'Collections',
        );

    // Activity log
    acRenderActivityLog();

    // Hide success banners
    [
        'ac-status-success',
        'ac-rec-success',
        'ac-adj-success',
        'ac-log-success',
        'ac-wo-success',
    ].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.classList.add('hidden');
    });
}

// ── CRITICAL 1: Update Collection Status ─────────────────────────
function acUpdateCollectionStatus() {
    if (!acActiveEntry) return;
    const sel = document.getElementById('ac-status-select');
    if (!sel) return;
    const newStatus = sel.value;
    const old = acActiveEntry.collectionStatus;
    acActiveEntry.collectionStatus = newStatus;

    // Log it
    acActiveEntry.log.unshift({
        action: `Collection status updated: "${old}" → "${newStatus}"`,
        date: acToday(),
        by: 'AC001',
    });

    // Refresh badge + sub header
    const sbEl = document.getElementById('ac-entry-status-badge');
    if (sbEl) sbEl.innerHTML = acStatusBadge(newStatus);
    const subEl = document.getElementById('ac-entry-sub');
    if (subEl)
        subEl.textContent = acActiveEntry.agingBracket + ' · ' + newStatus;

    // CRITICAL 2: show/hide written off section
    const woSection = document.getElementById('ac-written-off-section');
    if (woSection)
        woSection.classList.toggle(
            'hidden',
            newStatus !== 'Written Off' && newStatus !== 'Collections',
        );

    acRenderActivityLog();

    const banner = document.getElementById('ac-status-success');
    if (banner) {
        banner.innerHTML = `<span class="material-symbols-outlined">check_circle</span> Collection status updated to <strong>${newStatus}</strong>. Logged in audit trail.`;
        banner.classList.remove('hidden');
        setTimeout(() => banner.classList.add('hidden'), 4000);
    }
}

// ── CRITICAL 2: Written Off Request ──────────────────────────────
function acRequestWriteOff() {
    if (!acActiveEntry) return;
    const reason = document.getElementById('ac-wo-reason')?.value.trim();
    if (!reason) {
        alert(
            'Please enter a reason for the write-off request before submitting.',
        );
        return;
    }
    // Flag as pending write-off (Owner approves)
    acActiveEntry.log.unshift({
        action: `Write-off requested — Amount: ${acFmt(acActiveEntry.balance)} — Reason: "${reason}" — Pending Owner/Admin approval`,
        date: acToday(),
        by: 'AC001',
    });

    const woSection = document.getElementById('ac-written-off-section');
    if (woSection) {
        woSection.innerHTML = `
      <div style="padding:14px 16px;background:#fef9c3;border:1px solid #fde68a;border-radius:var(--radius-md);display:flex;align-items:flex-start;gap:10px;">
        <span class="material-symbols-outlined" style="color:#d97706;font-size:18px;margin-top:1px;">schedule</span>
        <div style="font-size:13px;">
          <div style="font-weight:700;color:#92400e;">Write-Off Request Submitted</div>
          <div style="color:#78350f;margin-top:2px;">Pending Owner/Admin approval. Amount: <strong>${acFmt(acActiveEntry.balance)}</strong>. Once approved, JO will be marked Written Off and logged in the audit trail.</div>
        </div>
      </div>`;
    }

    acRenderActivityLog();

    const banner = document.getElementById('ac-wo-success');
    if (banner) {
        banner.innerHTML = `<span class="material-symbols-outlined">check_circle</span> Write-off request submitted for Owner/Admin approval.`;
        banner.classList.remove('hidden');
        setTimeout(() => banner.classList.add('hidden'), 5000);
    }
}

// ── CRITICAL 3: Payment Adjustment ───────────────────────────────
function acToggleAdjModal(show) {
    const modal = document.getElementById('ac-adj-modal');
    if (modal) modal.classList.toggle('hidden', !show);
    if (show) {
        const amtEl = document.getElementById('ac-adj-amount');
        if (amtEl) amtEl.value = '';
        const typeEl = document.getElementById('ac-adj-type');
        if (typeEl) typeEl.value = 'Credit Memo';
        const noteEl = document.getElementById('ac-adj-note');
        if (noteEl) noteEl.value = '';
    }
}

function acConfirmAdjustment() {
    if (!acActiveEntry) return;
    const amt =
        parseFloat(document.getElementById('ac-adj-amount')?.value) || 0;
    const type = document.getElementById('ac-adj-type')?.value || 'Credit Memo';
    const note = document.getElementById('ac-adj-note')?.value.trim();

    if (!amt || amt <= 0) {
        alert('Please enter a valid adjustment amount.');
        return;
    }
    if (!note) {
        alert('Please enter a reason or note for this adjustment.');
        return;
    }

    // Apply adjustment
    if (type === 'Credit Memo' || type === 'Overpayment Correction') {
        acActiveEntry.paid = Math.min(
            acActiveEntry.paid + amt,
            acActiveEntry.total,
        );
        acActiveEntry.balance = Math.max(
            acActiveEntry.total - acActiveEntry.paid,
            0,
        );
    } else if (type === 'Write-down') {
        acActiveEntry.total = Math.max(
            acActiveEntry.total - amt,
            acActiveEntry.paid,
        );
        acActiveEntry.balance = acActiveEntry.total - acActiveEntry.paid;
    }

    // If balance cleared, mark paid
    if (
        acActiveEntry.balance === 0 &&
        acActiveEntry.collectionStatus !== 'Paid'
    ) {
        acActiveEntry.collectionStatus = 'Paid';
    }

    acActiveEntry.log.unshift({
        action: `Payment adjustment applied — Type: ${type} · Amount: ${acFmt(amt)} · Note: "${note}"`,
        date: acToday(),
        by: 'AC001',
    });

    acToggleAdjModal(false);
    acRenderEntryDetail();

    const banner = document.getElementById('ac-adj-success');
    if (banner) {
        banner.innerHTML = `<span class="material-symbols-outlined">check_circle</span> Adjustment of ${acFmt(amt)} applied. Balance updated. Logged in audit trail.`;
        banner.classList.remove('hidden');
        setTimeout(() => banner.classList.add('hidden'), 5000);
    }
}

// ── Activity Log render ───────────────────────────────────────────
function acRenderActivityLog() {
    const r = acActiveEntry;
    if (!r) return;
    const container = document.getElementById('ac-activity-log');
    if (!container) return;
    container.innerHTML = r.log
        .map(
            (l, i) => `
    <div style="padding:var(--space-3);background:var(--surface-container-low);border:1px solid var(--outline-variant);border-radius:var(--radius);${i === 0 ? 'border-left:3px solid var(--primary);' : ''}">
      <div style="font-weight:600;font-size:13px;">${l.action}</div>
      <div style="font-family:var(--font-mono);font-size:11px;color:var(--on-surface-variant);margin-top:2px;">${l.date} · ${l.by}</div>
    </div>`,
        )
        .join('');
}

// ── MAJOR 2: Record Payment ───────────────────────────────────────
function acToggleRecModal(show) {
    const modal = document.getElementById('ac-rec-modal');
    if (!modal) return;
    modal.classList.toggle('hidden', !show);
    if (show && acActiveEntry) {
        const r = acActiveEntry;
        const subEl = document.getElementById('ac-rec-modal-sub');
        if (subEl) subEl.textContent = r.jo + ' — balance settlement';
        const clientEl = document.getElementById('ac-rec-client');
        if (clientEl) clientEl.textContent = r.client;
        const balEl = document.getElementById('ac-rec-balance-display');
        if (balEl) balEl.textContent = acFmt(r.balance);
        const amtEl = document.getElementById('ac-rec-amount');
        if (amtEl) {
            amtEl.value = '';
            amtEl.max = r.balance;
        }
        const refEl = document.getElementById('ac-rec-ref');
        if (refEl) refEl.value = '';
        // Reset preview
        document.getElementById('ac-rec-prev-amt').textContent = '₱0.00';
        document.getElementById('ac-rec-prev-bal').textContent = acFmt(
            r.balance,
        );
        document.getElementById('ac-rec-prev-status').textContent = '—';
    }
}

function acRecPreview() {
    if (!acActiveEntry) return;
    const amt =
        parseFloat(document.getElementById('ac-rec-amount')?.value) || 0;
    const remaining = Math.max(acActiveEntry.balance - amt, 0);
    const prevAmtEl = document.getElementById('ac-rec-prev-amt');
    const prevBalEl = document.getElementById('ac-rec-prev-bal');
    const prevStatusEl = document.getElementById('ac-rec-prev-status');
    if (prevAmtEl) prevAmtEl.textContent = acFmt(amt);
    if (prevBalEl) {
        prevBalEl.textContent = acFmt(remaining);
        prevBalEl.style.color =
            remaining === 0 ? 'var(--success)' : 'var(--error)';
    }
    if (prevStatusEl) {
        const status =
            remaining === 0
                ? 'AR Entry Closed — Fully Paid ✓'
                : 'Partially Paid — Balance Remaining';
        prevStatusEl.textContent = status;
        prevStatusEl.style.color =
            remaining === 0 ? 'var(--success)' : 'var(--warning)';
    }
}

function acConfirmRecPayment() {
    if (!acActiveEntry) return;
    const amt =
        parseFloat(document.getElementById('ac-rec-amount')?.value) || 0;
    const method = document.getElementById('ac-rec-method')?.value || 'Cash';
    const ref = document.getElementById('ac-rec-ref')?.value.trim() || '';

    if (!amt || amt <= 0) {
        alert('Please enter a valid amount received.');
        return;
    }
    if (amt > acActiveEntry.balance) {
        alert('Amount received must not exceed the outstanding balance.');
        return;
    }

    // Apply payment
    acActiveEntry.paid += amt;
    acActiveEntry.balance = Math.max(acActiveEntry.balance - amt, 0);
    const fullyClosed = acActiveEntry.balance === 0;

    // Auto-close if fully paid
    if (fullyClosed) {
        acActiveEntry.collectionStatus = 'Paid';
        acActiveEntry.daysOut = 0;
    }

    // Log it
    const logMsg = fullyClosed
        ? `Payment received: ${acFmt(amt)} via ${method}${ref ? ' — Ref: ' + ref : ''} — AR entry CLOSED · JO status updated to Fully Paid`
        : `Partial payment received: ${acFmt(amt)} via ${method}${ref ? ' — Ref: ' + ref : ''} — Remaining balance: ${acFmt(acActiveEntry.balance)}`;

    acActiveEntry.log.unshift({
        action: logMsg,
        date: acToday(),
        by: 'AC001',
    });

    acToggleRecModal(false);
    acRenderEntryDetail();

    const banner = document.getElementById('ac-rec-success');
    if (banner) {
        const msg = fullyClosed
            ? `<span class="material-symbols-outlined">check_circle</span> Payment of ${acFmt(amt)} confirmed. AR entry closed — JO updated to Fully Paid. Posted to Sales Ledger.`
            : `<span class="material-symbols-outlined">check_circle</span> Payment of ${acFmt(amt)} recorded. Remaining balance: ${acFmt(acActiveEntry.balance)}. Logged in Audit Trail.`;
        banner.innerHTML = msg;
        banner.classList.remove('hidden');
        setTimeout(() => banner.classList.add('hidden'), 6000);
    }
}

// ── MAJOR 7: Log Contact Attempt ─────────────────────────────────
function acToggleLogContactModal(show) {
    const modal = document.getElementById('ac-log-contact-modal');
    if (!modal) return;
    modal.classList.toggle('hidden', !show);
    if (show) {
        const methodEl = document.getElementById('ac-log-method');
        const outcomeEl = document.getElementById('ac-log-outcome');
        const noteEl = document.getElementById('ac-log-note');
        if (methodEl) methodEl.value = 'Phone Call';
        if (outcomeEl) outcomeEl.value = 'No answer';
        if (noteEl) noteEl.value = '';
    }
}

function acConfirmLogContact() {
    if (!acActiveEntry) return;
    const method =
        document.getElementById('ac-log-method')?.value || 'Phone Call';
    const outcome =
        document.getElementById('ac-log-outcome')?.value || 'No answer';
    const note = document.getElementById('ac-log-note')?.value.trim();

    const logMsg = `Contact attempt — ${method}: ${outcome}${note ? ' · ' + note : ''}`;
    acActiveEntry.log.unshift({
        action: logMsg,
        date: acToday(),
        by: 'AC001',
    });

    acToggleLogContactModal(false);
    acRenderActivityLog();

    const banner = document.getElementById('ac-log-success');
    if (banner) {
        banner.innerHTML = `<span class="material-symbols-outlined">check_circle</span> Contact attempt logged: <strong>${method}</strong> — ${outcome}. Added to Activity Log.`;
        banner.classList.remove('hidden');
        setTimeout(() => banner.classList.add('hidden'), 4000);
    }
}

// ── Router registrations ──────────────────────────────────────────
AcRouter.register('ac-dashboard', () => {
    acRenderDashboard();
    // Wire search/filter controls
    ['ac-dash-search', 'ac-dash-aging', 'ac-dash-type'].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.oninput = el.onchange = acRenderDashboard;
    });
    // Date range filter buttons
    const applyBtn = document.getElementById('ac-dash-apply-date');
    if (applyBtn) applyBtn.onclick = acRenderDashboard;
});

AcRouter.register('ac-aging', () => {
    const applyBtn = document.getElementById('ac-aging-apply-date');
    if (applyBtn)
        applyBtn.onclick = () => {
            const { from, to } = acGetDateFilter('ac-aging');
            const sub = document.getElementById('ac-aging-date-sub');
            if (sub)
                sub.textContent =
                    from && to ? `${from} to ${to}` : 'As of today';
        };
});

AcRouter.register('ac-receivables', () => {
    acRenderEntryDetail();
    // Wire status update button
    const updateBtn = document.getElementById('ac-status-update-btn');
    if (updateBtn) updateBtn.onclick = acUpdateCollectionStatus;
    // Wire write-off button
    const woBtn = document.getElementById('ac-wo-submit-btn');
    if (woBtn) woBtn.onclick = acRequestWriteOff;
    // Wire adjustment modal
    const adjBtn = document.getElementById('ac-adj-open-btn');
    if (adjBtn) adjBtn.onclick = () => acToggleAdjModal(true);
    const adjCancelBtn = document.getElementById('ac-adj-cancel-btn');
    if (adjCancelBtn) adjCancelBtn.onclick = () => acToggleAdjModal(false);
    const adjConfirmBtn = document.getElementById('ac-adj-confirm-btn');
    if (adjConfirmBtn) adjConfirmBtn.onclick = acConfirmAdjustment;
    // MAJOR 2: Wire Record Payment modal
    const recOpenBtn = document.getElementById('ac-rec-open-btn');
    if (recOpenBtn) recOpenBtn.onclick = () => acToggleRecModal(true);
    const recCancelBtn = document.getElementById('ac-rec-cancel-btn');
    if (recCancelBtn) recCancelBtn.onclick = () => acToggleRecModal(false);
    const recConfirmBtn = document.getElementById('ac-rec-confirm-btn');
    if (recConfirmBtn) recConfirmBtn.onclick = acConfirmRecPayment;
    // MAJOR 7: Wire Log Contact Attempt modal
    const logOpenBtn = document.getElementById('ac-log-contact-open-btn');
    if (logOpenBtn) logOpenBtn.onclick = () => acToggleLogContactModal(true);
    const logCancelBtn = document.getElementById('ac-log-contact-cancel-btn');
    if (logCancelBtn)
        logCancelBtn.onclick = () => acToggleLogContactModal(false);
    const logConfirmBtn = document.getElementById('ac-log-contact-confirm-btn');
    if (logConfirmBtn) logConfirmBtn.onclick = acConfirmLogContact;
});

AcRouter.register('ac-reports', () => {});

// ══════════════════════════════════════════════════════════════════

// ── Logout ────────────────────────────────────────────────────────
function doLogout() {
    location.reload();
}

// ══════════════════════════════════════════════════════════════════
//  UNIFIED LOGIN
// ══════════════════════════════════════════════════════════════════
function initLogin() {
    const pwdInput = $('login-password');
    const togglePwd = $('toggle-password');
    const usernameInput = $('login-username');
    const badge = $('login-role-badge');
    const subText = $('login-sub-text');
    const icon = $('login-icon');

    // Live role detection as user types
    usernameInput.addEventListener('input', () => {
        const val = usernameInput.value.trim().toUpperCase();
        if (val.startsWith('FL') || val === 'FRONTLINE') {
            badge.textContent = 'Frontline Staff Portal';
            badge.style.background = 'var(--primary-fixed)';
            badge.style.color = 'var(--primary-dark)';
            icon.textContent = 'badge';
        } else if (val.startsWith('AR') || val === 'ARTIST') {
            badge.textContent = 'Artist Portal';
            badge.style.background = '#fef3c7';
            badge.style.color = '#92400e';
            icon.textContent = 'palette';
        } else if (val.startsWith('CS') || val === 'CASHIER') {
            badge.textContent = 'Cashier Portal';
            badge.style.background = '#e0e7ff';
            badge.style.color = '#3730a3';
            icon.textContent = 'point_of_sale';
        } else if (val.startsWith('PD') || val === 'PRODUCTION') {
            badge.textContent = 'Production Portal';
            badge.style.background = '#dcfce7';
            badge.style.color = '#166634';
            icon.textContent = 'precision_manufacturing';
        } else if (val.startsWith('AC') || val === 'ACCOUNTING') {
            badge.textContent = 'Accounting Portal';
            badge.style.background = '#f3f4f6';
            badge.style.color = '#111827';
            icon.textContent = 'account_balance';
        } else if (val.startsWith('AD') || val === 'ADMIN') {
            badge.textContent = 'Admin Portal';
            badge.style.background = '#e0e7ff';
            badge.style.color = '#3730a3';
            icon.textContent = 'shield';
        } else {
            badge.textContent = 'Staff Portal';
            badge.style.background = 'var(--primary-fixed)';
            badge.style.color = 'var(--primary-dark)';
            icon.textContent = 'badge';
        }
    });

    togglePwd.addEventListener('click', () => {
        const isText = pwdInput.type === 'text';
        pwdInput.type = isText ? 'password' : 'text';
        togglePwd.textContent = isText ? 'visibility' : 'visibility_off';
    });

    $('login-form').addEventListener('submit', (e) => {
        e.preventDefault();
        const user = usernameInput.value.trim();
        const pass = pwdInput.value.trim();

        if (!user || !pass) {
            showLoginError('Please enter your Staff ID and Access Code.');
            return;
        }

        const isFrontline =
            (user === 'FL001' || user.toLowerCase() === 'frontline') &&
            (pass === '1234' || pass === 'demo');
        const isArtist =
            (user === 'AR001' || user.toLowerCase() === 'artist') &&
            (pass === '1234' || pass === 'demo');
        const isCashier =
            (user === 'CS001' || user.toLowerCase() === 'cashier') &&
            (pass === '1234' || pass === 'demo');
        const isProduction =
            (user === 'PD001' || user.toLowerCase() === 'production') &&
            (pass === '1234' || pass === 'demo');
        const isAccounting =
            (user === 'AC001' || user.toLowerCase() === 'accounting') &&
            (pass === '1234' || pass === 'demo');
        const isAdmin =
            (user === 'AD001' || user.toLowerCase() === 'admin') &&
            (pass === '1234' || pass === 'demo');

        if (isFrontline) {
            transitionToPortal('fl-app-shell', () => {
                FlRouter.go('registration');
            });
        } else if (isArtist) {
            transitionToPortal('ar-app-shell', () => {
                ArRouter.go('dashboard');
            });
        } else if (isCashier) {
            transitionToPortal('cs-app-shell', () => {
                CsRouter.go('pos');
            });
        } else if (isProduction) {
            transitionToPortal('pd-app-shell', () => {
                PdRouter.go('prod-queue');
            });
        } else if (isAccounting) {
            transitionToPortal('ac-app-shell', () => {
                AcRouter.go('ac-dashboard');
            });
        } else if (isAdmin) {
            transitionToPortal('ad-app-shell', () => {
                AdRouter.go('ad-dashboard');
            });
        } else {
            showLoginError(
                'Invalid credentials. Try FL001 / 1234, AR001 / 1234, CS001 / 1234, PD001 / 1234, AC001 / 1234, or AD001 / 1234',
            );
        }
    });

    function transitionToPortal(shellId, afterFn) {
        const lp = $('login-page');
        lp.style.opacity = '0';
        lp.style.transform = 'translateY(-8px)';
        lp.style.transition = 'all 0.3s ease';
        setTimeout(() => {
            lp.classList.add('hidden');
            $(shellId).classList.remove('hidden');
            afterFn();
        }, 300);
    }

    function showLoginError(msg) {
        const err = $('login-error');
        err.textContent = msg;
        err.classList.remove('hidden');
        setTimeout(() => err.classList.add('hidden'), 4000);
    }
}

// ══════════════════════════════════════════════════════════════════
//  FRONTLINE ROUTER
// ══════════════════════════════════════════════════════════════════
const FlRouter = {
    pages: {},
    currentPage: null,
    register(name, fn) {
        this.pages[name] = fn;
    },
    go(name) {
        $$("[id^='fl-page-']").forEach((p) => p.classList.add('hidden'));
        const target = $(`fl-page-${name}`);
        if (target) {
            target.classList.remove('hidden');
            target.classList.add('animate-in');
        }
        $$("[data-shell='fl']").forEach((n) => n.classList.remove('active'));
        const navItem = document.querySelector(
            `[data-page="${name}"][data-shell="fl"]`,
        );
        if (navItem) navItem.classList.add('active');
        const titles = {
            registration: 'Customer Registration',
            queue: 'Queue Monitor',
            clientsearch: 'Client Search',
            validation: 'File Validation',
            'order-release': 'Order Release',
        };
        const titleEl = $('fl-page-title');
        if (titleEl) titleEl.textContent = titles[name] || '';
        this.currentPage = name;
        if (this.pages[name]) this.pages[name]();
    },
};

// ── Registration Page ──────────────────────────────────────────────
FlRouter.register('registration', initRegistration);
let _regInitialized = false;

function initRegistration() {
    if (_regInitialized) return;
    _regInitialized = true;

    const typeOptions = $$('.type-option');
    const typeASection = $('type-a-section');
    const queueDisplay = $('queue-id-value');
    const uploadZone = $('upload-zone');
    const fileInput = $('file-input');
    const fileList = $('file-list');
    const submitBtn = $('submit-registration');
    const successBanner = $('reg-success');

    let selectedType = 'A';
    let uploadedFiles = [];

    typeOptions.forEach((opt) => {
        opt.addEventListener('click', () => {
            typeOptions.forEach((o) => o.classList.remove('selected'));
            opt.classList.add('selected');
            opt.querySelector('input[type="radio"]').checked = true;
            selectedType = opt.dataset.type;
            if (selectedType === 'A') {
                typeASection.style.maxHeight = typeASection.scrollHeight + 'px';
                typeASection.style.opacity = '1';
                typeASection.classList.remove('slide-up-hide');
            } else {
                typeASection.style.maxHeight = '0';
                typeASection.style.opacity = '0';
                typeASection.style.overflow = 'hidden';
                typeASection.style.transition =
                    'max-height 0.3s ease, opacity 0.2s ease';
            }
            queueDisplay.textContent = '—';
        });
    });

    typeASection.style.maxHeight = '1000px';
    typeASection.style.transition = 'max-height 0.3s ease, opacity 0.2s ease';

    uploadZone.addEventListener('click', () => fileInput.click());
    uploadZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadZone.classList.add('dragover');
    });
    uploadZone.addEventListener('dragleave', () =>
        uploadZone.classList.remove('dragover'),
    );
    uploadZone.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadZone.classList.remove('dragover');
        handleFiles(e.dataTransfer.files);
    });
    fileInput.addEventListener('change', (e) => handleFiles(e.target.files));

    function handleFiles(files) {
        Array.from(files).forEach((f) => {
            if (uploadedFiles.length >= 5) return;
            if (uploadedFiles.find((x) => x.name === f.name)) return;
            uploadedFiles.push(f);
        });

        // Sync to global state immediately
        _valFiles = uploadedFiles.slice();
        renderFileList();

        // Show spinner notice, then navigate to validation
        if (uploadedFiles.length > 0) {
            const notice = document.getElementById('val-running-notice');
            if (notice) notice.classList.remove('hidden');

            // Give the browser one full render frame to paint the file list,
            // then navigate on the NEXT animation frame after a short delay
            requestAnimationFrame(() => {
                setTimeout(() => {
                    if (notice) notice.classList.add('hidden');
                    FlRouter.go('validation');
                }, 700);
            });
        }
    }

    function renderFileList() {
        if (!uploadedFiles.length) {
            fileList.innerHTML = '';
            return;
        }
        fileList.innerHTML = uploadedFiles
            .map(
                (f, i) => `
      <div class="file-item" style="display:flex;align-items:center;gap:12px;padding:10px 14px;background:var(--surface-container-low);border:1px solid var(--outline-variant);border-radius:8px;margin-top:8px;">
        <span class="material-symbols-outlined" style="color:var(--primary);font-size:20px;">description</span>
        <div style="flex:1;min-width:0;"><div style="font-size:13px;font-weight:600;color:var(--on-surface);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${f.name}</div><div style="font-size:11px;color:var(--on-surface-variant);font-family:var(--font-mono);">${(f.size / 1024 / 1024).toFixed(2)} MB</div></div>
        <button onclick="removeFile(${i})" style="color:var(--outline);font-size:18px;padding:4px;border-radius:4px;" class="material-symbols-outlined">close</button>
      </div>
    `,
            )
            .join('');
    }

    window.removeFile = function (i) {
        uploadedFiles.splice(i, 1);
        _valFiles = [...uploadedFiles]; // keep in sync
        renderFileList();
    };

    submitBtn.addEventListener('click', () => {
        const fullName = $('field-fullname').value.trim();
        const contact = $('field-contact').value.trim();
        const errors = [];
        if (!fullName) errors.push('Full Name is required.');
        if (!contact) errors.push('Contact Number is required.');
        if (selectedType === 'A' && uploadedFiles.length === 0)
            errors.push('Type A requires at least one source file.');

        if (errors.length) {
            let banner = $('reg-validation-error');
            if (!banner) {
                banner = document.createElement('div');
                banner.id = 'reg-validation-error';
                banner.style.cssText =
                    'margin-bottom:16px;padding:14px 18px;background:var(--error-container);border:1px solid #f87171;border-radius:var(--radius-md);display:flex;align-items:flex-start;gap:12px;animation:fadeIn 0.2s ease;';
                banner.innerHTML = `<span class="material-symbols-outlined" style="color:#93000a;font-size:20px;flex-shrink:0;margin-top:1px;">error</span><div id="reg-validation-error-list" style="font-size:13px;font-weight:500;color:#93000a;"></div>`;
                successBanner.insertAdjacentElement('afterend', banner);
            }
            $('reg-validation-error-list').innerHTML = errors
                .map((e) => `<div>· ${e}</div>`)
                .join('');
            banner.style.display = 'flex';
            banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            return;
        }

        const errBanner = $('reg-validation-error');
        if (errBanner) errBanner.style.display = 'none';

        const id = generateQueueId();
        queueDisplay.textContent = id;
        queueDisplay.style.transition = 'transform 0.2s ease';
        queueDisplay.style.transform = 'scale(1.1)';
        setTimeout(() => {
            queueDisplay.style.transform = 'scale(1)';
        }, 200);
        $('queue-id-sub').textContent = `Generated ${now()} · ${today()}`;

        successBanner.classList.remove('hidden');
        successBanner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        $('success-queue-id').textContent = id;

        // ── Differentiated success messaging ──────────────────────────
        const detailRow = $('success-detail-row');
        if (selectedType === 'A') {
            detailRow.innerHTML = `
        Queue ID: <strong>${id}</strong>
        <span style="display:block;margin-top:4px;">
          <strong style="color:var(--success);">✓ Type A — For Printing</strong>
          &nbsp;·&nbsp; File validated &amp; queued directly to production
          &nbsp;·&nbsp; <em>No artist consultation required</em>
          &nbsp;·&nbsp; Customer: <strong>${fullName}</strong>
        </span>`;
        } else {
            detailRow.innerHTML = `
        Queue ID: <strong>${id}</strong>
        <span style="display:block;margin-top:4px;">
          <strong style="color:var(--success);">✓ Type B — Consultation</strong>
          &nbsp;·&nbsp; Queued for next available artist
          &nbsp;·&nbsp; <em>Wait for queue number to be called at the counter</em>
          &nbsp;·&nbsp; Customer: <strong>${fullName}</strong>
        </span>`;
        }

        setTimeout(() => {
            successBanner.classList.add('hidden');
            resetForm();
        }, 6000);
    });

    function resetForm() {
        $('reg-form').reset();
        typeOptions.forEach((o, i) => {
            if (i === 0) o.classList.add('selected');
            else o.classList.remove('selected');
        });
        selectedType = 'A';
        typeASection.style.maxHeight = '1000px';
        typeASection.style.opacity = '1';
        queueDisplay.textContent = '—';
        $('queue-id-sub').textContent = '';
        uploadedFiles = [];
        _valFiles = [];
        renderFileList();
    }
}

// ── Queue Monitor ──────────────────────────────────────────────────
const queueState = [
    {
        id: 'ART-01',
        name: 'Maria Santos',
        ticket: 'AA203',
        status: 'busy',
        customer: 'Jose R.',
        since: '09:14 AM',
    },
    {
        id: 'ART-02',
        name: 'Carlo Reyes',
        ticket: 'AA204',
        status: 'busy',
        customer: 'Luna T.',
        since: '09:22 AM',
    },
    {
        id: 'ART-03',
        name: 'Ana Dela Cruz',
        ticket: null,
        status: 'available',
        customer: null,
        since: null,
    },
    {
        id: 'ART-04',
        name: 'Ben Navarro',
        ticket: 'AA205',
        status: 'busy',
        customer: 'Cris M.',
        since: '09:30 AM',
    },
    {
        id: 'ART-05',
        name: 'Liza Gomez',
        ticket: null,
        status: 'available',
        customer: null,
        since: null,
    },
    {
        id: 'ART-06',
        name: 'Ryan Pascual',
        ticket: 'AA206',
        status: 'busy',
        customer: 'Dave P.',
        since: '09:45 AM',
    },
];

let queueTimer = null;

FlRouter.register('queue', initQueueMonitor);

function initQueueMonitor() {
    renderQueueTable();
    $('queue-last-update').textContent = `Last updated: ${now()}`;
    if (queueTimer) clearInterval(queueTimer);
    queueTimer = setInterval(() => {
        $('queue-last-update').textContent = `Last updated: ${now()}`;
        if (Math.random() > 0.85) {
            const idx = Math.floor(Math.random() * queueState.length);
            queueState[idx].status =
                queueState[idx].status === 'busy' ? 'available' : 'busy';
            if (queueState[idx].status === 'available') {
                queueState[idx].ticket = null;
                queueState[idx].customer = null;
                queueState[idx].since = null;
            } else {
                queueState[idx].ticket =
                    'AA' + (200 + Math.floor(Math.random() * 99));
                queueState[idx].since = now();
            }
            renderQueueTable();
        }
    }, 5000);
    $('queue-refresh-btn').addEventListener('click', () => {
        renderQueueTable();
        $('queue-last-update').textContent = `Last updated: ${now()}`;
    });
}

function renderQueueTable() {
    const tbody = $('queue-tbody');
    const busyCount = queueState.filter((a) => a.status === 'busy').length;
    $('queue-busy-count').textContent = busyCount;
    $('queue-avail-count').textContent = queueState.length - busyCount;
    $('queue-wait-count').textContent = 8;
    tbody.innerHTML = queueState
        .map(
            (a) => `
    <tr class="${a.status === 'busy' ? 'queue-row-busy' : ''}">
      <td><div class="queue-artist-name">${a.name}</div><div class="queue-artist-id">${a.id}</div></td>
      <td>${a.status === 'busy' ? `<span class="ticket-chip ticket-busy"><span class="ticket-dot"></span>${a.ticket}</span>` : `<span class="ticket-chip ticket-available">— Available</span>`}</td>
      <td style="font-size:13px;color:var(--on-surface);">${a.customer ? `<span style="font-weight:500;">${a.customer}</span>` : '<span style="color:var(--outline);">—</span>'}</td>
      <td>${a.since ? `<span style="font-family:var(--font-mono);font-size:12px;color:var(--on-surface-variant);">${a.since}</span>` : '<span style="color:var(--outline);">—</span>'}</td>
      <td><span class="badge ${a.status === 'busy' ? 'badge-cyan' : 'badge-success'} badge-dot">${a.status === 'busy' ? 'In Service' : 'Available'}</span></td>
    </tr>
  `,
        )
        .join('');
}

// ── Client Search ──────────────────────────────────────────────────
const clientDatabase = [
    {
        id: 'C001',
        name: 'Maria Santos',
        contact: '0917-123-4567',
        business: 'Santos Events',
        address: 'Blk 5 Lot 2, Bagong Ilog, Pasig City',
        joHistory: [
            {
                jo: 'JO-2024-0891',
                date: 'Apr 12, 2024',
                product: 'Tarpaulin 4x8ft',
                status: 'Released',
            },
            {
                jo: 'JO-2024-0765',
                date: 'Feb 3, 2024',
                product: 'Event Backdrop 6x10ft',
                status: 'Released',
            },
        ],
    },
    {
        id: 'C002',
        name: 'Jose Reyes',
        contact: '0918-987-6543',
        business: 'JR Printing Supply',
        address: 'Sta. Mesa, Manila',
        joHistory: [
            {
                jo: 'JO-2024-0892',
                date: 'Apr 14, 2024',
                product: 'ID Cards (50 pcs)',
                status: 'In Production',
            },
            {
                jo: 'JO-2024-0812',
                date: 'Mar 1, 2024',
                product: 'Flyers A6 (500 pcs)',
                status: 'Released',
            },
        ],
    },
    {
        id: 'C003',
        name: 'Ana Reyes',
        contact: '0919-555-1234',
        business: '',
        address: 'Cainta, Rizal',
        joHistory: [
            {
                jo: 'JO-2024-0803',
                date: 'Feb 22, 2024',
                product: 'Wedding Tarpaulin 3x5ft',
                status: 'Released',
            },
        ],
    },
    {
        id: 'C004',
        name: 'Carlos Mendoza',
        contact: '0917-888-2222',
        business: 'Mendoza Hardware',
        address: 'Marikina City',
        joHistory: [
            {
                jo: 'JO-2024-0890',
                date: 'Apr 10, 2024',
                product: 'Signage Vinyl (2 pcs)',
                status: 'For Pickup',
            },
            {
                jo: 'JO-2024-0741',
                date: 'Jan 15, 2024',
                product: 'Business Cards (100 pcs)',
                status: 'Released',
            },
        ],
    },
];

let csSelectedClient = null;

FlRouter.register('clientsearch', initClientSearch);

function initClientSearch() {
    const searchInput = $('cs-search-input');
    const searchBtn = $('cs-search-btn');
    const chips = $$('.cs-chip');

    function showState(state) {
        ['empty', 'not-found', 'results-list', 'client-panel'].forEach((s) =>
            $('cs-' + s).classList.add('hidden'),
        );
        if (state) $('cs-' + state).classList.remove('hidden');
    }

    function doSearch(query) {
        if (!query.trim()) {
            showState('empty');
            return;
        }
        const q = query.trim().toLowerCase();
        const results = clientDatabase.filter(
            (c) =>
                c.name.toLowerCase().includes(q) ||
                c.contact
                    .replace(/[-\s]/g, '')
                    .includes(q.replace(/[-\s]/g, '')),
        );
        if (results.length === 0) {
            showState('not-found');
            return;
        }
        showState('results-list');
        $('cs-result-count').textContent = results.length;
        const container = $('cs-results-container');
        container.innerHTML = '';
        results.forEach((client) => {
            const card = document.createElement('div');
            card.style.cssText =
                'background:var(--surface-container);border:1px solid var(--outline-variant);border-radius:12px;padding:14px 18px;cursor:pointer;transition:box-shadow 0.15s,border-color 0.15s;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px;';
            card.addEventListener('mouseenter', () => {
                card.style.boxShadow = '0 2px 8px rgba(0,0,0,0.08)';
                card.style.borderColor = 'var(--primary)';
            });
            card.addEventListener('mouseleave', () => {
                card.style.boxShadow = '';
                card.style.borderColor = 'var(--outline-variant)';
            });
            const lastJO = client.joHistory[0];
            card.innerHTML = `
        <div style="display:flex;align-items:center;gap:14px;">
          <div style="width:40px;height:40px;border-radius:50%;background:color-mix(in srgb,var(--primary) 12%,transparent);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><span class="material-symbols-outlined" style="color:var(--primary);font-size:20px;">person</span></div>
          <div><div style="font-weight:600;font-size:14px;color:var(--on-surface);">${client.name}</div><div style="font-size:12px;color:var(--on-surface-variant);">${client.contact}${client.business ? ' · ' + client.business : ''}</div></div>
        </div>
        <div style="text-align:right;flex-shrink:0;">
          <div style="font-size:11px;color:var(--on-surface-variant);margin-bottom:2px;">${client.joHistory.length} order(s) · Last: ${lastJO ? lastJO.date : '—'}</div>
          <button class="btn btn-primary btn-sm" style="font-size:12px;padding:5px 12px;">Select</button>
        </div>
      `;
            card.addEventListener('click', () => showClientDetail(client));
            container.appendChild(card);
        });
    }

    searchBtn.addEventListener('click', () => doSearch(searchInput.value));
    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') doSearch(searchInput.value);
    });
    chips.forEach((chip) => {
        chip.addEventListener('click', () => {
            searchInput.value = chip.dataset.name;
            doSearch(chip.dataset.name);
        });
    });
    $('cs-back-btn').addEventListener('click', () => {
        showState('results-list');
        csSelectedClient = null;
        $('cs-prefill-notice').classList.add('hidden');
    });
    $('cs-new-order-btn').addEventListener('click', () => {
        if (!csSelectedClient) return;
        const nf = $('field-fullname');
        if (nf) nf.value = csSelectedClient.name;
        const cf = $('field-contact');
        if (cf) cf.value = csSelectedClient.contact;
        const bf = $('field-business');
        if (bf) bf.value = csSelectedClient.business;
        const af = $('field-address');
        if (af) af.value = csSelectedClient.address;
        $('cs-prefill-notice').classList.remove('hidden');
        FlRouter.go('registration');
    });
    showState('empty');
}

function showClientDetail(client) {
    csSelectedClient = client;
    $('cs-results-list').classList.add('hidden');
    $('cs-client-panel').classList.remove('hidden');
    $('cs-prefill-notice').classList.add('hidden');
    $('cs-client-name-display').textContent = client.name;
    $('cs-detail-name').textContent = client.name;
    $('cs-detail-contact').textContent = client.contact;
    $('cs-detail-business').textContent = client.business || '—';
    $('cs-detail-address').textContent = client.address || '—';
    const hist = $('cs-jo-history');
    hist.innerHTML = '';
    const sc = {
        Released: 'badge-success',
        'In Production': 'badge-warning',
        'For Pickup': 'badge-info',
    };
    client.joHistory.forEach((jo) => {
        const row = document.createElement('div');
        row.style.cssText =
            'display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:var(--surface-container-low);border-radius:8px;gap:8px;margin-bottom:4px;';
        // GAP 4: Add Reprint button for Released orders (same design, same specs)
        const canReprint = jo.status === 'Released';
        const reprintBtn = canReprint
            ? `<button class="btn btn-outline btn-sm" style="font-size:11px;padding:4px 10px;white-space:nowrap;color:var(--primary);border-color:var(--primary);"
           onclick="flInitiateReprint('${jo.jo}', '${jo.product}', '${client.name}')">
           <span class="material-symbols-outlined" style="font-size:13px;">print</span>Reprint
         </button>`
            : '';
        row.innerHTML = `
      <div>
        <div style="font-size:13px;font-weight:600;font-family:var(--font-mono);color:var(--primary);">${jo.jo}</div>
        <div style="font-size:11px;color:var(--on-surface-variant);">${jo.product} · ${jo.date}</div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <span class="badge ${sc[jo.status] || 'badge-neutral'}" style="font-size:10px;white-space:nowrap;">${jo.status}</span>
        ${reprintBtn}
      </div>`;
        hist.appendChild(row);
    });
}

// ── GAP 4: Reprint — Client Request ────────────────────────────────
// Process Flow Scenario A: Returning client, same design, same specs,
// no artist needed — straight to Cashier for payment, then Production.
// Generates a new JO linked to the original.

window.flInitiateReprint = function (originalJO, product, clientName) {
    // Generate linked reprint JO number
    const reprintJO =
        'JO-' +
        new Date().getFullYear() +
        '-' +
        Math.floor(Math.random() * 400 + 700);

    // Populate the reprint confirmation modal
    const origEl = $('fl-reprint-original-jo');
    const newEl = $('fl-reprint-new-jo');
    const prodEl = $('fl-reprint-product');
    const nameEl = $('fl-reprint-client');
    if (origEl) origEl.textContent = originalJO;
    if (newEl) newEl.textContent = reprintJO;
    if (prodEl) prodEl.textContent = product;
    if (nameEl) nameEl.textContent = clientName;

    // Store for confirm handler
    window._flReprintData = { originalJO, reprintJO, product, clientName };

    const modal = $('fl-reprint-modal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.style.display = '';
    }
};

window.flConfirmReprint = function () {
    const d = window._flReprintData;
    if (!d) return;

    // Close modal
    const modal = $('fl-reprint-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }

    // Add to Cashier JO database so it can be loaded at POS
    const totalSample = 350; // demo price
    csJODatabase[d.reprintJO] = {
        joNumber: d.reprintJO,
        customer: d.clientName,
        type: 'Reprint — Client Request',
        desc: `Reprint of ${d.originalJO} · ${d.product} · Same design, same specs. No artist work required.`,
        items: [
            { label: 'Print Cost (Reprint)', amount: totalSample },
            { label: 'Material Cost', amount: 50 },
        ],
        status: 'Approved for Payment',
        linkedTo: d.originalJO,
    };
    if (typeof csJOSpecs !== 'undefined') {
        csJOSpecs[d.reprintJO] = {
            productType: d.product,
            dimensions: '—',
            quantity: '—',
            urgency: 'Normal',
            urgencyColor: 'var(--success)',
        };
    }

    // Show success banner in Client Search
    const banner = $('cs-prefill-notice');
    if (banner) {
        banner.style.background = 'var(--success-container)';
        banner.style.borderColor = '#86efac';
        banner.innerHTML = `<span class="material-symbols-outlined" style="color:var(--success);font-size:18px;">check_circle</span>
      <div style="font-size:13px;">
        <strong>Reprint JO Generated: ${d.reprintJO}</strong> — Linked to ${d.originalJO}.<br>
        <span style="color:var(--on-surface-variant);">No artist required — refer client directly to Cashier. Use JO Number <strong>${d.reprintJO}</strong> at the POS.</span>
      </div>`;
        banner.classList.remove('hidden');
    }
};

window.flCloseReprintModal = function () {
    const modal = $('fl-reprint-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }
};

FlRouter.register('validation', function () {
    if (!_valFiles.length) {
        // No file uploaded — send back to registration
        FlRouter.go('registration');
        return;
    }
    _renderValidationPage(_valFiles[0]);
});

// Simulate validation checks on a File object
function _runSimulatedValidation(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    const accepted = ['pdf', 'ai', 'psd', 'png', 'jpg', 'jpeg', 'cdr'];
    const rejected = ['docx', 'pptx', 'xlsx', 'gif', 'bmp', 'webp'];
    const sizeMB = file.size / 1024 / 1024;

    const checks = [];
    let hasFail = false;
    let hasWarn = false;

    // 1 — Format
    if (rejected.includes(ext)) {
        checks.push({
            label: 'File Format',
            status: 'fail',
            detail: `.${ext.toUpperCase()} is not accepted for print — please export as PDF, PNG, JPG, AI, PSD, or CDR.`,
        });
        hasFail = true;
    } else if (accepted.includes(ext)) {
        const notes = {
            pdf: 'Print-ready PDF — optimal format for production',
            ai: 'Adobe Illustrator vector — ideal for logos & signage',
            psd: 'Photoshop file — resolution-dependent, DPI check required',
            png: 'PNG accepted — ensure minimum 150 DPI at actual print size',
            jpg: 'JPEG accepted — verify compression level is adequate for print',
            jpeg: 'JPEG accepted — verify compression level is adequate for print',
            cdr: 'CorelDRAW file — accepted for local print production',
        };
        checks.push({
            label: 'File Format',
            status: 'pass',
            detail: notes[ext] || 'Accepted format',
        });
    } else {
        checks.push({
            label: 'File Format',
            status: 'fail',
            detail: `Unknown format (.${ext}) — not supported for print production.`,
        });
        hasFail = true;
    }

    // 2 — File size
    if (sizeMB > 500) {
        checks.push({
            label: 'File Size',
            status: 'fail',
            detail: `${sizeMB.toFixed(2)} MB — exceeds 500 MB limit per file.`,
        });
        hasFail = true;
    } else {
        checks.push({
            label: 'File Size',
            status: 'pass',
            detail: `${sizeMB.toFixed(2)} MB — within 500 MB limit.`,
        });
    }

    if (!hasFail) {
        // 3 — Resolution
        const isRaster = ['jpg', 'jpeg', 'png', 'psd'].includes(ext);
        if (isRaster) {
            checks.push({
                label: 'Resolution (DPI)',
                status: 'warn',
                detail: 'Cannot verify exact DPI from browser — please confirm file is at least 150 DPI at actual print size to avoid blurry output.',
            });
            hasWarn = true;
        } else {
            checks.push({
                label: 'Resolution (DPI)',
                status: 'pass',
                detail: 'Vector format — resolution-independent, prints crisp at any size.',
            });
        }

        // 4 — Dimensions
        checks.push({
            label: 'Dimensions',
            status: 'pass',
            detail: 'Dimensions accepted — matches requested print size within tolerance.',
        });

        // 5 — Bleed Area
        checks.push({
            label: 'Bleed Area',
            status: 'pass',
            detail: 'Bleed area check passed — meets the 3 mm minimum requirement.',
        });

        // 6 — Color Mode
        if (['jpg', 'jpeg', 'png'].includes(ext)) {
            checks.push({
                label: 'Color Mode',
                status: 'warn',
                detail: 'RGB mode detected — output colors may differ slightly from screen. Recommend converting to CMYK before final print.',
            });
            hasWarn = true;
        } else {
            checks.push({
                label: 'Color Mode',
                status: 'pass',
                detail: 'CMYK color mode — optimal for print production, no color shift expected.',
            });
        }

        // 7 — Integrity
        checks.push({
            label: 'File Integrity',
            status: 'pass',
            detail: 'No corruption detected — file is readable and complete.',
        });
    }

    const overall = hasFail ? 'failed' : hasWarn ? 'warned' : 'passed';
    return {
        filename: file.name,
        checks,
        overall,
        warnCount: checks.filter((c) => c.status === 'warn').length,
        failCount: checks.filter((c) => c.status === 'fail').length,
    };
}

function _renderValidationPage(file) {
    const result = _runSimulatedValidation(file);
    _valOverallStatus = result.overall;

    // Filename
    const fnEl = $('val-filename');
    if (fnEl) fnEl.textContent = file.name;

    // Overall badge
    const badge = $('val-warning-badge');
    if (badge) {
        if (result.overall === 'failed') {
            badge.className = 'badge badge-error badge-dot';
            badge.textContent = `${result.failCount} Error${result.failCount > 1 ? 's' : ''} — File Rejected`;
        } else if (result.overall === 'warned') {
            badge.className = 'badge badge-warning badge-dot';
            badge.textContent = `${result.warnCount} Warning${result.warnCount > 1 ? 's' : ''}`;
        } else {
            badge.className = 'badge badge-success badge-dot';
            badge.textContent = 'All Checks Passed';
        }
    }

    // Checks list
    const list = $('val-checks-list');
    if (list) {
        const iconMap = { pass: 'check', warn: 'warning', fail: 'close' };
        const classMap = { pass: 'vi-pass', warn: 'vi-warn', fail: 'vi-fail' };
        const colorMap = {
            pass: 'var(--success)',
            warn: 'var(--warning)',
            fail: 'var(--error)',
        };
        const labelMap = { pass: 'PASS', warn: 'WARN', fail: 'FAIL' };
        list.innerHTML = result.checks
            .map(
                (c, i) => `
      <div class="validation-item" style="${i === result.checks.length - 1 ? 'margin-bottom:0' : ''}">
        <div class="validation-item-icon ${classMap[c.status]}">
          <span class="material-symbols-outlined" style="font-size:18px">${iconMap[c.status]}</span>
        </div>
        <div>
          <div class="validation-item-label">${c.label}</div>
          <div class="validation-item-detail">${c.detail}</div>
        </div>
        <div class="validation-item-value" style="color:${colorMap[c.status]}">${labelMap[c.status]}</div>
      </div>`,
            )
            .join('');
    }

    // Staff action box
    const actionBox = $('val-action-box');
    if (actionBox) {
        if (result.overall === 'failed') {
            actionBox.style.background = 'var(--error-container)';
            actionBox.style.borderColor = 'var(--error)';
            actionBox.innerHTML = `
        <span class="material-symbols-outlined" style="color:var(--error);font-size:20px;flex-shrink:0;margin-top:2px">error</span>
        <div>
          <div style="font-weight:700;font-size:14px;color:var(--error)">File Validation Failed — Action Required</div>
          <div style="font-size:13px;color:#93000a;margin-top:4px;line-height:1.6">
            <strong>${result.failCount} critical error${result.failCount > 1 ? 's' : ''}</strong> found:
            ${result.checks
                .filter((c) => c.status === 'fail')
                .map((c) => `<strong>${c.label}</strong> — ${c.detail}`)
                .join('. ')}
            <br>Inform client of the issue. They may re-upload a corrected file, or opt for artist assistance (Type B).
          </div>
        </div>`;
        } else if (result.overall === 'warned') {
            actionBox.style.background = 'var(--primary-fixed)';
            actionBox.style.borderColor = 'var(--primary-container)';
            actionBox.innerHTML = `
        <span class="material-symbols-outlined" style="color:var(--primary-dark);font-size:20px;flex-shrink:0;margin-top:2px">campaign</span>
        <div>
          <div style="font-weight:700;font-size:14px;color:var(--primary-dark)">Frontline Staff Action Required</div>
          <div style="font-size:13px;color:var(--on-primary-container);margin-top:4px;line-height:1.6">
            Inform client of ${result.warnCount} warning${result.warnCount > 1 ? 's' : ''}:
            ${result.checks
                .filter((c) => c.status === 'warn')
                .map((c, i) => `<strong>(${i + 1})</strong> ${c.detail}`)
                .join(' ')}
            Ask client if they wish to proceed or re-submit.
          </div>
        </div>`;
        } else {
            actionBox.style.background = 'var(--success-container)';
            actionBox.style.borderColor = '#86efac';
            actionBox.innerHTML = `
        <span class="material-symbols-outlined" style="color:var(--success);font-size:20px;flex-shrink:0;margin-top:2px">verified</span>
        <div>
          <div style="font-weight:700;font-size:14px;color:var(--success)">File Passed All Checks</div>
          <div style="font-size:13px;color:#166534;margin-top:4px;line-height:1.6">
            No issues found. You may proceed to complete the registration. The file is ready for print production.
          </div>
        </div>`;
        }
    }

    // Action buttons
    const btns = $('val-action-buttons');
    if (btns) {
        if (result.overall === 'failed') {
            btns.innerHTML = `
        <button class="btn btn-outline" onclick="FlRouter.go('registration')">
          <span class="material-symbols-outlined" style="font-size:16px">upload_file</span>Re-upload File
        </button>
        <button class="btn btn-warning" onclick="switchToTypeB()">
          <span class="material-symbols-outlined" style="font-size:16px">draw</span>Switch to Type B — Get Artist Help
        </button>
        <button class="btn btn-danger" onclick="FlRouter.go('registration')">
          <span class="material-symbols-outlined" style="font-size:16px">cancel</span>Cancel Transaction
        </button>`;
        } else {
            btns.innerHTML = `
        <button class="btn btn-outline" onclick="FlRouter.go('registration')">
          <span class="material-symbols-outlined" style="font-size:16px">upload_file</span>Re-upload File
        </button>
        <button class="btn btn-primary" onclick="flOpenFileSecurityModal()">
          <span class="material-symbols-outlined" style="font-size:16px">security</span>Confirm Upload &amp; Security Check
        </button>`;
        }
    }
}

// ── FILE SECURITY MODAL — Step 1: Double-Check Preview ────────────────
// Fulfils: File Upload Security Protocol (Process Flow §FILE UPLOAD — SECURITY PROTOCOL)
// Step 1: Show file preview with filename-vs-order-description mismatch check.
// Step 2: Confirm deletion of local copy after upload.

let _flSecurityStep = 1; // 1 = double-check preview, 2 = delete local copy

function flOpenFileSecurityModal() {
    if (!_valFiles.length) return;
    _flSecurityStep = 1;
    _flRenderSecurityStep();
    const modal = $('fl-file-security-modal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.style.display = '';
    }
}

function _flRenderSecurityStep() {
    const file = _valFiles[0];
    const titleEl = $('fl-sec-modal-title');
    const bodyEl = $('fl-sec-modal-body');
    const nextBtn = $('fl-sec-modal-next');

    // Grab order description for mismatch check
    const orderDesc =
        ($('field-service-desc') && $('field-service-desc').value.trim()) ||
        ($('reg-service-desc') && $('reg-service-desc').value.trim()) ||
        '';

    if (_flSecurityStep === 1) {
        // ── Step 1: Double-check file preview ─────────────────────────
        if (titleEl)
            titleEl.innerHTML = `<span class="material-symbols-outlined" style="font-size:18px;color:var(--primary);vertical-align:-4px;">find_in_page</span> Step 1 of 2 — Confirm Correct File`;

        // Mismatch detection (simple keyword check against filename)
        const nameLower = file.name.toLowerCase();
        const descLower = orderDesc.toLowerCase();
        const mismatch =
            orderDesc &&
            !descLower
                .split(/\s+/)
                .some((w) => w.length > 3 && nameLower.includes(w));
        const mismatchWarning = mismatch
            ? `
      <div style="margin-top:12px;padding:11px 14px;background:#fef3c7;border:1px solid #fcd34d;border-radius:var(--radius);display:flex;gap:10px;align-items:flex-start;">
        <span class="material-symbols-outlined" style="color:#d97706;font-size:18px;flex-shrink:0;">warning</span>
        <div style="font-size:13px;color:#78350f;line-height:1.5;">
          <strong>File name does not match order description</strong> — confirm correct file?<br>
          <span style="font-size:12px;opacity:0.85;">Filename: <code>${file.name}</code> · Description: <em>"${orderDesc}"</em></span>
        </div>
      </div>`
            : '';

        if (bodyEl)
            bodyEl.innerHTML = `
      <div style="padding:14px;background:var(--surface-container-low);border:1px solid var(--outline-variant);border-radius:var(--radius);margin-bottom:4px;">
        <div style="font-size:10px;font-family:var(--font-mono);text-transform:uppercase;letter-spacing:0.07em;color:var(--on-surface-variant);margin-bottom:10px;">File Preview</div>
        <div style="display:flex;align-items:center;gap:14px;">
          <div style="width:48px;height:48px;background:var(--primary-fixed);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <span class="material-symbols-outlined" style="color:var(--primary);font-size:24px;">description</span>
          </div>
          <div>
            <div style="font-size:14px;font-weight:700;color:var(--on-surface);word-break:break-all;">${file.name}</div>
            <div style="font-size:12px;color:var(--on-surface-variant);font-family:var(--font-mono);margin-top:3px;">${(file.size / 1024 / 1024).toFixed(2)} MB · ${file.type || 'Unknown type'}</div>
          </div>
        </div>
      </div>
      ${mismatchWarning}
      <div style="margin-top:14px;font-size:13px;color:var(--on-surface);line-height:1.6;">
        <strong>Client is still at the counter.</strong> Please verify with the client that this is the correct file before proceeding.
      </div>`;

        if (nextBtn) {
            nextBtn.textContent = 'File is Correct — Continue';
            nextBtn.onclick = () => {
                _flSecurityStep = 2;
                _flRenderSecurityStep();
            };
        }
    } else {
        // ── Step 2: Delete local copy confirmation ─────────────────────
        if (titleEl)
            titleEl.innerHTML = `<span class="material-symbols-outlined" style="font-size:18px;color:var(--error);vertical-align:-4px;">delete_sweep</span> Step 2 of 2 — Delete Local Copy`;

        if (bodyEl)
            bodyEl.innerHTML = `
      <div style="padding:14px;background:var(--error-container);border:1px solid #f87171;border-radius:var(--radius);display:flex;gap:12px;align-items:flex-start;margin-bottom:14px;">
        <span class="material-symbols-outlined" style="color:var(--error);font-size:20px;flex-shrink:0;margin-top:1px;">security</span>
        <div style="font-size:13px;color:#7f1d1d;line-height:1.6;">
          <strong>Security Protocol — Action Required</strong><br>
          The file <code>${file.name}</code> has been uploaded to the system. The local copy on this PC must be deleted immediately — it is a security risk.
        </div>
      </div>
      <div style="font-size:13px;color:var(--on-surface);line-height:1.7;">
        <div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:8px;"><span class="material-symbols-outlined" style="font-size:16px;color:var(--success);margin-top:1px;">check_circle</span> File uploaded &amp; saved to system — ✓ Done</div>
        <div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:12px;"><span class="material-symbols-outlined" style="font-size:16px;color:var(--error);margin-top:1px;">folder_delete</span> Delete downloaded/copied file from frontline PC — <strong>Required now</strong></div>
        <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:12px;background:var(--surface-container-low);border:1px solid var(--outline-variant);border-radius:var(--radius);">
          <input type="checkbox" id="fl-sec-delete-confirm" style="margin-top:2px;width:16px;height:16px;accent-color:var(--primary);flex-shrink:0;" />
          <span style="font-size:13px;font-weight:600;color:var(--on-surface);">I confirm that the local copy of <em>${file.name}</em> has been deleted from this PC.</span>
        </label>
      </div>`;

        if (nextBtn) {
            nextBtn.textContent = 'Confirm Deletion & Complete Upload';
            nextBtn.onclick = () => {
                const cb = $('fl-sec-delete-confirm');
                if (!cb || !cb.checked) {
                    cb &&
                        (cb.parentElement.style.outline =
                            '2px solid var(--error)');
                    return;
                }
                // Close modal and proceed to registration
                $('fl-file-security-modal').classList.add('hidden');
                $('fl-file-security-modal').style.display = 'none';
                // Show a security-complete banner then go to registration
                FlRouter.go('registration');
                requestAnimationFrame(() => {
                    const sb = $('reg-success');
                    const secBanner = document.getElementById(
                        'fl-sec-complete-banner',
                    );
                    if (secBanner) {
                        secBanner.classList.remove('hidden');
                        setTimeout(
                            () => secBanner.classList.add('hidden'),
                            5000,
                        );
                    }
                });
            };
        }
    }
}

window.flCloseSecurityModal = function () {
    const modal = $('fl-file-security-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }
};

// ── Switch to Type B helper ────────────────────────────────────────
window.switchToTypeB = function () {
    // Clear uploaded files
    _valFiles = [];
    _valOverallStatus = null;

    // Navigate to registration and switch type selector to B
    FlRouter.go('registration');
    requestAnimationFrame(() => {
        const typeBOption = document.querySelector(
            '.type-option[data-type="B"]',
        );
        if (typeBOption) typeBOption.click();
        // Show a notice
        const noticeEl = document.getElementById('switch-to-b-notice');
        if (noticeEl) {
            noticeEl.classList.remove('hidden');
            setTimeout(() => noticeEl.classList.add('hidden'), 5000);
        }
    });
};

// ══════════════════════════════════════════════════════════════════
//  ARTIST ROUTER
// ══════════════════════════════════════════════════════════════════
const ArRouter = {
    pages: {},
    currentPage: null,
    register(name, fn) {
        this.pages[name] = fn;
    },
    go(name) {
        $$("[id^='ar-page-']").forEach((p) => p.classList.add('hidden'));
        const target = $(`ar-page-${name}`);
        if (target) {
            target.classList.remove('hidden');
            target.classList.add('animate-in');
        }
        $$("[data-shell='ar']").forEach((n) => n.classList.remove('active'));
        const navItem = document.querySelector(
            `[data-page="${name}"][data-shell="ar"]`,
        );
        if (navItem) navItem.classList.add('active');
        const titles = {
            dashboard: 'Artist Dashboard',
            'active-jo': 'Active JO / JO Creation',
            'design-editor': 'Design Editor',
            'initial-review': 'Initial Review',
            revisions: 'Revision Management',
        };
        const titleEl = $('ar-page-title');
        if (titleEl) titleEl.textContent = titles[name] || '';
        this.currentPage = name;
        if (this.pages[name]) this.pages[name]();
    },
};

// ══════════════════════════════════════════════════════════════════
//  CASHIER ROUTER
// ══════════════════════════════════════════════════════════════════
const CsRouter = {
    pages: {},
    currentPage: null,
    register(name, fn) {
        this.pages[name] = fn;
    },
    go(name) {
        $$("[id^='cs-page-']").forEach((p) => p.classList.add('hidden'));
        const target = $(`cs-page-${name}`);
        if (target) {
            target.classList.remove('hidden');
            target.classList.add('animate-in');
        }
        $$("[data-shell='cs']").forEach((n) => n.classList.remove('active'));
        const navItem = document.querySelector(
            `[data-page="${name}"][data-shell="cs"]`,
        );
        if (navItem) navItem.classList.add('active');
        const titles = {
            pos: 'POS / Order Lookup',
            history: 'Transaction History',
            cancellation: 'Cancellation Fees',
        };
        const titleEl = $('cs-page-title');
        if (titleEl) titleEl.textContent = titles[name] || '';
        this.currentPage = name;
        if (this.pages[name]) this.pages[name]();
    },
};

// ── Artist Dashboard ───────────────────────────────────────────────
const upcomingQueue = [
    { id: 'AA205', name: 'Lina Torres', type: 'B' },
    { id: 'AA206', name: 'Ben Cruz', type: 'B' },
    { id: 'AA207', name: 'Sofia Ramos', type: 'B' },
    { id: 'AA208', name: 'Manny Diaz', type: 'B' },
    { id: 'AA209', name: 'Carla Reyes', type: 'B' },
];

ArRouter.register('dashboard', () => {
    renderUpcomingQueue();
});

function renderUpcomingQueue() {
    const container = $('upcoming-queue-list');
    if (!container) return;
    container.innerHTML = upcomingQueue
        .map(
            (item, i) => `
    <div style="display:grid;grid-template-columns:auto 1fr auto;padding:10px 16px;border-bottom:1px solid var(--outline-variant);align-items:center;${i === 0 ? 'background:var(--primary-fixed);' : ''}">
      <div style="font-family:var(--font-mono);font-size:12px;font-weight:700;color:${i === 0 ? 'var(--primary)' : 'var(--on-surface)'};">${item.id}</div>
      <div style="font-size:13px;font-weight:${i === 0 ? '700' : '400'};color:var(--on-surface);padding-left:12px;">${item.name}</div>
      <div><span class="badge badge-artist" style="font-size:10px;background:${i === 0 ? 'var(--primary)' : 'var(--warning-container)'};color:${i === 0 ? 'white' : 'var(--warning)'};">Type ${item.type}</span></div>
    </div>
  `,
        )
        .join('');
}

// ── Queue Actions ──────────────────────────────────────────────────
function handleQueueAction(action) {
    // ── GAP 2: Forward button — intercept and show reason dropdown ────
    if (action === 'forward') {
        flOpenForwardReasonModal();
        return;
    }

    const messages = {
        next: {
            msg: 'Next customer called — AA205 (Lina Torres) assigned.',
            color: 'var(--success-container)',
            icon: 'skip_next',
            iconColor: 'var(--success)',
        },
        recall: {
            msg: 'Current customer AA204 recalled to your station.',
            color: 'var(--primary-fixed)',
            icon: 'replay',
            iconColor: 'var(--primary)',
        },
        forward: {
            msg: 'AA204 forwarded to next available artist.',
            color: 'var(--warning-container)',
            icon: 'forward',
            iconColor: 'var(--warning)',
        },
        notappear: {
            msg: 'AA204 marked as not appeared. Removed from queue.',
            color: 'var(--error-container)',
            icon: 'person_off',
            iconColor: 'var(--error)',
        },
        continue: {
            msg: 'Continue service flagged — next customer hold.',
            color: 'var(--surface-container)',
            icon: 'more_time',
            iconColor: 'var(--on-surface-variant)',
        },
    };
    const m = messages[action];
    const banner = $('queue-action-banner');
    banner.innerHTML = `<span class="material-symbols-outlined" style="color:${m.iconColor};font-size:20px;">${m.icon}</span><div style="font-size:13px;font-weight:600;color:var(--on-surface);">${m.msg}</div>`;
    banner.style.background = m.color;
    banner.style.border = '1px solid var(--outline-variant)';
    banner.classList.remove('hidden');
    setTimeout(() => banner.classList.add('hidden'), 4000);
    const fb = $('queue-action-feedback');
    if (fb) fb.textContent = `Last action: ${action.toUpperCase()} · ${now()}`;

    // #2: Auto-update artist status when Next is called
    if (action === 'next') setArtistStatus('in-process');
}

// ── GAP 2: Forward Reason Modal ────────────────────────────────────
// Process Flow: "Ang reason ng forward ay nilo-log ng system (optional field
// ang Artist — pwedeng pumili ng reason: 'Specialization needed',
// 'Personal conflict', 'Workload', etc.)"

function flOpenForwardReasonModal() {
    const modal = $('ar-forward-reason-modal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.style.display = '';
    }
    // Reset selection
    const sel = $('ar-forward-reason-select');
    if (sel) sel.value = '';
}

window.arConfirmForward = function () {
    const sel = $('ar-forward-reason-select');
    const reason = sel ? sel.value : '';

    // Close modal
    const modal = $('ar-forward-reason-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }

    // Log the reason
    const reasonLabel = reason
        ? {
              specialization: 'Specialization needed',
              personal: 'Personal conflict',
              workload: 'Workload',
              other: 'Other',
          }[reason] || reason
        : 'No reason specified';

    // Show feedback banner (same pattern as other queue actions)
    const banner = $('queue-action-banner');
    if (banner) {
        banner.innerHTML = `<span class="material-symbols-outlined" style="color:var(--warning);font-size:20px;">forward</span>
      <div style="font-size:13px;font-weight:600;color:var(--on-surface);">
        AA204 forwarded to next available artist.
        <span style="font-weight:400;color:var(--on-surface-variant);"> · Reason logged: <em>${reasonLabel}</em></span>
      </div>`;
        banner.style.background = 'var(--warning-container)';
        banner.style.border = '1px solid var(--outline-variant)';
        banner.classList.remove('hidden');
        setTimeout(() => banner.classList.add('hidden'), 5000);
    }
    const fb = $('queue-action-feedback');
    if (fb)
        fb.textContent = `Last action: FORWARD · Reason: ${reasonLabel} · ${now()}`;
};

window.arCloseForwardModal = function () {
    const modal = $('ar-forward-reason-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }
};

// ── #2: Artist Status Toggle ───────────────────────────────────────
const artistStatusCycle = ['available', 'in-process', 'in-consultation'];
let _artistStatusIndex = 0;

const artistStatusConfig = {
    available: {
        label: 'Available',
        dotColor: '#22c55e',
        dotShadow: '0 0 0 3px rgba(34,197,94,0.25)',
        textColor: 'var(--success)',
        bg: 'var(--success-container)',
        border: '#86efac',
        toggleClass: '',
    },
    'in-process': {
        label: 'In Process',
        dotColor: '#f59e0b',
        dotShadow: '0 0 0 3px rgba(245,158,11,0.25)',
        textColor: '#92400e',
        bg: 'var(--warning-container)',
        border: '#fcd34d',
        toggleClass: 'in-process',
    },
    'in-consultation': {
        label: 'In Consultation',
        dotColor: 'var(--primary)',
        dotShadow: '0 0 0 3px rgba(26,58,143,0.2)',
        textColor: 'var(--primary)',
        bg: 'var(--primary-fixed)',
        border: '#93c5fd',
        toggleClass: 'in-consultation',
    },
    'on-break': {
        label: 'On Break',
        dotColor: '#f97316',
        dotShadow: '0 0 0 3px rgba(249,115,22,0.25)',
        textColor: '#9a3412',
        bg: '#ffedd5',
        border: '#fed7aa',
        toggleClass: 'on-break',
    },
    'off-shift': {
        label: 'Off Shift',
        dotColor: '#6b7280',
        dotShadow: '0 0 0 3px rgba(107,114,128,0.2)',
        textColor: 'var(--on-surface-variant)',
        bg: 'var(--surface-container)',
        border: 'var(--outline-variant)',
        toggleClass: 'off-shift',
    },
};

function setArtistStatus(status) {
    const cfg = artistStatusConfig[status];
    if (!cfg) return;
    _artistStatusIndex = artistStatusCycle.indexOf(status);

    const toggle = $('ar-status-chip');
    const dot = $('ar-status-dot');
    const label = $('ar-status-label');
    if (!toggle || !dot || !label) return;

    // Reset toggle classes
    toggle.classList.remove('in-process', 'in-consultation');
    if (cfg.toggleClass) toggle.classList.add(cfg.toggleClass);

    // Also update chip visual style directly
    toggle.style.background = cfg.bg;
    toggle.style.borderColor = cfg.border;
    toggle.style.color = cfg.textColor;

    dot.style.background = cfg.dotColor;
    dot.style.boxShadow = cfg.dotShadow;
    label.textContent = cfg.label;
    label.style.color = cfg.textColor;
}

// ── GAP 3: On Break Button ─────────────────────────────────────────
// Process Flow: "May 'On Break' button ang Artist sa dashboard.
// Kapag 'On Break', hindi mag-a-assign ang system ng bagong customer."

window.arSetOnBreak = function () {
    setArtistStatus('on-break');
    const banner = $('queue-action-banner');
    if (banner) {
        banner.innerHTML = `<span class="material-symbols-outlined" style="color:#f97316;font-size:20px;">coffee</span>
      <div style="font-size:13px;font-weight:600;color:var(--on-surface);">Status set to <strong>On Break</strong> — you will not receive new customer assignments until you return.</div>`;
        banner.style.background = '#ffedd5';
        banner.style.border = '1px solid #fed7aa';
        banner.classList.remove('hidden');
        setTimeout(() => banner.classList.add('hidden'), 5000);
    }
    const fb = $('queue-action-feedback');
    if (fb) fb.textContent = `Status: ON BREAK · ${now()}`;
};

// ── GAP 3: End Shift Button ────────────────────────────────────────
// Process Flow: "Bago mag-End Shift, tine-check ng system kung may current
// active customer ang artist: Wala → Direct logout, status: 'Off Shift';
// May active customer → prompt: 'May kasalukuyang customer ka pa...'"

// Simulate whether artist currently has an active customer
let _arHasActiveCustomer = true; // demo: default to true to show the prompt

window.arEndShift = function () {
    if (_arHasActiveCustomer) {
        // Show the active-customer prompt modal
        const modal = $('ar-end-shift-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = '';
        }
    } else {
        // No active customer — direct logout/off-shift
        arDoEndShift();
    }
};

window.arEndShiftForwardAndEnd = function () {
    // Forward current customer first, then end shift
    const modal = $('ar-end-shift-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }
    // Forward current customer
    handleQueueAction_internal('forward');
    _arHasActiveCustomer = false;
    setTimeout(() => arDoEndShift(), 500);
};

window.arEndShiftCancel = function () {
    const modal = $('ar-end-shift-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }
};

function arDoEndShift() {
    setArtistStatus('off-shift');
    const banner = $('queue-action-banner');
    if (banner) {
        banner.innerHTML = `<span class="material-symbols-outlined" style="color:var(--on-surface-variant);font-size:20px;">logout</span>
      <div style="font-size:13px;font-weight:600;color:var(--on-surface);">Shift ended — status set to <strong>Off Shift</strong>. Logged off at ${now()}.</div>`;
        banner.style.background = 'var(--surface-container)';
        banner.style.border = '1px solid var(--outline-variant)';
        banner.classList.remove('hidden');
        setTimeout(() => banner.classList.add('hidden'), 5000);
    }
    const fb = $('queue-action-feedback');
    if (fb) fb.textContent = `Status: OFF SHIFT · Log-off: ${now()}`;
}

// Internal forward (used by End Shift flow, skips the reason modal since context is clear)
function handleQueueAction_internal(action) {
    const messages = {
        forward: {
            msg: 'AA204 forwarded to next available artist (End Shift handoff).',
            color: 'var(--warning-container)',
            icon: 'forward',
            iconColor: 'var(--warning)',
        },
    };
    const m = messages[action];
    if (!m) return;
    const banner = $('queue-action-banner');
    if (banner) {
        banner.innerHTML = `<span class="material-symbols-outlined" style="color:${m.iconColor};font-size:20px;">${m.icon}</span><div style="font-size:13px;font-weight:600;color:var(--on-surface);">${m.msg}</div>`;
        banner.style.background = m.color;
        banner.style.border = '1px solid var(--outline-variant)';
        banner.classList.remove('hidden');
        setTimeout(() => banner.classList.add('hidden'), 4000);
    }
}

function toggleArtistStatus() {
    _artistStatusIndex = (_artistStatusIndex + 1) % artistStatusCycle.length;
    const newStatus = artistStatusCycle[_artistStatusIndex];
    setArtistStatus(newStatus);

    // Show a brief feedback toast
    const banner = $('queue-action-banner');
    if (banner) {
        const cfg = artistStatusConfig[newStatus];
        banner.innerHTML = `<span class="material-symbols-outlined" style="color:${cfg.dotColor};font-size:20px;">person</span><div style="font-size:13px;font-weight:600;color:var(--on-surface);">Artist status updated → <strong>${cfg.label}</strong></div>`;
        banner.style.background = 'var(--surface-container-low)';
        banner.style.border = '1px solid var(--outline-variant)';
        banner.classList.remove('hidden');
        setTimeout(() => banner.classList.add('hidden'), 3000);
    }
}

// ── #4: JO Type Tag Selector ───────────────────────────────────────
let _selectedJOType = 'new';

const joTypeNotes = {
    new: 'New layout needed — no source file from client.',
    revision: 'Client returned with change requests after receiving output.',
    reprint: 'Client repeating a previously approved order. No layout changes.',
};

function selectJOType(type) {
    _selectedJOType = type;
    ['new', 'revision', 'reprint'].forEach((t) => {
        const btn = $(`jo-tag-${t}`);
        if (btn) {
            btn.classList.remove('jo-type-tag-active');
        }
    });
    const activeBtn = $(`jo-tag-${type}`);
    if (activeBtn) activeBtn.classList.add('jo-type-tag-active');

    const noteEl = $('jo-type-note');
    if (noteEl) noteEl.textContent = joTypeNotes[type] || '';
}

// ── #7: Live Consultation Timer ────────────────────────────────────
let _liveConsultStart = null;
let _liveConsultInterval = null;

// ── Fix #6: Design Activity Flag ──────────────────────────────────
// Tracks whether the artist has opened the Design Editor for this JO.
// Set to true the first time the editor is entered; never reset during
// the same session so that Outcome C becomes unavailable once work begins.
let _designActivityStarted = false;

function startLiveConsultTimer() {
    _liveConsultStart = Date.now();
    const timerEl = $('live-consult-timer');
    if (!timerEl) return;
    clearInterval(_liveConsultInterval);
    _liveConsultInterval = setInterval(() => {
        const elapsed = Math.floor((Date.now() - _liveConsultStart) / 1000);
        const mins = String(Math.floor(elapsed / 60)).padStart(2, '0');
        const secs = String(elapsed % 60).padStart(2, '0');
        timerEl.textContent = `${mins}:${secs}`;
    }, 1000);
}

// ── Fix #6: Apply auto-detection UI on the Initial Review page ─────
// Called every time the Initial Review page is opened.
// If design work has already started, Outcome C is disabled and
// Outcome D shows a system-detected badge (artist cannot override).
function applyDesignActivityDetection() {
    const outcomeC = $('outcome-C');
    const outcomeD = $('outcome-D');
    const detectionBanner = $('design-activity-detection-banner');

    if (!outcomeC || !outcomeD) return;

    if (_designActivityStarted) {
        // Disable Outcome C — no longer valid once design work began
        outcomeC.classList.add('outcome-disabled');
        outcomeC.style.opacity = '0.45';
        outcomeC.style.pointerEvents = 'none';
        outcomeC.style.cursor = 'not-allowed';
        outcomeC.title =
            'Design work has already started — no-charge cancellation is no longer available.';

        // Add auto-detected label to Outcome D
        const dLabel = $('outcome-D-auto-tag');
        if (dLabel) dLabel.style.display = 'inline-flex';

        // Show the system detection info banner
        if (detectionBanner) detectionBanner.classList.remove('hidden');

        // If Outcome C was previously selected, deselect it
        if (outcomeC.classList.contains('selected-danger')) {
            outcomeC.classList.remove('selected-danger');
            if (selectedOutcome === 'C') {
                selectedOutcome = null;
                const noteEl = $('outcome-note');
                if (noteEl) noteEl.classList.add('hidden');
            }
        }
    } else {
        // No design activity yet — reset to normal state
        outcomeC.style.opacity = '';
        outcomeC.style.pointerEvents = '';
        outcomeC.style.cursor = '';
        outcomeC.title = '';
        const dLabel = $('outcome-D-auto-tag');
        if (dLabel) dLabel.style.display = 'none';
        if (detectionBanner) detectionBanner.classList.add('hidden');
    }
}

// Hook into ArRouter for Design Editor page
const _origArGo = ArRouter.go.bind(ArRouter);
ArRouter.go = function (name) {
    _origArGo(name);
    if (name === 'design-editor') {
        startLiveConsultTimer();
        setArtistStatus('in-consultation');
        // Mark design activity as started — this is the trigger for Fix #6
        _designActivityStarted = true;
    }
    if (name === 'initial-review') {
        // Re-evaluate detection state each time the page is shown
        applyDesignActivityDetection();
    }
};

// ── #9: JO Status Card Update after Approval ──────────────────────
function updateActiveJOStatus(status, badgeClass, label) {
    const chip = $('active-jo-status-chip');
    if (!chip) return;
    chip.className = `badge ${badgeClass}`;
    chip.textContent = label;

    // Also update the page header badge
    const headerBadge = document.querySelector(
        '#ar-page-active-jo .badge.badge-warning.badge-dot',
    );
    if (headerBadge) {
        headerBadge.className = `badge ${badgeClass} badge-dot`;
        headerBadge.textContent = status;
    }
}

// ── Initial Review — Outcome Selection ────────────────────────────
let selectedOutcome = null;

function selectOutcome(letter) {
    selectedOutcome = letter;
    ['A', 'B', 'C', 'D'].forEach((l) => {
        const el = $(`outcome-${l}`);
        if (el) el.classList.remove('selected', 'selected-danger');
    });
    const el = $(`outcome-${letter}`);
    if (['C', 'D'].includes(letter)) el.classList.add('selected-danger');
    else el.classList.add('selected');
    const notes = {
        A: "Client approved the design. JO will be updated to 'Approved for Payment' and forwarded to the Cashier module.",
        B: 'Artist will return to the Design Editor. This is still a live consultation — NOT a formal revision. Draft JO stays open.',
        C: "No design work was done. JO will be marked 'Cancelled' with no charge. Artist status returns to Available.",
        D: "System detected design activity. A cancellation fee will be applied. JO → 'Cancelled with Fee'. Cashier will be notified.",
    };
    const noteEl = $('outcome-note');
    noteEl.textContent = notes[letter];
    noteEl.classList.remove('hidden');
}

function confirmOutcome() {
    if (!selectedOutcome) {
        alert('Please select an outcome first.');
        return;
    }
    const configs = {
        A: {
            icon: 'check_circle',
            iconBg: 'var(--success-container)',
            iconColor: 'var(--success)',
            title: 'Confirm: Client Approved',
            body: "JO-2026-0892 status will be updated to <strong>'Approved for Payment'</strong>. The order will be forwarded to the Cashier module automatically.",
            confirmLabel: 'Confirm Approval',
            confirmStyle: 'background:var(--success);color:white;',
        },
        B: {
            icon: 'refresh',
            iconBg: 'var(--primary-fixed)',
            iconColor: 'var(--primary)',
            title: 'Confirm: Return to Editor',
            body: "Artist will return to the Design Editor to apply client's requested changes. This is a <strong>live consultation</strong> — NOT a formal revision.",
            confirmLabel: 'Return to Editor',
            confirmStyle: '',
        },
        C: {
            icon: 'cancel',
            iconBg: 'var(--surface-container)',
            iconColor: 'var(--on-surface-variant)',
            title: 'Confirm: Cancel Order (No Charge)',
            body: "JO-2026-0892 will be marked as <strong>'Cancelled'</strong>. No design work was done — no charge to client. Artist status returns to Available.",
            confirmLabel: 'Confirm Cancellation',
            confirmStyle: 'background:var(--outline);color:white;',
        },
        D: {
            icon: 'money_off',
            iconBg: 'var(--error-container)',
            iconColor: 'var(--error)',
            title: 'Confirm: Cancel with Fee',
            body: "Design work was already started. A <strong>cancellation fee</strong> will be applied. JO → 'Cancelled with Fee'. Cashier module will be notified.",
            confirmLabel: 'Apply Cancellation Fee',
            confirmStyle: 'background:var(--error);color:white;',
        },
    };
    const cfg = configs[selectedOutcome];
    $('modal-icon-el').style.background = cfg.iconBg;
    $('modal-icon').textContent = cfg.icon;
    $('modal-icon-el').querySelector('span').style.color = cfg.iconColor;
    $('modal-title').textContent = cfg.title;
    $('modal-sub').textContent = 'JO-2026-0892 · Jose Reyes';
    $('modal-body-content').innerHTML =
        `<p style="font-size:14px;color:var(--on-surface);line-height:1.6;">${cfg.body}</p>`;
    const confirmBtn = $('modal-confirm-btn');
    confirmBtn.textContent = cfg.confirmLabel;
    if (cfg.confirmStyle)
        confirmBtn.setAttribute(
            'style',
            cfg.confirmStyle +
                ';display:inline-flex;align-items:center;gap:8px;padding:9px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;',
        );
    $('outcome-modal').classList.remove('hidden');
}

function finalizeOutcome() {
    $('outcome-modal').classList.add('hidden');
    const banners = {
        A: {
            bg: 'var(--success-container)',
            border: '#86efac',
            icon: 'check_circle',
            iconColor: 'var(--success)',
            title: 'Design Approved!',
            detail: "JO-2026-0892 is now 'Approved for Payment'. Forwarded to Cashier.",
        },
        B: {
            bg: 'var(--primary-fixed)',
            border: 'var(--primary-container)',
            icon: 'refresh',
            iconColor: 'var(--primary)',
            title: 'Returning to Editor',
            detail: 'Live consultation continues — artist will apply changes.',
        },
        C: {
            bg: 'var(--surface-container)',
            border: 'var(--outline-variant)',
            icon: 'cancel',
            iconColor: 'var(--outline)',
            title: 'Order Cancelled',
            detail: "JO-2026-0892 marked 'Cancelled'. No charge. Artist status: Available.",
        },
        D: {
            bg: 'var(--error-container)',
            border: '#fca5a5',
            icon: 'money_off',
            iconColor: 'var(--error)',
            title: 'Cancelled with Fee',
            detail: "JO-2026-0892 → 'Cancelled with Fee'. Cashier notified for fee collection.",
        },
    };
    const b = banners[selectedOutcome];
    const banner = $('review-outcome-banner');
    banner.innerHTML = `<span class="material-symbols-outlined" style="color:${b.iconColor};font-size:24px;">${b.icon}</span><div><div style="font-weight:700;font-size:14px;color:var(--on-surface);">${b.title}</div><div style="font-size:13px;color:var(--on-surface-variant);margin-top:2px;">${b.detail}</div></div>`;
    banner.style.background = b.bg;
    banner.style.border = `1px solid ${b.border}`;
    banner.classList.remove('hidden');
    banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    if (selectedOutcome === 'B')
        setTimeout(() => ArRouter.go('design-editor'), 1500);

    // ── Fix #8: Post-Approval JO Status Update + Cashier Handoff ──────
    // Outcome A triggers a visible JO status chip update and a Cashier
    // handoff confirmation card so the artist knows the order has been
    // forwarded and the Cashier module has been notified.
    if (selectedOutcome === 'A') {
        // Update the JO status chip in the page header area
        const joStatusChip = $('review-jo-status-chip');
        if (joStatusChip) {
            joStatusChip.textContent = 'Approved for Payment';
            joStatusChip.className = 'badge badge-success badge-dot';
        }

        // Lock all outcome options — decision is final
        ['A', 'B', 'C', 'D'].forEach((l) => {
            const el = $(`outcome-${l}`);
            if (el) {
                el.style.pointerEvents = 'none';
                el.style.opacity = l === 'A' ? '1' : '0.35';
            }
        });

        // Disable the Confirm Decision button
        const confirmBtn = document.querySelector(
            '#ar-page-initial-review .btn-primary.btn-block',
        );
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.style.opacity = '0.5';
            confirmBtn.style.cursor = 'not-allowed';
        }

        // Show the Cashier Handoff Confirmation card
        const handoffCard = $('cashier-handoff-card');
        if (handoffCard) {
            handoffCard.classList.remove('hidden');
            handoffCard.style.animation = 'fadeIn 0.4s ease';
            setTimeout(
                () =>
                    handoffCard.scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest',
                    }),
                350,
            );
        }
    }

    // ── Fix #6 + #8: Outcome D — also lock outcome options after confirmation
    if (selectedOutcome === 'C' || selectedOutcome === 'D') {
        ['A', 'B', 'C', 'D'].forEach((l) => {
            const el = $(`outcome-${l}`);
            if (el) {
                el.style.pointerEvents = 'none';
                el.style.opacity = l === selectedOutcome ? '1' : '0.35';
            }
        });
        const confirmBtn = document.querySelector(
            '#ar-page-initial-review .btn-primary.btn-block',
        );
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.style.opacity = '0.5';
            confirmBtn.style.cursor = 'not-allowed';
        }
    }
}

// ── Revision helpers ───────────────────────────────────────────────
function openRevisionSubmitModal() {
    $('revision-submit-modal').classList.remove('hidden');
}
function showRevisionApproved() {
    $('revision-approved-banner').classList.remove('hidden');
    $('revision-approved-banner').scrollIntoView({
        behavior: 'smooth',
        block: 'nearest',
    });
}

// ══════════════════════════════════════════════════════════════════
//  CASHIER MODULE
// ══════════════════════════════════════════════════════════════════

// ── State ─────────────────────────────────────────────────────────
let csCurrentJO = null;
let csPayType = 'down'; // "full" | "down"

// Demo JO database (simulates backend)
const csJODatabase = {
    'JO-2026-0892': {
        joNumber: 'JO-2026-0892',
        customer: 'Jose Reyes',
        type: 'Type B – Consultation',
        desc: 'Tarpaulin design for wedding reception — 4x8ft. Custom layout with photo collage and floral motifs.',
        items: [
            { label: 'Base Consultation Fee', amount: 150 },
            { label: 'Complexity Add-on (Photo Collage)', amount: 80 },
            { label: 'Rush Fee (48hr)', amount: 50 },
            { label: 'Material Cost (Tarp 4x8ft)', amount: 120 },
        ],
        status: 'Approved for Payment',
    },
    'JO-2026-0870': {
        joNumber: 'JO-2026-0870',
        customer: 'Maria Santos',
        type: 'Type A – For Printing Only',
        desc: 'Sticker printing — 100pcs, 2x2 inches, glossy, full color. File passed validation.',
        items: [
            { label: 'Sticker Print (100pcs, 2x2in Glossy)', amount: 350 },
            { label: 'Material Cost', amount: 50 },
        ],
        status: 'Approved for Payment',
    },
    'JO-2026-0855': {
        joNumber: 'JO-2026-0855',
        customer: 'Lina Torres',
        type: 'Type B – Consultation',
        desc: 'Business card layout — 500pcs, standard size, double-sided.',
        items: [
            { label: 'Base Consultation Fee', amount: 100 },
            { label: 'Print (500pcs Business Card 2-sided)', amount: 450 },
            { label: 'Material Cost (Matte Cardstock)', amount: 80 },
        ],
        status: 'Approved for Payment',
    },
};

// ── POS: Load JO ──────────────────────────────────────────────────
function csLoadJO() {
    const input = $('cs-jo-input').value.trim().toUpperCase();
    if (!input) {
        csShowError('Please enter a JO Number to load.');
        return;
    }

    const jo = csJODatabase[input];
    if (!jo) {
        csShowError(
            `JO Number "${input}" not found. Make sure the format is correct (e.g. JO-2026-0892).`,
        );
        return;
    }
    csRenderJO(jo);
}

function csLoadDemo() {
    const keys = Object.keys(csJODatabase);
    const jo = csJODatabase[keys[0]];
    $('cs-jo-input').value = jo.joNumber;
    csRenderJO(jo);
}

function csRenderJO(jo) {
    csCurrentJO = jo;

    // Compute total
    const total = jo.items.reduce((s, i) => s + i.amount, 0);
    csCurrentJO.total = total;

    // Fill JO Summary card
    $('cs-jo-number').textContent = jo.joNumber;
    $('cs-customer-name').textContent = jo.customer;
    $('cs-jo-type').textContent = jo.type;
    $('cs-jo-desc').textContent = jo.desc;
    $('cs-design-fee').textContent =
        `₱${total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;

    // ── Dynamic price breakdown fields ──────────────────────────────
    const specs = csJOSpecs[jo.joNumber];
    const ptEl = document.getElementById('cs-product-type');
    const dimEl = document.getElementById('cs-dimensions');
    const qtyEl = document.getElementById('cs-quantity');
    const urgEl = document.getElementById('cs-urgency-badge');
    if (ptEl) ptEl.textContent = specs ? specs.productType : jo.type || '—';
    if (dimEl) dimEl.textContent = specs ? specs.dimensions : '—';
    if (qtyEl) qtyEl.textContent = specs ? specs.quantity : '—';
    if (urgEl) {
        const urgency = specs ? specs.urgency : 'Normal';
        const color = specs ? specs.urgencyColor : 'var(--success)';
        urgEl.innerHTML = `<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;background:${color}22;color:${color};border-radius:20px;font-size:12px;font-weight:700;border:1px solid ${color}55;">${urgency}</span>`;
    }
    const breakdownEl = document.getElementById('cs-price-breakdown-lines');
    if (breakdownEl && jo.items) {
        breakdownEl.innerHTML = jo.items
            .map(
                (item) =>
                    `<div style="display:flex;justify-content:space-between;align-items:center;padding:6px 10px;background:var(--surface-container-low);border-radius:6px;border:1px solid var(--outline-variant);">
        <span style="color:var(--on-surface-variant);">${item.label}</span>
        <span style="font-family:var(--font-mono);font-weight:600;">₱${item.amount.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span>
      </div>`,
            )
            .join('');
    }
    const overrideNote = document.getElementById('cs-override-note');
    if (overrideNote)
        overrideNote.classList.toggle('hidden', !(specs && specs.hasOverride));
    // ────────────────────────────────────────────────────────────────

    // Fill receipt preview
    $('cs-receipt-jo').textContent = jo.joNumber;
    $('cs-line-items').innerHTML = jo.items
        .map(
            (item) =>
                `<div style="display:flex;justify-content:space-between;padding-bottom:6px;border-bottom:1px solid var(--outline-variant);">
      <span>${item.label}</span>
      <span>₱${item.amount.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span>
    </div>`,
        )
        .join('');
    $('cs-receipt-total').textContent =
        `₱${total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
    $('cs-receipt-tendered').textContent = '₱0.00';
    $('cs-receipt-balance').textContent =
        `₱${total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;

    // Badge — reflect actual JO status
    const badge = $('cs-jo-status-badge');
    const _csStatusMap = {
        'For Payment': {
            label: 'FOR PAYMENT',
            cls: 'badge badge-success badge-dot',
        },
        'Partially Paid': {
            label: 'PARTIALLY PAID — COLLECT BALANCE',
            cls: 'badge badge-warning badge-dot',
        },
        'Pending Verification': {
            label: 'PENDING VERIFICATION',
            cls: 'badge badge-neutral badge-dot',
        },
    };
    const _csMapped = _csStatusMap[jo.status] || {
        label: jo.status.toUpperCase(),
        cls: 'badge badge-success badge-dot',
    };
    badge.textContent = _csMapped.label;
    badge.className = _csMapped.cls;

    // Reset payment inputs
    $('cs-amount-paid').value = '';
    $('cs-balance').value = '';
    $('cs-payment-success').classList.add('hidden');

    // Set default pay type to full
    csSelectPayType('full');

    // Show panel
    $('cs-jo-panel').classList.remove('hidden');

    // Hide any previous errors
    const errEl = $('cs-pos-error');
    if (errEl) errEl.classList.add('hidden');
}

// ── POS: Payment Method Selector ──────────────────────────────────
let csPayMethod = 'cash'; // cash | gcash | maya | bank

function csSelectMethod(method) {
    csPayMethod = method;

    // Style all method buttons
    const methods = ['cash', 'gcash', 'maya', 'bank'];
    const activeColors = {
        cash: {
            border: 'var(--primary)',
            bg: 'var(--primary-fixed)',
            textColor: 'var(--primary)',
        },
        gcash: { border: '#0070e0', bg: '#eff6ff', textColor: '#0070e0' },
        maya: { border: '#00a86b', bg: '#f0fdf4', textColor: '#00a86b' },
        bank: { border: '#7c3aed', bg: '#f5f3ff', textColor: '#7c3aed' },
    };

    methods.forEach((m) => {
        const el = $(`cs-method-${m}`);
        if (!el) return;
        const titleEl = el.querySelector('div > div:first-child');
        if (m === method) {
            el.style.borderColor = activeColors[m].border;
            el.style.background = activeColors[m].bg;
            if (titleEl) titleEl.style.color = activeColors[m].textColor;
        } else {
            el.style.borderColor = 'var(--outline-variant)';
            el.style.background = 'var(--surface-container-low)';
            if (titleEl) titleEl.style.color = 'var(--on-surface)';
        }
    });

    // Show/hide method-specific fields
    $('cs-method-fields-cash').classList.toggle('hidden', method !== 'cash');
    $('cs-method-fields-digital').classList.toggle(
        'hidden',
        method !== 'gcash' && method !== 'maya',
    );
    $('cs-method-fields-bank').classList.toggle('hidden', method !== 'bank');

    // Update digital note text for gcash vs maya
    if (method === 'gcash' || method === 'maya') {
        const name = method === 'gcash' ? 'GCash' : 'Maya';
        $('cs-digital-note-text').textContent =
            `Ask client to scan the store QR code or scan client's QR. Verify payment via ${name} merchant app, then enter the reference number below.`;
    }

    // Re-run pay type logic to auto-fill amounts
    csSelectPayType(csPayType);
}

// ── POS: Payment Type Selector ────────────────────────────────────
function csSelectPayType(type) {
    csPayType = type;
    $('cs-opt-full').classList.toggle('selected', type === 'full');
    $('cs-opt-down').classList.toggle('selected', type === 'down');

    const label = $('cs-confirm-btn-label');
    const isBankPending =
        csPayMethod === 'bank' && $('cs-bank-pending-toggle')?.checked;

    if (type === 'full') {
        label.textContent = isBankPending
            ? 'Mark as Pending Verification'
            : 'Confirm Full Payment';
        if (csCurrentJO) {
            // Auto-fill amount for active method
            if (csPayMethod === 'cash') {
                $('cs-amount-paid').value = csCurrentJO.total.toFixed(2);
                csCalcBalance();
            } else if (csPayMethod === 'gcash' || csPayMethod === 'maya') {
                if ($('cs-digital-amount'))
                    $('cs-digital-amount').value = csCurrentJO.total.toFixed(2);
                csCalcBalanceDigital();
            } else if (csPayMethod === 'bank') {
                if ($('cs-bank-amount'))
                    $('cs-bank-amount').value = csCurrentJO.total.toFixed(2);
                csCalcBalanceBank();
            }
        }
    } else {
        label.textContent = 'Confirm Down Payment — Production Activated';
        if ($('cs-amount-paid')) $('cs-amount-paid').value = '';
        if ($('cs-cash-change')) $('cs-cash-change').value = '';
        if ($('cs-balance')) $('cs-balance').value = '';
        if ($('cs-digital-amount')) $('cs-digital-amount').value = '';
        if ($('cs-digital-balance')) $('cs-digital-balance').value = '';
        if ($('cs-bank-amount')) $('cs-bank-amount').value = '';
        if ($('cs-bank-balance')) $('cs-bank-balance').value = '';
        $('cs-receipt-tendered').textContent = '₱0.00';
        $('cs-receipt-balance').textContent = csCurrentJO
            ? `₱${csCurrentJO.total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`
            : '₱0.00';
    }
}

// ── POS: Bank Pending Toggle ───────────────────────────────────────
function csBankPendingToggle() {
    const isChecked = $('cs-bank-pending-toggle').checked;
    const slider = $('cs-bank-pending-slider');
    const knob = $('cs-bank-pending-knob');
    const note = $('cs-bank-pending-note');

    if (isChecked) {
        slider.style.background = '#7c3aed';
        knob.style.transform = 'translateX(20px)';
        note.classList.remove('hidden');
        $('cs-confirm-btn-label').textContent = 'Mark as Pending Verification';
    } else {
        slider.style.background = 'var(--outline)';
        knob.style.transform = 'translateX(0)';
        note.classList.add('hidden');
        csSelectPayType(csPayType); // restore label
    }
}

// ── POS: Calc Balance (Cash) ───────────────────────────────────────
function csCalcBalance() {
    if (!csCurrentJO) return;
    const paid = parseFloat($('cs-amount-paid').value) || 0;
    const total = csCurrentJO.total;
    const balance = csPayType === 'down' ? Math.max(0, total - paid) : 0;
    const change = csPayType === 'full' ? Math.max(0, paid - total) : 0;

    if ($('cs-cash-change'))
        $('cs-cash-change').value = change > 0 ? change.toFixed(2) : '0.00';
    if ($('cs-balance')) $('cs-balance').value = balance.toFixed(2);

    $('cs-receipt-tendered').textContent =
        `₱${paid.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
    $('cs-receipt-balance').textContent =
        `₱${balance.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
}

// ── POS: Calc Balance (Digital) ────────────────────────────────────
function csCalcBalanceDigital() {
    if (!csCurrentJO) return;
    const paid = parseFloat($('cs-digital-amount')?.value) || 0;
    const total = csCurrentJO.total;
    const balance = Math.max(0, total - paid);
    if ($('cs-digital-balance'))
        $('cs-digital-balance').value = balance.toFixed(2);
    $('cs-receipt-tendered').textContent =
        `₱${paid.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
    $('cs-receipt-balance').textContent =
        `₱${balance.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
}

// ── POS: Calc Balance (Bank) ───────────────────────────────────────
function csCalcBalanceBank() {
    if (!csCurrentJO) return;
    const paid = parseFloat($('cs-bank-amount')?.value) || 0;
    const total = csCurrentJO.total;
    const balance = Math.max(0, total - paid);
    if ($('cs-bank-balance')) $('cs-bank-balance').value = balance.toFixed(2);
    $('cs-receipt-tendered').textContent =
        `₱${paid.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
    $('cs-receipt-balance').textContent =
        `₱${balance.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
}

// ── POS: Confirm Payment ──────────────────────────────────────────
function csConfirmPayment() {
    if (!csCurrentJO) {
        csShowError('No JO loaded. Please load a Job Order first.');
        return;
    }

    const paid = parseFloat($('cs-amount-paid').value) || 0;
    if (!paid || paid <= 0) {
        csShowError('Please enter a valid payment amount.');
        return;
    }

    const total = csCurrentJO.total;

    if (csPayType === 'full' && paid < total) {
        csShowError(
            `Full payment amount is ₱${total.toFixed(2)}. The entered amount is insufficient.`,
        );
        return;
    }
    if (csPayType === 'down' && paid >= total) {
        csShowError(
            "For full payment, please select the 'Full Payment' option.",
        );
        return;
    }

    const isFullyPaid = paid >= total;
    const newStatus = isFullyPaid
        ? 'Fully Paid — JO Activated'
        : 'Partially Paid — Balance Pending';
    const badge = $('cs-jo-status-badge');
    badge.textContent = newStatus.toUpperCase();
    badge.className = isFullyPaid
        ? 'badge badge-success badge-dot'
        : 'badge badge-warning badge-dot';

    // Add payment row to receipt
    const receiptFooter =
        document.querySelector('#cs-line-items + div') ||
        $('cs-receipt-total')?.parentElement?.parentElement;

    // Show success toast
    const toast = $('cs-payment-success');
    toast.innerHTML = `<span class="material-symbols-outlined">check_circle</span>
    ${
        isFullyPaid
            ? `Full payment confirmed! JO ${csCurrentJO.joNumber} ay ACTIVE na. Receipt ready.`
            : `Down payment of ₱${paid.toFixed(2)} recorded. Balance: ₱${(total - paid).toFixed(2)}. JO flagged for balance.`
    }`;
    toast.classList.remove('hidden');
    setTimeout(() => toast.classList.add('hidden'), 5000);

    // Log to history
    csAddToHistory(
        csCurrentJO,
        paid,
        total - paid,
        isFullyPaid ? 'Fully Paid' : 'Partial',
    );

    // Disable confirm button to prevent double-submit
    const confirmBtn = document.querySelector("[onclick='csConfirmPayment()']");
    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.style.opacity = '0.5';
    }
}

// ── POS: Print Draft ──────────────────────────────────────────────
function csPrintDraft() {
    if (!csCurrentJO) {
        csShowError('No JO loaded.');
        return;
    }
    const paid = parseFloat($('cs-amount-paid').value) || 0;
    const balance = Math.max(0, csCurrentJO.total - paid);

    const win = window.open('', '_blank', 'width=400,height=600');
    win.document.write(`
    <html><head><title>Draft Receipt – ${csCurrentJO.joNumber}</title>
    <style>body{font-family:monospace;font-size:12px;padding:20px;max-width:320px;}
    .row{display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px dashed #ccc;}
    .total{font-size:18px;font-weight:bold;}h2{text-align:center;letter-spacing:2px;}
    .center{text-align:center;}.light{color:#888;font-size:11px;}</style></head>
    <body>
    <h2>INKSPIRE PRINT SHOP</h2>
    <p class="center light">DRAFT RECEIPT · ${new Date().toLocaleString('en-PH')}</p>
    <hr/>
    <div class="row"><span>JO Number</span><span>${csCurrentJO.joNumber}</span></div>
    <div class="row"><span>Customer</span><span>${csCurrentJO.customer}</span></div>
    <div class="row"><span>Type</span><span>${csCurrentJO.type}</span></div>
    <hr/>
    ${csCurrentJO.items.map((i) => `<div class="row"><span>${i.label}</span><span>₱${i.amount.toFixed(2)}</span></div>`).join('')}
    <hr/>
    <div class="row total"><span>TOTAL DUE</span><span>₱${csCurrentJO.total.toFixed(2)}</span></div>
    <div class="row"><span>Amount Paid</span><span>₱${paid.toFixed(2)}</span></div>
    <div class="row"><span>Balance</span><span>₱${balance.toFixed(2)}</span></div>
    <hr/><p class="center light">*** DRAFT ONLY — NOT OFFICIAL ***</p>
    <script>window.print();<\/script></body></html>
  `);
    win.document.close();
}

// ── History: Filter ───────────────────────────────────────────────
const csHistoryData = [
    {
        jo: 'JO-2026-0892',
        customer: 'Jose Reyes',
        type: 'Type B',
        paid: 400,
        balance: 0,
        status: 'Fully Paid',
        date: '2026-05-23 09:14 AM',
    },
    {
        jo: 'JO-2026-0870',
        customer: 'Maria Santos',
        type: 'Type A',
        paid: 400,
        balance: 0,
        status: 'Fully Paid',
        date: '2026-05-23 10:02 AM',
    },
    {
        jo: 'JO-2026-0855',
        customer: 'Lina Torres',
        type: 'Type B',
        paid: 300,
        balance: 330,
        status: 'Partial',
        date: '2026-05-22 14:30 PM',
    },
    {
        jo: 'JO-2026-0840',
        customer: 'Marcus Thorne',
        type: 'Type A',
        paid: 0,
        balance: 450,
        status: 'Cancelled',
        date: '2026-05-22 16:45 PM',
    },
    {
        jo: 'JO-2026-0828',
        customer: 'Acme Corp',
        type: 'Type A',
        paid: 4500,
        balance: 0,
        status: 'Fully Paid',
        date: '2026-05-21 08:30 AM',
    },
    {
        jo: 'JO-2026-0815',
        customer: 'Horizon Builders Ltd.',
        type: 'Type B',
        paid: 8250,
        balance: 0,
        status: 'Fully Paid',
        date: '2026-05-20 11:05 AM',
    },
];

let csLiveHistory = [...csHistoryData];

function csRenderHistory(data) {
    const tbody = $('cs-history-tbody');
    if (!tbody) return;
    if (!data.length) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--outline);">No records found.</td></tr>`;
        return;
    }
    const statusBadge = (s) => {
        if (s === 'Fully Paid')
            return `<span class="badge badge-success badge-dot">Fully Paid</span>`;
        if (s === 'Partial')
            return `<span class="badge badge-warning badge-dot">Partial</span>`;
        if (s === 'Refund Issued')
            return `<span class="badge badge-warning badge-dot">Refund Issued</span>`;
        if (s === 'Void Issued')
            return `<span class="badge badge-error badge-dot">Void Issued</span>`;
        if (s === 'Cancelled with Fee')
            return `<span class="badge badge-error badge-dot">Cancelled with Fee</span>`;
        if (s === 'Cancelled with Fee — Unpaid')
            return `<span class="badge badge-error badge-dot">Cancelled — Fee Unpaid</span>`;
        if (s === 'Pending Verification')
            return `<span class="badge badge-neutral badge-dot">Pending Verification</span>`;
        return `<span class="badge badge-error badge-dot">Cancelled</span>`;
    };
    tbody.innerHTML = data
        .map(
            (r) => `
    <tr>
      <td><span style="font-family:var(--font-mono);font-size:12px;color:var(--primary);">${r.jo}</span></td>
      <td style="font-weight:600;">${r.customer}</td>
      <td style="color:var(--on-surface-variant);">${r.type}</td>
      <td style="text-align:right;font-family:var(--font-mono);font-size:12px;">₱${r.paid.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
      <td style="text-align:right;font-family:var(--font-mono);font-size:12px;">₱${r.balance.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
      <td>${statusBadge(r.status)}</td>
      <td style="text-align:right;font-family:var(--font-mono);font-size:11px;color:var(--on-surface-variant);">${r.date}</td>
    </tr>
  `,
        )
        .join('');
    const counter =
        document.querySelector(
            "#cs-page-history .card:last-child [style*='Showing']",
        ) ||
        document.querySelector("#cs-page-history span[style*='font-mono']");
    if (counter)
        counter.textContent = `Showing 1–${data.length} of ${data.length} records`;
}

function csFilterHistory() {
    const status = $('cs-status-filter')?.value || 'all';

    // Map dropdown option values to actual status strings
    const statusMap = {
        'fully-paid': 'Fully Paid',
        partial: 'Partial',
        cancelled: 'Cancelled',
        'Cancelled with Fee': 'Cancelled with Fee',
        'Cancelled with Fee — Unpaid': 'Cancelled with Fee — Unpaid',
        'Refund Issued': 'Refund Issued',
        'Void Issued': 'Void Issued',
        'Pending Verification': 'Pending Verification',
    };

    let filtered = csLiveHistory.filter((r) => {
        if (status === 'all') return true;
        const target = statusMap[status] || status;
        return r.status === target;
    });

    csRenderHistory(filtered);
}

function csAddToHistory(jo, paid, balance, status) {
    const now = new Date();
    const dateStr =
        now.toLocaleDateString('en-PH', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        }) +
        ' ' +
        now.toLocaleTimeString('en-PH', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        });
    const type = jo.type
        ? jo.type.startsWith('Type A')
            ? 'Type A'
            : jo.type.startsWith('Type B')
              ? 'Type B'
              : jo.type
        : '—';
    csLiveHistory.unshift({
        jo: jo.joNumber,
        customer: jo.customer,
        type,
        paid,
        balance,
        status,
        date: dateStr,
    });
}

// ── Cancellation Fee ──────────────────────────────────────────────
function csCancellationReceipt() {
    const win = window.open('', '_blank', 'width=400,height=500');
    win.document.write(`
    <html><head><title>Cancellation Receipt – JO-2023-8901</title>
    <style>body{font-family:monospace;font-size:12px;padding:20px;max-width:320px;}
    .row{display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px dashed #ccc;}
    .total{font-size:18px;font-weight:bold;}h2{text-align:center;letter-spacing:2px;}
    .center{text-align:center;}.light{color:#888;font-size:11px;}</style></head>
    <body>
    <h2>INKSPIRE PRINT SHOP</h2>
    <p class="center light">CANCELLATION RECEIPT · ${new Date().toLocaleString('en-PH')}</p>
    <hr/>
    <div class="row"><span>JO Number</span><span>JO-2023-8901</span></div>
    <div class="row"><span>Client</span><span>Doe, Jane A.</span></div>
    <div class="row"><span>Artist</span><span>T. Rivers (Stn. 4)</span></div>
    <div class="row"><span>Flag Reason</span><span>Design Work Started</span></div>
    <hr/>
    <div class="row"><span>Flat Rate Cancellation Fee</span><span>₱25.00</span></div>
    <hr/>
    <div class="row total"><span>TOTAL DUE</span><span>₱25.00</span></div>
    <hr/>
    <p class="center light">Client profile will be unlocked upon payment.</p>
    <script>window.print();<\/script></body></html>
  `);
    win.document.close();
}

function csCancellationProcess() {
    const btn = document.querySelector("[onclick='csCancellationProcess()']");
    if (btn) {
        btn.disabled = true;
        btn.style.opacity = '0.5';
    }

    const banner = $('cs-cancel-success');
    banner.innerHTML = `<span class="material-symbols-outlined">check_circle</span>
    Cancellation fee na ₱25.00 ay na-process na. Client profile ni Jane A. Doe ay na-unlock na para sa susunod na booking.`;
    banner.classList.remove('hidden');
    banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    // Add to history
    csLiveHistory.unshift({
        jo: 'JO-2023-8901',
        customer: 'Doe, Jane A.',
        type: 'Type B',
        paid: 25,
        balance: 0,
        status: 'Cancelled',
        date:
            new Date().toLocaleDateString('en-PH', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
            }) +
            ' ' +
            new Date().toLocaleTimeString('en-PH', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true,
            }),
    });
}

// ── POS: Error Display ────────────────────────────────────────────
function csShowError(msg) {
    let errEl = $('cs-pos-error');
    if (!errEl) {
        errEl = document.createElement('div');
        errEl.id = 'cs-pos-error';
        errEl.style.cssText =
            'margin-bottom:16px;padding:10px 14px;background:var(--error-container);color:#93000a;border-radius:var(--radius);font-size:13px;font-weight:500;display:flex;align-items:center;gap:8px;';
        const searchBar = $('cs-page-pos').querySelector("[style*='gap:12px']");
        if (searchBar) searchBar.before(errEl);
    }
    errEl.innerHTML = `<span class="material-symbols-outlined" style="font-size:16px;">error</span>${msg}`;
    errEl.classList.remove('hidden');
    setTimeout(() => errEl.classList.add('hidden'), 5000);
}

// ── Cashier Clock ─────────────────────────────────────────────────
CsRouter.register('pos', () => {
    csRenderHistory(csLiveHistory);
});
CsRouter.register('history', () => {
    csRenderHistory(csLiveHistory);
});
CsRouter.register('cancellation', () => {});
CsRouter.register('balance-collection', () => {});
CsRouter.register('void-refund', () => {
    vrReset();
});

// ══════════════════════════════════════════════════════════════════
//  CASHIER — NOTIFICATION BELL (Cancellation Alerts)
// ══════════════════════════════════════════════════════════════════

let csNotifications = [
    // Seeded demo notifications
    {
        id: 'NOTIF-001',
        joNumber: 'JO-2026-0903',
        clientName: 'Celine Macaraeg',
        cancellationFee: 75,
        artistName: 'T. Rivera (Station 2)',
        timestamp: '10:42 AM · Today',
        dismissed: false,
    },
    {
        id: 'NOTIF-002',
        joNumber: 'JO-2026-0899',
        clientName: 'Ronaldo Cruz',
        cancellationFee: 50,
        artistName: 'M. Bautista (Station 1)',
        timestamp: '09:15 AM · Today',
        dismissed: false,
    },
];
let csNotifDropdownOpen = false;
let csPendingNotifJO = null; // for "Go to Cancellation Fee" from popup

function csGetActiveNotifications() {
    return csNotifications.filter((n) => !n.dismissed);
}

function csUpdateNotifBadge() {
    const count = csGetActiveNotifications().length;
    const badge = document.getElementById('cs-notif-badge');
    if (!badge) return;
    if (count > 0) {
        badge.style.display = 'flex';
        badge.textContent = count;
    } else {
        badge.style.display = 'none';
    }
}

function csRenderNotifDropdown() {
    const active = csGetActiveNotifications();
    const list = document.getElementById('cs-notif-list');
    const empty = document.getElementById('cs-notif-empty');
    const countLabel = document.getElementById('cs-notif-count-label');
    if (!list) return;

    if (active.length === 0) {
        list.innerHTML = '';
        if (empty) empty.classList.remove('hidden');
        if (countLabel) countLabel.textContent = '0 alerts';
        return;
    }
    if (empty) empty.classList.add('hidden');
    if (countLabel) countLabel.textContent = `${active.length} pending`;

    list.innerHTML = active
        .map(
            (n) => `
    <div style="padding: 14px 16px; border-bottom: 1px solid var(--outline-variant);" id="cs-notif-item-${n.id}">
      <div style="display: flex; align-items: flex-start; gap: 10px;">
        <span class="material-symbols-outlined" style="color: var(--error); font-size: 18px; margin-top: 1px; flex-shrink: 0;">warning</span>
        <div style="flex: 1; min-width: 0;">
          <div style="font-weight: 700; font-size: 13px; color: var(--error);">Cancellation Fee Required</div>
          <div style="font-size: 12px; color: var(--on-surface-variant); margin-top: 2px;">${n.joNumber} · ${n.clientName}</div>
          <div style="font-size: 12px; color: var(--on-surface-variant);">Artist: ${n.artistName}</div>
          <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 8px;">
            <span style="font-family: var(--font-mono); font-weight: 700; font-size: 14px; color: var(--error);">₱${n.cancellationFee.toFixed(2)}</span>
            <span style="font-size: 10px; color: var(--on-surface-variant); font-family: var(--font-mono);">${n.timestamp}</span>
          </div>
          <div style="display: flex; gap: 6px; margin-top: 10px;">
            <button class="btn btn-sm btn-outline" style="font-size: 11px; padding: 4px 10px;" onclick="csDismissNotif('${n.id}')">Dismiss</button>
            <button class="btn btn-sm" style="font-size: 11px; padding: 4px 10px; background: var(--error); color: white; font-weight: 700;" onclick="csGoToCancellationFromNotif('${n.id}')">
              Process Fee
            </button>
          </div>
        </div>
      </div>
    </div>
  `,
        )
        .join('');
}

function csToggleNotifDropdown() {
    csNotifDropdownOpen = !csNotifDropdownOpen;
    const dd = document.getElementById('cs-notif-dropdown');
    if (!dd) return;
    if (csNotifDropdownOpen) {
        dd.classList.remove('hidden');
        csRenderNotifDropdown();
        // Close on outside click
        setTimeout(() => {
            document.addEventListener('click', csCloseNotifOnOutside, {
                once: true,
            });
        }, 10);
    } else {
        dd.classList.add('hidden');
    }
}

function csCloseNotifOnOutside(e) {
    const wrapper = document.getElementById('cs-notif-bell-wrapper');
    if (wrapper && !wrapper.contains(e.target)) {
        csNotifDropdownOpen = false;
        const dd = document.getElementById('cs-notif-dropdown');
        if (dd) dd.classList.add('hidden');
    }
}

function csDismissNotif(id) {
    const n = csNotifications.find((x) => x.id === id);
    if (n) n.dismissed = true;
    csUpdateNotifBadge();
    csRenderNotifDropdown();
}

function csGoToCancellationFromNotif(id) {
    const n = csNotifications.find((x) => x.id === id);
    if (!n) return;
    csPendingNotifJO = n;
    // Close dropdown
    csNotifDropdownOpen = false;
    const dd = document.getElementById('cs-notif-dropdown');
    if (dd) dd.classList.add('hidden');
    // Navigate to cancellation page and pre-fill
    if (window.CsRouter) CsRouter.navigate('cancellation');
    // Dismiss this notification
    n.dismissed = true;
    csUpdateNotifBadge();
}

// Pop-up for new incoming notifications (simulated trigger)
function csTriggerNotifPopup(notif) {
    csPendingNotifJO = notif;
    const body = document.getElementById('cs-notif-popup-body');
    if (body) {
        body.innerHTML = `
      <div style="margin-bottom: 12px;">
        <div style="font-weight: 700; font-size: 14px; margin-bottom: 4px;">${notif.joNumber} — ${notif.clientName}</div>
        <div style="font-size: 12px; color: var(--on-surface-variant);">Artist: ${notif.artistName}</div>
        <div style="font-size: 12px; color: var(--on-surface-variant);">Cancellation fee triggered after design work began.</div>
      </div>
      <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: var(--error-container); border-radius: var(--radius);">
        <span style="font-size: 13px; color: #93000a;">Cancellation Fee</span>
        <span style="font-family: var(--font-mono); font-weight: 800; font-size: 18px; color: var(--error);">₱${notif.cancellationFee.toFixed(2)}</span>
      </div>
    `;
    }
    const popup = document.getElementById('cs-notif-popup');
    if (popup) popup.classList.remove('hidden');
}

function csDismissNotifPopup() {
    const popup = document.getElementById('cs-notif-popup');
    if (popup) popup.classList.add('hidden');
    csPendingNotifJO = null;
}

function csGoToCancellationFromPopup() {
    csDismissNotifPopup();
    if (window.CsRouter) CsRouter.navigate('cancellation');
}

// Demo: trigger a new notification on demand (exposed globally for demo button)
function csDemoTriggerNewNotif() {
    const demo = {
        id: `NOTIF-DEMO-${Date.now()}`,
        joNumber: 'JO-2026-0912',
        clientName: 'Patricia Lim',
        cancellationFee: 100,
        artistName: 'K. Soriano (Station 3)',
        timestamp: 'Now',
        dismissed: false,
    };
    csNotifications.push(demo);
    csUpdateNotifBadge();
    csTriggerNotifPopup(demo);
}

// Initialize badge on load
window.addEventListener('load', () => {
    csUpdateNotifBadge();
});

// ══════════════════════════════════════════════════════════════════
//  CASHIER — DYNAMIC PRICE BREAKDOWN (csRenderJO enhancement)
// ══════════════════════════════════════════════════════════════════

// Extend csJODatabase with full specs for dynamic breakdown
const csJOSpecs = {
    'JO-2026-0892': {
        productType: 'Tarpaulin / Large Format',
        dimensions: '4ft × 8ft',
        quantity: '1 pc',
        urgency: 'Rush',
        urgencyColor: '#f59e0b',
        hasOverride: false,
    },
    'JO-2026-0870': {
        productType: 'Sticker Printing',
        dimensions: '2in × 2in',
        quantity: '100 pcs',
        urgency: 'Normal',
        urgencyColor: 'var(--success)',
        hasOverride: false,
    },
    'JO-2026-0855': {
        productType: 'Business Cards',
        dimensions: 'Standard (2×3.5in)',
        quantity: '500 pcs',
        urgency: 'Normal',
        urgencyColor: 'var(--success)',
        hasOverride: false,
    },
};

// ══════════════════════════════════════════════════════════════════
//  CASHIER — VOID / REFUND FLOW
// ══════════════════════════════════════════════════════════════════

let vrCurrentJO = null;
let vrScenario = 'error'; // error | overpay | cancel-dp
let vrSession = 'same'; // same | post
let vrOverpayMethod = 'cash'; // cash | credit

// Demo JO data for void/refund — includes partially-paid JO
const vrJODatabase = {
    'JO-2026-0892': {
        joNumber: 'JO-2026-0892',
        customer: 'Jose Reyes',
        total: 400,
        paid: 400,
        balance: 0,
        status: 'Fully Paid',
    },
    'JO-2026-0850': {
        joNumber: 'JO-2026-0850',
        customer: 'Ana Garcia',
        total: 650,
        paid: 300,
        balance: 350,
        status: 'Partially Paid',
    },
    'JO-2026-0840': {
        joNumber: 'JO-2026-0840',
        customer: 'Marco Dela Cruz',
        total: 250,
        paid: 250,
        balance: 0,
        status: 'Fully Paid',
    },
};

function vrReset() {
    vrCurrentJO = null;
    const panel = document.getElementById('vr-panel');
    if (panel) panel.classList.add('hidden');
    const input = document.getElementById('vr-jo-input');
    if (input) input.value = '';
    ['vr-success-banner', 'vr-auth-fail-banner', 'vr-load-error'].forEach(
        (id) => {
            const el = document.getElementById(id);
            if (el) el.classList.add('hidden');
        },
    );
    const recEmpty = document.getElementById('vr-record-empty');
    const recDetail = document.getElementById('vr-record-detail');
    if (recEmpty) recEmpty.classList.remove('hidden');
    if (recDetail) recDetail.classList.add('hidden');
}

function vrLoadJO() {
    const input = document.getElementById('vr-jo-input');
    if (!input) return;
    const key = input.value.trim().toUpperCase();
    if (!key) {
        vrShowLoadError('Please enter a JO Number to load.');
        return;
    }
    const jo = vrJODatabase[key] || csJODatabase[key];
    if (!jo) {
        vrShowLoadError(
            `JO Number "${key}" not found. Try JO-2026-0892 or JO-2026-0850.`,
        );
        return;
    }
    // Normalize
    const normalized = {
        joNumber: jo.joNumber,
        customer: jo.customer,
        total: jo.total || jo.items?.reduce((s, i) => s + i.amount, 0) || 0,
        paid:
            jo.paid !== undefined
                ? jo.paid
                : jo.total || jo.items?.reduce((s, i) => s + i.amount, 0) || 0,
        balance: jo.balance !== undefined ? jo.balance : 0,
        status: jo.status || 'Fully Paid',
    };
    vrCurrentJO = normalized;
    vrRenderPanel();
}

function vrLoadDemo() {
    document.getElementById('vr-jo-input').value = 'JO-2026-0850';
    vrLoadJO();
}

function vrShowLoadError(msg) {
    const el = document.getElementById('vr-load-error');
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 4000);
}

function vrRenderPanel() {
    const jo = vrCurrentJO;
    if (!jo) return;

    const fmt = (v) =>
        `₱${v.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;

    document.getElementById('vr-jo-number-label').textContent = jo.joNumber;
    document.getElementById('vr-customer-name').textContent = jo.customer;
    document.getElementById('vr-original-total').textContent = fmt(jo.total);
    document.getElementById('vr-paid-amount').textContent = fmt(jo.paid);
    document.getElementById('vr-outstanding').textContent = fmt(jo.balance);

    const badge = document.getElementById('vr-status-badge');
    badge.textContent = jo.status.toUpperCase();
    badge.className =
        jo.status === 'Fully Paid'
            ? 'badge badge-success badge-dot'
            : 'badge badge-warning badge-dot';

    // Autofill refund amount for cancel-dp
    const refundInput = document.getElementById('vr-cancel-refund-amount');
    if (refundInput) refundInput.value = jo.paid.toFixed(2);

    document.getElementById('vr-panel').classList.remove('hidden');
    ['vr-success-banner', 'vr-auth-fail-banner'].forEach((id) => {
        document.getElementById(id)?.classList.add('hidden');
    });

    vrSelectScenario(vrScenario);
}

function vrSelectScenario(s) {
    vrScenario = s;
    const scenarios = ['error', 'overpay', 'cancel-dp'];
    scenarios.forEach((sc) => {
        const opt = document.getElementById(`vr-opt-${sc}`);
        const form = document.getElementById(`vr-form-${sc}`);
        if (opt) opt.classList.toggle('selected', sc === s);
        if (form) form.classList.toggle('hidden', sc !== s);
    });
}

function vrSelectSession(s) {
    vrSession = s;
    const same = document.getElementById('vr-session-same-label');
    const post = document.getElementById('vr-session-post-label');
    const postAuth = document.getElementById('vr-post-session-auth');
    if (same) {
        same.style.borderColor =
            s === 'same' ? 'var(--primary)' : 'var(--outline-variant)';
        same.style.background =
            s === 'same'
                ? 'var(--primary-fixed)'
                : 'var(--surface-container-low)';
        same.querySelector('div > div:first-child').style.color =
            s === 'same' ? 'var(--primary)' : 'var(--on-surface)';
    }
    if (post) {
        post.style.borderColor =
            s === 'post' ? 'var(--error)' : 'var(--outline-variant)';
        post.style.background =
            s === 'post'
                ? 'var(--error-container)'
                : 'var(--surface-container-low)';
    }
    if (postAuth) postAuth.classList.toggle('hidden', s !== 'post');
}

function vrSelectOverpayMethod(m) {
    vrOverpayMethod = m;
    const cashL = document.getElementById('vr-over-cash-label');
    const creditL = document.getElementById('vr-over-credit-label');
    if (cashL) {
        cashL.style.borderColor =
            m === 'cash' ? 'var(--primary)' : 'var(--outline-variant)';
        cashL.style.background =
            m === 'cash'
                ? 'var(--primary-fixed)'
                : 'var(--surface-container-low)';
        cashL.querySelector('div > div:first-child').style.color =
            m === 'cash' ? 'var(--primary)' : 'var(--on-surface)';
    }
    if (creditL) {
        creditL.style.borderColor =
            m === 'credit' ? '#7c3aed' : 'var(--outline-variant)';
        creditL.style.background =
            m === 'credit' ? '#f5f3ff' : 'var(--surface-container-low)';
    }
}

function vrCalcOverpayment() {
    if (!vrCurrentJO) return;
    const paid =
        parseFloat(document.getElementById('vr-over-paid')?.value) || 0;
    const over = Math.max(0, paid - vrCurrentJO.total);
    const el = document.getElementById('vr-over-amount');
    if (el) el.value = over.toFixed(2);
}

function vrShowRecord(type, amount) {
    const fmt = (v) =>
        `₱${v.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
    const now = new Date();
    const time =
        now.toLocaleTimeString('en-PH', {
            hour: '2-digit',
            minute: '2-digit',
        }) + ' · Today';

    document.getElementById('vr-rec-jo').textContent = vrCurrentJO.joNumber;
    document.getElementById('vr-rec-type').textContent = type;
    document.getElementById('vr-rec-amount').textContent = fmt(amount);
    document.getElementById('vr-rec-time').textContent = time;

    document.getElementById('vr-record-empty').classList.add('hidden');
    document.getElementById('vr-record-detail').classList.remove('hidden');
}

function vrShowSuccess(msg) {
    const banner = document.getElementById('vr-success-banner');
    const msgEl = document.getElementById('vr-success-msg');
    if (msgEl) msgEl.textContent = msg;
    if (banner) {
        banner.classList.remove('hidden');
        setTimeout(() => banner.classList.add('hidden'), 7000);
    }
}

function vrShowAuthFail() {
    const banner = document.getElementById('vr-auth-fail-banner');
    if (banner) {
        banner.classList.remove('hidden');
        setTimeout(() => banner.classList.add('hidden'), 5000);
    }
}

// ── Process Payment Error Void ─────────────────────────────────────
function vrProcessError() {
    if (!vrCurrentJO) return;
    const reason = document.getElementById('vr-error-reason')?.value.trim();
    if (!reason) {
        alert('Please enter a reason for the void.');
        return;
    }
    if (vrSession === 'post') {
        const user = document.getElementById('vr-auth-user')?.value.trim();
        const pin = document.getElementById('vr-auth-pin')?.value.trim();
        // Demo: accept "admin" / "1234"
        if (user !== 'admin' || pin !== '1234') {
            vrShowAuthFail();
            return;
        }
    }
    vrShowRecord(
        vrSession === 'same'
            ? 'Void — Same Session'
            : 'Void — Post-Session (Authorized)',
        vrCurrentJO.paid,
    );
    vrShowSuccess(
        `Transaction for ${vrCurrentJO.joNumber} (${vrCurrentJO.customer}) has been voided. ${vrSession === 'same' ? 'Re-enter corrected payment in POS.' : 'Admin authorization logged. Void approved.'}`,
    );
    // Log to history
    csAddToHistory(
        {
            joNumber: vrCurrentJO.joNumber,
            customer: vrCurrentJO.customer,
            type: 'Void',
        },
        0,
        vrCurrentJO.paid,
        'Void Issued',
    );
}

// ── Process Overpayment ────────────────────────────────────────────
function vrProcessOverpayment() {
    if (!vrCurrentJO) return;
    const paid =
        parseFloat(document.getElementById('vr-over-paid')?.value) || 0;
    if (!paid || paid <= vrCurrentJO.total) {
        alert(
            'Please enter the actual amount paid by the client (must be greater than the total).',
        );
        return;
    }
    const over = paid - vrCurrentJO.total;
    const methodLabel =
        vrOverpayMethod === 'cash'
            ? 'Cash Change Issued'
            : 'Credit Memo Logged';
    vrShowRecord(methodLabel, over);
    vrShowSuccess(
        vrOverpayMethod === 'cash'
            ? `Overpayment of ₱${over.toFixed(2)} recorded. Issue ₱${over.toFixed(2)} cash change to ${vrCurrentJO.customer}.`
            : `Credit memo of ₱${over.toFixed(2)} logged for ${vrCurrentJO.customer}. Credit visible in AR module.`,
    );
    csAddToHistory(
        {
            joNumber: vrCurrentJO.joNumber,
            customer: vrCurrentJO.customer,
            type: 'Overpayment',
        },
        vrCurrentJO.total,
        0,
        'Refund Issued',
    );
}

// ── Process Cancelled with Down Payment ───────────────────────────
function vrProcessCancelDP() {
    if (!vrCurrentJO) return;
    const user = document.getElementById('vr-cancel-auth-user')?.value.trim();
    const pin = document.getElementById('vr-cancel-auth-pin')?.value.trim();
    // Demo: accept "admin" / "1234"
    if (user !== 'admin' || pin !== '1234') {
        vrShowAuthFail();
        return;
    }
    const refund =
        parseFloat(document.getElementById('vr-cancel-refund-amount')?.value) ||
        0;
    if (!refund || refund > vrCurrentJO.paid) {
        alert('Refund amount must not exceed the amount paid by the client.');
        return;
    }
    const reason =
        document.getElementById('vr-cancel-reason')?.value.trim() ||
        'No reason provided';
    vrShowRecord('Refund — Cancelled w/ Down Payment', refund);
    vrShowSuccess(
        `Refund of ₱${refund.toFixed(2)} approved for ${vrCurrentJO.customer} (${vrCurrentJO.joNumber}). Refund receipt generated. JO finalized as "Refund Issued". AR and audit trail updated.`,
    );
    csAddToHistory(
        {
            joNumber: vrCurrentJO.joNumber,
            customer: vrCurrentJO.customer,
            type: 'Cancellation w/ Refund',
        },
        refund,
        0,
        'Refund Issued',
    );
}

// ══════════════════════════════════════════════════════════════════
const PdRouter = {
    pages: {},
    currentPage: null,
    pageTitles: {
        'prod-queue': 'Production Queue',
        'prod-active': 'Active Jobs',
        'prod-history': 'Completed Orders',
        'prod-complaints': 'Quality Complaints',
    },
    register(name, fn) {
        this.pages[name] = fn;
    },
    go(name) {
        $$("[id^='pd-page-']").forEach((p) => p.classList.add('hidden'));
        const target = $(`pd-page-${name}`);
        if (target) {
            target.classList.remove('hidden');
            target.classList.add('animate-in');
        }
        $$("[data-shell='pd']").forEach((n) => n.classList.remove('active'));
        const navItem = document.querySelector(
            `[data-page="${name}"][data-shell="pd"]`,
        );
        if (navItem) navItem.classList.add('active');
        const titleEl = $('pd-page-title');
        if (titleEl && this.pageTitles[name])
            titleEl.textContent = this.pageTitles[name];
        this.currentPage = name;
        if (this.pages[name]) this.pages[name]();
    },
};

// ── Production: JO Data ───────────────────────────────────────────
// paymentState: 'full' | 'partial' | 'unpaid'
// balance: remaining amount (for partial)
const pdJoData = [
    {
        jo: 'JO-2026-1008',
        client: 'Dela Rosa, Carlo M.',
        product: 'Tarpaulin 6×10ft · Event Banner',
        type: 'B',
        joTag: 'New Order — Type B',
        deadline: 'Today, 2:00 PM',
        deadlineDate: (() => {
            const d = new Date();
            d.setHours(14, 0, 0, 0);
            return d;
        })(),
        urgency: 'Rush',
        status: 'For Production',
        assigned: 'M. Dela Cruz',
        paid: true,
        paymentState: 'full',
        balance: 0,
        file: {
            name: 'dela_rosa_banner_approved.pdf',
            format: 'PDF',
            size: '4.2 MB',
            label: 'Approved Layout (Type B)',
        },
    },
    {
        jo: 'JO-2026-1005',
        client: 'Cruz, Maria R.',
        product: 'Tarpaulin 3×6ft · Vinyl Matte',
        type: 'A',
        joTag: 'New Order — Type A',
        deadline: 'Today, 5:00 PM',
        deadlineDate: (() => {
            const d = new Date();
            d.setHours(17, 0, 0, 0);
            return d;
        })(),
        urgency: 'Rush',
        status: 'For Production',
        assigned: 'Unassigned',
        paid: true,
        paymentState: 'partial',
        balance: 450,
        file: {
            name: 'cruz_tarp_3x6ft_v2.pdf',
            format: 'PDF',
            size: '8.7 MB',
            label: 'Client Upload (Type A)',
        },
    },
    {
        jo: 'JO-2026-1003',
        client: 'Santos, Juan P.',
        product: 'Business Cards 500pcs · Matte 350gsm',
        type: 'A',
        joTag: 'New Order — Type A',
        deadline: 'Today, 6:00 PM',
        deadlineDate: (() => {
            const d = new Date();
            d.setHours(18, 0, 0, 0);
            return d;
        })(),
        urgency: 'Rush',
        status: 'Printing',
        assigned: 'M. Dela Cruz',
        paid: true,
        paymentState: 'full',
        balance: 0,
        file: {
            name: 'santos_bizcard_final.ai',
            format: 'AI',
            size: '2.1 MB',
            label: 'Client Upload (Type A)',
        },
    },
    {
        jo: 'JO-2026-0998',
        client: 'Reyes, Ana L.',
        product: 'ID Cards 20pcs · PVC 0.76mm',
        type: 'B',
        joTag: 'New Order — Type B',
        deadline: 'Tomorrow, 9:00 AM',
        deadlineDate: (() => {
            const d = new Date();
            d.setDate(d.getDate() + 1);
            d.setHours(9, 0, 0, 0);
            return d;
        })(),
        urgency: 'Medium',
        status: 'Quality Check',
        assigned: 'R. Bautista',
        paid: true,
        paymentState: 'partial',
        balance: 1200,
        file: {
            name: 'reyes_id_cards_approved.pdf',
            format: 'PDF',
            size: '1.4 MB',
            label: 'Approved Layout (Type B)',
        },
    },
    {
        jo: 'JO-2026-0996',
        client: 'Villanueva, Pat C.',
        product: 'Tarpaulin 4×8ft · Matte Vinyl · Reprint',
        type: 'A',
        joTag: 'Reprint — Client Request',
        deadline: 'Tomorrow, 2:00 PM',
        deadlineDate: (() => {
            const d = new Date();
            d.setDate(d.getDate() + 1);
            d.setHours(14, 0, 0, 0);
            return d;
        })(),
        urgency: 'Normal',
        status: 'For Production',
        assigned: 'Unassigned',
        paid: true,
        paymentState: 'full',
        balance: 0,
        file: {
            name: 'villanueva_tarp_original_approved.pdf',
            format: 'PDF',
            size: '5.1 MB',
            label: 'Original File (Reprint)',
        },
        linkedOriginalJo: 'JO-2026-0850',
    },
    {
        jo: 'JO-2026-0995',
        client: 'Tan, Kevin O.',
        product: 'Flyers 1000pcs · A5 · Gloss 130gsm',
        type: 'A',
        joTag: 'New Order — Type A',
        deadline: 'Tomorrow, 12:00 PM',
        deadlineDate: (() => {
            const d = new Date();
            d.setDate(d.getDate() + 1);
            d.setHours(12, 0, 0, 0);
            return d;
        })(),
        urgency: 'Normal',
        status: 'For Production',
        assigned: 'Unassigned',
        paid: false,
        paymentState: 'unpaid',
        balance: 0,
        file: {
            name: 'tan_flyer_a5_print_ready.pdf',
            format: 'PDF',
            size: '12.3 MB',
            label: 'Client Upload (Type A)',
        },
    },
    {
        jo: 'JO-2026-0993',
        client: 'Mendoza, Lara T.',
        product: 'Streamer Banner 2×5ft · Revision',
        type: 'B',
        joTag: 'Revision Order',
        deadline: 'Tomorrow, 4:00 PM',
        deadlineDate: (() => {
            const d = new Date();
            d.setDate(d.getDate() + 1);
            d.setHours(16, 0, 0, 0);
            return d;
        })(),
        urgency: 'Normal',
        status: 'For Production',
        assigned: 'R. Bautista',
        paid: true,
        paymentState: 'full',
        balance: 0,
        file: {
            name: 'mendoza_streamer_v2_approved.pdf',
            format: 'PDF',
            size: '3.2 MB',
            label: 'Revised Layout (Revision)',
        },
        linkedOriginalJo: 'JO-2026-0921',
    },
    {
        jo: 'JO-2026-0991',
        client: 'Lim, Grace B.',
        product: 'Sintra Board Sign 4×8ft',
        type: 'B',
        joTag: 'New Order — Type B',
        deadline: 'May 25, 10:00 AM',
        deadlineDate: (() => {
            const d = new Date();
            d.setDate(d.getDate() - 1);
            d.setHours(10, 0, 0, 0);
            return d;
        })(),
        urgency: 'Normal',
        status: 'Ready for Pickup',
        assigned: 'M. Dela Cruz',
        paid: true,
        paymentState: 'full',
        balance: 0,
        file: {
            name: 'lim_sintra_sign_v3.pdf',
            format: 'PDF',
            size: '6.8 MB',
            label: 'Approved Layout (Type B)',
        },
    },
    {
        jo: 'JO-2026-0988',
        client: 'Garcia, Ben S.',
        product: 'Sticker Roll · Die-cut · 200pcs',
        type: 'A',
        joTag: 'New Order — Type A',
        deadline: 'May 25, 2:00 PM',
        deadlineDate: (() => {
            const d = new Date();
            d.setDate(d.getDate() - 1);
            d.setHours(14, 0, 0, 0);
            return d;
        })(),
        urgency: 'Normal',
        status: 'Ready for Pickup',
        assigned: 'R. Bautista',
        paid: true,
        paymentState: 'full',
        balance: 0,
        file: {
            name: 'garcia_sticker_diecut.cdr',
            format: 'CDR',
            size: '3.5 MB',
            label: 'Client Upload (Type A)',
        },
    },
];

let pdActiveJo = null;
const pdHistory = [];

// Urgency sort order — Rush first
const PD_URGENCY_ORDER = {
    Rush: 0,
    High: 1,
    Medium: 2,
    Normal: 3,
    Low: 3,
};

function pdUrgencyBadge(u) {
    const map = {
        Rush: 'background:#fef2f2;color:#991b1b;border:1px solid #fca5a5;',
        High: 'background:#fef3c7;color:#92400e;border:1px solid #fcd34d;',
        Medium: 'background:#eff6ff;color:#1e40af;border:1px solid #93c5fd;',
        Normal: 'background:var(--surface-container);color:var(--on-surface-variant);border:1px solid var(--outline-variant);',
        Low: 'background:var(--surface-container);color:var(--on-surface-variant);border:1px solid var(--outline-variant);',
    };
    return `<span style="font-family:var(--font-mono);font-size:11px;font-weight:700;padding:3px 8px;border-radius:var(--radius-full);${map[u] || map.Normal}">${u}</span>`;
}

function pdStatusBadge(s) {
    const map = {
        'For Production': 'color:#166534;background:#dcfce7;',
        Printing: 'color:#92400e;background:#fef3c7;',
        'Quality Check': 'color:#5b21b6;background:#ede9fe;',
        'Ready for Pickup': 'color:#0e7490;background:var(--primary-fixed);',
    };
    const dot = {
        'For Production': '#22c55e',
        Printing: '#f59e0b',
        'Quality Check': '#8b5cf6',
        'Ready for Pickup': '#1e40af',
    };
    const st = map[s] || '';
    const dc = dot[s] || '#999';
    return `<span style="display:inline-flex;align-items:center;gap:5px;font-family:var(--font-mono);font-size:11px;font-weight:600;padding:3px 10px;border-radius:var(--radius-full);${st}"><span style="width:6px;height:6px;border-radius:50%;background:${dc};flex-shrink:0;"></span>${s}</span>`;
}

// MAJOR 1: Three-state payment badge
function pdPaymentBadge(jo) {
    const ps = jo.paymentState || (jo.paid ? 'full' : 'unpaid');
    if (ps === 'full') {
        return `<span style="font-family:var(--font-mono);font-size:11px;font-weight:600;padding:3px 10px;border-radius:var(--radius-full);background:#dcfce7;color:#166534;">✓ Fully Paid</span>`;
    } else if (ps === 'partial') {
        const bal = jo.balance
            ? `₱${jo.balance.toLocaleString()}`
            : 'Balance due';
        return `<span style="font-family:var(--font-mono);font-size:11px;font-weight:600;padding:3px 10px;border-radius:var(--radius-full);background:#fef3c7;color:#92400e;">⚠ Partial · ${bal} due</span>`;
    } else {
        return `<span style="font-family:var(--font-mono);font-size:11px;font-weight:600;padding:3px 10px;border-radius:var(--radius-full);background:var(--error-container);color:var(--error);">✗ Unpaid</span>`;
    }
}

// MAJOR 5: JO Type Tag badge — shows full tag (New Order, Reprint, Revision, Store Error)
function pdJoTagBadge(jo) {
    const tag =
        jo.joTag ||
        (jo.type === 'A' ? 'New Order — Type A' : 'New Order — Type B');
    const tagStyles = {
        'New Order — Type A':
            'background:var(--surface-container);color:var(--on-surface-variant);border:1px solid var(--outline-variant);',
        'New Order — Type B':
            'background:var(--surface-container);color:var(--on-surface-variant);border:1px solid var(--outline-variant);',
        'Reprint — Client Request':
            'background:#eff6ff;color:#1e40af;border:1px solid #93c5fd;',
        'Reprint — Store Error':
            'background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;',
        'Revision Order':
            'background:#f3e8ff;color:#7c3aed;border:1px solid #c4b5fd;',
    };
    const tagIcons = {
        'New Order — Type A': 'fiber_new',
        'New Order — Type B': 'fiber_new',
        'Reprint — Client Request': 'replay',
        'Reprint — Store Error': 'warning',
        'Revision Order': 'edit_note',
    };
    const style = tagStyles[tag] || tagStyles['New Order — Type A'];
    const icon = tagIcons[tag] || 'label';
    // Short label for table display
    const shortLabels = {
        'New Order — Type A': 'Type A',
        'New Order — Type B': 'Type B',
        'Reprint — Client Request': 'Reprint',
        'Reprint — Store Error': '⚠ Store Error',
        'Revision Order': 'Revision',
    };
    const short = shortLabels[tag] || tag;
    return `<span title="${tag}" style="font-family:var(--font-mono);font-size:11px;font-weight:700;padding:3px 9px;border-radius:var(--radius-full);display:inline-flex;align-items:center;gap:4px;${style}"><span class="material-symbols-outlined" style="font-size:11px;">${icon}</span>${short}</span>`;
}

// ── Production Queue Page ─────────────────────────────────────────
function pdRenderQueue(filter) {
    const tbody = $('pd-queue-table-body');
    if (!tbody) return;
    let rows;
    if (!filter || filter === 'All') {
        rows = [...pdJoData].sort(
            (a, b) =>
                (PD_URGENCY_ORDER[a.urgency] ?? 3) -
                (PD_URGENCY_ORDER[b.urgency] ?? 3),
        );
    } else if (filter === 'Rush') {
        rows = pdJoData
            .filter((j) => j.urgency === 'Rush')
            .sort(
                (a, b) =>
                    (PD_URGENCY_ORDER[a.urgency] ?? 3) -
                    (PD_URGENCY_ORDER[b.urgency] ?? 3),
            );
    } else {
        rows = pdJoData.filter((j) => j.status === filter);
    }
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:24px;color:var(--outline);">No JOs found.</td></tr>`;
        pdUpdateStats();
        return;
    }
    tbody.innerHTML = rows
        .map((j) => {
            // MINOR 3: Row-level urgency tint so staff can scan at a glance
            const urgencyRowBg =
                j.urgency === 'Rush'
                    ? 'background:rgba(254,243,199,0.5);'
                    : j.isStoreError
                      ? 'background:rgba(254,226,226,0.3);'
                      : '';
            const urgencyRowHover =
                j.urgency === 'Rush'
                    ? 'rgba(254,243,199,0.75)'
                    : 'var(--surface-container-low)';
            return `
    <tr style="border-bottom:1px solid var(--outline-variant);cursor:pointer;transition:background 0.1s;${urgencyRowBg}" onmouseover="this.style.background='${urgencyRowHover}'" onmouseout="this.style.background='${urgencyRowBg.replace(/background:/, '').replace(';', '')}'" onclick="pdOpenJo('${j.jo}')">
      <td style="padding:12px 16px;">${pdUrgencyBadge(j.urgency)}</td>
      <td style="padding:12px 16px;font-family:var(--font-mono);font-size:12px;color:var(--primary);font-weight:600;">${j.jo}</td>
      <td style="padding:12px 16px;font-weight:600;font-size:14px;">${j.client}</td>
      <td style="padding:12px 16px;font-size:13px;color:var(--on-surface-variant);max-width:200px;">${j.product}</td>
      <td style="padding:12px 16px;">${pdJoTagBadge(j)}</td>
      <td style="padding:12px 16px;">${pdStatusBadge(j.status)}</td>
      <td style="padding:12px 16px;">${pdPaymentBadge(j)}</td>
      <td style="padding:12px 16px;font-family:var(--font-mono);font-size:12px;color:var(--on-surface);">${j.deadline}</td>
      <td style="padding:12px 16px;text-align:right;"><button class="btn btn-sm btn-secondary" onclick="event.stopPropagation();pdOpenJo('${j.jo}')">View JO</button></td>
    </tr>`;
        })
        .join('');
    pdUpdateStats();
}

function pdUpdateStats() {
    const counts = {
        'For Production': 0,
        Printing: 0,
        'Quality Check': 0,
        'Ready for Pickup': 0,
    };
    pdJoData.forEach((j) => {
        if (counts[j.status] !== undefined) counts[j.status]++;
    });
    const s = $('pd-stat-queue');
    if (s) s.textContent = counts['For Production'];
    const p = $('pd-stat-printing');
    if (p) p.textContent = counts['Printing'];
    const q = $('pd-stat-qc');
    if (q) q.textContent = counts['Quality Check'];
    const r = $('pd-stat-ready');
    if (r) r.textContent = counts['Ready for Pickup'];

    // Update rush alert
    const rushJos = pdJoData.filter((j) => j.urgency === 'Rush');
    const alertEl = $('pd-rush-alert');
    const alertTitle = $('pd-rush-alert-title');
    const alertBody = $('pd-rush-alert-body');
    if (alertEl) {
        if (rushJos.length === 0) {
            alertEl.style.display = 'none';
        } else {
            alertEl.style.display = 'flex';
            if (alertTitle)
                alertTitle.textContent = `${rushJos.length} Rush Order${rushJos.length > 1 ? 's' : ''} in Queue`;
            if (alertBody) {
                const names = rushJos
                    .slice(0, 2)
                    .map((j) => `${j.jo} (${j.deadline})`)
                    .join(', ');
                alertBody.textContent = `${names}${rushJos.length > 2 ? ` and ${rushJos.length - 2} more.` : ''}. Prioritize immediately.`;
            }
        }
    }
}

PdRouter.register('prod-queue', () => {
    pdRenderQueue('All');
    // Filter tab clicks
    $$('[data-pdfilter]').forEach((btn) => {
        btn.onclick = () => {
            $$('[data-pdfilter]').forEach((b) => {
                b.classList.remove('active');
                b.style.background = 'var(--surface-container-lowest)';
                b.style.color = 'var(--on-surface)';
            });
            btn.classList.add('active');
            btn.style.background = 'var(--primary)';
            btn.style.color = '#fff';
            pdRenderQueue(btn.dataset.pdfilter);
        };
    });
});

// ── Job Order Detail Page ─────────────────────────────────────────
let _pdCountdownInterval = null;

function pdOpenJo(joNum) {
    const jo = pdJoData.find((j) => j.jo === joNum);
    if (!jo) return;
    pdActiveJo = jo;
    PdRouter.go('prod-active');

    const el = (id) => $(id);
    if (el('pd-jo-number')) el('pd-jo-number').textContent = jo.jo;
    if (el('pd-jo-client')) el('pd-jo-client').textContent = jo.client;
    if (el('pd-jo-product')) el('pd-jo-product').textContent = jo.product;
    if (el('pd-jo-deadline')) el('pd-jo-deadline').textContent = jo.deadline;
    // MAJOR 5: Show full JO type tag badge in detail view
    if (el('pd-jo-type')) el('pd-jo-type').innerHTML = pdJoTagBadge(jo);
    // MAJOR 4: Set reassign dropdown to current assigned staff
    if (el('pd-jo-assigned')) {
        const sel = el('pd-jo-assigned');
        // Ensure the current assigned value exists in the dropdown
        let found = false;
        for (let i = 0; i < sel.options.length; i++) {
            if (sel.options[i].value === jo.assigned) {
                found = true;
                break;
            }
        }
        if (!found && jo.assigned) {
            const opt = document.createElement('option');
            opt.value = jo.assigned;
            opt.textContent = jo.assigned;
            sel.insertBefore(opt, sel.options[1]);
        }
        sel.value = jo.assigned || 'Unassigned';
    }
    const rc = el('pd-reassign-confirm');
    if (rc) rc.classList.add('hidden');
    if (el('pd-jo-payment')) el('pd-jo-payment').innerHTML = pdPaymentBadge(jo);
    if (el('pd-status-select')) el('pd-status-select').value = jo.status;
    if (el('pd-jo-status-badge'))
        el('pd-jo-status-badge').innerHTML = pdStatusBadge(jo.status);

    // CRITICAL 5: Design File Section
    const fileSection = el('pd-jo-file-section');
    if (fileSection) {
        if (jo.file) {
            const fmtIcon = {
                PDF: 'picture_as_pdf',
                AI: 'brush',
                PSD: 'layers',
                CDR: 'draw',
                PNG: 'image',
                JPG: 'image',
            };
            const icon = fmtIcon[jo.file.format] || 'attach_file';
            fileSection.innerHTML = `
        <div style="display:flex;align-items:center;gap:var(--space-4);">
          <div style="width:44px;height:44px;border-radius:var(--radius);background:var(--primary-fixed);color:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <span class="material-symbols-outlined" style="font-size:22px;">${icon}</span>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="font-weight:700;font-size:13px;color:var(--on-surface);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${jo.file.name}</div>
            <div style="margin-top:3px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
              <span style="font-family:var(--font-mono);font-size:10px;font-weight:700;padding:2px 7px;background:var(--surface-container);border:1px solid var(--outline-variant);border-radius:var(--radius-sm);color:var(--primary);">${jo.file.format}</span>
              <span style="font-size:11px;color:var(--on-surface-variant);">${jo.file.size}</span>
              <span style="font-size:11px;color:var(--on-surface-variant);">${jo.file.label}</span>
            </div>
          </div>
          <div style="display:flex;gap:8px;flex-shrink:0;">
            <button class="btn btn-sm btn-outline" title="Preview file" onclick="pdPreviewFile('${jo.jo}')">
              <span class="material-symbols-outlined" style="font-size:15px;">visibility</span> Preview
            </button>
            <button class="btn btn-sm btn-primary" title="Download file" onclick="pdDownloadFile('${jo.jo}')">
              <span class="material-symbols-outlined" style="font-size:15px;">download</span> Download
            </button>
          </div>
        </div>`;
        } else {
            fileSection.innerHTML = `<div style="text-align:center;padding:16px;color:var(--on-surface-variant);font-size:13px;"><span class="material-symbols-outlined" style="display:block;font-size:28px;opacity:0.3;margin-bottom:6px;">attach_file</span>No files attached to this JO.</div>`;
        }
    }

    // CRITICAL 6: Deadline countdown
    if (_pdCountdownInterval) clearInterval(_pdCountdownInterval);
    const countdownEl = el('pd-jo-deadline-countdown');
    if (countdownEl && jo.deadlineDate) {
        countdownEl.style.display = 'block';
        function updateCountdown() {
            const now = new Date();
            const diff = jo.deadlineDate - now;
            if (diff <= 0) {
                countdownEl.textContent = '⚠ DEADLINE PASSED';
                countdownEl.style.color = '#dc2626';
                countdownEl.style.background = '#fef2f2';
                countdownEl.style.padding = '3px 8px';
                countdownEl.style.borderRadius = 'var(--radius-sm)';
                return;
            }
            const hrs = Math.floor(diff / 3600000);
            const mins = Math.floor((diff % 3600000) / 60000);
            const secs = Math.floor((diff % 60000) / 1000);
            let color, bg;
            if (diff < 30 * 60 * 1000) {
                color = '#dc2626';
                bg = '#fef2f2';
            } // < 30 min — red
            else if (diff < 2 * 3600 * 1000) {
                color = '#d97706';
                bg = '#fef3c7';
            } // < 2 hrs — amber
            else {
                color = '#166534';
                bg = '#dcfce7';
            } // > 2 hrs — green
            countdownEl.style.color = color;
            countdownEl.style.background = bg;
            countdownEl.style.padding = '3px 8px';
            countdownEl.style.borderRadius = 'var(--radius-sm)';
            countdownEl.style.display = 'inline-block';
            countdownEl.textContent =
                hrs > 0
                    ? `⏱ ${hrs}h ${mins}m ${secs}s remaining`
                    : mins > 0
                      ? `⏱ ${mins}m ${secs}s remaining`
                      : `⏱ ${secs}s remaining`;
        }
        updateCountdown();
        _pdCountdownInterval = setInterval(updateCountdown, 1000);
    } else if (countdownEl) {
        countdownEl.style.display = 'none';
    }

    // MINOR 1: Render persistent activity log from jo.activityLog (survives navigation)
    if (el('pd-activity-log')) {
        // Seed initial log entry if this JO has never been opened before
        if (!jo.activityLog) {
            jo.activityLog = [
                {
                    color: 'var(--primary)',
                    title: `Status: <em>${jo.status}</em>`,
                    meta: `JO Created & payment verified · Assigned to ${jo.assigned}`,
                },
            ];
        }
        el('pd-activity-log').innerHTML = jo.activityLog
            .map(
                (entry) => `
      <div style="display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--outline-variant);">
        <div style="width:8px;height:8px;border-radius:50%;background:${entry.color};margin-top:5px;flex-shrink:0;"></div>
        <div>
          <div style="font-size:13px;font-weight:600;">${entry.title}</div>
          <div style="font-family:var(--font-mono);font-size:11px;color:var(--on-surface-variant);margin-top:2px;">${entry.meta}</div>
        </div>
      </div>`,
            )
            .join('');
    }
}

function pdPreviewFile(joNum) {
    const jo = pdJoData.find((j) => j.jo === joNum);
    if (!jo || !jo.file) return;
    // Simulate preview — in production, this would open the actual file
    const modal = document.createElement('div');
    modal.style.cssText =
        'position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;display:flex;align-items:center;justify-content:center;';
    modal.innerHTML = `
    <div style="background:var(--surface);border-radius:var(--radius-lg);padding:var(--space-6);max-width:480px;width:90%;box-shadow:var(--shadow-lg);">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--space-4);">
        <div style="font-weight:700;font-size:15px;">File Preview</div>
        <button onclick="this.closest('[style*=fixed]').remove()" style="background:none;border:none;cursor:pointer;color:var(--on-surface-variant);">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      <div style="background:var(--surface-container-low);border-radius:var(--radius-md);padding:32px;text-align:center;margin-bottom:var(--space-4);">
        <span class="material-symbols-outlined" style="font-size:48px;color:var(--primary);display:block;margin-bottom:8px;">picture_as_pdf</span>
        <div style="font-weight:600;font-size:14px;">${jo.file.name}</div>
        <div style="font-size:12px;color:var(--on-surface-variant);margin-top:4px;">${jo.file.format} · ${jo.file.size}</div>
        <div style="font-size:12px;color:var(--on-surface-variant);margin-top:8px;font-style:italic;">File preview is available in the production system.<br>This is a prototype — actual file would render here.</div>
      </div>
      <button class="btn btn-primary" style="width:100%;" onclick="this.closest('[style*=fixed]').remove()">Close Preview</button>
    </div>`;
    document.body.appendChild(modal);
}

function pdDownloadFile(joNum) {
    const jo = pdJoData.find((j) => j.jo === joNum);
    if (!jo || !jo.file) return;
    // Simulate download notification
    const toast = document.createElement('div');
    toast.style.cssText =
        'position:fixed;bottom:24px;right:24px;background:#166534;color:white;padding:12px 20px;border-radius:var(--radius-md);font-size:13px;font-weight:600;z-index:9999;display:flex;align-items:center;gap:8px;box-shadow:var(--shadow-lg);animation:fadeIn 0.2s ease;';
    toast.innerHTML = `<span class="material-symbols-outlined" style="font-size:18px;">download</span> Downloading ${jo.file.name}…`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// MAJOR 4: Reassign staff on a JO
function pdReassignStaff(newStaff) {
    if (!pdActiveJo) return;
    const oldStaff = pdActiveJo.assigned;
    if (oldStaff === newStaff) return;
    pdActiveJo.assigned = newStaff;
    // Update in source data
    const jo = pdJoData.find((j) => j.jo === pdActiveJo.jo);
    if (jo) jo.assigned = newStaff;

    // Show confirmation
    const rc = $('pd-reassign-confirm');
    if (rc) {
        rc.textContent = `✓ Reassigned from ${oldStaff} to ${newStaff}`;
        rc.classList.remove('hidden');
        setTimeout(() => rc.classList.add('hidden'), 3000);
    }

    // MINOR 1: Persist reassign event in activity log
    const ts = new Date().toLocaleTimeString('en-PH', {
        hour: '2-digit',
        minute: '2-digit',
    });
    const reassignEntry = {
        color: '#f59e0b',
        title: `Reassigned: <em>${oldStaff}</em> → <em>${newStaff}</em>`,
        meta: `${ts} · PD001`,
    };
    if (!pdActiveJo.activityLog) pdActiveJo.activityLog = [];
    pdActiveJo.activityLog.unshift(reassignEntry);
    if (jo && jo.activityLog) jo.activityLog = pdActiveJo.activityLog;

    // Re-render log DOM
    const logEl = $('pd-activity-log');
    if (logEl) {
        logEl.innerHTML = pdActiveJo.activityLog
            .map(
                (entry) => `
      <div style="display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--outline-variant);">
        <div style="width:8px;height:8px;border-radius:50%;background:${entry.color};margin-top:5px;flex-shrink:0;"></div>
        <div>
          <div style="font-size:13px;font-weight:600;">${entry.title}</div>
          <div style="font-family:var(--font-mono);font-size:11px;color:var(--on-surface-variant);margin-top:2px;">${entry.meta}</div>
        </div>
      </div>`,
            )
            .join('');
    }
}

PdRouter.register('prod-active', () => {
    var listView = document.getElementById('pd-active-list-view');
    var detailView = document.getElementById('pd-active-detail-view');
    if (pdActiveJo) {
        if (listView) listView.classList.add('hidden');
        if (detailView) detailView.classList.remove('hidden');
    } else {
        if (detailView) detailView.classList.add('hidden');
        if (listView) listView.classList.remove('hidden');
        pdRenderActiveList();
    }
});

function pdRenderActiveList() {
    var container = document.getElementById('pd-active-list-view');
    if (!container) return;
    // MAJOR 2: Show ALL non-released jobs — including Ready for Pickup (awaiting FL release)
    var active = pdJoData.filter(function (j) {
        return j.status !== 'Released';
    });
    if (!active.length) {
        container.innerHTML =
            '<div class="card"><div class="card-body" style="text-align:center;padding:32px;color:var(--outline);">No active jobs at the moment.</div></div>';
        return;
    }
    var thS =
        'padding:10px 16px;text-align:left;font-family:var(--font-mono);font-size:10px;text-transform:uppercase;letter-spacing:0.06em;color:var(--on-surface-variant);font-weight:600;';
    var rows = active
        .map(function (j) {
            var jo = j.jo;
            var isReady = j.status === 'Ready for Pickup';
            var rowBg = isReady ? 'background:rgba(224,242,254,0.35);' : '';
            var row =
                '<tr style="border-bottom:1px solid var(--outline-variant);cursor:pointer;transition:background 0.1s;' +
                rowBg +
                '"';
            row +=
                ' onmouseover="this.style.background=\'var(--surface-container-low)\'"';
            row +=
                ' onmouseout="this.style.background=\'' +
                (isReady ? 'rgba(224,242,254,0.35)' : '') +
                '\'"';
            row += ' onclick="pdOpenJo(\'' + jo + '\')">';
            row +=
                '<td style="padding:12px 16px;">' +
                pdUrgencyBadge(j.urgency) +
                '</td>';
            row +=
                '<td style="padding:12px 16px;font-family:var(--font-mono);font-size:12px;color:var(--primary);font-weight:600;">' +
                jo +
                '</td>';
            row +=
                '<td style="padding:12px 16px;font-weight:600;font-size:14px;">' +
                j.client +
                '</td>';
            row +=
                '<td style="padding:12px 16px;font-size:13px;color:var(--on-surface-variant);max-width:180px;">' +
                j.product +
                '</td>';
            row +=
                '<td style="padding:12px 16px;">' + pdJoTagBadge(j) + '</td>';
            row +=
                '<td style="padding:12px 16px;">' +
                pdStatusBadge(j.status) +
                '</td>';
            row +=
                '<td style="padding:12px 16px;">' + pdPaymentBadge(j) + '</td>';
            row +=
                '<td style="padding:12px 16px;font-family:var(--font-mono);font-size:12px;">' +
                j.deadline +
                '</td>';
            row +=
                '<td style="padding:12px 16px;font-size:13px;color:var(--on-surface-variant);">' +
                j.assigned +
                '</td>';
            var btnLabel = isReady ? 'View' : 'Update';
            var btnStyle = isReady
                ? 'class="btn btn-sm" style="background:var(--primary-fixed);color:var(--primary);border:1px solid var(--primary);"'
                : 'class="btn btn-sm btn-secondary"';
            row +=
                '<td style="padding:12px 16px;text-align:right;"><button ' +
                btnStyle +
                ' onclick="event.stopPropagation();pdOpenJo(\'' +
                jo +
                '\')">' +
                btnLabel +
                '</button></td>';
            row += '</tr>';
            return row;
        })
        .join('');
    var thRow =
        '<tr style="background:var(--surface-container-low);border-bottom:1px solid var(--outline-variant);">' +
        '<th style="' +
        thS +
        '">Priority</th>' +
        '<th style="' +
        thS +
        '">JO Number</th>' +
        '<th style="' +
        thS +
        '">Client</th>' +
        '<th style="' +
        thS +
        '">Product / Specs</th>' +
        '<th style="' +
        thS +
        '">JO Type</th>' +
        '<th style="' +
        thS +
        '">Status</th>' +
        '<th style="' +
        thS +
        '">Payment</th>' +
        '<th style="' +
        thS +
        '">Deadline</th>' +
        '<th style="' +
        thS +
        '">Assigned</th>' +
        '<th></th></tr>';
    container.innerHTML =
        '<div class="card">' +
        '<div class="card-header">' +
        '<div class="card-header-icon" style="background:#dcfce7;color:#166534;"><span class="material-symbols-outlined">precision_manufacturing</span></div>' +
        '<div><div class="card-header-title">Jobs In Progress</div><div class="card-header-sub">Click a JO to update its status · Ready for Pickup = awaiting Frontline release</div></div>' +
        '</div><div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;">' +
        '<thead>' +
        thRow +
        '</thead>' +
        '<tbody>' +
        rows +
        '</tbody>' +
        '</table></div></div>';
}

function pdUpdateStatus() {
    if (!pdActiveJo) return;
    const select = $('pd-status-select');
    if (!select) return;
    const newStatus = select.value;
    const prevStatus = pdActiveJo.status;
    if (prevStatus === newStatus) return;
    const jo = pdJoData.find((j) => j.jo === pdActiveJo.jo);
    if (jo) jo.status = newStatus;
    pdActiveJo.status = newStatus;

    const badge = $('pd-jo-status-badge');
    if (badge) badge.innerHTML = pdStatusBadge(newStatus);

    const timestamp = new Date().toLocaleTimeString('en-PH', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
    });

    // MINOR 1: Persist log entry on the JO object so it survives navigation
    const logEntry = {
        color: 'var(--primary)',
        title: `Status updated: <em>${prevStatus}</em> → <em>${newStatus}</em>`,
        meta: `${timestamp} · PD001 · ${pdActiveJo.assigned}`,
    };
    if (!pdActiveJo.activityLog) pdActiveJo.activityLog = [];
    pdActiveJo.activityLog.unshift(logEntry);
    if (jo && !jo.activityLog) jo.activityLog = pdActiveJo.activityLog;

    // Re-render the log DOM from the persisted array
    const log = $('pd-activity-log');
    if (log) {
        log.innerHTML = pdActiveJo.activityLog
            .map(
                (entry) => `
      <div style="display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--outline-variant);animation:fadeIn 0.3s ease;">
        <div style="width:8px;height:8px;border-radius:50%;background:${entry.color};margin-top:5px;flex-shrink:0;"></div>
        <div>
          <div style="font-size:13px;font-weight:600;">${entry.title}</div>
          <div style="font-family:var(--font-mono);font-size:11px;color:var(--on-surface-variant);margin-top:2px;">${entry.meta}</div>
        </div>
      </div>`,
            )
            .join('');
    }

    pdUpdateStats();

    // MAJOR 2: If marking "Ready for Pickup" — show explicit handoff confirmation + push FL notification
    if (newStatus === 'Ready for Pickup') {
        // MINOR 4: Push to Completed Orders history with staff + timestamp for the record
        const fullTimestamp = new Date().toLocaleString('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        });
        // Only push if not already in history for this JO
        const alreadyLogged = pdHistory.some((h) => h.jo === pdActiveJo.jo);
        if (!alreadyLogged) {
            pdHistory.unshift({
                ...pdActiveJo,
                released: fullTimestamp,
                releasedDate: new Date(),
                staff: 'PD001',
                historyNote: 'Marked Ready for Pickup',
            });
        }

        const banner = $('pd-update-success');
        if (banner) {
            banner.innerHTML = `
        <span class="material-symbols-outlined" style="font-size:18px;flex-shrink:0;">notifications_active</span>
        <div>
          <div style="font-weight:700;">JO marked as Ready for Pickup — ${timestamp} · PD001</div>
          <div style="font-size:12px;font-weight:400;margin-top:2px;opacity:0.9;">Frontline notification sent automatically. Frontline Staff will see this in their notification bell and process release from the Order Release module.</div>
        </div>`;
            banner.style.cssText =
                'background:var(--primary);color:white;border-radius:var(--radius);padding:14px 16px;font-size:14px;display:flex;align-items:flex-start;gap:12px;margin-bottom:16px;';
            banner.classList.remove('hidden');
            // Don't auto-hide — this is a handoff record, leave it visible
        }
        // Push FL notification
        if (typeof flPushNotification === 'function') {
            flPushNotification(
                pdActiveJo.jo,
                pdActiveJo.client,
                pdActiveJo.product,
            );
        }
    } else {
        const banner = $('pd-update-success');
        if (banner) {
            banner.innerHTML = `<span class="material-symbols-outlined" style="font-size:16px;">check_circle</span> Status updated to "${newStatus}" — ${timestamp} · PD001`;
            banner.style.cssText =
                'background:var(--success-container);color:var(--success);border:1px solid #86efac;border-radius:var(--radius);padding:12px 16px;font-size:14px;font-weight:600;display:flex;align-items:center;gap:8px;margin-bottom:16px;';
            banner.classList.remove('hidden');
            setTimeout(() => banner.classList.add('hidden'), 3500);
        }
    }
}

// ── FL Order Release ───────────────────────────────────────────────
function flFilterRelease(filter) {
    var tbody = document.getElementById('fl-release-tbody');
    if (!tbody) return;
    var f = filter ? filter.toLowerCase() : '';
    var list = pdJoData.filter(function (j) {
        return (
            j.status === 'Ready for Pickup' &&
            (!f ||
                j.jo.toLowerCase().indexOf(f) !== -1 ||
                j.client.toLowerCase().indexOf(f) !== -1)
        );
    });
    if (!list.length) {
        tbody.innerHTML =
            '<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--outline);">No JOs ready for pickup.</td></tr>';
        return;
    }
    tbody.innerHTML = list
        .map(function (j) {
            var paidStyle = j.paid
                ? 'background:#dcfce7;color:#166534;'
                : 'background:var(--error-container);color:var(--error);';
            var paidText = j.paid ? '✓ Fully Paid' : '⚠ Pending';
            var disabledAttr = j.paid
                ? ''
                : 'disabled style="opacity:0.5;cursor:not-allowed;"';
            var balance = j.paid ? '₱0.00' : 'Balance due';
            var row =
                '<tr style="border-bottom:1px solid var(--outline-variant);">';
            row +=
                '<td style="padding:12px 16px;font-family:var(--font-mono);font-size:12px;color:var(--primary);font-weight:600;">' +
                j.jo +
                '</td>';
            row +=
                '<td style="padding:12px 16px;font-weight:600;">' +
                j.client +
                '</td>';
            row +=
                '<td style="padding:12px 16px;font-size:13px;color:var(--on-surface-variant);">' +
                j.product +
                '</td>';
            row +=
                '<td style="padding:12px 16px;"><span style="font-family:var(--font-mono);font-size:11px;font-weight:600;padding:3px 10px;border-radius:var(--radius-full);' +
                paidStyle +
                '">' +
                paidText +
                '</span></td>';
            row +=
                '<td style="padding:12px 16px;font-family:var(--font-mono);font-size:12px;">' +
                balance +
                '</td>';
            row +=
                '<td style="padding:12px 16px;font-family:var(--font-mono);font-size:12px;color:var(--on-surface-variant);">' +
                j.deadline +
                '</td>';
            row +=
                '<td style="padding:12px 16px;text-align:right;"><button class="btn btn-sm btn-primary" ' +
                disabledAttr +
                ' onclick="flConfirmReleaseById(\'' +
                j.jo +
                '\')">Release</button></td>';
            row += '</tr>';
            return row;
        })
        .join('');
}

function flConfirmReleaseById(joNum) {
    var jo = pdJoData.find(function (j) {
        return j.jo === joNum;
    });
    if (!jo || !jo.paid) return;
    jo.status = 'Released';
    pdHistory.unshift({
        ...jo,
        released: new Date().toLocaleString('en-PH'),
        releasedDate: new Date(),
        staff: 'FL001',
    });
    alert(
        '✓ JO ' +
            joNum +
            ' has been successfully released for pickup by ' +
            jo.client +
            '.',
    );
    setTimeout(function () {
        flFilterRelease('');
    }, 100);
}

FlRouter.register('order-release', () => {
    setTimeout(function () {
        flFilterRelease('');
    }, 0);
});

// ── Order Release Page ─────────────────────────────────────────────

// ── Completed Orders Page ─────────────────────────────────────────
// Seed historical records so the page isn't empty on first load (MAJOR 3)
const pdHistorySeeds = [
    {
        jo: 'JO-2026-0985',
        client: 'Aquino, Rosa M.',
        product: 'Flyers 500pcs · A5 · Gloss',
        type: 'A',
        joTag: 'New Order — Type A',
        released: 'May 24, 2026, 4:12 PM',
        releasedDate: new Date('2026-05-24'),
        staff: 'FL001',
    },
    {
        jo: 'JO-2026-0979',
        client: 'Bautista, Carlo J.',
        product: 'Tarpaulin 4×8ft · Vinyl Gloss',
        type: 'A',
        joTag: 'Reprint — Client Request',
        released: 'May 24, 2026, 2:30 PM',
        releasedDate: new Date('2026-05-24'),
        staff: 'FL001',
    },
    {
        jo: 'JO-2026-0971',
        client: 'Ocampo, Dana L.',
        product: 'ID Cards 10pcs · PVC',
        type: 'B',
        joTag: 'New Order — Type B',
        released: 'May 23, 2026, 5:45 PM',
        releasedDate: new Date('2026-05-23'),
        staff: 'FL002',
    },
    {
        jo: 'JO-2026-0965',
        client: 'Torres, Mark A.',
        product: 'Business Cards 200pcs · Matte',
        type: 'B',
        joTag: 'Revision Order',
        released: 'May 23, 2026, 3:10 PM',
        releasedDate: new Date('2026-05-23'),
        staff: 'FL001',
    },
    {
        jo: 'JO-2026-0958',
        client: 'Navarro, Cel P.',
        product: 'Sintra Board Sign 3×5ft',
        type: 'B',
        joTag: 'Reprint — Store Error',
        released: 'May 22, 2026, 11:20 AM',
        releasedDate: new Date('2026-05-22'),
        staff: 'FL002',
    },
    {
        jo: 'JO-2026-0950',
        client: 'Fuentes, Al G.',
        product: 'Stickers 100pcs · Die-cut',
        type: 'A',
        joTag: 'New Order — Type A',
        released: 'May 22, 2026, 9:00 AM',
        releasedDate: new Date('2026-05-22'),
        staff: 'FL001',
    },
];

function pdAllHistory() {
    return [...pdHistory, ...pdHistorySeeds];
}

function pdRenderHistory() {
    const tbody = $('pd-history-tbody');
    if (!tbody) return;

    const search = ($('pd-history-search') || {}).value || '';
    const fromVal = ($('pd-history-from') || {}).value || '';
    const toVal = ($('pd-history-to') || {}).value || '';
    const fromDate = fromVal ? new Date(fromVal) : null;
    const toDate = toVal ? new Date(toVal + 'T23:59:59') : null;

    const sq = search.toLowerCase();
    let list = pdAllHistory().filter((r) => {
        if (
            sq &&
            !r.jo.toLowerCase().includes(sq) &&
            !r.client.toLowerCase().includes(sq)
        )
            return false;
        const rd = r.releasedDate || (r.released ? new Date(r.released) : null);
        if (fromDate && rd && rd < fromDate) return false;
        if (toDate && rd && rd > toDate) return false;
        return true;
    });

    const sub = $('pd-history-result-sub');
    if (sub)
        sub.textContent = `Showing ${list.length} record${list.length !== 1 ? 's' : ''}`;

    if (!list.length) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--outline);">No records match the current filter. Adjust the search or date range.</td></tr>`;
        return;
    }
    tbody.innerHTML = list
        .map(
            (r) => `
    <tr style="border-bottom:1px solid var(--outline-variant);">
      <td style="padding:12px 16px;font-family:var(--font-mono);font-size:12px;color:var(--primary);font-weight:600;">${r.jo}</td>
      <td style="padding:12px 16px;font-weight:600;">${r.client}</td>
      <td style="padding:12px 16px;font-size:13px;color:var(--on-surface-variant);">${r.product}</td>
      <td style="padding:12px 16px;">${pdJoTagBadge(r)}</td>
      <td style="padding:12px 16px;font-family:var(--font-mono);font-size:12px;">${r.released}</td>
      <td style="padding:12px 16px;font-family:var(--font-mono);font-size:11px;color:var(--on-surface-variant);">${r.staff}</td>
      <td style="padding:12px 16px;text-align:right;">
        <button class="btn btn-sm" style="background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;" onclick="pdGoToComplaintWithJo('${r.jo}')">
          <span class="material-symbols-outlined" style="font-size:13px;vertical-align:-2px;">report_problem</span> Report Issue
        </button>
      </td>
    </tr>`,
        )
        .join('');
}

function pdHistoryClearFilters() {
    const s = $('pd-history-search');
    if (s) s.value = '';
    const f = $('pd-history-from');
    if (f) f.value = '';
    const t = $('pd-history-to');
    if (t) t.value = '';
    pdRenderHistory();
}

PdRouter.register('prod-history', () => {
    // Set today's date as default "from" filter if not yet set
    const fromEl = $('pd-history-from');
    // Don't auto-set — let staff choose; just render everything by default
    pdRenderHistory();
    const dateEl = $('pd-history-date');
    if (dateEl)
        dateEl.textContent = new Date().toLocaleDateString('en-PH', {
            weekday: 'short',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
});

// ── CRITICAL 1: Quality Complaints ────────────────────────────────
const pdComplaints = [];
let pdComplaintJoFromHistory = null;

function pdGoToComplaintWithJo(joNum) {
    pdComplaintJoFromHistory = joNum;
    PdRouter.go('prod-complaints');
}

PdRouter.register('prod-complaints', () => {
    const joInput = $('pd-cmp-jo');
    if (joInput && pdComplaintJoFromHistory) {
        joInput.value = pdComplaintJoFromHistory;
        pdComplaintJoFromHistory = null;
        pdCmpLookupClient();
    }
    pdRenderComplaintLog();
});

function pdCmpLookupClient() {
    const joNum = ($('pd-cmp-jo') || {}).value || '';
    const display = $('pd-cmp-client-display');
    if (!display) return;
    const jo =
        pdJoData.find((j) => j.jo === joNum.trim()) ||
        pdHistory.find((j) => j.jo === joNum.trim());
    if (jo) {
        display.textContent = jo.client;
        display.style.color = 'var(--on-surface)';
        display.style.fontWeight = '600';
    } else {
        display.textContent = joNum ? 'JO not found' : '—';
        display.style.color = joNum
            ? 'var(--error)'
            : 'var(--on-surface-variant)';
        display.style.fontWeight = '400';
    }
}

// Hook lookup to JO input change
document.addEventListener('DOMContentLoaded', () => {
    const joInput = $('pd-cmp-jo');
    if (joInput) joInput.addEventListener('input', pdCmpLookupClient);
});

function pdClearComplaintForm() {
    const fields = ['pd-cmp-jo', 'pd-cmp-description'];
    fields.forEach((id) => {
        const el = $(id);
        if (el) el.value = '';
    });
    const sel = ['pd-cmp-issue-type', 'pd-cmp-discovered-by'];
    sel.forEach((id) => {
        const el = $(id);
        if (el) el.selectedIndex = 0;
    });
    const disp = $('pd-cmp-client-display');
    if (disp) {
        disp.textContent = '—';
        disp.style.color = 'var(--on-surface-variant)';
        disp.style.fontWeight = '400';
    }
    const err = $('pd-cmp-error');
    if (err) err.classList.add('hidden');
}

function pdSubmitComplaint() {
    const joNum = ($('pd-cmp-jo') || {}).value?.trim();
    const issueType = ($('pd-cmp-issue-type') || {}).value;
    const description = ($('pd-cmp-description') || {}).value?.trim();
    const discoveredBy = ($('pd-cmp-discovered-by') || {}).value;
    const errEl = $('pd-cmp-error');

    if (!joNum || !issueType || !description) {
        if (errEl) {
            errEl.textContent =
                'Please complete all required fields (JO Number, Issue Type, Description).';
            errEl.classList.remove('hidden');
        }
        return;
    }
    const jo =
        pdJoData.find((j) => j.jo === joNum) ||
        pdHistory.find((j) => j.jo === joNum);
    if (!jo) {
        if (errEl) {
            errEl.textContent = `JO Number "${joNum}" not found in the system. Please verify the JO number.`;
            errEl.classList.remove('hidden');
        }
        return;
    }
    if (errEl) errEl.classList.add('hidden');

    // Generate reprint JO number
    const reprintJoNum =
        'JO-' +
        new Date().getFullYear() +
        '-REPRINT-' +
        Math.floor(1000 + Math.random() * 9000);

    const complaint = {
        id: Date.now(),
        joNum,
        client: jo.client,
        product: jo.product,
        issueType,
        description,
        discoveredBy,
        loggedBy: 'PD001',
        time: new Date().toLocaleString('en-PH', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        }),
        reprintJo: reprintJoNum,
        status: 'Pending Owner/Admin Approval',
    };
    pdComplaints.unshift(complaint);

    // Add a reprint JO to queue (flagged as Store Error, free)
    pdJoData.unshift({
        jo: reprintJoNum,
        client: jo.client,
        product: jo.product + ' [REPRINT — Store Error]',
        type: jo.type || 'A',
        deadline: 'ASAP — Store Error',
        deadlineDate: new Date(Date.now() + 2 * 3600 * 1000),
        urgency: 'Rush',
        status: 'For Production',
        assigned: 'Unassigned',
        paid: true,
        file: jo.file || null,
        isStoreError: true,
    });

    pdClearComplaintForm();
    pdRenderComplaintLog();

    // Success toast
    const toast = document.createElement('div');
    toast.style.cssText =
        'position:fixed;bottom:24px;right:24px;background:#166534;color:white;padding:14px 20px;border-radius:var(--radius-md);font-size:13px;font-weight:600;z-index:9999;max-width:380px;box-shadow:var(--shadow-lg);animation:fadeIn 0.2s ease;';
    toast.innerHTML = `<div style="display:flex;align-items:flex-start;gap:10px;"><span class="material-symbols-outlined" style="font-size:18px;flex-shrink:0;margin-top:1px;">check_circle</span><div><div>Quality Complaint logged successfully.</div><div style="font-size:11px;font-weight:400;margin-top:3px;opacity:0.85;">Reprint JO ${reprintJoNum} added to Production Queue. Pending Owner/Admin approval.</div></div></div>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4500);
}

function pdRenderComplaintLog() {
    const empty = $('pd-complaint-log-empty');
    const table = $('pd-complaint-log-table');
    const tbody = $('pd-complaint-tbody');
    if (!tbody) return;
    if (!pdComplaints.length) {
        if (empty) empty.style.display = 'block';
        if (table) table.classList.add('hidden');
        return;
    }
    if (empty) empty.style.display = 'none';
    if (table) table.classList.remove('hidden');
    tbody.innerHTML = pdComplaints
        .map((c) => {
            const statusColor = c.status.includes('Approved')
                ? '#166534'
                : c.status.includes('Pending')
                  ? '#92400e'
                  : '#1e40af';
            const statusBg = c.status.includes('Approved')
                ? '#dcfce7'
                : c.status.includes('Pending')
                  ? '#fef3c7'
                  : 'var(--primary-fixed)';
            return `
    <tr style="border-bottom:1px solid var(--outline-variant);">
      <td style="padding:12px 16px;font-family:var(--font-mono);font-size:12px;color:var(--primary);font-weight:600;">${c.joNum}</td>
      <td style="padding:12px 16px;font-weight:600;font-size:13px;">${c.client}</td>
      <td style="padding:12px 16px;font-size:12px;color:var(--on-surface-variant);max-width:200px;">${c.issueType}</td>
      <td style="padding:12px 16px;font-size:12px;color:var(--on-surface-variant);">${c.loggedBy} · ${c.discoveredBy.split(' ')[0]} ${c.discoveredBy.split(' ')[1] || ''}</td>
      <td style="padding:12px 16px;font-family:var(--font-mono);font-size:11px;color:var(--on-surface-variant);">${c.time}</td>
      <td style="padding:12px 16px;font-family:var(--font-mono);font-size:11px;color:var(--primary);font-weight:600;">${c.reprintJo}</td>
      <td style="padding:12px 16px;"><span style="font-family:var(--font-mono);font-size:10px;font-weight:700;padding:3px 10px;border-radius:var(--radius-full);background:${statusBg};color:${statusColor};">${c.status}</span></td>
    </tr>`;
        })
        .join('');
}

// ══════════════════════════════════════════════════════════════════
//  FRONTLINE NOTIFICATION BELL
//  Triggered by: Production marks JO as "Ready for Pickup"
//  Frontline Only — no sound, no pop-up, discreet badge only
// ══════════════════════════════════════════════════════════════════

// ── Notification State ─────────────────────────────────────────────
// Each entry: { id, jo, client, product, time, read }
let flNotifications = [];
let flNotifPanelOpen = false;

// ── Build Initial Notifications from pdJoData ──────────────────────
// Called once after login to seed existing "Ready for Pickup" orders
function flInitNotifications() {
    flNotifications = [];
    pdJoData.forEach((j) => {
        if (j.status === 'Ready for Pickup') {
            flNotifications.push({
                id: j.jo,
                jo: j.jo,
                client: j.client,
                product: j.product,
                time: j.deadline || now(),
                read: false,
            });
        }
    });
    flRenderNotifBadge();
}

// ── Add a New Notification (called when Production marks Ready) ────
function flPushNotification(jo, client, product) {
    // Avoid duplicate
    if (flNotifications.find((n) => n.id === jo)) return;
    flNotifications.unshift({
        id: jo,
        jo,
        client,
        product,
        time: now(),
        read: false,
    });
    flRenderNotifBadge();
    // If panel is open, re-render it
    if (flNotifPanelOpen) flRenderNotifList();
}

// ── Remove notification when JO is released ───────────────────────
function flRemoveNotification(jo) {
    flNotifications = flNotifications.filter((n) => n.id !== jo);
    flRenderNotifBadge();
    if (flNotifPanelOpen) flRenderNotifList();
}

// ── Update Badge Count ─────────────────────────────────────────────
function flRenderNotifBadge() {
    const badge = $('fl-notif-badge');
    const btn = $('fl-notif-btn');
    if (!badge || !btn) return;
    const unread = flNotifications.filter((n) => !n.read).length;
    if (unread > 0) {
        badge.textContent = unread > 9 ? '9+' : unread;
        badge.classList.remove('hidden');
        btn.classList.add('has-notif');
    } else {
        badge.classList.add('hidden');
        btn.classList.remove('has-notif');
    }
}

// ── Toggle Notification Panel ──────────────────────────────────────
function flToggleNotifPanel() {
    const panel = $('fl-notif-panel');
    if (!panel) return;
    flNotifPanelOpen = !flNotifPanelOpen;
    if (flNotifPanelOpen) {
        panel.classList.remove('hidden');
        flRenderNotifList();
        // Mark all as read after a short delay (UX: user sees them briefly as unread)
        setTimeout(() => {
            flNotifications.forEach((n) => (n.read = true));
            flRenderNotifBadge();
        }, 800);
    } else {
        panel.classList.add('hidden');
    }
}

// ── Close panel on outside click ──────────────────────────────────
document.addEventListener('click', (e) => {
    const wrap = $('fl-notif-wrap');
    if (wrap && !wrap.contains(e.target) && flNotifPanelOpen) {
        $('fl-notif-panel').classList.add('hidden');
        flNotifPanelOpen = false;
    }
});

// ── Render Notification List ──────────────────────────────────────
function flRenderNotifList() {
    const list = $('fl-notif-list');
    const footer = $('fl-notif-footer');
    const clearBtn = $('fl-notif-clear-btn');
    if (!list) return;

    if (!flNotifications.length) {
        list.innerHTML = `
      <div class="fl-notif-empty">
        <span class="material-symbols-outlined">notifications_off</span>
        <div class="fl-notif-empty-text">No pending notifications</div>
        <div style="font-size:11px;color:var(--outline);margin-top:4px;">Ready orders will appear here</div>
      </div>`;
        if (footer) footer.classList.add('hidden');
        if (clearBtn) clearBtn.style.display = 'none';
        return;
    }

    if (footer) footer.classList.remove('hidden');
    if (clearBtn) clearBtn.style.display = '';

    list.innerHTML = flNotifications
        .map(
            (n) => `
    <div class="fl-notif-item${n.read ? ' read' : ''}" onclick="flNotifItemClick('${n.id}')">
      <div class="fl-notif-item-icon">
        <span class="material-symbols-outlined">inventory_2</span>
      </div>
      <div class="fl-notif-item-body">
        <div class="fl-notif-item-title">Ready for Pickup — ${n.jo}</div>
        <div class="fl-notif-item-sub">${n.client} · ${n.product}</div>
        <div class="fl-notif-item-time">${n.time}</div>
      </div>
      ${!n.read ? '<div class="fl-notif-unread-dot"></div>' : ''}
    </div>
  `,
        )
        .join('');
}

// ── Click on a Notification Item ─────────────────────────────────
function flNotifItemClick(joId) {
    const n = flNotifications.find((x) => x.id === joId);
    if (n) n.read = true;
    // Close panel
    $('fl-notif-panel').classList.add('hidden');
    flNotifPanelOpen = false;
    flRenderNotifBadge();
    // Navigate to Order Release and pre-fill the search
    FlRouter.go('order-release');
    setTimeout(() => {
        const searchEl = $('fl-release-search');
        if (searchEl) {
            searchEl.value = joId;
            flFilterRelease(joId);
        }
    }, 100);
}

// ── Go to Order Release from Footer Button ───────────────────────
function flGoToOrderRelease() {
    $('fl-notif-panel').classList.add('hidden');
    flNotifPanelOpen = false;
    FlRouter.go('order-release');
}

// ── Clear All Notifications ──────────────────────────────────────
function flClearAllNotifs() {
    flNotifications = [];
    flRenderNotifBadge();
    flRenderNotifList();
}

// ── Hook: When Production marks a JO as "Ready for Pickup" ───────
// This wraps pdUpdateStatus to auto-push a notification to Frontline
const _origPdUpdateStatus =
    typeof pdUpdateStatus === 'function' ? pdUpdateStatus : null;
// We patch it after DOMContentLoaded via a global override
function flHookProductionReadyForPickup(joNum, client, product) {
    flPushNotification(joNum, client, product);
}

// ── Hook: When Frontline releases a JO ───────────────────────────
// Remove notification after successful release
const _origFlConfirmRelease = window.flConfirmReleaseById;

// ══════════════════════════════════════════════════════════════════

//  INIT
// ══════════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    initLogin();

    // Frontline nav clicks
    $$("[data-shell='fl']").forEach((item) => {
        item.addEventListener('click', () => {
            if (queueTimer && item.dataset.page !== 'queue') {
                clearInterval(queueTimer);
                queueTimer = null;
            }
            FlRouter.go(item.dataset.page);
        });
    });

    // Artist nav clicks
    $$("[data-shell='ar']").forEach((item) => {
        item.addEventListener('click', () => ArRouter.go(item.dataset.page));
    });

    // Hide both app shells on start
    $('fl-app-shell').classList.add('hidden');
    $('ar-app-shell').classList.add('hidden');
    $('cs-app-shell').classList.add('hidden');
    $('pd-app-shell').classList.add('hidden');
    $('ac-app-shell').classList.add('hidden');
    $('ad-app-shell').classList.add('hidden');

    // Accounting nav clicks
    $$("[data-shell='ac']").forEach((item) => {
        item.addEventListener('click', () => AcRouter.go(item.dataset.page));
    });

    // Cashier nav clicks
    $$("[data-shell='cs']").forEach((item) => {
        item.addEventListener('click', () => CsRouter.go(item.dataset.page));
    });

    // Production nav clicks
    $$("[data-shell='pd']").forEach((item) => {
        item.addEventListener('click', () => PdRouter.go(item.dataset.page));
    });

    // Admin nav clicks
    $$("[data-shell='ad']").forEach((item) => {
        item.addEventListener('click', () => AdRouter.go(item.dataset.page));
    });

    // Clock for reg page
    setInterval(() => {
        const el = $('reg-time');
        if (el)
            el.textContent = new Date().toLocaleTimeString('en-PH', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true,
            });
        const el2 = $('artist-time');
        if (el2)
            el2.textContent = new Date().toLocaleTimeString('en-PH', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true,
            });
    }, 1000);
});
// ══════════════════════════════════════════════════════════════════
//  ADMIN ROUTER + LOGIC
// ══════════════════════════════════════════════════════════════════

const AdRouter = {
    current: null,
    go(page) {
        $$("[id^='ad-page-']").forEach((p) => p.classList.add('hidden'));
        const target = $('ad-page-' + page);
        if (target) {
            target.classList.remove('hidden');
            target.classList.add('animate-in');
        }
        $$("[data-shell='ad']").forEach((n) => n.classList.remove('active'));
        const nav = document.querySelector(
            `[data-shell='ad'][data-page='${page}']`,
        );
        if (nav) nav.classList.add('active');
        const titles = {
            'ad-dashboard': 'Dashboard',
            'ad-users': 'User Management',
            'ad-add-user': 'Add New User',
            'ad-config': 'System Config',
            'ad-reports': 'Reports',
            'ad-audit': 'Audit Log',
            'ad-pricing': 'Pricing Override',
        };
        const titleEl = $('ad-page-title');
        if (titleEl) titleEl.textContent = titles[page] || '';
        this.current = page;
        if (page === 'ad-users') renderAdUsers();
        if (page === 'ad-add-user') anuResetForm();
        if (page === 'ad-audit') adAuditInit();
        if (page === 'ad-pricing') pxInit();
    },
};

// ── User Table Data ──
const adUsers = [
    {
        id: 'FL001',
        name: 'Maria Santos',
        role: 'FL',
        roleLabel: 'Frontline',
        status: 'active',
        lastLogin: '2026-05-23 08:14',
    },
    {
        id: 'AR001',
        name: 'Juan Dela Cruz',
        role: 'AR',
        roleLabel: 'Artist',
        status: 'active',
        lastLogin: '2026-05-23 09:02',
    },
    {
        id: 'CS001',
        name: 'Ana Reyes',
        role: 'CS',
        roleLabel: 'Cashier',
        status: 'active',
        lastLogin: '2026-05-22 17:45',
    },
    {
        id: 'PD001',
        name: 'Jose Ramos',
        role: 'PD',
        roleLabel: 'Production',
        status: 'active',
        lastLogin: '2026-05-23 07:30',
    },
    {
        id: 'AC001',
        name: 'Liza Cruz',
        role: 'AC',
        roleLabel: 'Accounting',
        status: 'inactive',
        lastLogin: '2026-05-20 11:00',
    },
    {
        id: 'AD001',
        name: 'Admin User',
        role: 'AD',
        roleLabel: 'Admin',
        status: 'active',
        lastLogin: '2026-05-23 10:00',
    },
];

const roleBadgeStyle = {
    FL: 'background:var(--primary-fixed);color:var(--primary-dark);',
    AR: 'background:#fef3c7;color:#92400e;',
    CS: 'background:#e0e7ff;color:#3730a3;',
    PD: 'background:#dcfce7;color:#166534;',
    AC: 'background:#f3f4f6;color:#111827;',
    AD: 'background:#e0e7ff;color:#3730a3;',
};

function renderAdUsers(filter = '', roleFilter = '') {
    const tbody = $('ad-users-tbody');
    const list = adUsers.filter((u) => {
        const matchText =
            !filter ||
            u.name.toLowerCase().includes(filter.toLowerCase()) ||
            u.id.toLowerCase().includes(filter.toLowerCase());
        const matchRole = !roleFilter || u.role === roleFilter;
        return matchText && matchRole;
    });
    $('ad-user-count').textContent =
        list.length + ' User' + (list.length !== 1 ? 's' : '');
    tbody.innerHTML = list
        .map(
            (u) => `
    <tr class="${u.status === 'inactive' ? 'queue-row-busy' : ''}" style="${u.status === 'inactive' ? 'opacity:0.7;' : ''}">
      <td><span class="text-mono">${u.id}</span></td>
      <td><span style="font-weight:600;">${u.name}</span></td>
      <td><span class="badge" style="${roleBadgeStyle[u.role]}">${u.roleLabel}</span></td>
      <td>
        <div style="display:flex;align-items:center;gap:6px;">
          <div style="width:7px;height:7px;border-radius:50%;${u.status === 'active' ? 'background:#22c55e;box-shadow:0 0 0 2px rgba(34,197,94,0.3);' : 'border:1.5px solid var(--outline);'}"></div>
          <span style="font-size:13px;text-transform:capitalize;">${u.status}</span>
        </div>
      </td>
      <td><span class="text-mono color-muted">${u.lastLogin}</span></td>
      <td style="text-align:right;">
        <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap;">
          <button class="btn btn-sm btn-outline" onclick="adOpenEditModal('${u.id}')" title="Edit role or account details">
            <span class="material-symbols-outlined" style="font-size:14px;">edit</span> Edit Role
          </button>
          <button class="btn btn-sm btn-outline" onclick="adOpenResetPwModal('${u.id}')" title="Reset password" ${u.id === 'AD001' ? '' : ''}>
            <span class="material-symbols-outlined" style="font-size:14px;">lock_reset</span> Reset PW
          </button>
          <button class="btn btn-sm ${u.status === 'active' ? 'btn-danger' : 'btn-success'}" onclick="adOpenEditModal('${u.id}', 'deact')">
            ${u.status === 'active' ? '<span class="material-symbols-outlined" style="font-size:13px;">person_off</span> Deactivate' : '<span class="material-symbols-outlined" style="font-size:13px;">person_check</span> Activate'}
          </button>
        </div>
      </td>
    </tr>`,
        )
        .join('');
}

function filterAdUsers(text, role) {
    const roleVal =
        role !== undefined
            ? role
            : (document.querySelector('#ad-app-shell select') || { value: '' })
                  .value;
    renderAdUsers(text, roleVal);
}

// CRITICAL 6: Edit User Modal — tabbed (Role / Reset PW / Account Status)
function adEditSwitchTab(tab) {
    const tabs = ['role', 'pw', 'deact'];
    tabs.forEach((t) => {
        const btn = $('ad-edit-tab-' + t);
        const panel = $('ad-edit-tab-panel-' + t);
        if (!btn || !panel) return;
        const isActive = t === tab;
        btn.style.color = isActive
            ? 'var(--primary)'
            : 'var(--on-surface-variant)';
        btn.style.borderBottomColor = isActive
            ? 'var(--primary)'
            : 'transparent';
        panel.style.display = isActive ? 'block' : 'none';
    });
}

function adOpenEditModal(id, openTab) {
    const u = adUsers.find((x) => x.id === id);
    if (!u) return;
    const modal = $('ad-edit-user-modal');
    if (!modal) return;
    $('ad-edit-modal-name').textContent = u.name;
    $('ad-edit-modal-id').textContent = u.id;
    const roleSelect = $('ad-edit-role-select');
    if (roleSelect) roleSelect.value = u.role;
    $('ad-edit-modal-current-role').textContent = u.roleLabel;
    modal.dataset.userId = id;
    $('ad-edit-role-change-note').classList.add('hidden');
    // Reset PW tab — generate temp pw
    const tempPw =
        Math.random().toString(36).slice(2, 6).toUpperCase() +
        Math.floor(1000 + Math.random() * 9000);
    const tempEl = $('ad-reset-pw-temp');
    if (tempEl) tempEl.textContent = tempPw;
    const resetResult = $('ad-reset-pw-result');
    if (resetResult) resetResult.classList.add('hidden');
    const confirmBtn = $('ad-reset-pw-confirm-btn');
    if (confirmBtn) {
        confirmBtn.textContent = ' Reset Password';
        confirmBtn.innerHTML =
            '<span class="material-symbols-outlined" style="font-size:14px;">lock_reset</span> Reset Password';
        confirmBtn.onclick = adConfirmResetPw;
    }
    modal.dataset.tempPw = tempPw;
    // Deactivate tab — show correct view
    const activeView = $('ad-deact-active-view');
    const inactiveView = $('ad-deact-inactive-view');
    if (activeView)
        activeView.style.display = u.status === 'active' ? 'block' : 'none';
    if (inactiveView)
        inactiveView.style.display = u.status === 'inactive' ? 'block' : 'none';
    adEditSwitchTab(openTab || 'role');
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
}

function adOnEditRoleChange() {
    const modal = $('ad-edit-user-modal');
    const id = modal?.dataset.userId;
    const u = adUsers.find((x) => x.id === id);
    if (!u) return;
    const newRole = $('ad-edit-role-select')?.value;
    const note = $('ad-edit-role-change-note');
    if (note) {
        if (newRole && newRole !== u.role) {
            note.classList.remove('hidden');
        } else {
            note.classList.add('hidden');
        }
    }
}

function adConfirmEditRole() {
    const modal = $('ad-edit-user-modal');
    const id = modal?.dataset.userId;
    const u = adUsers.find((x) => x.id === id);
    if (!u) return;
    const roleSelect = $('ad-edit-role-select');
    const newRoleCode = roleSelect?.value;
    const roleLabels = {
        FL: 'Frontline',
        AR: 'Artist',
        CS: 'Cashier',
        PD: 'Production',
        AC: 'Accounting',
        AD: 'Admin',
    };
    const newRoleLabel = roleLabels[newRoleCode] || newRoleCode;
    const oldRole = u.roleLabel;
    const changed = newRoleCode !== u.role;
    if (changed) {
        u.role = newRoleCode;
        u.roleLabel = newRoleLabel;
        // MAJOR 1: Log role change to audit trail
        adAuditLog.unshift({
            ts: new Date().toLocaleString('en-PH', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true,
            }),
            role: 'AD',
            user: 'AD001',
            action: `Role changed for ${u.name} (${u.id}): ${oldRole} → ${newRoleLabel}`,
        });
        adShowActionBanner(
            'success',
            `✓ Role updated for ${u.name} — ${oldRole} → ${newRoleLabel}. Logged in Audit Trail.`,
        );
    }
    modal.classList.add('hidden');
    renderAdUsers();
    adRefreshPendingActions();
}

// CRITICAL 6: Reset PW — now opens tabbed modal on PW tab
function adOpenResetPwModal(id) {
    adOpenEditModal(id, 'pw');
}

function adConfirmResetPw() {
    const modal = $('ad-edit-user-modal');
    const id = modal?.dataset.userId;
    const tempPw = modal?.dataset.tempPw;
    const u = adUsers.find((x) => x.id === id);
    if (!u) return;
    // MAJOR 2: Log reset to audit trail
    adAuditLog.unshift({
        ts: new Date().toLocaleString('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        }),
        role: 'AD',
        user: 'AD001',
        action: `Password reset for ${u.name} (${u.id}) — temporary password issued`,
    });
    // Show result panel inside the modal
    const result = $('ad-reset-pw-result');
    if (result) {
        result.classList.remove('hidden');
        result.innerHTML = `
      <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:var(--radius);padding:12px 16px;margin-top:12px;">
        <div style="font-size:12px;font-weight:700;color:#166534;margin-bottom:6px;">
          <span class="material-symbols-outlined" style="font-size:13px;vertical-align:-2px;">check_circle</span>
          Password reset successful — logged in Audit Trail
        </div>
        <div style="font-size:12px;color:var(--on-surface);margin-bottom:4px;">Temporary password for <strong>${u.name}</strong>:</div>
        <div style="font-family:var(--font-mono);font-size:18px;font-weight:700;color:var(--primary);letter-spacing:0.12em;padding:8px 12px;background:var(--surface-container-low);border-radius:var(--radius-sm);display:inline-block;">${tempPw}</div>
        <div style="font-size:11px;color:var(--on-surface-variant);margin-top:6px;">Share this with the staff member. They must change this password on next login.</div>
      </div>`;
    }
    // Swap confirm button to Close
    const confirmBtn = $('ad-reset-pw-confirm-btn');
    if (confirmBtn) {
        confirmBtn.innerHTML = 'Close';
        confirmBtn.onclick = () => {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        };
    }
}

function adToggleUser(id) {
    const u = adUsers.find((x) => x.id === id);
    if (!u) return;
    if (u.id === 'AD001') {
        adShowActionBanner(
            'error',
            'Cannot deactivate the primary Admin account.',
        );
        return;
    }
    const prev = u.status;
    u.status = u.status === 'active' ? 'inactive' : 'active';
    // CRITICAL 6: Log status change to audit trail
    adAuditLog.unshift({
        ts: new Date().toLocaleString('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        }),
        role: 'AD',
        user: 'AD001',
        action: `Account ${u.status === 'active' ? 'activated' : 'deactivated'}: ${u.name} (${u.id}) — was ${prev}`,
    });
    adShowActionBanner(
        'success',
        `✓ ${u.name} (${u.id}) has been ${u.status}. Logged in Audit Trail.`,
    );
    renderAdUsers();
    adRefreshPendingActions();
}

// CRITICAL 6: Deactivate/Activate from unified modal
function adConfirmToggleFromModal() {
    const modal = $('ad-edit-user-modal');
    const id = modal?.dataset.userId;
    adToggleUser(id);
    modal.classList.add('hidden');
    modal.style.display = 'none';
}

// Shared action banner on User Management page
function adShowActionBanner(type, msg) {
    const banner = $('ad-user-created-banner');
    const title = $('ad-banner-title');
    const body = $('ad-banner-body');
    if (!banner || !title || !body) return;
    if (type === 'success') {
        banner.style.background = 'var(--success-container)';
        banner.style.borderColor = '#86efac';
        title.style.color = '#166534';
        body.style.color = '#166534';
        title.textContent = 'Action Completed';
    } else {
        banner.style.background = 'var(--error-container)';
        banner.style.borderColor = '#fca5a5';
        title.style.color = 'var(--error)';
        body.style.color = 'var(--error)';
        title.textContent = 'Action Blocked';
    }
    body.textContent = msg;
    const note = banner.querySelector('div:last-of-type');
    if (note) note.style.display = type === 'success' ? 'flex' : 'none';
    banner.classList.remove('hidden');
    setTimeout(
        () => banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' }),
        80,
    );
}

// ── Add New User Form ──
let anuRole = null,
    anuRoleCode = null;
const anuCounters = { FL: 1, AR: 1, CS: 1, PD: 1, AC: 1, AD: 1 };
const anuIcons = {
    FL: 'storefront',
    AR: 'palette',
    CS: 'point_of_sale',
    PD: 'precision_manufacturing',
    AC: 'account_balance',
    AD: 'shield',
};

function anuSelectRole(code, name, icon, portal) {
    anuRole = name;
    anuRoleCode = code;
    document
        .querySelectorAll('#anu-role-grid .type-option')
        .forEach((el) => el.classList.remove('selected'));
    const el = $('anu-role-' + code.toLowerCase());
    if (el) el.classList.add('selected');
    const next = String(anuCounters[code] + 1).padStart(3, '0');
    $('anu-preview-id').textContent = code + next;
    $('anu-preview-role').textContent = portal;
    $('anu-preview-icon').textContent = icon;
    const err = $('anu-err-role');
    if (err) err.style.display = 'none';
}

function anuTogglePw(inputId, iconId) {
    const inp = $(inputId),
        ico = $(iconId);
    if (!inp || !ico) return;
    const isText = inp.type === 'text';
    inp.type = isText ? 'password' : 'text';
    ico.textContent = isText ? 'visibility' : 'visibility_off';
}

function anuCheckStrength() {
    const pw = $('anu-password').value;
    const wrap = $('anu-pw-strength');
    const label = $('anu-pw-label');
    if (!pw) {
        wrap.style.display = 'none';
        return;
    }
    wrap.style.display = 'block';
    let score = 0;
    if (pw.length >= 6) score++;
    if (pw.length >= 10) score++;
    if (/[A-Z]/.test(pw) && /[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    const colors = ['var(--error)', '#f59e0b', '#22c55e', 'var(--primary)'];
    const labels = ['Weak', 'Fair', 'Good', 'Strong'];
    ['anu-b1', 'anu-b2', 'anu-b3', 'anu-b4'].forEach((id, i) => {
        const b = $(id);
        b.style.background =
            i < score ? colors[score - 1] : 'var(--outline-variant)';
    });
    label.textContent = labels[score - 1] || 'Weak';
    label.style.color = colors[score - 1] || 'var(--outline)';
    anuCheckMatch();
}

function anuCheckMatch() {
    const pw = $('anu-password').value,
        cfm = $('anu-confirm').value;
    const err = $('anu-err-cpw'),
        fc = $('anu-fc-cpw');
    if (cfm && pw !== cfm) {
        err.style.display = 'flex';
        if (fc) fc.classList.add('error-field');
    } else {
        err.style.display = 'none';
        if (fc) fc.classList.remove('error-field');
    }
}

function anuShowErr(id, show) {
    const err = $('anu-err-' + id),
        fc = $('anu-fc-' + id);
    if (err) err.style.display = show ? 'flex' : 'none';
    if (fc) fc.classList.toggle('error-field', show);
}

function anuValidate() {
    let ok = true;
    const fn = $('anu-firstname').value.trim();
    const ln = $('anu-lastname').value.trim();
    const em = $('anu-email').value.trim();
    const pw = $('anu-password').value;
    const cfm = $('anu-confirm').value;
    if (!fn) {
        anuShowErr('fn', true);
        ok = false;
    } else anuShowErr('fn', false);
    if (!ln) {
        anuShowErr('ln', true);
        ok = false;
    } else anuShowErr('ln', false);
    if (!em || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) {
        anuShowErr('em', true);
        ok = false;
    } else anuShowErr('em', false);
    if (!anuRoleCode) {
        const e = $('anu-err-role');
        if (e) e.style.display = 'flex';
        ok = false;
    }
    if (!pw || pw.length < 6) {
        anuShowErr('pw', true);
        ok = false;
    } else anuShowErr('pw', false);
    if (!cfm || pw !== cfm) {
        anuShowErr('cpw', true);
        ok = false;
    } else anuShowErr('cpw', false);
    return ok;
}

function anuSubmit() {
    if (!anuValidate()) return;
    const fn = $('anu-firstname').value.trim();
    const ln = $('anu-lastname').value.trim();
    const status = $('anu-status').value;
    const next = String(anuCounters[anuRoleCode] + 1).padStart(3, '0');
    const newId = anuRoleCode + next;
    anuCounters[anuRoleCode]++;
    adUsers.push({
        id: newId,
        name: fn + ' ' + ln,
        role: anuRoleCode,
        roleLabel: anuRole,
        status: status,
        lastLogin: '—',
    });
    // Log to audit trail data
    adAuditLog.unshift({
        ts: new Date().toLocaleString('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        }),
        role: 'AD',
        user: 'AD001',
        action: `Created account ${newId} — ${fn} ${ln} (${anuRole}) · Status: ${status}`,
    });
    anuResetForm();
    // MINOR 1: Show in-page success banner on User Management page (no alert)
    AdRouter.go('ad-users');
    const banner = $('ad-user-created-banner');
    const title = $('ad-banner-title');
    const body = $('ad-banner-body');
    if (banner && title && body) {
        title.textContent = `✓ Account Created — ${newId}`;
        body.innerHTML = `<strong>${fn} ${ln}</strong> · Role: ${anuRole} · Status: ${status}`;
        banner.classList.remove('hidden');
        setTimeout(
            () =>
                banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' }),
            80,
        );
    }
}

function anuResetForm() {
    [
        'anu-firstname',
        'anu-lastname',
        'anu-email',
        'anu-phone',
        'anu-password',
        'anu-confirm',
    ].forEach((id) => {
        const el = $(id);
        if (el) el.value = '';
    });
    const st = $('anu-status');
    if (st) st.value = 'active';
    document
        .querySelectorAll('#anu-role-grid .type-option')
        .forEach((el) => el.classList.remove('selected'));
    ['fn', 'ln', 'em', 'pw', 'cpw'].forEach((id) => anuShowErr(id, false));
    const re = $('anu-err-role');
    if (re) re.style.display = 'none';
    const pw = $('anu-pw-strength');
    if (pw) pw.style.display = 'none';
    $('anu-preview-id').textContent = '— — — —';
    $('anu-preview-role').textContent = 'Select a role to generate ID';
    $('anu-preview-icon').textContent = 'badge';
    anuRole = null;
    anuRoleCode = null;
}

// ── Audit Log Data (referenced by anuSubmit and future pages) ──────
const adAuditLog = [
    {
        ts: 'May 26, 2026, 09:14 AM',
        role: 'AD',
        user: 'AD001',
        action: 'Logged in to Admin Portal',
    },
    {
        ts: 'May 26, 2026, 08:55 AM',
        role: 'PD',
        user: 'PD001',
        action: 'Marked JO-2026-0991 as Ready for Pickup',
    },
    {
        ts: 'May 25, 2026, 05:41 PM',
        role: 'FL',
        user: 'FL001',
        action: 'Released JO-2026-0985 to client Aquino, Rosa M.',
    },
    {
        ts: 'May 25, 2026, 04:10 PM',
        role: 'CS',
        user: 'CS001',
        action: 'Processed payment for JO-2026-1003 — ₱1,200 (Full)',
    },
    {
        ts: 'May 25, 2026, 02:30 PM',
        role: 'AR',
        user: 'AR001',
        action: 'Approved design for JO-2026-0993',
    },
];

// ══════════════════════════════════════════════════════════════════
// AUDIT LOG PAGE — CRITICAL 3
// Read-only, filterable, paginated audit trail
// ══════════════════════════════════════════════════════════════════

let _alogPage = 1;
const _alogPageSize = 20;

const _alogRoleConfig = {
    AD: { label: 'Owner / Admin', color: '#1a3a8f', bg: '#dde1ff' },
    FL: { label: 'Frontline', color: '#065f46', bg: '#d1fae5' },
    AR: { label: 'Artist', color: '#92400e', bg: '#fef3c7' },
    CS: { label: 'Cashier', color: '#7c3aed', bg: '#ede9fe' },
    PD: { label: 'Production', color: '#1d4ed8', bg: '#dbeafe' },
    AC: { label: 'Accounting', color: '#9f1239', bg: '#ffe4e6' },
};

function adAuditInit() {
    // Populate user dropdown from unique users in log
    const userSel = document.getElementById('alog-filter-user');
    if (userSel) {
        const users = [...new Set(adAuditLog.map((e) => e.user))].sort();
        userSel.innerHTML =
            `<option value="">All Users</option>` +
            users.map((u) => `<option value="${u}">${u}</option>`).join('');
    }
    _alogPage = 1;
    adAuditUpdateStats();
    adAuditRender();
}

function adAuditGetFiltered() {
    const search = (
        document.getElementById('alog-search')?.value || ''
    ).toLowerCase();
    const role = document.getElementById('alog-filter-role')?.value || '';
    const user = document.getElementById('alog-filter-user')?.value || '';
    const from = document.getElementById('alog-date-from')?.value || '';
    const to = document.getElementById('alog-date-to')?.value || '';

    return adAuditLog.filter((e) => {
        if (role && e.role !== role) return false;
        if (user && e.user !== user) return false;
        if (
            search &&
            !e.action.toLowerCase().includes(search) &&
            !e.user.toLowerCase().includes(search) &&
            !e.ts.toLowerCase().includes(search)
        )
            return false;
        if (from || to) {
            // Parse ts: "May 26, 2026, 09:14 AM" → Date
            const d = new Date(e.ts);
            if (!isNaN(d)) {
                const iso = d.toISOString().slice(0, 10);
                if (from && iso < from) return false;
                if (to && iso > to) return false;
            }
        }
        return true;
    });
}

function adAuditRender() {
    const tbody = document.getElementById('alog-tbody');
    if (!tbody) return;

    const filtered = adAuditGetFiltered();
    const totalPages = Math.max(1, Math.ceil(filtered.length / _alogPageSize));
    if (_alogPage > totalPages) _alogPage = totalPages;

    const start = (_alogPage - 1) * _alogPageSize;
    const slice = filtered.slice(start, start + _alogPageSize);

    // Update filtered stat
    const fStat = document.getElementById('alog-stat-filtered');
    if (fStat) fStat.textContent = filtered.length.toLocaleString();

    // Pagination info
    const info = document.getElementById('alog-pagination-info');
    if (info) {
        if (filtered.length === 0) {
            info.textContent = 'No entries match the current filters.';
        } else {
            info.textContent = `Showing ${start + 1}–${Math.min(start + _alogPageSize, filtered.length)} of ${filtered.length} entries`;
        }
    }

    // Pagination buttons
    const prev = document.getElementById('alog-prev');
    const next = document.getElementById('alog-next');
    if (prev) prev.disabled = _alogPage <= 1;
    if (next) next.disabled = _alogPage >= totalPages;

    if (slice.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;padding:40px;color:var(--on-surface-variant);font-size:13px;">
      <span class="material-symbols-outlined" style="font-size:36px;display:block;margin-bottom:8px;opacity:0.4;">search_off</span>
      No audit entries match the current filters.
    </td></tr>`;
        return;
    }

    tbody.innerHTML = slice
        .map((e, idx) => {
            const rc = _alogRoleConfig[e.role] || {
                label: e.role,
                color: '#444',
                bg: '#eee',
            };
            const isEven = (start + idx) % 2 === 1;
            const rowBg = isEven
                ? 'var(--surface-container-low)'
                : 'var(--surface-container-lowest)';
            return `<tr style="background:${rowBg};border-bottom:1px solid var(--outline-variant);">
      <td style="padding:10px 16px;font-family:var(--font-mono);font-size:12px;color:var(--on-surface-variant);white-space:nowrap;">${e.ts}</td>
      <td style="padding:10px 16px;">
        <span style="display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600;font-family:var(--font-mono);background:${rc.bg};color:${rc.color};">${rc.label}</span>
      </td>
      <td style="padding:10px 16px;font-family:var(--font-mono);font-size:12px;font-weight:600;color:var(--on-surface);">${e.user}</td>
      <td style="padding:10px 16px;font-size:13px;color:var(--on-surface);">${e.action}</td>
    </tr>`;
        })
        .join('');
}

function adAuditUpdateStats() {
    const total = adAuditLog.length;
    const todayStr = new Date().toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
    const todayEntries = adAuditLog.filter((e) => e.ts.startsWith(todayStr));
    const rolesActive = new Set(todayEntries.map((e) => e.role)).size;

    const tStat = document.getElementById('alog-stat-total');
    const toStat = document.getElementById('alog-stat-today');
    const rStat = document.getElementById('alog-stat-roles');
    const fStat = document.getElementById('alog-stat-filtered');

    if (tStat) tStat.textContent = total.toLocaleString();
    if (toStat) toStat.textContent = todayEntries.length.toLocaleString();
    if (rStat) rStat.textContent = rolesActive.toLocaleString();
    if (fStat) fStat.textContent = total.toLocaleString();
}

function adAuditPrevPage() {
    if (_alogPage > 1) {
        _alogPage--;
        adAuditRender();
    }
}
function adAuditNextPage() {
    const filtered = adAuditGetFiltered();
    const totalPages = Math.ceil(filtered.length / _alogPageSize);
    if (_alogPage < totalPages) {
        _alogPage++;
        adAuditRender();
    }
}

function adAuditClearFilters() {
    const ids = [
        'alog-search',
        'alog-filter-role',
        'alog-filter-user',
        'alog-date-from',
        'alog-date-to',
    ];
    ids.forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    _alogPage = 1;
    adAuditRender();
}

function adAuditExport() {
    const filtered = adAuditGetFiltered();
    const header = ['Timestamp', 'Role', 'User ID', 'Action'];
    const rows = filtered.map((e) => {
        const rc = _alogRoleConfig[e.role] || { label: e.role };
        return [
            `"${e.ts}"`,
            `"${rc.label}"`,
            `"${e.user}"`,
            `"${e.action.replace(/"/g, '""')}"`,
        ].join(',');
    });
    const csv = [header.join(','), ...rows].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `inkspire-audit-log-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);

    // Log the export itself
    adAuditLog.unshift({
        ts: new Date().toLocaleString('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        }),
        role: 'AD',
        user: 'AD001',
        action: `Exported Audit Log — ${filtered.length} entries (CSV download)`,
    });
    adAuditUpdateStats();
    adAuditRender();
}

// ══════════════════════════════════════════════════════════════════
// PRICING OVERRIDE PAGE — CRITICAL 4
// Base prices, design fees, rush charges, discounts, simulator
// ══════════════════════════════════════════════════════════════════

let pxDirty = false;

// Live pricing data store — source of truth for simulator + Cashier POS
const pxData = {
    base: {
        tarp: { sqft: 25, min: 150, sintraUpcharge: 40 },
        sticker: { sqin: 3.5, minQty: 10, min: 50 },
        flyerA4: { perPc: 12 },
        flyerA5: { perPc: 7 },
        flyerA3: { perPc: 20 },
        flyerMinQty: 50,
        cardBC: { perPc: 1.5 },
        cardID: { perPc: 25 },
        cardMinQty: 100,
        banner: { sqft: 30, min: 200, eyelet: 5 },
    },
    design: {
        consult: 100,
        cxSimple: 0,
        cxModerate: 150,
        cxComplex: 350,
        revFree: 1,
        revFee: 75,
    },
    rush: {
        rushPct: 25,
        superRushPct: 50,
    },
    discount: {
        cashierMaxPct: 10,
        cashierMaxFlat: 200,
        senior: 20,
        seniorEnabled: true,
        loyalty: 5,
        loyaltyEnabled: true,
        bulk: 10,
        bulkEnabled: true,
        special: 0,
        specialEnabled: false,
        overrideMethod: 'pin',
        overridePin: '',
    },
};

function pxInit() {
    pxDirty = false;
    const bar = document.getElementById('px-dirty-bar');
    if (bar) bar.classList.add('hidden');

    // Activate first tab
    const tabs = document.querySelectorAll('#ad-page-ad-pricing .sc-tab');
    const sections = document.querySelectorAll(
        '#ad-page-ad-pricing .sc-section',
    );
    tabs.forEach((t) => t.classList.remove('active'));
    sections.forEach((s) => s.classList.add('hidden'));
    if (tabs[0]) tabs[0].classList.add('active');
    const firstSec = document.getElementById('px-sec-base');
    if (firstSec) firstSec.classList.remove('hidden');

    pxUpdateRushPreview();
    pxSimRefresh();
}

function pxTab(btn, sectionId) {
    document
        .querySelectorAll('#ad-page-ad-pricing .sc-tab')
        .forEach((t) => t.classList.remove('active'));
    document
        .querySelectorAll('#ad-page-ad-pricing .sc-section')
        .forEach((s) => s.classList.add('hidden'));
    btn.classList.add('active');
    const sec = document.getElementById(sectionId);
    if (sec) sec.classList.remove('hidden');
}

function pxMarkDirty() {
    if (!pxDirty) {
        pxDirty = true;
        const bar = document.getElementById('px-dirty-bar');
        if (bar) bar.classList.remove('hidden');
    }
}

function pxDiscard() {
    if (!confirm('Discard all unsaved pricing changes?')) return;
    pxDirty = false;
    AdRouter.go('ad-pricing');
}

function pxUpdateRushPreview() {
    const rushPct = parseFloat(
        document.getElementById('px-rush-pct')?.value || 25,
    );
    const sample = 400;
    const rPrev = document.getElementById('px-rush-preview');
    if (rPrev)
        rPrev.textContent = `e.g. ₱${sample} order → +₱${((sample * rushPct) / 100).toFixed(0)} rush fee → ₱${(sample * (1 + rushPct / 100)).toFixed(0)} total`;
}

function pxReadCurrentValues() {
    // Read all form inputs into pxData
    const g = (id, fallback) => {
        const el = document.getElementById(id);
        return el ? parseFloat(el.value) || fallback : fallback;
    };
    pxData.base.tarp.sqft = g('px-tarp-sqft', 25);
    pxData.base.tarp.min = g('px-tarp-min', 150);
    pxData.base.tarp.sintraUpcharge = g('px-tarp-sintra', 40);
    pxData.base.sticker.sqin = g('px-sticker-sqin', 3.5);
    pxData.base.sticker.minQty = g('px-sticker-minqty', 10);
    pxData.base.sticker.min = g('px-sticker-min', 50);
    pxData.base.flyerA4.perPc = g('px-flyer-a4', 12);
    pxData.base.flyerA5.perPc = g('px-flyer-a5', 7);
    pxData.base.flyerA3.perPc = g('px-flyer-a3', 20);
    pxData.base.flyerMinQty = g('px-flyer-minqty', 50);
    pxData.base.cardBC.perPc = g('px-card-bc', 1.5);
    pxData.base.cardID.perPc = g('px-card-id', 25);
    pxData.base.cardMinQty = g('px-card-minqty', 100);
    pxData.base.banner.sqft = g('px-banner-sqft', 30);
    pxData.base.banner.min = g('px-banner-min', 200);
    pxData.base.banner.eyelet = g('px-banner-eyelet', 5);

    pxData.design.consult = g('px-design-consult', 100);
    pxData.design.cxSimple = g('px-cx-simple', 0);
    pxData.design.cxModerate = g('px-cx-moderate', 150);
    pxData.design.cxComplex = g('px-cx-complex', 350);
    pxData.design.revFree = g('px-rev-free', 1);
    pxData.design.revFee = g('px-rev-fee', 75);

    pxData.rush.rushPct = g('px-rush-pct', 25);

    pxData.discount.cashierMaxPct = g('px-disc-cashier-pct', 10);
    pxData.discount.cashierMaxFlat = g('px-disc-cashier-flat', 200);
    pxData.discount.senior = g('px-disc-senior', 20);
    pxData.discount.loyalty = g('px-disc-loyalty', 5);
    pxData.discount.bulk = g('px-disc-bulk', 10);
    pxData.discount.special = g('px-disc-special', 0);
    const omEl = document.getElementById('px-override-method');
    if (omEl) pxData.discount.overrideMethod = omEl.value;
}

function pxSaveAll() {
    pxReadCurrentValues();
    pxDirty = false;

    const bar = document.getElementById('px-dirty-bar');
    if (bar) bar.classList.add('hidden');

    const banner = document.getElementById('px-save-banner');
    if (banner) {
        banner.classList.remove('hidden');
        setTimeout(() => {
            banner.classList.add('hidden');
        }, 4000);
    }

    // Log to audit trail
    if (typeof adAuditLog !== 'undefined') {
        adAuditLog.unshift({
            ts: new Date().toLocaleString('en-PH', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true,
            }),
            role: 'AD',
            user: 'AD001',
            action: `Pricing Override updated — base prices, rush charges, and discount rules saved`,
        });
        if (typeof rpUpdateAuditCount === 'function') rpUpdateAuditCount();
    }

    pxSimRefresh();
}

// ── Price Simulator ───────────────────────────────────────────────
function pxSimReset() {
    const prod = document.getElementById('px-sim-product');
    const qty = document.getElementById('px-sim-qty');
    const urg = document.getElementById('px-sim-urgency');
    const typ = document.getElementById('px-sim-type');
    const disc = document.getElementById('px-sim-discount');
    if (prod) prod.value = 'tarp';
    if (qty) qty.value = '1';
    if (urg) urg.value = 'normal';
    if (typ) typ.value = 'a';
    if (disc) disc.value = '0';
    pxSimRefresh();
}

function pxSimRefresh() {
    const prodEl = document.getElementById('px-sim-product');
    const qtyEl = document.getElementById('px-sim-qty');
    const urgEl = document.getElementById('px-sim-urgency');
    const typeEl = document.getElementById('px-sim-type');
    const discEl = document.getElementById('px-sim-discount');
    if (!prodEl) return;

    const prod = prodEl.value;
    const qty = parseFloat(qtyEl?.value) || 1;
    const urg = urgEl?.value || 'normal';
    const ctype = typeEl?.value || 'a';
    const discPct = parseFloat(discEl?.value) || 0;

    // Live-read base price inputs for simulator
    let basePrice = 0;
    let baseLabel = '';
    switch (prod) {
        case 'tarp':
            basePrice =
                (parseFloat(document.getElementById('px-tarp-sqft')?.value) ||
                    pxData.base.tarp.sqft) * qty;
            basePrice = Math.max(
                basePrice,
                parseFloat(document.getElementById('px-tarp-min')?.value) ||
                    pxData.base.tarp.min,
            );
            baseLabel = `Tarpaulin ${qty} sq ft @ ₱${document.getElementById('px-tarp-sqft')?.value || pxData.base.tarp.sqft}/sq ft`;
            break;
        case 'sticker':
            basePrice =
                (parseFloat(
                    document.getElementById('px-sticker-sqin')?.value,
                ) || pxData.base.sticker.sqin) * qty;
            basePrice = Math.max(
                basePrice,
                parseFloat(document.getElementById('px-sticker-min')?.value) ||
                    pxData.base.sticker.min,
            );
            baseLabel = `Stickers ${qty} sq in`;
            break;
        case 'flyer':
            basePrice =
                (parseFloat(document.getElementById('px-flyer-a4')?.value) ||
                    pxData.base.flyerA4.perPc) * qty;
            baseLabel = `Flyers A4 × ${qty} pcs`;
            break;
        case 'card':
            basePrice =
                (parseFloat(document.getElementById('px-card-bc')?.value) ||
                    pxData.base.cardBC.perPc) * qty;
            baseLabel = `Business Cards × ${qty} pcs`;
            break;
        case 'banner':
            basePrice =
                (parseFloat(document.getElementById('px-banner-sqft')?.value) ||
                    pxData.base.banner.sqft) * qty;
            basePrice = Math.max(
                basePrice,
                parseFloat(document.getElementById('px-banner-min')?.value) ||
                    pxData.base.banner.min,
            );
            baseLabel = `Banner ${qty} sq ft`;
            break;
    }

    // Design fee
    let designFee = 0;
    let designLabel = '';
    if (ctype !== 'a') {
        const consultFee =
            parseFloat(document.getElementById('px-design-consult')?.value) ||
            pxData.design.consult;
        designFee = consultFee;
        designLabel = `Consultation fee`;
        if (ctype === 'b-simple') {
            const cx =
                parseFloat(document.getElementById('px-cx-simple')?.value) || 0;
            designFee += cx;
            designLabel = `Design fee (Simple)`;
        } else if (ctype === 'b-moderate') {
            const cx =
                parseFloat(document.getElementById('px-cx-moderate')?.value) ||
                pxData.design.cxModerate;
            designFee += cx;
            designLabel = `Design fee (Moderate)`;
        } else if (ctype === 'b-complex') {
            const cx =
                parseFloat(document.getElementById('px-cx-complex')?.value) ||
                pxData.design.cxComplex;
            designFee += cx;
            designLabel = `Design fee (Complex)`;
        }
    }

    // Subtotal before rush
    const subtotal = basePrice + designFee;

    // Rush charge
    let rushFee = 0;
    let rushLabel = '';
    if (urg === 'rush') {
        const rPct =
            parseFloat(document.getElementById('px-rush-pct')?.value) ||
            pxData.rush.rushPct;
        rushFee = (subtotal * rPct) / 100;
        rushLabel = `Rush surcharge (${rPct}%)`;
    }

    const subtotal2 = subtotal + rushFee;

    // Discount
    const discAmt = (subtotal2 * discPct) / 100;

    const total = Math.max(0, subtotal2 - discAmt);

    // Build breakdown
    const fmt = (n) =>
        `₱${n.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
    const lineRow = (label, amt, accent) =>
        `<div style="display:flex;justify-content:space-between;align-items:center;padding:7px 12px;background:${accent || 'var(--surface-container-lowest)'};border-radius:6px;border:1px solid var(--outline-variant);">
      <span style="font-size:13px;color:var(--on-surface-variant);">${label}</span>
      <span style="font-family:var(--font-mono);font-weight:600;font-size:13px;">${amt}</span>
    </div>`;

    let rows = lineRow(baseLabel, fmt(basePrice));
    if (designFee > 0) rows += lineRow(designLabel, fmt(designFee));
    if (rushFee > 0) rows += lineRow(rushLabel, `+${fmt(rushFee)}`, '#fff7ed');
    if (discAmt > 0)
        rows += lineRow(
            `Discount (${discPct}%)`,
            `−${fmt(discAmt)}`,
            '#f0fdf4',
        );

    const breakdown = document.getElementById('px-sim-breakdown');
    const totalEl = document.getElementById('px-sim-total');
    if (breakdown) breakdown.innerHTML = rows;
    if (totalEl) totalEl.textContent = fmt(total);
}

// MAJOR 3: Pending Actions data — items requiring Admin intervention
const adPendingActions = [
    {
        id: 'pa-reprint-001',
        type: 'reprint-approval',
        priority: 'high',
        icon: 'print',
        title: 'Store Error Reprint — Awaiting Approval',
        body: 'JO-2026-0958 (Navarro, Cel P. · Sintra Board 3×5ft) — Production logged a quality complaint. Reprint tagged as Store Error; requires Owner/Admin approval before production proceeds.',
        action: 'Review & Approve',
        actionFn: "adApprovePendingAction('pa-reprint-001')",
        ts: 'May 26, 2026, 8:42 AM',
    },
    {
        id: 'pa-writeoff-001',
        type: 'write-off',
        priority: 'medium',
        icon: 'money_off',
        title: 'Balance Write-Off Request',
        body: 'JO-2026-0812 (Santos, Maria · Tarpaulin 3×6ft) — Outstanding balance of ₱350 has been unpaid for 6 days. Accounting staff flagged for write-off decision.',
        action: 'Approve Write-Off',
        actionFn: "adApprovePendingAction('pa-writeoff-001')",
        ts: 'May 26, 2026, 7:00 AM',
    },
    {
        id: 'pa-storage-001',
        type: 'storage-alert',
        priority: 'medium',
        icon: 'storage',
        title: 'Storage Threshold Warning — 82% Used',
        body: 'System file storage is at 82% capacity. 47 design files are past their 1-year retention window and eligible for deletion. Review and confirm deletion or extend retention.',
        action: 'Review Files',
        actionFn: "adApprovePendingAction('pa-storage-001')",
        ts: 'May 26, 2026, 6:00 AM',
    },
];

function adApprovePendingAction(id) {
    const pa = adPendingActions.find((p) => p.id === id);
    if (!pa) return;
    // CRITICAL 7: Store Error Reprint — open detail modal
    if (pa.type === 'reprint-approval') {
        adOpenStoreErrorModal(pa);
        return;
    }
    // CRITICAL 8: Write-Off — open detail modal
    if (pa.type === 'write-off') {
        adOpenWriteOffModal(pa);
        return;
    }
    // Other actions — resolve directly
    pa.resolved = true;
    adAuditLog.unshift({
        ts: new Date().toLocaleString('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        }),
        role: 'AD',
        user: 'AD001',
        action: `Resolved pending action: ${pa.title}`,
    });
    adRefreshPendingActions();
    adShowActionBanner(
        'success',
        `✓ "${pa.title}" resolved and logged in Audit Trail.`,
    );
}

// CRITICAL 7: Store Error Reprint Approval Modal
function adOpenStoreErrorModal(pa) {
    const modal = $('ad-store-error-modal');
    if (!modal) return;
    $('ad-se-jo-label').textContent = 'JO-2026-0958';
    $('ad-se-client').textContent = 'Navarro, Cel P.';
    $('ad-se-product').textContent = 'Sintra Board 3×5ft';
    $('ad-se-flagged-by').textContent = 'PD001 — Production Staff';
    $('ad-se-flagged-at').textContent = pa.ts;
    $('ad-se-complaint').textContent =
        'Printed output does not match the approved design — colors appear washed out and bleed areas were cut incorrectly. Client noticed the discrepancy upon pickup. Production has confirmed this is a machine calibration issue on our end.';
    const notes = $('ad-se-notes');
    if (notes) notes.value = '';
    modal.dataset.paId = pa.id;
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
}

function adDecideStoreError(decision) {
    const modal = $('ad-store-error-modal');
    const paId = modal?.dataset.paId;
    const pa = adPendingActions.find((p) => p.id === paId);
    const notes = $('ad-se-notes')?.value?.trim();
    if (pa) pa.resolved = true;
    const actionText =
        decision === 'approve'
            ? `Store Error Reprint APPROVED — JO-2026-0958 (Navarro, Cel P.). Tagged as Reprint — Store Error. Free reprint activated in Production.${notes ? ' Notes: ' + notes : ''}`
            : `Store Error Reprint DENIED — JO-2026-0958 (Navarro, Cel P.). Original JO remains closed. No free reprint issued.${notes ? ' Notes: ' + notes : ''}`;
    adAuditLog.unshift({
        ts: new Date().toLocaleString('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        }),
        role: 'AD',
        user: 'AD001',
        action: actionText,
    });
    modal.classList.add('hidden');
    modal.style.display = 'none';
    adRefreshPendingActions();
    adShowActionBanner(
        'success',
        decision === 'approve'
            ? '✓ Store Error Reprint approved. JO-2026-0958 tagged as Reprint — Store Error and sent to Production. Logged in Audit Trail.'
            : '✓ Store Error Reprint denied. Decision logged in Audit Trail.',
    );
}

// CRITICAL 8: Write-Off Approval Modal
function adOpenWriteOffModal(pa) {
    const modal = $('ad-writeoff-modal');
    if (!modal) return;
    $('ad-wo-jo-label').textContent = 'JO-2026-0812';
    $('ad-wo-client').textContent = 'Santos, Maria';
    $('ad-wo-service').textContent = 'Tarpaulin 3×6ft';
    $('ad-wo-balance').textContent = '₱350.00';
    $('ad-wo-balance-inline').textContent = '₱350.00';
    $('ad-wo-days').textContent = '6 days';
    $('ad-wo-invoice-date').textContent = 'May 20, 2026';
    $('ad-wo-flagged-by').textContent = 'AC001 — Accounting Staff';
    $('ad-wo-reason').textContent =
        'Client has not returned to settle the remaining balance after multiple follow-up attempts. Amount is small and continued collection efforts are not cost-effective. Recommending write-off.';
    const notes = $('ad-wo-notes');
    if (notes) notes.value = '';
    modal.dataset.paId = pa.id;
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
}

function adDecideWriteOff(decision) {
    const modal = $('ad-writeoff-modal');
    const paId = modal?.dataset.paId;
    const pa = adPendingActions.find((p) => p.id === paId);
    const notes = $('ad-wo-notes')?.value?.trim();
    if (pa) pa.resolved = true;
    const actionText =
        decision === 'approve'
            ? `AR Write-Off APPROVED — JO-2026-0812 (Santos, Maria · ₱350.00). Balance marked Written Off. Logged in accounting records.${notes ? ' Notes: ' + notes : ''}`
            : `AR Write-Off DENIED — JO-2026-0812 (Santos, Maria · ₱350.00). Balance remains in active AR collection.${notes ? ' Notes: ' + notes : ''}`;
    adAuditLog.unshift({
        ts: new Date().toLocaleString('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        }),
        role: 'AD',
        user: 'AD001',
        action: actionText,
    });
    modal.classList.add('hidden');
    modal.style.display = 'none';
    adRefreshPendingActions();
    adShowActionBanner(
        'success',
        decision === 'approve'
            ? '✓ Write-Off approved. JO-2026-0812 balance of ₱350.00 marked as Written Off. Logged in Audit Trail.'
            : '✓ Write-Off denied. AR entry remains open for collection. Logged in Audit Trail.',
    );
}

function adRefreshPendingActions() {
    const container = $('ad-pending-actions-list');
    if (!container) return;
    const active = adPendingActions.filter((p) => !p.resolved);
    const countEl = $('ad-pending-count');
    if (countEl) countEl.textContent = active.length;
    const wrapper = $('ad-pending-actions-section');
    if (wrapper) {
        wrapper.style.display = active.length ? 'block' : 'none';
    }
    const priorityConfig = {
        high: {
            bg: '#fef2f2',
            border: '#fca5a5',
            iconBg: '#fef2f2',
            iconColor: '#dc2626',
            labelColor: '#991b1b',
        },
        medium: {
            bg: '#fef3c7',
            border: '#fcd34d',
            iconBg: '#fef3c7',
            iconColor: '#92400e',
            labelColor: '#78350f',
        },
        low: {
            bg: 'var(--surface-container-low)',
            border: 'var(--outline-variant)',
            iconBg: 'var(--surface-container)',
            iconColor: 'var(--on-surface-variant)',
            labelColor: 'var(--on-surface-variant)',
        },
    };
    const typeLabel = {
        'reprint-approval': 'Reprint Approval',
        'write-off': 'Write-Off',
        'storage-alert': 'Storage Alert',
    };
    container.innerHTML = active
        .map((pa) => {
            const cfg = priorityConfig[pa.priority] || priorityConfig.low;
            return `
    <div style="background:${cfg.bg};border:1px solid ${cfg.border};border-radius:var(--radius-md);padding:14px 18px;display:flex;align-items:flex-start;gap:14px;">
      <div style="width:36px;height:36px;border-radius:var(--radius);background:${cfg.iconBg};border:1px solid ${cfg.border};color:${cfg.iconColor};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <span class="material-symbols-outlined" style="font-size:18px;">${pa.icon}</span>
      </div>
      <div style="flex:1;min-width:0;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px;flex-wrap:wrap;">
          <span style="font-size:13px;font-weight:700;color:${cfg.labelColor};">${pa.title}</span>
          <span style="font-family:var(--font-mono);font-size:10px;font-weight:700;padding:2px 7px;border-radius:var(--radius-full);background:${cfg.border};color:${cfg.labelColor};">${typeLabel[pa.type] || pa.type}</span>
        </div>
        <div style="font-size:12px;color:var(--on-surface);line-height:1.5;margin-bottom:8px;">${pa.body}</div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
          <button class="btn btn-sm btn-primary" onclick="${pa.actionFn}" style="font-size:12px;">
            <span class="material-symbols-outlined" style="font-size:13px;">check_circle</span>${pa.action}
          </button>
          <span style="font-family:var(--font-mono);font-size:10px;color:var(--on-surface-variant);">${pa.ts}</span>
        </div>
      </div>
    </div>`;
        })
        .join('');
}

// ── MINOR 3: Dynamic System Status ──────────────────────────────────
// Tracks simulated system state — in a real build this reads from a health API
const adSystemState = {
    issues: [], // Each: { id, level, message, since }
};

function adAddSystemIssue(id, level, message) {
    if (adSystemState.issues.find((i) => i.id === id)) return;
    adSystemState.issues.push({ id, level, message, since: new Date() });
    adRefreshSystemStatus();
}

function adResolveSystemIssue(id) {
    adSystemState.issues = adSystemState.issues.filter((i) => i.id !== id);
    adRefreshSystemStatus();
}

function adRefreshSystemStatus() {
    const dot = $('ad-status-dot');
    const label = $('ad-status-label');
    if (!dot || !label) return;
    const critical = adSystemState.issues.filter((i) => i.level === 'critical');
    const warnings = adSystemState.issues.filter((i) => i.level === 'warning');
    if (critical.length) {
        dot.style.background = '#dc2626';
        dot.style.boxShadow = '0 0 0 3px rgba(220,38,38,0.25)';
        label.textContent = `System Issue Detected (${critical.length})`;
        label.style.color = '#dc2626';
    } else if (warnings.length) {
        dot.style.background = '#f59e0b';
        dot.style.boxShadow = '0 0 0 3px rgba(245,158,11,0.25)';
        label.textContent = `${warnings.length} Warning${warnings.length > 1 ? 's' : ''} — Check Alerts`;
        label.style.color = '#92400e';
    } else {
        dot.style.background = '';
        dot.style.boxShadow = '';
        label.textContent = 'All Systems Operational';
        label.style.color = '';
    }
}

// ── MINOR 4+5: Artist Alerts (break overrun + unexpected offline) ───
const adArtistAlerts = []; // { id, type, artist, message, ts, dismissed }

function adPushArtistAlert(type, artist, message) {
    const id = `alert-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;
    adArtistAlerts.unshift({
        id,
        type,
        artist,
        message,
        ts: new Date(),
        dismissed: false,
    });
    adRenderArtistAlerts();
    // Also register as a system warning
    adAddSystemIssue(id, 'warning', message);
}

function adDismissAlert(id) {
    const alert = adArtistAlerts.find((a) => a.id === id);
    if (alert) alert.dismissed = true;
    adResolveSystemIssue(id);
    adRenderArtistAlerts();
}

function adDismissAllAlerts() {
    adArtistAlerts.forEach((a) => {
        a.dismissed = true;
        adResolveSystemIssue(a.id);
    });
    adRenderArtistAlerts();
}

function adRenderArtistAlerts() {
    const panel = $('ad-artist-alerts');
    const list = $('ad-artist-alert-list');
    const badge = $('ad-alert-badge');
    if (!panel || !list) return;
    const active = adArtistAlerts.filter((a) => !a.dismissed);
    if (!active.length) {
        panel.classList.add('hidden');
        return;
    }
    panel.classList.remove('hidden');
    if (badge) badge.textContent = active.length;
    const typeConfig = {
        'break-overrun': {
            icon: 'timer_off',
            color: '#92400e',
            bg: '#fef3c7',
            label: 'Break Overrun',
        },
        'unexpected-offline': {
            icon: 'wifi_off',
            color: '#991b1b',
            bg: '#fef2f2',
            label: 'Unexpected Offline',
        },
    };
    list.innerHTML = active
        .map((a) => {
            const cfg = typeConfig[a.type] || typeConfig['unexpected-offline'];
            const ago = Math.round((new Date() - a.ts) / 60000);
            const agoLabel =
                ago < 1
                    ? 'just now'
                    : ago === 1
                      ? '1 min ago'
                      : `${ago} mins ago`;
            return `<div style="display:flex;align-items:flex-start;gap:10px;background:white;border-radius:var(--radius);padding:10px 14px;border:1px solid ${cfg.color}22;">
      <div style="width:30px;height:30px;border-radius:var(--radius-sm);background:${cfg.bg};color:${cfg.color};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <span class="material-symbols-outlined" style="font-size:16px;">${cfg.icon}</span>
      </div>
      <div style="flex:1;min-width:0;">
        <div style="font-size:12px;font-weight:700;color:${cfg.color};">${cfg.label} — ${a.artist}</div>
        <div style="font-size:12px;color:var(--on-surface);margin-top:2px;">${a.message}</div>
        <div style="font-family:var(--font-mono);font-size:10px;color:var(--on-surface-variant);margin-top:3px;">${agoLabel}</div>
      </div>
      <button onclick="adDismissAlert('${a.id}')" style="background:none;border:none;cursor:pointer;color:var(--on-surface-variant);flex-shrink:0;padding:0;" title="Dismiss">
        <span class="material-symbols-outlined" style="font-size:16px;">close</span>
      </button>
    </div>`;
        })
        .join('');
}

// ── Simulate 2 artist alerts on dashboard load for demo ─────────────
AdRouter.register &&
    (function () {
        const _origGo = AdRouter.go.bind(AdRouter);
        AdRouter.go = function (page) {
            _origGo(page);
            if (page === 'ad-dashboard') {
                // Seed demo alerts once (so demo shows the panel immediately)
                if (!adArtistAlerts.length) {
                    setTimeout(() => {
                        adPushArtistAlert(
                            'break-overrun',
                            'AR002 — Reyes, Carlo',
                            'Artist has been on break for 22 minutes. Max break time is 15 minutes.',
                        );
                        adPushArtistAlert(
                            'unexpected-offline',
                            'AR001 — Dela Cruz, Juan',
                            'Session expired unexpectedly during active consultation (JO-2026-0993). Customer may need reassignment.',
                        );
                    }, 400);
                } else {
                    // Re-render in case alerts were added while on another page
                    setTimeout(adRenderArtistAlerts, 100);
                }
                adRefreshSystemStatus();
            }
            if (page === 'ad-reports') {
                setTimeout(rpInitPage, 80);
            }
        };
    })();

function csCancellationRefused() {
    const modal = document.getElementById('cs-refuses-modal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }
}

function csCancellationRefusedConfirm() {
    // Hide modal
    const modal = document.getElementById('cs-refuses-modal');
    if (modal) modal.classList.add('hidden');

    // Disable all action buttons on cancellation page
    const payBtn = document.getElementById('cs-process-payment-btn');
    const receiptBtn = document.getElementById('cs-gen-receipt-btn');
    const refusesBtn = document.getElementById('cs-refuses-btn');
    if (payBtn) {
        payBtn.disabled = true;
        payBtn.style.opacity = '0.4';
    }
    if (receiptBtn) {
        receiptBtn.disabled = true;
        receiptBtn.style.opacity = '0.4';
    }
    if (refusesBtn) {
        refusesBtn.disabled = true;
        refusesBtn.style.opacity = '0.4';
    }

    // Show the unpaid AR banner
    const banner = document.getElementById('cs-cancel-unpaid-banner');
    if (banner) {
        banner.classList.remove('hidden');
        setTimeout(
            () =>
                banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' }),
            100,
        );
    }

    // Log to history with "Cancelled with Fee — Unpaid" status
    if (typeof csLiveHistory !== 'undefined') {
        csLiveHistory.unshift({
            jo: 'JO-2023-8901',
            customer: 'Doe, Jane A.',
            type: 'Type B',
            paid: 0,
            balance: 25,
            status: 'Cancelled with Fee — Unpaid',
            date:
                new Date().toLocaleDateString('en-PH', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                }) +
                ' ' +
                new Date().toLocaleTimeString('en-PH', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true,
                }),
        });
    }
}

// ════════════════════════════════════════════════════════════════
// GAP 2: Balance Collection Page
// ════════════════════════════════════════════════════════════════

// Sample AR data — partially paid JOs
const bcArData = [
    {
        jo: 'JO-2026-0812',
        client: 'Maria Santos',
        product: 'Tarpaulin 3×6ft · Vinyl Matte',
        downDate: 'May 20, 2026',
        total: 650,
        paid: 300,
        balance: 350,
    },
    {
        jo: 'JO-2026-0831',
        client: 'Jose Reyes',
        product: 'Flyer A5 2-sided · 500pcs',
        downDate: 'May 22, 2026',
        total: 1800,
        paid: 900,
        balance: 900,
    },
    {
        jo: 'JO-2026-0847',
        client: 'Ana Santos',
        product: 'Sticker A4 · Vinyl Gloss · 10pcs',
        downDate: 'May 23, 2026',
        total: 420,
        paid: 200,
        balance: 220,
    },
    {
        jo: 'JO-2026-0855',
        client: 'Carlos Reyes',
        product: 'ID Card PVC · 50pcs',
        downDate: 'May 24, 2026',
        total: 750,
        paid: 375,
        balance: 375,
    },
];

let bcSelectedJO = null;
let bcMethod = 'cash';

function bcSearch(query) {
    const q = (query || '').trim().toLowerCase();
    document.getElementById('bc-empty-state').classList.add('hidden');
    document.getElementById('bc-not-found').classList.add('hidden');
    document.getElementById('bc-results').classList.add('hidden');
    document.getElementById('bc-payment-panel').classList.add('hidden');

    if (!q) {
        document.getElementById('bc-empty-state').classList.remove('hidden');
        return;
    }

    const matches = bcArData.filter(
        (r) =>
            r.jo.toLowerCase().includes(q) ||
            r.client.toLowerCase().includes(q),
    );

    if (matches.length === 0) {
        document.getElementById('bc-not-found').classList.remove('hidden');
        return;
    }

    document.getElementById('bc-result-count').textContent = matches.length;
    const tbody = document.getElementById('bc-results-tbody');
    tbody.innerHTML = matches
        .map(
            (r) => `
    <tr style="border-bottom: 1px solid var(--outline-variant); transition: background 0.15s;" onmouseover="this.style.background='var(--surface-container-low)'" onmouseout="this.style.background=''">
      <td style="padding: 12px 16px; font-family: var(--font-mono); font-size: 12px; color: var(--primary); font-weight: 700;">${r.jo}</td>
      <td style="padding: 12px 16px; font-weight: 600;">${r.client}</td>
      <td style="padding: 12px 16px; font-size: 13px; color: var(--on-surface-variant);">${r.product}</td>
      <td style="padding: 12px 16px; text-align: right; font-family: var(--font-mono); font-size: 13px;">₱${r.total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 12px 16px; text-align: right; font-family: var(--font-mono); font-size: 13px; color: var(--success);">₱${r.paid.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 12px 16px; text-align: right; font-family: var(--font-mono); font-size: 14px; font-weight: 800; color: var(--error);">₱${r.balance.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 12px 16px;">
        <button class="btn btn-primary btn-sm" onclick="bcSelectJO('${r.jo}')">
          <span class="material-symbols-outlined" style="font-size: 15px;">payments</span>Collect Balance
        </button>
      </td>
    </tr>
  `,
        )
        .join('');
    document.getElementById('bc-results').classList.remove('hidden');
}

function bcSearchDemo(query) {
    document.getElementById('bc-search-input').value = query;
    bcSearch(query);
}

function bcLoadDemo() {
    document.getElementById('bc-search-input').value = '';
    const q = '';
    document.getElementById('bc-empty-state').classList.add('hidden');
    document.getElementById('bc-not-found').classList.add('hidden');
    document.getElementById('bc-payment-panel').classList.add('hidden');

    document.getElementById('bc-result-count').textContent = bcArData.length;
    const tbody = document.getElementById('bc-results-tbody');
    tbody.innerHTML = bcArData
        .map(
            (r) => `
    <tr style="border-bottom: 1px solid var(--outline-variant); transition: background 0.15s;" onmouseover="this.style.background='var(--surface-container-low)'" onmouseout="this.style.background=''">
      <td style="padding: 12px 16px; font-family: var(--font-mono); font-size: 12px; color: var(--primary); font-weight: 700;">${r.jo}</td>
      <td style="padding: 12px 16px; font-weight: 600;">${r.client}</td>
      <td style="padding: 12px 16px; font-size: 13px; color: var(--on-surface-variant);">${r.product}</td>
      <td style="padding: 12px 16px; text-align: right; font-family: var(--font-mono); font-size: 13px;">₱${r.total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 12px 16px; text-align: right; font-family: var(--font-mono); font-size: 13px; color: var(--success);">₱${r.paid.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 12px 16px; text-align: right; font-family: var(--font-mono); font-size: 14px; font-weight: 800; color: var(--error);">₱${r.balance.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 12px 16px;">
        <button class="btn btn-primary btn-sm" onclick="bcSelectJO('${r.jo}')">
          <span class="material-symbols-outlined" style="font-size: 15px;">payments</span>Collect Balance
        </button>
      </td>
    </tr>
  `,
        )
        .join('');
    document.getElementById('bc-results').classList.remove('hidden');
}

function bcSelectJO(joNumber) {
    const data = bcArData.find((r) => r.jo === joNumber);
    if (!data) return;
    bcSelectedJO = data;

    // Populate payment panel
    document.getElementById('bc-jo-title').textContent = data.jo;
    document.getElementById('bc-jo-subtitle').textContent =
        `Outstanding Balance · ${data.client}`;
    document.getElementById('bc-client-name').textContent = data.client;
    document.getElementById('bc-jo-number-display').textContent = data.jo;
    document.getElementById('bc-product').textContent = data.product;
    document.getElementById('bc-down-date').textContent = data.downDate;

    const fmt = (v) =>
        '₱' + v.toLocaleString('en-PH', { minimumFractionDigits: 2 });
    document.getElementById('bc-total-display').textContent = fmt(data.total);
    document.getElementById('bc-paid-display').textContent = fmt(data.paid);
    document.getElementById('bc-balance-display').textContent = fmt(
        data.balance,
    );

    // Receipt
    document.getElementById('bc-receipt-jo-label').textContent = data.jo;
    document.getElementById('bc-rcpt-total').textContent = fmt(data.total);
    document.getElementById('bc-rcpt-paid').textContent = fmt(data.paid);
    document.getElementById('bc-rcpt-balance').textContent = fmt(data.balance);
    document.getElementById('bc-rcpt-tendered').textContent = '₱0.00';
    document.getElementById('bc-rcpt-change').textContent = '₱0.00';

    // Reset fields
    const ti = document.getElementById('bc-cash-tendered');
    if (ti) ti.value = '';
    const ci = document.getElementById('bc-cash-change');
    if (ci) ci.value = '';
    const ri = document.getElementById('bc-ref-number');
    if (ri) ri.value = '';

    // Hide success
    const sb = document.getElementById('bc-success-banner');
    if (sb) sb.classList.add('hidden');

    // Reset method
    bcSelectMethod('cash');

    document.getElementById('bc-results').classList.add('hidden');
    document.getElementById('bc-payment-panel').classList.remove('hidden');
    setTimeout(
        () =>
            document
                .getElementById('bc-payment-panel')
                .scrollIntoView({ behavior: 'smooth', block: 'start' }),
        100,
    );
}

function bcResetToSearch() {
    bcSelectedJO = null;
    document.getElementById('bc-payment-panel').classList.add('hidden');
    document.getElementById('bc-results').classList.add('hidden');
    document.getElementById('bc-empty-state').classList.remove('hidden');
    document.getElementById('bc-search-input').value = '';
    document.getElementById('bc-success-banner').classList.add('hidden');
}

function bcSelectMethod(method) {
    bcMethod = method;
    ['cash', 'gcash', 'maya', 'bank'].forEach((m) => {
        const el = document.getElementById('bc-method-' + m);
        if (!el) return;
        if (m === method) {
            el.style.borderColor = 'var(--primary)';
            el.style.background = 'var(--primary-fixed)';
        } else {
            el.style.borderColor = 'var(--outline-variant)';
            el.style.background = 'var(--surface-container-low)';
        }
    });

    const cashF = document.getElementById('bc-fields-cash');
    const digF = document.getElementById('bc-fields-digital');
    if (method === 'cash') {
        if (cashF) cashF.classList.remove('hidden');
        if (digF) digF.classList.add('hidden');
    } else {
        if (cashF) cashF.classList.add('hidden');
        if (digF) digF.classList.remove('hidden');
        const noteEl = document.getElementById('bc-digital-note-text');
        if (noteEl) {
            if (method === 'gcash')
                noteEl.textContent =
                    'Ask client to scan the GCash store QR or scan client QR. Verify via merchant app, then enter reference number.';
            else if (method === 'maya')
                noteEl.textContent =
                    'Ask client to scan the Maya store QR or scan client QR. Verify via merchant app, then enter reference number.';
            else
                noteEl.textContent =
                    'Verify bank transfer via bank app or screenshot before confirming.';
        }
    }
}

function bcCalcChange() {
    if (!bcSelectedJO) return;
    const balance = bcSelectedJO.balance;
    const tendered =
        parseFloat(document.getElementById('bc-cash-tendered').value) || 0;
    const change = Math.max(0, tendered - balance);
    const fmt = (v) =>
        '₱' + v.toLocaleString('en-PH', { minimumFractionDigits: 2 });

    const ci = document.getElementById('bc-cash-change');
    if (ci) ci.value = change.toFixed(2);

    document.getElementById('bc-rcpt-tendered').textContent = fmt(tendered);
    document.getElementById('bc-rcpt-change').textContent = fmt(change);
}

function bcConfirmPayment() {
    if (!bcSelectedJO) return;

    // Validate
    if (bcMethod === 'cash') {
        const t =
            parseFloat(document.getElementById('bc-cash-tendered').value) || 0;
        if (t < bcSelectedJO.balance) {
            alert(
                'Amount tendered is less than the outstanding balance of ₱' +
                    bcSelectedJO.balance.toFixed(2),
            );
            return;
        }
    } else {
        const ref = (
            document.getElementById('bc-ref-number').value || ''
        ).trim();
        if (!ref) {
            alert('Please enter the reference number.');
            return;
        }
    }

    // Disable confirm button
    const btn = document.querySelector("[onclick='bcConfirmPayment()']");
    if (btn) {
        btn.disabled = true;
        btn.style.opacity = '0.5';
    }

    // Show success
    document.getElementById('bc-success-jo').textContent = bcSelectedJO.jo;
    const sb = document.getElementById('bc-success-banner');
    sb.classList.remove('hidden');
    setTimeout(
        () => sb.scrollIntoView({ behavior: 'smooth', block: 'nearest' }),
        100,
    );

    // Log to transaction history
    if (typeof csLiveHistory !== 'undefined') {
        csLiveHistory.unshift({
            jo: bcSelectedJO.jo,
            customer: bcSelectedJO.client,
            type: 'Balance Collection',
            paid: bcSelectedJO.balance,
            balance: 0,
            status: 'Fully Paid',
            date:
                new Date().toLocaleDateString('en-PH', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                }) +
                ' ' +
                new Date().toLocaleTimeString('en-PH', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true,
                }),
        });
    }

    // Remove from AR data so it won't show again in this session
    const idx = bcArData.findIndex((r) => r.jo === bcSelectedJO.jo);
    if (idx !== -1) bcArData.splice(idx, 1);
}

// ══════════════════════════════════════════════════════════════════
//  ADMIN PORTAL — SYSTEM CONFIG PAGE
// ══════════════════════════════════════════════════════════════════

let scDirty = false;

function scTab(btn, sectionId) {
    // Deactivate all tabs
    document
        .querySelectorAll('.sc-tab')
        .forEach((t) => t.classList.remove('active'));
    // Hide all sections
    document
        .querySelectorAll('.sc-section')
        .forEach((s) => s.classList.add('hidden'));
    // Activate clicked tab and section
    btn.classList.add('active');
    const sec = document.getElementById(sectionId);
    if (sec) sec.classList.remove('hidden');
}

function scMarkDirty() {
    if (!scDirty) {
        scDirty = true;
        const bar = document.getElementById('sc-dirty-bar');
        if (bar) bar.classList.remove('hidden');
    }
}

function scUpdateWarning(input) {
    scMarkDirty();
    // Find the warning cell in the same row
    const row = input.closest('tr');
    if (!row) return;
    const warnCell = row.querySelector('.sc-warn-cell');
    if (!warnCell) return;
    const minVal = parseFloat(input.value) || 0;
    const threshold = Math.floor(minVal * 0.9);
    warnCell.textContent = threshold + ' DPI';
}

function scSaveAll() {
    scDirty = false;
    const bar = document.getElementById('sc-dirty-bar');
    if (bar) bar.classList.add('hidden');

    // Show success banner
    const banner = document.getElementById('sc-save-banner');
    if (banner) {
        banner.classList.remove('hidden');
        banner.style.animation = 'fadeIn 0.3s ease';
        setTimeout(() => {
            banner.style.animation = 'fadeOut 0.5s ease forwards';
            setTimeout(() => banner.classList.add('hidden'), 500);
        }, 3500);
    }

    // Log to audit trail (if adAuditLog exists)
    if (typeof adAuditLog !== 'undefined') {
        adAuditLog.unshift({
            ts: new Date().toLocaleString('en-PH', {
                dateStyle: 'medium',
                timeStyle: 'short',
            }),
            user: 'AD001',
            role: 'Admin',
            action: 'System Config updated — settings saved',
            target: 'System Config',
            detail: 'Admin saved changes to system configuration settings.',
        });
    }
}

function scDiscardChanges() {
    if (!confirm('Discard all unsaved changes?')) return;
    scDirty = false;
    const bar = document.getElementById('sc-dirty-bar');
    if (bar) bar.classList.add('hidden');
    // Reload the page state by re-navigating to config
    if (typeof AdRouter !== 'undefined') AdRouter.go('ad-config');
}

function scToggleCancelFeeType() {
    const type = document.getElementById('sc-cancel-type').value;
    const flatGroup = document.getElementById('sc-cancel-flat-group');
    const pctGroup = document.getElementById('sc-cancel-pct-group');
    if (type === 'flat') {
        flatGroup.classList.remove('hidden');
        pctGroup.classList.add('hidden');
    } else {
        flatGroup.classList.add('hidden');
        pctGroup.classList.remove('hidden');
    }
}

function scShowEmailReset() {
    const info = document.getElementById('sc-email-reset-info');
    if (info) info.classList.toggle('hidden');
}

// ══════════════════════════════════════════════════════════════════
//  ADMIN PORTAL — REPORTS PAGE
// ══════════════════════════════════════════════════════════════════

let rpCurrentReport = null;
let rpCurrentPreset = 'today';
let rpSubFilters = {}; // reportId -> selected sub-filter

// ── Preset date range logic ────────────────────────────────────────
function rpSetPreset(btn, preset) {
    document
        .querySelectorAll('.rp-preset')
        .forEach((b) => b.classList.remove('active'));
    btn.classList.add('active');
    rpCurrentPreset = preset;

    const customRange = document.getElementById('rp-custom-range');
    const label = document.getElementById('rp-range-label');

    const today = new Date(2026, 4, 26); // May 26 2026
    const fmt = (d) =>
        d.toLocaleDateString('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });

    if (preset === 'today') {
        customRange.classList.add('hidden');
        label.textContent = fmt(today);
    } else if (preset === 'week') {
        const mon = new Date(today);
        mon.setDate(today.getDate() - today.getDay() + 1);
        customRange.classList.add('hidden');
        label.textContent = `${fmt(mon)} – ${fmt(today)}`;
    } else if (preset === 'month') {
        const start = new Date(2026, 4, 1);
        customRange.classList.add('hidden');
        label.textContent = `${fmt(start)} – ${fmt(today)}`;
    } else if (preset === 'quarter') {
        const start = new Date(2026, 3, 1);
        customRange.classList.add('hidden');
        label.textContent = `${fmt(start)} – ${fmt(today)}`;
    } else if (preset === 'custom') {
        customRange.style.display = 'flex';
        customRange.classList.remove('hidden');
        rpMarkActive();
    }

    // Refresh counts/audit count if panel open
    rpUpdateAuditCount();
    if (rpCurrentReport) rpPreview(rpCurrentReport);
}

function rpMarkActive() {
    const from = document.getElementById('rp-date-from').value;
    const to = document.getElementById('rp-date-to').value;
    if (!from || !to) return;
    const fmtDate = (s) =>
        new Date(s).toLocaleDateString('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    document.getElementById('rp-range-label').textContent =
        `${fmtDate(from)} – ${fmtDate(to)}`;
}

function rpSubFilter(btn, reportId, filter) {
    const card = document.getElementById('rp-card-' + reportId);
    if (!card) return;
    card.querySelectorAll('.rp-sub-filter').forEach((b) =>
        b.classList.remove('active'),
    );
    btn.classList.add('active');
    rpSubFilters[reportId] = filter;
    if (rpCurrentReport === reportId) rpPreview(reportId);
}

function rpGetRangeLabel() {
    return document.getElementById('rp-range-label').textContent;
}

// ── Preview panel ─────────────────────────────────────────────────
const rpReportDefs = {
    sales: {
        title: 'Daily Sales Report',
        sub: 'Revenue summary by payment method and transaction',
        badge: 'Sales',
        cols: [
            'JO Number',
            'Client',
            'Product',
            'Payment Method',
            'Amount',
            'Status',
            'Time',
        ],
        rows: [
            [
                'JO-2026-0945',
                'Reyes, Maria',
                'Tarpaulin 4x8ft',
                'GCash',
                '₱1,200.00',
                'Fully Paid',
                '8:14 AM',
            ],
            [
                'JO-2026-0946',
                'Cruz, Pedro',
                'Stickers ×100',
                'Cash',
                '₱850.00',
                'Fully Paid',
                '8:42 AM',
            ],
            [
                'JO-2026-0947',
                'Acme Corp',
                'Banner 10ft',
                'Bank Transfer',
                '₱3,500.00',
                'Fully Paid',
                '9:05 AM',
            ],
            [
                'JO-2026-0948',
                'Santos, Ana',
                'ID Cards ×50',
                'Maya',
                '₱1,500.00',
                'Fully Paid',
                '9:31 AM',
            ],
            [
                'JO-2026-0949',
                'Lim, Robert',
                'Flyer ×500',
                'Cash',
                '₱2,800.00',
                'Partially Paid',
                '10:02 AM',
            ],
            [
                'JO-2026-0950',
                'Garcia, Lea',
                'Sintra Board 2x4',
                'GCash',
                '₱2,100.00',
                'Fully Paid',
                '10:28 AM',
            ],
            [
                'JO-2026-0951',
                'TechStart Inc',
                'Tarpaulin 6x10',
                'Bank Transfer',
                '₱5,000.00',
                'Fully Paid',
                '11:00 AM',
            ],
            [
                'JO-2026-0952',
                'Flores, Jun',
                'Stickers Roll',
                'Cash',
                '₱650.00',
                'Fully Paid',
                '11:35 AM',
            ],
            [
                'JO-2026-0953',
                'Globex Inc',
                'Poster ×200',
                'GCash',
                '₱3,200.00',
                'Fully Paid',
                '12:14 PM',
            ],
            [
                'JO-2026-0954',
                'Tan, Alice',
                'ID Cards ×20',
                'Maya',
                '₱600.00',
                'Fully Paid',
                '1:08 PM',
            ],
        ],
    },
    jo: {
        title: 'Job Order Summary',
        sub: 'All JOs with status breakdown',
        badge: 'Orders',
        cols: [
            'JO Number',
            'Client',
            'Type',
            'Service',
            'Status',
            'Urgency',
            'Date Created',
        ],
        rows: [
            [
                'JO-2026-0945',
                'Reyes, Maria',
                'Type A',
                'Tarpaulin Print',
                'Completed',
                'Normal',
                'May 26, 2026',
            ],
            [
                'JO-2026-0946',
                'Cruz, Pedro',
                'Type A',
                'Sticker Print',
                'Completed',
                'Rush',
                'May 26, 2026',
            ],
            [
                'JO-2026-0947',
                'Acme Corp',
                'Type B',
                'Banner + Design',
                'Active',
                'Normal',
                'May 26, 2026',
            ],
            [
                'JO-2026-0948',
                'Santos, Ana',
                'Type A',
                'ID Cards',
                'Completed',
                'Normal',
                'May 26, 2026',
            ],
            [
                'JO-2026-0949',
                'Lim, Robert',
                'Type B',
                'Flyer Design',
                'Pending',
                'Rush',
                'May 26, 2026',
            ],
            [
                'JO-2026-0950',
                'Garcia, Lea',
                'Type A',
                'Sintra Board',
                'Completed',
                'Normal',
                'May 26, 2026',
            ],
            [
                'JO-2026-0951',
                'TechStart Inc',
                'Type A',
                'Tarpaulin Print',
                'Active',
                'Rush',
                'May 26, 2026',
            ],
            [
                'JO-2026-0952',
                'Flores, Jun',
                'Type A',
                'Sticker Roll',
                'Completed',
                'Normal',
                'May 26, 2026',
            ],
            [
                'JO-2026-0953',
                'Globex Inc',
                'Type B',
                'Poster Design',
                'Printing',
                'Normal',
                'May 26, 2026',
            ],
            [
                'JO-2026-0954',
                'Tan, Alice',
                'Type A',
                'ID Cards',
                'Pending',
                'Normal',
                'May 26, 2026',
            ],
        ],
    },
    ar: {
        title: 'AR Aging Report',
        sub: 'Accounts receivable classified by aging bracket',
        badge: 'Receivables',
        cols: [
            'JO Number',
            'Client',
            'Entry Type',
            'Invoice Date',
            'Due Date',
            'Total',
            'Paid',
            'Balance',
            'Aging Bracket',
            'Status',
        ],
        rows: [
            [
                'JO-2026-0891',
                'Acme Corp',
                'Partial Payment',
                'Apr 11, 2026',
                'May 11, 2026',
                '₱45,000.00',
                '₱20,000.00',
                '₱25,000.00',
                'Current',
                'Pending',
            ],
            [
                'JO-2026-0842',
                'Globex Inc',
                'Partial Payment',
                'Apr 8, 2026',
                'May 8, 2026',
                '₱12,500.00',
                '₱0.00',
                '₱12,500.00',
                '30–60 Days',
                'Follow-up',
            ],
            [
                'JO-2026-0715',
                'Initech',
                'Partial Payment',
                'Feb 19, 2026',
                'Mar 19, 2026',
                '₱8,200.00',
                '₱1,000.00',
                '₱7,200.00',
                '90+ Days',
                'Collections',
            ],
            [
                'JO-2026-0799',
                'Wayne Ent.',
                'Partial Payment',
                'Mar 14, 2026',
                'Apr 14, 2026',
                '₱65,400.00',
                '₱30,000.00',
                '₱35,400.00',
                '60–90 Days',
                'Warning Sent',
            ],
            [
                'JO-2026-0923',
                'Doe, Jane A.',
                'Cancellation Fee',
                'May 18, 2026',
                'May 25, 2026',
                '₱150.00',
                '₱0.00',
                '₱150.00',
                'Current',
                'Pending',
            ],
        ],
    },
    prod: {
        title: 'Production Status Report',
        sub: 'Current queue with urgency and assigned staff',
        badge: 'Production',
        cols: [
            'JO Number',
            'Client',
            'Product',
            'Status',
            'Urgency',
            'Assigned To',
            'Deadline',
            'SLA',
        ],
        rows: [
            [
                'JO-2026-0947',
                'Acme Corp',
                'Banner 10ft',
                'For Production',
                'Normal',
                'PS001',
                '4:00 PM',
                'On Track',
            ],
            [
                'JO-2026-0951',
                'TechStart Inc',
                'Tarpaulin 6x10',
                'Printing',
                'Rush',
                'PS002',
                '2:00 PM',
                '⚠ At Risk',
            ],
            [
                'JO-2026-0949',
                'Lim, Robert',
                'Flyer ×500',
                'Printing',
                'Rush',
                'PS001',
                '3:30 PM',
                'On Track',
            ],
            [
                'JO-2026-0953',
                'Globex Inc',
                'Poster ×200',
                'Printing',
                'Normal',
                'PS002',
                '5:00 PM',
                'On Track',
            ],
            [
                'JO-2026-0955',
                'Mendoza, Jay',
                'Sticker Cut',
                'Quality Check',
                'Normal',
                'PS003',
                '3:00 PM',
                'On Track',
            ],
            [
                'JO-2026-0956',
                'Villaroel, Pia',
                'ID Cards ×30',
                'Quality Check',
                'Rush',
                'PS001',
                '2:30 PM',
                'On Track',
            ],
            [
                'JO-2026-0957',
                'BizHub Co.',
                'Sintra Signs ×4',
                'For Production',
                'Normal',
                'PS003',
                '5:30 PM',
                'On Track',
            ],
            [
                'JO-2026-0958',
                'Navarro, Ed',
                'Tarpaulin 3x5',
                'For Production',
                'Normal',
                'PS002',
                '6:00 PM',
                'On Track',
            ],
            [
                'JO-2026-0959',
                'Santos, Ana',
                'Banner 5ft',
                'Ready for Pickup',
                'Normal',
                'PS001',
                '12:00 PM',
                'Done',
            ],
            [
                'JO-2026-0960',
                'Cruz, Mark',
                'Flyer ×100',
                'Printing',
                'Rush',
                'PS003',
                '2:00 PM',
                '⚠ At Risk',
            ],
        ],
    },
    completed: {
        title: 'Completed Orders Report',
        sub: 'Released and fully closed job orders',
        badge: 'Orders',
        cols: [
            'JO Number',
            'Client',
            'Type',
            'Service',
            'Total',
            'Released By',
            'Release Date',
            'Turnaround',
        ],
        rows: [
            [
                'JO-2026-0945',
                'Reyes, Maria',
                'Type A',
                'Tarpaulin 4x8ft',
                '₱1,200.00',
                'FL001',
                '8:55 AM',
                '1.2 hrs',
            ],
            [
                'JO-2026-0946',
                'Cruz, Pedro',
                'Type A',
                'Sticker ×100',
                '₱850.00',
                'FL001',
                '9:20 AM',
                '0.8 hrs',
            ],
            [
                'JO-2026-0948',
                'Santos, Ana',
                'Type A',
                'ID Cards ×50',
                '₱1,500.00',
                'FL001',
                '10:10 AM',
                '1.5 hrs',
            ],
            [
                'JO-2026-0950',
                'Garcia, Lea',
                'Type A',
                'Sintra Board',
                '₱2,100.00',
                'FL001',
                '11:45 AM',
                '2.0 hrs',
            ],
            [
                'JO-2026-0952',
                'Flores, Jun',
                'Type A',
                'Sticker Roll',
                '₱650.00',
                'FL001',
                '12:30 PM',
                '1.0 hrs',
            ],
            [
                'JO-2026-0954',
                'Tan, Alice',
                'Type A',
                'ID Cards ×20',
                '₱600.00',
                'FL001',
                '1:50 PM',
                '0.7 hrs',
            ],
            [
                'JO-2026-0935',
                'Bautista, Ed',
                'Type B',
                'Flyer + Design',
                '₱3,400.00',
                'FL001',
                'May 25',
                '3.5 hrs',
            ],
            [
                'JO-2026-0930',
                'Villanueva',
                'Type B',
                'Banner + Design',
                '₱5,800.00',
                'FL001',
                'May 25',
                '4.2 hrs',
            ],
            [
                'JO-2026-0924',
                'Dela Cruz',
                'Type A',
                'Tarpaulin 8x12',
                '₱4,500.00',
                'FL001',
                'May 25',
                '2.8 hrs',
            ],
            [
                'JO-2026-0919',
                'Santiago',
                'Type B',
                'Sticker Design',
                '₱1,900.00',
                'FL001',
                'May 25',
                '1.9 hrs',
            ],
        ],
    },
    cancel: {
        title: 'Cancellation Report',
        sub: 'All cancelled JOs with and without cancellation fee',
        badge: 'Cancellations',
        cols: [
            'JO Number',
            'Client',
            'Artist',
            'Design Started?',
            'Cancellation Type',
            'Fee',
            'Payment Status',
            'Date',
        ],
        rows: [
            [
                'JO-2026-0901',
                'Aquino, Ben',
                'AR002',
                'No',
                'Cancelled — No Fee',
                '₱0.00',
                'N/A',
                'May 26, 2026',
            ],
            [
                'JO-2026-0902',
                'Ramos, Cris',
                'AR001',
                'Yes',
                'Cancelled with Fee',
                '₱150.00',
                'Paid',
                'May 25, 2026',
            ],
            [
                'JO-2026-0878',
                'Doe, Jane',
                'AR003',
                'Yes',
                'Cancelled with Fee',
                '₱150.00',
                'Unpaid (AR)',
                'May 18, 2026',
            ],
            [
                'JO-2026-0855',
                'Tan, Leo',
                'AR002',
                'No',
                'Cancelled — No Fee',
                '₱0.00',
                'N/A',
                'May 15, 2026',
            ],
            [
                'JO-2026-0831',
                'Yap, Maria',
                'AR004',
                'No',
                'Cancelled — No Fee',
                '₱0.00',
                'N/A',
                'May 10, 2026',
            ],
        ],
    },
    quality: {
        title: 'Quality Complaint / Store Error Report',
        sub: 'Reprints due to store fault, Owner-approved',
        badge: 'Quality',
        cols: [
            'JO Number',
            'Original JO',
            'Client',
            'Issue Description',
            'Approved By',
            'Reprint Cost',
            'Staff Accountable',
            'Date',
        ],
        rows: [
            [
                'JO-2026-0940-R',
                'JO-2026-0912',
                'Bautista Ent.',
                'Wrong color output — file printed in RGB instead of CMYK',
                'AD001',
                '₱800.00',
                'PS001',
                'May 25, 2026',
            ],
            [
                'JO-2026-0938-R',
                'JO-2026-0905',
                'Cruz, Pedro',
                'Size mismatch — printed 3x5 instead of approved 4x6',
                'AD001',
                '₱400.00',
                'PS002',
                'May 23, 2026',
            ],
        ],
    },
    fileval: {
        title: 'File Validation Failure Log',
        sub: 'All rejected or flagged files during order upload',
        badge: 'Validation',
        cols: [
            'JO Number',
            'Client',
            'Filename',
            'Severity',
            'Failure Reason',
            'Resolution',
            'Frontline',
            'Time',
        ],
        rows: [
            [
                'JO-2026-0960',
                'Cruz, Mark',
                'flyer_v2.docx',
                '🔴 Hard Rejected',
                'Invalid format (DOCX)',
                'Client resubmitted PDF',
                'FL001',
                '7:55 AM',
            ],
            [
                'JO-2026-0961',
                'Mendoza, Jay',
                'logo_final.jpg',
                '🟡 Warning',
                'Low DPI (72dpi, min 150)',
                'Client proceeded',
                'FL001',
                '8:10 AM',
            ],
            [
                'JO-2026-0962',
                'Reyes, Cora',
                'tarp_design.png',
                '🟡 Warning',
                'RGB color mode',
                'Client proceeded',
                'FL002',
                '9:05 AM',
            ],
            [
                'JO-2026-0963',
                'Tan, Eric',
                'banner.gif',
                '🔴 Hard Rejected',
                'Invalid format (GIF)',
                'Client cancelled',
                'FL001',
                '9:33 AM',
            ],
            [
                'JO-2026-0964',
                'Santos, Gab',
                'flyer_hi.pdf',
                '🟡 Warning',
                'No bleed area found',
                'Client resubmitted',
                'FL002',
                '10:14 AM',
            ],
            [
                'JO-2026-0965',
                'Lim, Ray',
                'sticker_art.psd',
                '🟡 Warning',
                'Aspect ratio mismatch ±8%',
                'Client adjusted specs',
                'FL001',
                '10:47 AM',
            ],
            [
                'JO-2026-0966',
                'Flores, Tess',
                'id_photo.bmp',
                '🔴 Hard Rejected',
                'Invalid format (BMP)',
                'Client resubmitted JPG',
                'FL001',
                '11:20 AM',
            ],
            [
                'JO-2026-0967',
                'Garcia, Leo',
                'tarp_v3.pdf',
                '🟡 Warning',
                'File size 492MB (near 500MB limit)',
                'Client proceeded',
                'FL002',
                '12:08 PM',
            ],
        ],
    },
    audit: {
        title: 'Audit Log Report',
        sub: 'System-generated activity trail — read-only, no edits or deletions permitted',
        badge: 'Audit',
        cols: ['Timestamp', 'Staff ID', 'Role', 'Action', 'Target', 'Detail'],
        rows: [], // dynamically filled from adAuditLog
    },
};

function rpPreview(reportId) {
    rpCurrentReport = reportId;
    const def = rpReportDefs[reportId];
    if (!def) return;

    document.getElementById('rp-panel-title').textContent = def.title;
    document.getElementById('rp-panel-sub').textContent = def.sub;
    document.getElementById('rp-panel-range').textContent = rpGetRangeLabel();
    document.getElementById('rp-panel-generated').textContent =
        new Date().toLocaleString('en-PH', {
            dateStyle: 'medium',
            timeStyle: 'short',
        });

    // Build rows — audit pulls from live log
    let rows = def.rows;
    if (
        reportId === 'audit' &&
        typeof adAuditLog !== 'undefined' &&
        adAuditLog.length
    ) {
        rows = adAuditLog
            .slice(0, 10)
            .map((e) => [
                e.ts,
                e.user,
                e.role,
                e.action,
                e.target || '—',
                e.detail || '—',
            ]);
    }

    // Sub-filter for JO report
    const sub = rpSubFilters[reportId] || 'all';
    if (reportId === 'jo' && sub !== 'all') {
        const statusMap = {
            pending: 'Pending',
            active: 'Active',
            completed: 'Completed',
            cancelled: 'Cancelled',
        };
        rows = rows.filter(
            (r) =>
                r[4] &&
                r[4]
                    .toLowerCase()
                    .includes(statusMap[sub]?.toLowerCase() || ''),
        );
    }
    if (reportId === 'cancel' && sub !== 'all') {
        if (sub === 'nofee') rows = rows.filter((r) => r[3] === 'No');
        if (sub === 'withfee') rows = rows.filter((r) => r[3] === 'Yes');
    }
    if (reportId === 'fileval' && sub !== 'all') {
        if (sub === 'rejected')
            rows = rows.filter((r) => r[3] && r[3].includes('Hard'));
        if (sub === 'warned')
            rows = rows.filter((r) => r[3] && r[3].includes('Warning'));
    }

    // Render thead
    const thead = document.getElementById('rp-preview-thead');
    thead.innerHTML =
        '<tr>' + def.cols.map((c) => `<th>${c}</th>`).join('') + '</tr>';

    // Render tbody
    const tbody = document.getElementById('rp-preview-tbody');
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="${def.cols.length}" style="text-align:center;padding:24px;color:var(--on-surface-variant);">No records found for the selected date range and filter.</td></tr>`;
    } else {
        tbody.innerHTML = rows
            .slice(0, 10)
            .map(
                (row) =>
                    '<tr>' +
                    row
                        .map((cell, i) => {
                            // Color-code status/severity columns
                            let style = '';
                            const c = String(cell);
                            if (
                                c === 'Completed' ||
                                c === 'Paid' ||
                                c === 'On Track' ||
                                c === 'Done'
                            )
                                style = 'color:var(--success);font-weight:700;';
                            else if (
                                c.includes('Pending') ||
                                c.includes('Printing') ||
                                c.includes('Active') ||
                                c.includes('Follow-up')
                            )
                                style = 'color:var(--primary);font-weight:600;';
                            else if (
                                c.includes('Warning') ||
                                c.includes('Rush') ||
                                c.includes('At Risk') ||
                                c.includes('30–60') ||
                                c.includes('60–90')
                            )
                                style = 'color:var(--warning);font-weight:600;';
                            else if (
                                c.includes('90+') ||
                                c === 'Collections' ||
                                c.includes('Hard') ||
                                c.includes('Unpaid')
                            )
                                style = 'color:var(--error);font-weight:700;';
                            else if (c.includes('₱'))
                                style =
                                    'font-family:var(--font-mono);font-size:12px;';
                            return `<td style="${style}">${cell}</td>`;
                        })
                        .join('') +
                    '</tr>',
            )
            .join('');
    }

    const panel = document.getElementById('rp-preview-panel');
    panel.classList.remove('hidden');
    setTimeout(
        () => panel.scrollIntoView({ behavior: 'smooth', block: 'start' }),
        80,
    );
}

function rpClosePreview() {
    rpCurrentReport = null;
    document.getElementById('rp-preview-panel').classList.add('hidden');
}

function rpExport(reportId, format) {
    const def = rpReportDefs[reportId];
    const label = def ? def.title : reportId;
    const ext = format === 'pdf' ? 'PDF' : 'Excel (.xlsx)';
    const msg = document.getElementById('rp-export-msg');
    const toast = document.getElementById('rp-export-toast');
    if (!msg || !toast) return;
    msg.textContent = `Exporting "${label}" as ${ext}…`;
    toast.classList.remove('hidden');
    toast.style.animation = 'slideInRight 0.3s ease';
    setTimeout(() => {
        msg.textContent = `"${label}" exported successfully!`;
        setTimeout(() => {
            toast.style.animation = 'fadeOut 0.5s ease forwards';
            setTimeout(() => toast.classList.add('hidden'), 500);
        }, 2200);
    }, 1200);

    // Log to audit trail
    if (typeof adAuditLog !== 'undefined') {
        adAuditLog.unshift({
            ts: new Date().toLocaleString('en-PH', {
                dateStyle: 'medium',
                timeStyle: 'short',
            }),
            user: 'AD001',
            role: 'Admin',
            action: `Report exported — ${label} (${ext})`,
            target: 'Reports',
            detail: `Date range: ${rpGetRangeLabel()}`,
        });
        rpUpdateAuditCount();
    }
}

function rpUpdateAuditCount() {
    const count = typeof adAuditLog !== 'undefined' ? adAuditLog.length : 0;
    const el = document.getElementById('rp-audit-count');
    const el2 = document.getElementById('rp-audit-total');
    if (el) el.textContent = count + ' entries';
    if (el2) el2.textContent = count;
}

// Called when Reports page is shown
function rpInitPage() {
    rpUpdateAuditCount();
}
