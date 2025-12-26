BeMyHoney CRM

BeMyHoney CRM is a comprehensive inventory and order management system designed to manage all aspects of a business’s operations. This CRM handles products, orders, invoices, inbound and outbound shipments, and inventory tracking, providing a centralized platform for seamless business management.

The application is built using Laravel and Livewire:

Frontend: Laravel Blade templates integrated with Livewire components.

Backend: Laravel framework using Livewire for reactive and dynamic UI updates.

Hosting: Deployed on a VPS server for production usage.

Features

Manage all products and inventory.

Create, update, and track orders (inbound and outbound).

Generate and manage invoices.

Real-time inventory updates using Livewire.

Centralized order and stock management.

Technologies Used

PHP 8.x

Laravel 10.x

Livewire 3.x

MySQL or compatible database

Composer for dependency management

Node.js & npm for front-end assets

Getting Started / Local Setup

Follow these steps to run the application locally:

1. Clone the repository
git clone git@github.com:YOUR-USERNAME/BeMyHoney.git
cd BeMyHoney

2. Install PHP dependencies

Make sure Composer is installed:

composer install

3. Install Node dependencies (optional for compiling assets)
npm install
npm run dev   # or npm run build for production

4. Set up environment variables

Create a .env file from the example:

cp .env.example .env


Update the following in .env:

Database credentials: DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD

Any other API keys or credentials (ensure not to commit secrets)

5. Generate the application key
php artisan key:generate

6. Run database migrations
php artisan migrate


If you have seeders, you can also run:

php artisan db:seed

7. Start the development server
php artisan serve


The app should now be accessible at:
http://127.0.0.1:8000

Notes / Guidelines

Sensitive files like .env or API credentials must never be pushed to GitHub. Make sure they are included in .gitignore.

The /vendor and /node_modules folders should also be ignored.

To work with Livewire, ensure your PHP version and server configuration support the latest Laravel requirements.

Always run composer install and npm install after pulling updates from the repository.

For production deployment, configure your VPS with proper caching, queue, and storage setups as per Laravel best practices.
