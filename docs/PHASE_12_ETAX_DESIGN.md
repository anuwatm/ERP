# Phase 12: E-Tax & RD Filing Design

## Locked scope

- Generate organization-scoped XML for tax invoices, receipts, credit notes, and debit notes.
- Persist XML on the private `local` disk with a SHA-256 fingerprint and audit log.
- Keep certificate material outside the application. The configuration stores only a vault/KMS reference and expiry metadata.
- Queue provider submission attempts with retry history. The default adapter fails closed until a certified provider integration is installed.
- Export a pipe-delimited RD Prep staging text for `pnd3` and `pnd53`. It is deliberately labelled draft and must be verified against the current Revenue Department format before upload.
- Keep `pnd1` outside this phase because its authoritative source is the Phase 16 payroll domain.

## Compliance boundary

ETDA states that data sent to the Revenue Department for e-Tax Invoice/e-Receipt must be XML and that digital signatures apply to the relevant electronic documents. The implementation therefore separates source mapping, signing, and provider submission. It does not claim the internal `provider-mapping-v1` XML is a certified ETDA/RD submission schema without the provider's current XSD and certificate onboarding.

References: [ETDA e-Tax Invoice standard](https://www.etda.or.th/th/Our-Service/Standard/e-tax-Invoice-Receipt/Information/Our-Works/e-Tax-Invoice/e-Tax-Invoice.aspx), [ETDA FAQ](https://www.etda.or.th/th/contact/faq/e-Tax-Invoice-e-Receipt.aspx), [Revenue Department e-Filing](https://efiling.rd.go.th/).

## Status lifecycle

`generated -> signed -> submitted -> accepted | rejected`

The current default adapter may only generate XML. `signed`, `submitted`, and `accepted` require a configured, certified adapter; it cannot fabricate those states.

Once a document is `submitted` or `accepted`, regeneration is blocked. Corrections must be represented by a new credit note or debit note.

## Production activation prerequisites

1. Select and onboard a certified e-Tax provider.
2. Obtain that provider's current XSD, endpoint, authentication, and acceptance-test requirements.
3. Store signing key material in the provider or an approved vault/KMS, then configure only its reference and expiry metadata in ERP.
4. Replace the disabled signature and submission adapters, run provider acceptance tests, and only then enable `provider` mode.
