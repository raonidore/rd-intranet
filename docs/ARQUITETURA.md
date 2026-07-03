# RD Intranet - Arquitetura

## Visão geral

A RD Intranet é uma plataforma administrativa da RD Tecnologia para gestão de infraestrutura, inicialmente com foco no módulo Samba.

## Stack

- Ubuntu Server 24.04 LTS
- Apache2
- PHP
- MariaDB
- Samba
- Composer
- Git
- Bootstrap 5
- Bootstrap Icons

## Estrutura principal

```text
app/
├── Components
├── Controllers
├── Core
├── Helpers
├── Jobs
├── Middleware
├── Models
├── Repositories
├── Services
└── Views

public/
├── index.php
├── .htaccess
└── assets/

routes/
└── web.php

docs/
