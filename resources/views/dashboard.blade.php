<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PolicyFlow | Fault-Tolerant Insurance Issuance & Reconciliation Engine</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-base: #0b0f19;
            --bg-surface: #111827;
            --bg-surface-elevated: #1f2937;
            --border-subtle: rgba(255, 255, 255, 0.08);
            --border-active: rgba(99, 102, 241, 0.4);
            --text-primary: #f9fafb;
            --text-secondary: #9ca3af;
            --text-muted: #6b7280;
            --brand-primary: #6366f1;
            --brand-primary-hover: #4f46e5;
            --brand-accent: #06b6d4;
            --status-issued: #10b981;
            --status-issued-bg: rgba(16, 185, 129, 0.12);
            --status-pending: #f59e0b;
            --status-pending-bg: rgba(245, 158, 11, 0.12);
            --status-failed: #ef4444;
            --status-failed-bg: rgba(239, 68, 68, 0.12);
            --status-reconcile: #8b5cf6;
            --status-reconcile-bg: rgba(139, 92, 246, 0.12);
            --status-neutral: #9ca3af;
            --status-neutral-bg: rgba(156, 163, 175, 0.12);
            --shadow-card: 0 10px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.5);
            --font-sans: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-sans);
            background-color: var(--bg-base);
            color: var(--text-primary);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        /* Subtle Background Glow */
        .bg-glow {
            position: fixed;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 1000px;
            height: 400px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, rgba(6, 182, 212, 0.05) 45%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .container {
            max-width: 1380px;
            margin: 0 auto;
            padding: 2rem;
            position: relative;
            z-index: 1;
            width: 100%;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border-subtle);
            margin-bottom: 2rem;
        }

        .brand-cluster {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .brand-logo {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #6366f1 0%, #06b6d4 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
        }

        .brand-logo svg {
            width: 26px;
            height: 26px;
            fill: white;
        }

        .brand-text h1 {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.025em;
            background: linear-gradient(to right, #ffffff, #cbd5e1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .brand-text p {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn {
            font-family: var(--font-sans);
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0.625rem 1.125rem;
            border-radius: 8px;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-primary-hover));
            color: white;
            box-shadow: 0 2px 10px rgba(99, 102, 241, 0.3);
        }

        .btn-primary:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: var(--bg-surface-elevated);
            color: var(--text-primary);
            border-color: var(--border-subtle);
        }

        .btn-secondary:hover {
            background-color: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .btn-outline-reconcile {
            background: rgba(139, 92, 246, 0.1);
            color: #c4b5fd;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        .btn-outline-reconcile:hover {
            background: rgba(139, 92, 246, 0.2);
            color: white;
        }

        /* Metrics Bar */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .metric-card {
            background-color: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: var(--shadow-card);
            transition: border-color 0.2s;
        }

        .metric-card:hover {
            border-color: var(--border-active);
        }

        .metric-title {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .metric-value {
            font-size: 1.85rem;
            font-weight: 800;
            color: var(--text-primary);
            font-family: var(--font-mono);
        }

        .metric-sub {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        /* Filter & Control Bar */
        .controls-card {
            background-color: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .tab-btn {
            background: transparent;
            border: 1px solid transparent;
            color: var(--text-secondary);
            font-family: var(--font-sans);
            font-size: 0.825rem;
            font-weight: 600;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .tab-btn:hover {
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.05);
        }

        .tab-btn.active {
            background: var(--bg-surface-elevated);
            color: var(--text-primary);
            border-color: var(--border-subtle);
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background-color: var(--bg-base);
            border: 1px solid var(--border-subtle);
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            min-width: 280px;
        }

        .search-box input {
            background: transparent;
            border: none;
            color: var(--text-primary);
            font-family: var(--font-sans);
            font-size: 0.85rem;
            width: 100%;
            outline: none;
        }

        /* Data Table */
        .table-container {
            background-color: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            background-color: rgba(255, 255, 255, 0.02);
            color: var(--text-secondary);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.875rem 1.25rem;
            border-bottom: 1px solid var(--border-subtle);
        }

        td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-subtle);
            font-size: 0.875rem;
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background-color: rgba(255, 255, 255, 0.015);
        }

        .req-id {
            font-family: var(--font-mono);
            font-weight: 600;
            color: var(--text-primary);
            display: block;
        }

        .agent-badge {
            font-family: var(--font-mono);
            font-size: 0.725rem;
            color: var(--brand-accent);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.725rem;
            font-weight: 700;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .badge-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .badge-issued {
            background-color: var(--status-issued-bg);
            color: var(--status-issued);
        }
        .badge-issued .badge-dot { background-color: var(--status-issued); }

        .badge-pending {
            background-color: var(--status-pending-bg);
            color: var(--status-pending);
        }
        .badge-pending .badge-dot { background-color: var(--status-pending); }

        .badge-failed {
            background-color: var(--status-failed-bg);
            color: var(--status-failed);
        }
        .badge-failed .badge-dot { background-color: var(--status-failed); }

        .badge-reconcile {
            background-color: var(--status-reconcile-bg);
            color: var(--status-reconcile);
        }
        .badge-reconcile .badge-dot { background-color: var(--status-reconcile); }

        .badge-neutral {
            background-color: var(--status-neutral-bg);
            color: var(--status-neutral);
        }
        .badge-neutral .badge-dot { background-color: var(--status-neutral); }

        .policy-num {
            font-family: var(--font-mono);
            font-size: 0.8rem;
            color: #a7f3d0;
            background: rgba(16, 185, 129, 0.08);
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .premium-val {
            font-family: var(--font-mono);
            font-weight: 700;
            color: var(--text-primary);
        }

        .action-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-xs {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 5px;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
        }

        .modal-overlay.open {
            opacity: 1;
            pointer-events: auto;
        }

        .modal-card {
            background-color: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 16px;
            width: 100%;
            max-width: 650px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.75);
            padding: 1.75rem;
            transform: scale(0.95);
            transition: transform 0.2s ease;
        }

        .modal-overlay.open .modal-card {
            transform: scale(1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-subtle);
        }

        .modal-title {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1.5rem;
            line-height: 1;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group.full {
            grid-column: span 2;
        }

        label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            margin-bottom: 0.35rem;
        }

        input, select, textarea {
            width: 100%;
            background-color: var(--bg-base);
            border: 1px solid var(--border-subtle);
            border-radius: 8px;
            padding: 0.6rem 0.8rem;
            color: var(--text-primary);
            font-family: var(--font-sans);
            font-size: 0.875rem;
            outline: none;
            transition: border-color 0.2s;
        }

        input:focus, select:focus {
            border-color: var(--brand-primary);
        }

        /* Timeline Audit Trail */
        .timeline {
            position: relative;
            padding-left: 1.5rem;
            margin-top: 1rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 5px;
            top: 5px;
            bottom: 5px;
            width: 2px;
            background: var(--border-subtle);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 1.25rem;
        }

        .timeline-dot {
            position: absolute;
            left: -1.5rem;
            top: 4px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--brand-primary);
            border: 2px solid var(--bg-surface);
        }

        .timeline-time {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-family: var(--font-mono);
        }

        .timeline-content {
            font-size: 0.825rem;
            color: var(--text-secondary);
            margin-top: 0.2rem;
        }

        /* Toast notification */
        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            padding: 0.875rem 1.25rem;
            background-color: var(--bg-surface-elevated);
            color: white;
            border-radius: 10px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
            border: 1px solid var(--border-subtle);
            z-index: 1000;
            display: none;
            font-size: 0.875rem;
            font-weight: 500;
            align-items: center;
            gap: 0.75rem;
        }

        .toast.show {
            display: flex;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="bg-glow"></div>

    <div class="container">
        <!-- Header -->
        <header>
            <div class="brand-cluster">
                <div class="brand-logo">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 2L4 5v6.09c0 5.05 3.41 9.76 8 10.91 4.59-1.15 8-5.86 8-10.91V5l-8-3zm1 14.5h-2v-2h2v2zm0-4h-2V7h2v5.5z"/>
                    </svg>
                </div>
                <div class="brand-text">
                    <h1>POLICYFLOW</h1>
                    <p>Fault-Tolerant Insurance Issuance & Reconciliation Engine</p>
                </div>
            </div>

            <div class="header-actions">
                <button id="btn-open-intake" class="btn btn-primary" onclick="openIntakeModal()">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M8 0a1 1 0 0 1 1 1v6h6a1 1 0 1 1 0 2H9v6a1 1 0 1 1-2 0V9H1a1 1 0 0 1 0-2h6V1a1 1 0 0 1 1-1z"/>
                    </svg>
                    New Policy Request
                </button>
                <button id="btn-batch-reconcile" class="btn btn-outline-reconcile" onclick="triggerBatchReconcile()">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41zm-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9z"/>
                        <path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5.002 5.002 0 0 0 8 3zM3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9H3.1z"/>
                    </svg>
                    Reconcile Pending
                </button>
                <button id="btn-open-bulk" class="btn btn-secondary" onclick="openBulkModal()">
                    Bulk CSV
                </button>
            </div>
        </header>

        <!-- Executive Metrics -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-title">Total Requests</div>
                <div class="metric-value" id="metric-total">0</div>
                <div class="metric-sub">Processed through engine</div>
            </div>
            <div class="metric-card">
                <div class="metric-title">Issued Policies</div>
                <div class="metric-value" style="color: var(--status-issued);" id="metric-issued">0</div>
                <div class="metric-sub">Confirmed with carrier</div>
            </div>
            <div class="metric-card">
                <div class="metric-title">Pending Confirmation</div>
                <div class="metric-value" style="color: var(--status-pending);" id="metric-pending">0</div>
                <div class="metric-sub">Timeouts / In-flight</div>
            </div>
            <div class="metric-card">
                <div class="metric-title">Failed / Needs Review</div>
                <div class="metric-value" style="color: var(--status-failed);" id="metric-failed">0</div>
                <div class="metric-sub">Eligible for safe retry</div>
            </div>
        </div>

        <!-- Filter & Control Bar -->
        <div class="controls-card">
            <div class="filter-tabs">
                <button class="tab-btn active" data-filter="" onclick="setFilter(this, '')">All Requests</button>
                <button class="tab-btn" data-filter="ISSUED" onclick="setFilter(this, 'ISSUED')">Issued</button>
                <button class="tab-btn" data-filter="PENDING_CONFIRMATION" onclick="setFilter(this, 'PENDING_CONFIRMATION')">Pending</button>
                <button class="tab-btn" data-filter="NEEDS_RECONCILIATION" onclick="setFilter(this, 'NEEDS_RECONCILIATION')">Needs Reconciliation</button>
                <button class="tab-btn" data-filter="FAILED" onclick="setFilter(this, 'FAILED')">Failed</button>
            </div>

            <div class="search-box">
                <svg width="14" height="14" fill="var(--text-muted)" viewBox="0 0 16 16">
                    <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                </svg>
                <input type="text" id="input-search" placeholder="Filter by Request ID or Agent..." oninput="handleSearch(this.value)">
            </div>
        </div>

        <!-- Data Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Request / Agent</th>
                        <th>Customer</th>
                        <th>Trip & Plan</th>
                        <th>Calculated Premium</th>
                        <th>Status</th>
                        <th>Policy Number</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="policy-table-body">
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                            Loading policy requests...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal: New Policy Intake -->
    <div id="modal-intake" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h2 class="modal-title">New Policy Request</h2>
                <button class="modal-close" onclick="closeModal('modal-intake')">&times;</button>
            </div>
            <form id="form-intake" onsubmit="submitIntake(event)">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="intake-client-id">Client Request ID</label>
                        <input type="text" id="intake-client-id" required value="AGT-2026-{{ rand(1000, 9999) }}">
                    </div>
                    <div class="form-group">
                        <label for="intake-agent-id">Agent ID</label>
                        <input type="text" id="intake-agent-id" required value="AGENT-17">
                    </div>

                    <div class="form-group full">
                        <label for="intake-customer">Customer Full Name</label>
                        <input type="text" id="intake-customer" required value="Jane Doe">
                    </div>

                    <div class="form-group">
                        <label for="intake-dob">Date of Birth</label>
                        <input type="date" id="intake-dob" required value="1998-04-10">
                    </div>
                    <div class="form-group">
                        <label for="intake-destination">Destination</label>
                        <select id="intake-destination" required>
                            <option value="INTERNATIONAL" selected>INTERNATIONAL (1.80x)</option>
                            <option value="DOMESTIC">DOMESTIC (1.00x)</option>
                            <option value="SCHENGEN">SCHENGEN (1.50x)</option>
                            <option value="US_CANADA">US / CANADA (2.20x)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="intake-start">Trip Start Date</label>
                        <input type="date" id="intake-start" required value="2026-11-10">
                    </div>
                    <div class="form-group">
                        <label for="intake-end">Trip End Date</label>
                        <input type="date" id="intake-end" required value="2026-11-14">
                    </div>

                    <div class="form-group">
                        <label for="intake-plan">Plan Code</label>
                        <select id="intake-plan" required>
                            <option value="PLUS" selected>PLUS (₹20/day)</option>
                            <option value="BASIC">BASIC (₹10/day)</option>
                            <option value="COMPREHENSIVE">COMPREHENSIVE (₹35/day)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="intake-sum">Sum Insured (Coverage)</label>
                        <select id="intake-sum" required>
                            <option value="300000" selected>₹300,000 (1.25x)</option>
                            <option value="100000">₹100,000 (1.00x)</option>
                            <option value="500000">₹500,000 (1.50x)</option>
                            <option value="1000000">₹1,000,000 (2.00x)</option>
                        </select>
                    </div>

                    <div class="form-group full">
                        <label for="intake-scenario">Mock Insurer Scenario (Fault Simulation)</label>
                        <select id="intake-scenario" style="border-color: var(--brand-accent);">
                            <option value="SUCCESS" selected>SUCCESS - Instant issuance & policy number</option>
                            <option value="TIMEOUT">TIMEOUT - Insurer issues internally, but socket times out</option>
                            <option value="TEMP_ERROR">TEMP_ERROR - Downstream 503 error (retriable)</option>
                            <option value="REJECTED">REJECTED - Underwriting declined (422)</option>
                            <option value="SLOW_RESPONSE">SLOW_RESPONSE - Queued for async verification</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-intake')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Audit Trail & Details -->
    <div id="modal-audit" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title" id="audit-title">Policy Audit Trail</h2>
                    <p style="font-size: 0.8rem; color: var(--text-muted); font-family: var(--font-mono);" id="audit-sub">AGT-2026-0001</p>
                </div>
                <button class="modal-close" onclick="closeModal('modal-audit')">&times;</button>
            </div>
            
            <div id="audit-pricing-box" style="background: var(--bg-surface-elevated); padding: 1rem; border-radius: 8px; margin-bottom: 1.25rem;">
                <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 0.5rem; font-weight: 700;">Immutable Pricing Snapshot</div>
                <div id="audit-pricing-details" style="font-size: 0.85rem; font-family: var(--font-mono); color: var(--text-primary); line-height: 1.6;"></div>
            </div>

            <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">State Transition History</div>
            <div class="timeline" id="audit-timeline"></div>

            <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); margin-top: 1.5rem; margin-bottom: 0.5rem;">Carrier Issuance Attempts</div>
            <div id="audit-attempts" style="font-size: 0.825rem; font-family: var(--font-mono);"></div>
        </div>
    </div>

    <!-- Modal: Bulk CSV -->
    <div id="modal-bulk" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h2 class="modal-title">Bulk CSV Policy Intake</h2>
                <button class="modal-close" onclick="closeModal('modal-bulk')">&times;</button>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">
                Upload a CSV file with policy requests. The engine validates every row, prevents duplicate entries atomically, and reports row-level outcomes.
            </p>
            <form id="form-bulk" onsubmit="submitBulkCsv(event)">
                <div class="form-group">
                    <label for="bulk-file">Select CSV File</label>
                    <input type="file" id="bulk-file" accept=".csv" required>
                </div>
                <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-bulk')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Process Bulk CSV</button>
                </div>
            </form>
            <div id="bulk-results" style="margin-top: 1rem;"></div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast">
        <span id="toast-message">Notification message</span>
    </div>

    <script>
        let allPolicies = [];
        let currentFilter = '';
        let currentSearch = '';

        document.addEventListener('DOMContentLoaded', () => {
            fetchPolicies();
        });

        async function fetchPolicies() {
            try {
                const res = await fetch('/api/policy-requests?per_page=100');
                const json = await res.json();
                if (json.success) {
                    allPolicies = json.data;
                    updateMetrics(allPolicies);
                    renderTable();
                }
            } catch (err) {
                console.error('Failed to load policies:', err);
            }
        }

        function updateMetrics(policies) {
            document.getElementById('metric-total').textContent = policies.length;
            document.getElementById('metric-issued').textContent = policies.filter(p => p.status === 'ISSUED').length;
            document.getElementById('metric-pending').textContent = policies.filter(p => p.status === 'PENDING_CONFIRMATION').length;
            document.getElementById('metric-failed').textContent = policies.filter(p => p.status === 'FAILED' || p.status === 'NEEDS_RECONCILIATION').length;
        }

        function renderTable() {
            const tbody = document.getElementById('policy-table-body');
            let filtered = allPolicies;

            if (currentFilter) {
                filtered = filtered.filter(p => p.status === currentFilter);
            }

            if (currentSearch) {
                const q = currentSearch.toLowerCase();
                filtered = filtered.filter(p => 
                    p.client_request_id.toLowerCase().includes(q) ||
                    p.agent_id.toLowerCase().includes(q) ||
                    p.customer_name.toLowerCase().includes(q) ||
                    (p.policy_number && p.policy_number.toLowerCase().includes(q))
                );
            }

            if (filtered.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">No matching policy requests found.</td></tr>`;
                return;
            }

            tbody.innerHTML = filtered.map(p => {
                const badgeClass = getBadgeClass(p.status);
                return `
                    <tr>
                        <td>
                            <span class="req-id">${escapeHtml(p.client_request_id)}</span>
                            <span class="agent-badge">${escapeHtml(p.agent_id)}</span>
                        </td>
                        <td>
                            <strong>${escapeHtml(p.customer_name)}</strong>
                        </td>
                        <td>
                            <div>${escapeHtml(p.plan_code)} &bull; ${escapeHtml(p.destination)}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">${p.trip_start} to ${p.trip_end} (${p.travel_days}d)</div>
                        </td>
                        <td>
                            <span class="premium-val">₹${parseFloat(p.premium).toFixed(2)}</span>
                        </td>
                        <td>
                            <span class="badge ${badgeClass}">
                                <span class="badge-dot"></span>
                                ${p.status}
                            </span>
                        </td>
                        <td>
                            ${p.policy_number 
                                ? `<span class="policy-num">${escapeHtml(p.policy_number)}</span>` 
                                : `<span style="color: var(--text-muted); font-size: 0.8rem;">—</span>`
                            }
                        </td>
                        <td>
                            <div class="action-group">
                                <button class="btn btn-secondary btn-xs" onclick="viewAuditTrail(${p.id})">Audit</button>
                                ${['PENDING_CONFIRMATION', 'NEEDS_RECONCILIATION'].includes(p.status) 
                                    ? `<button class="btn btn-outline-reconcile btn-xs" onclick="reconcilePolicy(${p.id})">Reconcile</button>` 
                                    : ''
                                }
                                ${['FAILED', 'NEEDS_RECONCILIATION'].includes(p.status) 
                                    ? `<button class="btn btn-primary btn-xs" onclick="retryPolicy(${p.id})">Retry</button>` 
                                    : ''
                                }
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function getBadgeClass(status) {
            switch (status) {
                case 'ISSUED': return 'badge-issued';
                case 'PENDING_CONFIRMATION': return 'badge-pending';
                case 'FAILED': return 'badge-failed';
                case 'NEEDS_RECONCILIATION': return 'badge-reconcile';
                default: return 'badge-neutral';
            }
        }

        function setFilter(btn, status) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = status;
            renderTable();
        }

        function handleSearch(val) {
            currentSearch = val;
            renderTable();
        }

        async function submitIntake(e) {
            e.preventDefault();
            const payload = {
                client_request_id: document.getElementById('intake-client-id').value,
                agent_id: document.getElementById('intake-agent-id').value,
                customer_name: document.getElementById('intake-customer').value,
                date_of_birth: document.getElementById('intake-dob').value,
                destination: document.getElementById('intake-destination').value,
                trip_start: document.getElementById('intake-start').value,
                trip_end: document.getElementById('intake-end').value,
                plan_code: document.getElementById('intake-plan').value,
                sum_insured: parseFloat(document.getElementById('intake-sum').value),
                mock_scenario: document.getElementById('intake-scenario').value,
            };

            try {
                const res = await fetch('/api/policy-requests', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                closeModal('modal-intake');

                if (res.ok) {
                    showToast(data.is_duplicate 
                        ? `[Safe Duplicate] Request returned existing Policy #${data.data.id}` 
                        : `Policy created successfully: Status ${data.data.status}`);
                    fetchPolicies();
                } else {
                    showToast(data.message || 'Error creating policy');
                }
            } catch (err) {
                showToast('Network error submitting request');
            }
        }

        async function viewAuditTrail(id) {
            try {
                const res = await fetch(`/api/policy-requests/${id}`);
                const json = await res.json();
                if (!json.success) return;

                const p = json.data;
                document.getElementById('audit-title').textContent = `Policy #${p.id} Details`;
                document.getElementById('audit-sub').textContent = `${p.client_request_id} • Agent: ${p.agent_id} • Status: ${p.status}`;

                const ps = p.pricing_snapshot;
                document.getElementById('audit-pricing-details').innerHTML = `
                    <div>Base Daily Rate: ₹${ps.base_daily_rate.toFixed(2)} &times; Travel Days: ${ps.travel_days}</div>
                    <div>&times; Age Multiplier: ${ps.age_multiplier.toFixed(2)} &times; Dest: ${ps.destination_multiplier.toFixed(2)} &times; Coverage: ${ps.coverage_multiplier.toFixed(2)}</div>
                    <div style="margin-top: 0.35rem; color: #34d399; font-weight: 700;">= Total Immutable Premium: ₹${ps.premium.toFixed(2)}</div>
                `;

                const timeline = document.getElementById('audit-timeline');
                timeline.innerHTML = p.audit_trail.map(item => `
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-time">${new Date(item.created_at).toLocaleTimeString()} - ${new Date(item.created_at).toLocaleDateString()}</div>
                        <div class="timeline-content">
                            <strong>${item.from_status || 'INIT'} &rarr; ${item.to_status}</strong>: ${escapeHtml(item.reason)}
                        </div>
                    </div>
                `).join('');

                const attempts = document.getElementById('audit-attempts');
                attempts.innerHTML = p.issuance_attempts.length ? p.issuance_attempts.map(att => `
                    <div style="background: var(--bg-base); padding: 0.6rem; border-radius: 6px; margin-bottom: 0.5rem; border: 1px solid var(--border-subtle);">
                        <div>Attempt #${att.attempt_number} &bull; HTTP ${att.http_status || 'TIMEOUT'} &bull; Outcome: ${att.outcome}</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">${att.idempotency_key}</div>
                        ${att.error_message ? `<div style="color: var(--status-failed); font-size: 0.75rem; margin-top: 0.2rem;">${escapeHtml(att.error_message)}</div>` : ''}
                    </div>
                `).join('') : '<div style="color: var(--text-muted);">No attempts logged.</div>';

                openModal('modal-audit');
            } catch (err) {
                showToast('Failed to load audit trail');
            }
        }

        async function reconcilePolicy(id) {
            try {
                const res = await fetch(`/api/policy-requests/${id}/reconcile`, { method: 'POST' });
                const json = await res.json();
                showToast(json.message);
                fetchPolicies();
            } catch (err) {
                showToast('Error executing reconciliation');
            }
        }

        async function retryPolicy(id) {
            try {
                const res = await fetch(`/api/policy-requests/${id}/retry`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ mock_scenario: 'SUCCESS' }),
                });
                const json = await res.json();
                showToast(json.message);
                fetchPolicies();
            } catch (err) {
                showToast('Error retrying policy');
            }
        }

        async function triggerBatchReconcile() {
            try {
                const res = await fetch('/api/reconciliation/run', { method: 'POST' });
                const json = await res.json();
                showToast(`Batch Reconciled: ${json.summary.reconciled_to_issued} issued, ${json.summary.still_pending} pending.`);
                fetchPolicies();
            } catch (err) {
                showToast('Failed to run batch reconciliation');
            }
        }

        async function submitBulkCsv(e) {
            e.preventDefault();
            const fileInput = document.getElementById('bulk-file');
            if (!fileInput.files.length) return;

            const formData = new FormData();
            formData.append('file', fileInput.files[0]);

            try {
                const res = await fetch('/api/policy-requests/bulk-csv', {
                    method: 'POST',
                    body: formData,
                });
                const json = await res.json();
                if (json.success) {
                    const s = json.summary;
                    document.getElementById('bulk-results').innerHTML = `
                        <div style="background: var(--bg-surface-elevated); padding: 0.8rem; border-radius: 8px; font-size: 0.85rem;">
                            <strong>Summary:</strong> Total: ${s.total_rows} | Successful: ${s.successful} | Duplicates: ${s.duplicates} | Failed: ${s.failed}
                        </div>
                    `;
                    showToast(`Bulk upload complete: ${s.successful} created, ${s.duplicates} duplicates ignored.`);
                    fetchPolicies();
                } else {
                    showToast(json.message);
                }
            } catch (err) {
                showToast('Error uploading CSV');
            }
        }

        function openModal(id) {
            document.getElementById(id).classList.add('open');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
        }

        function openIntakeModal() {
            document.getElementById('intake-client-id').value = 'AGT-2026-' + Math.floor(1000 + Math.random() * 9000);
            openModal('modal-intake');
        }

        function openBulkModal() {
            document.getElementById('bulk-results').innerHTML = '';
            openModal('modal-bulk');
        }

        function showToast(msg) {
            const t = document.getElementById('toast');
            document.getElementById('toast-message').textContent = msg;
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 3500);
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    </script>
</body>
</html>
