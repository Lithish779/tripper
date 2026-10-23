# PolicyFlow: Fault-Tolerant Insurance Issuance & Reconciliation Engine
*Campus Placement | Software Engineering Practical Assessment*

**Assignment Reference:** `TRIPPERS SHIELD TC.43933.2027.64564 & TC.43933.2027.64696`  
**Candidate GitHub:** [@Lithish779](https://github.com/Lithish779)  
**Repository URL:** [https://github.com/Lithish779/tripper](https://github.com/Lithish779/tripper)  
**Reviewer:** [@MasterAdmin2](https://github.com/MasterAdmin2)  
**Submission Deadline:** 23-10-2026 till 11:59 PM  

[![Automated Tests](https://img.shields.io/badge/PHPUnit-20%20Passed%20%7C%20104%20Assertions-brightgreen)](https://github.com/Lithish779/tripper)
[![Laravel](https://img.shields.io/badge/Laravel-11.x-red)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-gray)](LICENSE)

---

## 1. Overview & Business Problem

PolicyFlow is an enterprise-grade, fault-tolerant insurance issuance and reconciliation engine built for travel-insurance distributors. It ingests policy purchase requests from agents, validates coverage rules, calculates immutable snapshot pricing, submits issuance requests to external insurer gateways, and tracks the lifecycle of every policy.

In the real world, insurer gateways are notoriously unreliable: network sockets time out, responses drop, asynchronous webhooks arrive out-of-order or duplicate, and requests fail intermittently. PolicyFlow ensures:
1. **Zero Duplicate Issuances:** Even under concurrent race conditions or near-simultaneous retries.
2. **Deterministic Pricing:** Snapshot multipliers preserved immutably across attempts.
3. **Explicit State Machine:** Rejects invalid regressions (e.g., `ISSUED → SUBMITTED`).
4. **Automated Reconciliation:** Self-heals in-flight and timed-out requests using carrier status lookups.

---

## 2. Architecture & Data Model

```
                    +---------------------------+
                    | Agent Client / API Intake |
                    +-------------+-------------+
                                  |
                                  v
                    +---------------------------+
                    |    PolicyFlow Engine      |
                    | (Validation & Pricing)    |
                    +-------------+-------------+
                                  |
          +-----------------------+-----------------------+
          |                                               |
          v                                               v
+-----------------------+                       +-----------------------+
|  Idempotency & Guard  |                       | Pricing Snapshot DTO  |
| UNIQUE(agent_id, req) |                       | (Immutable Factors)   |
+-----------+-----------+                       +-----------+-----------+
            |                                               |
            +-----------------------+-----------------------+
                                    |
                                    v
                    +---------------------------+
                    |    State Machine Guard    |
                    | RECEIVED -> VALIDATED     |
                    |   -> SUBMITTED -> ISSUED  |
                    +-------------+-------------+
                                  |
             +--------------------+--------------------+
             | (HTTP Call)                             | (Timeout/Webhook)
             v                                         v
+-------------------------+               +-------------------------+
|   Mock Insurer Gateway  |               | Idempotent Webhook &    |
| (Deterministic Scenarios|               | Status Reconciliation   |
+-------------------------+               +-------------------------+
```

### Core Database Entities
- **`policy_requests`**: Master ledger holding customer details, coverage limits, trip dates, state status, immutable pricing snapshot factors, and unique carrier policy number. Hard database unique index on `(agent_id, client_request_id)` guarantees intake idempotency at the storage layer.
- **`policy_state_histories`**: Audit trail of every lifecycle transition with reason, timestamps, and contextual metadata.
- **`issuance_attempts`**: Comprehensive log of every carrier HTTP attempt, request payload, response payload, HTTP status code, and error trace.
- **`provider_events`**: Webhook deduplication table enforcing uniqueness on `event_id` to guarantee at-most-once callback processing.
- **`mock_insurer_policies`**: Carrier-side mock ledger enabling true status lookups for reconciliation when upstream requests experience connection timeouts.

---

## 3. Implemented Reliability Scenarios

PolicyFlow implements **all four reliability scenarios** specified in the assessment:

### Scenario 1: Duplicate Intake (Near-Simultaneous Submissions)
- **Challenge:** Two identical requests with the same `agent_id` + `client_request_id` are delivered simultaneously.
- **Solution:** 
  1. Atomic database transaction with composite unique constraint `UNIQUE(agent_id, client_request_id)`.
  2. If an identical request exists, PolicyFlow catches the constraint / check and returns **HTTP 200 OK** with the existing policy record and `is_duplicate: true`.
  3. No second carrier call is made, and no redundant pricing is calculated.

### Scenario 2: Timeout After Success (Reconciliation Recovery)
- **Challenge:** The insurer issues the policy internally, but the HTTP connection drops or times out before PolicyFlow receives the response.
- **Solution:**
  1. PolicyFlow catches the timeout, marks the status as `PENDING_CONFIRMATION`, and records the error without blindly re-issuing.
  2. The reconciliation engine (`php artisan policyflow:reconcile` or `POST /api/policy-requests/{id}/reconcile`) queries the insurer's status lookup endpoint (`GET /api/mock-insurer/policies/{client_request_id}`).
  3. The insurer confirms the policy exists; PolicyFlow smoothly transitions to `ISSUED` and links the existing policy number.

### Scenario 3: Duplicate Webhook Callback (Idempotency)
- **Challenge:** The insurer fires the same asynchronous webhook event multiple times or out of order.
- **Solution:**
  1. Dedicated `provider_events` table with unique constraint on `event_id`.
  2. First delivery transitions the policy to `ISSUED`.
  3. Subsequent deliveries are trapped at the database level and return **HTTP 200** with `status: DUPLICATE_IGNORED`.
  4. Explicit state machine prevents any late or duplicate callback from regressing an already confirmed `ISSUED` policy.

### Scenario 4: Retry After Temporary Failure (Preserving Immutable Pricing)
- **Challenge:** Downstream insurer returns HTTP 503 (temporary failure). Retrying must not lose the accepted premium.
- **Solution:**
  1. Failed submission transitions state to `FAILED` with retryable flag.
  2. Endpoint `POST /api/policy-requests/{id}/retry` re-submits the existing policy record using the original immutable snapshot pricing.
  3. Upon carrier recovery, the policy issues with exactly one unique policy number.

---

## 4. Deterministic Pricing Rules

Premium is calculated via the specified formula:
$$\text{Premium} = \text{Base Daily Rate} \times \text{Travel Days} \times \text{Age Multiplier} \times \text{Destination Multiplier} \times \text{Coverage Multiplier}$$

- **Base Daily Rate:** `BASIC` = ₹10.00 | `PLUS` = ₹20.00 | `COMPREHENSIVE` = ₹35.00
- **Travel Days:** Inclusive days between `trip_start` and `trip_end` ($\text{diffInDays} + 1$).
- **Age Multiplier (as of trip start):**
  - `< 18`: 0.90
  - `18–35`: 1.00 *(Reference example: Age 28 $\rightarrow$ 1.00)*
  - `36–50`: 1.20
  - `51–65`: 1.50
  - `> 65`: 2.00
- **Destination Multiplier:**
  - `DOMESTIC`: 1.00
  - `SCHENGEN`: 1.50
  - `INTERNATIONAL`: 1.80 *(Reference example $\rightarrow$ 1.80)*
  - `US_CANADA`: 2.20
- **Coverage Multiplier (Sum Insured):**
  - `₹100,000`: 1.00
  - `₹300,000`: 1.25 *(Reference example $\rightarrow$ 1.25)*
  - `₹500,000`: 1.50
  - `₹1,000,000`: 2.00

**Verification against Reference Example:**
For 5 days, Age 28, PLUS plan, INTERNATIONAL, ₹300,000 sum insured:
$$\text{Premium} = 20 \times 5 \times 1.00 \times 1.80 \times 1.25 = \mathbf{₹225.00}$$

---

## 5. Local Setup Instructions

### Prerequisites
- PHP 8.2 or newer (Tested on PHP 8.5)
- Composer 2.x
- SQLite 3 (or MySQL 8+ / PostgreSQL)

### Step-by-Step Installation

```bash
# 1. Clone repository
git clone git@github.com:Lithish779/tripper.git policyflow
cd policyflow

# 2. Install dependencies
composer install

# 3. Environment configuration
cp .env.example .env
php artisan key:generate

# 4. Run database migrations & seed demo scenarios
php artisan migrate --seed

# 5. Start development server
php artisan serve
```
Open **[http://127.0.0.1:8000](http://127.0.0.1:8000)** in your browser to access the interactive operations dashboard.

---

## 6. Automated Tests Execution

Run the complete test suite:

```bash
php artisan test
```

All 20 test cases will execute and pass:
- **`PricingEngineTest`**: Verifies exact mathematical formula, factor brackets, and error rejections.
- **`PolicyStateMachineTest`**: Tests transition graph and rejects invalid regressions.
- **`PolicyIntakeApiTest`**: Validates HTTP 201/422 responses, date validations, and audit trails.
- **`ReliabilityScenariosTest`**: Proves all 4 reliability scenarios under automated conditions.
- **`BulkCsvIntakeTest`**: Verifies row-level reporting and duplicate handling.

---

## 7. API Contract & Examples

### 7.1 Policy Intake
`POST /api/policy-requests`

**Request Body:**
```json
{
  "client_request_id": "AGT-2026-0001",
  "agent_id": "AGENT-17",
  "customer_name": "Sample Customer",
  "date_of_birth": "1998-04-10",
  "destination": "INTERNATIONAL",
  "trip_start": "2026-11-10",
  "trip_end": "2026-11-14",
  "plan_code": "PLUS",
  "sum_insured": 300000
}
```

**Response (HTTP 201 Created):**
```json
{
  "success": true,
  "is_duplicate": false,
  "status": "ISSUED",
  "message": "Policy request accepted and processed.",
  "data": {
    "id": 1,
    "client_request_id": "AGT-2026-0001",
    "agent_id": "AGENT-17",
    "status": "ISSUED",
    "policy_number": "POL-INS-84920194",
    "provider_reference": "REF-INS-9A8B7C6D5E",
    "premium": 225.00,
    "travel_days": 5
  }
}
```

**Repeated Submission (HTTP 200 OK):**
```json
{
  "success": true,
  "is_duplicate": true,
  "status": "ISSUED",
  "message": "Duplicate intake detected. Returning existing policy request safely without re-issuing.",
  "data": {
    "id": 1,
    "client_request_id": "AGT-2026-0001",
    "policy_number": "POL-INS-84920194"
  }
}
```

### 7.2 Read Policy & Audit Trail
`GET /api/policy-requests/{id}`

Returns policy status, immutable pricing snapshot breakdown, and full timeline of transitions:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "ISSUED",
    "pricing_snapshot": {
      "travel_days": 5,
      "base_daily_rate": 20,
      "age_multiplier": 1.0,
      "destination_multiplier": 1.8,
      "coverage_multiplier": 1.25,
      "premium": 225.0
    },
    "audit_trail": [
      {
        "from_status": null,
        "to_status": "RECEIVED",
        "reason": "Policy intake accepted and premium calculated"
      },
      {
        "from_status": "RECEIVED",
        "to_status": "VALIDATED",
        "reason": "Validation rules and pricing parameters passed"
      },
      {
        "from_status": "VALIDATED",
        "to_status": "SUBMITTED",
        "reason": "Submitting request to insurer gateway"
      },
      {
        "from_status": "SUBMITTED",
        "to_status": "ISSUED",
        "reason": "Policy issuance confirmed by insurer"
      }
    ]
  }
}
```

### 7.3 Reconciliation Command & API
- **CLI Command:** `php artisan policyflow:reconcile`
- **Batch Endpoint:** `POST /api/reconciliation/run`
- **Single Policy Endpoint:** `POST /api/policy-requests/{id}/reconcile`

### 7.4 Running Automated cURL Evaluation Script
```bash
./examples/curl_commands.sh http://127.0.0.1:8000
```

---

## 8. Stretch Features Implemented

1. **Operations Dashboard (`/`):** Interactive UI providing real-time metrics, status filters, policy request forms with carrier scenario simulation, audit trail modal, and one-click reconciliation and retry actions.
2. **Bulk CSV Ingestion (`POST /api/policy-requests/bulk-csv`):** Validates and ingests large batches with per-row error tracking and duplicate detection.
3. **Automated Artisan Command (`policyflow:reconcile`):** CLI and cron-compatible command to detect and heal unconfirmed policies.

---

## 9. Known Limitations & Production Enhancements
- **Database Driver:** Defaults to SQLite for local development; supports MySQL 8+ or PostgreSQL in production by setting `DB_CONNECTION` in `.env`.
- **Distributed Queues:** Current implementation processes HTTP requests synchronously. In enterprise scale (>10,000 req/sec), intake would push to a Redis/RabbitMQ queue with worker jobs executing carrier submission with exponential backoff.
- **Authentication:** For internal placement evaluation, endpoints are open; in production, Sanctum token or mTLS authentication would guard carrier webhooks and agent requests.
