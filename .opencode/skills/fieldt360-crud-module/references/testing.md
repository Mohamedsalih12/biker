# Testing Reference

Use the project's existing PHPUnit conventions, factories, test helpers and use WarehouseTest as reference. Do not weaken application security or validation to make a test pass.

## Authentication

* Guest cannot access protected routes.

## Authorization

* Authorized user can perform permitted actions; unauthorized user cannot.
* Manipulated IDs cannot reach another user's or unauthorized records.
* Unauthorized users cannot import, export, or bulk delete.

## Model

* Creation, update, relationships, casts, constraints, deletion using spatie/laravel-deleted-models.

## CRUD

* Index, search, filtering, pagination
* Create, validation failure, update, delete

## Bulk delete

* Valid selection, empty selection, invalid IDs
* Unauthorized records, protected records
* Successful deletion
* Transaction/failure behavior where applicable

## Import

* Valid file, invalid file, missing headings
* Invalid rows, duplicate rows, invalid foreign keys, validation failures
* Authorization
* All-or-nothing behavior (a bad row leaves no partial data)

## Export

* Correct headings and data
* Search/filter behavior
* Authorization
* Formula-injection protection where applicable

## Activity log

* Important successful operations create the expected activity logs.
* Failed operations do not create misleading success logs.



## Model Deletion

* Deleted entries captured in the deleted models.
* Restored entries recorded in the original table.

