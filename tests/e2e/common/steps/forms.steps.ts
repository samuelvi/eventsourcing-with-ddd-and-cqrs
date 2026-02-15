import { expect, Page, Locator } from '@playwright/test';
import { When, Then } from '../bdd';

// =============================================================================
// Form Input Steps
// =============================================================================

When('I fill in {string} with {string}', async ({ page }, field: string, value: string) => {
    const locator = await resolveFieldLocator(page, field);
    await locator.fill(value);
});

When(
    'I fill in the field with placeholder {string} with {string}',
    async ({ page }, placeholder: string, value: string) => {
        const locator = await resolvePlaceholderLocator(page, placeholder);
        await locator.fill(value);
    }
);

When('I clear the field {string}', async ({ page }, field: string) => {
    const locator = await resolveFieldLocator(page, field);
    await locator.clear();
});

// =============================================================================
// Click Steps
// =============================================================================

When('I click the {string} button', async ({ page }, name: string) => {
    const locator = await resolveButtonLocator(page, name);
    await locator.click();
});

When('I click the {string} link', async ({ page }, name: string) => {
    const locator = await resolveLinkLocator(page, name);
    await locator.click();
});

When('I click on {string}', async ({ page }, text: string) => {
    const locator = await resolveClickableTextLocator(page, text);
    await locator.first().click();
});

// =============================================================================
// Select/Checkbox Steps
// =============================================================================

When('I select {string} from {string}', async ({ page }, option: string, field: string) => {
    const locator = await resolveSelectLocator(page, field);
    await locator.selectOption(option);
});

When('I check {string}', async ({ page }, label: string) => {
    const locator = await resolveCheckboxLocator(page, label);
    await locator.check();
});

When('I uncheck {string}', async ({ page }, label: string) => {
    const locator = await resolveCheckboxLocator(page, label);
    await locator.uncheck();
});

// =============================================================================
// Form Field Assertions
// =============================================================================

Then(
    'the field {string} should have value {string}',
    async ({ page }, field: string, value: string) => {
        const locator = await resolveFieldLocator(page, field);
        await expect(locator).toHaveValue(value);
    }
);

Then('the field {string} should be empty', async ({ page }, field: string) => {
    const locator = await resolveFieldLocator(page, field);
    await expect(locator).toHaveValue('');
});

Then('the checkbox {string} should be checked', async ({ page }, label: string) => {
    const locator = await resolveCheckboxLocator(page, label);
    await expect(locator).toBeChecked();
});

Then('the checkbox {string} should not be checked', async ({ page }, label: string) => {
    const locator = await resolveCheckboxLocator(page, label);
    await expect(locator).not.toBeChecked();
});

async function resolveFieldLocator(page: Page, field: string) {
    const pattern = buildPattern(field);
    const alternatives = splitAlternatives(field);
    const byLabel = await firstVisible(page.getByLabel(pattern));
    if (byLabel) return byLabel;

    const byRole = await firstVisible(page.getByRole('textbox', { name: pattern }));
    if (byRole) return byRole;

    const byPlaceholder = await firstVisible(page.getByPlaceholder(pattern));
    if (byPlaceholder) return byPlaceholder;

    return page
        .locator(
            xpathByAttrs(['input', 'textarea'], alternatives, [
                'name',
                'id',
                'aria-label',
                'placeholder'
            ])
        )
        .first();
}

async function resolvePlaceholderLocator(page: Page, placeholder: string) {
    const pattern = buildPattern(placeholder);
    const alternatives = splitAlternatives(placeholder);
    const byPlaceholder = await firstVisible(page.getByPlaceholder(pattern));
    if (byPlaceholder) return byPlaceholder;

    return page.locator(xpathByAttrs(['input', 'textarea'], alternatives, ['placeholder'])).first();
}

async function resolveSelectLocator(page: Page, field: string) {
    const pattern = buildPattern(field);
    const alternatives = splitAlternatives(field);
    const byLabel = await firstVisible(page.getByLabel(pattern));
    if (byLabel) return byLabel;

    const byRole = await firstVisible(page.getByRole('combobox', { name: pattern }));
    if (byRole) return byRole;

    const byText = await firstVisible(page.locator('select', { hasText: pattern }));
    if (byText) return byText;

    return page
        .locator(xpathByAttrs(['select'], alternatives, ['name', 'id', 'aria-label']))
        .first();
}

