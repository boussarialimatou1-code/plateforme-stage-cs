# AGENTS.md - Plateau Stage Cour Suprême

## Quick Start

```bash
# Install dependencies
composer install

# Create database
php bin/console doctrine:database:create

# Run migrations
php bin/console doctrine:migrations:migrate

# Start dev server
symfony server:start
# OR: php -S localhost:8000 -t public
```

## Key Architecture Facts

- **Framework**: Symfony 8.0, PHP 8.4+
- **Database**: MySQL 8.0+, Doctrine ORM 3.6
- **Entry point**: `public/index.php`

## Critical Quirks

1. **Entity name**: `App\Entity\Utilisateur` with subclasses `Candidat`, `Evaluateur`, `Admin`
   - Security provider uses `Utilisateur::class` with `email` property as identifier

2. **Two authentication systems**:
   - **Admin/Evaluator**: Symfony Security with `email` + hashed password
   - **Candidate**: Session-based with 6-digit access code (stored PLAIN in DB, not hashed)
   - Candidate has `password = NULL`

3. **Role hierarchy**:
   ```
   ROLE_ADMIN > ROLE_EVALUATEUR > ROLE_USER > ROLE_CANDIDAT
   ```

4. **Access control routes**:
   - `/admin/*` requires `ROLE_EVALUATEUR` or `ROLE_ADMIN`
   - Candidate routes use manual session checks, not Symfony Security

5. **File storage**: `var/storage/documents/` (not in `public/`)

## Important Commands

```bash
# Create admin user
php bin/console app:create-admin

# Hash password (for manual DB insert)
php bin/console security:hash-password

# Clear cache
php bin/console cache:clear
```

## Email

- Dev: `MAILER_DSN=smtp://localhost:1025?verify_peer=0`
- Production: Uncomment and configure Brevo or Gmail in `.env`

## DB Connection

```env
DATABASE_URL="mysql://root:@127.0.0.1:3306/cour_supreme_stage?serverVersion=8.4.7&charset=utf8mb4"
```