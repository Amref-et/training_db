# Amref Training Database Documentation

## 1. Overview

The Amref Training Database is a Laravel-based training management and reporting platform used to manage:

- geographic master data: regions, zones, woredas
- organizations and facilities
- participants and participant profiles
- projects and subawardees
- trainings and training materials
- training events and participant enrollment
- workshop-level scoring and final performance reporting
- public website content, navigation, and appearance settings
- role-based administration, activity logs, and configurable dashboards

The application exposes two primary surfaces:

- a public website and CMS-driven page system
- an authenticated admin workspace under `/admin`

The codebase is heavily metadata-driven. Most master-data modules are defined in `app/Support/ResourceRegistry.php` and rendered through a shared generic CRUD controller and shared Blade views.

## 2. Technology Stack

### Backend

- PHP `^8.2`
- Laravel `^12.0`
- Eloquent ORM
- Laravel Sanctum
- Laravel Breeze authentication

### Frontend

- Blade-based admin and website templates
- Vite build pipeline
- Tailwind CSS available in the frontend toolchain
- React dependencies are installed in `package.json`, but the current admin/CMS implementation is primarily Blade-driven

### Storage and runtime assumptions

- MySQL-compatible relational database
- `public` disk for uploaded files
- Apache with `mod_rewrite`

## 3. High-Level Functional Scope

The platform covers six major domains.

### 3.1 Master data administration

- Regions
- Zones
- Woredas
- Organizations
- Professions
- Training categories
- Project categories

### 3.2 Training operations

- Project setup under the admin label `Projects`
- Project subawardee management as a one-to-many child list
- Training catalog management
- Training event planning and scheduling
- Event participant enrollment
- Workshop score entry and CSV import/export
- Final event report export

### 3.3 Participant management

- participant profile capture
- automatic participant code generation
- automatic DOB/age synchronization
- participant CSV import and export
- organization-aware hierarchical validation

### 3.4 Reporting and dashboards

- grouped training event views
- week/month/year training calendar
- calendar embed for use in CMS pages or external sites
- user-configurable dashboard tabs and widgets

### 3.5 Website and CMS

- public pages under `/pages/{slug}`
- homepage designation
- modular page sections and content blocks
- public navigation management
- appearance studio for branding and login page settings

### 3.6 Access control and governance

- users
- roles
- permission mapping
- activity logging
- admin sidebar configuration

## 4. Application URL Structure

### Public routes

- `/` : public homepage
- `/pages/{slug}` : public CMS page
- `/public/pages/{slug}` : compatibility route for public page access
- `/embed/training-events-calendar` : public calendar embed
- `/login` : administrator login

### Admin routes

All admin routes are prefixed with `/admin` and protected by authentication plus permission checks.

Key examples:

- `/admin`
- `/admin/pages`
- `/admin/appearance`
- `/admin/users`
- `/admin/roles`
- `/admin/training-events`
- `/admin/training-events-calendar`
- `/admin/training-workflow`
- `/admin/training-organizers` : exposed in the UI as `Projects`

## 5. Architectural Design

## 5.1 Metadata-driven CRUD

Most core resources are configured in `app/Support/ResourceRegistry.php`. Each resource defines:

- route path
- permission namespace
- label and singular label
- backing Eloquent model
- list columns
- form fields
- validation rules
- search fields
- eager-loaded relationships

The generic controller `app/Http/Controllers/ManagedResourceController.php` uses this configuration to power:

- listing
- search
- create/edit forms
- validation
- persistence
- file uploads
- relation syncing
- import/export endpoints for selected resources

This keeps the implementation consistent across modules and reduces duplicate CRUD code.

## 5.2 Specialized controllers

The following modules use dedicated controllers because their workflows are more complex:

- `TrainingWorkflowController`
- `TrainingEventsCalendarController`
- `DashboardController`
- `PageController`
- `AppearanceController`
- `RoleController`
- `UserController`

## 5.3 Public website rendering

The website is served by `WebsiteController` and `PageController`-managed content. Branding is centralized in `WebsiteSetting`.

## 6. Core Data Model

This section documents the main entities and their relationships.

## 6.1 Geography

### Region

- table: `regions`
- primary purpose: top-level geography

### Zone

- table: `zones`
- belongs to a region

### Woreda

- table: `woredas`
- belongs to a zone
- also stores a region reference

Hierarchy integrity is enforced in the generic resource controller.

## 6.2 Organizations

### Organization

- table: `organizations`
- linked to region, zone, and woreda
- stores category, type, city/town, phone, fax

The organization module is designed to support both manually curated master data and bulk CSV hierarchy imports.

## 6.3 Participants

