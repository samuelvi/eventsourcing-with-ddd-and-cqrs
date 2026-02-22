---
name: algorithm-optimization
description: Analyze and optimize algorithmic complexity (Big-O), detect O(n^2) hotspots, and apply pragmatic improvements including dynamic programming, indexing, and data-structure changes.
metadata:
    short-description: Algorithmic complexity and performance optimization
---

# Algorithm Optimization

Use this skill when performance issues are linked to algorithmic complexity.

## Goals

- Detect complexity bottlenecks (`O(n^2)`, repeated scans, nested loops with lookups).
- Propose lower-complexity alternatives with clear tradeoffs.
- Keep readability and correctness while improving asymptotic behavior.

## Workflow

1. Baseline the current algorithm.
    - Identify dominant path and estimate complexity.
    - Mark memory complexity too (`O(1)`, `O(n)`, etc.).
2. Find hotspot patterns.
    - Nested loops over growing collections.
    - Repeated DB/API calls inside loops.
    - Recomputing overlapping subproblems.
3. Apply targeted strategy.
    - Replace repeated search with hash/index (`O(1)` average lookup).
    - Sort once + linear merge (`O(n log n)` then `O(n)`).
    - Prefix sums / memoization / tabulation for dynamic programming.
    - Sliding window / two pointers where applicable.
4. Validate behavior.
    - Preserve semantics with tests (edge cases + regression tests).
5. Validate performance.
    - Compare before/after complexity.
    - Add micro-benchmark or representative timing when possible.

## Strategy Guide

- If `O(n^2)` due to lookups in arrays/lists: build map/set first (`O(n)`), then single pass (`O(n)`).
- If same subproblem is solved repeatedly: use memoization (top-down DP) or tabulation (bottom-up DP).
- If sequence optimization with local decisions fails: consider DP state definition explicitly.
- If memory pressure matters: trade speed for memory consciously, document it.

## Output Requirements

When proposing an optimization, always include:

- Current complexity and proposed complexity.
- Why it is correct.
- Tradeoffs (readability, memory, maintainability).
- Test impact (what to add/update).

## Guardrails

- Do not optimize blindly; optimize proven hotspots.
- Prefer simple improvements before advanced DP.
- Avoid premature micro-optimizations if asymptotic complexity is the real issue.
