---
name: cqrs-pattern
description: Command Query Responsibility Segregation (CQRS) implementation pattern. Separates write operations (Commands) from read operations (Queries).
---

# CQRS Pattern

## Overview

Separates write operations (Commands) from read operations (Queries) for better scalability and clarity.

## Components

### 1. Commands (Write)

- **DTOs:** Simple PHP classes (usually `readonly`) carrying data.
- **Intent:** Named after the user intent (e.g., `CreateUser`, `AssignOrder`).
- **No Return:** Commands generally do not return values (void).

### 2. Command Handlers

- **Logic:** Validate business rules, modify state, persist entities.
- **Events:** Dispatch domain events on success.

### 3. Queries (Read)

- **Direct Access:** For simple apps, Controllers/Resolvers can read directly from Repositories (optimized queries).
- **Projections:** For complex apps, use separate Read Models.

## Implementation Example (Symfony Messenger)

### Command

```php
final readonly class CreateUserCommand
{
    public function __construct(
        public string $email,
        public string $name,
    ) {}
}
```

### Handler

```php
#[AsMessageHandler]
final readonly class CreateUserHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function __invoke(CreateUserCommand $command): void
    {
        $user = new User($command->name, $command->email);
        $this->userRepository->save($user);
    }
}
```

### Controller

```php
#[Route('/users', methods: ['POST'])]
public function create(
    #[MapRequestPayload] CreateUserDto $dto,
    MessageBusInterface $bus
): JsonResponse {
    $bus->dispatch(new CreateUserCommand($dto->email, $dto->name));
    return new JsonResponse(null, 201);
}
```
