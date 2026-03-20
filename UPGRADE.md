# UPGRADE Guide

This document describes breaking changes and how to upgrade between major versions.

### From v1.9.6 to 1.10

Run this Command **once** when upgrading from v1.9.6 to v1.10 to migrate Product-ProductCategory relationship to ManyToMany:

```bash
php bin/console c975l:shop:migrate-category-many-to-many;
```

Then, run the following commands to update your database schema:

```bash
php bin/console make:migration;
php bin/console doctrine:migrations:migrate;
```
