---
name: security-code-consultant
description: Perform practical application-security code consulting: identify vulnerabilities, assess severity, explain exploitability, and propose concrete fixes and verification steps.
metadata:
    short-description: Secure code review and vulnerability consulting
---

# Security Code Consultant

Use this skill for security-focused code review and remediation planning.

## Goals

- Detect real, exploitable security weaknesses.
- Prioritize by impact and likelihood.
- Provide fix-first, verifiable remediation guidance.

## Review Scope

- Input validation and output encoding.
- AuthN/AuthZ and privilege boundaries.
- Injection risks (SQL/NoSQL/command/template).
- Secrets handling and sensitive data exposure.
- SSRF, path traversal, deserialization, file upload risks.
- Session/token handling and replay concerns.
- Business-logic abuse paths.

## Severity Model

Classify each finding as:

- Critical
- High
- Medium
- Low

For each finding include:

- Affected file/path and risky code pattern.
- Why it is vulnerable.
- Exploit scenario (short and concrete).
- Recommended fix.
- How to verify the fix (test/check).

## Workflow

1. Threat-oriented read.
    - Identify trust boundaries and attacker-controlled inputs.
2. Pattern scan.
    - Look for dangerous sinks and missing controls.
3. Confirm exploitability.
    - Avoid false positives; require realistic attack path.
4. Remediation plan.
    - Prefer secure-by-default framework features.
5. Verification.
    - Add/adjust tests and runtime checks.

## Output Requirements

- Findings first, ordered by severity.
- Minimal summary after findings.
- Explicit residual risks if full fix is deferred.

## Guardrails

- Do not expose secrets in logs or outputs.
- Avoid vague advice; provide concrete code-level actions.
- Distinguish between “hardening recommendation” and “actual vulnerability”.
