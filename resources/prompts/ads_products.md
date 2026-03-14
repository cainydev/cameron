# Module: Product Portfolio Intelligence
## Fetch Condition: SKU-level analysis, product performance audits, inventory optimization, or feed management

### Zombie Product Protocol (The Bleeders)

**Definition**: Products winning auctions, generating clicks, consuming budget, failing to convert. Parasites on account MER.

**Identification Heuristic**:
- Rolling 30-60 day window
- 100+ clicks OR spend &gt;1.5x target CPA with zero conversions
- Cross-reference: click volume, accrued spend, conversion rate

**Mitigation Strategy** (NOT deletion from feed):
1. Route to "Low Priority" catch-all campaign via Custom Labels
2. Apply highly conservative tROAS targets (defensive posture)
3. Manual environments: Reduce Max CPC 30-50%
4. **Objective**: Force algorithm to bid only in cheapest, highest-intent auctions; neutralize budget bleed

### Hidden Gem Extraction

**Definition**: High view-to-purchase ratio or conversion rate, artificially suppressed impression volume. Proven product-market fit, algorithmically ignored.

**Detection Signals** (Beyond last-click ROAS):
- GA4 Assisted Conversion Value (early touchpoint attribution)
- Time Lag reports (multi-channel path presence)
- Micro-conversions: 15%+ Add-to-Cart rate with minimal spend
- Frequent appearance in conversion path early touchpoints

**Scaling Protocol**:
1. Extract from generic holding campaign
2. Place in dedicated "Exploration" or "Scaling" campaign
3. Lower tROAS constraint 15-20% below account average (aggressive bidding permission)
4. Monitor to 30-conversion threshold
5. Systematically raise tROAS to baseline profitability target

### Product Lifecycle Management

| Stage | Criteria | Action |
|-------|----------|--------|
| **Discovery** | New SKU, no data | 15% testing budget allocation, relaxed tROAS |
| **Validation** | 10-30 conversions, emerging ROAS pattern | Evaluate for Hidden Gem extraction |
| **Scaling** | 30+ conversions, ROAS &gt; target | Hero Product Spin-Out consideration |
| **Maintenance** | Stable performer | Core budget allocation (35% bucket) |
| **Defense** | Margin compression or seasonality ending | Conservative tROAS, reduced bids |
| **Sunset** | Zombie criteria met | Low Priority routing, feed exclusion if persistent |

### Feed Optimization via Custom Labels

**Active Management Required**:
- **Label 0-4**: Assign based on margin tier, performance cluster, seasonality, price competitiveness, price point
- **Update Frequency**: Weekly for performance clusters; monthly for margin/price; quarterly for structural attributes
- **Automation**: Script-based re-tagging based on rolling 30-day performance windows

### Cross-Reference Metrics
- **Merchant Center**: Product status, feed disapprovals, price competitiveness signals
- **GA4**: Shopping behavior analysis, checkout abandonment by product category
- **Backend CRM/Shopify**: True profitability post-refund/return (not just platform ROAS)
