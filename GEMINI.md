# Project Instruction: Cour Suprême Internship Platform

This project is a Symfony-based web application designed to manage internship applications for the Supreme Court. It features a public interface for candidates to apply and track their status, and a secured backoffice for administrators and evaluators.

## Tech Stack & Architecture

- **Framework**: Symfony 8.0 (PHP 8.4+)
- **ORM**: Doctrine 3.6 (MySQL 8.0+)
- **Templates**: Twig with Symfony UX (Icons, Components)
- **Email**: Symfony Mailer (Google Mailer configured)
- **PDF**: Dompdf for letter generation
- **Storage**: Documents are stored in `var/storage/documents/` (private, not public).

## Core Domain Models

The project uses a Single Table Inheritance (STI) pattern for users:
- **`App\Entity\Utilisateur`**: Base entity (id, email, nom, prenom).
    - **`App\Entity\Candidat`**: Uses a 6-digit `codeAcces` (stored plain) for tracking. `password` is usually NULL.
    - **`App\Entity\Evaluateur`**: Staff member who reviews dossiers.
    - **`App\Entity\Admin`**: System administrator with user management rights.

**Core Entities:**
- **`Dossier`**: Represents an internship application. Links a `Candidat` to multiple `Document` entities and one `Evaluateur`.
- **`Evaluation`**: Review entry for a dossier.
- **`AppConfig`**: Global settings (e.g., mailer settings, platform status).

## Authentication & Authorization

### Two Auth Systems
1. **Admin/Evaluator**: standard Symfony Security (`email` + hashed password).
2. **Candidate**: Session-based identification using the 6-digit `codeAcces`. **Manual checks** in `HomeController` handle candidate access, not Symfony Security firewalls.

### Role Hierarchy
```yaml
ROLE_ADMIN > ROLE_EVALUATEUR > ROLE_USER
```
- `ROLE_ADMIN`: Full backoffice access, user management.
- `ROLE_EVALUATEUR`: Dossier management and evaluation.

## Key Development Workflows

### Business Logic
- **`App\Service\DossierManager`**: Handles dossier creation/updates, file uploads, and initial notifications.
- **`App\Service\NotificationService`**: Orchestrates email sending.
- **Enums**: Used for status management:
    - `StatutDossier`: EN_ATTENTE, EN_EVALUATION, VALIDE, REJETE, MIS_EN_RESERVE.
    - `TypeDocument`: CV, LETTRE_MOTIVATION, etc.

### UI & Styling
- **Symfony UX Icons**: Use `<twig:ux:icon name="..." />`.
- **Twig Components**: Used for modular UI elements.
- **Assets**: Public assets are in `public/css/` and `public/images/`.

## Essential Commands

### Setup & Migrations
```bash
# Install dependencies
composer install

# Database setup
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### Administration
```bash
# Create/Update the main admin user (uses .env variables)
php bin/console app:create-admin

# Clear application cache
php bin/console cache:clear

# Lock UX icons for production
php bin/console ux:icons:lock
```

## Directory Structure Highlights
- `config/`: Application configuration (security, services, packages).
- `migrations/`: Database versioning files.
- `src/`: Core PHP source code.
- `templates/`: Twig templates organized by domain.
- `var/storage/`: Secured location for candidate documents.
