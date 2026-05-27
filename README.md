# Kopafasta Microfinance System (Laravel)

This project is a full backend starter for a microfinance loan management platform built with Laravel 13.

## Implemented Core Modules

1. Self-Onboarding Portal
- Customer registration and login endpoints
- KYC submission endpoint
- Application tracking endpoint

2. Loan Products
- Product CRUD with rate, tenure, amount limits
- Collateral and guarantor rules
- Product-level workflow mapping

3. Applications
- Application CRUD
- Stage transition flow:
  - submitted -> screening -> credit_appraisal -> pre_approval -> approval -> disbursement
- Stage history audit trail

4. Customers
- Customer profile CRUD
- KYC and documents relationships
- Loan and application relationships

5. Vendor Management
- Vendor CRUD
- Vendor task assignment and completion
- Supports GPS, valuation, insurance, recovery use-cases

6. Loan Disbursement
- Disbursement CRUD
- Release action
- Supports channel and recipient model for customer/vendor/direct payments

7. Repayments
- Repayment recording
- Schedule lookup endpoint
- Outstanding balance updates when payments are posted

8. Arrears Management
- Arrear case listing and updates
- Follow-up action logging

9. Restructuring
- Restructure request CRUD
- Approval action with loan term/interest update

10. Reports & Analytics
- Portfolio
- Disbursement
- Repayment
- Arrears
- PAR
- Product performance
- Officer listing
- Vendor performance
- Customer risk

11. System Administration
- Users and role assignment
- System settings key/value store
- Audit log listing

## API Route Groups

- /api/auth
- /api/portal
- /api/customers
- /api/loan-products
- /api/loan-applications
- /api/vendors
- /api/vendor-tasks
- /api/loans
- /api/disbursements
- /api/repayments
- /api/arrears
- /api/restructures
- /api/reports/*
- /api/system/*

## Menu Structure Config

Menu structure is available in:
- config/microfinance_menu.php

## Setup

1. Install dependencies
- composer install

2. Configure environment
- cp .env.example .env
- php artisan key:generate

3. Configure database in .env

4. Run migrations
- php artisan migrate

5. Run server
- php artisan serve

## Important Notes

- This is a strong backend foundation and starter architecture.
- Add authentication hardening (Sanctum/JWT), policies, and granular permissions before production.
- Add request/response resources, events, queues, and tests for production use.
