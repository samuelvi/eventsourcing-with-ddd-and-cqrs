# Event Sourcing & n8n POC

This is a Proof of Concept (POC) for an event-sourced booking system using **Symfony 6.4**, **API Platform 3.4**, **React 18 (Vite)**, and **n8n**.

## 🚀 Quick Start

To initialize the entire system from scratch (containers, database, dependencies, and test data):

```bash
make init
```

## 🌐 Service URLs

### 🖥️ Frontend
*   **Main Entry Point (via Symfony):** [http://localhost:8080/](http://localhost:8080/)
*   **Vite Dev Server (Direct):** [http://localhost:5173/](http://localhost:5173/)

### 🔌 API & Documentation
*   **Swagger UI (API Docs):** [http://localhost:8080/docs](http://localhost:8080/docs)
*   **Alternative Swagger Link:** [http://localhost:8080/swagger](http://localhost:8080/swagger)

#### Core Endpoints
*   **Users:** `GET/POST http://localhost:8080/api/users`
*   **Suppliers:** `GET/POST http://localhost:8080/api/suppliers`
*   **Products (Menus):** `GET/POST http://localhost:8080/api/products`
*   **Booking Wizard:** `POST http://localhost:8080/api/booking-wizard`

### ⚙️ Automation & Tools
*   **n8n Workflow Tool:** [http://localhost:5678/](http://localhost:5678/)
*   **Adminer (Database Management):** [http://localhost:8081/](http://localhost:8081/)

#### 🗄️ Dev Database Credentials (Adminer)
*   **System:** `PostgreSQL`
*   **Server:** `postgres-db`
*   **Username:** `user`
*   **Password:** `password`
*   **Database:** `event_sourcing_dev`

## 🏗️ Architecture Features
*   **DDD (Domain-Driven Design):** Strict separation of layers (Domain, Application, Infrastructure).
*   **CQRS:** Separation of Read (DBAL/SQL) and Write (ORM) responsibilities.
*   **UUID v7:** Domain-generated identity for all entities.
*   **PHP 8.4:** Utilizing modern features like Property Hooks and Asymmetric Visibility.
*   **Clean Code:** Adhering to strict engineering standards defined in the `.skills/` directory.