async function resolveCheckboxLocator(page: Page, label: string) {
    const pattern = buildPattern(label);
    const alternatives = splitAlternatives(label);
    const byLabel = await firstVisible(page.getByLabel(pattern));
    if (byLabel) return byLabel;

    const byRole = await firstVisible(page.getByRole('checkbox', { name: pattern }));
    if (byRole) return byRole;

    return page
        .locator(
            xpathByAttrs(['input'], alternatives, ['name', 'id', 'aria-label'], '@type="checkbox"')
        )
        .first();
}

async function resolveButtonLocator(page: Page, name: string) {
    const pattern = buildPattern(name);
    const alternatives = splitAlternatives(name);
    const byRole = await firstVisible(page.getByRole('button', { name: pattern }));
    if (byRole) return byRole;

    const byText = await firstVisible(page.locator('button', { hasText: pattern }));
    if (byText) return byText;

    const byTextXpath = await firstVisible(page.locator(xpathByText(['button'], alternatives)));
    if (byTextXpath) return byTextXpath;

    return page
        .locator(
            xpathByAttrs(
                ['button', 'input'],
                alternatives,
                ['aria-label', 'title', 'value', 'name', 'id'],
                '@type="button" or @type="submit" or self::button'
            )
        )
        .first();
}

async function resolveLinkLocator(page: Page, name: string) {
    const pattern = buildPattern(name);
    const alternatives = splitAlternatives(name);
    const byRole = await firstVisible(page.getByRole('link', { name: pattern }));
    if (byRole) return byRole;

    const byText = await firstVisible(page.locator('a', { hasText: pattern }));
    if (byText) return byText;

    const byTextXpath = await firstVisible(page.locator(xpathByText(['a'], alternatives)));
    if (byTextXpath) return byTextXpath;

    return page.locator(xpathByAttrs(['a'], alternatives, ['aria-label', 'title'])).first();
}

async function resolveClickableTextLocator(page: Page, text: string) {
    const pattern = buildPattern(text);
    const byButton = await firstVisible(page.getByRole('button', { name: pattern }));
    if (byButton) return byButton;

    const byLink = await firstVisible(page.getByRole('link', { name: pattern }));
    if (byLink) return byLink;

    return page.getByText(pattern, { exact: false });
}

function splitAlternatives(value: string): string[] {
    return value
        .split('|')
        .map((part) => part.trim())
        .filter(Boolean);
}

function buildPattern(value: string): RegExp | string {
    const alternatives = splitAlternatives(value);
    if (alternatives.length <= 1) return value;
    const escaped = alternatives.map(escapeRegex).join('|');
    return new RegExp(escaped, 'i');
}

function escapeRegex(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function xpathByAttrs(
    tags: string[],
    values: string[],
    attrs: string[],
    extraPredicate?: string
): string {
    const attrChecks = values
        .map((value) => {
            const literal = xpathLiteral(value);
            return attrs.map((attr) => `@${attr}=${literal}`).join(' or ');
        })
        .map((chunk) => `(${chunk})`)
        .join(' or ');

    const predicate = extraPredicate ? `(${attrChecks}) and (${extraPredicate})` : attrChecks;
    const tagXpath = tags.map((tag) => `//${tag}[${predicate}]`).join(' | ');
    return `xpath=${tagXpath}`;
}

function xpathByText(tags: string[], values: string[]): string {
    const textChecks = values
        .map((value) => {
            const literal = xpathLiteral(value);
            return `(normalize-space(.)=${literal} or contains(normalize-space(.), ${literal}))`;
        })
        .join(' or ');
    const tagXpath = tags.map((tag) => `//${tag}[${textChecks}]`).join(' | ');
    return `xpath=${tagXpath}`;
}

function xpathLiteral(value: string): string {
    if (!value.includes('"')) return `"${value}"`;
    if (!value.includes("'")) return `'${value}'`;
    const parts = value.split('"').map((part) => `"${part}"`);
    return `concat(${parts.join(', ", ", ')})`;
}

async function firstVisible(locator: Locator, timeout = 3000) {
    try {
        await locator.first().waitFor({ state: 'visible', timeout });
        return locator.first();
    } catch {
        return null;
    }
}
