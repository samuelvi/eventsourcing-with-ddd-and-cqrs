# Datafixtures with Factory Pattern

Structured, extensible test data generation without hardcoded IDs or SQL dumps.

## Core Library: Fishery

Fishery provides TypeScript-friendly factory pattern with extension and hooks.

```bash
npm install -D fishery @faker-js/faker
```

## Basic Factory Pattern

```typescript
// factories/userFactory.ts
import { Factory } from 'fishery';
import { faker } from '@faker-js/faker';

interface User {
    id?: string;
    email: string;
    name: string;
    password: string;
    createdAt?: Date;
}

export const userFactory = Factory.define<User>(({ sequence }) => ({
    email: faker.internet.email(),
    name: faker.person.fullName(),
    password: faker.internet.password({ length: 20 }),
    createdAt: faker.date.past()
}));

// Usage
const user = userFactory.build();
const users = userFactory.buildList(5);
```

## Extensibility Patterns

### 1. Factory Extension for Variants

```typescript
// Base factory
export const userFactory = Factory.define<User>(() => ({
    email: faker.internet.email(),
    role: 'user',
    isActive: true
}));

// Admin variant
export const adminUserFactory = userFactory.extend({
    role: 'admin',
    permissions: ['read', 'write', 'delete']
});

// Inactive variant
export const inactiveUserFactory = userFactory.extend({
    isActive: false,
    deactivatedAt: () => faker.date.recent()
});
```

### 2. Complex Data Types

#### Binary Data (e.g., Geolocation)

```typescript
export const locationFactory = Factory.define<Location>(() => ({
    name: faker.location.city(),
    // Binary geolocation data
    coordinates: Buffer.from(
        JSON.stringify({
            lat: faker.location.latitude(),
            lng: faker.location.longitude()
        })
    ),
    // Or PostGIS format
    geom: `POINT(${faker.location.longitude()} ${faker.location.latitude()})`
}));
```

#### Encrypted/Hashed Data

```typescript
import bcrypt from 'bcryptjs';

export const userFactory = Factory.define<User>(({ sequence }) => ({
    email: faker.internet.email(),
    // Properly hashed password
    passwordHash: bcrypt.hashSync('testPassword123', 10),
    // API tokens
    apiToken: faker.string.uuid()
}));
```

#### File Attachments

```typescript
export const attachmentFactory = Factory.define<Attachment>(() => ({
    filename: faker.system.fileName(),
    mimeType: faker.system.mimeType(),
    // Base64 encoded file content
    content: Buffer.from(faker.lorem.paragraphs()).toString('base64'),
    size: faker.number.int({ min: 1024, max: 1048576 })
}));
```

### 3. Relational Data

```typescript
export const postFactory = Factory.define<Post>(({ associations }) => ({
    title: faker.lorem.sentence(),
    content: faker.lorem.paragraphs(),
    // Lazy association - created on demand
    author: associations.author || userFactory.build()
}));

// Usage with explicit associations
const author = userFactory.build();
const post = postFactory.build({ author });

// Or let factory create the author
const post = postFactory.build();
```

### 4. Transient Parameters

```typescript
export const orderFactory = Factory.define<Order>(({ transientParams }) => {
    const itemCount = transientParams.itemCount || 1;

    return {
        orderNumber: faker.string.alphanumeric(10),
        items: orderItemFactory.buildList(itemCount),
        total: 0 // calculated in afterBuild
    };
});

// Usage
const largeOrder = orderFactory.build({}, { transient: { itemCount: 10 } });
```

### 5. AfterBuild Hooks

```typescript
export const orderFactory = Factory.define<Order>(() => ({
    items: orderItemFactory.buildList(3),
    total: 0,
    tax: 0
})).afterBuild((order) => {
    // Calculate derived values
    order.total = order.items.reduce((sum, item) => sum + item.price, 0);
    order.tax = order.total * 0.1;
    return order;
});
```

## Avoiding Hardcoded IDs

### ❌ Bad: Hardcoded IDs

```typescript
// Fragile - breaks if DB state changes
Given('I view product {int}', async ({ page }, id) => {
    await page.goto(`/products/${id}`);
});

// Creates test order dependency
const product = productFactory.build({ id: 1 });
```

### ✅ Good: Dynamic References

```typescript
// Store reference in test context
Given('a product exists', async ({ page }, world) => {
    const product = await productFactory.create();
    world.currentProduct = product;
});

When('I view that product', async ({ page }, world) => {
    await page.goto(`/products/${world.currentProduct.slug}`);
});

// Or search by visible content
When('I view the {string} product', async ({ page }, productName) => {
    await page.getByRole('link', { name: productName }).click();
});
```

## Database Integration

### With Knex

```typescript
// factories/index.ts
import { db } from '../support/database';

class FactoryDatabase {
    async create<T>(factory: Factory<T>): Promise<T> {
        const data = factory.build();
        const [record] = await db('table_name').insert(data).returning('*');
        return record;
    }

    async createList<T>(factory: Factory<T>, count: number): Promise<T[]> {
        const data = factory.buildList(count);
        return db('table_name').insert(data).returning('*');
    }
}

export const factoryDb = new FactoryDatabase();

// Usage in steps
Given('users exist', async ({}) => {
    await factoryDb.createList(userFactory, 5);
});
```

### With TypeORM

```typescript
// factories/userFactory.ts
export const userFactory = Factory.define<User>(() => ({
    email: faker.internet.email(),
    name: faker.person.fullName()
}));

// fixtures/database.fixture.ts
import { getRepository } from 'typeorm';

export const test = base.extend<{ createUser: (overrides?) => Promise<User> }>({
    createUser: async ({}, use) => {
        await use(async (overrides = {}) => {
            const userData = userFactory.build(overrides);
            const repo = getRepository(User);
            return repo.save(userData);
        });
    }
});

// Usage in steps
Given('a user with email {string} exists', async ({ createUser }, email) => {
    await createUser({ email });
});
```

## Factory Organization

```
factories/
├── index.ts              # Export all factories
├── userFactory.ts
├── productFactory.ts
├── orderFactory.ts
└── helpers/
    ├── addresses.ts      # Reusable address generator
    └── timestamps.ts     # Reusable timestamp patterns
```

```typescript
// factories/index.ts
export { userFactory, adminUserFactory } from './userFactory';
export { productFactory } from './productFactory';
export { orderFactory } from './orderFactory';

// factories/helpers/addresses.ts
export const generateAddress = () => ({
    street: faker.location.streetAddress(),
    city: faker.location.city(),
    postalCode: faker.location.zipCode(),
    country: faker.location.countryCode()
});
```

## Testing Factories

Always test critical factories to ensure data validity:

```typescript
// factories/__tests__/userFactory.test.ts
import { userFactory } from '../userFactory';

describe('userFactory', () => {
    it('generates valid email', () => {
        const user = userFactory.build();
        expect(user.email).toMatch(/^[^\s@]+@[^\s@]+\.[^\s@]+$/);
    });

    it('generates hashed password', () => {
        const user = userFactory.build();
        expect(user.passwordHash).toHaveLength(60); // bcrypt length
    });
});
```

## Performance Considerations

```typescript
// Reuse expensive operations
const passwordHash = bcrypt.hashSync('defaultPassword', 10);

export const userFactory = Factory.define<User>(() => ({
    email: faker.internet.email(),
    passwordHash // Reuse instead of recalculating
}));

// Batch inserts for large datasets
Given('1000 products exist', async ({}) => {
    const products = productFactory.buildList(1000);
    await db.batchInsert('products', products, 100); // chunks of 100
});
```