### Participant

- table: `participants`
- stores:
  - generated `participant_code`
  - first, father, and grandfather names
  - full display name
  - DOB and age
  - gender
  - contact details
  - profession
  - region, zone, woreda, organization

Important model behavior in `app/Models/Participant.php`:

- the full `name` is rebuilt from name parts on save
- if DOB is provided, age is recalculated
- if age is provided but DOB is missing, DOB is estimated as July 1 of the derived birth year
- if `participant_code` is missing, a unique code is generated automatically

Participant code composition:

- first initial
- father initial
- grandfather initial
- DOB year
- DOB month
- last 4 digits of the mobile phone

If a collision occurs, the model appends a numeric suffix.

## 6.4 Projects and subawardees

### Training Organizer

Back-end model: `TrainingOrganizer`  
Admin label: `Projects`

Fields:

- `project_code` : required, unique
- `project_name` : required
- `title` : synchronized to match `project_name` for backward compatibility

### Project subawardee

- table: `project_subawardees`
- belongs to a project via `project_id`
- stores `subawardee_name`

Relationship:

- one project can have many subawardees

## 6.5 Training catalog

### Training category

- table: `training_categories`
- name, description, sort order, active status

### Training

- table: `trainings`
- belongs to a training category
- stores title, description, modality, and type

### Training material

- table: `training_materials`
- belongs to a training
- can store uploaded resource files and/or external URLs

## 6.6 Training events

### Training Event

- table: `training_events`
- belongs to:
  - training
  - project
  - optional project subawardee
  - optional training region

Key fields:

- `event_name`
- `training_id`
- `training_organizer_id`
- `organizer_type`
- `project_subawardee_id`
- `training_region_id`
- `training_city`
- `course_venue`
- `workshop_count`
- `start_date`
- `end_date`
- `status`

Organizer model in training events:

- `Project Name` identifies the parent project
- `Who organized the training` defines whether delivery is attributed to:
  - `The project`
  - `Subawardee`
- if `Subawardee` is selected, `Subawardee Name` becomes required and must belong to the selected project

## 6.7 Event enrollment and scores

### TrainingEventParticipant

- table: `training_event_participants`
- joins a participant to a training event
- stores:
  - final score
  - completion status
  - trainer flags and comments

Important behavior:

- after save/delete, event-level score aggregates are refreshed
- final score is derived from workshop post-test scores once all required workshops have a score

### TrainingEventWorkshopScore

- table: `training_event_workshop_scores`
- belongs to an event enrollment
- stores:
  - workshop number
  - pre-test score
  - mid-test score
  - post-test score

## 6.8 Projects module

Separate from training organizer projects, the app also has a `projects` module used for participant-linked project/coaching tracking.

It includes:

- project category
- linked participants
- coaching visit dates
- coaching visit notes
- uploaded project file

This is a distinct functional module and should not be confused with the `Projects` label used for training organizer records.

## 6.9 Website and administration

### ContentPage

- CMS page model
- title, slug, summary, body, status, homepage flag
- stores modular sections and flattened blocks

### WebsiteSetting

Centralized branding/settings record including:

- site name and tagline
- favicon
- header and footer branding
- colors
- radii
- login page text and styling
- custom CSS and JavaScript

### WebsiteMenuItem

- public site navigation model

### AdminSidebarMenuItem and AdminSidebarMenuSection

- configurable admin navigation

### DashboardTab and DashboardWidget

- per-user dashboard layout configuration

### User, Role, Permission

- authentication and authorization models

## 7. Admin Modules

The following admin resources are configured in `ResourceRegistry`.

### Geography and master data

- Regions
- Zones
- Woredas
- Organizations
- Professions
- Training Categories
- Project Categories

### Training domain

- Projects (`training-organizers`)
- Trainings
- Training Materials
- Training Events
- Event Participants
- Workshop Scores

### Project/coaching domain

- Projects (`projects`)

### Platform administration

- Pages
- Menus
- Sidebar Menus
- Appearance
- Environment Settings
- Users
- Roles
- CRUD Builder
- Dashboard
- User Activity Logs

## 8. Training Workflow

The dedicated workflow UI at `/admin/training-workflow` guides operations in four stages.

### Step 1: Training event

Create or select a training event.

### Step 2: Participant enrollment

Enroll one or more participants into the selected event.

### Step 3: Workshops

- set the workshop count
- capture pre-, mid-, and post-test scores per workshop
- import/export workshop scores via CSV

### Step 4: Report

Generate event-level reporting based on participant enrollment and workshop results.

The workflow computes progress such as:

- enrollment completion
- workshop completeness
- report readiness

## 9. Training Event List and Calendar

