# Fiscal Compliance Documentation

## Overview

This document outlines the fiscal compliance measures implemented in La Frenona 3 to ensure adherence to Spanish fiscal regulations including VeriFactu, TicketBAI, and SII (Suministro Inmediato de Información).

**IMPORTANT**: This is an internal fiscal compliance system. Legal validation by a qualified professional is required before production use.

## Current Implementation

### Fiscal Records

The system implements a cryptographically-secure fiscal record system:

- **Hash Chaining**: Each fiscal record includes a hash of the previous record
- **SHA-256 Encryption**: Cryptographic hash function for integrity verification
- **Immutable Records**: Fiscal records cannot be modified or deleted
- **Append-Only**: New records are always appended to the chain
- **Sequence Numbers**: Sequential numbering for audit trail

### Data Captured

Each fiscal record includes:
- Restaurant ID
- Order ID
- Sequence number
- Total amount
- Tax amount
- Currency
- Hash and previous hash
- Timestamp

### Verification

The system provides chain verification:
```php
$service = app(FiscalChainingService::class);
$isValid = $service->verifyChain($restaurantId);
```

## Compliance Requirements

### VeriFactu (Spain)

**Status**: ⚠️ PARTIAL - Requires professional validation

**Requirements**:
1. ✅ Immutable fiscal records
2. ✅ Cryptographic hash chain
3. ✅ Sequential numbering
4. ✅ Complete transaction data
5. ⏳ Official certification pending
6. ⏳ Integration with Agencia Tributaria

**Implementation Notes**:
- Records are stored in `fiscal_records` table
- Hash chain uses SHA-256
- Sequence numbers are per-restaurant
- Records include all required fields

### TicketBAI (Basque Country)

**Status**: ⏳ NOT IMPLEMENTED - Requires development

**Requirements**:
1. QR code generation
2. XML format compliance
3. Real-time submission
4. Cryptographic signatures
5. Integration with EAE system

**Implementation Plan**:
- Create QR code service
- Implement XML generation
- Add webhook for submissions
- Integrate with EAE API

### SII (Suministro Inmediato de Información)

**Status**: ⏳ NOT IMPLEMENTED - Requires development

**Requirements**:
1. Real-time invoice reporting
2. XML format (FacturaE)
3. Scheduled submissions
4. Error handling
5. Reconciliation

**Implementation Plan**:
- Create SII service
- Implement XML generation
- Add queue jobs for submissions
- Implement error handling

## Data Retention

### Operational Data
- **Retention**: 4 years (Spanish fiscal requirement)
- **Storage**: Database with backups
- **Archiving**: Annual archive to cold storage

### Fiscal Records
- **Retention**: Indefinite (legal requirement)
- **Storage**: Immutable database records
- **Backup**: Encrypted, off-site backups

### Customer Data
- **Retention**: Until deletion request
- **Anonymization**: GDPR compliant
- **Export**: Available on request

## Audit Trail

### What is Logged
- All fiscal records created
- All order modifications
- All payment transactions
- All user authentication attempts
- All configuration changes

### How it's Stored
- Database audit_logs table
- Append-only records
- Cryptographic verification
- Regular backups

## Legal Disclaimers

### Important Notices

1. **Not Legal Advice**: This system implements fiscal features but does not constitute legal compliance. Consult a fiscal professional.

2. **Regional Variations**: Fiscal requirements vary by region (VeriFactu for Spain, TicketBAI for Basque Country, etc.)

3. **Certification Required**: Many fiscal systems require official certification before production use.

4. **Professional Validation**: All fiscal implementations must be validated by a qualified fiscal professional.

5. **Regular Updates**: Fiscal regulations change. Regular reviews are necessary.

### Recommended Actions

1. ✅ Implement fiscal record system (DONE)
2. ✅ Add hash chain verification (DONE)
3. ⏳ Consult with fiscal professional
4. ⏳ Obtain necessary certifications
5. ⏳ Implement regional requirements
6. ⏳ Set up regular compliance reviews
7. ⏳ Train staff on fiscal procedures

## Contact

For fiscal compliance questions:
- Email: fiscal@lafrenona3.test
- Professional: [Your Fiscal Advisor]

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-08-05 | Initial implementation |
