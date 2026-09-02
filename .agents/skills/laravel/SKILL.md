# Laravel Development Skill

Use standard Laravel conventions.

Prefer:

* Eloquent models
* migrations
* factories
* seeders
* Form Requests where appropriate
* policies
* middleware
* services/actions for non-trivial business logic
* Laravel validation
* configuration/environment variables
* feature tests
* database transactions where appropriate

Do not place large amounts of application logic inside controllers.

Do not place business logic inside Blade templates.

Do not place security enforcement only in frontend code.

Use route middleware for access restrictions where appropriate.

Use policies for resource authorization.

Use mass-assignment protection.

Use appropriate database indexes for frequently queried security data.

Ensure migrations can be executed from a clean database.

When modifying the schema:

1. create/update migration
2. update model
3. update relationships
4. update factory/seeder where appropriate
5. update tests
6. verify migrations and tests

Follow the Laravel version actually installed in the repository.