## 9.1 Grouped event view

The standard training event index groups similar event records by:

- normalized event name
- training
- project
- organizer type
- project subawardee

This prevents project-led and subawardee-led deliveries from being merged incorrectly.

The grouped list shows:

- event name
- training title
- project name
- organized by
- total events
- participants total
- average final score
- event date span

## 9.2 Calendar

The calendar is available at:

- admin: `/admin/training-events-calendar`
- public embed: `/embed/training-events-calendar`

Supported views:

- week
- month
- year

Calendar data includes:

- event title
- training
- project
- organized by
- venue
- date span
- status

The UI includes hover detail display for events.

## 9.3 Calendar embedding

The admin calendar provides iframe embed code for reuse in CMS pages or external sites.

CMS normalization logic strips problematic iframe sandbox attributes and normalizes relative embed URLs to full application URLs.

## 10. Import and Export Features

## 10.1 Organization hierarchy import/export

Routes:

- export: `/admin/organizations/export`
- import: `/admin/organizations/import`

Supported CSV columns include:

- `region`
- `zone`
- `woreda`
- `organization`
- `facility`

Behavior:

- creates missing regions, zones, and woredas when possible
- creates or updates organizations by name
- accepts `facility` as an alias of `organization`
- exports hierarchy plus additional organization metadata

Fallbacks for newly created organizations from hierarchy-only CSV:

- category defaults to `Private`
- type defaults to `Other (specify)`

There is also a CLI command:

```bash
php artisan organizations:import-hierarchy "D:\path\to\file.csv"
```

## 10.2 Participant import/export

Routes:

- export: `/admin/participants/export`
- import: `/admin/participants/import`

Participant export includes:

- participant code
- name parts
- DOB
- age
- gender
- phones
- email
- profession
- region/zone/woreda
- organization IDs and names

Participant import behavior:

- can match existing rows by `participant_code` or email
- resolves geography and organization by ID or name
- accepts either DOB or age
- preserves model-based participant ID generation by not forcing a code for new rows
- preserves the DOB/age synchronization logic defined on the model

## 10.3 Workshop score import/export

Routes:

- export: `/admin/training-workflow/events/{trainingEvent}/workshops/export`
- import: `/admin/training-workflow/events/{trainingEvent}/workshops/import`

The workshop CSV supports:

- participant ID or participant code matching
- pre/mid/post score columns
- blank rows for score clearing

## 10.4 Training report export

Route:

- `/admin/training-workflow/events/{trainingEvent}/report/export`

The CSV report includes:

- event metadata
- participant details
- organization details
- per-workshop score columns
- average pre/post scores
- final score

## 10.5 Dashboard layout import/export

Routes:

- `/admin/dashboard/layout/export`
- `/admin/dashboard/layout/import`

Used to move personal dashboard layouts between environments or users.

## 11. CMS and Public Website

## 11.1 Page management

Pages are managed under `/admin/pages`.

Each page supports:

- title and slug
- summary
- rich body content
- published/draft status
- homepage flag
- meta title
- modular sections/blocks

Only one page can be the homepage at a time.

## 11.2 Supported content block types

Defined in `app/Support/PageBlockRegistry.php`:

- hero
- rich text
- image
- stats
- quote
- call to action
- feature list
- gallery
- video embed
- dashboard block
- callout

These blocks are grouped inside higher-level sections and normalized before save.

## 11.3 Public navigation

Website menu items are managed in the admin UI and rendered on the public website.

Public header behavior is driven by appearance settings, including:

- site name
- tagline
- admin link visibility
- login link visibility
- header CTA

## 11.4 Appearance Studio

Appearance settings are edited at `/admin/appearance`.

Managed settings include:

- site name and site tagline
- favicon upload or URL
- header logo and CTA
- header/body/footer colors
- border radii
- footer contact/about content
- login page branding and text
- custom CSS and custom JavaScript
- login/admin link visibility

## 11.5 Login page customization

Login copy and styling are editable from Appearance Studio.

Editable content includes:

- eyebrow
- page title/subtitle
- form title/subtitle
- field labels
- submit/back labels
- three supporting feature lines
- background and card colors

## 12. Dashboards and Analytics

The dashboard system is user-specific and tab-based.

Features:

- multiple tabs per user
- configurable widgets
- widget ordering
- widget sizing and color schemes
- filter-driven metrics
- layout import/export

Widget execution is handled by `DashboardLayoutService` and `DashboardMetricsService`.

The CMS also supports embedding a live dashboard block inside public pages.

## 13. Roles, Permissions, and Activity Logging

## 13.1 Authentication

Authentication is handled by Laravel Breeze routes:

