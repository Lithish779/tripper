#!/bin/bash
# PolicyFlow Demonstration & Evaluation Script
# Usage: ./examples/curl_commands.sh [BASE_URL]
# Default BASE_URL is http://127.0.0.1:8000

BASE_URL="${1:-http://127.0.0.1:8000}"

echo "=========================================================="
echo " PolicyFlow API Evaluation & Scenario Test Runner"
echo " Target: ${BASE_URL}"
echo "=========================================================="
echo ""

echo ">>> 1. SUBMITTING VALID POLICY INTAKE (HTTP 201 Expected)"
curl -s -X POST "${BASE_URL}/api/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_request_id": "AGT-DEMO-001",
    "agent_id": "AGENT-17",
    "customer_name": "Alice Smith",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }' | jq .
echo ""

echo ">>> 2. RELIABILITY CASE 1: DUPLICATE INTAKE SUBMISSION (HTTP 200 Expected with identical policy)"
curl -s -X POST "${BASE_URL}/api/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_request_id": "AGT-DEMO-001",
    "agent_id": "AGENT-17",
    "customer_name": "Alice Smith",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }' | jq .
echo ""

echo ">>> 3. RELIABILITY CASE 2: TIMEOUT AFTER SUCCESS (Simulating Carrier Timeout)"
curl -s -X POST "${BASE_URL}/api/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_request_id": "AGT-DEMO-TIMEOUT",
    "agent_id": "AGENT-17",
    "customer_name": "Timeout User",
    "date_of_birth": "1990-01-01",
    "destination": "SCHENGEN",
    "trip_start": "2026-12-01",
    "trip_end": "2026-12-10",
    "plan_code": "PLUS",
    "sum_insured": 300000,
    "mock_scenario": "TIMEOUT"
  }' | jq .
echo ""

echo ">>> 4. RESOLVING TIMEOUT VIA RECONCILIATION"
curl -s -X POST "${BASE_URL}/api/reconciliation/run" \
  -H "Accept: application/json" | jq .
echo ""

echo ">>> 5. RELIABILITY CASE 3: WEBHOOK CALLBACK DEDUPLICATION"
echo "Delivering Webhook Event 1..."
curl -s -X POST "${BASE_URL}/api/insurer/callback" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "event_id": "EVT-CURL-9901",
    "client_request_id": "AGT-DEMO-TIMEOUT",
    "provider_reference": "REF-WEBHOOK-9901",
    "event_type": "POLICY_ISSUED"
  }' | jq .

echo "Delivering Duplicate Webhook Event..."
curl -s -X POST "${BASE_URL}/api/insurer/callback" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "event_id": "EVT-CURL-9901",
    "client_request_id": "AGT-DEMO-TIMEOUT",
    "provider_reference": "REF-WEBHOOK-9901",
    "event_type": "POLICY_ISSUED"
  }' | jq .
echo ""

echo ">>> 6. RELIABILITY CASE 4: RETRY AFTER TEMPORARY 503 ERROR"
RETRY_RES=$(curl -s -X POST "${BASE_URL}/api/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_request_id": "AGT-DEMO-FAIL",
    "agent_id": "AGENT-17",
    "customer_name": "Temporary Fail User",
    "date_of_birth": "1992-05-15",
    "destination": "DOMESTIC",
    "trip_start": "2026-11-01",
    "trip_end": "2026-11-05",
    "plan_code": "BASIC",
    "sum_insured": 100000,
    "mock_scenario": "TEMP_ERROR"
  }')
echo "${RETRY_RES}" | jq .
POLICY_ID=$(echo "${RETRY_RES}" | jq -r '.data.id')

echo "Retrying policy ID ${POLICY_ID}..."
curl -s -X POST "${BASE_URL}/api/policy-requests/${POLICY_ID}/retry" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"mock_scenario": "SUCCESS"}' | jq .
echo ""

echo ">>> 7. FETCHING AUDIT TRAIL & IMMUTABLE PRICING BREAKDOWN"
curl -s -X GET "${BASE_URL}/api/policy-requests/${POLICY_ID}" \
  -H "Accept: application/json" | jq .
echo ""

echo ">>> 8. TESTING VALIDATION ERROR REJECTION (HTTP 422 Expected)"
curl -s -X POST "${BASE_URL}/api/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_request_id": "AGT-INVALID",
    "agent_id": "AGENT-17",
    "customer_name": "Invalid User",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-14",
    "trip_end": "2026-11-10",
    "plan_code": "INVALID_PLAN",
    "sum_insured": 999999
  }' | jq .
echo ""

echo "=========================================================="
echo " Evaluation complete."
echo "=========================================================="
