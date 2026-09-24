# Changelog

All notable changes to this library are documented here. It follows [Semantic Versioning](https://semver.org).

## 0.1.0 - Unreleased

First release.

- A client for every endpoint of the v1 API: account, charges, payment links, invoices and events.
- Typed resources, with every attribute the API sends kept and readable.
- Pages and an auto-paging iterator for lists.
- Automatic retries of network errors, rate limits and outages, with idempotency keys so a retried write is never carried out twice.
- An exception for each kind of API error.
- Webhook signature verification and parsing, for plain PHP and PSR-7 requests.