- `GET /login`
- `POST /login`
- `POST /logout`

## 13.2 Role-based access control

Users can have multiple roles via `role_assignments`.

Roles aggregate permissions via `role_permissions`.

Permission checks are used throughout the admin route layer:

- `resource.view`
- `resource.create`
- `resource.update`
- `resource.delete`

Examples:

- `training_events.view`
- `participants.create`
- `appearance.update`

## 13.3 User activity logs

Admin activity is tracked through `LogUserActivity` middleware and stored in `user_activity_logs`.

## 14. File Uploads

The application stores uploaded assets on the `public` disk, including:

- header logos
- footer logos
- favicons
- training materials
- project files

Developer requirement:

- run `php artisan storage:link` if the public storage symlink has not been created

## 15. Environment Setup

## 15.1 Prerequisites

- PHP 8.2+
- Composer
- Node.js and npm
- MySQL or compatible database
- Apache with `mod_rewrite`

## 15.2 Installation

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
npm run build
```

For local development:

```bash
composer dev
```

Or run the services individually as needed:

```bash
php artisan serve
npm run dev
```

## 15.3 Environment configuration

Key environment values:

- `APP_URL`
- database connection settings
- filesystem settings

For the current Laragon subdirectory deployment, the application is configured to work from a path like:

```text
http://localhost/test/hil-v2
```

Do not point `APP_URL` at `/public`; the repository root uses a front controller and rewrite rules that proxy requests into `public`.

## 16. Apache and Deployment Notes

This repository includes:

- root `index.php` that forwards to `public/index.php`
- root `.htaccess` for subdirectory routing into the Laravel public entrypoint
- `public/.htaccess` for standard Laravel request rewriting

This matters when the application is deployed under a subdirectory such as `/test/hil-v2`.

Requirements:

- `AllowOverride All`
- `mod_rewrite` enabled

If routes like `/login`, `/admin`, or `/pages/{slug}` return an Apache 404 before Laravel is reached, the first place to check is Apache rewrite configuration.

## 17. Extensibility

## 17.1 CRUD Builder

The CRUD builder at `/admin/crud-builders` can generate additional resource tables and admin CRUD screens dynamically.

It stores metadata in `generated_cruds` and injects those generated resources into `ResourceRegistry::all()`.

## 17.2 Resource registry extension

Static resources can be extended by editing:

- `app/Support/ResourceRegistry.php`

This is the right place to change:

- field definitions
- search behavior
- validation rules
- list columns
- eager-loaded relationships

## 17.3 Page block extension

CMS block types are defined in:

- `app/Support/PageBlockRegistry.php`
- `app/Support/PageSectionRegistry.php`

## 18. Known Operational Notes

### 18.1 MySQL-oriented migrations

The application is operationally oriented toward MySQL. At least one migration in the project history uses MySQL-style SQL syntax, so SQLite-based test environments may require adjustment before all migrations can run cleanly.

### 18.2 Large organization datasets

Large organization lists are handled with on-demand searching in participant forms to avoid loading the full organization table into the browser.

### 18.3 Geography consistency

Region/zone/woreda/organization integrity is validated in the generic resource controller. CSV imports that conflict with existing hierarchy definitions may be skipped instead of being forced into the database.

## 19. Important Source Files

Key files for future maintenance:

- `routes/web.php`
- `routes/auth.php`
- `routes/console.php`
- `app/Support/ResourceRegistry.php`
- `app/Support/PageBlockRegistry.php`
- `app/Http/Controllers/ManagedResourceController.php`
- `app/Http/Controllers/TrainingWorkflowController.php`
- `app/Http/Controllers/TrainingEventsCalendarController.php`
- `app/Http/Controllers/PageController.php`
- `app/Http/Controllers/AppearanceController.php`
- `app/Models/Participant.php`
- `app/Models/TrainingEvent.php`
- `app/Models/TrainingEventParticipant.php`
- `app/Models/TrainingOrganizer.php`
- `app/Models/WebsiteSetting.php`

## 20. Summary

The Amref Training Database is a combined training operations platform, public-facing CMS, and admin reporting system. Its main strengths are:

- metadata-driven CRUD management
- hierarchy-aware participant and organization data
- project and subawardee-aware training event attribution
- workshop-level performance tracking
- configurable dashboards
- branded public site and login experience
- embeddable training calendar

For onboarding, start with:

1. `routes/web.php`
2. `app/Support/ResourceRegistry.php`
3. `app/Http/Controllers/ManagedResourceController.php`
4. `app/Http/Controllers/TrainingWorkflowController.php`
5. `app/Http/Controllers/TrainingEventsCalendarController.php`
