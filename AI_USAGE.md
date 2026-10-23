# AI Usage Disclosure

## 1. Tools Used
- **Antigravity AI (Pair Programming Assistant)**
- **PHPUnit & Artisan CLI**

---

## 2. Concrete Ways AI Assisted
1. **Deterministic Pricing Engine & State Machine Formulation:**
   - AI helped scaffold the mathematical calculation engine enforcing `base_daily_rate × travel_days × age_multiplier × destination_multiplier × coverage_multiplier` and ensuring calculations match the prompt's reference sample (₹225.00 for PLUS plan, 5 days, age 28, INTERNATIONAL destination, 300k sum insured).
   - Designed an explicit state transition validator in `PolicyStatus` enum rejecting illegal state regressions (such as `ISSUED → SUBMITTED` or `ISSUED → FAILED`).

2. **Fault-Tolerance Architecture for Reliability Scenarios:**
   - Assisted in designing the composite unique database constraint on `(agent_id, client_request_id)` coupled with atomic database transactions to handle high-concurrency race conditions during near-simultaneous duplicate intake.
   - Structured the mock carrier ledger `mock_insurer_policies` to simulate real-world carrier timeout scenarios where downstream issuance succeeds internally despite dropped client connections, enabling status lookup reconciliation.

3. **Automated Test Matrix:**
   - Rapidly generated 20 automated test cases across unit and feature domains covering all 4 reliability scenarios, validation rejections, and bulk CSV ingestion.

---

## 3. Incorrect or Incomplete Suggestions Encountered & Remediation
- **Heredoc Delimiter Conflict in Feature Tests:**
  - *Issue:* During initial test generation for bulk CSV ingestion, AI used `<<<CSV` as the heredoc delimiter while the CSV content began with `CSV-001`. The PHP parser interpreted `CSV` at the start of column 1 as the closing delimiter, generating an unexpected token syntax error.
  - *Remediation:* Replaced the delimiter with `<<<'EOT'` nowdoc syntax to guarantee safe string escaping and prevent delimiter collisions.
- **Strict Type Float Comparison in PHPUnit:**
  - *Issue:* Initial test used `$this->assertSame(225.00, $premium)` which failed when comparing JSON-decoded numbers (`225` vs `225.0`).
  - *Remediation:* Changed to `$this->assertEquals(225.00, (float) $premium)` for robust numeric comparison across serializers.

---

## 4. Verification and Testing Methodology
- **Automated Testing:**
  - Executed `php artisan test` validating all 20 tests and 104 assertions across unit and feature suites.
- **End-to-End API Execution:**
  - Executed curl commands via `examples/curl_commands.sh` verifying HTTP 201 for intake, 200 for idempotent duplicate submission, 422 for input validation rejections, and safe status transitions during retry and reconciliation.
- **CLI Command Verification:**
  - Verified `php artisan policyflow:reconcile` against pending policies in the database, observing status transitions and audit log persistence.
