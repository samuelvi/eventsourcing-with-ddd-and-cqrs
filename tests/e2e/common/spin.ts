/**
 * Spin — Poll-based retry utility for E2E step definitions.
 *
 * Instead of using fixed `waitForTimeout()` calls or fragile one-shot assertions,
 * `spin` repeatedly executes a callback at short intervals until it succeeds or
 * the timeout expires. This makes tests resilient to variable backend/rendering
 * latency without over-sleeping.
 *
 * ## Usage
 *
 * ```ts
 * import { spin } from '../common/spin';
 *
 * // Inside a step definition:
 * await spin(async () => {
 *   const count = await getAuditTrailCount(page);
 *   expect(count).toBeGreaterThan(initialCount);
 * });
 *
 * // With custom options:
 * await spin(async () => { ... }, { timeout: 30_000, interval: 500 });
 * ```
 */

export interface SpinOptions {
    /** Maximum time (ms) to keep retrying before giving up. Default: 600 000 (10 min). */
    timeout?: number;
    /** Pause (ms) between consecutive retries. Default: 250. */
    interval?: number;
}

const DEFAULT_TIMEOUT = 600_000; // 10 minutes
const DEFAULT_INTERVAL = 250; // 250 ms

/**
 * Retry `fn` until it resolves or `timeout` ms elapse.
 *
 * @param fn        Async callback that performs assertions. Should throw on failure.
 * @param options   Optional timeout / interval overrides.
 * @returns         Whatever `fn` returns on success.
 */
export async function spin<T>(fn: () => T | Promise<T>, options: SpinOptions = {}): Promise<T> {
    const timeout = options.timeout ?? DEFAULT_TIMEOUT;
    const interval = options.interval ?? DEFAULT_INTERVAL;
    const deadline = Date.now() + timeout;
    let lastError: unknown;

    while (true) {
        try {
            return await fn();
        } catch (error) {
            lastError = error;
            if (Date.now() >= deadline) break;
            await sleep(Math.min(interval, deadline - Date.now()));
        }
    }

    throw lastError;
}

function sleep(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
}
