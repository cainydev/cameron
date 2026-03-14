# Module: Diagnostic Frameworks
## Fetch Condition: Performance anomalies, sudden drops, bleeding spend detection, or troubleshooting protocols

### Sudden Drop Framework (24-48 Hour Crashes)

**CRITICAL**: Do NOT alter bidding strategies during dip. Changes reset algorithmic learning phase, exacerbating issue.

#### Stage 1: Tracking Integrity & Data Sync (Priority 1)
Leading cause of perceived crashes is mechanical breakdown, not strategy failure.

**Verification Checklist**:
- [ ] Query Google Ads API: All primary conversion actions active and verified?
- [ ] GCLID persistence across redirects confirmed?
- [ ] Enhanced conversions firing without data loss?
- [ ] Review Time Lag report: Is drop merely attribution delay? (7-14 day lag common in high-ticket)

#### Stage 2: Site Health & Environmental Friction
If tracking intact, friction is environmental.

**GA4 Diagnostic Query**:
- Sudden bounce rate spikes?
- Precipitous session duration drops on core landing pages?
- Localized 404 errors?
- Checkout script failures?
- Page load time &gt;3 seconds?

**Action**: If bounce rate spike correlates with ROAS drop → friction is on-site, not in-account. Alert operator immediately.

#### Stage 3: Auction Conditions & Impression Share Loss
If site health optimal, examine external market forces.

**Auction Insights Analysis**:
- New competitor entry?
- Existing competitor bid aggression?
- Sudden Search Impression Share loss?
- Unexpected CPC spikes?

**Decision Matrix**:
- Defensible territory + high lifetime value → Temporary bid increase proposal
- Contested territory + low differentiation → Pivot budget to alternative product categories

#### Stage 4: Search Intent Mismatch
If auction environment stable, evaluate query mapping.

**Search Terms Report Analysis**:
- Algorithm update or broad match expansion mapping to high-volume, low-intent queries?
- CTR stable/high but CVR plummeting to near-zero?
- **Diagnosis**: Ads attracting irrelevant, non-transactional traffic
- **Action**: Immediate negative keyword cluster extraction and application

---

### Bleeding Spend Framework (Cumulative Waste)

**Philosophy**: Wasted spend is insidious, often normalized. Routine, aggressive pruning required.

#### Search Term Level Pruning
- **Extraction**: Scrape Search Terms reports across all campaigns
- **Negation Trigger**: Cost &gt;1.5x average CPA without conversion
- **Match Type Strategy**: Aggressive exact match and phrase match exclusions

#### N-Gram Analysis (Root Word Immunization)
Beyond individual terms, identify toxic root words correlating with negative ROI across query variations.

**Common Toxic Roots**:
- "cheap", "used", "manual", "rental", "free", "DIY", "repair", "how to"

**Action**: Extract roots → Account-level negative keyword lists → Immunize entire architecture against future waste

#### Automated Defense
- **Script**: Weekly Search Terms report analysis with auto-negation suggestions
- **Threshold**: 7-day lookback, spend &gt;CPA threshold, zero conversions
- **Human Escalation**: Brand-adjacent terms or high-volume queries before negation

---

### Diagnostic Hierarchy Summary

| Symptom | First Check | Second Check | Third Check |
|---------|-------------|--------------|-------------|
| Sudden conversion drop | Tracking integrity | Site health (bounce rate) | Auction insights |
| Sudden ROAS drop | Time lag/attribution | Search term intent mismatch | Competitor aggression |
| Stable CTR, zero CVR | Search term relevance | Landing page alignment | Offer competitiveness |
| High CPC, low IS | Quality Score components | Competitor bid analysis | Budget constraint status |
