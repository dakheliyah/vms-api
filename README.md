# Mumineen API

## Project Overview

A Laravel-based REST API with PostgreSQL integration for managing Mumineen records. The API provides comprehensive CRUD operations with Swagger documentation for all endpoints.

## Features

- Full CRUD operations for Mumineen records
- Family member lookup functionality
- API documentation with Swagger UI
- PostgreSQL database integration
- Based on Laravel 10

## Technology Stack

- **Framework**: Laravel 10.x
- **Database**: PostgreSQL (Remote Cloud Instance)
- **Documentation**: OpenAPI/Swagger (darkaonline/l5-swagger)
- **PHP Version**: 8.1.6 (XAMPP bundled)

## Requirements

- PHP >= 8.1
- Composer
- PostgreSQL PDO Extension
- Laravel requirements (BCMath, Ctype, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML)

## Database Schema

The `mumineens` table has the following structure:

| Column      | Type      | Description                                  |
|-------------|-----------|----------------------------------------------|
| its_id      | integer   | Primary key, 8-digit unique ID               |
| eits_id     | integer   | Optional 8-digit ID                          |
| hof_its_id  | integer   | Head of Family ITS ID (for family grouping)  |
| full_name   | string    | Full name of the person                      |
| gender      | enum      | Male, Female or Other                        |
| age         | integer   | Age of the person                            |
| mobile      | string    | Contact number                               |
| country     | string    | Country of residence                         |
| created_at  | timestamp | Record creation timestamp                     |
| updated_at  | timestamp | Record last updated timestamp                 |

## Installation

1. Clone the repository
   ```bash
   git clone [repository-url]
   cd api-2
   ```

2. Install dependencies
   ```bash
   composer install
   ```

3. Configure environment
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Configure database in `.env`
   ```
   DB_CONNECTION=pgsql
   DB_HOST=your-postgres-host
   DB_PORT=5432
   DB_DATABASE=your-database
   DB_USERNAME=your-username
   DB_PASSWORD=your-password
   ```

5. Run migrations
   ```bash
   php artisan migrate
   ```

6. Generate Swagger documentation
   ```bash
   php artisan l5-swagger:generate
   ```

7. Start the server
   ```bash
   php artisan serve
   ```

## API Endpoints

| Method | Endpoint                              | Description                        |
|--------|---------------------------------------|------------------------------------|  
| GET    | /api/mumineen                         | List all Mumineen                  |
| POST   | /api/mumineen                         | Create a new Mumineen              |
| GET    | /api/mumineen/{its_id}                | Get a specific Mumineen by its_id  |
| PUT    | /api/mumineen/{its_id}                | Update a Mumineen record           |
| DELETE | /api/mumineen/{its_id}                | Delete a Mumineen record           |
| GET    | /api/mumineen/family-by-its-id/{its_id} | Get all family members by its_id   |

## Family Lookup Functionality

The `/api/mumineen/family-by-its-id/{its_id}` endpoint implements a specialized lookup:

1. It takes an individual's ITS ID as input
2. Finds that individual's Head of Family (HOF) ITS ID
3. Returns all members who share the same HOF ITS ID

This allows for retrieving entire family groups in a single API call.

## Project Structure

- **Models**: `app/Models/Mumineen.php`
- **Controllers**: `app/Http/Controllers/API/MumineenController.php`
- **Routes**: `routes/api.php`
- **Migrations**: `database/migrations/2025_06_10_162017_create_mumineens_table.php`
- **Swagger Documentation**: 
  - Configuration: `config/l5-swagger.php`
  - Model Annotations: `app/Models/Swagger/Mumineen.php`
  - Controller Annotations: Inline in `MumineenController.php`

## API Documentation

Swagger UI documentation is available at `/api/documentation` when the server is running.

## Validation Rules

The API implements validation for all inputs:

- `its_id`: Required, 8-digit integer, unique
- `eits_id`: Optional, 8-digit integer
- `hof_its_id`: Optional, 8-digit integer
- `full_name`: Required, string, max 255 characters
- `gender`: Required, enum (male, female, other)
- `age`: Optional, integer
- `mobile`: Optional, string
- `country`: Optional, string

## Response Format

All API responses follow a consistent JSON format:

```json
{
  "success": true|false,
  "message": "Operation status message",
  "data": [] // Data payload or error details
}
```

## Error Handling

The API returns appropriate HTTP status codes:

- 200: Success
- 201: Created (for POST requests)
- 400: Bad Request (validation errors)
- 404: Not Found
- 500: Server Error

## Development and Maintenance

- Created: June 10, 2025
- Current Version: 1.0.0
- Maintained by: [Your Organization Name]

## License

This project is proprietary and confidential. Unauthorized copying, distribution, or use is strictly prohibited.

